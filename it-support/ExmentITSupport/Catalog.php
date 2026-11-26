<?php

namespace App\Plugins\ExmentITSupport;

use App\Plugins\ExmentITSupport\Alerts\CatalogSpec;
use Exceedone\Exment\Model\CustomValue;

/**
 * The request catalog (the ManageEngine research of ISS02928, idea 1): ready-made request menus
 * ("an account does not work", "lend me a PC", "install a program", "give me access", "reset a
 * password"...) on the request page of the portal, each with its own fields, and, when the item
 * asks for it, an approval before the work starts.
 *
 * Off at first (the setting "catalog_enabled"). A request that needs an approval waits "on hold"
 * until its approver approves it on the approval page (the bell and a mail tell them); rejected,
 * it is closed and the requester is told.
 *
 * The approval is the plugin's own, one step, not an Exment workflow: a workflow belongs to a
 * whole table, and would change how every ticket is edited.
 */
class Catalog
{
    const APPROVAL_STATES = [
        'none' => '承認なし',
        'waiting' => '承認待ち',
        'approved' => '承認ずみ',
        'rejected' => '却下',
    ];

    const TAG_WAITING = '承認待ち';

    public static function enabled(): bool
    {
        return Its::setting('catalog_enabled', '0') === '1' && Its::table(Its::T_CATALOG) !== null;
    }

    public static function query()
    {
        return Its::table(Its::T_CATALOG)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
    }

    /**
     * The items shown on the portal, in their order.
     *
     * @return array<int, array{id: int, name: string, description: string, icon: string, approval: bool}>
     */
    public static function publicItems(): array
    {
        if (!static::enabled()) {
            return [];
        }
        $out = [];
        foreach (static::query()->where('value->public', '1')->get()->sortBy(function ($row) {
            return intval(array_get($row->value, 'display_order', 0)) * 100000 + intval($row->id);
        }) as $row) {
            $out[] = [
                'id' => intval($row->id),
                'name' => strval(array_get($row->value, 'name')),
                'description' => strval(array_get($row->value, 'description')),
                'icon' => preg_match('/^fa-[a-z0-9-]{1,40}$/', strval(array_get($row->value, 'icon'))) ? strval(array_get($row->value, 'icon')) : 'fa-file-text-o',
                'approval' => boolval(array_get($row->value, 'needs_approval', '0')),
            ];
        }

        return $out;
    }

    public static function item($id): ?CustomValue
    {
        if (!static::enabled() || !ctype_digit(strval($id))) {
            return null;
        }
        $row = static::query()->find(intval($id));

        return isset($row) && boolval(array_get($row->value, 'public', '1')) ? $row : null;
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, choices: array<int, string>}>
     */
    public static function fields(CustomValue $item): array
    {
        return CatalogSpec::parse(strval(array_get($item->value, 'fields')));
    }

    /**
     * The values a ticket of this item starts with: its subject, text, category, priority, and the
     * approval it waits for.
     *
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    public static function ticketValues(CustomValue $item, array $answers, string $note = ''): array
    {
        $v = (array) $item->value;
        $body = CatalogSpec::render(static::fields($item), $answers) . (trim($note) !== '' ? "\n\n" . trim($note) : '');
        $values = [
            'subject' => mb_substr('【申請】' . strval($v['name'] ?? ''), 0, 250),
            'description' => mb_substr($body, 0, 20000),
            'catalog' => $item->id,
            'category' => !empty($v['category']) ? $v['category'] : null,
            'priority' => isset(Its::PRIORITIES[strval($v['priority'] ?? '')]) ? strval($v['priority']) : 'normal',
            'tags' => '申請',
            'approval_state' => 'none',
        ];
        if (boolval($v['needs_approval'] ?? '0') && !empty($v['approver'])) {
            $values['approval_state'] = 'waiting';
            $values['approver'] = $v['approver'];
            $values['status'] = 'on_hold';
            $values['tags'] = '申請, ' . static::TAG_WAITING;
        }

        return $values;
    }

    /** After the ticket of a request is made: the approver is asked. */
    public static function askApproval(CustomValue $ticket): void
    {
        if (strval(array_get($ticket->value, 'approval_state')) !== 'waiting' || empty(array_get($ticket->value, 'approver'))) {
            return;
        }
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => '承認を待っています。承認されると、対応を始めます。', 'via' => 'staff']);
        Automation::notifyUsers($ticket, [strval(array_get($ticket->value, 'approver'))],
            '【承認のお願い】' . array_get($ticket->value, 'ticket_no') . ' ' . array_get($ticket->value, 'subject'),
            "次の申請の承認をお願いします。\n承認の画面: " . Its::url('approval', strval($ticket->id)) . "\n\n" . mb_substr(strval(array_get($ticket->value, 'description')), 0, 3000));
    }

    /** Whether the login user may decide this request: its approver, or a system administrator. */
    public static function mayDecide(CustomValue $ticket): bool
    {
        $user = \Exment::user();
        if (!isset($user)) {
            return false;
        }
        if (Desk::isAdmin()) {
            return true;
        }

        return strval(array_get($ticket->value, 'approver')) === strval($user->getUserId());
    }

    /**
     * Approve or reject a waiting request.
     *
     * @return bool false: it was not waiting
     */
    public static function decide(CustomValue $ticket, bool $approve, string $comment = ''): bool
    {
        if (strval(array_get($ticket->value, 'approval_state')) !== 'waiting') {
            return false;
        }
        $who = strval(optional(optional(\Exment::user())->base_user)->getValue('user_name'));
        $comment = mb_substr(trim($comment), 0, 2000);
        $tags = implode(', ', array_values(array_filter(array_map('trim', explode(',', strval(array_get($ticket->value, 'tags')))), function ($t) {
            return $t !== '' && $t !== static::TAG_WAITING;
        })));
        if ($approve) {
            $ticket->setValue(['approval_state' => 'approved', 'status' => 'open', 'tags' => $tags]);
            $ticket->save();
            Tickets::addMessage($ticket, ['kind' => 'system', 'body' => "{$who} さんが承認しました。" . ($comment !== '' ? "\n" . $comment : ''), 'via' => 'staff']);
            Automation::notifyAgents($ticket, '【承認されました】' . array_get($ticket->value, 'ticket_no') . ' ' . array_get($ticket->value, 'subject'), '承認されたので、対応を始めてください。');

            return true;
        }
        $ticket->setValue(['approval_state' => 'rejected', 'status' => 'closed', 'tags' => $tags]);
        $ticket->save();
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => "{$who} さんが却下しました。" . ($comment !== '' ? "\n" . $comment : ''), 'via' => 'staff']);
        $to = strval(array_get($ticket->value, 'requester_email'));
        if (Its::isEmail($to)) {
            $name = strval(array_get($ticket->value, 'requester_name')) ?: 'お客様';
            $text = "{$name} 様\n\n" . array_get($ticket->value, 'ticket_no') . ' の申請は、承認されませんでした。' . ($comment !== '' ? "\n\n" . $comment : '') . "\n\nご不明な点は、このメールに返信してください。";
            $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, $text, null));
            Tickets::addMessage($ticket, ['kind' => 'system', 'body' => '却下のメールを送りました。', 'via' => 'staff', 'mail_status' => $id !== null ? 'sent' : 'failed', 'message_id' => $id]);
        }

        return true;
    }

    /**
     * The ready-made items (put in from the settings): only those not there yet, by name. They
     * start without an approver: an administrator chooses one before they ask for approval.
     *
     * @return int the items added
     */
    public static function installTemplates(): int
    {
        $table = Its::table(Its::T_CATALOG);
        if (!isset($table)) {
            return 0;
        }
        $have = static::query()->get()->map(function ($r) {
            return strval(array_get($r->value, 'name'));
        })->all();
        $n = 0;
        foreach (static::templates() as $i => $t) {
            if (in_array($t['name'], $have, true)) {
                continue;
            }
            $row = $table->getValueModel();
            $row->setValue($t + ['public' => '1', 'display_order' => ($i + 1) * 10]);
            $row->save();
            $n++;
        }

        return $n;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function templates(): array
    {
        $systems = '対象のシステム|select|必須|パソコンのログイン,メール,VPN,社内システム,そのほか';

        return [
            ['name' => 'アカウントの問題（ログインできない・ロックされた）', 'icon' => 'fa-user-times', 'priority' => 'high', 'needs_approval' => '0',
                'description' => 'ログインできない、アカウントがロックされた、など。',
                'fields' => $systems . "\nユーザー ID|text|必須\nいつから|text\n困っていること|textarea|必須"],
            ['name' => 'パスワードの再設定', 'icon' => 'fa-key', 'priority' => 'high', 'needs_approval' => '0',
                'description' => 'パスワードを忘れた、期限が切れた。パスワードは書かないでください。',
                'fields' => $systems . "\nユーザー ID|text|必須\n連絡のつく電話番号|text"],
            ['name' => 'PC の貸し出し', 'icon' => 'fa-laptop', 'priority' => 'normal', 'needs_approval' => '1',
                'description' => '出張・研修などで、PC を借りたいとき。',
                'fields' => "使う人|text|必須\n借りる日|date|必須\n返す日|date|必須\n使い道|textarea\nWi-Fi のルーターも要る|yesno"],
            ['name' => 'ソフトのインストール', 'icon' => 'fa-download', 'priority' => 'normal', 'needs_approval' => '1',
                'description' => '仕事に使うソフトを PC に入れたいとき。',
                'fields' => "ソフトの名前|text|必須\n版（わかれば）|text\n入れる PC（管理番号・名前）|text|必須\n使う理由|textarea|必須"],
            ['name' => 'アクセスの申請（フォルダー・システム）', 'icon' => 'fa-unlock-alt', 'priority' => 'normal', 'needs_approval' => '1',
                'description' => '共有フォルダーや社内システムを使えるようにしてほしいとき。',
                'fields' => "対象（フォルダー・システムの名前）|text|必須\n権限|select|必須|見るだけ,変える\n使う人|text|必須\nいつまで|date\n理由|textarea|必須"],
        ];
    }
}
