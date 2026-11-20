<?php

namespace App\Plugins\ExmentGroupware\Mail;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mail accounts of the signed-in user (table gw_mail_accounts).
 *
 * An account is only ever read through its owner: every lookup takes the
 * user id, so one user cannot open another user's mailbox by changing the
 * number in a URL.
 */
class Accounts
{
    const TABLE = 'gw_mail_accounts';

    public static function ready(): bool
    {
        try {
            return Schema::hasTable(static::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function currentUserId(): ?int
    {
        $user = \Exment::user();
        if (!isset($user)) {
            return null;
        }
        $id = intval($user->base_user_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return array<int, object> */
    public static function forUser(int $userId): array
    {
        return DB::table(static::TABLE)->where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get()->all();
    }

    public static function find(int $id, int $userId): ?object
    {
        $row = DB::table(static::TABLE)->where('id', $id)->where('user_id', $userId)->first();

        return $row ?: null;
    }

    /** @param array<string, mixed> $values */
    public static function create(int $userId, array $values): int
    {
        $values['user_id'] = $userId;
        $values['sort_order'] = intval(DB::table(static::TABLE)->where('user_id', $userId)->max('sort_order')) + 1;
        $values['created_at'] = now();
        $values['updated_at'] = now();

        return intval(DB::table(static::TABLE)->insertGetId($values));
    }

    /** @param array<string, mixed> $values */
    public static function update(int $id, array $values): void
    {
        $values['updated_at'] = now();
        DB::table(static::TABLE)->where('id', $id)->update($values);
    }

    public static function delete(int $id, int $userId): void
    {
        DB::table(static::TABLE)->where('id', $id)->where('user_id', $userId)->delete();
    }

    // ---- secrets -----------------------------------------------------------

    /**
     * The password, or for OAuth ['access_token', 'refresh_token'].
     *
     * @return array<string, string>
     */
    public static function secret(object $account): array
    {
        if (empty($account->secret_enc)) {
            return [];
        }
        try {
            $data = json_decode(Crypt::decryptString(strval($account->secret_enc)), true);
        } catch (\Throwable $e) {
            // APP_KEY changed: the user signs in again.
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /** @param array<string, string> $secret */
    public static function encryptSecret(array $secret): string
    {
        return Crypt::encryptString(strval(json_encode($secret)));
    }

    // ---- for the screen ----------------------------------------------------

    /** What the browser may see: never the secret. */
    public static function toPublic(object $a): array
    {
        $p = Providers::get(strval($a->provider));

        return [
            'id' => intval($a->id),
            'name' => strval($a->name),
            'email' => strval($a->email),
            'from_name' => strval($a->from_name ?? ''),
            'provider' => strval($a->provider),
            'provider_label' => strval($p['short']),
            'auth_type' => strval($a->auth_type),
            'signature' => strval($a->signature ?? ''),
            'last_error' => $a->last_error ? strval($a->last_error) : null,
            'needs_signin' => $a->auth_type === 'oauth' && empty($a->secret_enc),
        ];
    }

    public static function recordError(int $id, ?string $error): void
    {
        $values = ['last_error' => $error === null ? null : mb_substr($error, 0, 500)];
        if ($error === null) {
            $values['last_connected_at'] = now();
        }
        DB::table(static::TABLE)->where('id', $id)->update($values);
    }
}
