<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;

/**
 * Where Web Push keeps its rows: the browsers that take the notices (pm_portal_push_subs) and
 * the site's key pair (pm_portal_push_keys, one row). PortalPush works through this class only,
 * so a test gives an in-memory one.
 *
 * A subscription row: user_id, endpoint, endpoint_hash (sha256 of the endpoint, unique),
 * p256dh, auth, device, failures, last_sent_at, created_at, updated_at.
 */
class PortalPushStore
{
    const SUBS = 'pm_portal_push_subs';
    const KEYS = 'pm_portal_push_keys';

    public function exists(): bool
    {
        try {
            return \Schema::hasTable(static::SUBS) && \Schema::hasTable(static::KEYS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> the person's browsers, the newest first */
    public function forUser(int $userId): array
    {
        return array_map(function ($r) {
            return (array) $r;
        }, DB::table(static::SUBS)->where('user_id', $userId)->orderByDesc('id')->get()->all());
    }

    /** @return array<string, mixed>|null */
    public function byHash(string $hash): ?array
    {
        $row = DB::table(static::SUBS)->where('endpoint_hash', $hash)->first();

        return $row === null ? null : (array) $row;
    }

    /** @param array<string, mixed> $row */
    public function insert(array $row): int
    {
        $now = date('Y-m-d H:i:s');

        return intval(DB::table(static::SUBS)->insertGetId(array_merge($row, ['created_at' => $now, 'updated_at' => $now])));
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        DB::table(static::SUBS)->where('id', $id)->update(array_merge($values, ['updated_at' => date('Y-m-d H:i:s')]));
    }

    public function delete(int $id): void
    {
        DB::table(static::SUBS)->where('id', $id)->delete();
    }

    public function deleteAll(): int
    {
        return intval(DB::table(static::SUBS)->delete());
    }

    /** @return array{public_key: string, private_enc: string}|null */
    public function keys(): ?array
    {
        $row = DB::table(static::KEYS)->orderByDesc('id')->first();

        return $row === null ? null : ['public_key' => strval($row->public_key), 'private_enc' => strval($row->private_enc)];
    }

    /**
     * Run $f under the lock of the key pair (Laravel's cache lock), so two requests do not make
     * two pairs. A cache without locks: run as it is (putKeys keeps one row either way).
     *
     * @return mixed what $f gives
     */
    public function locked(callable $f)
    {
        try {
            $lock = \Illuminate\Support\Facades\Cache::lock('pm_portal_push_keys', 30);
        } catch (\Throwable $e) {
            return $f();
        }

        return $lock->block(10, $f);
    }

    /** One pair only: the old one goes. */
    public function putKeys(string $public, string $privateEnc): void
    {
        $now = date('Y-m-d H:i:s');
        DB::transaction(function () use ($public, $privateEnc, $now) {
            DB::table(static::KEYS)->delete();
            DB::table(static::KEYS)->insert(['public_key' => $public, 'private_enc' => $privateEnc, 'created_at' => $now, 'updated_at' => $now]);
        });
    }
}
