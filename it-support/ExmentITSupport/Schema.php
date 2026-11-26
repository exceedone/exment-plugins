<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\RoleGroupPermission;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\TemplateImportExport\TemplateImporter;
use Illuminate\Support\Facades\Log;

/**
 * The tables the help desk keeps, written once as data.
 *
 * The tables are ordinary Exment custom tables, so an administrator can add a
 * column, a view, a form, a workflow or an API client to any of them without
 * touching this plugin - the answer to "the framework is too rigid", the most
 * common complaint about the help desks on the market. What the plugin needs
 * to find is only the columns named here.
 *
 * install() and upgrade() are the same call: the fragment is imported with
 * $is_update = true, so a table or column that is missing is created and
 * everything that exists - including what an administrator changed - stays.
 * Adding a column in a later version is adding a line here and bumping
 * VERSION.
 */
class Schema
{
    /** Bumped whenever this file gains something an install has to apply. */
    /**
     * 2: messages remember which files they carry (attachment_uuids), so the
     * customer pages show only files from the customer's own conversation;
     * the menu gets the desk itself (受付箱) as its first entry.
     * 3: the menu is one entry, ITサポート, that opens the desk; the tables are
     * reached from the desk (see arrangeMenu()).
     * 4: the senders of alerts (its_intake_sources, ISS02928).
     * 5: the step of the SLA escalation told (sla_escalation).
     * 6: problems (its_problem, the ticket's column problem).
     */
    const VERSION = 6;

    /**
     * Tables in creation order: a select_table column needs its target first.
     *
     * Each column is [name, label, type, options]. Options are Exment's own
     * column options; 'valtext' is a shorthand for select_item_valtext.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tables(): array
    {
        $status = Its::STATUSES;
        $priority = Its::PRIORITIES;

        return [
            Its::T_SLA => [
                'label' => 'SLA（返事・解決の目安）',
                'icon' => 'fa-clock-o',
                'description' => 'お客様ごとの、最初の返事と解決までの目安の時間。営業時間で数えます。',
                'columns' => [
                    ['name', '名前', 'text', ['required' => '1', 'use_label_flg' => '1']],
                    ['is_default', '標準にする', 'yesno', ['help' => 'お客様に SLA が決まっていないときに使います。']],
                    ['business_hours_only', '営業時間だけで数える', 'yesno', ['default' => '1']],
                    ['first_urgent', '最初の返事（緊急・分）', 'integer', ['number_min' => '0', 'default' => '30']],
                    ['first_high', '最初の返事（高・分）', 'integer', ['number_min' => '0', 'default' => '60']],
                    ['first_normal', '最初の返事（中・分）', 'integer', ['number_min' => '0', 'default' => '240']],
                    ['first_low', '最初の返事（低・分）', 'integer', ['number_min' => '0', 'default' => '480']],
                    ['resolve_urgent', '解決（緊急・時間）', 'integer', ['number_min' => '0', 'default' => '4']],
                    ['resolve_high', '解決（高・時間）', 'integer', ['number_min' => '0', 'default' => '8']],
                    ['resolve_normal', '解決（中・時間）', 'integer', ['number_min' => '0', 'default' => '24']],
                    ['resolve_low', '解決（低・時間）', 'integer', ['number_min' => '0', 'default' => '72']],
                    ['note', 'メモ', 'textarea', []],
                ],
            ],
            Its::T_CUSTOMER => [
                'label' => 'お客様（会社）',
                'icon' => 'fa-building',
                'description' => 'サポートするお客様の会社。メールのドメインから、どのお客様のメールかを自動で決めます。',
                'columns' => [
                    ['name', '会社名', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1', 'index_enabled' => '1']],
                    ['code', 'お客様コード', 'text', ['index_enabled' => '1', 'freeword_search' => '1']],
                    ['email_domains', 'メールのドメイン', 'textarea', ['help' => '1 行に 1 つ（例: example.co.jp）。このドメインから来たメールは、このお客様のチケットになります。']],
                    ['sla', 'SLA', 'select_table', ['select_target_table_name' => Its::T_SLA]],
                    ['contract_hours', '月の契約時間（時間）', 'decimal', ['number_min' => '0', 'help' => '月ごとのレポートで、使った時間と比べます。空なら比べません。']],
                    ['phone', '電話番号', 'text', []],
                    ['address', '住所', 'text', []],
                    ['note', 'メモ（社内）', 'textarea', []],
                ],
            ],
            Its::T_CONTACT => [
                'label' => 'お客様の担当者',
                'icon' => 'fa-user',
                'description' => 'お客様の会社の人。メールアドレスで依頼とつながります。',
                'columns' => [
                    ['name', '名前', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1']],
                    ['email', 'メールアドレス', 'email', ['required' => '1', 'index_enabled' => '1', 'unique' => '1', 'freeword_search' => '1']],
                    ['customer', 'お客様（会社）', 'select_table', ['select_target_table_name' => Its::T_CUSTOMER, 'index_enabled' => '1']],
                    ['phone', '電話番号', 'text', []],
                    ['is_customer_admin', 'お客様の管理者', 'yesno', ['help' => 'はいにすると、ポータルで会社のチケットをすべて見られます。']],
                    ['user', 'Exment のユーザー', 'user', ['index_enabled' => '1', 'help' => 'ログインして顧客ポータルを使うときのユーザー。']],
                    ['note', 'メモ（社内）', 'textarea', []],
                ],
            ],
            Its::T_CATEGORY => [
                'label' => 'チケットの種類',
                'icon' => 'fa-tags',
                'description' => '依頼の種類（PC・Web サイト・システムなど）。種類ごとに最初の担当者を決められます。',
                'columns' => [
                    ['name', '種類', 'text', ['required' => '1', 'use_label_flg' => '1']],
                    ['description', '説明（受付フォームに出ます）', 'text', []],
                    ['default_assignee', '最初の担当者', 'user', []],
                    ['public', '受付フォームに出す', 'yesno', ['default' => '1']],
                    ['display_order', '並び順', 'integer', ['default' => '0']],
                ],
            ],
            Its::T_ASSET => [
                'label' => 'IT 資産',
                'icon' => 'fa-desktop',
                'description' => 'お客様の PC・サーバー・ライセンス・ドメインなど。期限が近づくと自動でチケットを作ります。',
                'columns' => [
                    ['name', '名前', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1']],
                    ['asset_tag', '管理番号', 'text', ['index_enabled' => '1', 'freeword_search' => '1']],
                    ['kind', '種類', 'select_valtext', ['valtext' => Its::ASSET_KINDS, 'default' => 'pc']],
                    ['customer', 'お客様（会社）', 'select_table', ['select_target_table_name' => Its::T_CUSTOMER, 'index_enabled' => '1']],
                    ['contact', '使っている人', 'select_table', ['select_target_table_name' => Its::T_CONTACT]],
                    ['maker_model', 'メーカー・型番', 'text', ['freeword_search' => '1']],
                    ['serial', 'シリアル番号', 'text', ['freeword_search' => '1']],
                    ['os', 'OS・バージョン', 'text', []],
                    ['location', '場所・IP アドレス', 'text', []],
                    ['purchase_date', '購入日', 'date', []],
                    ['warranty_end', '保守・保証の期限', 'date', ['index_enabled' => '1']],
                    ['expires_on', 'ライセンス・ドメイン・証明書の期限', 'date', ['index_enabled' => '1']],
                    ['note', 'メモ（社内）', 'textarea', ['help' => 'パスワードは書かないでください。']],
                ],
            ],
            Its::T_PROBLEM => [
                'label' => '問題',
                'icon' => 'fa-bug',
                'description' => '同じ原因のチケットをまとめるもの。原因・回避のしかた・解決のしかたを書き、つないだチケットをまとめて解決にできます。',
                'columns' => [
                    ['problem_no', '問題の番号', 'auto_number', ['auto_number_type' => 'format', 'auto_number_format' => 'PRB-${id:5}', 'index_enabled' => '1', 'freeword_search' => '1']],
                    ['title', '件名', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1']],
                    ['status', '状態', 'select_valtext', ['valtext' => Its::PROBLEM_STATUSES, 'default' => 'open', 'required' => '1', 'index_enabled' => '1']],
                    ['assignee', '担当者', 'user', ['index_enabled' => '1']],
                    ['description', '起きていること', 'textarea', ['freeword_search' => '1']],
                    ['root_cause', '原因', 'textarea', ['freeword_search' => '1']],
                    ['workaround', '回避のしかた', 'textarea', ['freeword_search' => '1']],
                    ['resolution', '解決のしかた', 'textarea', ['freeword_search' => '1']],
                    ['knowledge_id', 'ナレッジの ID', 'integer', ['help' => 'この問題から作ったナレッジ。']],
                    ['resolved_at', '解決した日時', 'datetime', []],
                ],
            ],
            Its::T_TICKET => [
                'label' => 'チケット',
                'icon' => 'fa-life-ring',
                'description' => 'お客様からの依頼・問い合わせと、社内で見つけた問題。',
                'columns' => [
                    ['ticket_no', 'チケット番号', 'auto_number', ['auto_number_type' => 'format', 'auto_number_format' => 'SUP-${id:5}', 'index_enabled' => '1', 'freeword_search' => '1']],
                    ['subject', '件名', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1']],
                    ['description', '内容', 'textarea', ['freeword_search' => '1']],
                    ['status', '状態', 'select_valtext', ['valtext' => $status, 'default' => 'new', 'required' => '1', 'index_enabled' => '1', 'grid_style' => 'pill', 'colors' => Its::STATUS_COLORS]],
                    ['priority', '優先度', 'select_valtext', ['valtext' => $priority, 'default' => 'normal', 'required' => '1', 'index_enabled' => '1', 'grid_style' => 'pill', 'colors' => Its::PRIORITY_COLORS]],
                    ['category', '種類', 'select_table', ['select_target_table_name' => Its::T_CATEGORY, 'index_enabled' => '1']],
                    ['channel', '受付の方法', 'select_valtext', ['valtext' => Its::CHANNELS, 'default' => 'staff', 'index_enabled' => '1']],
                    ['customer', 'お客様（会社）', 'select_table', ['select_target_table_name' => Its::T_CUSTOMER, 'index_enabled' => '1']],
                    ['contact', 'お客様の担当者', 'select_table', ['select_target_table_name' => Its::T_CONTACT, 'index_enabled' => '1']],
                    ['requester_name', '依頼した人', 'text', ['freeword_search' => '1']],
                    ['requester_email', '依頼した人のメール', 'email', ['index_enabled' => '1', 'freeword_search' => '1']],
                    ['cc', 'CC（メール）', 'text', ['help' => 'お客様への返事を一緒に送るアドレス。カンマ区切り。']],
                    ['assignee', '担当者', 'user', ['index_enabled' => '1', 'grid_style' => 'avatar', 'assign_me_enabled' => '1']],
                    ['asset', 'IT 資産', 'select_table', ['select_target_table_name' => Its::T_ASSET]],
                    ['tags', 'タグ', 'text', ['freeword_search' => '1', 'help' => 'カンマ区切り']],
                    ['customer_visible', 'お客様に見せる', 'yesno', ['default' => '1', 'help' => 'いいえのチケットは、お客様のリンク・ポータルに出ません（監視から自動でできたものなど）。']],
                    ['require_otp', '開くときに確認のコード', 'yesno', ['default' => '0', 'help' => 'はいにすると、リンクを開く人に、登録のメールアドレスへ送る 6 桁のコードを入れてもらいます。']],
                    ['first_response_due', '最初の返事の期限', 'datetime', ['index_enabled' => '1']],
                    ['first_responded_at', '最初に返事した日時', 'datetime', []],
                    ['resolve_due', '解決の期限', 'datetime', ['index_enabled' => '1']],
                    ['resolved_at', '解決した日時', 'datetime', ['index_enabled' => '1']],
                    ['closed_at', '完了した日時', 'datetime', []],
                    ['sla_state', 'SLA', 'select_valtext', ['valtext' => Its::SLA_STATES, 'default' => 'none', 'index_enabled' => '1', 'grid_style' => 'pill', 'colors' => Its::SLA_COLORS]],
                    ['sla_paused_at', 'SLA を止めた日時', 'datetime', []],
                    ['sla_escalation', 'SLA の知らせの段階', 'integer', ['default' => '0', 'help' => 'SLA のエスカレーションで、知らせた段階（0：まだ、1：担当者、2：リーダー、3：管理者）。']],
                    ['last_customer_at', 'お客様が最後に書いた日時', 'datetime', ['index_enabled' => '1']],
                    ['last_agent_at', '担当者が最後に返事した日時', 'datetime', []],
                    ['reminded_at', '返事のお願いを送った日時', 'datetime', []],
                    ['time_spent', '作業時間（分）', 'integer', ['number_min' => '0', 'default' => '0']],
                    ['csat', '満足度（1〜5）', 'integer', ['number_min' => '1', 'number_max' => '5']],
                    ['csat_comment', '満足度のコメント', 'textarea', []],
                    ['fingerprint', 'エラーの印', 'text', ['index_enabled' => '1', 'help' => '監視・エラーログから来たとき、同じエラーを 1 件にまとめるための印。']],
                    ['occurrences', '発生回数', 'integer', ['default' => '0']],
                    ['last_occurred_at', '最後に発生した日時', 'datetime', []],
                    ['merged_into', 'まとめた先', 'select_table', ['select_target_table_name' => Its::T_TICKET]],
                    ['problem', '問題', 'select_table', ['select_target_table_name' => Its::T_PROBLEM, 'index_enabled' => '1']],
                ],
            ],
            Its::T_MESSAGE => [
                'label' => 'チケットのやりとり',
                'icon' => 'fa-comments',
                'description' => 'チケットごとの返事・お客様のメール・社内メモ。',
                'columns' => [
                    ['kind', '種類', 'select_valtext', ['valtext' => Its::MESSAGE_KINDS, 'default' => 'note', 'required' => '1', 'index_enabled' => '1']],
                    ['body', '本文', 'textarea', ['freeword_search' => '1']],
                    ['author_name', '書いた人', 'text', []],
                    ['author_email', '書いた人のメール', 'email', []],
                    ['via', '経路', 'select_valtext', ['valtext' => Its::CHANNELS]],
                    ['mail_status', 'メール', 'select_valtext', ['valtext' => Its::MAIL_STATUSES, 'default' => 'none']],
                    ['message_id', 'Message-ID', 'text', ['index_enabled' => '1']],
                    ['time_spent', '作業時間（分）', 'integer', ['number_min' => '0', 'default' => '0']],
                    ['attachments', '添付ファイル', 'text', []],
                    ['attachment_uuids', '添付ファイルの ID', 'text', ['help' => 'このやりとりに付いたファイル。お客様のページには、お客様とのやりとりのファイルだけを出します。']],
                ],
            ],
            Its::T_KNOWLEDGE => [
                'label' => 'ナレッジ（FAQ）',
                'icon' => 'fa-book',
                'description' => '解決のしかたのまとめ。公開にしたものは、受付フォームとFAQのページに出ます。',
                'columns' => [
                    ['title', '題名', 'text', ['required' => '1', 'use_label_flg' => '1', 'freeword_search' => '1']],
                    ['body', '本文', 'textarea', ['freeword_search' => '1']],
                    ['category', '種類', 'select_table', ['select_target_table_name' => Its::T_CATEGORY]],
                    ['keywords', 'キーワード', 'text', ['freeword_search' => '1', 'help' => '探すときの言葉。カンマ区切り。']],
                    ['public', 'お客様に公開', 'yesno', ['default' => '0', 'index_enabled' => '1']],
                    ['views', '見られた回数', 'integer', ['default' => '0']],
                    ['helpful', '役に立った', 'integer', ['default' => '0']],
                    ['not_helpful', '役に立たなかった', 'integer', ['default' => '0']],
                    ['source_ticket', 'もとのチケット', 'select_table', ['select_target_table_name' => Its::T_TICKET]],
                ],
            ],
            Its::T_TEMPLATE => [
                'label' => '定型文',
                'icon' => 'fa-file-text-o',
                'description' => '返事によく使う文章。{name}・{ticket_no}・{subject}・{link} はチケットの値に置き換わります。',
                'columns' => [
                    ['name', '名前', 'text', ['required' => '1', 'use_label_flg' => '1']],
                    ['body', '本文', 'textarea', ['required' => '1']],
                    ['category', '種類', 'select_table', ['select_target_table_name' => Its::T_CATEGORY]],
                    ['set_status', '送ったあとの状態', 'select_valtext', ['valtext' => $status]],
                ],
            ],
        ];
    }

    /**
     * Seed rows for a fresh install, so the desk works on day one.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function seeds(): array
    {
        return [
            Its::T_SLA => [
                ['name' => '標準', 'is_default' => '1', 'business_hours_only' => '1',
                    'first_urgent' => 30, 'first_high' => 60, 'first_normal' => 240, 'first_low' => 480,
                    'resolve_urgent' => 4, 'resolve_high' => 8, 'resolve_normal' => 24, 'resolve_low' => 72],
                ['name' => 'プレミアム（24 時間）', 'is_default' => '0', 'business_hours_only' => '0',
                    'first_urgent' => 15, 'first_high' => 30, 'first_normal' => 120, 'first_low' => 240,
                    'resolve_urgent' => 2, 'resolve_high' => 4, 'resolve_normal' => 12, 'resolve_low' => 48],
            ],
            Its::T_CATEGORY => [
                ['name' => 'PC・周辺機器', 'description' => 'パソコンのセットアップ・故障・プリンターなど', 'public' => '1', 'display_order' => 1],
                ['name' => 'アカウント・パスワード', 'description' => 'ログインできない・ユーザーの追加や削除', 'public' => '1', 'display_order' => 2],
                ['name' => 'メール・Microsoft 365・Google', 'description' => 'メールが届かない・Teams・共有ドライブなど', 'public' => '1', 'display_order' => 3],
                ['name' => 'ネットワーク・Wi-Fi', 'description' => 'インターネットにつながらない・VPN', 'public' => '1', 'display_order' => 4],
                ['name' => 'Web サイト', 'description' => 'サイトの修正・表示されない・ドメイン', 'public' => '1', 'display_order' => 5],
                ['name' => 'システム・Exment', 'description' => '業務システムの不具合・使い方', 'public' => '1', 'display_order' => 6],
                ['name' => 'その他', 'description' => 'どれにも当てはまらないもの', 'public' => '1', 'display_order' => 9],
            ],
            Its::T_TEMPLATE => [
                ['name' => '受付しました', 'body' => "{name} 様\n\nお問い合わせありがとうございます。チケット {ticket_no} で受け付けました。\n担当者が確認し、あらためてご連絡いたします。\n\n進み具合はこちらからご覧いただけます。\n{link}", 'set_status' => 'open'],
                ['name' => '詳しく教えてください', 'body' => "{name} 様\n\n確認のため、次のことを教えていただけますか。\n\n・いつから起きていますか\n・画面に出ているメッセージ（スクリーンショットがあれば添付してください）\n・お使いの PC・ブラウザ\n\nこのメールにそのまま返信してください。", 'set_status' => 'pending'],
                ['name' => '対応しました（ご確認ください）', 'body' => "{name} 様\n\n{subject} の件、対応いたしました。\nお手数ですが、問題がなくなったかご確認ください。\n\nまだ問題があるときは、このメールに返信してください。チケットがもう一度開きます。", 'set_status' => 'resolved'],
            ],
            Its::T_KNOWLEDGE => [
                ['title' => 'パスワードを忘れたとき', 'body' => "1. ログイン画面の「パスワードを忘れた方」を押します。\n2. 登録のメールアドレスを入れます。\n3. 届いたメールのリンクから、新しいパスワードを決めます。\n\nメールが届かないときは、迷惑メールのフォルダも見てください。", 'keywords' => 'パスワード,ログイン,忘れた,リセット', 'public' => '1'],
                ['title' => 'インターネットにつながらないとき', 'body' => "1. ほかの PC・スマホではつながるか確かめます。\n2. Wi-Fi を一度オフにして、オンに戻します。\n3. ルーターの電源を抜き、30 秒待ってから入れ直します。\n\nそれでもつながらないときは、受付フォームからご連絡ください。", 'keywords' => 'ネット,Wi-Fi,つながらない,インターネット', 'public' => '1'],
                ['title' => 'プリンターで印刷できないとき', 'body' => "1. プリンターの電源と用紙・トナーを確かめます。\n2. PC の「設定 → プリンター」で、使うプリンターが「既定」になっているか見ます。\n3. 印刷の待ちを一度すべて消して、もう一度印刷します。", 'keywords' => 'プリンター,印刷,複合機', 'public' => '1'],
            ],
        ];
    }

    /** Role groups the setup makes, with the table permissions they get. */
    public static function roleGroups(): array
    {
        $edit = [Permission::CUSTOM_VALUE_EDIT_ALL];
        $view = [Permission::CUSTOM_VALUE_VIEW_ALL];

        return [
            Its::ROLE_AGENT => [
                'label' => 'ITサポート 担当者',
                'description' => 'チケットに返事をし、お客様・資産・ナレッジを編集できる人。',
                'tables' => [
                    Its::T_TICKET => $edit, Its::T_MESSAGE => $edit, Its::T_CUSTOMER => $edit,
                    Its::T_CONTACT => $edit, Its::T_ASSET => $edit, Its::T_KNOWLEDGE => $edit,
                    Its::T_TEMPLATE => $edit, Its::T_CATEGORY => $view, Its::T_SLA => $view, Its::T_PROBLEM => $edit,
                ],
            ],
            Its::ROLE_CUSTOMER => [
                'label' => 'ITサポート お客様',
                'description' => '顧客ポータルにログインするお客様。チケットは、ポータルから自分（お客様の管理者は会社）のものだけが見えます。テーブルの権限は付けません。',
                'tables' => [],
            ],
        ];
    }

    // ---- install / upgrade ---------------------------------------------

    /**
     * @return array{ok: bool, message: string, steps?: array<int, string>}
     */
    public static function install(bool $seed = true): array
    {
        $steps = [];
        try {
            $fresh = Its::table(Its::T_TICKET) === null;

            // The plugin's own tables. Exment runs database/migrations when
            // the zip is uploaded; this covers a copy put in place by hand.
            if ((!hasTable('its_tokens') || !hasTable('its_intake_sources')) && ($plugin = Its::plugin()) !== null) {
                \Exceedone\Exment\Services\Plugin\PluginInstaller::migrationInstall($plugin);
                $steps[] = 'プラグインのテーブルを作りました。';
            }

            $importer = new TemplateImporter();
            $importer->import(static::template(), false, true);
            System::clearCache();
            $steps[] = 'テーブルを作りました（すでにあるテーブル・列はそのまま）。';

            static::registerPluginRow();
            if (static::arrangeMenu()) {
                $steps[] = 'メニューを「ITサポート」1 つにしました（テーブルは受付箱から開きます）。';
            }

            $before = intval(array_get(Its::state(), 'schema_version', 0));
            if (!$fresh && $before < 2) {
                $n = static::backfillAttachmentUuids();
                $steps[] = "やりとりとファイルを {$n} 件つなぎました。";
            }

            $granted = static::grantRoles();
            if ($granted > 0) {
                $steps[] = "役割グループに権限を {$granted} 件付けました。";
            }

            if ($fresh && $seed) {
                $count = static::seed();
                $steps[] = "最初のデータ（SLA・種類・定型文・ナレッジ）を {$count} 件入れました。";
            }

            $settings = [];
            if (Its::setting('intake_key') === '') {
                $settings['intake_key'] = bin2hex(random_bytes(24));
            }
            if ($fresh) {
                // A new site takes alerts only with a sender's own key (ISS02928);
                // the shared key stays for /support/intake/mail.
                $settings['alert_shared_key'] = '0';
            }
            if (Its::setting('holidays') === '') {
                $settings['holidays'] = implode("\n", static::japaneseHolidays());
            }
            if (!empty($settings)) {
                Its::saveSettings($settings);
            }

            Its::patchState(['installed' => true, 'schema_version' => static::VERSION, 'installed_at' => now()->toDateTimeString()]);

            return ['ok' => true, 'message' => 'セットアップが終わりました。', 'steps' => $steps];
        } catch (\Throwable $e) {
            $ref = substr(sha1(uniqid('', true)), 0, 8);
            Log::error('ExmentITSupport setup failed [' . $ref . ']: ' . $e->getMessage(), ['exception' => $e]);

            return ['ok' => false, 'message' => 'セットアップできませんでした（ログの番号: ' . $ref . '）。', 'steps' => $steps];
        }
    }

    /**
     * Apply a newer schema by itself the first time an agent opens the desk or
     * the batch runs after an update, so a forgotten "テーブルを更新" can
     * not leave the plugin writing to columns that are not there yet (Exment
     * drops values for unknown columns without a word).
     */
    public static function ensureCurrent(): void
    {
        if (!static::needsUpgrade()) {
            return;
        }
        // After a failed attempt, wait a while instead of retrying the whole
        // install on every request.
        if (\Illuminate\Support\Facades\Cache::has('its_schema_upgrade_failed')) {
            return;
        }
        $lock = \Illuminate\Support\Facades\Cache::lock('its_schema_upgrade', 300);
        if (!$lock->get()) {
            return;
        }
        try {
            Its::forget();
            if (static::needsUpgrade()) {
                $result = static::install(false);
                if (!$result['ok']) {
                    \Illuminate\Support\Facades\Cache::put('its_schema_upgrade_failed', true, 900);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::put('its_schema_upgrade_failed', true, 900);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public static function needsUpgrade(): bool
    {
        return Its::isInstalled() && intval(array_get(Its::state(), 'schema_version', 0)) < static::VERSION;
    }

    /**
     * The template fragment Exment's importer understands.
     *
     * @return array<string, mixed>
     */
    public static function template(): array
    {
        $tables = [];
        $relations = [];
        foreach (static::tables() as $table_name => $table) {
            $columns = [];
            $order = 1;
            foreach ($table['columns'] as [$name, $label, $type, $options]) {
                $columns[] = [
                    'column_name' => $name,
                    'column_view_name' => $label,
                    'column_type' => $type,
                    'description' => null,
                    'system_flg' => '0',
                    'order' => strval($order++),
                    'options' => static::columnOptions($options),
                ];
            }

            $tables[] = [
                'table_name' => $table_name,
                'table_view_name' => $table['label'],
                'description' => $table['description'],
                'system_flg' => '0',
                'showlist_flg' => '1',
                'order' => '0',
                'options' => ['icon' => $table['icon'], 'search_enabled' => '1', 'revision_flg' => '1'],
                'custom_columns' => $columns,
                'custom_column_multisettings' => [],
            ];
        }

        $relations[] = [
            'relation_type' => '1',
            'options' => [],
            'parent_custom_table_name' => Its::T_TICKET,
            'child_custom_table_name' => Its::T_MESSAGE,
        ];

        return [
            'custom_tables' => $tables,
            'custom_relations' => $relations,
            'custom_views' => [static::kanbanView()],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function columnOptions(array $options): array
    {
        if (isset($options['valtext'])) {
            $options['select_item_valtext'] = implode("\n", array_map(function ($k, $v) {
                return $k . ',' . $v;
            }, array_keys($options['valtext']), $options['valtext']));
            unset($options['valtext']);
        }
        if (isset($options['colors'])) {
            $options['grid_value_colors'] = implode("\n", array_map(function ($k, $v) {
                return $k . ',' . $v;
            }, array_keys($options['colors']), $options['colors']));
            unset($options['colors']);
        }

        return $options;
    }

    /** A board of the tickets by status, for people who like to drag. */
    protected static function kanbanView(): array
    {
        $col = function (string $name, int $order, array $options = []) {
            return [
                'view_column_type' => '0',
                'order' => strval($order),
                'options' => $options,
                'view_column_table_name' => Its::T_TICKET,
                'view_column_target_name' => $name,
            ];
        };

        return [
            'suuid' => 'its0kanban0status001',
            'table_name' => Its::T_TICKET,
            'view_type' => '0',
            'view_kind_type' => '5',
            'view_view_name' => 'ボード（状態別）',
            'default_flg' => '0',
            'order' => '10',
            'options' => [
                'kanban_source' => 'column',
                'kanban_group_column' => Its::T_TICKET . '.status',
                'kanban_title_column' => Its::T_TICKET . '.subject',
                'kanban_editable' => '1',
                'kanban_labels_column' => Its::T_TICKET . '.priority',
                'kanban_labels_style' => 'bar',
                'kanban_assignee_column' => Its::T_TICKET . '.assignee',
                'kanban_limit_column' => Its::T_TICKET . '.resolve_due',
                'kanban_limit_warn' => '24',
                'kanban_swimlane_column' => Its::T_TICKET . '.priority',
                'kanban_kpi' => '1',
                'kanban_drawer' => '1',
                'kanban_col_count' => '30',
                'kanban_max_count' => '300',
                'condition_join' => 'and',
            ],
            'custom_view_columns' => [
                $col('ticket_no', 10, ['kanban_position' => 'meta']),
                $col('customer', 20, ['kanban_position' => 'meta']),
                $col('sla_state', 30, ['kanban_position' => 'meta']),
            ],
            'custom_view_filters' => [],
            'custom_view_sorts' => [],
            'custom_view_summaries' => [],
            'custom_view_grid_filters' => [],
        ];
    }

    /**
     * Exment calls an event plugin only for the tables in its
     * options.target_tables, so the ticket table is put there; anything an
     * administrator added stays.
     */
    protected static function registerPluginRow(): void
    {
        $plugin = Its::plugin();
        if (!isset($plugin)) {
            return;
        }
        $options = (array) $plugin->options;
        $tables = array_values(array_filter(array_map('strval', stringToArray(array_get($options, 'target_tables', []))), 'strlen'));
        if (!in_array(Its::T_TICKET, $tables, true)) {
            $tables[] = Its::T_TICKET;
        }
        $triggers = array_values(array_filter(array_map('strval', stringToArray(array_get($options, 'event_triggers', []))), 'strlen'));
        foreach (['saving', 'saved'] as $t) {
            if (!in_array($t, $triggers, true)) {
                $triggers[] = $t;
            }
        }
        $options['target_tables'] = $tables;
        $options['event_triggers'] = $triggers;
        if (is_nullorempty(array_get($options, 'batch_cron'))) {
            $options['batch_cron'] = '*/5 * * * *';
        }
        $plugin->options = $options;
        if ($plugin->isDirty()) {
            $plugin->save();
        }
        Its::forget();
    }

    /**
     * The menu is one entry, ITサポート, that opens the desk (schema 3, the same
     * as ExmentPM in ISS00256). The tables are reached from the desk.
     *
     * Up to 1.0.6 the menu was the folder its_root with the desk and the
     * tables in it. The entry takes the place of the folder, the rows the
     * plugin made in the folder go, and the folder stays only when an
     * administrator put something of their own in it. Once there is no
     * folder, a later run only keeps the entry pointing at the plugin: a
     * name or place an administrator gave it stays.
     *
     * @return bool whether the old folder was taken apart
     */
    /**
     * The menu entry before the setup (7.2.1): on a fresh site the plugin made it only
     * in the setup, so nobody knew where to start. The same entry the setup keeps
     * (its_desk, in the group "ITサポート" when the core has it), made once; the desk
     * shows the setup until it is done. Only those who may use the plugin see an
     * entry of a plugin - on a fresh site, the system administrators. A site that is
     * set up is left alone.
     */
    public static function ensurePreSetupMenu(): void
    {
        if (Its::isInstalled() || !hasTable('admin_menu')) {
            return;
        }
        if (\DB::table('admin_menu')->where('menu_type', 'plugin')->where('menu_name', 'its_desk')->exists()) {
            return;
        }
        static::arrangeMenu();
        \Exceedone\Exment\Model\System::clearCache();
    }

    protected static function arrangeMenu(): bool
    {
        $plugin = Its::plugin();
        if (!isset($plugin)) {
            return false;
        }
        $root = \DB::table('admin_menu')->where('menu_type', 'parent_node')->where('menu_name', 'its_root')->first();
        $desk = \DB::table('admin_menu')->where('menu_type', 'plugin')->where('menu_name', 'its_desk')->first();
        $now = now();

        // the group "ITサポート" of the feature map, when the core has the groups (7.2.1)
        $group = method_exists(\Exceedone\Exment\Model\Menu::class, 'groupParentId')
            ? \Exceedone\Exment\Model\Menu::groupParentId('it_support') : null;

        $values = ['menu_target' => $plugin->id];
        if (isset($root)) {
            $values += ['parent_id' => $root->parent_id, 'order' => $root->order, 'title' => 'ITサポート', 'icon' => 'fa-life-ring'];
        }
        // still at the top (an administrator did not move it): into the group
        $parentNow = array_key_exists('parent_id', $values) ? intval($values['parent_id']) : (isset($desk) ? intval($desk->parent_id) : 0);
        if (isset($group) && $parentNow === 0) {
            $values['parent_id'] = $group;
            $values['order'] = 1;
        }
        if (isset($desk)) {
            $changed = array_filter($values, function ($v, $k) use ($desk) {
                return strval($desk->{$k}) !== strval($v);
            }, ARRAY_FILTER_USE_BOTH);
            if (!empty($changed)) {
                \DB::table('admin_menu')->where('id', $desk->id)->update($changed + ['updated_at' => $now]);
            }
        } else {
            \DB::table('admin_menu')->insert($values + [
                'parent_id' => $group ?? 0,
                'order' => isset($group) ? 1 : 40,
                'title' => 'ITサポート',
                'icon' => 'fa-life-ring',
                'uri' => '',
                'menu_type' => 'plugin',
                'menu_name' => 'its_desk',
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!isset($root)) {
            return false;
        }
        $gone = \DB::table('admin_menu')->where('parent_id', $root->id)->where('menu_type', 'table')
            ->whereIn('menu_name', array_keys(static::tables()))->pluck('id')->all();
        if (!\DB::table('admin_menu')->where('parent_id', $root->id)->whereNotIn('id', $gone)->exists()) {
            $gone[] = $root->id;
        }
        if (hasTable('admin_role_menu')) {
            \DB::table('admin_role_menu')->whereIn('menu_id', $gone)->delete();
        }
        \DB::table('admin_menu')->whereIn('id', $gone)->delete();

        return true;
    }

    /**
     * Messages written before schema 2 do not say which files they carried.
     * A file is linked to a customer message or a reply only when nothing
     * else could own it: attach() stores the file just before it saves the
     * message, so the file must be 0-30 seconds older than the message; no
     * other message of any kind (an internal note included) may list the
     * same name in that window; and the message may not have a second file
     * of that name in it. Anything unclear stays hidden from the customer.
     * Ticket by ticket, so a large install is not read at once.
     */
    public static function backfillAttachmentUuids(): int
    {
        $messages = Its::table(Its::T_MESSAGE);
        $docs = CustomTable::getEloquent(\Exceedone\Exment\Enums\SystemTableName::DOCUMENT);
        if (!isset($messages, $docs)) {
            return 0;
        }
        $scope = \Exceedone\Exment\Model\CustomValueModelScope::class;
        $ticketIds = $messages->getValueModel()->query()->withoutGlobalScope($scope)
            ->where('parent_type', Its::T_TICKET)->whereIn('value->kind', ['customer', 'reply'])
            ->whereNotNull('value->attachments')->where('value->attachments', '<>', '')
            ->distinct()->pluck('parent_id')->all();

        $names = function ($m) {
            return array_values(array_filter(array_map('trim', explode(',', strval(array_get($m->value, 'attachments'))))));
        };
        $inWindow = function ($docAt, $msgAt) {
            $gap = $msgAt - $docAt;

            return $gap >= 0 && $gap <= 30;
        };

        $linked = 0;
        foreach ($ticketIds as $ticketId) {
            $all = $messages->getValueModel()->query()->withoutGlobalScope($scope)
                ->where('parent_type', Its::T_TICKET)->where('parent_id', $ticketId)->get();
            $files = $docs->getValueModel()->query()->withoutGlobalScope($scope)
                ->where('parent_type', Its::T_TICKET)->where('parent_id', $ticketId)->get();
            foreach ($files as $doc) {
                $name = strval(array_get($doc->value, 'document_name'));
                // A row without a time cannot be placed: leave the file hidden.
                $listing = $all->filter(function ($m) use ($name, $names) {
                    return in_array($name, $names($m), true);
                });
                $sameName = $files->filter(function ($d) use ($name) {
                    return strval(array_get($d->value, 'document_name')) === $name;
                });
                if (empty($doc->created_at) || $listing->contains(function ($m) {
                    return empty($m->created_at);
                }) || $sameName->contains(function ($d) {
                    return empty($d->created_at);
                })) {
                    continue;
                }
                $docAt = \Carbon\Carbon::parse($doc->created_at)->getTimestamp();
                $owners = $listing->filter(function ($m) use ($docAt, $inWindow) {
                    return $inWindow($docAt, \Carbon\Carbon::parse($m->created_at)->getTimestamp());
                });
                if ($owners->count() !== 1) {
                    continue;
                }
                $m = $owners->first();
                if (!in_array(strval(array_get($m->value, 'kind')), ['customer', 'reply'], true)) {
                    continue;
                }
                $msgAt = \Carbon\Carbon::parse($m->created_at)->getTimestamp();
                $twins = $sameName->filter(function ($d) use ($msgAt, $inWindow) {
                    return $inWindow(\Carbon\Carbon::parse($d->created_at)->getTimestamp(), $msgAt);
                });
                if ($twins->count() !== 1) {
                    continue;
                }
                $have = array_values(array_filter(array_map('trim', explode(',', strval(array_get($m->value, 'attachment_uuids'))))));
                $uuid = strval(array_get($doc->value, 'file_uuid'));
                if ($uuid === '' || in_array($uuid, $have, true)) {
                    continue;
                }
                $have[] = $uuid;
                $m->setValue(['attachment_uuids' => implode(',', $have)]);
                $m->save();
                $linked++;
            }
        }

        return $linked;
    }

    protected static function grantRoles(): int
    {
        $created = 0;
        $plugin = Its::plugin();
        $order = 50;

        foreach (static::roleGroups() as $name => $meta) {
            $group = RoleGroup::where('role_group_name', $name)->first();
            if (!isset($group)) {
                $group = new RoleGroup();
                $group->role_group_name = $name;
                $group->role_group_view_name = $meta['label'];
                $group->role_group_order = $order++;
                $group->description = $meta['description'];
                $group->save();
            }

            $targets = [];
            foreach ($meta['tables'] as $table_name => $permissions) {
                $table = CustomTable::getEloquent($table_name);
                if (isset($table)) {
                    $targets[] = [RoleType::TABLE, $table->id, $permissions];
                }
            }
            if (isset($plugin)) {
                $targets[] = [RoleType::PLUGIN, $plugin->id, [Permission::PLUGIN_ACCESS]];
            }

            foreach ($targets as [$type, $id, $permissions]) {
                $exists = RoleGroupPermission::where('role_group_id', $group->id)
                    ->where('role_group_permission_type', $type)
                    ->where('role_group_target_id', $id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $permission = new RoleGroupPermission();
                $permission->role_group_id = $group->id;
                $permission->role_group_permission_type = $type;
                $permission->role_group_target_id = $id;
                $permission->permissions = array_values($permissions);
                $permission->save();
                $created++;
            }
        }

        return $created;
    }

    protected static function seed(): int
    {
        $count = 0;
        foreach (static::seeds() as $table_name => $rows) {
            $table = Its::table($table_name);
            if (!isset($table) || $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->exists()) {
                continue;
            }
            foreach ($rows as $row) {
                $model = $table->getValueModel();
                $model->setValue($row);
                $model->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Japanese public holidays for this year and the next, so the SLA clock
     * is right without anyone typing them in. Editable on the settings
     * screen; nothing here is fetched from the network.
     *
     * @return array<int, string>
     */
    public static function japaneseHolidays(): array
    {
        $out = [];
        $year = intval(date('Y'));
        foreach ([$year, $year + 1] as $y) {
            foreach (static::holidaysOf($y) as $date) {
                $out[] = $date;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @return array<int, string>
     */
    protected static function holidaysOf(int $y): array
    {
        $nth = function (int $month, int $n) use ($y) {
            // n-th Monday of the month
            $first = mktime(0, 0, 0, $month, 1, $y);
            $offset = (8 - intval(date('N', $first))) % 7;

            return sprintf('%04d-%02d-%02d', $y, $month, 1 + $offset + 7 * ($n - 1));
        };
        $spring = intval(floor(20.8431 + 0.242194 * ($y - 1980) - floor(($y - 1980) / 4)));
        $autumn = intval(floor(23.2488 + 0.242194 * ($y - 1980) - floor(($y - 1980) / 4)));

        $days = [
            "$y-01-01", $nth(1, 2), "$y-02-11", "$y-02-23", sprintf('%04d-03-%02d', $y, $spring),
            "$y-04-29", "$y-05-03", "$y-05-04", "$y-05-05", $nth(7, 3), "$y-08-11",
            $nth(9, 3), sprintf('%04d-09-%02d', $y, $autumn), $nth(10, 2), "$y-11-03", "$y-11-23",
        ];

        // Substitute holiday: a holiday on Sunday moves to the next weekday
        // that is not a holiday itself.
        $set = array_flip($days);
        foreach ($days as $d) {
            if (date('N', strtotime($d)) == 7) {
                $next = strtotime($d . ' +1 day');
                while (isset($set[date('Y-m-d', $next)])) {
                    $next = strtotime('+1 day', $next);
                }
                $set[date('Y-m-d', $next)] = true;
            }
        }
        // A weekday between two holidays is a holiday too (Silver Week).
        foreach (array_keys($set) as $d) {
            $mid = date('Y-m-d', strtotime($d . ' +1 day'));
            $after = date('Y-m-d', strtotime($d . ' +2 day'));
            if (!isset($set[$mid]) && isset($set[$after]) && date('N', strtotime($mid)) < 7) {
                $set[$mid] = true;
            }
        }

        $list = array_keys($set);
        sort($list);

        return $list;
    }
}
