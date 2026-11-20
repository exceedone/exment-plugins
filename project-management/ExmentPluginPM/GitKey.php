<?php

namespace App\Plugins\ExmentPluginPM;

use Exceedone\Exment\Model\ModelBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The portal's record of who issued a credential on a repository.
 *
 * The Git server owns the credential itself - it exists there, it is checked
 * there, it is revoked there. What the Git server does not keep is an owner
 * the portal can trust: a deploy key or deploy token carries a title, and a
 * title is whatever the caller typed. Deciding "may this person revoke this
 * credential" against that string would decide nothing, so the owner is
 * written here at the moment of issue and read back from here.
 *
 * A credential the Git server lists with no row here is ownerless - created
 * by hand, or before this table existed - and only a project administrator
 * may remove it.
 *
 * @property mixed $git_repo_id
 * @property mixed $provider_key_id
 * @property mixed $key_type
 * @property mixed $title
 * @property mixed $created_user_id
 * @phpstan-consistent-constructor
 */
class GitKey extends ModelBase
{
    public const TYPE_SSH = 'ssh';
    public const TYPE_TOKEN = 'token';

    /**
     * How many HTTP credentials one person may hold on one repository.
     *
     * Five covers a laptop, a desktop, a build agent and a spare, and stops
     * a page that issues them in a loop from leaving a list nobody can
     * audit. Revoking one frees a slot, so this is a ceiling on credentials
     * alive at once and not on how many may ever be issued.
     */
    public const TOKENS_PER_USER = 5;

    /**
     * The part of a title the portal writes itself ("portal user 12
     * 261015-093000"), ahead of the label the person gave. It keeps titles
     * unique on the Git server and means nothing on the screen.
     */
    protected const GENERATED_TITLE = '/\A(?:portal user \d+ \d{6}-\d{6})\s*/u';

    protected $table = 'pm_portal_git_keys';

    protected $guarded = ['id'];

    /**
     * Every record for one repository, keyed by the id the Git server gave -
     * which is the shape the caller needs, because it merges these against a
     * list that comes back from that server.
     *
     * @param GitRepo $repo
     * @return array<int, static>
     */
    public static function ofRepo(GitRepo $repo): array
    {
        $out = [];
        foreach (static::where('git_repo_id', intval($repo->id))->get() as $row) {
            $out[intval($row->provider_key_id)] = $row;
        }

        return $out;
    }

    /**
     * How many HTTP credentials this person already holds on this
     * repository.
     *
     * Counted from the portal's own record rather than from the Git server's
     * list, because the Git server does not know who issued what: a deploy
     * token carries a title, and a title is whatever the caller typed.
     *
     * @param GitRepo $repo
     * @param mixed $userId
     * @return int
     */
    public static function tokenCount(GitRepo $repo, $userId): int
    {
        if (is_nullorempty($userId)) {
            return 0;
        }

        return static::where('git_repo_id', intval($repo->id))
            ->where('key_type', static::TYPE_TOKEN)
            ->where('created_user_id', $userId)
            ->count();
    }

    /**
     * The title a token is issued under: who and when, so that two presses
     * never collide on the Git server, and then the person's own label.
     *
     * @param mixed $userId
     * @param string $label
     * @return string
     */
    public static function tokenTitle($userId, string $label): string
    {
        $label = trim(strval(preg_replace('/\s+/u', ' ', $label)));

        return 'portal user ' . strval($userId) . ' ' . date('ymd-His')
            . ($label === '' ? '' : ' ' . mb_substr($label, 0, 40));
    }

    /**
     * What of a recorded credential's title is worth showing: the label the
     * person typed, without the part the portal generated.
     *
     * @param string $title
     * @return string
     */
    public static function labelOf(string $title): string
    {
        return trim(strval(preg_replace(static::GENERATED_TITLE, '', $title)));
    }

    /**
     * Issue one HTTP credential to the signed-in user, inside the per-user
     * ceiling.
     *
     * Counting and creating are one step. Counted first and created after,
     * two presses that arrived together both saw four and both made a fifth
     * and a sixth. The repository row is locked for the whole step, so the
     * second press waits for the first to be recorded and then counts it.
     * The lock is held across the Git server's answer, which is short; it
     * only ever makes another credential request on the same repository
     * wait.
     *
     * When the Git server made the credential but the record could not be
     * written, the credential is taken back off the Git server: one nobody
     * owns could only be found and removed by an administrator.
     *
     * @param GitRepo $repo
     * @param string $title
     * @param bool $readOnly
     * @return array<string, mixed> the credential, including the plaintext 'token'
     * @throws GitError when the repository is not ready or the ceiling is reached
     * @throws \Throwable whatever the Git server's driver throws
     */
    public static function issueToken(GitRepo $repo, string $title, bool $readOnly): array
    {
        $userId = \Exment::getUserId();
        $issued = null;

        try {
            return DB::transaction(function () use ($repo, $userId, $title, $readOnly, &$issued) {
                $locked = GitRepo::whereKey($repo->id)->lockForUpdate()->first();
                if (!isset($locked) || $locked->status !== GitRepo::STATUS_READY) {
                    throw new GitError(PortalDefinition::trans('git_repo.keys_no_repo'), 'no_repo');
                }
                if (static::tokenCount($locked, $userId) >= static::TOKENS_PER_USER) {
                    throw new GitError(PortalDefinition::trans('git_repo.token_too_many', [
                        'max' => static::TOKENS_PER_USER,
                    ]), 'token_too_many');
                }

                $issued = GitProvisioner::addToken($locked, $title, $readOnly);
                static::record($locked, intval(array_get($issued, 'id')), static::TYPE_TOKEN,
                    strval(array_get($issued, 'title')));

                return $issued;
            });
        } catch (\Throwable $e) {
            $keyId = is_array($issued) ? intval(array_get($issued, 'id')) : 0;
            if ($keyId > 0) {
                try {
                    GitProvisioner::removeKey($repo, $keyId);
                } catch (\Throwable $undo) {
                    Log::error(sprintf(
                        'pm_portal git: credential %d on repository %s was issued but not recorded, and could not be revoked again',
                        $keyId,
                        strval($repo->repo_name)
                    ), ['exception' => $undo]);
                }
            }

            throw $e;
        }
    }

    /**
     * Record a credential the Git server has just created.
     *
     * The owner is not a parameter on purpose. ModelBase sets
     * created_user_id on creating, from \Exment::getUserId(), and it
     * overwrites whatever was assigned before - so an owner passed in here
     * would be silently discarded. Taking it from the session is also the
     * safer of the two: there is no argument for a caller to influence.
     *
     * Issued from the console, where there is no session user, the column
     * stays null and the credential is ownerless - which is the right
     * answer, because only a project administrator should then revoke it.
     *
     * @param GitRepo $repo
     * @param int $providerKeyId the id the Git server gave it
     * @param string $keyType
     * @param string $title
     * @return static
     */
    public static function record(GitRepo $repo, int $providerKeyId, string $keyType, string $title): self
    {
        $row = new static();
        $row->git_repo_id = intval($repo->id);
        $row->provider_key_id = $providerKeyId;
        $row->key_type = $keyType;
        $row->title = mb_substr($title, 0, 120);
        $row->save();

        return $row;
    }

    /**
     * Forget one credential, after the Git server has let go of it.
     *
     * @param GitRepo $repo
     * @param int $providerKeyId
     * @return void
     */
    public static function forget(GitRepo $repo, int $providerKeyId): void
    {
        static::where('git_repo_id', intval($repo->id))
            ->where('provider_key_id', $providerKeyId)
            ->delete();
    }

    /**
     * Revoke every credential these people were issued on one project's
     * repository: on the Git server first, then the record here.
     *
     * For people taken off a project or moved to reading it: a token is a
     * password that works until somebody revokes it, and nobody else would
     * think to. Best effort - a credential the Git server would not let go
     * of keeps its record, so it is still listed and can be removed by hand,
     * and the failure is logged. Never throws: the change of people that
     * asked for this has already been made.
     *
     * @param int $projectId
     * @param array<int, mixed> $userIds
     * @return int how many were revoked
     */
    public static function revokeForUsers(int $projectId, array $userIds): int
    {
        $ids = [];
        foreach ($userIds as $id) {
            if (is_numeric($id) && intval($id) > 0) {
                $ids[] = intval($id);
            }
        }
        $ids = array_values(array_unique($ids));
        if ($projectId <= 0 || empty($ids)) {
            return 0;
        }

        try {
            $repo = GitRepo::ofProject($projectId);
            if (!isset($repo)) {
                return 0;
            }
            $rows = static::where('git_repo_id', intval($repo->id))
                ->whereIn('created_user_id', $ids)
                ->get();

            return static::revokeRows($repo, $rows)['revoked'];
        } catch (\Throwable $e) {
            Log::error('pm_portal git: revoking the credentials of users ' . implode(',', $ids)
                . ' on project ' . $projectId . ' failed: ' . $e->getMessage(), ['exception' => $e]);

            return 0;
        }
    }

    /**
     * Revoke every credential the portal issued on one project's repository.
     * The same best effort as revokeForUsers(), for a project that is being
     * deleted.
     *
     * @param int $projectId
     * @return array{revoked: int, failed: int}
     */
    public static function revokeProject(int $projectId): array
    {
        try {
            $repo = $projectId > 0 ? GitRepo::ofProject($projectId) : null;
            if (!isset($repo)) {
                return ['revoked' => 0, 'failed' => 0];
            }

            return static::revokeRows($repo, static::where('git_repo_id', intval($repo->id))->get());
        } catch (\Throwable $e) {
            Log::error('pm_portal git: revoking the credentials of project ' . $projectId
                . ' failed: ' . $e->getMessage(), ['exception' => $e]);

            return ['revoked' => 0, 'failed' => 1];
        }
    }

    /**
     * Revoke the given records one by one; one that fails does not stop the
     * rest.
     *
     * @param GitRepo $repo
     * @param iterable<mixed> $rows records of this repository
     * @return array{revoked: int, failed: int}
     */
    public static function revokeRows(GitRepo $repo, iterable $rows): array
    {
        $revoked = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $keyId = intval($row->provider_key_id);
            try {
                GitProvisioner::removeKey($repo, $keyId);
                $row->delete();
                $revoked++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error(sprintf(
                    'pm_portal git: could not revoke credential %d on repository %s (project %s): %s',
                    $keyId,
                    strval($repo->repo_name),
                    strval($repo->project_id),
                    $e->getMessage()
                ), ['exception' => $e]);
            }
        }

        return ['revoked' => $revoked, 'failed' => $failed];
    }
}
