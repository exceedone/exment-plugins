<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\ModelBase;

/**
 * One call to the AI, and what became of it.
 *
 * Written for three reasons: the daily cap counts rows here, the settings
 * tab's statistics read them, and the backtest compares what was suggested
 * with what the person finally saved (the "applied" column, filled by the
 * feedback endpoint or by the batch itself). The raw answers are kept
 * so a criteria change can be re-scored offline without calling again.
 *
 * The row is written before the call is made, as "pending", and finished
 * afterwards. A request that dies half way - a timeout, a crash, a parse
 * failure - has still spent an attempt, and the cap has to see it.
 *
 * input_hash identifies what was scored. For the button it is the text; for
 * the batch it also covers the questions, so the batch can tell an issue it
 * already scored from one whose text or whose project's lists changed since.
 *
 * created_user_id is the person who pressed the button; null for the
 * batch.
 *
 * @property mixed $project_id
 * @property mixed $issue_id
 * @property mixed $kind
 * @property mixed $input_hash
 * @property mixed $model
 * @property mixed $input_tokens
 * @property mixed $latency_ms
 * @property mixed $answers
 * @property mixed $suggested
 * @property mixed $applied
 * @property mixed $status
 * @property mixed $error
 * @property mixed $created_user_id
 * @phpstan-consistent-constructor
 */
class AiLog extends ModelBase
{
    public const KIND_SUGGEST = 'suggest';
    public const KIND_BATCH = 'batch';
    public const KIND_BACKTEST = 'backtest';
    public const KIND_TEST = 'test';

    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';
    /** Written before the call; a row left in this state never got an answer. */
    public const STATUS_PENDING = 'pending';

    protected $table = 'pm_portal_ai_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'answers' => 'json',
        'suggested' => 'json',
        'applied' => 'json',
    ];

    /**
     * How many calls this project has made today, errors included - a key
     * that keeps failing still costs attempts.
     *
     * @param mixed $project_id
     * @return int
     */
    public static function countToday($project_id): int
    {
        return static::where('project_id', intval($project_id))
            ->where('created_at', '>=', \Carbon\Carbon::today())
            ->count();
    }

    /**
     * Claim one call of today's allowance, as a pending row, or null when
     * the allowance is spent.
     *
     * The row goes in first and is counted afterwards, against the rows
     * written before it. Checking first and writing afterwards let every
     * request that arrived together pass the same "299 < 300"; counting by
     * id after the insert lets exactly the ones that fit through.
     *
     * @param mixed $project_id
     * @param mixed $issue_id
     * @param string $kind
     * @param string $hash
     * @param int $cap calls per project per day; 0 for no limit
     * @return static|null
     */
    public static function reserve($project_id, $issue_id, string $kind, string $hash, int $cap): ?self
    {
        $row = new static();
        $row->project_id = intval($project_id);
        $row->issue_id = is_nullorempty($issue_id) ? null : intval($issue_id);
        $row->kind = mb_substr($kind, 0, 16);
        $row->input_hash = $hash;
        $row->status = static::STATUS_PENDING;
        $row->save();

        if ($cap > 0) {
            $upToMe = static::where('project_id', intval($project_id))
                ->where('created_at', '>=', \Carbon\Carbon::today())
                ->where('id', '<=', $row->id)
                ->count();
            if ($upToMe > $cap) {
                $row->delete();

                return null;
            }
        }

        return $row;
    }

    /**
     * Whether the batch already has an answer for exactly this: the same
     * issue, the same text and the same questions.
     *
     * Only an answered call counts. A failed one is worth trying again; an
     * answered one would cost the same money for the same answer.
     *
     * @param mixed $issue_id
     * @param string $hash
     * @return bool
     */
    public static function batchSeen($issue_id, string $hash): bool
    {
        return static::where('issue_id', intval($issue_id))
            ->where('kind', static::KIND_BATCH)
            ->where('input_hash', $hash)
            ->where('status', static::STATUS_OK)
            ->exists();
    }

    /**
     * The rows the statistics are drawn from: real use only, over a window.
     *
     * @param mixed $project_id
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function recent($project_id, int $days = 30)
    {
        return static::where('project_id', intval($project_id))
            ->whereIn('kind', [self::KIND_SUGGEST, self::KIND_BATCH])
            ->where('created_at', '>=', \Carbon\Carbon::today()->subDays($days));
    }

    /**
     * Drop every row of one project.
     *
     * Project ids are not forever: exment:refreshdata truncates the custom
     * tables and numbering starts again at 1, so a brand new project would
     * otherwise inherit an old one's calls - its statistics, and a daily
     * allowance already spent. Called for a project that was just created
     * and for one that is being deleted.
     *
     * @param int $projectId
     * @return void
     */
    public static function forgetProject(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }
        static::where('project_id', $projectId)->delete();
    }

    /**
     * Drop every row that points at one issue, for the same reason: a new
     * issue may carry the id of one that is gone, and the batch must not
     * take the old issue's answer for the new one's.
     *
     * @param int $issueId
     * @return void
     */
    public static function forgetIssue(int $issueId): void
    {
        if ($issueId <= 0) {
            return;
        }
        static::where('issue_id', $issueId)->delete();
    }
}
