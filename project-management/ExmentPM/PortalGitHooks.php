<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Git linked to the issues (ISS02939 item 5): a project's repositories connected by webhook, the
 * deliveries, and what the issues' timelines show. See docs/git-link-design.md.
 *
 * The webhook is POST /pm-git/hook/<hook key>, outside the admin (no session, no CSRF, no
 * login): its own path, so a WAF can be told about it once (Exment SaaS: see the README).
 * No call goes out to a Git host.
 */
class PortalGitHooks
{
    const FEATURE = 'exment_pm.git_link';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const HOOKS = 'pm_portal_git_hooks';
    const EVENTS = 'pm_portal_git_events';
    const PREFIX = 'pm-git';

    protected static $routed = false;

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('gitlink.feature')),
                'description' => strval(PortalDefinition::trans('gitlink.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            // an older Exment: the switch is not there
        }
    }

    public static function enabled(): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable(static::HOOKS)) {
            return false;
        }
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /** The webhook route, once per process (not when the routes are cached by Laravel). */
    public static function registerRoutes(): void
    {
        if (static::$routed || app()->routesAreCached()) {
            return;
        }
        static::$routed = true;
        Route::post(static::PREFIX . '/hook/{key}', function (Request $request, $key) {
            return static::receive($request, strval($key));
        })->where('key', '[0-9a-f]{32}');
    }

    public static function hookUrl(string $key): string
    {
        return url(static::PREFIX . '/hook/' . $key);
    }

    // ---- the project's settings -------------------------------------------------

    /** @return array<int, array<string, mixed>> the hooks of a project (no secret) */
    public static function list(int $projectId): array
    {
        return DB::table(static::HOOKS)->where('project_id', $projectId)->orderBy('id')->get()->map(function ($h) {
            return [
                'id' => intval($h->id), 'provider' => $h->provider, 'repo' => $h->repo,
                'url' => static::hookUrl(strval($h->hook_key)), 'close_on_merge' => boolval($h->close_on_merge),
                'last_delivery_at' => $h->last_delivery_at ? PortalTime::format($h->last_delivery_at, 'Y-m-d H:i') : null,
                'last_error' => $h->last_error,
            ];
        })->values()->all();
    }

    /** Connect a repository: the secret is answered once, then kept encrypted / hashed only. */
    public static function create(int $projectId, string $provider, string $repo, bool $closeOnMerge, int $userId): array
    {
        $repo = mb_strtolower(trim($repo));
        if (!in_array($provider, PortalGitParse::PROVIDERS, true) || !preg_match('#^[a-z0-9._\-]+(/[a-z0-9._\-]+){1,5}$#', $repo)) {
            throw new \InvalidArgumentException(strval(PortalDefinition::trans('gitlink.err_repo')));
        }
        if (DB::table(static::HOOKS)->where('project_id', $projectId)->count() >= 20) {
            throw new \InvalidArgumentException(strval(PortalDefinition::trans('gitlink.err_many')));
        }
        $secret = bin2hex(random_bytes(24));
        $id = intval(DB::table(static::HOOKS)->insertGetId([
            'project_id' => $projectId, 'provider' => $provider, 'repo' => $repo, 'hook_key' => bin2hex(random_bytes(16)),
            'secret_enc' => $provider === 'gitlab' ? null : PortalChannels::encrypt($secret),
            'secret_hash' => hash('sha256', $secret), 'close_on_merge' => $closeOnMerge,
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));

        return ['id' => $id, 'secret' => $secret, 'hooks' => static::list($projectId)];
    }

    public static function newSecret(int $projectId, int $hookId): array
    {
        $h = DB::table(static::HOOKS)->where('id', $hookId)->where('project_id', $projectId)->first();
        if (!isset($h)) {
            abort(404);
        }
        $secret = bin2hex(random_bytes(24));
        DB::table(static::HOOKS)->where('id', $hookId)->update([
            'secret_enc' => $h->provider === 'gitlab' ? null : PortalChannels::encrypt($secret),
            'secret_hash' => hash('sha256', $secret), 'updated_at' => now(),
        ]);

        return ['id' => $hookId, 'secret' => $secret, 'hooks' => static::list($projectId)];
    }

    public static function update(int $projectId, int $hookId, bool $closeOnMerge): array
    {
        DB::table(static::HOOKS)->where('id', $hookId)->where('project_id', $projectId)->update(['close_on_merge' => $closeOnMerge, 'updated_at' => now()]);

        return ['hooks' => static::list($projectId)];
    }

    public static function remove(int $projectId, int $hookId): array
    {
        DB::table(static::HOOKS)->where('id', $hookId)->where('project_id', $projectId)->delete();

        return ['hooks' => static::list($projectId)];
    }

    // ---- what the issues show ---------------------------------------------------------

    /** @return array<int, array<string, mixed>> the timeline lines of an issue */
    public static function timeline(int $issueId): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable(static::EVENTS)) {
            return [];
        }

        return DB::table(static::EVENTS)->where('issue_id', $issueId)->orderBy('occurred_at')->limit(200)->get()->map(function ($e) {
            return [
                'tp' => 'git', 'ts' => strval($e->occurred_at), 'at' => PortalTime::format($e->occurred_at, 'Y-m-d H:i'),
                'kind' => $e->kind, 'ref' => strval($e->ref), 'title' => strval($e->title), 'author' => strval($e->author),
                'url' => $e->url, 'branch' => strval($e->branch), 'state' => $e->state,
            ];
        })->values()->all();
    }

    // ---- the webhook --------------------------------------------------------------

    public static function receive(Request $request, string $key)
    {
        if (!static::enabled()) {
            return response()->json(['ok' => false], 404);
        }
        $hook = DB::table(static::HOOKS)->where('hook_key', $key)->first();
        if (!isset($hook)) {
            return response()->json(['ok' => false], 404);
        }
        $body = strval($request->getContent());
        if (strlen($body) > PortalGitParse::MAX_BODY) {
            return response()->json(['ok' => false, 'message' => 'too large'], 413);
        }
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = strval($values[0] ?? '');
        }
        $secret = $hook->provider === 'gitlab' ? null : PortalChannels::decrypt($hook->secret_enc);
        if (!PortalGitParse::verify(strval($hook->provider), $body, $headers, $secret, strval($hook->secret_hash))) {
            static::mark($hook, 'signature');

            return response()->json(['ok' => false], 403);
        }
        // a delivery seen already (sent again): once
        $delivery = PortalGitParse::deliveryId(strval($hook->provider), $headers);
        if ($delivery !== null && !Cache::add('pm_git_delivery:' . $hook->id . ':' . $delivery, 1, 86400)) {
            return response()->json(['ok' => true, 'replay' => true]);
        }
        $event = PortalGitParse::event(strval($hook->provider), $headers);
        if ($event === 'other') {
            static::mark($hook, null);

            return response()->json(['ok' => true, 'ignored' => true]);
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            static::mark($hook, 'json');

            return response()->json(['ok' => false], 400);
        }
        if (PortalGitParse::repoOf(strval($hook->provider), $payload) !== strval($hook->repo)) {
            static::mark($hook, 'repository');

            return response()->json(['ok' => false], 403);
        }
        try {
            // outside the admin middleware: Exment's names of the tables (the history, the
            // comments) are not set yet; without them a save is kept under the bare class name
            if (class_exists(\Exceedone\Exment\Middleware\Morph::class)) {
                \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            }
            $n = static::store($hook, PortalGitParse::items(strval($hook->provider), $event, $payload));
        } catch (\Throwable $e) {
            Log::warning('pm_portal git hook ' . $hook->id . ': ' . $e->getMessage());
            static::mark($hook, 'store');

            return response()->json(['ok' => false], 500);
        }
        static::mark($hook, null);

        return response()->json(['ok' => true, 'linked' => $n]);
    }

    protected static function mark(object $hook, ?string $error): void
    {
        DB::table(static::HOOKS)->where('id', $hook->id)->update(['last_delivery_at' => now(), 'last_error' => $error, 'updated_at' => now()]);
    }

    /** The items, each for the issues of this project it names. */
    protected static function store(object $hook, array $items): int
    {
        $def = PortalDefinition::config();
        $projects = CustomTable::getEloquent(strval(array_get($def, 'project_table')));
        $issues = CustomTable::getEloquent(strval(array_get($def, 'issue_table')));
        $project = isset($projects) ? $projects->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($hook->project_id) : null;
        if (!isset($project, $issues)) {
            return 0;
        }
        $projectKey = strtoupper(strval(array_get($project->value, array_get($def, 'project.key', 'project_code'), '')));
        $keyCol = strval(array_get($def, 'issue.key', 'issue_key'));
        $aliases = static::aliasesOf(intval($project->id), $projects->table_name, $issues);
        $n = 0;
        // the issues a text names, in the order written, at most 20 (linking and closing alike)
        $resolve = function (string $text) use ($projectKey, $aliases, $issues, $projects, $project, $keyCol): array {
            $hits = PortalGitParse::hits($text, $projectKey, array_keys($aliases));
            $words = [];
            foreach ($hits as $h) {
                if ($h['type'] === 'word' && count($words) < 50) {
                    $words[] = $h['k'];
                }
            }
            // the issue's own key (the key column: what the screen shows and the branch name copies)
            $byWord = [];
            if (!empty($words)) {
                $rows = $issues->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
                    ->where('parent_type', $projects->table_name)->where('parent_id', $project->id)
                    ->whereIn('value->' . $keyCol, $words)->limit(100)->get(['id', 'value']);
                foreach ($rows as $row) {
                    $byWord[strval(array_get($row->value, $keyCol))] = intval($row->id);
                }
            }
            $ids = [];
            foreach ($hits as $h) {
                if (count($ids) >= PortalGitParse::MAX_ISSUES) {
                    break;
                }
                $id = null;
                if ($h['type'] === 'alias') {
                    $id = $aliases[$h['k']] ?? null;
                } elseif ($h['type'] === 'word') {
                    $id = $byWord[$h['k']] ?? null;
                } else {
                    // the key on screen is "<PROJECT KEY>-<the issue's id>"; an issue moved here
                    // from another project is found by its old key too
                    $k = $h['k'];
                    $old = PortalIssueAliases::exists() ? PortalIssueAliases::issueOf($k) : null;
                    $id = static::ofProject($issues, $projects->table_name, intval($project->id), intval(substr($k, strrpos($k, '-') + 1)))
                        ?? ($old !== null ? static::ofProject($issues, $projects->table_name, intval($project->id), $old) : null);
                }
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }

            return $ids;
        };
        foreach ($items as $it) {
            $ids = $resolve($it['text']);
            // 「fixes / closes KEY」 of a merged PR, when the hook says so (step 2): only issues it links
            $closing = [];
            if ($it['kind'] === 'pr' && $it['merged'] && boolval($hook->close_on_merge)) {
                $words = PortalGitParse::closeWords($it['text']);
                $closing = empty($words) ? [] : array_intersect_key($resolve(implode(' ', $words)), $ids);
            }
            foreach (array_keys($ids) as $issueId) {
                $before = DB::table(static::EVENTS)->where(['hook_id' => $hook->id, 'kind' => $it['kind'], 'ref' => $it['ref'], 'issue_id' => $issueId])->value('state');
                DB::table(static::EVENTS)->updateOrInsert(
                    ['hook_id' => $hook->id, 'kind' => $it['kind'], 'ref' => $it['ref'], 'issue_id' => $issueId],
                    ['project_id' => $hook->project_id, 'title' => $it['title'], 'author' => $it['author'], 'url' => $it['url'],
                        'branch' => $it['branch'], 'state' => $it['state'], 'occurred_at' => $it['at'], 'updated_at' => now(), 'created_at' => now()]
                );
                $n++;
                // once, when the PR becomes merged: an issue opened again later is left alone
                if (isset($closing[$issueId]) && $before !== 'merged') {
                    static::closeIssue($def, $issues, $project, $issueId, $hook, $it);
                }
            }
        }

        return $n;
    }

    /**
     * Move an issue to the first done status of its project, with a line in its history. Never
     * an issue already done (any done status), never when the statuses are still a word list.
     */
    protected static function closeIssue(array $def, CustomTable $issues, $project, int $issueId, object $hook, array $it): void
    {
        try {
            $statusTable = CustomTable::getEloquent(strval(array_get($def, 'status_table')));
            $statusCol = strval(array_get($def, 'issue.status', 'status'));
            if (!isset($statusTable) || $statusCol === '') {
                return;
            }
            $doneCol = strval(array_get($def, 'status_row.done', 'is_done'));
            $orderCol = strval(array_get($def, 'status_row.order', 'display_order'));
            $rows = $statusTable->getValueModel()->query()->withoutGlobalScopes()->where(function ($w) use ($project) {
                $w->where('parent_id', $project->id)->orWhereNull('parent_id');
            })->orderBy('id')->get();
            $doneIds = [];
            foreach ($rows as $r) {
                if (boolval(array_get($r->value, $doneCol))) {
                    $doneIds[] = strval($r->id);
                }
            }
            // an issue table with a workflow: its steps are the way to done, never skipped here
            if (class_exists(\Exceedone\Exment\Model\Workflow::class) && !empty(\Exceedone\Exment\Model\Workflow::getWorkflowByTable($issues))) {
                return;
            }
            $issue = $issues->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($issueId);
            if (!isset($issue) || empty($doneIds)) {
                return;
            }
            $now = array_get($issue->value, $statusCol);
            $now = is_array($now) ? strval(reset($now)) : strval($now ?? '');
            if (in_array($now, $doneIds, true)) {
                return;
            }
            // the project's own statuses when it has any (as the settings tab shows them), else the shared ones
            $own = $rows->filter(function ($r) {
                return !is_null($r->parent_id);
            });
            $list = ($own->isNotEmpty() ? $own : $rows)->filter(function ($r) use ($doneCol) {
                return boolval(array_get($r->value, $doneCol));
            })->sortBy(function ($r) use ($orderCol) {
                return intval(array_get($r->value, $orderCol, 0));
            })->values();
            if ($list->isEmpty()) {
                return;
            }
            $issue->setValue($statusCol, strval($list->first()->id));
            $issue->save();
            // the line of the issue's history: by 「Git（owner/repo）」 with the PR's link, not by nobody
            DB::table(static::EVENTS)->updateOrInsert(
                ['hook_id' => $hook->id, 'kind' => 'close', 'ref' => $it['ref'], 'issue_id' => $issueId],
                ['project_id' => $hook->project_id, 'title' => $it['title'], 'author' => static::by($hook), 'url' => $it['url'],
                    'branch' => $it['branch'], 'state' => 'merged', 'occurred_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::warning('pm_portal git hook ' . $hook->id . ': issue ' . $issueId . ' could not be moved to done: ' . $e->getMessage());
        }
    }

    /** Who a change of the webhook is shown as: 「Git（owner/repo）」. */
    public static function by(object $hook): string
    {
        return 'Git（' . strval($hook->repo) . '）';
    }

    /**
     * The issues' status changes the webhook made (its "close" lines), for the history lines of
     * no user: [time, who, the PR's link, its number].
     *
     * @return array<int, array{ts: int, who: string, url: ?string, ref: string}>
     */
    public static function closers(int $issueId): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable(static::EVENTS)) {
            return [];
        }

        return DB::table(static::EVENTS)->where('issue_id', $issueId)->where('kind', 'close')->get()->map(function ($e) {
            return ['ts' => intval(strtotime(strval($e->occurred_at))), 'who' => strval($e->author), 'url' => $e->url, 'ref' => strval($e->ref)];
        })->values()->all();
    }

    /** The issue's id when it is an issue of the project, else null. */
    protected static function ofProject(CustomTable $issues, string $projectTable, int $projectId, int $issueId): ?int
    {
        if ($issueId <= 0) {
            return null;
        }
        $row = $issues->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('id', $issueId)->where('parent_type', $projectTable)->where('parent_id', $projectId)->first(['id']);

        return isset($row) ? intval($row->id) : null;
    }

    /** @return array<string, int> the aliases of the project's issues, lower case => issue id */
    protected static function aliasesOf(int $projectId, string $projectTable, CustomTable $issues): array
    {
        if (!PortalIssueAliases::exists()) {
            return [];
        }
        $ids = $issues->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', $projectTable)->where('parent_id', $projectId)->limit(5000)->pluck('id')->all();
        $out = [];
        foreach (PortalIssueAliases::names(array_map('intval', $ids)) as $issueId => $name) {
            if (is_string($name) && $name !== '') {
                $out[mb_strtolower($name)] = intval($issueId);
            }
        }

        return $out;
    }
}
