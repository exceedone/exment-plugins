<?php

namespace App\Plugins\ExmentPMBacklog;

/**
 * The screen's and the log's words. Japanese only for now; the keys are
 * there for a second language.
 */
class Lang
{
    const JA = [
        'title' => 'Backlog 連携',
        'intro' => 'Backlog のプロジェクトの課題とコメントを、Exment PM のプロジェクトへ決まった間隔で取り込みます。Backlog が正です（Exment で直した内容は、Backlog の内容で上書きされます）。取り込みでは通知を出しません。',
        'connections' => 'つながり',
        'add' => 'つながりを追加',
        'none' => 'まだつながりがありません。',
        'col_name' => '名前',
        'col_backlog' => 'Backlog',
        'col_exment' => 'Exment のプロジェクト',
        'col_interval' => '間隔',
        'col_enabled' => '自動',
        'col_last' => '前回',
        'col_next' => '次回',
        'col_issues' => '取り込んだ課題',
        'minutes' => ':n 分',
        'on' => 'オン',
        'off' => 'オフ',
        'edit' => '設定',
        'back' => '一覧へ',
        'save' => '保存',
        'saved' => '保存しました。',
        'f_name' => '名前',
        'f_space_url' => 'Backlog のスペースの URL',
        'f_space_url_help' => '例: https://example.backlog.com（.jp のスペースもあります）',
        'f_api_key' => 'Backlog の API キー',
        'f_api_key_help_set' => '保存されています。変えるときだけ入れてください（画面には出しません）。',
        'f_api_key_help_unset' => 'Backlog の「個人設定 → API」で作ったキー。暗号化して保存し、あとから画面には出しません。キーの持ち主が見られる課題だけを取り込みます。',
        'f_project_key' => 'Backlog のプロジェクトキー',
        'f_exment_project' => '取り込み先の Exment のプロジェクト',
        'f_interval' => '間隔（分）',
        'f_interval_help' => '5 分以上。決まった時間に動かすには、サーバーで Exment のスケジュール（cron）が動いている必要があります。',
        'f_enabled' => '決まった間隔で自動に取り込む',
        'mapping' => '割り付け',
        'mapping_help' => 'Backlog の状態・種別・ユーザーなどを、Exment のものに合わせます。自動の取り込みは、割り付けのあとに始まります。',
        'mapping_refresh' => 'Backlog から読み直す',
        'mapping_create' => '読み直して、足りない状態・種別・カテゴリー・マイルストーンを作る',
        'mapping_done' => '割り付けを読み直しました。',
        'mapping_none' => 'まだ割り付けていません。',
        'mapping_missing' => '割り付けのないもの',
        'mapping_ok' => '状態・種別などは、すべて割り付けられています。',
        'users' => 'ユーザー',
        'users_help' => 'メールアドレスで自動に合わせています。合わない人は選んでください。いない人は担当者が空になり、名前は「移行元の担当者」に残ります。',
        'user_none' => '（いない）',
        'users_save' => 'ユーザーの割り付けを保存',
        'run' => '今すぐ同期',
        'run_help' => '1 回に :n 件まで取り込みます。残りは次の回に続きます。',
        'run_done' => '同期しました: 追加 :add・更新 :update・コメント :comment・エラー :error',
        'run_locked' => 'ほかの同期が動いているので、今回は動かしませんでした。',
        'run_failed' => '同期に失敗しました: :message',
        'runs' => '最近の同期',
        'r_started' => '開始',
        'r_trigger' => 'きっかけ',
        'r_status' => '結果',
        'r_counts' => '件数',
        'r_problems' => '気づいたこと',
        'trigger_schedule' => '自動',
        'trigger_manual' => '手動',
        'status_ok' => '成功',
        'status_partial' => '一部エラー',
        'status_failed' => '失敗',
        'status_running' => '実行中',
        'status_skipped' => '見送り',
        'delete' => 'つながりを削除',
        'delete_confirm' => 'このつながりを削除しますか？ Exment に取り込んだ課題は消えません（対応の記録だけが消えます）。',
        'deleted' => '削除しました。',
        'denied' => 'システム管理者だけが使えます。',
        'need_key' => 'API キーを入れてください。',
        'need_fields' => '名前・スペースの URL・プロジェクトキー・Exment のプロジェクトを入れてください。',
        'bad_url' => 'スペースの URL は https:// で始めてください。',
        'error_no_project' => 'Exment のプロジェクト :id が見つかりません。',
        'error_no_mapping' => 'まだ割り付けていません。設定画面の「Backlog から読み直す」を押してください。',
        'error_no_pm' => 'Exment PM の課題のテーブルが見つかりません。',
        'created_status' => '状態を作りました: :name',
        'created_type' => '種別を作りました: :name',
        'created_category' => 'カテゴリーを作りました: :name',
        'created_milestone' => 'マイルストーンを作りました: :name',
        'kind_status' => '状態',
        'kind_issue_type' => '種別',
        'kind_priority' => '優先度',
        'kind_category' => 'カテゴリー',
        'kind_milestone' => 'マイルストーン',
        'kind_resolution' => '完了理由',
        'problem_no_map' => ':key: :kind「:name」の割り付けがない',
        'problem_no_user' => ':key: 担当者「:name」が Exment のユーザーに割り付けられていない（名前は移行元の担当者に残した）',
        'problem_assignee_not_member_new' => ':key: 担当者がプロジェクトのメンバーでないので、担当者なしで取り込んだ',
        'problem_assignee_not_member_keep' => ':key: 担当者がプロジェクトのメンバーでないので、担当者は変えなかった',
        'problem_no_parent' => ':key: 親課題がまだ取り込まれていない',
        'problem_parent_refused' => ':key: 親課題をつなげなかった（:reason）',
        'problem_error' => 'エラー',
        'batch_none' => '動かすつながりはありません。',
        'batch_run' => ':name: :status（追加 :add・更新 :update・コメント :comment・エラー :error）',
    ];

    /**
     * @param array<string, mixed> $replace
     */
    public static function t(string $key, array $replace = []): string
    {
        $s = static::JA[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $s = str_replace(':' . $k, strval($v), $s);
        }

        return $s;
    }
}
