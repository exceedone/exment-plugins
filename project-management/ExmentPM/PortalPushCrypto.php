<?php

namespace App\Plugins\ExmentPM;

/**
 * The encryption and the signature of a Web Push message, with PHP's openssl only (no library).
 * See docs/pwa-design.md, step 2.
 *
 * - RFC 8291 (Message Encryption for Web Push), content coding aes128gcm (RFC 8188): ECDH on
 *   P-256 between a one-time key of the server and the browser's key (p256dh), HKDF-SHA-256
 *   with the browser's auth secret, AES-128-GCM, one record.
 * - RFC 8292 (VAPID): a JWT signed with ES256 by the site's key pair, in the Authorization
 *   header with the public key.
 *
 * Keys are raw where the protocol has them raw (the public key: 65 bytes, 0x04 || X || Y; the
 * private key: 32 bytes) and PEM where openssl needs them.
 */
class PortalPushCrypto
{
    /** SubjectPublicKeyInfo of a P-256 key (id-ecPublicKey, prime256v1), before the 65 bytes. */
    const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** The record size written in the header (RFC 8188). One record is enough for a notice. */
    const RECORD_SIZE = 4096;

    // ---- base64url -------------------------------------------------------

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $text): ?string
    {
        $text = trim($text);
        if ($text === '' || !preg_match('/^[A-Za-z0-9_\-+\/]+={0,2}$/', $text)) {
            return null;
        }
        $bin = base64_decode(strtr(rtrim($text, '='), '-_', '+/'), true);

        return $bin === false ? null : $bin;
    }

    // ---- keys ------------------------------------------------------------

    /** A raw public key (65 bytes) as a PEM openssl takes. */
    public static function publicPem(string $raw): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(strval(hex2bin(static::SPKI_PREFIX)) . $raw), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** A raw private key (32 bytes) and its public key as a PEM (SEC1, RFC 5915). */
    public static function privatePem(string $d, string $public): string
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20" . $d
            . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            . "\xa1\x44\x03\x42\x00" . $public;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** Whether a raw public key is a point of P-256 that openssl takes. */
    public static function validPublic(string $raw): bool
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            return false;
        }

        return openssl_pkey_get_public(static::publicPem($raw)) !== false;
    }

    /**
     * A new P-256 key pair.
     *
     * @return array{public: string, private: string} the raw public key and the private key's PEM
     */
    public static function newKeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new \RuntimeException('openssl could not make a P-256 key');
        }
        $details = openssl_pkey_get_details($key);
        $pem = '';
        if (!is_array($details) || !openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('openssl could not export the key');
        }

        return ['public' => static::rawPublic($details), 'private' => strval($pem)];
    }

    /** The raw public key of a private key's PEM, or null. */
    public static function publicOf(string $privatePem): ?string
    {
        $key = openssl_pkey_get_private($privatePem);
        $details = $key !== false ? openssl_pkey_get_details($key) : false;

        return is_array($details) && isset($details['ec']['x'], $details['ec']['y']) ? static::rawPublic($details) : null;
    }

    /** @param array<string, mixed> $details openssl_pkey_get_details of an EC key */
    protected static function rawPublic(array $details): string
    {
        return "\x04" . str_pad(strval($details['ec']['x']), 32, "\0", STR_PAD_LEFT) . str_pad(strval($details['ec']['y']), 32, "\0", STR_PAD_LEFT);
    }

    // ---- RFC 8291 ------------------------------------------------------

    /**
     * Encrypt a message for one browser (aes128gcm, one record).
     *
     * @param string $plain the message
     * @param string $uaPublic the browser's key (p256dh), raw 65 bytes
     * @param string $authSecret the browser's auth secret, 16 bytes
     * @param array{public: string, private: string}|null $ephemeral the server's one-time key pair (a test gives RFC 8291's)
     * @param string|null $salt 16 bytes (a test gives RFC 8291's)
     * @return string the body to POST
     */
    public static function encrypt(string $plain, string $uaPublic, string $authSecret, ?array $ephemeral = null, ?string $salt = null): string
    {
        if (!static::validPublic($uaPublic) || strlen($authSecret) !== 16) {
            throw new \InvalidArgumentException('not a key of a browser');
        }
        $ephemeral = $ephemeral ?? static::newKeyPair();
        $salt = $salt ?? random_bytes(16);
        $asPublic = $ephemeral['public'];

        $secret = openssl_pkey_derive(static::publicPem($uaPublic), $ephemeral['private'], 32);
        if ($secret === false || strlen($secret) !== 32) {
            throw new \RuntimeException('ECDH failed');
        }
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $tag = '';
        // the last (and only) record ends with the delimiter 0x02, no padding after it
        $cipher = openssl_encrypt($plain . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('AES-GCM failed');
        }

        return $salt . pack('N', static::RECORD_SIZE) . chr(strlen($asPublic)) . $asPublic . $cipher . $tag;
    }

    // ---- RFC 8292 ------------------------------------------------------

    /**
     * The Authorization header of a push: "vapid t=<JWT>, k=<public key>".
     *
     * @param string $endpoint the subscription's address (its origin is the audience)
     * @param string $subject the site's address (no e-mail address)
     * @param string $public the site's raw public key
     * @param string $privatePem the site's private key
     */
    public static function vapid(string $endpoint, string $subject, string $public, string $privatePem, ?int $now = null): string
    {
        $parts = parse_url($endpoint);
        $aud = strtolower(strval($parts['scheme'] ?? 'https')) . '://' . strtolower(strval($parts['host'] ?? ''))
            . (isset($parts['port']) && intval($parts['port']) !== 443 ? ':' . intval($parts['port']) : '');
        $claims = ['aud' => $aud, 'exp' => ($now ?? time()) + 12 * 3600, 'sub' => $subject];
        $input = static::b64u(strval(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])))
            . '.' . static::b64u(strval(json_encode($claims, JSON_UNESCAPED_SLASHES)));
        $der = '';
        if (!openssl_sign($input, $der, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('ES256 failed');
        }

        return 'vapid t=' . $input . '.' . static::b64u(static::derToRaw($der)) . ', k=' . static::b64u($public);
    }

    /** An ECDSA signature from DER (what openssl gives) to r || s, 64 bytes (what a JWT takes). */
    public static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = ltrim(substr($der, $pos + 2, $len), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    /** r || s back to DER (to check a JWT with openssl_verify). */
    public static function rawToDer(string $raw): string
    {
        $int = function (string $b) {
            $b = ltrim($b, "\0");
            if ($b === '' || (ord($b[0]) & 0x80)) {
                $b = "\0" . $b;
            }

            return "\x02" . chr(strlen($b)) . $b;
        };
        $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32, 32));

        return "\x30" . chr(strlen($body)) . $body;
    }
}
