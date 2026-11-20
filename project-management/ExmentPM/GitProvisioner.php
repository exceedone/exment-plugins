<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\Log;

/**
 * The one place that knows how a requested repository would become a real
 * one on a Git server.
 *
 * The portal itself never talks to a Git server. It records the request
 * (a GitRepo row, status "pending") and hands it here. What happens next
 * depends on the deployment:
 *
 *  - Nothing configured (the default): the row stays pending. The settings
 *    screen says so, honestly, and nothing else happens. This is the state
 *    this plugin ships in - the UI and the data contract exist, the network
 *    call does not.
 *
 *  - A SaaS build sets config('exment.pmportal.git.provisioner') to a class
 *    with a provision(GitRepo $repo, array $settings): void method. That
 *    class does the HTTPS call and writes status ready / failed plus
 *    remote_url back onto the row. Pending rows can also be swept by a
 *    scheduled command calling provisionPending().
 *
 * Where the connection settings live, and why.
 *
 * Every one of them belongs to whoever runs the deployment, not to the
 * customer using the portal - so none of them has an editable field any
 * more. PluginSetting.php shows a read-only summary and that is all. Three
 * layers answer, in this order:
 *
 *  1. config('exment.pmportal.git.*'). A published config file is compiled
 *     into "artisan config:cache", so this layer is the only one that
 *     survives it. A deployment that caches its config and wants the values
 *     fixed has to use this one.
 *  2. env(). The names are PMPORTAL_GIT_* , plus GITEA_ORG for the
 *     organisation, which is the name a deployment declares it under. Note
 *     the cliff: after "artisan config:cache" Laravel stops reading .env and
 *     env() answers null for everything, which is why layer 3 exists.
 *  3. The plugin's custom options, in the plugins table. Nothing writes them
 *     from a browser now - storage/app/pmportal-dev/git-apply-env.php copies
 *     .env into them, and it parses .env itself for the same reason. So this
 *     layer holds the same values as layer 2 by construction, and keeps
 *     holding them on an installation whose config is cached.
 *
 * The token follows the same order and is never part of what a browser is
 * sent: only its presence, as has_token.
 */
class GitProvisioner
{
    /**
     * Keys a provider config consists of.
     *
     * What is not here any more, and why it is not a setting at all:
     *
     *  - git_private. Every repository this plugin creates is private, full
     *    stop. A switch that can be turned off is a public source tree one
     *    click away, and there is no deployment where that is wanted.
     *  - git_ssh_base. The SSH route is gone with it: a clone is HTTP with a
     *    per-repository deploy token, and the Git server needs no SSH port
     *    open at all.
     */
    protected const OPTION_KEYS = [
        'git_provider', 'git_api_url', 'git_namespace', 'git_clone_base',
    ];

    /**
     * Environment names per setting, most specific first.
     *
     * GITEA_ORG is the name a deployment declares the organisation under -
     * one customer, one organisation - and PMPORTAL_GIT_NAMESPACE stays
     * accepted so an installation configured before this does not have to be
     * touched.
     */
    protected const ENV_KEYS = [
        'provider' => ['PMPORTAL_GIT_PROVIDER'],
        'api_url' => ['PMPORTAL_GIT_API_URL'],
        'namespace' => ['GITEA_ORG', 'PMPORTAL_GIT_NAMESPACE'],
        'clone_base' => ['PMPORTAL_GIT_CLONE_BASE'],
        // Not one of OPTION_KEYS: token() reads it, so it never reaches
        // what settings() hands to a browser.
        'token' => ['PMPORTAL_GIT_API_TOKEN'],
    ];

    /** @var array<string, string>|null which layer answered, per setting */
    protected static $sources = null;

    /**
     * The drivers this plugin ships, by provider.
     *
     * A deployment config still wins over this (see provision), which is how
     * a SaaS build swaps in its own. It is the fallback rather than the only
     * way because config('exment.pmportal.*') cannot be set at all on an
     * install that has no published config/exment.php - which is every
     * self-hosted one.
     */
    protected const DRIVERS = [
        'gitea' => GiteaProvisioner::class,
    ];

    /** @var array<string, mixed>|null request-local cache of resolved settings */
    protected static $resolved = null;

    /**
     * Resolved connection settings, deployment config over plugin options.
     *
     * @param bool $withToken include the secret; only the provisioning call
     *                        itself may ask for it
     * @return array<string, mixed>
     */
    public static function settings(bool $withToken = false): array
    {
        if (static::$resolved === null) {
            $plugin = PortalDefinition::plugin();
            $out = [];
            $from = [];
            foreach (static::OPTION_KEYS as $key) {
                // "git_provider" -> config('exment.pmportal.git.provider')
                $short = substr($key, 4);
                $value = config('exment.pmportal.git.' . $short);
                $source = 'config';
                if (is_nullorempty($value)) {
                    $value = static::fromEnv($short);
                    $source = 'env';
                }
                if (is_nullorempty($value) && isset($plugin)) {
                    $value = $plugin->getCustomOption($key);
                    $source = 'db';
                }
                $out[$short] = is_nullorempty($value) ? null : strval($value);
                $from[$short] = isset($out[$short]) ? $source : 'unset';
            }
            $out['has_token'] = static::token() !== null;
            static::$resolved = $out;
            static::$sources = $from;
        }

        $settings = static::$resolved;
        if ($withToken) {
            $settings['token'] = static::token();
        }

        return $settings;
    }

    /**
     * @return string|null
     */
    public static function provider(): ?string
    {
        return array_get(static::settings(), 'provider');
    }

    /**
     * Whether a real provisioning call could be made at all: a provider, an
     * endpoint and a token. The settings screen turns this into "connected"
     * or "ask your administrator".
     *
     * @return bool
     */
    public static function configured(): bool
    {
        $settings = static::settings();

        return !is_nullorempty(array_get($settings, 'provider'))
            && !is_nullorempty(array_get($settings, 'api_url'))
            && boolval(array_get($settings, 'has_token'));
    }

    /**
     * Try to turn a requested row into a real repository.
     *
     * With no driver configured this is a no-op by design and the row stays
     * pending. A driver that throws marks the row failed and keeps the
     * message, so the settings screen can show what went wrong instead of a
     * silent forever-pending.
     *
     * @param GitRepo $repo
     * @return GitRepo the same row, refreshed
     */
    public static function provision(GitRepo $repo): GitRepo
    {
        $driver = static::driver();
        if (!isset($driver)) {
            return $repo;
        }

        try {
            $driver->provision($repo, static::settings(true));
        } catch (\Throwable $e) {
            $repo->status = GitRepo::STATUS_FAILED;
            // Not $e->getMessage(): last_error is shown on the project
            // settings tab, and an exception this plugin did not write
            // carries the API host and port in its text. See GitError.
            $repo->last_error = mb_substr(static::publicError($e, $repo->project_id), 0, 1000);
            $repo->save();
        }

        return $repo->refresh();
    }

    /**
     * Sweep every pending request. Meant for a scheduled command in a build
     * that ships a real driver, so a request made while the Git server was
     * down still completes later without anybody clicking again.
     *
     * @return int how many rows were looked at
     */
    public static function provisionPending(): int
    {
        $rows = GitRepo::where('status', GitRepo::STATUS_PENDING)->get();
        foreach ($rows as $row) {
            static::provision($row);
        }

        return count($rows);
    }

    /**
     * The driver for the configured provider, or null when this build has
     * none. Config wins over the shipped table so a deployment can replace
     * the driver without touching the plugin.
     *
     * @return object|null
     */
    protected static function driver()
    {
        $driver = config('exment.pmportal.git.provisioner');
        if (is_nullorempty($driver)) {
            $driver = array_get(static::DRIVERS, strval(static::provider()));
        }

        return is_string($driver) && $driver !== '' && class_exists($driver)
            ? app($driver)
            : null;
    }

    /**
     * Which layer answered for each setting: config, env, db, or unset.
     *
     * Only for the read-only summary on the plugin screen. It is the one
     * thing an operator cannot work out by looking: all three layers usually
     * hold the same value, and which one is in force decides whether
     * "artisan config:cache" or an edit to the plugins table could change it.
     *
     * @return array<string, string>
     */
    public static function sources(): array
    {
        static::settings();

        return (array) static::$sources;
    }

    /**
     * A message about a Git failure that is safe to show, and a log entry
     * holding the part that is not.
     *
     * Anything this plugin wrote (GitError) is passed through. Anything else
     * is replaced: a connection failure's own text names the API host, its
     * port and the organisation. What the caller gets instead is one
     * translated line and a six-character reference that appears in the log
     * beside the real reason.
     *
     * @param \Throwable $e
     * @param mixed $context project id, repository name - whatever helps in a log
     * @return string
     */
    public static function publicError(\Throwable $e, $context = null): string
    {
        if ($e instanceof GitError) {
            return $e->getMessage();
        }

        $ref = strtoupper(substr(md5(uniqid('pmportal', true)), 0, 6));
        Log::error('pm_portal git [' . $ref . '] ' . strval($context) . ': ' . $e->getMessage(), [
            'exception' => $e,
        ]);

        return PortalDefinition::trans('git_repo.remote_unavailable', ['ref' => $ref]);
    }

    /**
     * The first environment name that answers for one setting.
     *
     * @param string $short
     * @return string|null
     */
    protected static function fromEnv(string $short): ?string
    {
        foreach (array_get(static::ENV_KEYS, $short, []) as $name) {
            $value = env($name);
            if (!is_nullorempty($value)) {
                return strval($value);
            }
        }

        return null;
    }

    /**
     * The credentials registered against one repository.
     *
     * Credential management is optional for a driver: a provider this plugin
     * can only create repositories on answers "none" rather than breaking
     * the settings tab.
     *
     * @param GitRepo $repo
     * @return array<int, array<string, mixed>>
     */
    public static function keys(GitRepo $repo): array
    {
        $driver = static::driver();

        return isset($driver) && method_exists($driver, 'keys')
            ? $driver->keys($repo, static::settings(true))
            : [];
    }

    /**
     * Issue a credential that clones this one repository over HTTP.
     *
     * The plaintext value is in the returned array and is the only time it
     * exists: the Git server keeps a hash of it, so it cannot be read back.
     *
     * @param GitRepo $repo
     * @param string $title what the Git server should call it
     * @param bool $readOnly true to refuse pushes. The default, because a
     *                        caller that forgot to say should get the
     *                        credential that cannot overwrite history.
     * @return array<string, mixed> the credential, including 'token'
     * @throws GitError
     */
    public static function addToken(GitRepo $repo, string $title, bool $readOnly = true): array
    {
        $driver = static::driver();
        if (!isset($driver) || !method_exists($driver, 'addToken')) {
            throw new GitError(PortalDefinition::trans('git_repo.keys_unsupported'), 'unsupported');
        }

        return $driver->addToken($repo, $title, $readOnly, static::settings(true));
    }

    /**
     * Take one credential off the Git server. A credential the Git server
     * no longer has counts as taken off: the driver answers without
     * throwing, so the portal's record of it can go too.
     *
     * @param GitRepo $repo
     * @param int $keyId the id the Git server gave the key
     * @return void
     * @throws \Exception
     */
    public static function removeKey(GitRepo $repo, int $keyId): void
    {
        $driver = static::driver();
        if (!isset($driver) || !method_exists($driver, 'removeKey')) {
            throw new GitError(PortalDefinition::trans('git_repo.keys_unsupported'), 'unsupported');
        }

        $driver->removeKey($repo, $keyId, static::settings(true));
    }

    /**
     * The API token, in the same three layers as every other setting:
     * config, then PMPORTAL_GIT_API_TOKEN, then the plugin option.
     *
     * @return string|null
     */
    protected static function token(): ?string
    {
        $token = config('exment.pmportal.git.token');
        if (!is_nullorempty($token)) {
            return strval($token);
        }

        // The plugin screen tells an operator to put the token in
        // PMPORTAL_GIT_API_TOKEN; without this layer that advice left the
        // connection "unset" until the value was also copied into the
        // plugin options by hand.
        $token = static::fromEnv('token');
        if (!is_nullorempty($token)) {
            return $token;
        }

        $plugin = PortalDefinition::plugin();
        $stored = isset($plugin) ? $plugin->getCustomOption('git_api_token') : null;
        if (is_nullorempty($stored)) {
            return null;
        }

        // What sits in the plugin options is ciphertext (git-apply-env.php
        // encrypts it). trydecrypt answers null for anything it cannot
        // decrypt - a value written by hand in plain text, or one encrypted
        // under an APP_KEY that has since changed - and an unusable token is
        // no token: has_token and configured() must not report a connection
        // that every call would then refuse.
        $plain = trydecrypt(strval($stored));

        return is_nullorempty($plain) ? null : strval($plain);
    }
}
