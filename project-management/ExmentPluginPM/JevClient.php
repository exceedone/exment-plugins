<?php

namespace App\Plugins\ExmentPluginPM;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The one place that talks to TypeSafe's System One endpoint.
 *
 * The model is pinned on purpose: the jev-latest alias moves with every
 * release, and a moved alias changes answers while nothing in this code
 * base changed. The response's own model field is logged so a drift can be
 * seen in the log table.
 *
 * Where the key lives, and why: config('services.typesafe.key'), i.e. the
 * TYPESAFE_KEY environment variable, wins. The encrypted plugin option is a
 * fallback for an install whose operator cannot edit .env. The key is never
 * sent to a browser and never written to a log.
 */
class JevClient
{
    public const DEFAULT_MODEL = 'jev-1.13.0';

    public const DEFAULT_ENDPOINT = 'https://api.typesafe.ai/v1/systemone';

    /** USD per one million input tokens, from the published price list. */
    public const USD_PER_MILLION_TOKENS = 0.042;

    public static function configured(): bool
    {
        return static::key() !== null;
    }

    /**
     * Ask one set of questions about one state.
     *
     * @param array<string, mixed>|string $state
     * @param array<string, array<string, mixed>> $questions
     * @return array{answers: array<string, mixed>, model: string, usage: array<string, int>, latency_ms: int}
     * @throws JevException
     */
    public function ask($state, array $questions): array
    {
        $key = static::key();
        if ($key === null) {
            throw new JevException('not_configured', 0);
        }

        $started = microtime(true);
        try {
            $res = Http::withToken($key)
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(static::timeout())
                // One retry, only for the two codes TypeSafe documents as
                // transient. throw=false hands the final response back for
                // every other status so the reason can be named below.
                ->retry(2, 300, function ($e) {
                    $response = is_object($e) && isset($e->response) ? $e->response : null;
                    $status = is_object($response) && method_exists($response, 'status') ? $response->status() : 0;

                    return in_array($status, [429, 529], true);
                }, false)
                ->post(static::endpoint(), [
                    'model' => static::model(),
                    'state' => $state,
                    'questions' => $questions,
                ]);
        } catch (ConnectionException $e) {
            throw new JevException('unreachable', 0, $e);
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        if ($res->status() !== 200) {
            throw new JevException(static::reason($res->status()), $res->status());
        }

        $json = $res->json();
        if (!is_array($json) || !is_array($json['answers'] ?? null)) {
            throw new JevException('bad_response', 200);
        }

        $usage = [];
        foreach ((array) ($json['usage'] ?? []) as $k => $v) {
            if (is_numeric($v)) {
                $usage[strval($k)] = intval($v);
            }
        }

        return [
            'answers' => $json['answers'],
            'model' => strval($json['model'] ?? ''),
            'usage' => $usage,
            'latency_ms' => $ms,
        ];
    }

    public static function model(): string
    {
        $model = config('services.typesafe.model');
        if (is_nullorempty($model)) {
            $model = static::pluginOption('ai_model');
        }

        return is_nullorempty($model) ? self::DEFAULT_MODEL : strval($model);
    }

    public static function endpoint(): string
    {
        $endpoint = config('services.typesafe.endpoint');

        return is_nullorempty($endpoint) ? self::DEFAULT_ENDPOINT : strval($endpoint);
    }

    /** Seconds the whole call may take; the browser is waiting on it. */
    public static function timeout(): int
    {
        $timeout = intval(config('services.typesafe.timeout', 3));

        return $timeout > 0 ? $timeout : 3;
    }

    /**
     * Cost of a call in US dollars, from its input token count.
     */
    public static function costUsd(int $inputTokens): float
    {
        return $inputTokens * self::USD_PER_MILLION_TOKENS / 1000000;
    }

    protected static function reason(int $status): string
    {
        if ($status === 401 || $status === 403) {
            return 'unauthorized';
        }
        if ($status === 422) {
            return 'rejected';
        }
        if ($status === 429) {
            return 'rate_limited';
        }
        if ($status === 529) {
            return 'overloaded';
        }

        return 'bad_response';
    }

    /**
     * Environment config first, encrypted plugin option second, never logged.
     */
    protected static function key(): ?string
    {
        $key = config('services.typesafe.key');
        if (!is_nullorempty($key)) {
            return strval($key);
        }

        $stored = static::pluginOption('ai_api_key');
        if (is_nullorempty($stored)) {
            return null;
        }

        // The settings form saves this field through encpassword, so what
        // sits in the plugin options is ciphertext. trydecrypt answers null
        // for anything it cannot decrypt - a value written by hand in plain
        // text, or one encrypted under an APP_KEY that has since changed -
        // and that counts as no key at all.
        $plain = trydecrypt(strval($stored));

        return is_nullorempty($plain) ? null : strval($plain);
    }

    /**
     * @return mixed
     */
    protected static function pluginOption(string $key)
    {
        $plugin = PortalDefinition::plugin();

        return isset($plugin) ? $plugin->getCustomOption($key) : null;
    }
}
