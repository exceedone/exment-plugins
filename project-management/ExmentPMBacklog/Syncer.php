<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * One run of one connection: the Backlog issues changed since the last run,
 * and their new comments, into the Exment project. Backlog is the master;
 * an issue changed in Exment is overwritten by Backlog's text.
 *
 * - Written from inside Exment, with Exment's notifications off: a run of a
 *   thousand issues must not ring a thousand bells or send the mails.
 * - Created and updated times are Backlog's, so the project's lists and
 *   ExmentPM's archive see the issue's own age, not the import's.
 * - An assignee with no user here is kept by name (source_assignees).
 * - A failure on one issue is that issue's: it is counted, logged, and tried
 *   again next run; the cursor never passes it.
 */
class Syncer
{
    /** Issues one run takes at most; the rest wait for the next run. */
    const DEFAULT_LIMIT = 300;

    /** Problem lines kept per run. */
    const MAX_PROBLEMS = 200;

    /** Backlog changeLog field -> the words on the screen. */
    const FIELD_LABELS = [
        'summary' => '件名', 'description' => '詳細', 'status' => '状態', 'assigner' => '担当者',
        'issueType' => '種別', 'priority' => '優先度', 'component' => 'カテゴリー', 'milestone' => 'マイルストーン',
        'version' => '発生バージョン', 'resolution' => '完了理由', 'startDate' => '開始日', 'limitDate' => '期限日',
        'estimatedHours' => '予定時間', 'actualHours' => '実績時間', 'parentIssue' => '親課題',
    ];

    /** Fields whose change is noted, not copied. */
    const LONG_FIELDS = ['description'];

    /** @var object */
    protected $conn;

    /** @var Backlog */
    protected $bl;

    /** @var array<string, mixed> */
    protected $map;

    /** @var array<int, string> */
    protected $problems = [];

    /** @var array<string, int> */
    protected $counts = ['add' => 0, 'update' => 0, 'skip' => 0, 'comment' => 0, 'error' => 0];

    /** @var array<string, bool> issue columns that exist */
    protected $cols = [];

    /** @var \Exceedone\Exment\Model\CustomTable */
    protected $issueTable;

    public function __construct(object $conn)
    {
        $this->conn = $conn;
        $this->bl = Connections::client($conn);
        $this->map = Connections::mapping($conn);
    }

    /**
     * Run it, holding the connection's lock, and write the run log.
     *
     * @return array{status: string, counts: array<string, int>, problems: array<int, string>, message: string}
     */
    public static function runConnection(object $conn, string $trigger, int $limit = self::DEFAULT_LIMIT): array
    {
        $id = intval($conn->id);
        if (!Connections::lock($id)) {
            return ['status' => 'skipped', 'counts' => [], 'problems' => [], 'message' => Lang::t('run_locked')];
        }
        $runId = DB::table('pmbl_runs')->insertGetId([
            'connection_id' => $id, 'trigger' => $trigger, 'started_at' => Carbon::now(), 'status' => 'running',
        ]);
        $status = 'failed';
        $message = '';
        $self = null;
        try {
            // A command has no middleware to bind parent_type to a class.
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $self = new self($conn);
            $self->run($limit);
            $status = $self->counts['error'] > 0 ? 'partial' : 'ok';
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);
            Log::error('pm_backlog connection ' . $id . ' failed: ' . $message, ['exception' => $e]);
        } finally {
            $counts = $self ? $self->counts : [];
            $problems = $self ? array_slice($self->problems, 0, static::MAX_PROBLEMS) : [];
            if ($self) {
                $counts['calls'] = $self->bl->calls;
            }
            DB::table('pmbl_runs')->where('id', $runId)->update([
                'finished_at' => Carbon::now(),
                'status' => $status,
                'counts' => json_encode($counts),
                'problems' => json_encode($problems, JSON_UNESCAPED_UNICODE),
                'message' => $message,
            ]);
            $fails = $status === 'failed' ? intval($conn->fail_count) + 1 : 0;
            // Failing again and again: wait longer each time, up to a day.
            $wait = intval($conn->interval_minutes) * (2 ** min($fails, 5));
            Connections::patch($id, [
                'last_run_at' => Carbon::now(),
                'next_run_at' => Carbon::now()->addMinutes(min($wait, 1440)),
                'fail_count' => $fails,
                'locked_until' => null,
            ]);
            Notices::afterRun($conn, intval($conn->fail_count), $fails, $message, Connections::settingsUrl($id));
            // Keep the last 200 runs of a connection.
            $keep = DB::table('pmbl_runs')->where('connection_id', $id)->orderByDesc('id')->skip(200)->take(1)->value('id');
            if ($keep) {
                DB::table('pmbl_runs')->where('connection_id', $id)->where('id', '<=', $keep)->delete();
            }
        }

        return ['status' => $status, 'counts' => $self ? $counts : [], 'problems' => $problems, 'message' => $message];
    }

    public function run(int $limit): void
    {
        if (empty($this->map['backlog_project_id'])) {
            throw new \RuntimeException(Lang::t('error_no_mapping'));
        }
        $this->issueTable = Pm::table('issue_table');
        if (!$this->issueTable) {
            throw new \RuntimeException(Lang::t('error_no_pm'));
        }
        foreach ($this->issueTable->custom_columns as $c) {
            $this->cols[$c->column_name] = true;
        }

        $cursor = $this->conn->cursor_updated ?: null;
        $host = $this->bl->host();
        $todo = [];
        foreach ($this->bl->issues(intval($this->map['backlog_project_id']), $cursor) as $issue) {
            // Backlog filters by day only; the same day's earlier changes come again.
            $link = Links::find($host, 'issue', intval($issue['id']));
            if ($link && $link->backlog_updated === $issue['updated']) {
                $this->counts['skip']++;
                continue;
            }
            if (count($todo) >= $limit) {
                break;
            }
            $todo[] = $issue;
        }

        $failed = [];
        $newest = $cursor;
        $needParent = [];
        foreach ($todo as $issue) {
            try {
                DB::transaction(function () use ($issue, $host, &$needParent) {
                    $this->one($issue, $host, $needParent);
                });
                $newest = max(strval($newest), strval($issue['updated']));
            } catch (\Throwable $e) {
                $this->counts['error']++;
                $failed[] = strval($issue['updated']);
                $this->problem($issue['issueKey'] . ': ' . Lang::t('problem_error') . ' ' . mb_substr($e->getMessage(), 0, 300));
                Log::warning('pm_backlog ' . $issue['issueKey'] . ': ' . $e->getMessage());
            }
        }

        // The cursor never passes an issue that failed: next run takes it again.
        if ($failed) {
            $newest = min(array_merge($failed, $cursor ? [$cursor] : []));
        }
        Connections::patch(intval($this->conn->id), ['cursor_updated' => $newest]);

        // A child that came before its parent.
        foreach ($needParent as $bid => $parentBid) {
            $child = Links::find($host, 'issue', $bid);
            $parent = Links::find($host, 'issue', $parentBid);
            if (!$child || !$parent) {
                $this->problem(Lang::t('problem_no_parent', ['key' => $child->backlog_key ?? $bid]));
                continue;
            }
            $row = $this->issueTable->getValueModel($child->exment_id);
            if (!$row) {
                continue;
            }
            try {
                $updatedAt = $row->updated_at;
                $this->saveQuietly($row, ['parent_issue' => strval($parent->exment_id)]);
                DB::table(getDBTableName($this->issueTable))->where('id', $row->id)->update(['updated_at' => $updatedAt]);
            } catch (ValidationException $e) {
                $this->problem(Lang::t('problem_parent_refused', ['key' => $child->backlog_key, 'reason' => static::firstError($e)]));
            }
        }

        // Once a day: the issues gone from this Backlog project. Only after a
        // run that took everything, so a half-done first import is not
        // mistaken for deletions.
        $last = $this->conn->last_full_check_at ?? null;
        if (!$failed && count($todo) < $limit && (!$last || Carbon::parse($last)->lt(Carbon::now()->subDay()))) {
            try {
                $this->fullCheck($host);
                Connections::patch(intval($this->conn->id), ['last_full_check_at' => Carbon::now()]);
            } catch (\Throwable $e) {
                $this->problem(Lang::t('problem_full_check', ['message' => mb_substr($e->getMessage(), 0, 300)]));
            }
        }
    }

    /** Issues looked up one by one in a single check, at most. */
    const MAX_GONE_LOOKUPS = 50;

    /**
     * Compare every issue number in Backlog with the ones taken. One missing
     * was deleted (Backlog answers 404) or moved to another project. A moved
     * one whose new project has a connection too is left to that connection,
     * which moves it on its next run; otherwise it is marked, with a comment
     * on the Exment issue. Nothing is deleted from Exment.
     */
    protected function fullCheck(string $host): void
    {
        $present = [];
        foreach ($this->bl->issues(intval($this->map['backlog_project_id'])) as $issue) {
            $present[intval($issue['id'])] = true;
        }
        $links = DB::table('pmbl_links')->where('connection_id', $this->conn->id)
            ->where('kind', 'issue')->whereNull('gone_at')->get();
        $looked = 0;
        foreach ($links as $link) {
            if (isset($present[intval($link->backlog_id)])) {
                continue;
            }
            if (++$looked > static::MAX_GONE_LOOKUPS) {
                break;
            }
            $projectKey = null;
            try {
                $now = (array) $this->bl->get('issues/' . $link->backlog_id);
                $pid = intval($now['projectId'] ?? 0);
                if ($pid === intval($this->map['backlog_project_id'])) {
                    continue;
                }
                $other = DB::table('pmbl_connections')->where('backlog_project_id', $pid)->where('id', '!=', $this->conn->id)->get()
                    ->first(function ($c) use ($host) {
                        return strtolower(strval(parse_url($c->space_url, PHP_URL_HOST))) === $host;
                    });
                if ($other) {
                    continue;
                }
                $projectKey = explode('-', strval($now['issueKey'] ?? ''))[0];
                $reason = 'moved:' . $projectKey;
            } catch (BacklogException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
                $reason = 'deleted';
            }
            DB::table('pmbl_links')->where('id', $link->id)->update(['gone_at' => Carbon::now(), 'gone_reason' => $reason]);
            $row = $this->issueTable->getValueModel($link->exment_id);
            if ($row) {
                $this->addComment($row, $reason === 'deleted'
                    ? Lang::t('comment_deleted', ['key' => $link->backlog_key, 'date' => Carbon::now()->format('Y-m-d')])
                    : Lang::t('comment_moved', ['key' => $link->backlog_key, 'project' => $projectKey]));
            }
            $k = $reason === 'deleted' ? 'deleted' : 'moved';
            $this->counts[$k] = ($this->counts[$k] ?? 0) + 1;
        }
    }

    /**
     * @param array<string, mixed> $issue
     * @param array<int, int> $needParent
     */
    protected function one(array $issue, string $host, array &$needParent): void
    {
        $bid = intval($issue['id']);
        $link = Links::find($host, 'issue', $bid);
        $value = $this->mapIssue($issue);
        if (!empty($issue['parentIssueId']) && empty($value['parent_issue'])) {
            $needParent[$bid] = intval($issue['parentIssueId']);
        }

        $row = $link ? $this->issueTable->getValueModel($link->exment_id) : null;
        if ($link && !$row) {
            // Deleted in Exment: Backlog is the master, so it comes back.
            Links::forget($link->id);
            $link = null;
        }
        if ($row && intval($link->connection_id) !== intval($this->conn->id)) {
            // Moved in Backlog from another connection's project to this
            // one's: the Exment issue follows, with this project's statuses,
            // types, categories and milestones (mapped above).
            $this->problem(Lang::t('problem_moved_in', ['key' => $issue['issueKey'], 'id' => $row->id]));
            $row->parent_type = Pm::name('project_table');
            $row->parent_id = intval($this->conn->exment_project_id);
            DB::table('pmbl_links')->where('id', $link->id)->update(['connection_id' => $this->conn->id]);
            $this->counts['moved_in'] = ($this->counts['moved_in'] ?? 0) + 1;
        }
        if ($link && $link->gone_at) {
            // Back in Backlog (restored, or moved back).
            DB::table('pmbl_links')->where('id', $link->id)->update(['gone_at' => null, 'gone_reason' => null]);
        }
        if ($row) {
            $action = 'update';
        } else {
            $row = $this->issueTable->getValueModel();
            $row->parent_type = Pm::name('project_table');
            $row->parent_id = intval($this->conn->exment_project_id);
            $value['progress'] = $value['progress'] ?? 0;
            $action = 'add';
        }
        $this->saveIssue($row, $value, $issue, $action === 'add');

        if (!$link) {
            Links::put(intval($this->conn->id), $host, 'issue', $bid, intval($row->id), strval($issue['issueKey']));
            $link = Links::find($host, 'issue', $bid);
        }
        $this->counts[$action]++;

        // Files first, so the images the text points at have somewhere to point.
        $this->attachments($issue, $row);
        $images = new ImageLinks($this->issueTable, intval($row->id));
        $desc = strval($value['description'] ?? '');
        $fixed = $images->replace($desc);
        if ($fixed !== $desc) {
            $this->saveQuietly($row, ['description' => $fixed]);
        }

        $lastComment = $this->comments($issue, $row, $link, $images);
        Links::touch(intval($link->id), strval($issue['updated']), $lastComment);

        // Backlog's times and people, after every save of this run.
        $this->stamp($this->issueTable, intval($row->id), $issue['created'] ?? null, $issue['updated'] ?? null,
            $issue['createdUser'] ?? null, $issue['updatedUser'] ?? null);
    }

    /**
     * Save, and when ExmentPM refuses the assignee (not on the project), save
     * again without it - with no assignee on a new issue, keeping the current
     * one on an update - and say so.
     *
     * @param array<string, mixed> $value
     * @param array<string, mixed> $issue
     */
    protected function saveIssue($row, array $value, array $issue, bool $isNew): void
    {
        for ($try = 0; ; $try++) {
            try {
                $this->saveQuietly($row, $value);

                return;
            } catch (ValidationException $e) {
                $errors = $e->errors();
                if ($try < 2 && !empty($value['assignees']) && array_key_exists('value.assignees', $errors)) {
                    $this->problem(Lang::t($isNew ? 'problem_assignee_not_member_new' : 'problem_assignee_not_member_keep', [
                        'key' => $issue['issueKey'],
                    ]));
                    if ($isNew) {
                        $value['assignees'] = [];
                    } else {
                        unset($value['assignees']);
                    }
                    continue;
                }
                // The project has sub-issues switched off, or the parent is
                // somewhere this issue may not hang from: keep it unparented.
                if ($try < 2 && !empty($value['parent_issue']) && array_key_exists('value.parent_issue', $errors)) {
                    $this->problem(Lang::t('problem_parent_refused', ['key' => $issue['issueKey'], 'reason' => static::firstError($e)]));
                    $value['parent_issue'] = null;
                    continue;
                }
                throw $e;
            }
        }
    }

    protected static function firstError(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            foreach ((array) $messages as $m) {
                return strval($m);
            }
        }

        return $e->getMessage();
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function saveQuietly($row, array $value): void
    {
        $row->saved_notify(false);
        $row->setValue($value);
        $row->save();
    }

    /**
     * Comments after the last one taken. Saved with the saved events off as
     * well: saved_notify(false) does not stop the @mention notices, and a
     * Backlog comment's "@name" is not meant for anybody here.
     *
     * @param array<string, mixed> $issue
     * @return int|null the last Backlog comment id taken
     */
    protected function comments(array $issue, $row, object $link, ImageLinks $images): ?int
    {
        $last = $link->last_comment_id ? intval($link->last_comment_id) : null;
        $table = CustomTable::getEloquent(SystemTableName::COMMENT);
        foreach ($this->bl->comments(intval($issue['id']), $last) as $c) {
            $text = static::commentText($c);
            if ($text !== null) {
                $text = $images->replace($text);
                $model = $table->getValueModel();
                $model->saved_notify(false);
                $model->disable_saved_event(true);
                $model->parent_id = $row->id;
                $model->parent_type = $this->issueTable->table_name;
                $model->setValue(['comment_detail' => $text]);
                $model->save();
                $this->stamp($table, intval($model->id), $c['created'] ?? null, $c['updated'] ?? ($c['created'] ?? null),
                    $c['createdUser'] ?? null, $c['createdUser'] ?? null);
                $this->counts['comment']++;
            }
            $last = intval($c['id']);
        }

        return $last;
    }

    /** A note from the connector on an issue, quietly. */
    protected function addComment($row, string $text): void
    {
        $model = CustomTable::getEloquent(SystemTableName::COMMENT)->getValueModel();
        $model->saved_notify(false);
        $model->disable_saved_event(true);
        $model->parent_id = $row->id;
        $model->parent_type = $this->issueTable->table_name;
        $model->setValue(['comment_detail' => $text]);
        $model->save();
    }

    /** Files bigger than this stay in Backlog (the run log says so). */
    const MAX_FILE_BYTES = 20 * 1024 * 1024;

    /**
     * The issue's Backlog attachments not taken yet, as Exment documents of
     * the issue. Saved quietly: Exment's own saveDocumentModel() rings the
     * issue's notifications.
     *
     * @param array<string, mixed> $issue
     */
    protected function attachments(array $issue, $row): void
    {
        $host = $this->bl->host();
        $docTable = CustomTable::getEloquent(SystemTableName::DOCUMENT);
        foreach ((array) ($issue['attachments'] ?? []) as $a) {
            $aid = intval($a['id'] ?? 0);
            if (!$aid || Links::find($host, 'attachment', $aid)) {
                continue;
            }
            $name = strval($a['name'] ?? ('attachment-' . $aid));
            if (intval($a['size'] ?? 0) > static::MAX_FILE_BYTES) {
                $this->problem(Lang::t('problem_file_too_big', ['key' => $issue['issueKey'], 'name' => $name]));
                continue;
            }
            $bytes = $this->bl->download('issues/' . intval($issue['id']) . '/attachments/' . $aid);
            $file = \Exceedone\Exment\Model\File::storeAs(
                \Exceedone\Exment\Enums\FileType::CUSTOM_VALUE_DOCUMENT, $bytes, $this->issueTable->table_name, $name
            )->saveCustomValue($row->id, null, $this->issueTable);
            $doc = $docTable->getValueModel();
            $doc->saved_notify(false);
            $doc->parent_id = $row->id;
            $doc->parent_type = $this->issueTable->table_name;
            $doc->setValue(['file_uuid' => $file->uuid, 'document_name' => $name]);
            $doc->save();
            $this->stamp($docTable, intval($doc->id), $a['created'] ?? null, $a['created'] ?? null, $a['createdUser'] ?? null, $a['createdUser'] ?? null);
            Links::put(intval($this->conn->id), $host, 'attachment', $aid, intval($doc->id), $name, intval($issue['id']));
            $this->counts['file'] = ($this->counts['file'] ?? 0) + 1;
        }
    }

    /**
     * created_at / updated_at and the people, straight in the table: a model
     * save would stamp now and the run's user over them.
     *
     * @param mixed $createdUser Backlog user
     * @param mixed $updatedUser Backlog user
     */
    protected function stamp($table, int $id, ?string $created, ?string $updated, $createdUser, $updatedUser): void
    {
        $set = [];
        if ($t = Pm::localTime($created)) {
            $set['created_at'] = $t;
        }
        if ($t = Pm::localTime($updated)) {
            $set['updated_at'] = $t;
        }
        if ($u = $this->userId($createdUser)) {
            $set['created_user_id'] = $u;
        }
        if ($u = $this->userId($updatedUser)) {
            $set['updated_user_id'] = $u;
        }
        if ($set) {
            DB::table(getDBTableName($table))->where('id', $id)->update($set);
        }
    }

    /** @param mixed $u Backlog user */
    protected function userId($u): ?int
    {
        if (!is_array($u) || !isset($u['id'])) {
            return null;
        }
        $id = $this->map['users'][strval($u['id'])] ?? null;

        return $id ? intval($id) : null;
    }

    /**
     * Backlog issue -> pm_issue value (backlog_sync's map_issue).
     *
     * @param array<string, mixed> $issue
     * @return array<string, mixed>
     */
    protected function mapIssue(array $issue): array
    {
        $key = strval($issue['issueKey']);
        $m = $this->map;
        $pick = function (string $kind, string $name, string $defaultKey) use ($key, $m) {
            $v = $m[$kind][$name] ?? null;
            if (!$v) {
                $v = $m[$defaultKey] ?? null;
                $this->problem(Lang::t('problem_no_map', ['key' => $key, 'kind' => Lang::t('kind_' . $kind), 'name' => $name]));
            }

            return $v ? strval($v) : null;
        };
        $value = [
            'subject' => strval($issue['summary'] ?? ''),
            'issue_type' => $pick('issue_type', strval($issue['issueType']['name'] ?? ''), 'default_issue_type'),
            'status' => $pick('status', strval($issue['status']['name'] ?? ''), 'default_status'),
            'priority' => !empty($issue['priority']['name']) && !empty($m['priority'][$issue['priority']['name']])
                ? strval($m['priority'][$issue['priority']['name']]) : null,
            'description' => strval($issue['description'] ?? ''),
            'start_date' => substr(strval($issue['startDate'] ?? ''), 0, 10) ?: null,
            'due_date' => substr(strval($issue['dueDate'] ?? ''), 0, 10) ?: null,
            'estimate_h' => $issue['estimatedHours'] ?? null,
            'actual_h' => $issue['actualHours'] ?? null,
        ];
        $res = $issue['resolution'] ?? null;
        if (isset($this->cols['resolution'])) {
            $value['resolution'] = $res && !empty($m['resolution'][$res['name']]) ? strval($m['resolution'][$res['name']]) : null;
            if ($res && $value['resolution'] === null) {
                $this->problem(Lang::t('problem_no_map', ['key' => $key, 'kind' => Lang::t('kind_resolution'), 'name' => $res['name']]));
            }
        }
        if (isset($this->cols['source_key'])) {
            $value += [
                'source_key' => $key,
                'source_url' => $this->bl->issueUrl($key),
                'source_created_by' => static::userLabel($issue['createdUser'] ?? null),
                'source_created_at' => Pm::localTime($issue['created'] ?? null),
                'source_updated_by' => !empty($issue['updatedUser']) ? static::userLabel($issue['updatedUser']) : null,
                'source_updated_at' => Pm::localTime($issue['updated'] ?? null),
                'custom_fields' => static::customFieldsText($issue) ?: null,
            ];
        }
        $a = $issue['assignee'] ?? null;
        if (isset($this->cols['source_assignees'])) {
            $value['source_assignees'] = $a ? static::userLabel($a) : null;
        }
        $value['assignees'] = [];
        if ($a) {
            $uid = $m['users'][strval($a['id'])] ?? null;
            if ($uid) {
                $value['assignees'] = [strval($uid)];
            } else {
                $this->problem(Lang::t('problem_no_user', ['key' => $key, 'name' => static::userLabel($a)]));
            }
        }
        $ids = function (string $kind, $list) use ($m) {
            $out = [];
            foreach ((array) $list as $x) {
                $id = $m[$kind][$x['name'] ?? ''] ?? null;
                if ($id) {
                    $out[] = strval($id);
                }
            }

            return $out;
        };
        $value['category'] = $ids('category', $issue['category'] ?? []);
        $ms = $ids('milestone', $issue['milestone'] ?? []);
        // Exment PM has one milestone; the others are kept in custom_fields.
        $value['milestone'] = $ms[0] ?? null;
        $value['versions'] = $ids('milestone', $issue['versions'] ?? []);
        $value['parent_issue'] = null;
        if (!empty($issue['parentIssueId'])) {
            $parent = Links::find($this->bl->host(), 'issue', intval($issue['parentIssueId']));
            $value['parent_issue'] = $parent ? strval($parent->exment_id) : null;
        }

        return $value;
    }

    /** @param mixed $u */
    public static function userLabel($u): string
    {
        return is_array($u) && !empty($u['name']) ? strval($u['name']) : '（不明）';
    }

    /**
     * @param array<string, mixed> $issue
     */
    public static function customFieldsText(array $issue): string
    {
        $lines = [];
        foreach ((array) ($issue['customFields'] ?? []) as $f) {
            $v = $f['value'] ?? null;
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_array($v) && array_key_exists(0, $v)) {
                $v = implode(', ', array_map(function ($x) {
                    return is_array($x) ? strval($x['name'] ?? '') : strval($x);
                }, $v));
            } elseif (is_array($v)) {
                $v = strval($v['name'] ?? ($v['id'] ?? ''));
            }
            $lines[] = ($f['name'] ?? '') . ': ' . $v;
        }
        $extra = array_map(function ($x) {
            return strval($x['name'] ?? '');
        }, array_slice((array) ($issue['milestone'] ?? []), 1));
        if ($extra) {
            $lines[] = 'マイルストーン（2 つ目から）: ' . implode(', ', $extra);
        }

        return implode("\n", $lines);
    }

    /**
     * A Backlog comment as text: who and when, what changed, what was said.
     * null when there is nothing to show (an attachment or notice only).
     *
     * @param array<string, mixed> $c
     */
    public static function commentText(array $c): ?string
    {
        $head = '【Backlog】' . static::userLabel($c['createdUser'] ?? null) . ' ' . substr(strval(Pm::localTime($c['created'] ?? null)), 0, 16);
        $changes = [];
        foreach ((array) ($c['changeLog'] ?? []) as $ch) {
            $f = strval($ch['field'] ?? '');
            if ($f === 'attachment' || $f === 'notification') {
                continue;
            }
            // The whole text before and after is on Backlog and in the issue
            // itself; repeating it made one thread of 132 comments 4MB.
            if (in_array($f, static::LONG_FIELDS, true)) {
                $changes[] = '- ' . (static::FIELD_LABELS[$f] ?? $f) . ': 変更しました';
                continue;
            }
            $changes[] = '- ' . (static::FIELD_LABELS[$f] ?? $f) . ': '
                . (strval($ch['originalValue'] ?? '') ?: '（なし）') . ' → ' . (strval($ch['newValue'] ?? '') ?: '（なし）');
        }
        $body = trim(strval($c['content'] ?? ''));
        if ($body === '' && !$changes) {
            return null;
        }
        $parts = [$head];
        if ($changes) {
            $parts[] = implode("\n", $changes);
        }
        if ($body !== '') {
            $parts[] = $body;
        }

        return implode("\n\n", $parts);
    }

    protected function problem(string $line): void
    {
        if (count($this->problems) < static::MAX_PROBLEMS) {
            $this->problems[] = $line;
        }
    }
}
