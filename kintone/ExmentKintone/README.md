# Exment Plugin kintone 連携 (ExmentKintone)

The kintone connector of the data sync of Exment (Exment 7.3.0 or later): the records of kintone apps are imported into Exment tables on a schedule, also for a move from kintone to Exment.
kintone のアプリのレコードを、Exment のテーブルに決まった時間に取り込みます（Exment 7.3.0 以降の「データ同期」のコネクタ）。kintone から Exment への乗り換えにも使えます。

| | |
|---|---|
| Plugin name / プラグイン名 | ExmentKintone |
| Type / 種類 | page |
| Address / アドレス | `/admin/plugins/exment_kintone` |
| Needs / 必要なもの | Exment 7.3.0 or later, "Data sync" on in Feature management / Exment 7.3.0 以降、機能の管理で「データ同期」がオン |

**Unreleased / 未リリース**

- The first version (ISS00113). / 最初の版です。
  - A connection of "Data sync" to a kintone subdomain, with API tokens (one per app) or the login name and the password of a user. The secrets are kept encrypted by Exment and never shown again.
    「データ同期」のつながりで、kintone のサブドメインに、API トークン（アプリごと）か、ユーザーのログイン名とパスワードでつなぎます。秘密の値は Exment が暗号化して持ち、画面には出しません。
  - The records of an app are imported into a table: the ones changed since the last run (by the updated time and the record id), the attachments as the attachments of each record, the users by their login name (a mail address), a record deleted in kintone marked once a day.
    アプリのレコードをテーブルに取り込みます。前回から変わったものだけ（更新日時とレコード番号で）、添付ファイルはデータの添付ファイルに、ユーザーはログイン名（メールアドレス）で合わせます。kintone で消えたレコードは、1 日 1 回見つけて印を付けます。
  - The page of the plugin makes an Exment table from the definition of an app (a column for each field, the choices of a select, the required ones required) and a mapping with every field mapped (the schedule off: a dry run first).
    プラグインの画面で、アプリの定義から Exment のテーブル（項目ごとの列・選択肢・必須）と、項目の対応がそろった割り付け（自動の同期はオフ。まず試し実行）を作れます。
  - Not yet: a table in a record (subtable), related records, process management, views and permissions, and the way back (Exment → kintone).
    まだできないこと：レコードの中の表（サブテーブル）、関連レコード、プロセス管理、一覧・権限の写し、Exment から kintone へ送ること。

## Use / 使い方

1. Install the plugin, and turn on "Data sync" in Feature management. / プラグインを入れ、機能の管理で「データ同期」をオンにします。
2. Admin page "Data sync" → "kintone": the subdomain, the app ids and the API tokens (or the login name and the password) → "Connection test". / 管理画面の「データ同期」→「kintone」で、サブドメイン・アプリの ID・API トークン（またはログイン名とパスワード）を入れて「接続テスト」。
3. Either make a table on the page of this plugin, or add a mapping to a table there is in "Data sync". / このプラグインの画面でテーブルを作るか、「データ同期」で今あるテーブルに割り付けを足します。
4. "Dry run" → "Sync now" → turn on the schedule of the mapping. / 「試し実行」→「今すぐ同期」→ 割り付けの自動の同期をオン。

An API token needs "View records" of its app (and the app is given in the app ids). The server must reach the kintone subdomain over https.
API トークンには、そのアプリの「レコード閲覧」の権限が要ります（アプリの ID も入れてください）。サーバーから kintone のサブドメインに https でつながる必要があります。

## Development / 開発

`php kintone/tools/test-kintone-api.php` checks the calls of the kintone REST API with a fake kintone (CI runs it). There is no kintone tenant in the tests: try a kintone developer environment before a release.
`php kintone/tools/test-kintone-api.php` は、偽の kintone で REST API の呼び方を確かめます（CI で動きます）。テストには本物の kintone がないので、公開の前に kintone の開発者用の環境で試してください。
