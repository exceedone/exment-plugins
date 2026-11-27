<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\NotifyNavbar;
use Illuminate\Support\Facades\Log;

/**
 * What the desk does by itself, every few minutes (PluginBatch).
 *
 * Each step is small, idempotent and logged on the ticket, so it is always
 * possible to see why a ticket changed without asking anyone.
 */
class Automation
{
    /**
     * @return array<string, mixed> what each step did
     */
    public static function run(): array
    {
        $out = [];
        foreach (['mail' => [Inbox::class, 'fetch'], 'sla' => [static::class, 'checkSla'], 'escalation' => [Escalation::class, 'run'], 'pending' => [static::class, 'chasePending'],
            'close' => [static::class, 'closeResolved'], 'assets' => [static::class, 'assetExpiry'], 'chat_mail' => [GuestChat::class, 'mailUnread']] as $name => $step) {
            try {
                $out[$name] = call_user_func($step);
            } catch (\Throwable $e) {
                Log::error('ExmentITSupport batch step ' . $name . ' failed: ' . $e->getMessage(), ['exception' => $e]);
                $out[$name] = 'error';
            }
        }
        Its::patchState(['batch_at' => now()->toDateTimeString()]);

        return $out;
    }

    /**
     * Recompute the SLA state; tell the assignee (or every agent, when
     * nobody has it) the moment a ticket gets close to its limit or passes it.
     */
    public static function checkSla(): int
    {
        $changed = 0;
        foreach (Tickets::query()->whereIn('value->status', Its::ACTIVE_STATUSES)->cursor() as $ticket) {
            $old = strval(array_get($ticket->value, 'sla_state'));
            $new = Sla::state($ticket);
            if ($old === $new) {
                continue;
            }
            $ticket->setValue(['sla_state' => $new]);
            $ticket->save();
            $changed++;
            // the steps of the escalation take the place of these two notices when it is on
            if (in_array($new, ['warning', 'breached'], true) && !Escalation::enabled()) {
                $no = array_get($ticket->value, 'ticket_no');
                $title = $new === 'breached' ? "【SLA 超過】{$no} " : "【SLA まもなく期限】{$no} ";
                static::notifyAgents($ticket, $title . array_get($ticket->value, 'subject'),
                    '最初の返事の期限: ' . (array_get($ticket->value, 'first_response_due') ?: '—') . "\n解決の期限: " . (array_get($ticket->value, 'resolve_due') ?: '—'));
            }
        }

        return $changed;
    }

    /**
     * Waiting for the customer: a reminder after N days, then solved after M
     * days of silence. Both counted from our last answer.
     */
    public static function chasePending(): array
    {
        $remindDays = intval(Its::setting('pending_remind_days', 3));
        $resolveDays = intval(Its::setting('pending_resolve_days', 7));
        $reminded = 0;
        $resolved = 0;
        foreach (Tickets::query()->where('value->status', 'pending')->cursor() as $ticket) {
            $since = array_get($ticket->value, 'last_agent_at') ?: $ticket->updated_at;
            if (empty($since)) {
                continue;
            }
            $days = Carbon::parse($since)->diffInDays(Carbon::now());
            if ($resolveDays > 0 && $days >= $resolveDays) {
                Tickets::addMessage($ticket, ['kind' => 'system', 'body' => "お客様から {$resolveDays} 日返事がないので、解決にしました。", 'via' => 'staff']);
                $ticket->setValue(['status' => 'resolved']);
                $ticket->save();
                $resolved++;
                continue;
            }
            if ($remindDays > 0 && $days >= $remindDays && empty(array_get($ticket->value, 'reminded_at'))) {
                $to = strval(array_get($ticket->value, 'requester_email'));
                $id = null;
                if (Its::isEmail($to)) {
                    $name = strval(array_get($ticket->value, 'requester_name')) ?: 'お客様';
                    $text = "{$name} 様\n\n先日おたずねした件について、ご返事をお待ちしております。\nこのメールに返信していただければ、対応を続けます。" . ($resolveDays > 0 ? "\n\nご返事がない場合、数日後にこのチケットは解決とさせていただきます。" : '');
                    $link = Its::portalOn() && boolval(array_get($ticket->value, 'customer_visible', '1')) ? Tokens::linkFor(intval($ticket->id), $to) : null;
                    $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, $text, $link));
                }
                Tickets::addMessage($ticket, ['kind' => 'system', 'body' => '返事のお願いを送りました。', 'via' => 'staff', 'mail_status' => $id !== null ? 'sent' : 'failed', 'message_id' => $id]);
                $ticket->setValue(['reminded_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                $ticket->save();
                $reminded++;
            }
        }

        return ['reminded' => $reminded, 'resolved' => $resolved];
    }

    public static function closeResolved(): int
    {
        $days = intval(Its::setting('resolved_close_days', 7));
        if ($days <= 0) {
            return 0;
        }
        $closed = 0;
        $limit = Carbon::now()->subDays($days)->format('Y-m-d H:i:s');
        foreach (Tickets::query()->where('value->status', 'resolved')->where('value->resolved_at', '<=', $limit)->cursor() as $ticket) {
            $ticket->setValue(['status' => 'closed']);
            $ticket->save();
            $closed++;
        }

        return $closed;
    }

    /**
     * Warranty, licence, domain and certificate dates coming up: one internal
     * ticket per asset and date, made N days before. Once a day is enough.
     */
    public static function assetExpiry(): int
    {
        $today = Carbon::today()->format('Y-m-d');
        if (array_get(Its::state(), 'asset_checked_on') === $today) {
            return 0;
        }
        $assets = Its::table(Its::T_ASSET);
        if (!isset($assets)) {
            return 0;
        }
        $days = max(1, intval(Its::setting('asset_notice_days', 30)));
        $until = Carbon::today()->addDays($days)->format('Y-m-d');
        $made = 0;
        foreach (['warranty_end' => '保守・保証', 'expires_on' => 'ライセンス・ドメイン・証明書'] as $column => $label) {
            foreach ($assets->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
                ->whereNotNull('value->' . $column)->where('value->' . $column, '<=', $until)->where('value->' . $column, '>=', $today)->cursor() as $asset) {
                $date = strval(array_get($asset->value, $column));
                $fingerprint = 'asset:' . $asset->id . ':' . $column . ':' . $date;
                if (Tickets::query()->where('value->fingerprint', $fingerprint)->exists()) {
                    continue;
                }
                Tickets::create([
                    'subject' => "{$label}の期限が近づいています: " . array_get($asset->value, 'name') . "（{$date}）",
                    'description' => "IT 資産「" . array_get($asset->value, 'name') . "」の{$label}の期限は {$date} です。更新・買い替えの確認をしてください。",
                    'channel' => 'asset',
                    'priority' => 'normal',
                    'customer' => array_get($asset->value, 'customer'),
                    'asset' => $asset->id,
                    'customer_visible' => '0',
                    'fingerprint' => $fingerprint,
                    'tags' => '資産の期限',
                ]);
                $made++;
            }
        }
        Its::patchState(['asset_checked_on' => $today]);

        return $made;
    }

    /**
     * A solved ticket asks the customer for a rating, through a link. One
     * click is the rating; the comment is optional.
     */
    /** @var bool set while tickets are resolved without telling the customer (Problems::resolveAll) */
    public static $quiet = false;

    public static function askForRating(CustomValue $ticket): void
    {
        if (static::$quiet) {
            return;
        }
        if (Its::setting('csat_enabled', '1') !== '1' || !Its::portalOn()
            || !boolval(array_get($ticket->value, 'customer_visible', '1')) || !empty(array_get($ticket->value, 'csat'))) {
            return;
        }
        $to = strval(array_get($ticket->value, 'requester_email'));
        if (!Its::isEmail($to)) {
            return;
        }
        $link = Tokens::linkFor(intval($ticket->id), $to);
        $name = strval(array_get($ticket->value, 'requester_name')) ?: 'お客様';
        $lines = ["{$name} 様", '', 'お問い合わせの件を「解決」にしました。', 'よろしければ、対応の満足度を教えてください（クリックするだけです）。', ''];
        foreach ([5 => 'とても満足', 4 => '満足', 3 => 'ふつう', 2 => '不満', 1 => 'とても不満'] as $score => $label) {
            $lines[] = str_repeat('★', $score) . str_repeat('☆', 5 - $score) . " {$label}: {$link}?rate={$score}";
        }
        $lines[] = '';
        $lines[] = 'まだ解決していないときは、このメールに返信してください。チケットがもう一度開きます。';
        Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, implode("\n", $lines), null));
    }

    /**
     * The bell in Exment's header, plus a mail, for the assignee - or for
     * every agent when nobody has the ticket yet.
     */
    public static function notifyAgents(CustomValue $ticket, string $subject, string $body): void
    {
        $assignees = Tickets::assigneeIds($ticket);
        $ids = !empty($assignees) ? $assignees : Tickets::agentIds();
        static::notifyUsers($ticket, array_map('strval', $ids), $subject, $body);
    }

    /**
     * The bell and a mail (each when asked) for these users, about a ticket.
     *
     * @param array<int, string> $ids
     */
    public static function notifyUsers(CustomValue $ticket, array $ids, string $subject, string $body, bool $bell = true, bool $mail = true): void
    {
        $users = CustomTable::getEloquent(SystemTableName::USER);
        $url = Its::url('ticket', strval($ticket->id));
        foreach (array_slice($ids, 0, 50) as $id) {
            if ($bell) {
                static::bell($ticket, strval($id), $subject, $body . "\n" . $url);
            }
            if (!$mail) {
                continue;
            }
            $user = $users->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($id);
            $email = isset($user) ? strval(array_get($user->value, 'email')) : '';
            if (Its::isEmail($email)) {
                try {
                    \Illuminate\Support\Facades\Mail::raw($body . "\n\n" . $url, function ($m) use ($email, $subject) {
                        // without it, a site with no MAIL_FROM_ADDRESS has the mail refused
                        Mailer::setFrom($m);
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $e) {
                    Log::warning('ExmentITSupport agent mail failed: ' . $e->getMessage());
                }
            }
        }
    }

    protected static function bell(CustomValue $ticket, string $id, string $subject, string $body): void
    {
        try {
            $navbar = new NotifyNavbar();
            $navbar->notify_id = 0;
            $navbar->parent_type = Its::T_TICKET;
            $navbar->parent_id = $ticket->id;
            $navbar->target_user_id = $id;
            $navbar->trigger_user_id = null;
            $navbar->notify_subject = mb_substr($subject, 0, 200);
            $navbar->notify_body = $body;
            $navbar->save();
        } catch (\Throwable $e) {
            Log::warning('ExmentITSupport navbar notify failed: ' . $e->getMessage());
        }
    }
}
