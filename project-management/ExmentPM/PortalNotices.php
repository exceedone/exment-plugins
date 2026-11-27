<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\NotifyNavbar;

/**
 * The notices of the bell inside the portal (ISS02939 item 6): a dropdown on a wide screen, a
 * sheet on a phone, where Exment's own list (/notify_navbar) is a desktop table. Only the
 * person's own rows, the newest 30, unread first. A notice opens through Exment's
 * notify_navbar/rowdetail/{id}, which marks it read and opens the record (the portal's screen
 * for an issue, RecordUrl), as Exment's bell does. Push notices (step 2) open here too.
 */
class PortalNotices
{
    const LIMIT = 30;

    /** @return array{items: array<int, array<string, mixed>>, unread: int} */
    public static function list(int $userId): array
    {
        if ($userId <= 0) {
            return ['items' => [], 'unread' => 0];
        }
        $rows = NotifyNavbar::withoutGlobalScopes()->where('target_user_id', $userId)
            ->orderBy('read_flg')->orderByDesc('created_at')->orderByDesc('id')
            ->limit(static::LIMIT)->get(['id', 'parent_type', 'parent_id', 'notify_subject', 'read_flg', 'created_at']);
        $unread = intval(NotifyNavbar::withoutGlobalScopes()->where('target_user_id', $userId)->where('read_flg', 0)->count());

        return ['items' => $rows->map(function ($r) {
            return static::item(intval($r->id), $r->parent_type, strval($r->notify_subject), boolval($r->read_flg),
                $r->created_at ? PortalTime::format($r->created_at, 'Y-m-d H:i') : '');
        })->values()->all(), 'unread' => $unread];
    }

    /**
     * One notice as the screen gets it: text only (the screen puts it in with textContent), and
     * where a tap goes: the record through Exment (marked read on the way), or the notice itself.
     *
     * @return array{id: int, subject: string, read: bool, at: string, url: string}
     */
    public static function item(int $id, $parentType, string $subject, bool $read, string $at, ?callable $url = null): array
    {
        $url = $url ?? function (string $path) {
            return admin_url($path);
        };
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? '');

        return [
            'id' => $id,
            'subject' => mb_strimwidth($subject, 0, 200, '…'),
            'read' => $read,
            'at' => $at,
            'url' => strval(!is_null($parentType) && strval($parentType) !== '' ? $url('notify_navbar/rowdetail/' . $id) : $url('notify_navbar/' . $id)),
        ];
    }

    /** 「すべて既読」: the person's own unread notices only. */
    public static function readAll(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return intval(NotifyNavbar::withoutGlobalScopes()->where('target_user_id', $userId)->where('read_flg', 0)->update(['read_flg' => 1]));
    }
}
