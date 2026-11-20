<?php

namespace App\Plugins\ExmentPluginPM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a requested repository into a real one on a Gitea / Forgejo server.
 *
 * Gitea owns every repository through a user or an organisation, so the
 * namespace setting is not optional here: all of the portal's repositories
 * land in that one organisation, and the token has to be allowed to create
 * them there.
 *
 * The clone URL written back is deliberately NOT the one Gitea reports.
 * Gitea's own clone_url carries the owner segment
 * (http://wf.jp/git/pm/portal.git); what the portal hands a person is the
 * short form (http://wf.jp/git/portal), which the web server maps back onto
 * the owner for git's three HTTP endpoints. The short form is the one people
 * are given, so it is the one stored.
 */
class GiteaProvisioner
{
    /** Long enough for a cold Gitea to answer, short enough not to hold a save. */
    protected const TIMEOUT = 15;

    /** How much of a refusal's body goes into the log. */
    protected const LOG_BODY_CHARS = 500;

    /**
     * @param GitRepo $repo the request row, already saved as pending
     * @param array<string, mixed> $settings GitProvisioner::settings(true)
     * @return void
     * @throws GitError when the repository could not be created - the caller
     *                    puts the message on the row as last_error
     */
    public function provision(GitRepo $repo, array $settings): void
    {
        $api = rtrim(strval(array_get($settings, 'api_url', '')), '/');
        $org = trim(strval(array_get($settings, 'namespace', '')));
        $token = strval(array_get($settings, 'token', ''));
        $name = strval($repo->repo_name);

        if ($api === '' || $org === '' || $token === '') {
            throw new GitError(PortalDefinition::trans('git_repo.not_configured'), 'not_configured');
        }

        // Written on the repository so it can be recognised as this
        // project's own later: see adopt().
        $mark = $repo->ownerMark();

        $res = Http::withHeaders([
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/json',
            ])
            ->timeout(static::TIMEOUT)
            ->post($api . '/orgs/' . rawurlencode($org) . '/repos', [
                'name' => $name,
                'description' => strval($mark),
                // Not a setting. A portal repository holds a customer's own
                // source, and "private" is the only answer this plugin will
                // send - there is no deployment where a public one is wanted,
                // so there is no switch that could be left off.
                'private' => true,
                // An uninitialised repository has no branch at all, and
                // cloning one warns instead of working. auto_init is what
                // makes the URL in the settings screen a URL somebody can
                // actually use straight away.
                'auto_init' => true,
                'default_branch' => 'main',
            ]);

        if ($res->status() !== 201) {
            if ($res->status() === 409 && $this->adopt($repo, $settings, $mark)) {
                return;
            }
            static::logRefusal('create repository ' . $name, $res);

            throw new GitError(static::message($res, $name), 'remote_' . $res->status());
        }

        $this->markReady($repo, $settings, strval($res->json('clone_url')));
    }

    /**
     * Take over a repository that already carries this project's name, but
     * only when it provably is this project's own.
     *
     * The case this is for: Gitea creates the repository, the answer is lost
     * - a timeout on a cold server - and the row is marked failed. Every
     * retry under the same name is then answered 409, for ever, with the
     * repository sitting in the organisation. What proves it is ours is the
     * mark provision() wrote into its description, which names the project
     * row (GitRepo::ownerMark). A repository without it - another project's,
     * or one made by hand - is never taken: that is the 409 it always was.
     *
     * @param GitRepo $repo
     * @param array<string, mixed> $settings
     * @param string|null $mark
     * @return bool whether the repository was taken over
     */
    protected function adopt(GitRepo $repo, array $settings, ?string $mark): bool
    {
        if (is_nullorempty($mark)) {
            return false;
        }

        try {
            $res = static::api($settings, 'get', static::repoPath($repo, $settings));
        } catch (\Throwable $e) {
            return false;
        }
        if ($res->status() !== 200 || !str_contains(strval($res->json('description')), strval($mark))) {
            return false;
        }

        Log::info('pm_portal git: adopted existing repository ' . strval($repo->repo_name)
            . ' for project ' . strval($repo->project_id) . ' (created earlier under the same mark)');
        $this->markReady($repo, $settings, strval($res->json('clone_url')));

        return true;
    }

    /**
     * @param GitRepo $repo
     * @param array<string, mixed> $settings
     * @param string $reportedCloneUrl what Gitea calls it, for when no clone base is set
     * @return void
     */
    protected function markReady(GitRepo $repo, array $settings, string $reportedCloneUrl): void
    {
        $base = rtrim(strval(array_get($settings, 'clone_base', '')), '/');
        $repo->status = GitRepo::STATUS_READY;
        $repo->remote_url = $base !== '' ? $base . '/' . strval($repo->repo_name) : $reportedCloneUrl;
        $repo->last_error = null;
        $repo->provisioned_at = now();
        $repo->save();
    }

    /**
     * The deploy keys registered against one repository.
     *
     * A failure here is reported as an empty list rather than thrown: the
     * settings tab has to render even when the Git server is down, and the
     * repository state shown next to it already says so.
     *
     * @param GitRepo $repo
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    public function keys(GitRepo $repo, array $settings): array
    {
        try {
            $res = static::api($settings, 'get', static::repoPath($repo, $settings) . '/keys');
        } catch (\Throwable $e) {
            // Nothing configured to call, or the Git server did not answer
            // at all - Laravel's HTTP client throws on a refused connection
            // rather than handing back a response. Both are the case this
            // method promises to survive: the settings tab still has to
            // draw, and the repository state shown beside this list already
            // says the server is unreachable.
            //
            // It matters past that tab, too. postGitToken reads this list
            // after the Git server has issued a token, and the plaintext
            // token is in that one answer and nowhere else - so a throw
            // here would leave a credential nobody can see or revoke.
            return [];
        }
        if ($res->status() !== 200) {
            static::logRefusal('list keys of ' . strval($repo->repo_name), $res);

            return [];
        }

        $out = [];
        foreach ((array) $res->json() as $row) {
            // One endpoint returns both kinds of credential, and key_type is
            // what tells them apart: "ssh" is a public key, "token" is a
            // password for cloning over HTTP. Both are scoped to this one
            // repository, and both are removed the same way.
            $out[] = [
                'id' => intval(array_get($row, 'id')),
                'title' => strval(array_get($row, 'title')),
                'key_type' => strval(array_get($row, 'key_type', 'ssh')),
                'fingerprint' => strval(array_get($row, 'fingerprint')),
                'read_only' => boolval(array_get($row, 'read_only')),
                'created_at' => mb_substr(strval(array_get($row, 'created_at')), 0, 10),
            ];
        }

        return $out;
    }

    /**
     * A deploy token: the same idea as a deploy key, for HTTP instead of SSH.
     *
     * This exists because an HTTP clone has nothing else to present. Gitea
     * authenticates git over HTTP against its own user table, and an access
     * token carries the permissions of the user who owns it - so handing one
     * out would hand out every repository that user can reach. A deploy token
     * belongs to the repository, not to a user: it opens this repository and
     * answers "Repository not found" for any other.
     *
     * The plaintext token comes back once, on creation, and is never
     * retrievable afterwards. Whoever calls this has to show it immediately
     * or lose it.
     *
     * @param GitRepo $repo
     * @param string $title what it is called on the Git server
     * @param bool $readOnly true to refuse pushes
     * @param array<string, mixed> $settings
     * @return array<string, mixed> the row, plus the plaintext token
     * @throws GitError
     */
    public function addToken(GitRepo $repo, string $title, bool $readOnly, array $settings): array
    {
        $res = static::api($settings, 'post', static::repoPath($repo, $settings) . '/keys/tokens', [
            'title' => $title,
            'read_only' => $readOnly,
        ]);
        if ($res->status() !== 201) {
            // Only a refusal is logged. A 201 carries the plaintext token,
            // which must never reach a log.
            static::logRefusal('issue token on ' . strval($repo->repo_name), $res);

            throw new GitError(static::keyMessage($res), 'remote_' . $res->status());
        }

        // Created but with no token in the answer: there is nothing to show
        // and no way to ask for it again, so say so rather than present an
        // empty credential as a success.
        $token = strval($res->json('token'));
        if ($token === '') {
            throw new GitError(PortalDefinition::trans('git_repo.token_absent'), 'token_absent');
        }

        return [
            'id' => intval($res->json('id')),
            'title' => strval($res->json('title')),
            'key_type' => strval($res->json('key_type')),
            'read_only' => boolval($res->json('read_only')),
            'created_at' => mb_substr(strval($res->json('created_at')), 0, 10),
            'token' => $token,
        ];
    }

    /**
     * Take one credential off the repository.
     *
     * 404 counts as done. The credential is not there - revoked on the Git
     * server by hand, or with its repository - and that is what was asked
     * for. Treating it as a failure kept the portal's record alive, unlisted
     * and impossible to delete, and five such records locked a person out of
     * issuing a credential at all.
     *
     * @param GitRepo $repo
     * @param int $keyId
     * @param array<string, mixed> $settings
     * @return void
     * @throws GitError
     */
    public function removeKey(GitRepo $repo, int $keyId, array $settings): void
    {
        $res = static::api($settings, 'delete', static::repoPath($repo, $settings) . '/keys/' . $keyId);
        if ($res->status() === 204 || $res->status() === 404) {
            return;
        }

        static::logRefusal('revoke key ' . $keyId . ' on ' . strval($repo->repo_name), $res);

        throw new GitError(static::keyMessage($res), 'remote_' . $res->status());
    }

    /**
     * Where this repository lives on the Git server, owner and all. The
     * portal's own clone URL hides the owner; the API never does.
     *
     * @param GitRepo $repo
     * @param array<string, mixed> $settings
     * @return string
     */
    protected static function repoPath(GitRepo $repo, array $settings): string
    {
        $org = trim(strval(array_get($settings, 'namespace', '')));

        return '/repos/' . rawurlencode($org) . '/' . rawurlencode(strval($repo->repo_name));
    }

    /**
     * The one caller for the key endpoints.
     *
     * @param array<string, mixed> $settings
     * @param string $method get, post or delete
     * @param string $path below the API root
     * @param array<string, mixed> $body
     * @return \Illuminate\Http\Client\Response
     * @throws GitError when there is nothing configured to call
     */
    protected static function api(array $settings, string $method, string $path, array $body = [])
    {
        $api = rtrim(strval(array_get($settings, 'api_url', '')), '/');
        $org = trim(strval(array_get($settings, 'namespace', '')));
        $token = strval(array_get($settings, 'token', ''));
        if ($api === '' || $org === '' || $token === '') {
            throw new GitError(PortalDefinition::trans('git_repo.not_configured'), 'not_configured');
        }

        $http = Http::withHeaders([
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/json',
            ])
            ->timeout(static::TIMEOUT);

        return $method === 'post'
            ? $http->post($api . $path, $body)
            : $http->{$method}($api . $path);
    }

    /**
     * Put Gitea's own reason for a refusal in the log, where an operator can
     * read it. It never goes on a screen: Gitea quotes the path it was asked
     * for, which carries the API URL and the organisation. Never called for
     * a success - the answer that issues a token holds the token.
     *
     * @param string $what
     * @param \Illuminate\Http\Client\Response $res
     * @return void
     */
    protected static function logRefusal(string $what, $res): void
    {
        $body = trim(strval(preg_replace('/\s+/u', ' ', strval($res->body()))));

        Log::warning(sprintf(
            'pm_portal git: %s answered HTTP %d: %s',
            $what,
            $res->status(),
            mb_strimwidth($body, 0, static::LOG_BODY_CHARS, '...')
        ));
    }

    /**
     * Why a key call failed, in words the person who pasted it can act on.
     *
     * @param \Illuminate\Http\Client\Response $res
     * @return string
     */
    protected static function keyMessage($res): string
    {
        $status = $res->status();
        if ($status === 401 || $status === 403) {
            return PortalDefinition::trans('git_repo.remote_denied', ['status' => $status]);
        }

        // Gitea's own wording used to be relayed here, and it was the more
        // useful message - but it quotes the path it was asked for, so it
        // carries the API URL and the organisation onto a screen a project
        // member can open. The status is all that is left; the body goes to
        // the log through logRefusal().
        return PortalDefinition::trans('git_repo.key_failed', ['status' => $status]);
    }

    /**
     * What went wrong, in words the settings screen can show.
     *
     * 409 is the interesting one: Gitea answers it when the name is taken,
     * and that must not be read as success - the repository sitting there may
     * belong to another project entirely. Only a repository carrying this
     * project's mark is taken over (adopt). Nor is it the same as 422, which
     * is Gitea refusing the name itself.
     *
     * @param \Illuminate\Http\Client\Response $res
     * @param string $name
     * @return string
     */
    protected static function message($res, string $name): string
    {
        $status = $res->status();
        // 409 is the duplicate name. 422 is a name Gitea refuses outright,
        // which is a different problem - it falls through so the writer sees
        // the status rather than advice to rename.
        if ($status === 409) {
            return PortalDefinition::trans('git_repo.remote_taken', ['name' => $name]);
        }
        if ($status === 401 || $status === 403) {
            return PortalDefinition::trans('git_repo.remote_denied', ['status' => $status]);
        }
        if ($status === 404) {
            return PortalDefinition::trans('git_repo.remote_no_org');
        }

        // No response body, and no :detail parameter any more. Gitea's own
        // text quotes the path it was asked for, which is the API URL and the
        // organisation - and this string ends up on a settings tab. The body
        // is in the log instead, put there by logRefusal().
        return PortalDefinition::trans('git_repo.remote_failed', ['status' => $status]);
    }
}
