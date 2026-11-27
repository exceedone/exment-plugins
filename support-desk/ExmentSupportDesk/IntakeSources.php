<?php

namespace App\Plugins\ExmentITSupport;

use App\Plugins\ExmentITSupport\Alerts\Adapters;
use App\Plugins\ExmentITSupport\Alerts\Rules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The senders of alerts (ISS02928): each monitoring tool has its own key,
 * which is shown once and kept only as a hash, can be turned off or deleted
 * alone, and can be bound to the sender's addresses and a signature.
 */
class IntakeSources
{
    const TABLE = 'its_intake_sources';

    public static function ready(): bool
    {
        return hasTable(static::TABLE);
    }

    /**
     * @return array<int, object>
     */
    public static function all(): array
    {
        return static::ready() ? DB::table(static::TABLE)->orderBy('id')->get()->all() : [];
    }

    public static function find(int $id): ?object
    {
        return static::ready() ? DB::table(static::TABLE)->where('id', $id)->first() : null;
    }

    /**
     * A new sender, off until it is turned on.
     *
     * @param array<string, mixed> $values
     * @return array{id: int, key: string, secret: string}
     */
    public static function create(string $name, string $kind, array $values = []): array
    {
        $key = 'its_' . bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(32));
        $id = intval(DB::table(static::TABLE)->insertGetId(array_merge(static::clean($values), [
            'name' => mb_substr(trim($name) ?: Adapters::KINDS[$kind] ?? $kind, 0, 100),
            'kind' => array_key_exists($kind, Adapters::KINDS) ? $kind : 'generic',
            'key_hash' => Tokens::hash($key),
            'key_hint' => substr($key, 0, 10),
            'secret_enc' => Crypt::encryptString($secret),
            'enabled' => false,
            'created_user_id' => optional(\Exment::user())->getUserId(),
            'created_at' => now(),
            'updated_at' => now(),
        ])));

        return ['id' => $id, 'key' => $key, 'secret' => $secret];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function update(int $id, array $values): void
    {
        DB::table(static::TABLE)->where('id', $id)->update(array_merge(static::clean($values), ['updated_at' => now()]));
    }

    /**
     * A new key (and signing secret) for the same sender: the old one stops at once.
     *
     * @return array{key: string, secret: string}
     */
    public static function renew(int $id): array
    {
        $key = 'its_' . bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(32));
        DB::table(static::TABLE)->where('id', $id)->update([
            'key_hash' => Tokens::hash($key),
            'key_hint' => substr($key, 0, 10),
            'secret_enc' => Crypt::encryptString($secret),
            'updated_at' => now(),
        ]);

        return ['key' => $key, 'secret' => $secret];
    }

    public static function delete(int $id): void
    {
        DB::table(static::TABLE)->where('id', $id)->delete();
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected static function clean(array $values): array
    {
        $out = [];
        if (array_key_exists('name', $values)) {
            $out['name'] = mb_substr(trim(strval($values['name'])), 0, 100) ?: '送り元';
        }
        foreach (['enabled', 'require_signature', 'link_asset'] as $k) {
            if (array_key_exists($k, $values)) {
                $out[$k] = boolval($values[$k]);
            }
        }
        if (array_key_exists('on_resolved', $values)) {
            $out['on_resolved'] = array_key_exists(strval($values['on_resolved']), Rules::ON_RESOLVED) ? strval($values['on_resolved']) : 'note';
        }
        if (array_key_exists('flap_minutes', $values)) {
            $out['flap_minutes'] = max(0, min(1440, intval($values['flap_minutes'])));
        }
        if (array_key_exists('allowed_ips', $values)) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', strval($values['allowed_ips'])) ?: [])));
            $out['allowed_ips'] = implode("\n", array_slice($lines, 0, 50));
        }

        return $out;
    }

    /** Whether every line of an address list is an address or a CIDR range. */
    public static function validIpList(string $list): bool
    {
        foreach (array_filter(array_map('trim', preg_split('/[\s,]+/', $list) ?: [])) as $range) {
            $parts = explode('/', $range, 2);
            if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
                return false;
            }
            if (isset($parts[1])) {
                $max = strpos($parts[0], ':') !== false ? 128 : 32;
                if (preg_match('/^\d+$/', $parts[1]) !== 1 || intval($parts[1]) > $max) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The key a request carries: the X-ITS-Key header, a bearer token, the
     * password of basic auth (for tools that only take a URL:
     * https://its:KEY@host/...), or ?key= in the URL.
     */
    public static function keyOf(Request $request): string
    {
        $key = strval($request->header('X-ITS-Key', ''));
        if ($key === '') {
            $key = strval($request->bearerToken() ?? '');
        }
        if ($key === '') {
            $key = strval($request->getPassword() ?? '');
        }
        if ($key === '') {
            $key = strval($request->query('key', ''));
        }

        return trim($key);
    }

    public static function byKey(string $key): ?object
    {
        if ($key === '' || strpos($key, 'its_') !== 0 || !static::ready()) {
            return null;
        }

        return DB::table(static::TABLE)->where('key_hash', Tokens::hash($key))->first();
    }

    public static function secretOf(object $source): string
    {
        try {
            return empty($source->secret_enc) ? '' : Crypt::decryptString($source->secret_enc);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function touch(object $source): void
    {
        DB::table(static::TABLE)->where('id', $source->id)->update([
            'last_used_at' => now(),
            'use_count' => DB::raw('use_count + 1'),
        ]);
    }

    /**
     * The settings of the old shared key, for the alerts it still takes:
     * as before, every alert is a problem (none resolves a ticket).
     *
     * @return array{kind: string, on_resolved: string, flap_minutes: int, link_asset: bool, legacy: bool}
     */
    public static function legacy(): array
    {
        return ['kind' => 'generic', 'on_resolved' => 'note', 'flap_minutes' => 0, 'link_asset' => false, 'legacy' => true];
    }

    /**
     * @return array{kind: string, on_resolved: string, flap_minutes: int, link_asset: bool, legacy: bool}
     */
    public static function options(object $source): array
    {
        return [
            'kind' => strval($source->kind),
            'on_resolved' => strval($source->on_resolved),
            'flap_minutes' => intval($source->flap_minutes),
            'link_asset' => boolval($source->link_asset),
            'legacy' => false,
        ];
    }
}
