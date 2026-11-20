<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomValueModelScope;
use Illuminate\Support\Facades\DB;

/**
 * The integrity rules a project, an issue, a wiki page and an issue link
 * have to satisfy.
 *
 * Both main tables carry a self reference - a project may sit under a parent
 * project, an issue may be a sub-task of another issue - and a plain
 * select_table column will happily accept any row at all, including the row
 * being edited. Nothing else in the stack notices, because Exment has no
 * concept of a graph: it stores an id and moves on. So a project can be its
 * own parent, two projects can point at each other, and an issue can be a
 * sub-task of an issue in a different project. Every one of those was
 * reachable before this class existed.
 *
 * The rules live here rather than in a controller because a row can be
 * written from many places - the portal, the plugin's lightapi, the Exment
 * admin form, the REST API, a CSV import, a revision restore - and a rule
 * that only one of them obeys is not a rule. PluginValidator and PluginEvent
 * hand every one of those paths through this class.
 *
 * Each check returns messages keyed by column name, which is the shape
 * Exment's validator expects, so a failure lands on the right field.
 */
class PortalRules
{
    /**
     * How far an ancestor walk goes before giving up.
     *
     * Data written before these rules existed may already contain a loop, and
     * the walk that looks for one must not become the thing that hangs the
     * request.
     */
    const MAX_DEPTH = 64;

    /** Backlog shaped project key: starts with a letter, then letters, digits or underscore. */
    const CODE_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    const CODE_MAX = 32;

    /**
     * How long a save waits, in seconds, for another save of the same project
     * key to finish before it gives up and asks the user to try again.
     */
    const CODE_LOCK_WAIT = 5;

    /**
     * Whether the rules are standing aside for a bulk maintenance write.
     *
     * @var bool
     */
    protected static $suspended = false;

    /**
     * The named database locks this process holds on project keys, until the
     * save that took them is committed.
     *
     * @var array<string, bool>
     */
    protected static $codeLocks = [];

    /**
     * Run $work with every rule off.
     *
     * The schema upgrade rewrites stored rows in bulk - issues of archived
     * projects included, and legacy projects that would not pass the rules
     * as they stand. Refusing those writes would leave an upgrade half done,
     * which is worse than any state it was going to fix.
     *
     * @param callable $work
     * @return mixed whatever $work returns
     */
    public static function suspend(callable $work)
    {
        static::$suspended = true;
        try {
            return $work();
        } finally {
            static::$suspended = false;
        }
    }

    /**
     * Validate the value of one row about to be written.
     *
     * Only what the write changes is judged. Both callers hand over the whole
     * value - the saving event the merged row, the admin form every field it
     * rendered - and judging values nobody touched refused saves for reasons
     * the person saving could not see: a sub-issue could not be saved at all
     * once sub-tasking was turned off, and an issue of an archived project
     * could not even be deleted, because a soft delete is a save. A rule that
     * every write obeys holds for the data without re-reading it each time.
     *
     * @param CustomTable $table
     * @param array<string, mixed> $input the submitted value array
     * @param CustomValue|null $current the row being edited, or the new row
     *        being saved; null on a create the caller has no model for
     * @return array<string, string> column name => message
     */
    public static function validate(CustomTable $table, array $input, ?CustomValue $current = null): array
    {
        if (static::$suspended) {
            return [];
        }

        $def = PortalDefinition::config();
        $kind = static::kindOf($table, $def);
        if ($kind === null || $kind === 'link') {
            return [];
        }

        // An issue on its way to another project is checked whole: a status
        // nobody touched still names the old project's list, and has to be
        // looked at against the new one.
        if ($current && $current->exists && !static::moving($current)) {
            $input = static::changed($input, PortalMembership::storedValue($current));
            if (empty($input)) {
                return [];
            }
        }

        if ($kind === 'project') {
            return static::project($table, $input, $current, $def);
        }
        if ($kind === 'issue') {
            return static::issue($table, $input, $current, $def);
        }

        return static::wiki($current, $def);
    }

    /**
     * Every rule a model about to be saved has to pass: the value rules of
     * validate(), plus the ones only the model can answer - which project an
     * issue or a wiki page sits in, and which issues a link joins.
     *
     * Called from the saving event, and from the guard that covers revision
     * restores, which Exment saves without asking any plugin.
     *
     * @param CustomValue $model
     * @return array<string, string> column name => message
     */
    public static function validateSave(CustomValue $model): array
    {
        if (static::$suspended) {
            return [];
        }

        $table = $model->custom_table;
        if (!isset($table)) {
            return [];
        }

        $def = PortalDefinition::config();
        $kind = static::kindOf($table, $def);
        if ($kind === 'link') {
            return static::link($table, $model, $def);
        }
        if ($kind === 'issue' || $kind === 'wiki') {
            $errors = static::placement($table, $model, $kind, $def);
            if (!empty($errors)) {
                return $errors;
            }
        }

        $value = $model->getAttribute('value');

        return static::validate($table, is_array($value) ? $value : [], $model);
    }

    /**
     * Whether rows of this table nest exactly one level.
     *
     * An issue does: a sub-task's parent may not itself be a sub-task, which
     * is Backlog's rule and parentIssue()'s. A project does not - it nests as
     * deep as anyone cares to go and only a loop is refused.
     *
     * Public because the rule decides what a parent picker should offer, and
     * a picker that offers what the save is about to reject is worse than one
     * that offers nothing.
     *
     * @param CustomTable $table
     * @return bool
     */
    public static function nestsOneLevel(CustomTable $table): bool
    {
        $def = PortalDefinition::config();

        return strval($table->table_name) === strval(array_get($def, 'issue_table'));
    }

    /**
     * Refuse a delete that would strand rows pointing at the one going away,
     * or that is not this user's to make.
     *
     * Exment does not cascade a select_table reference, so deleting a parent
     * leaves its children holding an id that resolves to nothing. Blocking is
     * recoverable - the user re-parents and tries again - whereas a silent
     * orphan is discovered months later.
     *
     * @param CustomValue $row
     * @return string|null the reason, or null when the delete may proceed
     */
    public static function validateDestroy(CustomValue $row): ?string
    {
        if (static::$suspended) {
            return null;
        }

        $table = $row->custom_table;
        if (!isset($table)) {
            return null;
        }

        $def = PortalDefinition::config();
        $kind = static::kindOf($table, $def);

        if ($kind === 'project') {
            // A project goes with every issue and page in it. Backlog leaves
            // that to the project's administrator, and so does the portal's
            // own delete button; the admin grid and the REST API ask here.
            if (\Exment::user() !== null && !PortalMembership::canManage($row)) {
                return PortalDefinition::trans('rules.delete_manager_only');
            }

            $children = static::countPointingAt($table, 'parent_project', $row->id);
            if ($children > 0) {
                return PortalDefinition::trans('rules.project_has_children', ['count' => $children]);
            }
        }

        if ($kind === 'issue') {
            $children = static::countPointingAt($table, static::column($def, 'issue.parent', 'parent_issue'), $row->id);
            if ($children > 0) {
                return PortalDefinition::trans('rules.issue_has_children', ['count' => $children]);
            }
        }

        if ($kind === 'link') {
            $refusal = static::linkRefusal($def, (array) $row->value, null);

            return $refusal === null ? null : PortalDefinition::trans('rules.' . $refusal);
        }

        return null;
    }

    /**
     * Whether the signed-in user may make this change to the issue links:
     * add one ($old null), repoint one, or remove one ($new null).
     *
     * A link belongs to no project, so no share can say who may touch it -
     * the issues it joins do. Adding a link edits the issue it is added on
     * (from) and needs only sight of the other one (to). Removing one is
     * allowed from either end, as long as both are in sight: the link shows
     * on both issues and either side may tidy it up. A repoint is a removal
     * and an addition at once.
     *
     * Fails closed: a link whose columns cannot be read, or whose ends do not
     * resolve to two different live issues, is refused.
     *
     * @param array<string, mixed> $def PortalDefinition::config()
     * @param array<string, mixed>|null $old the link as stored, null on create
     * @param array<string, mixed>|null $new the link as it will be, null on delete
     * @return string|null 'link_invalid' or 'link_forbidden' - a key under
     *         rules and under api_error alike - or null when allowed
     */
    public static function linkRefusal(array $def, ?array $old, ?array $new): ?string
    {
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $from = strval(array_get($def, 'related_row.from'));
        $to = strval(array_get($def, 'related_row.to'));
        if (!isset($issueTable) || $from === '' || $to === '') {
            return 'link_invalid';
        }

        $ends = function (array $value) use ($issueTable, $from, $to) {
            return [
                static::findRow($issueTable, static::idOf(array_get($value, $from))),
                static::findRow($issueTable, static::idOf(array_get($value, $to))),
            ];
        };

        $newEnds = null;
        if ($new !== null) {
            $newEnds = $ends($new);
            if (!$newEnds[0] || !$newEnds[1] || intval($newEnds[0]->id) === intval($newEnds[1]->id)) {
                return 'link_invalid';
            }
        }

        // A batch or the schema upgrade: no person to ask about.
        if (\Exment::user() === null) {
            return null;
        }

        $reads = function ($issue) {
            return $issue !== null && $issue->enableAccess() === true;
        };
        $edits = function ($issue) {
            return $issue !== null && PortalDefinition::enableEdit($issue) === true;
        };

        if ($old !== null) {
            [$a, $b] = $ends($old);
            if (!$reads($a) || !$reads($b) || (!$edits($a) && !$edits($b))) {
                return 'link_forbidden';
            }
        }
        if ($newEnds !== null && (!$edits($newEnds[0]) || !$reads($newEnds[1]))) {
            return 'link_forbidden';
        }

        return null;
    }

    /**
     * Whether another live link already joins the same two issues, whichever
     * way round.
     *
     * One link per pair, the way Redmine has it: a second link between the
     * same two issues says nothing the first does not, and only doubles what
     * both issue screens list.
     *
     * @param CustomTable $table the link table
     * @param array<string, mixed> $value the link as it will be
     * @param int|null $exceptId the link being changed, which is not a duplicate of itself
     * @return bool
     */
    public static function linkTaken(CustomTable $table, array $value, ?int $exceptId = null): bool
    {
        $def = PortalDefinition::config();
        $fromName = static::column($def, 'related_row.from', 'from_issue');
        $toName = static::column($def, 'related_row.to', 'to_issue');
        $fromColumn = CustomColumn::getEloquent($fromName, $table);
        $toColumn = CustomColumn::getEloquent($toName, $table);
        $a = static::idOf(array_get($value, $fromName));
        $b = static::idOf(array_get($value, $toName));
        if (!isset($fromColumn) || !isset($toColumn) || $a === null || $b === null) {
            return false;
        }

        $fromKey = $fromColumn->getQueryKey();
        $toKey = $toColumn->getQueryKey();
        $query = $table->getValueModel()->newQuery()
            ->withoutGlobalScope(CustomValueModelScope::class)
            ->where(function ($query) use ($fromKey, $toKey, $a, $b) {
                $query->where(function ($one) use ($fromKey, $toKey, $a, $b) {
                    $one->where($fromKey, strval($a))->where($toKey, strval($b));
                })->orWhere(function ($one) use ($fromKey, $toKey, $a, $b) {
                    $one->where($fromKey, strval($b))->where($toKey, strval($a));
                });
            });
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Let go of the project key locks once the save that took them can be
     * seen by everyone else: right away when no transaction is open, else
     * when it commits. Called from the saved event.
     */
    public static function saved(): void
    {
        if (empty(static::$codeLocks)) {
            return;
        }

        try {
            DB::afterCommit(function () {
                static::releaseCodeLocks();
            });
        } catch (\Throwable $e) {
            static::releaseCodeLocks();
        }
    }

    /**
     * A transaction was rolled back. Once none is open any more, a save that
     * took a project key lock is not coming, and the lock has nothing left
     * to guard - without this it stayed until the connection closed, which
     * for a queue worker is not the end of the request.
     */
    public static function rolledBack(): void
    {
        if (!empty(static::$codeLocks) && DB::transactionLevel() === 0) {
            static::releaseCodeLocks();
        }
    }

    // ---- project --------------------------------------------------------

    /**
     * @param CustomTable $table
     * @param array<string, mixed> $input the values this write changes
     * @param CustomValue|null $current
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function project(CustomTable $table, array $input, ?CustomValue $current, array $def): array
    {
        // Backlog keeps a project's settings - its key, its dates and
        // switches, and who is on it - with the project's administrator. The
        // members column grants edit on the project row only because Exment
        // asks for an editable parent before anyone may file an issue under
        // it, so "may edit the row" says nothing about who runs the project:
        // without this a member could turn the AI or Git on, archive the
        // project, or take the manager off it.
        if ($current && $current->exists && !PortalMembership::canManage($current)) {
            $column = strval(array_key_first($input));
            $people = [];
            foreach (['manager', 'members', 'viewers', 'member_orgs', 'viewer_orgs'] as $key) {
                $people[] = static::column($def, 'project.' . $key, '');
            }
            $message = in_array($column, $people, true) ? 'rules.people_manager_only' : 'rules.project_manager_only';

            return [$column => PortalDefinition::trans($message)];
        }

        $errors = [];

        // -- project key ------------------------------------------------
        $codeColumn = static::column($def, 'project.key', 'project_code');
        if (array_key_exists($codeColumn, $input)) {
            $raw = $input[$codeColumn];
            $code = is_scalar($raw) ? trim(strval($raw)) : '';

            if ($code === '') {
                // Only demanded when the field was actually submitted, so a
                // partial update that never mentions the key is left alone.
                $errors[$codeColumn] = PortalDefinition::trans('rules.code_required');
            } elseif (mb_strlen($code) > self::CODE_MAX) {
                $errors[$codeColumn] = PortalDefinition::trans('rules.code_too_long', ['max' => self::CODE_MAX]);
            } elseif (!preg_match(self::CODE_PATTERN, $code)) {
                $errors[$codeColumn] = PortalDefinition::trans('rules.code_format');
            } elseif (!static::lockCode($code)) {
                $errors[$codeColumn] = PortalDefinition::trans('rules.code_busy');
            } elseif (static::codeTaken($table, $codeColumn, $code, $current)) {
                $errors[$codeColumn] = PortalDefinition::trans('rules.code_taken', ['code' => $code]);
            }
        }

        // -- parent project ---------------------------------------------
        if (array_key_exists('parent_project', $input)) {
            $parent = static::idOf($input['parent_project']);
            $self = ($current && $current->exists) ? intval($current->id) : null;

            if ($parent !== null) {
                if ($self === $parent) {
                    $errors['parent_project'] = PortalDefinition::trans('rules.parent_self');
                } elseif (!static::rowExists($table, $parent)) {
                    $errors['parent_project'] = PortalDefinition::trans('rules.parent_missing');
                } elseif ($self !== null && static::wouldLoop($table, 'parent_project', $parent, $self)) {
                    $errors['parent_project'] = PortalDefinition::trans('rules.parent_loop');
                }
            }
        }

        $end = static::column($def, 'project.end', 'end_date');
        $dates = static::dateOrder($input, $current, static::column($def, 'project.start', 'start_date'), $end);
        if ($dates) {
            $errors[$end] = $dates;
        }

        return $errors;
    }

    // ---- issue ----------------------------------------------------------

    /**
     * @param CustomTable $table
     * @param array<string, mixed> $input the values this write changes
     * @param CustomValue|null $current
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function issue(CustomTable $table, array $input, ?CustomValue $current, array $def): array
    {
        $errors = [];

        // Backlog turns an archived project read-only: its issues neither
        // change nor grow until somebody unarchives the project. The project
        // is the Exment parent relation, not a column, so this is only
        // checkable when the caller supplied the model - the saving event
        // always does, which is what makes the rule hold on every path.
        $projectId = $current ? $current->parent_id : null;
        if (!is_nullorempty($projectId) && static::projectArchived($def, $projectId)) {
            // Keyed on the subject column so the message lands on a field
            // every form actually renders.
            $errors[static::column($def, 'issue.subject', 'subject')] = PortalDefinition::trans('rules.project_archived');

            return $errors;
        }

        $parentColumn = static::column($def, 'issue.parent', 'parent_issue');
        if (array_key_exists($parentColumn, $input)) {
            $message = static::parentIssue($table, $input[$parentColumn], $current, $def);
            if ($message) {
                $errors[$parentColumn] = $message;
            }
        }

        $due = static::column($def, 'issue.due', 'due_date');
        $dates = static::dateOrder($input, $current, static::column($def, 'issue.start', 'start_date'), $due);
        if ($dates) {
            $errors[$due] = $dates;
        }

        $progress = static::column($def, 'issue.progress', 'progress');
        if (array_key_exists($progress, $input)) {
            $raw = $input[$progress];
            if ($raw !== null && $raw !== '' && (!is_numeric($raw) || $raw < 0 || $raw > 100)) {
                $errors[$progress] = PortalDefinition::trans('rules.progress_range');
            }
        }

        if (!is_nullorempty($projectId)) {
            $errors += static::projectLists($table, $input, $projectId, $def);
            $errors += static::people($input, $current, $projectId, $def);
        }

        return $errors;
    }

    /**
     * Backlog's sub-task rules, which are stricter than a bare id reference.
     *
     * A sub-task belongs to the same project as its parent, the hierarchy is
     * exactly one level deep, and the project has to have sub-tasking turned
     * on in the first place.
     *
     * @param CustomTable $table
     * @param mixed $raw the parent issue value being written
     * @param CustomValue|null $current
     * @param array<string, mixed> $def
     * @return string|null
     */
    protected static function parentIssue(CustomTable $table, $raw, ?CustomValue $current, array $def): ?string
    {
        $parentId = static::idOf($raw);
        if ($parentId === null) {
            return null;
        }

        $self = ($current && $current->exists) ? intval($current->id) : null;
        if ($self === $parentId) {
            return PortalDefinition::trans('rules.parent_self');
        }

        // Deleted rows included, for the same reason as rowExists(): a child
        // whose parent went to the trash first must not have its own field
        // called dangling.
        $parent = static::findRow($table, $parentId, true);
        if (!$parent) {
            return PortalDefinition::trans('rules.parent_missing');
        }

        // The project an issue belongs to is the Exment parent relation, not a
        // column, so on create it is read off the model the caller prepared.
        $project = $current ? $current->parent_id : null;
        if (!is_nullorempty($project) && strval($parent->parent_id) !== strval($project)) {
            return PortalDefinition::trans('rules.parent_other_project');
        }

        $parentColumn = static::column($def, 'issue.parent', 'parent_issue');
        if (static::idOf(array_get((array) $parent->value, $parentColumn)) !== null) {
            return PortalDefinition::trans('rules.parent_is_child');
        }

        if ($self !== null && static::countPointingAt($table, $parentColumn, $self) > 0) {
            return PortalDefinition::trans('rules.parent_has_children');
        }

        if (!is_nullorempty($project) && !static::subtaskingEnabled($def, $project)) {
            return PortalDefinition::trans('rules.subtasking_off');
        }

        return null;
    }

    /**
     * The per-project lists an issue picks from have to be its own project's.
     *
     * Status and type rows hang off a project; milestones and categories name
     * theirs in a column. Exment only checks that a picked row can be read,
     * and every portal user can read every project's lists, so an issue
     * could be saved with another project's status - and drop out of its own
     * board, taking its done flag from a stranger's list. A row that belongs
     * to no project at all, a list the whole space shares, stays allowed.
     *
     * @param CustomTable $table
     * @param array<string, mixed> $input
     * @param mixed $projectId
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function projectLists(CustomTable $table, array $input, $projectId, array $def): array
    {
        $errors = [];
        $projectTable = strval(array_get($def, 'project_table'));
        $message = PortalDefinition::trans('rules.other_project_value');

        // Lists that hang off the project through Exment's parent relation.
        foreach (['issue.status', 'issue.type'] as $path) {
            $column = static::column($def, $path, '');
            if ($column === '' || !array_key_exists($column, $input)) {
                continue;
            }
            $target = static::targetTable($table, $column);
            $ids = PortalMembership::ids($input[$column]);
            if (!isset($target) || empty($ids)) {
                continue;
            }

            $foreign = $target->getValueModel()->newQueryWithoutScopes()
                ->whereIn('id', $ids)
                ->whereNotNull('parent_id')
                ->where(function ($query) use ($projectTable, $projectId) {
                    $query->where('parent_type', '<>', $projectTable)
                        ->orWhere('parent_id', '<>', $projectId);
                })
                ->exists();
            if ($foreign) {
                $errors[$column] = $message;
            }
        }

        // Lists that name their project in a column of their own.
        foreach ([['issue.milestone', 'milestone'], ['issue.category', 'category'], ['issue_extra.versions', 'milestone']] as [$path, $role]) {
            $column = static::column($def, $path, '');
            if ($column === '' || !array_key_exists($column, $input)) {
                continue;
            }
            $target = static::targetTable($table, $column);
            $owner = static::column($def, $role . '.project', '');
            $ids = PortalMembership::ids($input[$column]);
            if (!isset($target) || $owner === '' || empty($ids)
                || $target->table_name !== strval(array_get($def, $role . '_table'))) {
                continue;
            }

            foreach ($target->getValueModel()->newQueryWithoutScopes()->whereIn('id', $ids)->get() as $row) {
                $belongs = static::idOf(array_get((array) $row->value, $owner));
                if ($belongs !== null && $belongs !== intval($projectId)) {
                    $errors[$column] = $message;
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * The people an issue names have to be people of its project.
     *
     * Exment gives each assignee edit rights on the issue, and tells each
     * assignee and each user to notify about every change - its subject, and
     * on a comment the comment itself. Naming someone who is not on the
     * project hands them both. An assignee has to be able to do the work, so
     * the manager or a member; anyone on the project may be told about it.
     *
     * Only the names this write adds are checked: someone who has since left
     * the project does not block an edit that leaves them where they are.
     * A project that names nobody does not use membership at all.
     *
     * @param array<string, mixed> $input
     * @param CustomValue|null $current
     * @param mixed $projectId
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function people(array $input, ?CustomValue $current, $projectId, array $def): array
    {
        $checks = [
            [static::column($def, 'issue.assignees', ''), PortalMembership::EDITORS, 'rules.assignee_not_member'],
            [static::column($def, 'issue_extra.notified_users', ''), null, 'rules.notify_not_member'],
            // Schema 27. A sub-assignee does the work too; a manager or a
            // reviewer only has to be on the project.
            [static::column($def, 'issue_extra.sub_assignees', ''), PortalMembership::EDITORS, 'rules.sub_assignee_not_member'],
            [static::column($def, 'issue_extra.managers', ''), null, 'rules.manager_not_member'],
            [static::column($def, 'issue_extra.reviewers', ''), null, 'rules.reviewer_not_member'],
        ];

        $asked = false;
        foreach ($checks as [$column]) {
            $asked = $asked || ($column !== '' && array_key_exists($column, $input));
        }
        if (!$asked) {
            return [];
        }

        $project = static::findRow(strval(array_get($def, 'project_table')), $projectId, true);
        $roles = $project ? PortalMembership::roles((array) $project->value) : [];
        if (empty($roles)) {
            return [];
        }

        // Moving to another project, everyone named is new to that project.
        $stored = ($current && $current->exists && !static::moving($current)) ? PortalMembership::storedValue($current) : [];
        $errors = [];
        foreach ($checks as [$column, $allowed, $message]) {
            if ($column === '' || !array_key_exists($column, $input)) {
                continue;
            }
            $added = array_diff(PortalMembership::ids($input[$column]), PortalMembership::ids($stored[$column] ?? null));
            foreach ($added as $id) {
                $role = $roles[$id] ?? null;
                if ($role === null || ($allowed !== null && !in_array($role, $allowed, true))) {
                    $errors[$column] = PortalDefinition::trans($message);
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Whether the project still allows sub-tasks.
     *
     * A project created before the flag existed has no value stored, and that
     * is read as "on" - turning the feature off for every legacy project
     * would be a surprise, not a safeguard.
     *
     * @param array<string, mixed> $def
     * @param mixed $projectId
     * @return bool
     */
    protected static function subtaskingEnabled(array $def, $projectId): bool
    {
        $project = static::findRow(strval(array_get($def, 'project_table')), $projectId, true);
        if (!$project) {
            return true;
        }

        $raw = array_get((array) $project->value, static::column($def, 'project_extra.subtasking', 'subtasking'));
        if ($raw === null || $raw === '') {
            return true;
        }

        return boolval($raw);
    }

    // ---- wiki -----------------------------------------------------------

    /**
     * An archived project is read-only for its wiki too.
     *
     * @param CustomValue|null $current
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function wiki(?CustomValue $current, array $def): array
    {
        $projectId = $current ? $current->parent_id : null;
        if (!is_nullorempty($projectId) && static::projectArchived($def, $projectId)) {
            return [static::column($def, 'wiki.title', 'title') => PortalDefinition::trans('rules.wiki_archived')];
        }

        return [];
    }

    // ---- placement and links --------------------------------------------

    /**
     * Whether this save takes a stored row out of the project it was in.
     *
     * @param CustomValue $model
     * @return bool
     */
    public static function moving(CustomValue $model): bool
    {
        return $model->exists
            && !is_nullorempty($model->getOriginal('parent_id'))
            && ($model->isDirty('parent_id') || $model->isDirty('parent_type'));
    }

    /**
     * Whether the save that is about to run - an API or form request naming a
     * parent - moves this issue to another project. The validator plugin runs
     * before the new parent is set on the model, so it can only tell from the
     * request; it leaves the checks to the saving event, which sees the model
     * with its new project and runs every rule against that one.
     *
     * @param CustomTable $table
     * @param CustomValue|null $current
     * @return bool
     */
    public static function moveRequested(CustomTable $table, ?CustomValue $current): bool
    {
        if (!$current || !$current->exists || static::kindOf($table, PortalDefinition::config()) !== 'issue') {
            return false;
        }
        $request = request();
        if (!$request || !$request->has('parent_id')) {
            return false;
        }
        $to = $request->input('parent_id');

        return is_scalar($to) && strval($to) !== '' && strval($to) !== strval($current->parent_id);
    }

    /**
     * Why this issue may not leave its project, or null when it may.
     *
     * A sub-task belongs to its parent's project and a parent takes its
     * sub-tasks' project with it, so neither moves alone - Backlog asks to
     * take the parent off first. The project it leaves has to let the caller
     * edit it, as the one it goes to does (placement() checks that one), and
     * neither may be archived.
     *
     * @param CustomTable $table
     * @param CustomValue $model
     * @param array<string, mixed> $def
     * @return string|null
     */
    protected static function moveRefusal(CustomTable $table, CustomValue $model, array $def): ?string
    {
        $projectTable = strval(array_get($def, 'project_table'));
        if (strval($model->getOriginal('parent_type')) !== $projectTable) {
            return PortalDefinition::trans('rules.parent_change');
        }

        $parentColumn = static::column($def, 'issue.parent', 'parent_issue');
        if (static::countPointingAt($table, $parentColumn, $model->id) > 0) {
            return PortalDefinition::trans('rules.move_has_children');
        }

        $from = $model->getOriginal('parent_id');
        if (static::projectArchived($def, $from)) {
            return PortalDefinition::trans('rules.project_archived');
        }
        if (\Exment::user() === null) {
            return null;
        }
        $old = static::findRow($projectTable, $from, true);
        if ($old && PortalDefinition::enableEdit($old, true) !== true) {
            return PortalDefinition::trans('rules.parent_readonly');
        }

        return null;
    }

    /**
     * Which project an issue or a wiki page sits in.
     *
     * The project is Exment's parent relation, not a column, so nothing in
     * the value says where a row lives and no column rule can guard it:
     *
     * - A new row needs one. A row without a project shows on no portal
     *   screen, and the same-project sub-task rule has nothing to compare.
     * - Whoever files a row into a project must be allowed to write there.
     *   Exment's own REST API checks only that the parent can be read, so a
     *   project's viewer could file issues into it.
     * - Once filed, a row stays where it is. Moving an issue carries the old
     *   project's statuses, types and milestones along and strands its
     *   sub-issues; the portal never offers it. A row that has no project
     *   yet may still be given one, under the rules for a new row.
     *
     * @param CustomTable $table
     * @param CustomValue $model
     * @param string $kind issue | wiki
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function placement(CustomTable $table, CustomValue $model, string $kind, array $def): array
    {
        $projectTable = strval(array_get($def, 'project_table'));
        $key = $kind === 'issue'
            ? static::column($def, 'issue.subject', 'subject')
            : static::column($def, 'wiki.title', 'title');

        if ($model->exists) {
            if (!$model->isDirty('parent_id') && !$model->isDirty('parent_type')) {
                return [];
            }
            if (!is_nullorempty($model->getOriginal('parent_id'))) {
                // Backlog moves an issue to another project; a wiki page
                // stays where it was written.
                if ($kind !== 'issue') {
                    return [$key => PortalDefinition::trans('rules.parent_change')];
                }
                $refusal = static::moveRefusal($table, $model, $def);
                if ($refusal !== null) {
                    return [$key => $refusal];
                }
                // The new project is then checked like the project of a new
                // issue, below; validate() checks every value against it.
            }
        }

        if (is_nullorempty($model->parent_id) || strval($model->parent_type) !== $projectTable) {
            return [$key => PortalDefinition::trans('rules.parent_required')];
        }

        // A batch or the schema upgrade: no person to ask about.
        if (\Exment::user() === null) {
            return [];
        }

        $project = static::findRow($projectTable, $model->parent_id);
        if (!$project) {
            return [$key => PortalDefinition::trans('rules.parent_missing')];
        }

        // Exment creates, edits and deletes a child row only where its parent
        // is editable, unless the table turns editable_with_parent off.
        $allowed = boolval($table->getOption('editable_with_parent') ?? 1)
            ? PortalDefinition::enableEdit($project, true)
            : $project->enableAccess();

        return $allowed === true ? [] : [$key => PortalDefinition::trans('rules.parent_readonly')];
    }

    /**
     * A link about to be written: who may, and that it is not there already
     * (see linkRefusal() and linkTaken()).
     *
     * @param CustomTable $table
     * @param CustomValue $model
     * @param array<string, mixed> $def
     * @return array<string, string>
     */
    protected static function link(CustomTable $table, CustomValue $model, array $def): array
    {
        $fromName = static::column($def, 'related_row.from', 'from_issue');
        $value = (array) $model->getAttribute('value');

        $old = null;
        if ($model->exists) {
            $old = PortalMembership::storedValue($model);
            // A soft delete, a touch: nothing about the link moves.
            if (empty(static::changed($value, $old))) {
                return [];
            }
        }

        $refusal = static::linkRefusal($def, $old, $value);
        if ($refusal !== null) {
            return [$fromName => PortalDefinition::trans('rules.' . $refusal)];
        }

        if (static::linkTaken($table, $value, $model->exists ? intval($model->id) : null)) {
            return [$fromName => PortalDefinition::trans('rules.link_duplicate')];
        }

        return [];
    }

    // ---- helpers --------------------------------------------------------

    /**
     * Which of the portal's tables this is: project, issue, wiki or link.
     *
     * @param CustomTable $table
     * @param array<string, mixed> $def
     * @return string|null
     */
    protected static function kindOf(CustomTable $table, array $def): ?string
    {
        $name = strval($table->table_name);
        $roles = ['project' => 'project_table', 'issue' => 'issue_table', 'wiki' => 'wiki_table', 'link' => 'related_table'];
        foreach ($roles as $kind => $role) {
            if ($name !== '' && $name === strval(array_get($def, $role))) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * A column name from the definition, or the template's when the
     * definition has none.
     *
     * @param array<string, mixed> $def
     * @param string $path
     * @param string $fallback
     * @return string
     */
    protected static function column(array $def, string $path, string $fallback): string
    {
        $name = array_get($def, $path);

        return is_string($name) && $name !== '' ? $name : $fallback;
    }

    /**
     * The values a write changes, compared with the row as stored.
     *
     * Compared loosely, the way a form and the database disagree about the
     * same value: "5" and 5, an empty string and a missing key, the same
     * people listed in another order. Anything else counts as a change -
     * judging a value twice costs nothing, missing a change would let it
     * through unjudged.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function changed(array $input, array $stored): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            if (static::normal($value) !== static::normal($stored[$key] ?? null)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return string|array<int, string>|null
     */
    protected static function normal($value)
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return strval($value);
        }
        if (is_array($value)) {
            $list = [];
            foreach ($value as $one) {
                $list[] = (is_scalar($one) || $one === null) ? strval($one) : strval(json_encode($one));
            }
            // A multi-value column's order carries nothing.
            sort($list, SORT_STRING);

            return $list;
        }

        return strval(json_encode($value));
    }

    /**
     * The table a select_table column points at.
     *
     * @param CustomTable $table
     * @param string $column
     * @return CustomTable|null
     */
    protected static function targetTable(CustomTable $table, string $column)
    {
        $custom = CustomColumn::getEloquent($column, $table);
        if (!isset($custom) || $custom->column_type !== ColumnType::SELECT_TABLE) {
            return null;
        }

        return $custom->select_target_table;
    }

    /**
     * A row by id, whoever is asking.
     *
     * The rules answer for the data, not for the user's view of it: a check
     * run through the caller's row scope misses whatever that caller cannot
     * see - a hidden parent read as missing, a child nobody here can see
     * counted as none.
     *
     * @param CustomTable|string $table
     * @param mixed $id
     * @param bool $withTrashed
     * @return CustomValue|null
     */
    protected static function findRow($table, $id, bool $withTrashed = false)
    {
        $table = $table instanceof CustomTable ? $table : CustomTable::getEloquent($table);
        if (!isset($table) || is_nullorempty($id) || !is_numeric($id)) {
            return null;
        }

        $query = $table->getValueModel()->newQueryWithoutScopes();
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }
        $row = $query->find(intval($id));

        return $row instanceof CustomValue ? $row : null;
    }

    /**
     * Whether the project has been archived.
     *
     * A missing project, a missing column or an empty value all read as "not
     * archived": the rule exists to protect finished projects, not to lock
     * up rows whose project cannot be resolved.
     *
     * @param array<string, mixed> $def
     * @param mixed $projectId
     * @return bool
     */
    protected static function projectArchived(array $def, $projectId): bool
    {
        $project = static::findRow(strval(array_get($def, 'project_table')), $projectId, true);
        if (!$project) {
            return false;
        }

        return boolval(array_get((array) $project->value, static::column($def, 'project_extra.archived', 'archived')));
    }

    /**
     * Walk up from $startId looking for $needle.
     *
     * @param CustomTable $table
     * @param string $column
     * @param int $startId first ancestor to inspect
     * @param int $needle the row that must not appear above it
     * @return bool
     */
    protected static function wouldLoop(CustomTable $table, string $column, int $startId, int $needle): bool
    {
        $seen = [];
        $id = $startId;

        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            if ($id === $needle) {
                return true;
            }
            if (isset($seen[$id])) {
                // A loop that was already in the data. Not this edit's doing,
                // but continuing to walk it would never end.
                return false;
            }
            $seen[$id] = true;

            $row = static::findRow($table, $id, true);
            if (!$row) {
                return false;
            }

            $next = static::idOf(array_get((array) $row->value, $column));
            if ($next === null) {
                return false;
            }
            $id = $next;
        }

        // Deeper than anything legitimate; treat it as a loop rather than
        // letting an unbounded chain through.
        return true;
    }

    /**
     * How many live rows of this table point at $id through $column.
     *
     * @param CustomTable $table
     * @param string $column
     * @param mixed $id
     * @return int
     */
    protected static function countPointingAt(CustomTable $table, string $column, $id): int
    {
        if (is_nullorempty($id)) {
            return 0;
        }

        try {
            return intval($table->getValueModel()->newQuery()
                ->withoutGlobalScope(CustomValueModelScope::class)
                ->where('value->' . $column, strval($id))
                ->count());
        } catch (\Throwable $e) {
            \Log::warning('PmPortal reference count failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Hold the project key while this save checks and writes it.
     *
     * The check that a key is free and the write that takes it are two
     * steps, so two saves of the same new key at the same moment would both
     * find it free. A named database lock queues the second behind the
     * first until the first is committed (see saved()). The name is hashed
     * with the database name: these locks are server wide, and another
     * install on the same server must not queue behind this one.
     *
     * @param string $code
     * @return bool false when another save held the key for too long
     */
    protected static function lockCode(string $code): bool
    {
        try {
            $connection = DB::connection();
            if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                // No GET_LOCK there; the check below still runs on its own.
                return true;
            }

            $name = 'pmportal_code_' . md5($connection->getDatabaseName() . '|' . mb_strtoupper($code));
            if (isset(static::$codeLocks[$name])) {
                return true;
            }

            $got = $connection->selectOne('SELECT GET_LOCK(?, ?) AS l', [$name, self::CODE_LOCK_WAIT]);
            if (intval($got->l ?? 0) !== 1) {
                return false;
            }
            static::$codeLocks[$name] = true;

            return true;
        } catch (\Throwable $e) {
            // A lock that cannot be taken at all leaves the key as safe as it
            // was before the lock existed; refusing every save would not.
            \Log::warning('PmPortal project key lock failed: ' . $e->getMessage());

            return true;
        }
    }

    protected static function releaseCodeLocks(): void
    {
        foreach (array_keys(static::$codeLocks) as $name) {
            try {
                DB::connection()->selectOne('SELECT RELEASE_LOCK(?) AS l', [$name]);
            } catch (\Throwable $e) {
                // The session ending releases it anyway.
            }
        }
        static::$codeLocks = [];
    }

    /**
     * Whether another project already uses this key, ignoring case.
     *
     * @param CustomTable $table
     * @param string $column the key column
     * @param string $code
     * @param CustomValue|null $current
     * @return bool
     */
    protected static function codeTaken(CustomTable $table, string $column, string $code, ?CustomValue $current): bool
    {
        // Without the row scope, deliberately. A project key is unique
        // across the space - the same promise Backlog makes - and a user who
        // cannot see the project holding a key must still be refused it,
        // otherwise two projects end up answering to PORTAL-12. The check
        // says nothing but "taken", which is what the message already says.
        $base = function () use ($table, $current) {
            $query = $table->getValueModel()->newQuery()->withoutGlobalScope(CustomValueModelScope::class);
            if ($current && $current->exists) {
                $query->where('id', '<>', $current->id);
            }

            return $query;
        };

        try {
            // Through the index first, and as a locking read: a locking read
            // sees the latest committed rows rather than the snapshot this
            // save's transaction may have opened before the key lock was
            // granted. An index lookup locks next to nothing, which a scan of
            // the json would not.
            $custom = CustomColumn::getEloquent($column, $table);
            if (isset($custom) && $custom->index_enabled
                && $base()->where($custom->getIndexColumnName(), $code)->sharedLock()->value('id') !== null) {
                return true;
            }

            // Then by the stored value itself, which also finds keys written
            // in lower case before the format rule existed.
            return $base()
                ->whereRaw('UPPER(JSON_UNQUOTE(JSON_EXTRACT(value, ?))) = ?', ['$."' . $column . '"', mb_strtoupper($code)])
                ->exists();
        } catch (\Throwable $e) {
            \Log::warning('PmPortal project code check failed: ' . $e->getMessage());

            // Fail closed. "Available" is the one answer this must never
            // guess at: letting the save through on a check that did not
            // run breaks the uniqueness the key promises, and two projects
            // answering to PORTAL-12 is not something a later retry can
            // undo. Reporting the code as taken costs the user one retry.
            return true;
        }
    }

    protected static function rowExists(CustomTable $table, int $id): bool
    {
        // Deleted rows count as existing. What this rule is for is a
        // reference to an id that never was; a row in the trash is still a
        // row, and can be restored. Without them, deleting a parent and then
        // touching one of its children - which the project delete does by
        // definition - reports the child's own untouched parent field as
        // missing and refuses a save nobody asked to change.
        return $table->getValueModel()->newQueryWithoutScopes()->where('id', $id)->exists();
    }

    /**
     * Compare two dates, taking whichever side the edit did not supply from
     * the row as it stands.
     *
     * @param array<string, mixed> $input
     * @param CustomValue|null $current
     * @param string $fromColumn
     * @param string $toColumn
     * @return string|null
     */
    protected static function dateOrder(array $input, ?CustomValue $current, string $fromColumn, string $toColumn): ?string
    {
        if (!array_key_exists($fromColumn, $input) && !array_key_exists($toColumn, $input)) {
            return null;
        }

        $pick = function (string $column) use ($input, $current) {
            if (array_key_exists($column, $input)) {
                return $input[$column];
            }

            return $current ? array_get((array) $current->value, $column) : null;
        };

        $from = $pick($fromColumn);
        $to = $pick($toColumn);

        if (is_nullorempty($from) || is_nullorempty($to) || !is_scalar($from) || !is_scalar($to)) {
            return null;
        }

        try {
            $fromAt = new \DateTime(strval($from));
            $toAt = new \DateTime(strval($to));
        } catch (\Throwable $e) {
            // Exment's own date rule reports a malformed date; not this one's job.
            return null;
        }

        if ($toAt < $fromAt) {
            return PortalDefinition::trans('rules.date_order');
        }

        return null;
    }

    /**
     * Read a row id out of a select value, which may arrive as a string, an
     * int, or a single element array from a multi select widget.
     *
     * @param mixed $raw
     * @return int|null
     */
    protected static function idOf($raw): ?int
    {
        if (is_array($raw)) {
            $raw = count($raw) === 1 ? reset($raw) : null;
        }

        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $id = intval($raw);

        return $id > 0 ? $id : null;
    }
}
