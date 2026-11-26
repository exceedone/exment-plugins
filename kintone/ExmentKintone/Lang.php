<?php

namespace App\Plugins\ExmentKintone;

/**
 * The texts of the plugin: Japanese, and English for every other language.
 */
class Lang
{
    const TEXTS = [
        'ja' => [
            'domain' => 'サブドメイン（xxxx.cybozu.com）',
            'apps' => 'アプリの ID（カンマで区切る。API トークンのときは必須）',
            'login' => 'ログイン名（パスワードでつなぐとき）',
            'token' => 'API トークン（アプリごと、カンマで区切る）',
            'password' => 'パスワード',
            'bad_domain' => 'kintone のサブドメインではありません（例：example.cybozu.com）。',
            'no_auth' => 'API トークンか、ログイン名とパスワードを入れてください。',
            'not_allowed' => ':host にはつなげません（アドレスを確かめてください）。',
            'title' => 'kintone 連携',
            'intro' => 'kintone のアプリのレコードを、Exment のテーブルに取り込みます（Exment の「データ同期」を使います）。つながり・割り付け・実行は、管理画面の「データ同期」で行います。ここでは、kintone のアプリの定義から、Exment のテーブルを作れます。',
            'need_sync' => 'この Exment には「データ同期」がありません（Exment 7.3.0 以降で使えます）。',
            'need_feature' => '「機能の管理」で「データ同期」をオンにしてください。',
            'open_sync' => 'データ同期を開く',
            'make_header' => 'アプリの定義からテーブルを作る',
            'make_help' => 'つながりとアプリを選ぶと、同じ項目のテーブルと、項目の対応がそろった割り付け（自動の同期はオフ）を作ります。作ったあと、データ同期で試し実行をしてから、今すぐ同期してください。',
            'connection' => 'つながり',
            'app' => 'アプリ',
            'table_name' => 'テーブル名（半角英数字と _ ）',
            'table_view_name' => 'テーブルの表示名',
            'make' => 'テーブルを作る',
            'made' => 'テーブル「:table」と割り付けを作りました。:skipped',
            'skipped' => '作れなかった項目（手直しがいります）：:fields',
            'exists' => 'その名前のテーブルは、もうあります。',
            'bad_name' => 'テーブル名は、半角の英小文字で始まり、英小文字・数字・_ だけで 30 文字までにしてください。',
            'admin_only' => 'システム管理者だけが使えます。',
        ],
        'en' => [
            'domain' => 'Subdomain (xxxx.cybozu.com)',
            'apps' => 'App IDs (comma separated; needed with API tokens)',
            'login' => 'Login name (to connect with a password)',
            'token' => 'API tokens (one per app, comma separated)',
            'password' => 'Password',
            'bad_domain' => 'This is not a kintone subdomain (such as example.cybozu.com).',
            'no_auth' => 'Enter API tokens, or a login name and a password.',
            'not_allowed' => 'Cannot connect to :host (check the address).',
            'title' => 'kintone',
            'intro' => 'Imports the records of kintone apps into Exment tables, with the "Data sync" of Exment. The connections, the mappings and the runs are on the admin page "Data sync". Here you can make an Exment table from the definition of a kintone app.',
            'need_sync' => 'This Exment has no "Data sync" (Exment 7.3.0 or later).',
            'need_feature' => 'Turn on "Data sync" in Feature management.',
            'open_sync' => 'Open Data sync',
            'make_header' => 'Make a table from the definition of an app',
            'make_help' => 'Choose a connection and an app: a table with the same fields is made, and a mapping with its fields mapped (the schedule off). Then run a dry run in Data sync, and sync.',
            'connection' => 'Connection',
            'app' => 'App',
            'table_name' => 'Table name (letters, digits and _)',
            'table_view_name' => 'Table display name',
            'make' => 'Make the table',
            'made' => 'The table ":table" and its mapping are made. :skipped',
            'skipped' => 'Fields not made (to fix by hand): :fields',
            'exists' => 'A table of that name is already there.',
            'bad_name' => 'A table name starts with a lowercase letter and has only lowercase letters, digits and _, 30 characters at most.',
            'admin_only' => 'System administrators only.',
        ],
    ];

    /**
     * @param array<string, string> $replace
     */
    public static function t(string $key, array $replace = []): string
    {
        $locale = function_exists('app') ? strval(app()->getLocale()) : 'en';
        $texts = static::TEXTS[$locale === 'ja' ? 'ja' : 'en'];
        $text = $texts[$key] ?? static::TEXTS['en'][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, $value, $text);
        }
        return $text;
    }
}
