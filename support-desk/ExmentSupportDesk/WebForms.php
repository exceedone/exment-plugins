<?php

namespace App\Plugins\ExmentITSupport;

/**
 * The website form of a 受付箱 (ISS02941 step 2): a public form of Exment (PublicForm) made for
 * the box by the core's PublicFormService (exment 7.3.0, ISS02942). What a visitor sends lands
 * in the box (fixed values: the box, the channel); the receipt mail, the spam guard and the
 * limit per hour are the public form's own.
 *
 * The plugin makes no form of its own: without the service (an older core) the button is not
 * there.
 */
class WebForms
{
    const SERVICE = '\\Exceedone\\Exment\\Services\\PublicFormService';

    /** What a box without fields of its own asks for. */
    const DEFAULT_COLUMNS = ['requester_name', 'requester_email', 'subject', 'description'];

    /**
     * The ticket's columns a visitor must not fill: the desk's own (status, SLA, who works on
     * it, links, what only staff see) and the fixed ones.
     */
    const DENIED = [
        'ticket_no', 'status', 'stage', 'progress', 'priority', 'category', 'channel', 'customer', 'contact', 'cc',
        'assignee', 'asset', 'tags', 'customer_visible', 'require_otp',
        'first_response_due', 'first_responded_at', 'resolve_due', 'resolved_at', 'closed_at',
        'sla_state', 'sla_paused_at', 'sla_escalation', 'last_customer_at', 'last_agent_at', 'reminded_at',
        'time_spent', 'csat', 'csat_comment', 'fingerprint', 'occurrences', 'last_occurred_at',
        'merged_into', 'problem', 'catalog', 'approval_state', 'approver',
    ];

    const RATE_LIMIT = 20;

    public static function available(): bool
    {
        return class_exists(static::SERVICE);
    }

    public static function ownerKey(int $boxId): string
    {
        return 'exment_it_support:box:' . $boxId;
    }

    /**
     * The columns of the form for a box's fields: the defaults when it has none; never a
     * denied one; the email of the person always (the receipt mail goes there).
     *
     * @param array<int, array{column: string, required: bool}> $fields
     * @return array{columns: array<int, string>, dropped: array<int, string>}
     */
    public static function columnsFor(array $fields): array
    {
        $wanted = empty($fields) ? static::DEFAULT_COLUMNS : array_column($fields, 'column');
        $columns = [];
        $dropped = [];
        foreach ($wanted as $c) {
            if (in_array($c, static::DENIED, true)) {
                $dropped[] = $c;
            } elseif (!in_array($c, $columns, true)) {
                $columns[] = $c;
            }
        }
        if (!in_array('requester_email', $columns, true)) {
            array_unshift($columns, 'requester_email');
        }

        return ['columns' => $columns, 'dropped' => $dropped];
    }

    /**
     * The columns a visitor must fill: the email always (the receipt mail), and those of the
     * box's fields marked 必須 that are on the form.
     *
     * @param array<int, array{column: string, required: bool}> $fields
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function requiredFor(array $fields, array $columns): array
    {
        $required = ['requester_email'];
        foreach ($fields as $f) {
            if (!empty($f['required'])) {
                $required[] = strval($f['column']);
            }
        }

        return array_values(array_intersect($columns, array_unique($required)));
    }

    /**
     * What every sending gets without the visitor choosing: the box, the channel, seen by the
     * customer; a subject when the form has none.
     *
     * @return array<string, mixed>
     */
    public static function fixedValues(int $boxId, string $boxName, array $columns): array
    {
        $fixed = ['category' => strval($boxId), 'channel' => 'form', 'customer_visible' => '1'];
        if (!in_array('subject', $columns, true)) {
            $fixed['subject'] = mb_substr($boxName . '（Web サイトのフォーム）', 0, 250);
        }

        return $fixed;
    }

    /**
     * Make the box's form, or bring it up to date (the same form: its tags stay).
     *
     * @return array{ok: bool, message: string, dropped?: array<int, string>}
     */
    public static function ensure(array $box): array
    {
        if (!static::available()) {
            return ['ok' => false, 'message' => 'この Exment には、公開フォームを作るしくみ（Exment 7.3.0 以降）がありません。'];
        }
        $table = Its::table(Its::T_TICKET);
        if (!isset($table) || empty($box['id'])) {
            return ['ok' => false, 'message' => '受付箱が見つかりません。'];
        }
        $cols = static::columnsFor($box['fields']);
        $service = static::SERVICE;
        $opts = [
            'view_name' => mb_substr('サポート窓口：' . $box['name'], 0, 40),
            'active' => true,
            'fixed_values' => static::fixedValues(intval($box['id']), strval($box['name']), $cols['columns']),
            'complete_title' => 'お問い合わせを受け付けました',
            'complete_text' => '担当者から、入力いただいたメールアドレスへご連絡します。',
            'notify_user_column' => 'requester_email',
            'required' => static::requiredFor($box['fields'], $cols['columns']),
            'rate_limit' => static::RATE_LIMIT,
        ];
        if (!empty($box['default_assignee'])) {
            $opts['notify_admin_users'] = [intval($box['default_assignee'])];
        }
        try {
            $service::ensure($table, static::ownerKey(intval($box['id'])), $cols['columns'], $opts);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => 'フォームを作れませんでした：' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => '「' . $box['name'] . '」のフォームを作りました（同じフォームを新しくしたときは、貼ったコードはそのまま使えます）。', 'dropped' => $cols['dropped']];
    }

    /**
     * The box's form and its tags, or null when it has none yet.
     *
     * @return array{active: bool, url: string, iframe: string, script: string}|null
     */
    public static function tagsOf(int $boxId): ?array
    {
        if (!static::available()) {
            return null;
        }
        $service = static::SERVICE;
        $form = $service::findByOwner(static::ownerKey($boxId));
        if (!isset($form)) {
            return null;
        }
        $tags = $service::embedTags($form);

        return [
            'active' => boolval($form->active_flg ?? true),
            'url' => strval($tags['url'] ?? ''),
            'iframe' => strval($tags['iframe'] ?? ''),
            'script' => strval($tags['script'] ?? ''),
        ];
    }
}
