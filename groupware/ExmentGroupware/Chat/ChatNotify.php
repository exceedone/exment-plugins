<?php

namespace App\Plugins\ExmentGroupware\Chat;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Notifications\MailSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The notices of the chat (ISS02945 step 3): who is told of a message, and how.
 *
 * - A direct room: the other person. A group room: the people named (@名前), everyone for
 *   @全員. Never the writer, never someone who muted the room.
 * - Always the bell. Mail or Teams as the person chose for ExmentPM's notices
 *   (user setting pmportal.notify_by: bell / mail / teams / both), when that is set.
 * - Sent after the response (or at the end of a command).
 */
class ChatNotify
{
    const SETTING = 'pmportal.notify_by';
    const CHOICES = ['bell', 'mail', 'teams', 'both'];

    /** @var array<int, array> */
    protected static $pending = [];

    protected static $hooked = false;

    /**
     * Who is told of a message, from its room and its mentions (no database).
     *
     * @param array<int, array{user_id: int, muted: bool}> $members
     * @param array<int, int|string> $mentions
     * @return array<int, int>
     */
    public static function recipients(string $kind, array $members, array $mentions, int $writer): array
    {
        $all = $kind === 'direct' || in_array('all', $mentions, true);
        $named = array_map('intval', array_filter($mentions, 'is_int'));
        $out = [];
        foreach ($members as $m) {
            $id = intval($m['user_id']);
            if ($id === $writer || !empty($m['muted'])) {
                continue;
            }
            if ($all || in_array($id, $named, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** Mail and Teams by a choice (the bell always). */
    public static function channels(string $choice): array
    {
        $c = in_array($choice, static::CHOICES, true) ? $choice : 'bell';

        return ['mail' => in_array($c, ['mail', 'both'], true), 'teams' => in_array($c, ['teams', 'both'], true)];
    }

    public static function note(int $roomId, array $message, string $url): void
    {
        static::$pending[] = ['room' => $roomId, 'message' => $message, 'url' => $url];
        if (!static::$hooked) {
            static::$hooked = true;
            app()->terminating(function () {
                static::flush();
            });
        }
    }

    public static function flush(): void
    {
        $list = static::$pending;
        static::$pending = [];
        foreach ($list as $one) {
            try {
                static::send($one['room'], $one['message'], $one['url']);
            } catch (\Throwable $e) {
                Log::warning('[ExmentGroupware] chat notice failed: ' . class_basename($e));
            }
        }
    }

    protected static function send(int $roomId, array $m, string $url): void
    {
        $room = DB::table('gw_chat_rooms')->where('id', $roomId)->first();
        if (!isset($room)) {
            return;
        }
        $members = DB::table('gw_chat_members')->where('room_id', $roomId)->whereNotNull('user_id')->get(['user_id', 'muted'])
            ->map(function ($r) {
                return ['user_id' => intval($r->user_id), 'muted' => boolval($r->muted)];
            })->all();
        $to = static::recipients(strval($room->kind), $members, (array) ($m['mentions'] ?? []), intval($m['by'] ?? 0));
        if ($to === []) {
            return;
        }
        $roomName = $room->kind === 'direct' ? strval($m['name'] ?? '') : strval($room->name ?? '');
        $subject = mb_substr(str_replace(['<', '>', '${'], ['＜', '＞', '$ {'], 'チャット：' . $roomName . ' ' . strval($m['name'] ?? '')), 0, 200);
        $text = mb_substr(strval($m['body'] ?? ''), 0, 500) ?: '（ファイル）';
        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $to)->get();
        $choices = static::choicesOf($to);
        foreach ($users as $user) {
            $uid = intval($user->id);
            try {
                $bell = new NotifyNavbar();
                $bell->fill([
                    'notify_id' => 0,
                    'parent_id' => null,
                    'parent_type' => null,
                    'target_user_id' => $uid,
                    'notify_subject' => $subject,
                    'notify_body' => mb_substr(e($text), 0, 1800) . "\n\n" . '<a href="' . e($url) . '">' . e($url) . '</a>',
                    'trigger_user_id' => intval($m['by'] ?? 0) ?: null,
                ]);
                $bell->save();
            } catch (\Throwable $e) {
                Log::warning('[ExmentGroupware] chat bell to user ' . $uid . ' failed: ' . class_basename($e));
            }
            $ch = static::channels($choices[$uid] ?? 'bell');
            if ($ch['mail'] && !is_nullorempty($user->getValue('email'))) {
                try {
                    MailSender::make(null, $user)->subject($subject)
                        ->body(str_replace('${', '$ {', $text) . "\n\n" . $url)->send();
                } catch (\Throwable $e) {
                    Log::warning('[ExmentGroupware] chat mail to user ' . $uid . ' failed: ' . class_basename($e));
                }
            }
            if ($ch['teams'] && method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal')) {
                try {
                    call_user_func(['Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal'], [
                        'user' => $user, 'custom_value' => null, 'subject' => $subject, 'body' => str_replace('${', '$ {', $text), 'url' => $url,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[ExmentGroupware] chat Teams to user ' . $uid . ' failed: ' . class_basename($e));
                }
            }
        }
    }

    /** @return array<int, string> user id => their choice of ExmentPM's notices (one read) */
    protected static function choicesOf(array $ids): array
    {
        $out = [];
        try {
            foreach (\Exceedone\Exment\Model\UserSetting::whereIn('base_user_id', $ids)->get(['base_user_id', 'settings']) as $row) {
                $v = strval(array_get((array) ($row->settings ?? []), static::SETTING));
                if (in_array($v, static::CHOICES, true)) {
                    $out[intval($row->base_user_id)] = $v;
                }
            }
        } catch (\Throwable $e) {
            // the bell only
        }

        return $out;
    }
}
