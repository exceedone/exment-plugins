<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Services\Plugin\PluginBatchBase;
use Illuminate\Support\Facades\Log;

/**
 * Fill the AI columns of the issues that still have them empty.
 *
 *   php artisan exment:batch --name=ExmentPM --param1=<project id|all> --param2=<limit> --param3=dry
 *
 *   param1  one project's id, or "all" for every project that has the AI
 *           switched on (default: all). Archived projects are never filled.
 *   param2  how many AI calls this run may make (default: 100). A call that
 *           failed counts: it was still an attempt.
 *           "recur" makes the recurring issues whose turn has come instead
 *   param3  "dry" prints what would be asked and written, and neither calls
 *           the AI nor writes anything
 *
 *   --param1=schedule_shares lists the creator's shares that runs of the recurring
 *           schedules and issues, and imports from Backlog (ExmentPMBacklog), gave on their issues
 *           to people outside the project (a dry run); --param2=apply removes them
 *           (PortalRunShares::repair)
 *   --param1=schedule_keys lists the issues that show the key of an older issue (a dry
 *           run); --param2=apply gives each a key of its own (PortalRecurrence::repairKeys)
 *
 * Meant for the issues that were there before the switch was turned on,
 * and for a day on which the switch was off while a CSV was imported. One
 * call per issue with a pause between them, far below the rate limit; the
 * daily cap of each project still applies.
 *
 * Fair by construction. Projects take turns, one call each, so a big
 * project at the front cannot spend the whole run while the others wait; an
 * issue whose text and questions were already answered is not paid for
 * again; and an issue that only lacks its assignee is filled by the rule,
 * which needs no call at all.
 *
 * It also sweeps the log: exment:refreshdata renumbers issues from 1, so a
 * row pointing at an issue that is gone, or at one created after the row
 * was last written, points at nothing it ever scored.
 *
 * The console text is Japanese, from the lang file. A per-issue error line
 * keeps the raw exception text: it is for the operator, and is logged too.
 */
class PluginBatch extends PluginBatchBase
{
    /** Pause between two calls, so a run of hundreds stays polite. */
    protected const PAUSE_MS = 50;

    /** Refused for being too fast this many times in a row, the run stops. */
    protected const MAX_BACKOFFS = 5;

    /** The longest wait after a "too many requests". */
    protected const BACKOFF_MAX_MS = 30000;

    // What working on one issue came to, for interleave().
    /** Nothing to do. */
    public const STEP_SKIP = 'skip';
    /** Something was done that cost no call. */
    public const STEP_FREE = 'free';
    /** A call was made - or, in a dry run, would be - answered or not. */
    public const STEP_CALL = 'call';
    /** This project is finished for this run (its daily cap is spent). */
    public const STEP_DONE = 'done';
    /** The whole run stops. */
    public const STEP_STOP = 'stop';

    /** @var array<string, int> */
    protected $stats = [];

    /** @var int "too many requests" answers in a row */
    protected $limited = 0;

    /**
     * @return int|false
     */
    public function execute()
    {
        $options = (array) ($this->pluginOptions->command_options ?? []);
        $target = strval(array_get($options, 'param1') ?? 'all');
        $limit = max(1, intval(array_get($options, 'param2') ?: 100));
        $dry = strtolower(strval(array_get($options, 'param3') ?? '')) === 'dry';

        // --param1=recur: make the recurring issues whose turn has come
        // (PortalRecurrence). Page loads do this too; this is for a server
        // that runs the batch on a schedule.
        // --param1=shares: give the projects and the issues there are the shares of the people
        // they name, the ones not in a role group yet when they were saved too (ISS00385)
        if ($target === 'shares') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $def = PortalDefinition::config();
            $added = 0;
            $rows = 0;
            foreach ([array_get($def, 'project_table'), array_get($def, 'issue_table')] as $tableName) {
                $table = CustomTable::getEloquent($tableName);
                if (!isset($table)) {
                    continue;
                }
                $table->getValueModel()->query()->withoutGlobalScopes()->orderBy('id')->chunk(200, function ($chunk) use (&$added, &$rows, $dry) {
                    foreach ($chunk as $row) {
                        $rows++;
                        if (!$dry) {
                            $added += PortalMembership::ensureShares($row);
                        }
                    }
                });
            }
            $this->say($dry ? 'shares_dry' : 'shares', ['rows' => $rows, 'count' => $added]);

            return 0;
        }

        // --param1=schedule: the issues of the recurring schedules (ISS00432), once a day or more
        if ($target === 'schedule') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            if (!PortalSchedule::enabled()) {
                $this->say('schedule_off');

                return 0;
            }
            $made = PortalSchedule::run($dry);
            $this->say($dry ? 'schedule_dry' : 'schedule', ['count' => count($made)]);

            return 0;
        }

        // --param1=schedule_shares: list the creator's shares that runs of the schedules (and the
        // imports of ExmentPMBacklog) gave on the issues they made to people outside the project
        // (PortalRunShares::repair). A dry run unless --param2=apply, which removes them; a second
        // run finds nothing more.
        if ($target === 'schedule_shares') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $apply = strtolower(strval(array_get($options, 'param2') ?? '')) === 'apply';
            $r = PortalRunShares::repair($apply);
            foreach ($r['found'] as $s) {
                echo static::pad(($s['key'] !== '' ? $s['key'] : '#' . $s['issue']), 12) . ' '
                    . static::pad($s['type'] . ':' . $s['target'], 20) . ' ' . $s['permission'] . "\n";
            }
            $this->say($apply ? 'schedule_shares' : 'schedule_shares_dry', ['issues' => $r['issues'], 'count' => count($r['found']), 'removed' => $r['removed']]);

            return 0;
        }

        // --param1=schedule_keys: list the issues that show the key of an older issue (copies made
        // by the schedules and the recurring issues before the copy left the key out). A dry run
        // unless --param2=apply, which gives each a key of its own (PortalRecurrence::repairKeys).
        if ($target === 'schedule_keys') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $apply = strtolower(strval(array_get($options, 'param2') ?? '')) === 'apply';
            $r = PortalRecurrence::repairKeys($apply);
            foreach ($r['found'] as $f) {
                echo static::pad('#' . $f['issue'], 10) . ' ' . static::pad($f['old_key'], 14) . ' '
                    . ($f['key'] ? 'key ' : '') . ($f['source'] ? 'source_key=' . $f['source_key'] . ' ' : '') . '(#' . $f['dup_of'] . ")\n";
            }
            $this->say($apply ? 'schedule_keys' : 'schedule_keys_dry', ['issues' => $r['issues'], 'count' => count($r['found']), 'fixed' => $r['fixed'], 'failed' => count($r['failed'])]);

            return empty($r['failed']) ? 0 : 1;
        }

        // --param1=digest: the morning digest of each person (ISS02939), every 15 minutes
        if ($target === 'digest') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            if (!PortalDigest::enabled()) {
                $this->say('digest_off');

                return 0;
            }
            $r = PortalDigest::run($dry);
            $this->say($dry ? 'digest_dry' : 'digest', ['people' => $r['people'], 'count' => $r['sent'], 'empty' => $r['empty'], 'failed' => $r['failed'] ?? 0]);

            return 0;
        }

        // --param1=remind: the reminders before the due date (ISS00432), every 15 minutes
        if ($target === 'remind') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            if (!PortalReminder::enabled()) {
                $this->say('remind_off');

                return 0;
            }
            $r = PortalReminder::run($dry);
            $this->say($dry ? 'remind_dry' : 'remind', ['issues' => $r['issues'], 'count' => $r['sent'], 'skipped' => $r['skipped']]);

            return 0;
        }

        if ($target === 'recur') {
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $made = PortalRecurrence::run($dry);
            $this->say($dry ? 'recur_dry' : 'recur', ['count' => count($made)]);

            return 0;
        }

        $def = PortalDefinition::config();
        $project_table = CustomTable::getEloquent(array_get($def, 'project_table'));
        $issue_table = CustomTable::getEloquent(array_get($def, 'issue_table'));
        if (!isset($project_table) || !isset($issue_table)) {
            $this->say('no_tables');

            return false;
        }
        // A dry run calls nobody, so it can show what a real run would do
        // before a key is in place.
        if (!$dry && !JevClient::configured()) {
            $this->say('no_key');

            return false;
        }

        // A command has no middleware to bind parent_type to a class.
        \Exceedone\Exment\Middleware\Morph::defineMorphMap();

        $autofill = $this->autofill();
        $projects = $this->projects($project_table, $target, $autofill);
        if (empty($projects)) {
            $this->say('no_projects');

            return 0;
        }

        $is = (array) array_get($def, 'issue', []);
        $labels = $this->columnLabels($issue_table);
        $queues = [];
        $byId = [];
        $keys = [];
        foreach ($projects as $project) {
            $id = strval($project->id);
            $byId[$id] = $project;
            $keys[$id] = strtoupper(strval(array_get($project->value, array_get($def, 'project.key'), ''))) ?: ('#' . $id);
            $done = $autofill->masters($project)['done_status_ids'];
            $queues[$id] = $this->openIssues($issue_table, $project_table, $project, $is, $done)->all();
        }

        $this->say($dry ? 'header_dry' : 'header', [
            'limit' => $limit,
            'threshold' => IssueAutofill::thresholdAuto(),
        ]);

        $this->stats = ['scored' => 0, 'written' => 0, 'errors' => 0, 'unchanged' => 0];
        $this->limited = 0;
        static::interleave($queues, $limit, function ($id, $issue) use ($autofill, $byId, $keys, $dry, $is, $labels) {
            return $this->step($autofill, $byId[$id], $keys[$id], $issue, $dry, $is, $labels);
        });

        $swept = $dry ? 0 : $this->sweepLogs($project_table, $issue_table);

        $this->say($dry ? 'summary_dry' : 'summary', [
            'scored' => $this->stats['scored'],
            'written' => $this->stats['written'],
            'errors' => $this->stats['errors'],
            'unchanged' => $this->stats['unchanged'],
            'swept' => $swept,
        ]);

        return $this->stats['errors'] > 0 && $this->stats['scored'] === 0 && $this->stats['written'] === 0 ? 1 : 0;
    }

    /**
     * Work through several queues in turns, until $limit calls are spent.
     *
     * Each queue - a project's open issues - goes on until it has spent a
     * call, then hands the turn to the next. Issues that need no call cost
     * nothing and do not end a turn. A queue leaves the rotation when it is
     * empty or says it is done (its daily cap); the run ends when the calls
     * are spent, every queue has left, or a step says stop.
     *
     * @param array<int|string, array<int, mixed>> $queues items per key, in the order to try them
     * @param int $limit calls the run may spend
     * @param callable $step fn(int|string $key, mixed $item): string, one of the STEP_* values
     * @return int calls spent
     */
    public static function interleave(array $queues, int $limit, callable $step): int
    {
        $spent = 0;
        $positions = array_fill_keys(array_keys($queues), 0);

        while ($spent < $limit && !empty($queues)) {
            foreach (array_keys($queues) as $key) {
                while (true) {
                    if ($positions[$key] >= count($queues[$key])) {
                        unset($queues[$key]);

                        continue 2;
                    }
                    $item = $queues[$key][$positions[$key]];
                    $positions[$key]++;

                    $result = $step($key, $item);
                    if ($result === static::STEP_STOP) {
                        return $spent;
                    }
                    if ($result === static::STEP_DONE) {
                        unset($queues[$key]);

                        continue 2;
                    }
                    if ($result === static::STEP_CALL) {
                        $spent++;
                        if ($spent >= $limit) {
                            return $spent;
                        }

                        continue 2;
                    }
                    // skip / free: the same queue goes on.
                }
            }
        }

        return $spent;
    }

    /**
     * How long to wait after the n-th "too many requests" in a row: one
     * second, doubling, never more than BACKOFF_MAX_MS.
     *
     * @param int $consecutive
     * @return int milliseconds
     */
    public static function backoffMs(int $consecutive): int
    {
        $consecutive = max(1, $consecutive);

        return intval(min(static::BACKOFF_MAX_MS, 1000 * (2 ** min(15, $consecutive - 1))));
    }

    /**
     * One issue.
     *
     * @param IssueAutofill $autofill
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param string $key the project's key, for the console
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param bool $dry
     * @param array<string, mixed> $is
     * @param array<string, string> $labels column name => label
     * @return string one of the STEP_* values
     */
    protected function step(IssueAutofill $autofill, $project, string $key, $issue, bool $dry, array $is, array $labels): string
    {
        $before = $autofill->callsMade();
        try {
            $result = $autofill->applyEmpty($project, $issue, AiLog::KIND_BATCH, $dry);
        } catch (JevException $e) {
            $called = $autofill->callsMade() > $before;
            if ($e->reason === 'daily_cap') {
                $this->say('daily_cap', ['key' => $key]);

                return static::STEP_DONE;
            }
            $this->stats['errors']++;
            $reason = PortalDefinition::trans($e->langKey());
            $this->line($key, $issue, $is, $this->text('mark_error'),
                PortalDefinition::isUnresolved($reason) ? $e->reason : strval($reason));
            // A bad key or a missing one does not get better issue by issue.
            if (in_array($e->reason, ['unauthorized', 'not_configured'], true)) {
                $this->say('stop_key');

                return static::STEP_STOP;
            }
            if (in_array($e->reason, ['rate_limited', 'overloaded'], true)) {
                $this->limited++;
                if ($this->limited >= static::MAX_BACKOFFS) {
                    $this->say('stop_rate', ['n' => $this->limited]);

                    return static::STEP_STOP;
                }
                $wait = static::backoffMs($this->limited);
                $this->say('backoff', ['ms' => $wait]);
                $this->pause($wait);
            }

            return $called ? static::STEP_CALL : static::STEP_SKIP;
        } catch (\Throwable $e) {
            // One issue that cannot be saved - a rule it breaks, a row
            // deleted under the batch - is that issue's problem only.
            $this->stats['errors']++;
            $this->line($key, $issue, $is, $this->text('mark_error'), $e->getMessage());
            Log::error('pm_portal batch: issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);

            return $autofill->callsMade() > $before ? static::STEP_CALL : static::STEP_SKIP;
        }

        if ($result['skipped'] === 'unchanged') {
            $this->stats['unchanged']++;
        }
        if (!$result['called'] && empty($result['set']) && !($dry && !empty($result['rule']))) {
            return static::STEP_SKIP;
        }

        if ($result['called']) {
            $this->limited = 0;
            $this->stats['scored']++;
        }
        if (!empty($result['set'])) {
            $this->stats['written']++;
        }

        $mark = !empty($result['set'])
            ? $this->text($dry ? 'mark_would' : 'mark_set')
            : $this->text($dry ? 'mark_ask' : 'mark_skip');
        $detail = $this->describe($result['fields'], $result['set'], $labels);
        if ($dry && $result['called']) {
            $detail = trim($detail . ' ' . $this->text('asks', [
                'columns' => implode('、', array_map(function ($column) use ($labels) {
                    return array_get($labels, $column, $column);
                }, $result['ask'])),
            ]));
        }
        $this->line($key, $issue, $is, $mark, $detail);

        if ($result['called'] && !$dry) {
            $this->pause(static::PAUSE_MS);
        }

        return $result['called'] ? static::STEP_CALL : static::STEP_FREE;
    }

    /**
     * The autofill the run uses. A seam for the probes, which give it a
     * client that never leaves the machine.
     */
    protected function autofill(): IssueAutofill
    {
        return new IssueAutofill();
    }

    /**
     * @param int $ms
     * @return void
     */
    protected function pause(int $ms): void
    {
        usleep(max(0, $ms) * 1000);
    }

    /**
     * The projects this run looks at: one by id, or every one that has the
     * switch on. An archived project is never filled, whichever way it was
     * named: its issues cannot be saved, so a call would be paid for nothing.
     *
     * @param CustomTable $project_table
     * @param string $target
     * @param IssueAutofill $autofill
     * @return array<int, \Exceedone\Exment\Model\CustomValue>
     */
    protected function projects(CustomTable $project_table, string $target, IssueAutofill $autofill): array
    {
        if ($target !== 'all' && $target !== '') {
            $project = $project_table->getValueModel()->find(intval($target));
            if (!isset($project)) {
                $this->say('not_found', ['target' => $target]);

                return [];
            }
            if ($autofill->projectFlag($project, 'archived', false)) {
                $this->say('archived', ['target' => $target]);

                return [];
            }
            if (!$autofill->projectFlag($project, 'ai_enabled', false)) {
                $this->say('ai_off', ['target' => $target]);

                return [];
            }

            return [$project];
        }

        $out = [];
        foreach ($project_table->getValueModel()->newQuery()->orderBy('id')->get() as $project) {
            if ($autofill->projectFlag($project, 'archived', false)) {
                continue;
            }
            if ($autofill->projectFlag($project, 'ai_enabled', false)) {
                $out[] = $project;
            }
        }

        return $out;
    }

    /**
     * The project's issues that are not finished, newest first - the ones
     * somebody is going to open next. An issue with no status is not
     * finished.
     *
     * @param CustomTable $issue_table
     * @param CustomTable $project_table
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<string, mixed> $is
     * @param array<int, string> $doneStatusIds
     * @return \Illuminate\Support\Collection
     */
    protected function openIssues(CustomTable $issue_table, CustomTable $project_table, $project, array $is, array $doneStatusIds)
    {
        $query = $issue_table->getValueModel()->newQuery()
            ->where('parent_id', $project->id)
            ->where('parent_type', $project_table->table_name);

        $statusColumn = CustomColumn::getEloquent(array_get($is, 'status'), $issue_table);
        if (isset($statusColumn) && !empty($doneStatusIds)) {
            IssueAutofill::whereOpen($query, $statusColumn->getQueryKey(), $doneStatusIds);
        }

        return $query->orderBy('id', 'desc')->get();
    }

    /**
     * One line per field: what was decided and whether it was written.
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $set
     * @param array<string, string> $labels
     * @return string
     */
    protected function describe(array $fields, array $set, array $labels = []): string
    {
        $parts = [];
        foreach ($fields as $column => $field) {
            if (!is_array($field)) {
                continue;
            }
            $label = array_get($field, 'label');
            $mark = array_key_exists($column, $set) ? '*' : '';
            $parts[] = array_get($labels, $column, $column) . '=' . (isset($label) ? $label : '-') . $mark
                . ' ' . number_format(floatval(array_get($field, 'confidence', 0)), 2);
        }

        return implode(' | ', $parts);
    }

    /**
     * Issue column name => the label people see.
     *
     * @param CustomTable $issue_table
     * @return array<string, string>
     */
    protected function columnLabels(CustomTable $issue_table): array
    {
        $out = [];
        foreach ($issue_table->custom_columns as $column) {
            $out[strval($column->column_name)] = strval($column->column_view_name);
        }

        return $out;
    }

    /**
     * Drop log rows that point at nothing they ever scored: an issue that is
     * gone, or was created after the row was last written - which is what
     * exment:refreshdata leaves behind when it renumbers from 1 - and rows
     * of a project that no longer exists at all.
     *
     * "Gone" means gone from the table, trash included: an issue in the
     * trash can be restored, and its log with it.
     *
     * "Last written" is updated_at, not created_at: a suggestion made while
     * an issue was still being written is older than the issue by design,
     * and only the feedback that links it, after the save, is newer.
     *
     * @param CustomTable $project_table
     * @param CustomTable $issue_table
     * @return int rows deleted
     */
    protected function sweepLogs(CustomTable $project_table, CustomTable $issue_table): int
    {
        $deleted = 0;
        AiLog::whereNotNull('issue_id')->orderBy('id')->chunk(500, function ($rows) use ($issue_table, &$deleted) {
            // Not map('intval'): map() passes the key as intval's base, and
            // this connection hands ids back as strings.
            $ids = $rows->pluck('issue_id')->map(function ($id) {
                return intval($id);
            })->unique()->values()->all();
            // Without scopes: the trash included, and no row filter - the
            // batch runs as nobody, and a sweep must see every issue.
            $created = [];
            foreach ($issue_table->getValueModel()->newQueryWithoutScopes()->whereIn('id', $ids)->get(['id', 'created_at']) as $issue) {
                $created[intval($issue->id)] = strval($issue->created_at ?? '');
            }

            foreach ($rows as $row) {
                $issueId = intval($row->issue_id);
                $gone = !array_key_exists($issueId, $created);
                $younger = !$gone && strcmp($created[$issueId], strval($row->updated_at ?? $row->created_at ?? '')) > 0;
                if ($gone || $younger) {
                    $row->delete();
                    $deleted++;
                }
            }
        });

        $projectIds = AiLog::query()->distinct()->pluck('project_id')->map(function ($id) {
            return intval($id);
        })->all();
        if (!empty($projectIds)) {
            $existing = $project_table->getValueModel()->newQueryWithoutScopes()
                ->whereIn('id', $projectIds)->pluck('id')->map(function ($id) {
                    return intval($id);
                })->all();
            $missing = array_values(array_diff($projectIds, $existing));
            if (!empty($missing)) {
                $deleted += AiLog::whereIn('project_id', $missing)->delete();
            }
        }

        return $deleted;
    }

    // ---- console ----------------------------------------------------------

    /**
     * @param string $key under batch
     * @param array<string, mixed> $replace
     * @return string
     */
    protected function text(string $key, array $replace = []): string
    {
        return strval(PortalDefinition::trans('batch.' . $key, $replace));
    }

    /**
     * One line about the run as a whole.
     *
     * @param string $key under batch
     * @param array<string, mixed> $replace
     * @return void
     */
    protected function say(string $key, array $replace = []): void
    {
        echo '[' . PortalDefinition::pluginName() . '] ' . $this->text($key, $replace) . "\n";
    }

    /**
     * One line about one issue, in columns that line up in Japanese too.
     *
     * @param string $key
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param array<string, mixed> $is
     * @param string $mark
     * @param string $detail
     * @return void
     */
    protected function line(string $key, $issue, array $is, string $mark, string $detail): void
    {
        $subject = mb_strimwidth(strval(array_get($issue->value, array_get($is, 'subject'), '')), 0, 40, '…');

        echo static::pad($key . '-' . $issue->id, 12) . ' ' . static::pad($mark, 10) . ' '
            . static::pad($subject, 40) . ' | ' . $detail . "\n";
    }

    /**
     * Pad to a display width: a full-width character takes two columns.
     *
     * @param string $text
     * @param int $width
     * @return string
     */
    protected static function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strwidth($text)));
    }
}
