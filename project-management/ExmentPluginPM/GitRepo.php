<?php

namespace App\Plugins\ExmentPluginPM;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\ModelBase;
use Illuminate\Support\Facades\Log;

/**
 * The Git repository one project asked for, and how far that request got.
 *
 * The row is created the moment somebody clicks "create repository" in the
 * project settings; whether an actual repository appears on a Git server is
 * the provisioner's business (GitProvisioner), which is the only writer of
 * status / remote_url / last_error.
 *
 * @property mixed $project_id
 * @property mixed $repo_name
 * @property mixed $provider
 * @property mixed $status
 * @property mixed $remote_url
 * @property mixed $last_error
 * @property mixed $requested_user_id
 * @property mixed $provisioned_at
 * @phpstan-consistent-constructor
 */
class GitRepo extends ModelBase
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    /** What a repository may be called on any of the intended providers. */
    public const NAME_PATTERN = '/\A[a-z0-9][a-z0-9._-]{0,99}\z/';

    /** Prefix of the owner mark a provisioner leaves on the repository. */
    public const MARK_PREFIX = 'pm_portal:';

    protected $table = 'pm_portal_git_repos';

    protected $guarded = ['id'];

    /**
     * The repo row of one project, or null.
     *
     * @param mixed $project_id
     * @return static|null
     */
    public static function ofProject($project_id): ?self
    {
        return static::where('project_id', intval($project_id))->first();
    }

    /**
     * A safe default repository name derived from the project key:
     * "PORTAL_FE" becomes "portal_fe", an empty key falls back to the id.
     *
     * @param string $key
     * @param mixed $project_id
     * @return string
     */
    public static function suggestName(string $key, $project_id): string
    {
        $name = preg_replace('/[^a-z0-9._-]+/', '-', strtolower(trim($key)));
        $name = trim(strval($name), '-._');

        return $name !== '' ? mb_substr($name, 0, 100) : 'project-' . intval($project_id);
    }

    /**
     * What a provisioner writes on the repository it creates, so that the
     * repository can later be recognised as this project's own.
     *
     * The project row's suuid rather than its id: an id comes back after
     * exment:refreshdata, a suuid does not, so a new project that happens to
     * carry an old one's id is never taken for it. Null when the project row
     * cannot be found, and then nothing is ever recognised.
     *
     * @return string|null
     */
    public function ownerMark(): ?string
    {
        $table = CustomTable::getEloquent(array_get(PortalDefinition::config(), 'project_table'));
        if (!isset($table) || is_nullorempty($this->project_id)) {
            return null;
        }
        // Unscoped and with the trash: this is the plugin's own bookkeeping,
        // not something the person asking may or may not see.
        $suuid = $table->getValueModel()->newQueryWithoutScopes()
            ->where('id', intval($this->project_id))
            ->value('suuid');

        return is_nullorempty($suuid) ? null : static::MARK_PREFIX . strval($suuid);
    }

    /**
     * Drop the repository row of one project and the credential rows that
     * hang off it. Nothing is sent to the Git server: the repository itself
     * stays where it is, and so does any credential a provider still holds.
     *
     * Project ids are not forever - exment:refreshdata truncates the custom
     * tables and numbering starts again at 1 - and a brand new project must
     * not find an old one's repository waiting for it, with clone access for
     * whoever is on the new project. Called for a project that was just
     * created, and for one that has been deleted once its credentials have
     * been revoked.
     *
     * @param int $projectId
     * @return void
     */
    public static function forgetProject(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        foreach (static::where('project_id', $projectId)->get() as $repo) {
            $left = GitKey::where('git_repo_id', intval($repo->id))->count();
            if ($left > 0) {
                // The rows are the portal's only record of who holds what on
                // that repository. Say so where an operator will look.
                Log::warning(sprintf(
                    'pm_portal git: forgetting repository %s of project %d with %d credential record(s); revoke them on the Git server if they are still active',
                    strval($repo->repo_name),
                    $projectId,
                    $left
                ));
            }
            GitKey::where('git_repo_id', intval($repo->id))->delete();
            $repo->delete();
        }
    }
}
