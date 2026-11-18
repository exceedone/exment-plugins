<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;

/**
 * Fills the four fields of an issue the AI can answer for: type, category,
 * priority and assignee.
 *
 * How the decision is split, and why:
 *
 *  - Type and category are Choice questions whose options are the project's
 *    own master rows, named by their label and described by ai_hint. The
 *    answer comes back as a label and is mapped to the row id here; a label
 *    the project has not got is dropped rather than guessed at.
 *  - Priority is never asked for directly. Two narrow Score questions -
 *    how many people, how soon - are combined by code, so there is a reason
 *    to show and a formula a project can argue with. A third, yes/no
 *    question flags security and data-loss reports, which the two scales
 *    cannot express, and lifts the result one level.
 *  - The assignee is never chosen by the AI. No name leaves the server;
 *    the category's default assignee wins, then whoever has the least open
 *    work. That rule is visible and arguable, which "the AI picked him" is not.
 *
 * The criteria wording is the result of a 200 case Japanese backtest
 * (plan section 13): the model reads criteria literally, so every level
 * names its boundary (head counts, dates) and says what to do when the text
 * does not say. Change the wording and run the backtest harness again.
 *
 * The questions, criteria and type hints are English on purpose: they are
 * the model's input, and Jev reads English better than Japanese. None of
 * them is ever sent to a browser - what goes back is a row id, the row's own
 * label, a confidence and a reason from the lang file.
 */
class IssueAutofill
{
    /** Jev accepts at most this many options per Choice question. */
    public const MAX_CHOICES = 255;

    /** Longest description sent; anything past this is noise to the model. */
    public const STATE_MAX_CHARS = 3000;

    /** Longest subject sent. A subject is a line; past this it is a pasted text. */
    public const SUBJECT_MAX_CHARS = 255;

    /**
     * How much of a text the masks read. Only the first STATE_MAX_CHARS are
     * ever sent; this only bounds the regex work on a pasted log file.
     */
    public const SCAN_MAX_CHARS = 20000;

    /** Probability at which the security / data-loss flag counts. */
    public const SEVERE_MIN = 0.8;

    /** The option that means "no listed category fits". */
    public const NONE_KEY = '__none__';

    /**
     * Used when a type row has no ai_hint. Keys are the seeded names. They
     * describe the grammatical form Japanese writers use, which the backtest
     * showed works better than describing the concept (plan 13.5, lesson 3).
     *
     * バグ, 改善 and その他 were rewritten after the test bed left three types
     * unfilled: the model split between バグ and 改善 on slowness, and between
     * バグ and その他 on a text that said nothing. Each row now says which side
     * of that line it takes. ai_backtest/type_check.php, in the dev kit at
     * storage/app/pmportal-dev, measured on 259 cases: accepted 97.7% ->
     * 99.2-99.6%, filled 94.6% -> 96.5-96.9% (plan 14.4). Change a word and
     * run it again.
     */
    public const DEFAULT_TYPE_HINTS = [
        'タスク' => 'Work the team itself has decided to carry out, written as an instruction to do it (set up, migrate, update, apply a patch, create accounts, replace a file); the writer hands over work rather than asking for a change',
        'バグ' => 'A report that something does not work as it should, written as what happens: an error, a crash, wrong or missing data, a screen or button that does not respond, or slowness (〜できない, 〜にならない, エラーになる, 〜が遅い, 〜に時間がかかる, タイムアウトする)',
        '要望' => 'A wish or proposal that the product or the environment should be changed or gain a capability, written as a request (〜してほしい, 〜したい, 〜が欲しい, 〜を追加してください); not yet planned work',
        '改善' => 'Improvement work the team has planned on something that already works, written as an instruction to improve it (〜を改善する, 〜を見直す, 〜を整理する, 〜を高速化する, 〜を短縮する, リファクタリング); a report of a symptom is a バグ and a wish is a 要望, even when they use the word 改善',
        'その他' => 'A question asking how something works or whether something is possible (〜できますか, 〜はありますか, 〜ですか), a consultation, an announcement, a purchase or business matter, a text that does not say what is wrong or what is wanted, or anything else',
    ];

    /**
     * The type question. Writers put the kind of report in the form of the
     * subject - a symptom, a wish, an instruction, a question - while the
     * description often ends with a request whatever the kind. Reading the
     * subject first is what settled the slowness reports.
     */
    public const TYPE_INSTRUCTIONS = 'Which issue type fits this report? Judge mainly by how the subject is written, and use the description when the subject alone does not tell.';

    /** @var array<string, mixed> */
    protected $def;

    /** @var CustomTable|null */
    protected $project_table;

    /** @var CustomTable|null */
    protected $issue_table;

    /** @var JevClient */
    protected $client;

    /**
     * masters() per project id, for the life of this object: the batch asks
     * about every issue of a project in turn.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $mastersMemo = [];

    /** @var int paid calls this object has started, answered or not */
    protected $calls = 0;

    public function __construct(?JevClient $client = null)
    {
        $this->def = PortalDefinition::config();
        $this->project_table = CustomTable::getEloquent($this->def['project_table']);
        $this->issue_table = CustomTable::getEloquent($this->def['issue_table']);
        $this->client = $client ?? new JevClient();
    }

    /**
     * How many paid calls this object has started. The batch reads it to
     * tell an issue that cost a call from one that did not, whatever
     * happened afterwards.
     */
    public function callsMade(): int
    {
        return $this->calls;
    }

    // ---- plugin options ----------------------------------------------------

    /**
     * One of the plugin's custom options, or the default when unset.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function option(string $key, $default)
    {
        $plugin = PortalDefinition::plugin();
        $value = isset($plugin) ? $plugin->getCustomOption($key) : null;

        return is_nullorempty($value) ? $default : $value;
    }

    public static function flag(string $key, bool $default): bool
    {
        $value = static::option($key, null);

        return $value === null ? $default : boolval(intval($value));
    }

    public static function number(string $key, float $default): float
    {
        $value = static::option($key, null);

        return is_numeric($value) ? floatval($value) : $default;
    }

    /** Below this the button leaves a field empty and says "not sure". */
    public static function thresholdSuggest(): float
    {
        return static::number('ai_threshold_suggest', 0.6);
    }

    /** Below this nothing is written without a person looking. */
    public static function thresholdAuto(): float
    {
        return static::number('ai_threshold_auto', 0.8);
    }

    /**
     * Shortest description worth asking impact and urgency about. With less
     * the model answers "lowest level" with high confidence and is wrong
     * more often than not (plan 13.6).
     */
    public static function minDescription(): int
    {
        return max(0, intval(static::number('ai_min_description', 30)));
    }

    /**
     * Calls one project may make per day, errors included.
     *
     * Always a limit. 0 used to mean "no limit at all", which nothing on the
     * settings screen said: whoever typed 0 to stop the spending got the
     * opposite. Anything below 1 is not a cap, and the default applies.
     */
    public static function dailyCap(): int
    {
        $cap = intval(static::number('ai_daily_cap', 300));

        return $cap >= 1 ? $cap : 300;
    }

    /** A member with this many open issues is not offered more. */
    public static function assignCap(): int
    {
        return max(1, intval(static::number('ai_assign_cap', 15)));
    }

    // ---- project switches ---------------------------------------------------

    /**
     * One yesno column of the project as a bool, defaulting when the column
     * does not exist yet or the row was never saved with it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param string $column_name
     * @param bool $default
     * @return bool
     */
    public function projectFlag($project, string $column_name, bool $default): bool
    {
        if (!isset($this->project_table) || CustomColumn::getEloquent($column_name, $this->project_table) === null) {
            return $default;
        }
        $value = array_get($project->value, $column_name);

        return is_nullorempty($value) ? $default : boolval(intval($value));
    }

    /**
     * Whether this project wants the assignee filled in as well.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return bool
     */
    public function suggestsAssignee($project): bool
    {
        $column = array_get(PortalSchema::PROJECT_COLUMNS, 'ai_assign', 'ai_assign');

        return static::assignSwitchOn(array_get($project->value, $column));
    }

    /**
     * What one stored ai_assign value means. Empty is on, the column's
     * default: a project saved before the switch existed holds nothing, and
     * the settings tab shows that as on, so it has to work as on.
     *
     * @param mixed $value
     * @return bool
     */
    public static function assignSwitchOn($value): bool
    {
        return is_nullorempty($value) || boolval($value);
    }

    // ---- entry points -------------------------------------------------------

    /**
     * Score one text and turn the answers into field values of this project.
     *
     * $input may carry 'category': the category the form holds right now,
     * saved or not. When it is there it decides the assignee's category
     * step, as the stored category does when it is not - see withAssignee().
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array{subject: string, description: string, category?: mixed} $input
     * @param \Exceedone\Exment\Model\CustomValue|null $issue null while the issue is still being written
     * @param string $kind one of the AiLog::KIND_* values
     * @param float|null $assignThreshold what the category needs before its default assignee is used; null for the suggest threshold
     * @return array{suggestion_id: int|null, fields: array<string, array<string, mixed>|null>, has_description: bool, model: string, usage: array<string, int>, latency_ms: int, threshold: float, threshold_auto: float}
     * @throws JevException
     */
    public function suggest($project, array $input, $issue, string $kind, ?float $assignThreshold = null): array
    {
        $prep = $this->prepare($project, $input, $kind);
        $threshold = static::thresholdSuggest();
        $suggestionId = null;
        $res = ['model' => '', 'usage' => [], 'latency_ms' => 0];

        if (empty($prep['questions'])) {
            // A project with no type rows and no categories, and a text too
            // short for the scales: nothing to ask, so nothing is sent. The
            // assignee is a rule, not an answer, and is still worked out.
            $fields = $this->normalize([], $prep['masters'], $prep['has_description'], $threshold);
            $fields = $this->withAssignee($fields, $project, $input, $issue, $prep['masters'], $assignThreshold);
        } else {
            $log = $this->reserveCall($project, $issue, $kind, $prep['hash']);
            $res = null;
            try {
                $res = $this->client->ask($prep['state'], $prep['questions']);
                $fields = $this->readAnswers($res, $prep, $threshold);
                $fields = $this->withAssignee($fields, $project, $input, $issue, $prep['masters'], $assignThreshold);
            } catch (\Throwable $e) {
                $this->finishLog($log, $res, [], AiLog::STATUS_ERROR, $e->getMessage());
                throw $e;
            }
            $this->finishLog($log, $res, $fields);
            $suggestionId = intval($log->id);
        }

        return [
            'suggestion_id' => $suggestionId,
            'fields' => $fields,
            'has_description' => $prep['has_description'],
            'model' => strval(array_get($res, 'model', '')),
            'usage' => (array) array_get($res, 'usage', []),
            'latency_ms' => intval(array_get($res, 'latency_ms', 0)),
            'threshold' => $threshold,
            'threshold_auto' => static::thresholdAuto(),
        ];
    }

    /**
     * Everything one call would be made of, without making it: the text as
     * it would be sent, this project's lists and the questions built from
     * them, and the hash the log records.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array{subject: string, description: string} $input
     * @param string $kind one of the AiLog::KIND_* values
     * @return array{state: array{subject: string, description: string}, has_description: bool, masters: array<string, mixed>, questions: array<string, array<string, mixed>>, hash: string}
     */
    public function prepare($project, array $input, string $kind = AiLog::KIND_SUGGEST): array
    {
        $state = $this->buildState($input);
        $hasDescription = $this->hasDescription($state);
        $masters = $this->masters($project);
        $questions = $this->buildQuestions($masters['types'], $masters['categories'], $hasDescription);

        return [
            'state' => $state,
            'has_description' => $hasDescription,
            'masters' => $masters,
            'questions' => $questions,
            'hash' => $kind === AiLog::KIND_BATCH ? static::batchHash($input, $questions) : static::inputHash($input),
        ];
    }

    /**
     * The answers as field values. An answer this code cannot read is the
     * remote side's failure, and is reported as one - not as a crash of the
     * page that asked.
     *
     * @param array<string, mixed> $res the client's answer
     * @param array<string, mixed> $prep prepare()
     * @param float $threshold
     * @return array<string, array<string, mixed>|null>
     * @throws JevException
     */
    protected function readAnswers(array $res, array $prep, float $threshold): array
    {
        try {
            return $this->normalize((array) array_get($res, 'answers', []), $prep['masters'], $prep['has_description'], $threshold);
        } catch (\Throwable $e) {
            throw new JevException('bad_response', 200, $e);
        }
    }

    /**
     * Claim one of today's calls for this project, as a log row written
     * before the call is made. Whatever happens to the call afterwards, the
     * attempt has been counted.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue|null $issue
     * @param string $kind
     * @param string $hash
     * @return AiLog
     * @throws JevException daily_cap when today's calls are spent
     */
    protected function reserveCall($project, $issue, string $kind, string $hash): AiLog
    {
        $log = AiLog::reserve($project->id, isset($issue) ? $issue->id : null, $kind, $hash, static::dailyCap());
        if ($log === null) {
            throw new JevException('daily_cap', 429);
        }
        $this->calls++;

        return $log;
    }

    /**
     * Add the assignee the rule picks.
     *
     * Which category the rule's first step reads, in this order:
     *  - the category the form holds, when the form said (input 'category'):
     *    what the person is about to save, which the client keeps whatever
     *    the AI guessed - an empty one falls through to the guess, which is
     *    then what the client fills in;
     *  - otherwise the issue's stored category: applyEmpty never overwrites a
     *    non-empty column, so the guess would be thrown away, and letting it
     *    pick the person would tie them to a category the issue does not
     *    carry;
     *  - otherwise the AI's own guess.
     *
     * @param array<string, array<string, mixed>|null> $fields
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<string, mixed> $input
     * @param \Exceedone\Exment\Model\CustomValue|null $issue
     * @param array<string, mixed> $masters
     * @param float|null $threshold
     * @return array<string, array<string, mixed>|null>
     */
    protected function withAssignee(array $fields, $project, array $input, $issue, array $masters, ?float $threshold): array
    {
        $is = $this->def['issue'];
        $assigneeColumn = array_get($is, 'assignees');
        if (is_nullorempty($assigneeColumn)) {
            return $fields;
        }

        $category = array_key_exists('category', $input)
            ? $this->formCategory($input['category'], $masters)
            : $this->existingCategory($issue);
        if ($category === null) {
            $category = array_get($fields, strval(array_get($is, 'category', '')));
        }

        $assignee = $this->assigneeFor($project, $category, $masters, $threshold);
        if ($assignee !== null) {
            $fields[$assigneeColumn] = $assignee;
        }

        return $fields;
    }

    /**
     * The category a form holds, as a field assigneeFor() can read.
     *
     * Null for an empty form field. A category that is not one of this
     * project's own rows - a shared row, a stale id - comes back as a field
     * that is not "ok": the client keeps it all the same, so no category
     * default may be read off a guess instead, and the rule moves on to the
     * least busy member.
     *
     * @param mixed $raw
     * @param array<string, mixed> $masters
     * @return array<string, mixed>|null
     */
    protected function formCategory($raw, array $masters): ?array
    {
        $value = static::firstValue($raw);
        if ($value === null) {
            return null;
        }
        $known = array_key_exists($value, (array) array_get($masters, 'categories', []));

        return ['ok' => $known, 'confidence' => $known ? 1.0 : 0.0, 'value' => $value];
    }

    /**
     * The first non-empty id of a value that may be one id, a list of them
     * or a comma separated string - or null when there is none.
     *
     * @param mixed $raw
     * @return string|null
     */
    public static function firstValue($raw): ?string
    {
        if (is_string($raw) && strpos($raw, ',') !== false) {
            $raw = explode(',', $raw);
        }
        foreach ((array) $raw as $value) {
            if (!is_scalar($value) || is_bool($value)) {
                continue;
            }
            $value = trim(strval($value));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Fill whatever is still empty on a saved issue, without a person
     * looking: the batch's job.
     *
     * Only empty columns are written, and only the ones that can actually be
     * filled here (fillable()): asking about a column nothing can come back
     * for paid for an answer that was thrown away, and left the issue "empty"
     * for the next run to pay for again. The AI's columns need a call and are
     * written at the auto threshold; the assignee is a rule and needs none.
     *
     * The same text with the same questions is not asked twice: the answer
     * would be the same, and so would the bill. A text, a list or a hint that
     * changes is asked again.
     *
     * A dry run decides what it would do and makes no call at all.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param string $kind the AiLog kind to record, AiLog::KIND_BATCH
     * @param bool $dry true to decide without calling or writing
     * @return array{set: array<string, mixed>, fields: array<string, mixed>, suggestion_id: int|null, has_description: bool, skipped: string|null, called: bool, ask: array<int, string>, rule: array<int, string>}
     * @throws JevException
     */
    public function applyEmpty($project, $issue, string $kind, bool $dry = false): array
    {
        $out = [
            'set' => [], 'fields' => [], 'suggestion_id' => null, 'has_description' => false,
            'skipped' => null, 'called' => false, 'ask' => [], 'rule' => [],
        ];

        // The copy the caller holds may be minutes old - the batch reads a
        // project's open issues once - and everything below is decided
        // against what the issue says now. One deleted in the meantime is
        // left alone: in the trash (refresh() reads past the soft-delete
        // scope), or gone from the table altogether.
        try {
            $issue->refresh();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $out['skipped'] = 'deleted';

            return $out;
        }
        if (method_exists($issue, 'trashed') && $issue->trashed()) {
            $out['skipped'] = 'deleted';

            return $out;
        }
        $prep = $this->prepare($project, $this->inputOf($issue), $kind);
        $out['has_description'] = $prep['has_description'];

        $fillable = $this->fillable($project, $prep);
        $empty = $this->emptyFields($issue);
        $ask = array_values(array_intersect($fillable['ask'], $empty));
        $rule = array_values(array_intersect($fillable['rule'], $empty));
        if (empty($ask) && empty($rule)) {
            $out['skipped'] = empty($empty) ? 'nothing_empty' : 'nothing_fillable';

            return $out;
        }
        if (!empty($ask) && AiLog::batchSeen($issue->id, $prep['hash'])) {
            $ask = [];
            $out['skipped'] = 'unchanged';
        }
        $out['ask'] = $ask;
        $out['rule'] = $rule;

        $threshold = static::thresholdAuto();
        $set = [];
        $log = null;
        $res = null;
        if (!empty($ask)) {
            $out['called'] = true;
            if (!$dry) {
                $log = $this->reserveCall($project, $issue, $kind, $prep['hash']);
                try {
                    $res = $this->client->ask($prep['state'], $prep['questions']);
                    $out['fields'] = $this->readAnswers($res, $prep, $threshold);
                } catch (\Throwable $e) {
                    $this->finishLog($log, $res, [], AiLog::STATUS_ERROR, $e->getMessage());
                    throw $e;
                }
                foreach ($ask as $column) {
                    $field = array_get($out['fields'], $column);
                    if (is_array($field) && boolval(array_get($field, 'ok')) && array_get($field, 'value') !== null) {
                        $set[$column] = array_get($field, 'value');
                    }
                }

                // Someone may have filled a column by hand while the call was
                // out, and a save rewrites the whole value: re-read, and keep
                // only what is still empty.
                $issue->refresh();
                $set = array_intersect_key($set, array_flip($this->emptyFields($issue)));
            }
        }

        // The assignee by rule, from the category the issue is going to carry:
        // the one it has now, or the one being written in this same save.
        $assigneeColumn = strval(array_get($this->def['issue'], 'assignees', ''));
        if (!empty($rule) && ($dry || in_array($assigneeColumn, $this->emptyFields($issue), true))) {
            $categoryColumn = strval(array_get($this->def['issue'], 'category', ''));
            $category = $this->existingCategory($issue);
            if ($category === null && $categoryColumn !== '' && array_key_exists($categoryColumn, $set)) {
                $category = ['ok' => true, 'confidence' => 1.0, 'value' => static::firstValue($set[$categoryColumn])];
            }
            $assignee = $this->assigneeFor($project, $category, $prep['masters'], null);
            if (is_array($assignee) && boolval(array_get($assignee, 'ok')) && array_get($assignee, 'value') !== null) {
                $out['fields'][$assigneeColumn] = $assignee;
                $set[$assigneeColumn] = array_get($assignee, 'value');
            }
        }

        if (isset($log)) {
            $this->finishLog($log, $res, $out['fields']);
            $out['suggestion_id'] = intval($log->id);
        }
        if (!$dry && !empty($set)) {
            $issue->setValue($set);
            $issue->save();
            if (isset($log)) {
                $log->applied = $set;
                $log->save();
            }
        }
        $out['set'] = $set;

        return $out;
    }

    /**
     * Which of the AI columns can be filled for this issue at all.
     *
     *  ask   the columns a call can answer: the ones a question is built for
     *        (a type list of at least two, any category, a description long
     *        enough for the priority scales) whose column exists;
     *  rule  the assignee, when the project's switch for it is on and the
     *        project names anybody. A rule, so it never needs a call.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<string, mixed> $prep prepare()
     * @return array{ask: array<int, string>, rule: array<int, string>}
     */
    public function fillable($project, array $prep): array
    {
        $is = $this->def['issue'];
        $columns = array_flip($this->aiColumns());
        $questions = (array) array_get($prep, 'questions', []);

        $ask = [];
        foreach (['type' => 'issue_type', 'category' => 'category', 'priority' => 'impact'] as $role => $question) {
            $name = strval(array_get($is, $role, ''));
            if ($name === '' || !isset($columns[$name]) || !isset($questions[$question])) {
                continue;
            }
            if ($role === 'priority' && empty(array_get($prep, 'masters.priorities'))) {
                continue;
            }
            $ask[] = $name;
        }

        $rule = [];
        $assignees = strval(array_get($is, 'assignees', ''));
        if ($assignees !== '' && isset($columns[$assignees])
            && $this->suggestsAssignee($project) && !empty($this->projectMembers($project))) {
            $rule[] = $assignees;
        }

        return ['ask' => $ask, 'rule' => $rule];
    }

    /**
     * The text of a saved issue, in the shape suggest() takes.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @return array{subject: string, description: string}
     */
    public function inputOf($issue): array
    {
        $is = $this->def['issue'];

        return [
            'subject' => strval(array_get($issue->value, array_get($is, 'subject'), '')),
            'description' => strval(array_get($issue->value, array_get($is, 'description'), '')),
        ];
    }

    /**
     * Which of the four AI columns this issue has nothing in.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @return array<int, string>
     */
    public function emptyFields($issue): array
    {
        $out = [];
        foreach ($this->aiColumns() as $column) {
            $value = array_get($issue->value, $column);
            if (is_array($value)) {
                $value = array_filter($value, function ($v) {
                    return !is_nullorempty($v);
                });
            }
            if (is_nullorempty($value)) {
                $out[] = $column;
            }
        }

        return $out;
    }

    /**
     * The issue's own category, as a field assigneeFor() can read, or null when
     * it has none. A stored category is a fact, not a prediction, so it carries
     * full confidence - the category-default step then uses it exactly as it
     * would a sure guess.
     *
     * @param \Exceedone\Exment\Model\CustomValue|null $issue
     * @return array<string, mixed>|null
     */
    protected function existingCategory($issue): ?array
    {
        $column = array_get($this->def['issue'], 'category');
        if (is_nullorempty($column) || !isset($issue)) {
            return null;
        }
        // Empty entries do not count, the same as in emptyFields(): a
        // multi-select cleared to [] or [""] holds no category, and reading
        // it as one would skip the category step for a guess the batch then
        // writes.
        $value = static::firstValue(array_get($issue->value, $column));
        if ($value === null) {
            return null;
        }

        return ['ok' => true, 'confidence' => 1.0, 'value' => $value];
    }

    /**
     * The four issue columns this class may write, by this install's names.
     *
     * @return array<int, string>
     */
    public function aiColumns(): array
    {
        $is = $this->def['issue'];
        $out = [];
        foreach (['type', 'category', 'priority', 'assignees'] as $role) {
            $name = array_get($is, $role);
            if (!is_nullorempty($name) && isset($this->issue_table) && CustomColumn::getEloquent($name, $this->issue_table) !== null) {
                $out[] = strval($name);
            }
        }

        return $out;
    }

    /**
     * @param array{subject: string, description: string} $input
     * @return string
     */
    public static function inputHash(array $input): string
    {
        return sha1(trim(strval(array_get($input, 'subject', ''))) . "\n" . trim(strval(array_get($input, 'description', ''))));
    }

    /**
     * What the batch has scored: the text, and the questions it was asked -
     * so a project that adds a category or rewrites a hint gets its open
     * issues asked again. Only the criteria count; the urgency question's
     * wording names today's weekday and would otherwise change every day.
     *
     * @param array{subject: string, description: string} $input
     * @param array<string, array<string, mixed>> $questions
     * @return string
     */
    public static function batchHash(array $input, array $questions): string
    {
        $criteria = [];
        foreach ($questions as $key => $question) {
            $criteria[strval($key)] = array_get((array) $question, 'criteria');
        }
        ksort($criteria);

        return sha1(static::inputHash($input) . "\n" . strval(json_encode($criteria, JSON_UNESCAPED_UNICODE)));
    }

    // ---- text -----------------------------------------------------------------

    /**
     * The two fields the model reads, and only those: everything else about
     * an issue is noise to it. HTML is dropped, personal data is masked and
     * the description is cut, so what leaves the server is the least needed.
     *
     * @param array{subject: string, description: string} $input
     * @return array{subject: string, description: string}
     */
    public function buildState(array $input): array
    {
        return static::prepareText(
            strval(array_get($input, 'subject', '')),
            strval(array_get($input, 'description', '')),
            static::flag('ai_mask_pii', true)
        );
    }

    /**
     * buildState() without the plugin option: the text as it is sent.
     *
     * Masked before it is cut, never after. Cut first, an address or a
     * number standing across the limit lost its tail, no longer looked like
     * one, and went out as a fragment.
     *
     * @param string $subject
     * @param string $description rich text
     * @param bool $mask whether personal data is masked
     * @return array{subject: string, description: string}
     */
    public static function prepareText(string $subject, string $description, bool $mask): array
    {
        $description = static::plainText($description);
        if (mb_strlen($description) > self::SCAN_MAX_CHARS) {
            $description = mb_substr($description, 0, self::SCAN_MAX_CHARS);
        }
        $subject = mb_substr($subject, 0, self::SCAN_MAX_CHARS);
        $subject = trim(preg_replace('/\s+/u', ' ', strip_tags($subject)) ?? '');

        if ($mask) {
            $subject = static::maskPii($subject);
            $description = static::maskPii($description);
        }

        if (mb_strlen($description) > self::STATE_MAX_CHARS) {
            $description = mb_substr($description, 0, self::STATE_MAX_CHARS);
        }
        if (mb_strlen($subject) > self::SUBJECT_MAX_CHARS) {
            $subject = mb_substr($subject, 0, self::SUBJECT_MAX_CHARS);
        }

        return ['subject' => $subject, 'description' => $description];
    }

    /**
     * @param array{subject: string, description: string} $state
     * @return bool
     */
    public function hasDescription(array $state): bool
    {
        return mb_strlen(strval(array_get($state, 'description', ''))) >= static::minDescription();
    }

    /**
     * Rich text to plain text. Line breaks are kept because a numbered list
     * is how a reporter writes reproduction steps, and the list shape is
     * part of what a later question will read.
     */
    public static function plainText(string $html): string
    {
        $text = preg_replace('~<\s*(br|/p|/div|/li|/tr|/h[1-6])\s*/?>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Emails and phone numbers are not needed for any of the four decisions,
     * so they never leave the server. A regex is enough.
     *
     * A phone number here starts with 0 or + - every domestic Japanese
     * number does, and an international one carries its +. Without that
     * anchor a date (2026-10-15), a log timestamp or an order number was
     * masked too, and a deadline is something the urgency question reads.
     *
     * The text is NFKC-normalised first, and what is sent is the normalised
     * text. Japanese input is full of full-width forms - ０９０, ＠, ＋ - that
     * the patterns, written for ASCII, did not see; an address typed in
     * them went out as it was.
     */
    public static function maskPii(string $s): string
    {
        $s = static::normalizeForMask($s);
        $s = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $s) ?? $s;

        return preg_replace('/(?<![\d+(])(\(?(?:\+|0)[\d\-\s()]{8,}\d)(?!\d)/u', '[phone]', $s) ?? $s;
    }

    /**
     * Full-width letters, digits and signs to their ASCII forms (NFKC), and
     * the dashes a Japanese writer puts between the groups of a number -
     * ー, −, ‐ and their relatives - to "-", only where they stand between
     * two digits, so a katakana word keeps its long vowel.
     *
     * Without the intl extension mb_convert_kana does the part that matters
     * here: full-width ASCII, the full-width space and half-width katakana.
     */
    protected static function normalizeForMask(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $normal = \Normalizer::normalize($s, \Normalizer::FORM_KC);
            if (is_string($normal)) {
                $s = $normal;
            }
        } else {
            $s = mb_convert_kana($s, 'asKV', 'UTF-8');
        }

        return preg_replace('/(?<=\d)[\x{2010}-\x{2015}\x{2212}\x{30FC}\x{FF70}\x{FE63}\x{FF0D}](?=\d)/u', '-', $s) ?? $s;
    }

    // ---- master lists ------------------------------------------------------------

    /**
     * The lists the answers are mapped onto, read once per call.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array{types: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>, priorities: array<int, array{0: string, 1: string}>, done_status_ids: array<int, string>}
     */
    public function masters($project): array
    {
        $key = strval($project->id);
        if (!isset($this->mastersMemo[$key])) {
            $this->mastersMemo[$key] = [
                'types' => $this->typeRows($project),
                'categories' => $this->categoryRows($project),
                'priorities' => $this->priorityRows(),
                'done_status_ids' => $this->doneStatusIds($project),
            ];
        }

        return $this->mastersMemo[$key];
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, array<string, mixed>> id => [label, hint]
     */
    protected function typeRows($project): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'issue_type_table'));
        $map = (array) array_get($this->def, 'issue_type_row', []);
        if (!isset($table)) {
            return [];
        }

        $out = [];
        foreach ($this->scopedRows($table, $project, array_get($map, 'order')) as $row) {
            $label = trim(strval(array_get($row->value, array_get($map, 'name', 'name'), '')));
            if ($label === '') {
                $label = trim(strval($row->getLabel()));
            }
            if ($label === '') {
                continue;
            }
            $hint = trim(strval(array_get($row->value, array_get($map, 'ai_hint', 'ai_hint'), '')));
            if ($hint === '') {
                $hint = array_get(self::DEFAULT_TYPE_HINTS, $label, $label);
            }
            $out[strval($row->id)] = ['label' => $label, 'hint' => $hint];
            if (count($out) >= self::MAX_CHOICES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, array<string, mixed>> id => [label, hint, assignee]
     */
    protected function categoryRows($project): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'category_table'));
        $cat = (array) array_get($this->def, 'category', []);
        if (!isset($table)) {
            return [];
        }
        $projectColumn = CustomColumn::getEloquent(array_get($cat, 'project', 'project'), $table);
        if (!isset($projectColumn)) {
            return [];
        }

        $query = $table->getValueModel()->newQuery()
            ->where($projectColumn->getQueryKey(), strval($project->id));
        $rows = $query->get()->sortBy(function ($row) {
            return intval(array_get($row->value, 'display_order', 0));
        })->values();

        $hintColumn = array_get(PortalSchema::CATEGORY_COLUMNS, 'ai_hint', 'ai_hint');
        $assigneeColumn = array_get(PortalSchema::CATEGORY_COLUMNS, 'default_assignee', 'default_assignee');

        $out = [];
        foreach ($rows as $row) {
            $label = trim(strval(array_get($row->value, array_get($cat, 'name', 'name'), '')));
            if ($label === '') {
                continue;
            }
            $hint = trim(strval(array_get($row->value, $hintColumn, '')));
            $assignee = array_get($row->value, $assigneeColumn);
            $assignee = is_array($assignee) ? reset($assignee) : $assignee;
            $out[strval($row->id)] = [
                'label' => $label,
                'hint' => $hint !== '' ? $hint : $label,
                'assignee' => is_numeric($assignee) ? intval($assignee) : null,
            ];
            // One slot is kept for __none__.
            if (count($out) >= self::MAX_CHOICES - 1) {
                break;
            }
        }

        return $out;
    }

    /**
     * The space-wide priority rows, top of the list first.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function priorityRows(): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'priority_table'));
        $map = (array) array_get($this->def, 'priority_row', []);
        if (!isset($table)) {
            return [];
        }

        $out = [];
        foreach ($this->scopedRows($table, null, array_get($map, 'order')) as $row) {
            $label = trim(strval(array_get($row->value, array_get($map, 'name', 'name'), '')));
            if ($label === '') {
                $label = trim(strval($row->getLabel()));
            }
            $out[] = [strval($row->id), $label];
        }

        return $out;
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<int, string>
     */
    protected function doneStatusIds($project): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'status_table'));
        $map = (array) array_get($this->def, 'status_row', []);
        if (!isset($table)) {
            return [];
        }

        $out = [];
        // Every done row an issue might point at, shared ones included: this
        // only ever narrows a "not finished" query, where one too many is
        // harmless and one too few counts a closed issue as open.
        foreach ($this->scopedRows($table, $project, array_get($map, 'order'), true) as $row) {
            if (boolval(array_get($row->value, array_get($map, 'done', 'is_done')))) {
                $out[] = strval($row->id);
            }
        }

        return $out;
    }

    /**
     * Rows of a master table in the order the settings tab shows them.
     *
     * A project's own rows, or the shared ones (no parent) while it has none
     * of its own. Setup leaves a shared copy behind when it ran before the
     * first project existed, and every project gets its own copy when it is
     * created, so taking both offered the model each type twice - while the
     * issue form's picker only ever offers the project's copy. $withShared
     * keeps the shared rows anyway, for a check that has to recognise every
     * row an issue might point at rather than offer a choice.
     *
     * @param CustomTable $table
     * @param \Exceedone\Exment\Model\CustomValue|null $project
     * @param string|null $orderColumn
     * @param bool $withShared
     * @return \Illuminate\Support\Collection
     */
    protected function scopedRows(CustomTable $table, $project, $orderColumn, bool $withShared = false)
    {
        $query = $table->getValueModel()->newQuery();
        if (isset($project)) {
            $query->where(function ($w) use ($project) {
                $w->where('parent_id', $project->id)->orWhereNull('parent_id');
            });
        }

        $rows = $query->orderBy('id')->get();
        if (isset($project) && !$withShared) {
            $own = $rows->filter(function ($row) {
                return !is_null($row->parent_id);
            });
            if ($own->isNotEmpty()) {
                $rows = $own;
            }
        }

        return $rows->sortBy(function ($row) use ($orderColumn) {
            return is_nullorempty($orderColumn) ? 0 : intval(array_get($row->value, $orderColumn, 0));
        })->values();
    }

    // ---- questions ----------------------------------------------------------------

    /**
     * The questions, built from this project's lists. Wording is the v2 set
     * from the backtest, the type question from the later type check (plan
     * 14.4); see the class comment before changing a word.
     *
     * @param array<string, array<string, mixed>> $types
     * @param array<string, array<string, mixed>> $categories
     * @param bool $hasDescription
     * @return array<string, array<string, mixed>>
     */
    public function buildQuestions(array $types, array $categories, bool $hasDescription): array
    {
        $q = [];

        $typeCriteria = $this->criteriaOf($types);
        if (count($typeCriteria) >= 2) {
            $q['issue_type'] = [
                'type' => 'choice',
                'instructions' => self::TYPE_INSTRUCTIONS,
                'criteria' => $typeCriteria,
            ];
        }

        $categoryCriteria = $this->criteriaOf($categories);
        if (!empty($categoryCriteria)) {
            $categoryCriteria[self::NONE_KEY] = 'None of the listed areas (for example licences, schedules, documents, purchasing)';
            $q['category'] = [
                'type' => 'choice',
                'instructions' => 'Which functional area does this report concern? Choose ' . self::NONE_KEY . ' if none of the listed areas fits.',
                'criteria' => $categoryCriteria,
            ];
        }

        // Impact, urgency and the severity flag need a description. With a
        // bare subject the model still answers "lowest level" with high
        // confidence, which is wrong 56-63% of the time (plan 13.6).
        if ($hasDescription) {
            $q['impact'] = [
                'type' => 'score',
                'instructions' => 'How many people are affected by the problem, or would use the requested change, according to the text? Ignore numbers given only as background (licence counts, server sizing, total staff). If the text does not say, choose the lowest level.',
                'criteria' => [
                    'Only the reporter, or the text does not say who is affected',
                    'A few people (roughly 2 to 10), even if they belong to one department',
                    'All members of one team or department',
                    'Everyone in the company, all users',
                ],
            ];
            $q['urgency'] = [
                'type' => 'score',
                // The real weekday is given so "this week" has a fixed meaning.
                'instructions' => sprintf('How urgent is this, according to the text? Today is %s. If no deadline is stated, choose the lowest level.', now()->format('l')),
                'criteria' => [
                    'No deadline, or a deadline later than this week (next week, next month, a date more than 7 days away)',
                    'Within this week (今週中, by Friday, 週内)',
                    'Today or tomorrow, or marked as urgent (至急, ASAP, なるべく早く) without a date',
                    'Work is stopped right now and people cannot continue',
                ],
            ];
            $q['severe'] = [
                'type' => 'noul',
                'instructions' => 'Does the report describe a security problem (wrong access rights, data visible or editable by the wrong people), loss or corruption of stored data, or a failed backup?',
                'criteria' => [
                    'true' => 'Yes, one of these is described as happening',
                    'false' => 'No, or it is only a feature request or a question about these topics',
                ],
            ];
        }

        return $q;
    }

    /**
     * label => hint, for the rows of one list. Two rows with the same label
     * cannot both be options, so the first one keeps the name.
     *
     * @param array<string, array<string, mixed>> $rows
     * @return array<string, string>
     */
    protected function criteriaOf(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $label = strval(array_get($row, 'label'));
            if ($label === '' || $label === self::NONE_KEY || array_key_exists($label, $out)) {
                continue;
            }
            $out[$label] = strval(array_get($row, 'hint', $label));
        }

        return $out;
    }

    // ---- normalisation ------------------------------------------------------------

    /**
     * Answers to field values. Keys are this install's issue column names.
     *
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $masters
     * @param bool $hasDescription
     * @param float $threshold
     * @return array<string, array<string, mixed>|null>
     */
    public function normalize(array $answers, array $masters, bool $hasDescription, float $threshold): array
    {
        $is = $this->def['issue'];
        $fields = [];

        $typeColumn = array_get($is, 'type');
        if (!is_nullorempty($typeColumn)) {
            $fields[$typeColumn] = $this->choiceField(array_get($answers, 'issue_type'), $masters['types'], $threshold, false);
        }

        $categoryColumn = array_get($is, 'category');
        if (!is_nullorempty($categoryColumn) && !empty($masters['categories'])) {
            $multiple = $this->isMultiple($categoryColumn);
            $fields[$categoryColumn] = $this->choiceField(array_get($answers, 'category'), $masters['categories'], $threshold, $multiple);
        }

        $priorityColumn = array_get($is, 'priority');
        if (!is_nullorempty($priorityColumn)) {
            $fields[$priorityColumn] = $hasDescription
                ? $this->priorityField($answers, $masters['priorities'], $threshold)
                : $this->emptyField('need_desc');
        }

        return $fields;
    }

    /**
     * @param mixed $answer the Choice answer block
     * @param array<string, array<string, mixed>> $rows id => [label, ...]
     * @param float $threshold
     * @param bool $multiple whether the column stores a list
     * @return array<string, mixed>
     */
    protected function choiceField($answer, array $rows, float $threshold, bool $multiple): array
    {
        if (!is_array($answer)) {
            return $this->emptyField('unsure');
        }
        $choice = trim(strval(array_get($answer, 'choice', '')));
        $confidence = $this->confidenceOf($answer);

        if ($choice === self::NONE_KEY) {
            return $this->emptyField('no_category', $confidence);
        }

        $id = null;
        foreach ($rows as $rowId => $row) {
            if (strval(array_get($row, 'label')) === $choice) {
                $id = strval($rowId);
                break;
            }
        }
        if ($id === null) {
            // A label the project has not got: dropped, never guessed at.
            return $this->emptyField('unsure', $confidence);
        }

        $ok = $confidence >= $threshold;

        return [
            'value' => $multiple ? [$id] : $id,
            'label' => strval(array_get($rows[$id], 'label')),
            'confidence' => round($confidence, 3),
            'ok' => $ok,
            'reason' => $ok ? null : PortalDefinition::trans('ai.unsure'),
            'reason_key' => $ok ? null : 'unsure',
        ];
    }

    /**
     * Two levels and a flag, combined into one row of the priority list.
     *
     * sum runs 0..6; the list has N rows; index = min(N-1, floor(sum*N/6))
     * counted from the lowest priority. Integer arithmetic on the argmax
     * levels, never the model's float score (plan 13.5, lesson 8).
     *
     * @param array<string, mixed> $answers
     * @param array<int, array{0: string, 1: string}> $priorities top first
     * @param float $threshold
     * @return array<string, mixed>
     */
    protected function priorityField(array $answers, array $priorities, float $threshold): array
    {
        $n = count($priorities);
        $impact = $this->levelOf(array_get($answers, 'impact'), 'impact_levels');
        $urgency = $this->levelOf(array_get($answers, 'urgency'), 'urgency_levels');
        if ($n === 0 || $impact === null || $urgency === null) {
            return $this->emptyField('unsure');
        }

        $sum = $impact['level'] + $urgency['level'];
        $index = min($n - 1, intdiv($sum * $n, 6));

        $severeP = floatval(array_get((array) array_get($answers, 'severe', []), 'noul', 0));
        $severe = $severeP >= self::SEVERE_MIN && static::flag('ai_severe_bump', true);
        if ($severe) {
            $index = min($n - 1, $index + 1);
        }

        $topIsHigh = static::flag('ai_priority_top_is_high', true);
        $position = $topIsHigh ? $n - 1 - $index : $index;
        list($id, $label) = $priorities[$position];

        $confidence = min($impact['confidence'], $urgency['confidence']);
        $ok = $confidence >= $threshold;

        $reason = PortalDefinition::trans('ai.reason_priority', [
            'impact' => $impact['label'],
            'urgency' => $urgency['label'],
        ]);
        if ($severe) {
            $reason .= ' / ' . PortalDefinition::trans('ai.severe');
        }
        if (!$ok) {
            $reason = PortalDefinition::trans('ai.unsure') . ' (' . $reason . ')';
        }

        return [
            'value' => $id,
            'label' => $label,
            'confidence' => round($confidence, 3),
            'ok' => $ok,
            'reason' => $reason,
            'reason_key' => $ok ? null : 'unsure',
            'severe' => $severe,
            'parts' => [
                'impact' => $impact['level'],
                'urgency' => $urgency['level'],
                'severe_p' => round($severeP, 2),
            ],
        ];
    }

    /**
     * The predicted level of a Score answer: the index with the largest
     * probability, with the model's own confidence when it gives one.
     *
     * @param mixed $answer
     * @param string $labelsKey lang key under ai holding the level names
     * @return array{level: int, confidence: float, label: string}|null
     */
    protected function levelOf($answer, string $labelsKey): ?array
    {
        if (!is_array($answer)) {
            return null;
        }
        $probabilities = array_values(array_map('floatval', (array) array_get($answer, 'probabilities', [])));
        if (empty($probabilities)) {
            return null;
        }

        $level = 0;
        $max = -1.0;
        foreach ($probabilities as $i => $p) {
            if ($p > $max) {
                $max = $p;
                $level = $i;
            }
        }

        $labels = PortalDefinition::trans('ai.' . $labelsKey);
        $label = is_array($labels) ? strval(array_get($labels, $level, strval($level))) : strval($level);

        return [
            'level' => $level,
            'confidence' => $this->confidenceOf($answer),
            'label' => $label,
        ];
    }

    /**
     * The model's confidence, or TypeSafe's own formula from the top
     * probability when the field is missing: (p_max - 1/n) / (1 - 1/n).
     * n is the number of probabilities, one per option actually sent - a
     * duplicate label is sent once, and the category has __none__ besides.
     *
     * @param array<string, mixed> $answer
     * @return float
     */
    protected function confidenceOf(array $answer): float
    {
        $given = array_get($answer, 'confidence');
        if (is_numeric($given)) {
            return max(0.0, min(1.0, floatval($given)));
        }

        $probabilities = array_map('floatval', (array) array_get($answer, 'probabilities', []));
        $optionCount = count($probabilities);
        if ($optionCount <= 1) {
            return 0.0;
        }
        $pMax = max($probabilities);
        $floor = 1 / $optionCount;

        return max(0.0, min(1.0, ($pMax - $floor) / (1 - $floor)));
    }

    /**
     * A field the AI leaves alone, with the reason it will show.
     *
     * @param string $reasonKey lang key under ai
     * @param float $confidence
     * @return array<string, mixed>
     */
    protected function emptyField(string $reasonKey, float $confidence = 0.0): array
    {
        return [
            'value' => null,
            'label' => null,
            'confidence' => round($confidence, 3),
            'ok' => false,
            'reason' => PortalDefinition::trans('ai.' . $reasonKey),
            'reason_key' => $reasonKey,
        ];
    }

    protected function isMultiple(string $columnName): bool
    {
        $column = isset($this->issue_table) ? CustomColumn::getEloquent($columnName, $this->issue_table) : null;

        return isset($column) && boolval($column->getOption('multiple_enabled'));
    }

    // ---- assignee -------------------------------------------------------------------

    /**
     * Who should take this, by rule, with the rule written on the answer.
     *
     *  1. The category's default assignee, if the category is sure - the
     *     issue's own, the form's, or a guess at the threshold the caller
     *     writes at - and that person is the manager or a member.
     *  2. The manager or member with the fewest open issues in this project,
     *     skipping anybody at the cap. Work in other projects is not
     *     counted. A tie goes to the one whose newest open issue here was
     *     created longest ago - the rows hold no "assigned at", so creation
     *     stands in for it - and after that to the project's own order,
     *     manager first. An issue with no status counts as open.
     *  3. Nobody, said as such.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<string, mixed>|null $categoryField
     * @param array<string, mixed> $masters
     * @param float|null $threshold confidence the category needs for rule 1; null for its own "ok"
     * @return array<string, mixed>|null null when the project does not want a person picked
     */
    public function assigneeFor($project, $categoryField, array $masters, ?float $threshold = null): ?array
    {
        if (!$this->suggestsAssignee($project)) {
            return null;
        }
        $is = $this->def['issue'];
        $assigneeColumn = array_get($is, 'assignees');
        if (is_nullorempty($assigneeColumn)) {
            return null;
        }
        $multiple = $this->isMultiple($assigneeColumn);

        $members = $this->projectMembers($project);
        if (empty($members)) {
            return $this->emptyField('reason_none');
        }

        // 1. the category's own default
        $sure = is_array($categoryField) && boolval(array_get($categoryField, 'ok'))
            && ($threshold === null || floatval(array_get($categoryField, 'confidence', 0)) >= $threshold);
        if ($sure) {
            $value = array_get($categoryField, 'value');
            $categoryId = is_array($value) ? strval(reset($value)) : strval($value);
            $row = array_get($masters['categories'], $categoryId);
            $default = isset($row) ? array_get($row, 'assignee') : null;
            if (isset($default) && in_array(intval($default), $members, true)) {
                return $this->assigneeField(intval($default), $multiple, 'reason_category', [
                    'name' => strval(array_get($row, 'label')),
                ]);
            }
        }

        // 2. the least busy member
        $counts = $this->openCounts($project, $masters['done_status_ids']);
        $cap = static::assignCap();
        $best = null;
        $bestCount = null;
        $bestLatest = null;
        foreach ($members as $uid) {
            $count = intval(array_get($counts, $uid . '.count', 0));
            if ($count >= $cap) {
                continue;
            }
            $latest = strval(array_get($counts, $uid . '.latest', ''));
            if ($best === null || $count < $bestCount || ($count === $bestCount && strcmp($latest, strval($bestLatest)) < 0)) {
                $best = $uid;
                $bestCount = $count;
                $bestLatest = $latest;
            }
        }
        if ($best !== null) {
            return $this->assigneeField($best, $multiple, 'reason_least_busy', ['n' => $bestCount]);
        }

        // 3. everybody is at the cap
        return $this->emptyField('reason_none');
    }

    /**
     * @param int $uid
     * @param bool $multiple
     * @param string $reasonKey
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    protected function assigneeField(int $uid, bool $multiple, string $reasonKey, array $args): array
    {
        return [
            'value' => $multiple ? [strval($uid)] : strval($uid),
            'label' => $this->userName($uid),
            // Rule based, so there is no probability behind it: the number
            // says "the rule applied", and the reason says which rule.
            'confidence' => 1.0,
            'ok' => true,
            'reason' => PortalDefinition::trans('ai.' . $reasonKey, $args),
            'reason_key' => $reasonKey,
        ];
    }

    /**
     * Manager and members of the project, as user ids, manager first.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<int, int>
     */
    protected function projectMembers($project): array
    {
        $pj = $this->def['project'];
        $ids = [intval(array_get($project->value, array_get($pj, 'manager')))];
        foreach ((array) array_get($project->value, array_get($pj, 'members'), []) as $id) {
            $ids[] = intval($id);
        }
        // Then the people member organizations bring (ISS00333), after the
        // ones named, so a project without organizations picks as before.
        foreach (PortalMembership::roles((array) $project->value) as $id => $role) {
            if (in_array($role, PortalMembership::EDITORS, true)) {
                $ids[] = intval($id);
            }
        }

        return array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
    }

    /**
     * Open issues per assignee of this project, and when each was last
     * handed one.
     *
     * One indexed query for the rows, counted in PHP: the assignee column
     * is a JSON list, and a project's open issues are hundreds at most.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<int, string> $doneStatusIds
     * @return array<int, array{count: int, latest: string}>
     */
    protected function openCounts($project, array $doneStatusIds): array
    {
        if (!isset($this->issue_table) || !isset($this->project_table)) {
            return [];
        }
        $is = $this->def['issue'];
        $query = $this->issue_table->getValueModel()->newQuery()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name);

        $statusColumn = CustomColumn::getEloquent(array_get($is, 'status'), $this->issue_table);
        if (isset($statusColumn) && !empty($doneStatusIds)) {
            static::whereOpen($query, $statusColumn->getQueryKey(), $doneStatusIds);
        }

        $out = [];
        $assigneeColumn = array_get($is, 'assignees');
        foreach ($query->get() as $row) {
            $created = strval($row->created_at ?? '');
            foreach ((array) array_get($row->value, $assigneeColumn, []) as $uid) {
                $uid = intval($uid);
                if ($uid <= 0) {
                    continue;
                }
                $out[$uid]['count'] = intval(array_get($out, $uid . '.count', 0)) + 1;
                if (strcmp($created, strval(array_get($out, $uid . '.latest', ''))) > 0) {
                    $out[$uid]['latest'] = $created;
                }
            }
        }

        return $out;
    }

    /**
     * Narrow an issue query to the ones not finished.
     *
     * "Not one of the done statuses" alone is NOT IN, which is never true
     * for NULL: an issue with no status at all - a CSV import, a row written
     * by the API - counted as neither open nor done, so the batch never
     * filled it and nobody's workload included it.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param string $statusKey the status column's query key
     * @param array<int, string> $doneStatusIds
     * @return void
     */
    public static function whereOpen($query, string $statusKey, array $doneStatusIds): void
    {
        $query->where(function ($open) use ($statusKey, $doneStatusIds) {
            $open->whereNotIn($statusKey, $doneStatusIds)
                ->orWhereNull($statusKey)
                ->orWhere($statusKey, '');
        });
    }

    protected function userName(int $uid): string
    {
        $table = CustomTable::getEloquent(SystemTableName::USER);
        $row = isset($table) ? $table->getValueModel()->find($uid) : null;

        return isset($row) ? strval($row->getLabel()) : strval($uid);
    }

    // ---- log ------------------------------------------------------------------------

    /**
     * Complete the row reserveCall() wrote before the call.
     *
     * @param AiLog $log
     * @param array<string, mixed>|null $res the client's answer, null when there was none
     * @param array<string, mixed> $fields
     * @param string $status
     * @param string|null $error kept for the log table only; never shown
     * @return void
     */
    protected function finishLog(AiLog $log, ?array $res, array $fields, string $status = AiLog::STATUS_OK, ?string $error = null): void
    {
        $log->model = isset($res) ? mb_substr(strval(array_get($res, 'model', '')), 0, 32) : null;
        $log->input_tokens = isset($res) ? intval(array_get($res, 'usage.input_tokens', 0)) : 0;
        $log->latency_ms = isset($res) ? intval(array_get($res, 'latency_ms', 0)) : 0;
        $log->answers = isset($res) ? array_get($res, 'answers') : null;
        $log->suggested = $this->compactFields($fields);
        $log->status = $status;
        $log->error = isset($error) ? mb_substr($error, 0, 200) : null;
        $log->save();
    }

    /**
     * What the log keeps of a suggestion: the value, the label, the
     * confidence and the verdict. The translated reason is left out - it
     * is derived, and the lang file may change.
     *
     * @param array<string, array<string, mixed>|null> $fields
     * @return array<string, mixed>
     */
    protected function compactFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $column => $field) {
            if (!is_array($field)) {
                continue;
            }
            $entry = [
                'value' => array_get($field, 'value'),
                'label' => array_get($field, 'label'),
                'confidence' => array_get($field, 'confidence'),
                'ok' => boolval(array_get($field, 'ok')),
            ];
            foreach (['reason_key', 'severe', 'parts'] as $key) {
                if (array_key_exists($key, $field) && $field[$key] !== null) {
                    $entry[$key] = $field[$key];
                }
            }
            $out[$column] = $entry;
        }

        return $out;
    }
}
