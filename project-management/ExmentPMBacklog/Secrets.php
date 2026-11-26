<?php

namespace App\Plugins\ExmentPMBacklog;

/**
 * A message as it may be kept, logged or shown: without a secret. The values given (the API key
 * of the connection) and the values of the query parameters and headers that carry one
 * (apiKey=, key=, token=, Authorization: ...) are replaced by "***". Plain PHP (tested by
 * project-management/tools/test-pmbl-secrets.php).
 */
class Secrets
{
    const MASK = '***';

    /**
     * @param array<int, string|null> $secrets the values to take out, as they are
     */
    public static function scrub(string $text, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            $secret = strval($secret);
            if (strlen($secret) >= 4) {
                $text = str_replace([$secret, rawurlencode($secret), urlencode($secret)], static::MASK, $text);
            }
        }
        // a query parameter that carries a secret
        $text = preg_replace('/([?&;](?:api_?key|apikey|key|token|access_token|refresh_token|password|secret)=)[^&\s#"\']*/i', '$1' . static::MASK, $text) ?? $text;
        // a header that carries one
        $text = preg_replace('/((?:authorization|x-api-key|x-cybozu-api-token)\s*[:=]\s*)(?:(?:bearer|basic|token)\s+)?[^\s,;"\']+/i', '$1' . static::MASK, $text) ?? $text;
        return $text;
    }
}
