<?php

namespace App\Plugins\ExmentPluginPM;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The AI endpoints of a project: what the issue form calls when its button
 * is pressed, what it reports back after a save, and what the settings tab
 * reads and tests.
 *
 * Permissions are the portal's own: findProject() for access, then the
 * same create / edit check the save itself will make, so nobody can have
 * the AI read a text they could not save. What spends the project's daily
 * allowance or shows its figures - the settings tab's test and statistics -
 * is the manager's (PortalMembership::canManage). The key never comes this
 * way - only whether one exists.
 *
 * A refusal answers in the shape every portal screen reads: ok false, a
 * machine code in `error` (and in `reason`, which the form branches on),
 * and the Japanese sentence to show in `message`.
 */
trait PortalAiTrait
{
    // ---- payload helpers -------------------------------------------------

    /**
     * What the issue form needs to decide whether to show the button and
     * how to label what comes back.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, mixed>
     */
    protected function aiPayload($project): array
    {
        $autofill = new IssueAutofill();
        $configured = JevClient::configured();

        return [
            'enabled' => $configured && $autofill->projectFlag($project, 'ai_enabled', false),
            'configured' => $configured,
            'min_subject' => 3,
            'min_description' => IssueAutofill::minDescription(),
            'threshold_suggest' => IssueAutofill::thresholdSuggest(),
            'threshold_auto' => IssueAutofill::thresholdAuto(),
        ];
    }

    /**
     * The AI pane of the settings tab: switches, connection and the last
     * thirty days in numbers.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, mixed>
     */
    protected function aiStateData($project): array
    {
        $autofill = new IssueAutofill();

        return [
            'configured' => JevClient::configured(),
            'model' => JevClient::model(),
            'enabled' => $autofill->projectFlag($project, 'ai_enabled', false),
            'assign' => $autofill->suggestsAssignee($project),
            'min_description' => IssueAutofill::minDescription(),
            'threshold_suggest' => IssueAutofill::thresholdSuggest(),
            'threshold_auto' => IssueAutofill::thresholdAuto(),
            'daily_cap' => IssueAutofill::dailyCap(),
            'today' => AiLog::countToday($project->id),
            'labels' => $this->aiColumnLabels(),
            'stats' => $this->aiStats($project, 30),
        ];
    }

    /**
     * Calls, cost and how often a person kept what was suggested.
     *
     * "Kept" is counted per column over the rows that have an applied value:
     * the feedback endpoint writes it when the form is saved, the batch
     * writes it for itself. A column the AI was not sure about is not
     * counted - it was never offered. A call that never got an answer - an
     * error, or one still pending because its request died - counts as an
     * error.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param int $days
     * @return array<string, mixed>
     */
    protected function aiStats($project, int $days): array
    {
        $rows = AiLog::recent($project->id, $days)->get();

        $calls = 0;
        $errors = 0;
        $tokens = 0;
        $ms = 0;
        $kept = [];
        foreach ($rows as $row) {
            $calls++;
            if (strval($row->status) !== AiLog::STATUS_OK) {
                $errors++;
                continue;
            }
            $tokens += intval($row->input_tokens);
            $ms += intval($row->latency_ms);

            $suggested = is_array($row->suggested) ? $row->suggested : [];
            $applied = is_array($row->applied) ? $row->applied : null;
            if ($applied === null) {
                continue;
            }
            foreach ($suggested as $column => $field) {
                if (!is_array($field) || !boolval(array_get($field, 'ok')) || !array_key_exists($column, $applied)) {
                    continue;
                }
                $n = intval(array_get($kept, $column . '.n', 0)) + 1;
                $same = intval(array_get($kept, $column . '.kept', 0));
                if ($this->aiSameValue(array_get($field, 'value'), $applied[$column])) {
                    $same++;
                }
                $kept[$column] = ['n' => $n, 'kept' => $same];
            }
        }

        $okCalls = $calls - $errors;

        return [
            'days' => $days,
            'calls' => $calls,
            'errors' => $errors,
            'avg_ms' => $okCalls > 0 ? intdiv($ms, $okCalls) : 0,
            'tokens' => $tokens,
            'cost_usd' => round(JevClient::costUsd($tokens), 4),
            'kept' => $kept,
        ];
    }

    /**
     * Whether two column values name the same rows, however they are shaped:
     * "12", 12, ["12"] and [12] are all one value. Anything that is not a
     * plain value - a nested list in a row written before the feedback was
     * checked - is ignored rather than allowed to take the statistics down.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function aiSameValue($a, $b): bool
    {
        $norm = function ($v) {
            $out = [];
            foreach (is_array($v) ? $v : [$v] as $x) {
                if (is_scalar($x) && !is_bool($x) && strval($x) !== '') {
                    $out[] = strval($x);
                }
            }
            sort($out);

            return $out;
        };

        return $norm($a) === $norm($b);
    }

    /**
     * What a feedback call may store as "applied": only the columns the AI
     * suggested on that very call, and only a value shaped like one - an id,
     * a list of ids, or null for a field saved empty.
     *
     * @param mixed $applied as posted
     * @param array<string, mixed> $suggested the log row's suggested columns
     * @param array<int, string> $allowed the AI columns of this install
     * @return array<string, mixed>
     */
    protected function aiCleanApplied($applied, array $suggested, array $allowed): array
    {
        $clean = [];
        if (!is_array($applied)) {
            return $clean;
        }
        foreach ($applied as $column => $value) {
            $column = strval($column);
            if (!in_array($column, $allowed, true) || !array_key_exists($column, $suggested)) {
                continue;
            }
            if ($value === null) {
                $clean[$column] = null;
                continue;
            }
            if (is_scalar($value) && !is_bool($value)) {
                $clean[$column] = mb_substr(strval($value), 0, 64);
                continue;
            }
            if (!is_array($value) || count($value) > 50) {
                continue;
            }
            $list = [];
            foreach ($value as $item) {
                if (!is_scalar($item) || is_bool($item)) {
                    // A nested list is not a column value: drop the column.
                    continue 2;
                }
                $list[] = mb_substr(strval($item), 0, 64);
            }
            $clean[$column] = $list;
        }

        return $clean;
    }

    /**
     * A request field as a plain string. A list posted where a text belongs
     * is not a text.
     *
     * @param mixed $value
     * @return string
     */
    protected function aiString($value): string
    {
        return is_scalar($value) && !is_bool($value) ? strval($value) : '';
    }

    // ---- endpoints ---------------------------------------------------------

    /**
     * POST {id}/ai/suggest - score a subject and description against this
     * project's lists.
     *
     * 'category' is the category the form holds right now, saved or not;
     * the assignee's category step reads it instead of the stored one.
     *
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function aiSuggest(Request $request, $id)
    {
        $project = $this->findProject($id);
        $autofill = new IssueAutofill();

        // An archived project's issues cannot be saved; a call would be
        // paid for an answer nobody can keep. Asked first, so the answer
        // names the reason rather than a bare "no permission" - everybody
        // past findProject() may see the project is archived anyway.
        if ($autofill->projectFlag($project, 'archived', false)) {
            return $this->aiFail(403, 'archived');
        }

        // The same gate the save will meet: editing that issue
        // (LightApiController::update asks PortalDefinition::enableEdit,
        // which also asks the project), or filing a new one under this
        // project (resolveParent asks the same two questions as
        // childAddable). Nobody gets the AI to read a text they cannot save
        // - or to spend a project's allowance they cannot file anything in.
        $issue = null;
        $issueId = $request->input('issue_id');
        if (!is_nullorempty($issueId)) {
            if (!is_scalar($issueId)) {
                return PortalDefinition::jsonError(422, 'invalid_params', [
                    'message' => strval(PortalDefinition::trans('message.invalid_params')),
                ]);
            }
            $issue = $this->findIssue($project, $issueId);
            if (PortalDefinition::enableEdit($issue, true) !== true) {
                return PortalDefinition::jsonError(403, 'forbidden');
            }
        } elseif (!$this->childAddable($this->issue_table ?? null, $project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        if (!$autofill->projectFlag($project, 'ai_enabled', false)) {
            return $this->aiFail(403, 'disabled');
        }
        if (!JevClient::configured()) {
            return $this->aiFail(503, 'err_not_configured');
        }

        // Cut before anything reads them: the text is masked and trimmed
        // again on the way out, this only bounds the work on a pasted log.
        $subject = trim(mb_substr($this->aiString($request->input('subject', '')), 0, IssueAutofill::SCAN_MAX_CHARS));
        $description = mb_substr($this->aiString($request->input('description', '')), 0, IssueAutofill::SCAN_MAX_CHARS);
        if (mb_strlen($subject) < 3) {
            return $this->aiFail(422, 'need_text');
        }

        // A cheap early answer. The cap itself is enforced where the call
        // is logged, before it is made (AiLog::reserve), so requests that
        // arrive together cannot all slip under it.
        if (AiLog::countToday($project->id) >= IssueAutofill::dailyCap()) {
            return $this->aiFail(429, 'err_daily_cap');
        }

        $input = ['subject' => $subject, 'description' => $description];
        if ($request->has('category')) {
            // Checked against this project's own categories where it is used.
            $input['category'] = $request->input('category');
        }

        try {
            $result = $autofill->suggest($project, $input, $issue, AiLog::KIND_SUGGEST);
        } catch (JevException $e) {
            return $this->aiError($e);
        } catch (\Throwable $e) {
            return $this->aiCrash($e, 'suggest');
        }

        return response()->json(['ok' => true] + $result);
    }

    /**
     * POST {id}/ai/feedback - what the person finally saved, against what
     * was suggested. This is the number the settings tab reports.
     *
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function aiFeedback(Request $request, $id)
    {
        $project = $this->findProject($id);

        $suggestionId = $request->input('suggestion_id');
        $log = is_scalar($suggestionId)
            ? AiLog::where('id', intval($suggestionId))->where('project_id', intval($project->id))->first()
            : null;
        if (!isset($log)) {
            return PortalDefinition::jsonError(404, 'not_found');
        }

        // Only whoever asked may say what they kept - or the project's
        // manager. Any other member could otherwise rewrite the figures the
        // settings tab reports.
        $me = intval(\Exment::getUserId());
        if (intval($log->created_user_id) !== $me && !PortalMembership::canManage($project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        $log->applied = $this->aiCleanApplied(
            $request->input('applied'),
            is_array($log->suggested) ? $log->suggested : [],
            (new IssueAutofill())->aiColumns()
        );

        // A suggestion made while the issue was being written is linked to
        // the issue once there is one.
        $issueId = $request->input('issue_id');
        if (is_scalar($issueId) && !is_nullorempty($issueId) && isset($this->issue_table)) {
            $issue = $this->issue_table->getValueModel()->find(intval($issueId));
            if (isset($issue) && intval($issue->parent_id) === intval($project->id)) {
                $log->issue_id = intval($issue->id);
            }
        }
        $log->save();

        return response()->json(['ok' => true]);
    }

    /**
     * GET {id}/ai/state - the AI pane's numbers, for whoever manages the
     * project.
     *
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function aiState(Request $request, $id)
    {
        $project = $this->findProject($id);
        if (!PortalMembership::canManage($project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        return response()->json(['ok' => true, 'ai' => $this->aiStateData($project)]);
    }

    /**
     * POST {id}/ai/test - one call with a sample report, so the tab can show
     * the connection works and what an answer looks like. It spends one of
     * the project's calls for the day, so it is the manager's to press.
     *
     * @param Request $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function aiTest(Request $request, $id)
    {
        $project = $this->findProject($id);
        if (!PortalMembership::canManage($project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }
        if (!JevClient::configured()) {
            return $this->aiFail(503, 'err_not_configured');
        }
        // A test call costs what a real one does, and counts the same.
        if (AiLog::countToday($project->id) >= IssueAutofill::dailyCap()) {
            return $this->aiFail(429, 'err_daily_cap');
        }

        try {
            $result = (new IssueAutofill())->suggest($project, [
                'subject' => strval(PortalDefinition::trans('ai.test_subject')),
                'description' => strval(PortalDefinition::trans('ai.test_description')),
            ], null, AiLog::KIND_TEST);
        } catch (JevException $e) {
            return $this->aiError($e);
        } catch (\Throwable $e) {
            return $this->aiCrash($e, 'test');
        }

        return response()->json(['ok' => true, 'labels' => $this->aiColumnLabels()] + $result);
    }

    /**
     * Issue column name => the label people see, for the test output.
     *
     * @return array<string, string>
     */
    protected function aiColumnLabels(): array
    {
        $out = [];
        if (isset($this->issue_table)) {
            foreach ($this->issue_table->custom_columns as $column) {
                $out[strval($column->column_name)] = strval($column->column_view_name);
            }
        }

        return $out;
    }

    // ---- answers -----------------------------------------------------------

    /**
     * A refusal the form can show as it is.
     *
     * @param int $status
     * @param string $langKey key under ai; also the code in `error` and `reason`
     * @return JsonResponse
     */
    protected function aiFail(int $status, string $langKey): JsonResponse
    {
        $message = PortalDefinition::trans('ai.' . $langKey);

        return PortalDefinition::jsonError($status, $langKey, array_merge(
            ['reason' => $langKey],
            PortalDefinition::isUnresolved($message) ? [] : ['message' => strval($message)]
        ));
    }

    /**
     * The remote side did not answer. 503 for all of them: the form's own
     * save is unaffected, and the message says which kind it was. A spent
     * daily allowance is the portal's own refusal, and keeps its 429.
     *
     * @param JevException $e
     * @return JsonResponse
     */
    protected function aiError(JevException $e): JsonResponse
    {
        if ($e->reason === 'daily_cap') {
            return $this->aiFail(429, 'err_daily_cap');
        }

        Log::warning('pm_portal ai call failed: ' . $e->getMessage());

        $message = PortalDefinition::trans($e->langKey());
        if (PortalDefinition::isUnresolved($message)) {
            $message = PortalDefinition::trans('ai.err_failed');
        }

        return PortalDefinition::jsonError(503, strval($e->reason), [
            'message' => strval($message),
            'reason' => $e->reason,
        ]);
    }

    /**
     * Something this code did not plan for. Logged for the operator; the
     * person gets a sentence, never the exception's text.
     *
     * @param \Throwable $e
     * @param string $what
     * @return JsonResponse
     */
    protected function aiCrash(\Throwable $e, string $what): JsonResponse
    {
        Log::error('pm_portal ai ' . $what . ' failed: ' . $e->getMessage(), ['exception' => $e]);

        return PortalDefinition::jsonError(500, 'server_error');
    }
}
