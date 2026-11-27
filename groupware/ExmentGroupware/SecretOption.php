<?php

namespace App\Plugins\ExmentGroupware;

/**
 * A secret among the plugin's settings (custom_options), such as the client secret of an OAuth
 * app: kept encrypted, read decrypted only by the code that sends it (the token request).
 *
 * The stored text is encrypted the way ExmentPM keeps the secrets of its chat channels
 * (PortalChannels::encrypt):
 *
 *   enc:v1:<key id>:<...>   Exment's data keys (Exceedone\Exment\Services\Encryption, 7.3.0 and
 *                           later), when the site has a data key;
 *   app:<...>               else the app key (APP_KEY, Laravel's encrypt()).
 *
 * A value that Exment's own encpassword field saved (Laravel's encrypt() payload, without a
 * prefix) is read as well, so other settings may use this class too.
 *
 * Any other text is a value saved before it was encrypted (the plain text of 0.5.0 and before):
 * reveal() gives it as it is, and seal() encrypts it. seal() never encrypts an encrypted value
 * again, so the migration of the upgrade (2026_11_29_000000_encrypt_gw_mail_client_secrets)
 * and the save of the settings can both run it any number of times.
 */
class SecretOption
{
    const APP_PREFIX = 'app:';
    const DATA_PREFIX = 'enc:v1:';

    const ENCRYPTED = 'Exceedone\Exment\Services\Encryption\EncryptedValue';
    const DATA_KEYS = 'Exceedone\Exment\Services\Encryption\DataKeys';

    /** The web mail's OAuth client secrets (Mail\OAuth). */
    const MAIL_KEYS = ['ms_client_secret', 'google_client_secret'];

    /** Whether the stored text is encrypted (by this class or by Exment's encpassword). */
    public static function isSealed($value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        if (strpos($value, static::APP_PREFIX) === 0 || strpos($value, static::DATA_PREFIX) === 0) {
            return true;
        }

        return static::isLaravelPayload($value);
    }

    /**
     * The text to store: an encrypted value as it is, an empty one as null, any other text
     * encrypted.
     *
     * @param mixed $value
     */
    public static function seal($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = strval($value);
        if (static::isSealed($value)) {
            return $value;
        }
        if (trim($value) === '') {
            return null;
        }

        return static::encrypt(trim($value));
    }

    /**
     * The plain text of a stored value: null when there is none or it cannot be decrypted (for
     * example under another key). A value stored before it was encrypted is given as it is.
     *
     * @param mixed $value
     */
    public static function reveal($value): ?string
    {
        if (!is_scalar($value) || strval($value) === '') {
            return null;
        }
        $value = strval($value);
        try {
            if (strpos($value, static::APP_PREFIX) === 0) {
                return static::plainOrNull(decrypt(substr($value, strlen(static::APP_PREFIX))));
            }
            if (strpos($value, static::DATA_PREFIX) === 0) {
                if (!class_exists(static::ENCRYPTED)) {
                    return null;
                }
                return static::plainOrNull(call_user_func([static::ENCRYPTED, 'decrypt'], $value));
            }
            if (static::isLaravelPayload($value)) {
                return static::plainOrNull(decrypt($value));
            }
        } catch (\Throwable $e) {
            static::warn();
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Whether a value is stored (the screen shows 「設定済み」, never the value). */
    public static function has($value): bool
    {
        return is_scalar($value) && trim(strval($value)) !== '';
    }

    /**
     * The settings with the given secrets encrypted: what the migration of the upgrade writes.
     * The same settings again give the same result (nothing is encrypted twice).
     *
     * @param array<string, mixed> $options the plugin's custom_options
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public static function sealOptions(array $options, array $keys = self::MAIL_KEYS): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $options)) {
                continue;
            }
            $options[$key] = static::seal($options[$key]);
        }

        return $options;
    }

    /**
     * What the form saves for a secret: a value typed in, encrypted; nothing typed, the stored
     * value (encrypted, if it was not yet); nothing typed and "remove" checked, null.
     *
     * @param mixed $input what was typed
     * @param mixed $original what is stored
     */
    public static function submitted($input, $original, bool $clear = false): ?string
    {
        if (is_scalar($input) && trim(strval($input)) !== '') {
            return static::seal($input);
        }
        if ($clear) {
            return null;
        }

        return static::seal($original);
    }

    /**
     * The data keys when the site has one, else the app key (as ExmentPM's PortalChannels).
     */
    protected static function encrypt(string $plain): string
    {
        if (class_exists(static::ENCRYPTED) && class_exists(static::DATA_KEYS)) {
            try {
                if (call_user_func([static::DATA_KEYS, 'hasKey'])) {
                    return strval(call_user_func([static::ENCRYPTED, 'encrypt'], $plain));
                }
            } catch (\Throwable $e) {
                // the application key below
            }
        }

        return static::APP_PREFIX . encrypt($plain);
    }

    /** Laravel's encrypt() payload: base64 of a JSON with iv, value and mac. */
    protected static function isLaravelPayload(string $value): bool
    {
        if (strlen($value) < 40 || preg_match('/\A[A-Za-z0-9+\/]+=*\z/', $value) !== 1) {
            return false;
        }
        $json = json_decode(strval(base64_decode($value, true)), true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }

    /** @param mixed $plain */
    protected static function plainOrNull($plain): ?string
    {
        if (!is_scalar($plain)) {
            return null;
        }
        $plain = trim(strval($plain));

        return $plain === '' ? null : $plain;
    }

    protected static function warn(): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class) && \Illuminate\Support\Facades\Log::getFacadeRoot() !== null) {
                \Illuminate\Support\Facades\Log::warning('exment_groupware: a secret of the plugin settings could not be decrypted');
            }
        } catch (\Throwable $e) {
            // nothing to log to
        }
    }
}
