<?php

namespace App\Plugins\ExmentGroupware\Mail;

use App\Plugins\ExmentGroupware\SecretOption;
use Illuminate\Support\Facades\Http;

/**
 * Sign-in with Microsoft 365 and Google (OAuth 2.0, authorization code + PKCE).
 *
 * The user signs in on Microsoft's or Google's own page; Exment never sees
 * the password. What comes back is a token for IMAP and SMTP only
 * (XOAUTH2), kept encrypted and renewed with the refresh token.
 *
 * The system administrator registers the app once (Azure portal / Google
 * Cloud console) and enters its client ID and secret in the plugin's
 * settings, with the redirect URI the settings page shows. The secret is kept
 * encrypted there (SecretOption) and decrypted only here, for the token request.
 */
class OAuth
{
    /** @var \Exceedone\Exment\Model\Plugin */
    protected $plugin;

    /** @param \Exceedone\Exment\Model\Plugin $plugin */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /** @return array<string, string> */
    public function config(string $provider): array
    {
        if ($provider === Providers::MICROSOFT) {
            $tenant = trim(strval($this->plugin->getCustomOption('ms_tenant'))) ?: 'organizations';
            if (preg_match('/^[A-Za-z0-9.\-]+$/', $tenant) !== 1) {
                $tenant = 'organizations';
            }

            return [
                'client_id' => trim(strval($this->plugin->getCustomOption('ms_client_id'))),
                'client_secret' => strval(SecretOption::reveal($this->plugin->getCustomOption('ms_client_secret'))),
                'authorize' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize',
                'token' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token',
                'scope' => 'openid email profile offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
            ];
        }
        if ($provider === Providers::GMAIL) {
            return [
                'client_id' => trim(strval($this->plugin->getCustomOption('google_client_id'))),
                'client_secret' => strval(SecretOption::reveal($this->plugin->getCustomOption('google_client_secret'))),
                'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token' => 'https://oauth2.googleapis.com/token',
                'scope' => 'openid email https://mail.google.com/',
            ];
        }

        return [];
    }

    public function configured(string $provider): bool
    {
        $c = $this->config($provider);

        return !empty($c['client_id']) && !empty($c['client_secret']);
    }

    public function redirectUri(): string
    {
        return $this->plugin->getFullUrl('mail/oauth/callback');
    }

    /**
     * The URL of the provider's sign-in page. What to do on the way back is
     * kept in the session under a random state.
     *
     * @param array<string, mixed> $then account id to update, or the new account's settings
     */
    public function authorizeUrl(string $provider, array $then, ?string $loginHint = null): string
    {
        $c = $this->config($provider);
        if (empty($c['client_id'])) {
            throw new MailException('OAuth のアプリがまだ設定されていません。システム管理者に、プラグインの設定（' . Providers::get($provider)['short'] . ' のクライアント ID）を頼んでください。');
        }
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $pending = (array) session('gw_mail_oauth', []);
        // Keep only a few, so abandoned sign-ins do not pile up in the session.
        $pending = array_slice($pending, -4, null, true);
        $pending[$state] = ['provider' => $provider, 'verifier' => $verifier, 'then' => $then, 'at' => time()];
        session(['gw_mail_oauth' => $pending]);

        $query = [
            'client_id' => $c['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'scope' => $c['scope'],
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        if ($provider === Providers::GMAIL) {
            // A refresh token is only given with offline access and consent.
            $query['access_type'] = 'offline';
            $query['prompt'] = 'consent';
        } else {
            $query['prompt'] = 'select_account';
        }
        if ($loginHint) {
            $query['login_hint'] = $loginHint;
        }

        return $c['authorize'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Back from the provider: the code into tokens, and who signed in.
     *
     * @return array{provider: string, then: array<string, mixed>, email: string, name: string, tokens: array<string, string>, expires_at: string}
     */
    public function finish(string $state, string $code): array
    {
        $pending = (array) session('gw_mail_oauth', []);
        $entry = $pending[$state] ?? null;
        unset($pending[$state]);
        session(['gw_mail_oauth' => $pending]);
        if (!is_array($entry) || time() - intval($entry['at']) > 900) {
            throw new MailException('サインインの有効期限が切れました。もう一度やり直してください。');
        }
        $provider = strval($entry['provider']);
        $c = $this->config($provider);
        $res = Http::asForm()->timeout(20)->post($c['token'], [
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => strval($entry['verifier']),
        ]);
        $json = $res->json();
        if (!$res->successful() || empty($json['access_token'])) {
            throw new MailException('サインインできませんでした: ' . strval($json['error_description'] ?? ($json['error'] ?? $res->status())));
        }
        $claims = static::claims(strval($json['id_token'] ?? ''));
        $email = mb_strtolower(strval($claims['email'] ?? ($claims['preferred_username'] ?? ($claims['upn'] ?? ''))));
        if ($email === '' || strpos($email, '@') === false) {
            throw new MailException('サインインした人のメールアドレスがわかりませんでした。');
        }

        return [
            'provider' => $provider,
            'then' => (array) $entry['then'],
            'email' => $email,
            'name' => strval($claims['name'] ?? ''),
            'tokens' => [
                'access_token' => strval($json['access_token']),
                'refresh_token' => strval($json['refresh_token'] ?? ''),
            ],
            'expires_at' => now()->addSeconds(max(60, intval($json['expires_in'] ?? 3600)))->toDateTimeString(),
        ];
    }

    /**
     * A valid access token for the account, renewed when it is about to expire.
     */
    public function accessToken(object $account): string
    {
        $secret = Accounts::secret($account);
        $access = strval($secret['access_token'] ?? '');
        $expires = $account->token_expires_at ? strtotime(strval($account->token_expires_at)) : 0;
        if ($access !== '' && $expires > time() + 120) {
            return $access;
        }
        $refresh = strval($secret['refresh_token'] ?? '');
        if ($refresh === '') {
            throw new MailException('サインインの期限が切れました。アカウントの設定から、もう一度サインインしてください。');
        }
        $c = $this->config(strval($account->provider));
        $form = [
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
        ];
        if ($account->provider === Providers::MICROSOFT) {
            $form['scope'] = $c['scope'];
        }
        $res = Http::asForm()->timeout(20)->post($c['token'], $form);
        $json = $res->json();
        if (!$res->successful() || empty($json['access_token'])) {
            throw new MailException('サインインの期限が切れました。アカウントの設定から、もう一度サインインしてください（' . strval($json['error'] ?? $res->status()) . '）。');
        }
        $secret = [
            'access_token' => strval($json['access_token']),
            // Microsoft gives a new refresh token each time; Google keeps the old one.
            'refresh_token' => strval($json['refresh_token'] ?? $refresh),
        ];
        $expiresAt = now()->addSeconds(max(60, intval($json['expires_in'] ?? 3600)))->toDateTimeString();
        Accounts::update(intval($account->id), ['secret_enc' => Accounts::encryptSecret($secret), 'token_expires_at' => $expiresAt]);
        $account->secret_enc = Accounts::encryptSecret($secret);
        $account->token_expires_at = $expiresAt;

        return $secret['access_token'];
    }

    /**
     * The claims of an ID token. It came straight from the provider's token
     * endpoint over TLS, so it is read, not verified (OpenID Connect 3.1.3.7).
     *
     * @return array<string, mixed>
     */
    public static function claims(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return [];
        }
        $json = json_decode(strval(base64_decode(strtr($parts[1], '-_', '+/'))), true);

        return is_array($json) ? $json : [];
    }
}
