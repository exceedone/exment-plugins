<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Sign-in with Google and Microsoft for the calendar (ISS02947, part 6): OAuth 2.0, the
 * authorization code with PKCE (S256), the way the web mail signs in (Mail\OAuth), with scopes
 * to read the calendar only:
 *
 *   Google     https://www.googleapis.com/auth/calendar.readonly
 *   Microsoft  https://graph.microsoft.com/Calendars.Read offline_access
 *
 * The site's own app: its client ID and secret are the plugin's settings (cal_google_client_id,
 * cal_google_client_secret, cal_ms_client_id, cal_ms_client_secret, cal_ms_tenant), the secrets
 * encrypted by Exment (encpassword). The redirect URI is plugins/<uri>/cal/oauth/callback.
 *
 * What a sign-in is for is kept in the session under a random state, with the person who began
 * it; the way back is refused for a state unknown, too old (15 minutes), used once already, or
 * begun by somebody else. The tokens (access and refresh) are kept encrypted in
 * gw_cal_links.secret_enc and never leave the server.
 */
class CloudOAuth
{
    const GOOGLE = 'google';
    const MICROSOFT = 'microsoft';
    const PROVIDERS = [self::GOOGLE, self::MICROSOFT];
    const SESSION = 'gw_cal_oauth';
    const STATE_SECONDS = 900;

    /** @var object|null the plugin (its settings and its URL) */
    protected $plugin;
    /** @var CloudFetch */
    protected $fetch;

    /** @param object|null $plugin \Exceedone\Exment\Model\Plugin (null: found by its name) */
    public function __construct($plugin = null, ?CloudFetch $fetch = null)
    {
        $this->plugin = $plugin ?? static::sitePlugin();
        $this->fetch = $fetch ?? new CloudFetch();
    }

    /** @return object|null */
    protected static function sitePlugin()
    {
        try {
            return \Exceedone\Exment\Model\Plugin::getPluginByName('ExmentGroupware');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetch(): CloudFetch
    {
        return $this->fetch;
    }

    protected function option(string $key): string
    {
        return $this->plugin !== null ? trim(strval($this->plugin->getCustomOption($key))) : '';
    }

    /** A secret of the settings: encrypted by Exment (encpassword); one that cannot be read is none. */
    protected function secretOption(string $key): string
    {
        $v = $this->option($key);
        if ($v === '') {
            return '';
        }
        try {
            return trim(strval(Crypt::decrypt($v)));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @return array{client_id: string, client_secret: string, authorize: string, token: string, scope: string} */
    public function config(string $provider): array
    {
        if ($provider === static::MICROSOFT) {
            $tenant = $this->option('cal_ms_tenant') ?: 'organizations';
            if (preg_match('/^[A-Za-z0-9.\-]{1,100}$/', $tenant) !== 1) {
                $tenant = 'organizations';
            }
            return [
                'client_id' => $this->option('cal_ms_client_id'),
                'client_secret' => $this->secretOption('cal_ms_client_secret'),
                'authorize' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize',
                'token' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token',
                'scope' => 'https://graph.microsoft.com/Calendars.Read offline_access',
            ];
        }
        return [
            'client_id' => $this->option('cal_google_client_id'),
            'client_secret' => $this->secretOption('cal_google_client_secret'),
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
        ];
    }

    public function configured(string $provider): bool
    {
        if (!in_array($provider, static::PROVIDERS, true)) {
            return false;
        }
        $c = $this->config($provider);
        return $c['client_id'] !== '' && $c['client_secret'] !== '';
    }

    public function redirectUri(): string
    {
        return $this->plugin !== null ? strval($this->plugin->getFullUrl('cal', 'oauth', 'callback')) : '';
    }

    /**
     * The address of the provider's sign-in page; what it is for is kept in the session.
     *
     * @param int|null $linkId the link to sign in again (reconnect), or null for a new one
     * @throws IcsFetchError not_configured
     */
    public function authorizeUrl(string $provider, int $uid, ?int $linkId = null, ?string $loginHint = null): string
    {
        if (!$this->configured($provider)) {
            throw new IcsFetchError('not_configured');
        }
        $c = $this->config($provider);
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $pending = (array) session(static::SESSION, []);
        // a few at most: sign-ins left halfway do not pile up in the session
        $pending = array_slice($pending, -4, null, true);
        $pending[$state] = ['provider' => $provider, 'uid' => $uid, 'link_id' => $linkId, 'verifier' => $verifier, 'at' => time()];
        session([static::SESSION => $pending]);
        $q = [
            'client_id' => $c['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'scope' => $c['scope'],
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        if ($provider === static::GOOGLE) {
            // a refresh token only with offline access and the consent asked again
            $q['access_type'] = 'offline';
            $q['prompt'] = 'consent';
        } else {
            $q['prompt'] = 'select_account';
        }
        if ($loginHint !== null && $loginHint !== '') {
            $q['login_hint'] = $loginHint;
        }
        return $c['authorize'] . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Back from the provider: the state checked (and used up), the code into tokens.
     *
     * @return array{provider: string, link_id: int|null, secret: array<string, mixed>}
     * @throws IcsFetchError oauth_state (unknown, old, another person's), oauth_token, or a code of CloudFetch
     */
    public function finish(int $uid, string $state, string $code): array
    {
        $pending = (array) session(static::SESSION, []);
        $entry = $state !== '' ? ($pending[$state] ?? null) : null;
        if ($entry !== null) {
            unset($pending[$state]);
            session([static::SESSION => $pending]);
        }
        if (!is_array($entry) || time() - intval($entry['at'] ?? 0) > static::STATE_SECONDS || intval($entry['uid'] ?? 0) !== $uid || $uid <= 0
            || !in_array(strval($entry['provider'] ?? ''), static::PROVIDERS, true) || $code === '') {
            throw new IcsFetchError('oauth_state');
        }
        $provider = strval($entry['provider']);
        $c = $this->config($provider);
        $res = $this->fetch->request('POST', $c['token'], [], [
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => strval($entry['verifier']),
        ]);
        $json = $res['json'] ?? [];
        if ($res['status'] !== 200 || empty($json['access_token']) || empty($json['refresh_token'])) {
            throw new IcsFetchError('oauth_token');
        }
        return [
            'provider' => $provider,
            'link_id' => isset($entry['link_id']) ? intval($entry['link_id']) : null,
            'secret' => static::tokens($json, ''),
        ];
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: int} */
    protected static function tokens(array $json, string $oldRefresh): array
    {
        return [
            'access_token' => strval($json['access_token'] ?? ''),
            // Microsoft gives a new refresh token each time; Google keeps the old one
            'refresh_token' => strval(($json['refresh_token'] ?? '') ?: $oldRefresh),
            'expires_at' => time() + max(60, intval($json['expires_in'] ?? 3600)),
        ];
    }

    // ---- the tokens of a link ---------------------------------------------------------

    /** @return array<string, mixed> the tokens of a link ([] when none, or when they cannot be read) */
    public static function secret(object $link): array
    {
        if (empty($link->secret_enc)) {
            return [];
        }
        try {
            $data = json_decode(Crypt::decryptString(strval($link->secret_enc)), true);
        } catch (\Throwable $e) {
            // APP_KEY changed: sign in again
            return [];
        }
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $secret */
    public static function encryptSecret(array $secret): string
    {
        return Crypt::encryptString(strval(json_encode($secret)));
    }

    /**
     * A valid access token of a link, renewed with its refresh token when it is about to end
     * (or $force: the API said 401). The new tokens are kept at once (Microsoft's refresh token
     * changes each time).
     *
     * @throws IcsFetchError auth (signed out, revoked: sign in again), or a code of CloudFetch
     */
    public function accessToken(object $link, bool $force = false): string
    {
        $secret = static::secret($link);
        $access = strval($secret['access_token'] ?? '');
        if (!$force && $access !== '' && intval($secret['expires_at'] ?? 0) > time() + 120) {
            return $access;
        }
        $refresh = strval($secret['refresh_token'] ?? '');
        $provider = strval($link->provider);
        if ($refresh === '' || !$this->configured($provider)) {
            throw new IcsFetchError($refresh === '' ? 'auth' : 'not_configured');
        }
        $c = $this->config($provider);
        $form = ['client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'grant_type' => 'refresh_token', 'refresh_token' => $refresh];
        if ($provider === static::MICROSOFT) {
            $form['scope'] = $c['scope'];
        }
        $res = $this->fetch->request('POST', $c['token'], [], $form);
        $json = $res['json'] ?? [];
        if ($res['status'] !== 200 || empty($json['access_token'])) {
            // the refresh token refused (revoked, expired, the password changed): sign in again;
            // the provider out of order: a try later
            if (in_array($res['status'], [400, 401], true)) {
                throw new IcsFetchError('auth');
            }
            throw new IcsFetchError('http_' . $res['status']);
        }
        $secret = static::tokens($json, $refresh);
        $enc = static::encryptSecret($secret);
        DB::table(Links::TABLE)->where('id', intval($link->id))->update(['secret_enc' => $enc]);
        $link->secret_enc = $enc;
        return $secret['access_token'];
    }

    /**
     * When a link is removed: Google's refresh token revoked (a single request, short); Microsoft
     * has no request to revoke one app's token, so it is only forgotten (the person can remove
     * the app's consent in their account). A failure is ignored: the tokens go with the link.
     */
    public function revoke(object $link): bool
    {
        if (strval($link->provider) !== static::GOOGLE) {
            return false;
        }
        $secret = static::secret($link);
        $token = strval(($secret['refresh_token'] ?? '') ?: ($secret['access_token'] ?? ''));
        if ($token === '') {
            return false;
        }
        try {
            $this->fetch->within(5);
            $res = $this->fetch->request('POST', 'https://oauth2.googleapis.com/revoke', [], ['token' => $token]);
            return $res['status'] === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
