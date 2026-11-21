# Exment Plugin Project Management

Project and ticket management for Exment: a portal with issues, board, Gantt chart, wiki and files, built on Exment's own tables.
Exment のプロジェクト管理・チケット管理のプラグインです。課題・ボード・ガントチャート・Wiki・ファイルをまとめたポータルを、Exment のテーブルの上に作ります。

| Folder | Plugin | Version | Type |
|---|---|---|---|
| [`ExmentPM/`](ExmentPM/) | Exment Plugin PM（プロジェクト管理） | 1.7.1 | page, validator, event, batch |

| Name / 名前 | Value / 値 |
|---|---|
| Name in the plugin list / プラグインの一覧に出る名前 | Exment Plugin PM（プロジェクト管理） |
| Plugin name (`plugin_name`), folder, zip / 内部の名前・フォルダ・zip | `ExmentPM`（1.2.0 までは `PmPortal`、1.3.0 は `ExmentPluginPM`） |
| PHP namespace | `App\Plugins\ExmentPM` |
| Address / アドレス | `/admin/plugins/exment_pm`（1.3.0 までは `/admin/plugins/pm_portal`） |
| Screen title and menu / 画面の見出しとメニュー | プロジェクト管理 |

**Changed in 1.7.1 / 1.7.1 で変わったこと**

- Word, Excel and PowerPoint attachments can be edited on the screen (ISS00416): with Exment Plugin Groupware 0.4.0 or later, the window that **表示** opens has **編集する** (Edit) for those who may edit the issue (or wiki page, or project). Saving replaces the attachment's content; the earlier content stays linked from a comment. Closing the window with unsaved changes asks first.
  Exment Plugin Groupware 0.4.0 以降があると、**表示** で開いた窓に、その課題（Wiki・プロジェクト）を編集できる人には **編集する** が出て、Word・Excel・PowerPoint をその場で編集・保存できます（ISS00416）。保存すると添付の中身が新しくなり、前の中身はコメントのリンクから開けます。保存していない変更があるときは、窓を閉じる前に確かめます。

**Changed in 1.7.0 / 1.7.0 で変わったこと**

- Word, Excel and PowerPoint attachments open on the screen (ISS00416): the files of issues, comments, wiki pages and the project's file list get a **表示** (View) button that shows the file read only in a window on the page, without downloading it. It uses Exment Plugin Groupware 0.3.0 or later (ibisOffice, a browser with WebGPU); without it, the files download as before. The file is read from its usual download address, so nobody sees more than before.
  課題・コメント・Wiki・プロジェクトのファイルの Word・Excel・PowerPoint に **表示** ボタンが付き、ダウンロードせずに画面の中で読み取り専用で開けるようになりました（ISS00416）。Exment Plugin Groupware 0.3.0 以降（ibisOffice、WebGPU のあるブラウザー）を使います。入っていないときは今までどおりダウンロードです。ファイルはいつものダウンロードの URL から読むので、見られる人は変わりません。

**Changed in 1.6.6 / 1.6.6 で変わったこと**

- Faster project data (ISS00257): each change in the history is read once per request, even when both the activity feed and the issue screens show it; the names it mentions (people, statuses, types...) are read with one query per table; and finding where a long text was changed compares bytes instead of characters. On ex1task the project data takes about 340ms instead of 420ms, and the activity feed 14 queries instead of 38.
  プロジェクトのデータが速くなりました（ISS00257）。変更の履歴は、最近の動きと課題の詳細の両方に出るときも 1 回だけ読み解きます。履歴に出る名前（担当者・状態・種別など）は、表ごとに 1 回のクエリでまとめて読みます。長い本文のどこが変わったかを探すのも速くしました。ex1task では、プロジェクトのデータが約 420ms から約 340ms に、最近の動きのクエリが 38 から 14 になりました。

**Changed in 1.6.5 / 1.6.5 で変わったこと**

- Faster after a save (ISS00257): saving an issue or a wiki page, posting a comment that changes the status, uploading a file or deleting an issue no longer reloads the whole page. The portal fetches the project's data once and draws the list and the detail again in place (on ex1task, about 400KB of data instead of a 724KB page and everything it loads after). If the data cannot be fetched, it reloads as before.
  保存したあとが速くなりました（ISS00257）。課題・Wiki の保存、状態を変えるコメント、ファイルのアップロード、課題の削除のあと、ページ全体を読み直さず、プロジェクトのデータだけを取り直して、一覧と詳細をその場で描き直します（ex1task では、724KB のページとそのあとの読み込みの代わりに、約 400KB のデータ 1 回）。データが取れないときは、今までどおり読み直します。
- A short link `/view/KEY-12` for a moved issue only counts KEY when it is a project you can open.
  短いリンク `/view/KEY-12` で移した課題を開くときの KEY は、自分が開けるプロジェクトのキーだけを見ます。
- Note for 1.6.4: an install that already ran データ構造の更新 to schema 28 with 1.6.2 or 1.6.3 does not run the member role group tidy again. If an administrator had added permissions to that group, check it in the role group settings.
  1.6.4 の補足: 1.6.2・1.6.3 でスキーマ 28 まで更新した環境では、メンバーのロールグループの整理はもう一度は流れません。そのグループに管理者が権限を足していた場合は、ロールグループの設定で確かめてください。

**Changed in 1.6.4 / 1.6.4 で変わったこと**

- When a new version is waiting for **データ構造の更新**, administrators see **データ構造の更新があります** at the top of the portal; it opens the settings.
  新しい版で **データ構造の更新** がまだのときは、管理者にだけ、画面の上に **データ構造の更新があります** と出します。押すと設定の画面が開きます。
- The tidy of the member role group (1.6.2) also covers a group an administrator added permissions to. Press **データ構造の更新** once after updating.
  1.6.2 のメンバーのロールグループの権限の整理が、管理者が権限を足したグループにも効くようにしました。更新したあと、**データ構造の更新** を 1 回押します。
- A short link `/view/KEY-12` opens issue 12 only when KEY is a project's key.
  短いリンク `/view/KEY-12` は、KEY がどれかのプロジェクトのキーのときだけ課題 12 を開きます。

**Changed in 1.6.3 / 1.6.3 で変わったこと**

- The project menu (**プロジェクト ▼**) is wider, and a long project name wraps instead of being cut off. Keys get a wider column there and on the dashboard.
  プロジェクトのメニュー（**プロジェクト ▼**）を広げ、長いプロジェクト名は「…」で切らずに折り返すようにしました。キーの欄も、メニューとダッシュボードで広げました。

**Changed in 1.6.2 / 1.6.2 で変わったこと**

- The member role group's permissions are tidied (schema 28). Press **データ構造の更新** once after updating. People are added to a project on its member settings, as before.
  メンバーのロールグループの権限を整理しました（スキーマ 28）。更新したあと、**データ構造の更新** を 1 回押します。プロジェクトに人を足すのは、今までどおりメンバーの設定からです。

**Changed in 1.6.1 / 1.6.1 で変わったこと**

- Short links like Backlog's: `/plugins/exment_pm/view/EXMENT_CLOUD-407` opens an issue and `/plugins/exment_pm/projects/EXMENT_CLOUD` a project (on a self-hosted Exment, `/admin/plugins/exment_pm/...`). An issue also opens by its issue_key (`view/ISS00252`) and, if imported, by its key at the source (`view/MIGRE_TEST_2026-2`). The mails and **リンクをコピー** (in the issue's … menu, and on a comment) give the short form. The old `/15#issue-...` links keep working. No データ構造の更新 needed.
  Backlog のような短いリンクにしました。`/plugins/exment_pm/view/EXMENT_CLOUD-407` で課題が、`/plugins/exment_pm/projects/EXMENT_CLOUD` でプロジェクトが開きます（自分のサーバーの Exment では `/admin/plugins/exment_pm/...`）。課題キー（`view/ISS00252`）や、取り込んだ課題は移行元のキー（`view/MIGRE_TEST_2026-2`）でも開けます。メールと **リンクをコピー**（課題の「…」のメニューとコメント）は短い形になります。今までの `/15#issue-...` のリンクもそのまま開けます。データ構造の更新はいりません。

**Changed in 1.6.0 / 1.6.0 で変わったこと**

- More roles on an issue (schema 27, ISS00252): **副担当者** (sub-assignees), **管理者** (managers), **レビュアー** (reviewers) and **難易度** (difficulty: 易しい・普通・難しい). They are on the edit form under the assignees, and the issue detail shows them with the **起票者** (the author; for an imported issue, the author at the source). Sub-assignees, managers and reviewers get the issue's update notices like the assignees, and have to be on the project. Watchers stay the **お知らせ先** column and the ウォッチ button. Press **データ構造の更新** in the plugin settings once after updating.
  課題の役割を足しました（スキーマ 27、ISS00252）。**副担当者**・**管理者**・**レビュアー**・**難易度**（易しい・普通・難しい）です。編集の画面では担当者の下に並び、課題の詳細には **起票者**（取り込んだ課題は移行元の登録者）と一緒に出ます。副担当者・管理者・レビュアーにも、担当者と同じく更新のお知らせが届きます。プロジェクトに参加している人だけを選べます。ウォッチャーは、今までどおり **お知らせ先** とウォッチのボタンです。更新したあと、プラグインの設定で **データ構造の更新** を 1 回押します。

**Changed in 1.5.0 / 1.5.0 で変わったこと**

- Room for what a Backlog import brings (schema 26). Issues get the source key and URL, who raised and last changed them at the source and when, and the source's custom attributes; the issue detail shows them under 移行元 and カスタム属性. Milestones get an archived flag, wiki pages get tags and their source. All empty for anything not imported. Press **データ構造の更新** in the plugin settings once after updating.
  Backlog から取り込むときの入れ先を足しました（スキーマ 26）。課題に、移行元の課題キー・URL・登録者・登録日時・更新者・更新日時と、カスタム属性が入ります。課題の詳細の「移行元」「カスタム属性」に出ます。マイルストーンに「アーカイブ済み」、Wiki に「タグ」と移行元が入ります。取り込んでいないものは空のままです。更新したあと、プラグインの設定で **データ構造の更新** を 1 回押します。

**Changed in 1.4.1 / 1.4.1 で変わったこと**

- The left menu has a single entry, **プロジェクト管理**, which opens the portal. A new install no longer creates the other entries (プロジェクトポータル, the settings link, マスタ and the template's table entries). The settings and the master lists open from **⚙ 設定** at the top of the portal (administrators only).
  左のメニューは **プロジェクト管理** 1 つだけになりました（開くとポータル）。新しく入れたときに、ほかのメニュー（プロジェクトポータル・設定・マスタ・テンプレートの表のメニュー）を作りません。設定とマスタは、ポータルの上の **⚙ 設定** から開きます（管理者だけ）。
- An install that already has the old entries: press **⚙ 設定 → データ構造の更新** once. It removes only the plugin's own entries, found by their names, and moves プロジェクト管理 to the top of the menu. An entry an administrator added stays (and so does the folder it is in). The screens and the data do not change. The **PM データ移行** entry (PmMigrate) no longer shows once the PmMigrate plugin is deleted.
  古いメニューがある環境では、**⚙ 設定 → データ構造の更新** を 1 回押します。プラグインが作ったメニューだけを名前で見つけて外し、プロジェクト管理をメニューのいちばん上の段に移します。管理者が足したメニュー（と、それが入っているフォルダ）は残ります。画面とデータは変わりません。**PM データ移行** のメニュー（PmMigrate）は、PmMigrate プラグインを消すと出なくなります。

**Changed in 1.4.0 / 1.4.0 で変わったこと**

- The address is now `/admin/plugins/exment_pm` (on Exment SaaS `/plugins/exment_pm`). **Links and bookmarks to the old address `/admin/plugins/pm_portal/...` stop working ("404 not found")**: links in mails already sent, in issue bodies, comments and wiki pages, and browser bookmarks. Exment does not redirect them. The menu entry, the bell notifications and new mails use the new address by themselves. Links stored in issue bodies, comments and wiki pages can be rewritten once with [`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) (optional).
  アドレスが `/admin/plugins/exment_pm`（Exment SaaS では `/plugins/exment_pm`）になりました。**古いアドレス `/admin/plugins/pm_portal/...` へのリンクやブックマークは開けなくなります（「404 not found」）**。送信済みのメールのリンク、課題の本文・コメント・Wiki に書いたリンク、ブラウザのブックマークです。Exment は転送しません。メニュー・ベルのお知らせ・新しく送るメールは、何もしなくても新しいアドレスになります。課題の本文・コメント・Wiki に保存されたリンクは、[`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) で 1 回書き換えられます（やらなくてもかまいません）。
- The internal name is now `ExmentPM` (it was `ExmentPluginPM` in 1.3.0 and `PmPortal` before). An Exment that already has the plugin needs the steps in [Moving to ExmentPM](#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) once.
  内部の名前が `ExmentPM` になりました（1.3.0 は `ExmentPluginPM`、その前は `PmPortal`）。すでにプラグインが入っている Exment では、[ExmentPM への移行](#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) を 1 回だけします。
- The screens say プロジェクト管理 instead of ポータル (for example **プロジェクト管理の設定**, was ポータル設定).
  画面の「ポータル」という言い方を「プロジェクト管理」に直しました（例: **プロジェクト管理の設定**。前は「ポータル設定」）。

Data migration was the separate PmMigrate plugin until 1.2.0; it is now part of this plugin. PmMigrate is retired: after updating you can delete it in **Admin settings → Plugins** (its last version stays on [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1)).
データ移行は、1.2.0 までは別のプラグイン PmMigrate でしたが、今はこのプラグインに入っています。PmMigrate は使い終わりです。プラグインを新しくしたあと、**管理者設定 → プラグイン** で消してかまいません（最後の版は [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1) に残っています）。

## Features / できること

- **Issues / 課題**: list with filters and quick chips, parent/child tree, Excel-style inline edit, bulk update, comments, attachments, Markdown bodies with pasted images
  一覧（絞り込み・クイックフィルター）、親子のツリー表示、一覧での直接編集、一括更新、コメント、添付、Markdown の本文（画像の貼り付け）
- **Read receipts and unread / 既読・未読**: who has read an issue, unread issues tinted in the list, right-click to mark read or unread
  誰が読んだか、まだ見ていない課題の色分け、右クリックで既読・未読
- **Notifications / 通知**: in-app bells, and optional mail on a new issue or comment, or with the "mail this issue" button (sent with Exment's mail settings)
  ベルのお知らせ、課題の登録・コメント・「メールで知らせる」ボタンでのメール（Exment のメール設定で送る）
- **Board, Gantt chart, milestones, burndown / ボード・ガントチャート・マイルストーン・バーンダウン**
- **Wiki and files / Wiki・ファイル共有**
- **Members and roles / メンバーと権限**: project manager, members and viewers, on Exment's role groups
  プロジェクトの管理者・メンバー・閲覧者（Exment の役割グループを使う）
- **Data migration / データ移行** (**⚙ Settings → データ移行**, system administrators only): export projects with comments, history, attachments, stars and review requests to a ZIP, and import it into another Exment (check first, then write)
  プロジェクトをコメント・変更履歴・添付・スター・レビュー依頼ごと ZIP に書き出し、別の Exment に取り込む（先に確認だけして、それから書き込む）。**⚙ 設定 → データ移行** から、システム管理者だけが使えます
  The data migration feature was originally written by TTM (as the PmMigrate plugin).
  データ移行の機能は、もとは TTM さんが（PmMigrate プラグインとして）作りました。

## Install / 入れ方

1. Download `ExmentPM-<version>.zip` from **[Releases](https://github.com/exceedone/exment-plugins/releases)**. Do not unzip it. (PmMigrate is no longer needed.)
   **[Releases](https://github.com/exceedone/exment-plugins/releases)** から `ExmentPM-<版>.zip` をダウンロードします。展開はしません（PmMigrate はもう要りません）。
2. Upload the zip in **Admin settings → Plugins → Plugin upload**. On Exment SaaS (`*.exments.com`) there is nothing to upload: from Exment 7.2.0 the tenant administrator presses **Use (install)** in **Admin settings → Feature management**, which also does the first-time setup, and the operator updates it from then on (data structure update included).
   **管理者設定 → プラグイン → プラグインアップロード** で zip を送ります。Exment SaaS（`*.exments.com`）では、アップロードは要りません。Exment 7.2.0 から、テナントの管理者が **管理者設定 → 機能の管理** で **使う（インストールする）** を押すと、初期セットアップまで済みます。そのあとは運営側が、データ構造の更新も含めて新しくします。
3. Open Project Management (**プロジェクト管理** in the menu) as an administrator and run the first-time setup. It creates the tables the portal uses.
   管理者でプロジェクト管理（メニューの **プロジェクト管理**）を開き、初期設定をします。ポータルが使うテーブルが作られます。
4. After installing a newer version, press **⚙ Settings → Update data structure** if the screen asks for it.
   新しい版を入れたあと、画面に言われたら **⚙ 設定 → データ構造の更新** を押します。

## Moving to ExmentPM (from PmPortal or ExmentPluginPM) / ExmentPM への移行

Only for an Exment that already has **PmPortal** (1.2.0 or older) or **ExmentPluginPM** (1.3.0). A new install does not need this. Both go straight to ExmentPM with the same steps; below, `<OLD>` is the name you have now (`PmPortal` or `ExmentPluginPM`).
すでに **PmPortal**（1.2.0 まで）か **ExmentPluginPM**（1.3.0）が入っている Exment だけの作業です。新しく入れる場合は要りません。どちらからでも、同じ手順で直接 ExmentPM にできます。下の `<OLD>` は、今入っている名前（`PmPortal` か `ExmentPluginPM`）です。

Why: Exment treats an upload as an update only when both `plugin_name` and `uuid` are the same as an installed plugin. The `uuid` is unchanged and the name is new, so uploading the ExmentPM zip on top of the old plugin stops with "UUID exists but the plugin name is wrong" and changes nothing. The steps below rename the installed plugin in place and set its new address. The row in the `plugins` table keeps its id, so the settings, the menu, the role group permissions, the notifications and all data stay as they are.
理由: Exment がアップロードを「更新」として扱うのは、`plugin_name` と `uuid` の両方が、入っているプラグインと同じときだけです。`uuid` は同じで名前だけ新しいので、古いプラグインの上に ExmentPM の zip をアップロードすると「UUIDは存在しますが、プラグイン名が正しくありません」と出て止まります（何も変わりません）。下の手順は、入っているプラグインの名前をその場で変え、新しいアドレスを入れます。`plugins` テーブルの行の id は同じままなので、設定・メニュー・役割グループの権限・通知・データはそのまま残ります。

Do not delete or disable the old plugin and then upload the new zip: that makes a new plugin, and the settings, the menu entry and the permissions are lost.
古いプラグインを消したり無効にしたりしてから新しい zip をアップロードしないでください。別の新しいプラグインになり、設定・メニュー・権限が消えます。

What changes for users: the address becomes `/admin/plugins/exment_pm`. Links and bookmarks to `/admin/plugins/pm_portal/...` stop working; tell your users before you start.
利用者に変わること: アドレスが `/admin/plugins/exment_pm` になります。`/admin/plugins/pm_portal/...` へのリンクやブックマークは開けなくなるので、作業の前に利用者に知らせてください。

### On your own server / 自分のサーバーの場合

You need shell access to the server and to the database. Keep the order: files first, then the database. (If the name is changed while the new folder is missing, the screen answers 404 and Exment makes an empty `ExmentPM` folder.)
サーバーとデータベースに入れる人が作業します。順番を守ります（先にファイル、あとでデータベース）。新しいフォルダがないまま名前を変えると、画面は 404 になり、Exment が空の `ExmentPM` フォルダを作ります。

1. Back up the database and the folder `storage/app/plugins/<OLD>`.
   データベースと `storage/app/plugins/<OLD>` フォルダのバックアップを取ります。
2. Unzip `ExmentPM-<version>.zip` into a new folder `storage/app/plugins/ExmentPM` (so that `storage/app/plugins/ExmentPM/config.json` exists). Give it the same owner as the old folder. Leave the old folder where it is for now.
   `ExmentPM-<版>.zip` を、新しいフォルダ `storage/app/plugins/ExmentPM` に展開します（`storage/app/plugins/ExmentPM/config.json` ができる形）。持ち主は古いフォルダと同じにします。古いフォルダはまだ消しません。

   ```
   cd /path/to/exment/storage/app/plugins
   mkdir ExmentPM && unzip -q /path/to/ExmentPM-1.4.0.zip -d ExmentPM
   chown -R --reference=<OLD> ExmentPM
   ```

   If the plugins are kept on S3 or another remote disk (`EXMENT_DRIVER_PLUGIN`), put the same files under `ExmentPM/` there too.
   プラグインを S3 などに置いている場合（`EXMENT_DRIVER_PLUGIN`）は、そこにも同じファイルを `ExmentPM/` として置きます。
3. Rename the plugin and set the new address in the database. Connect with `utf8mb4` (for example `mysql --default-character-set=utf8mb4`), or the Japanese name is stored broken. The statement must change exactly 1 row. Note the old `plugin_name` and `version` first; you need them to go back.
   データベースで、プラグインの名前を変え、新しいアドレスを入れます。`utf8mb4` でつなぎます（例: `mysql --default-character-set=utf8mb4`）。そうしないと日本語の名前が文字化けして入ります。変わる行は 1 行だけです。元に戻すときに使うので、先に今の `plugin_name` と `version` を控えます。

   ```sql
   SELECT id, plugin_name, version, JSON_EXTRACT(options, '$.uri') AS uri
     FROM plugins WHERE uuid = '00da3e93-1a24-4f44-b993-d61367e43649';

   UPDATE plugins
      SET plugin_name = 'ExmentPM',
          plugin_view_name = 'Exment Plugin PM（プロジェクト管理）',
          version = '1.4.0',
          options = JSON_SET(COALESCE(options, '{}'), '$.uri', 'exment_pm'),
          updated_at = NOW()
    WHERE uuid = '00da3e93-1a24-4f44-b993-d61367e43649'
      AND plugin_name IN ('PmPortal', 'ExmentPluginPM');
   ```

   The address is the `uri` key in the `options` column. Uploading a zip also sets it from `config.json`, but the upload is refused while the name differs, so it is set here.
   アドレスは `options` 列の `uri` です。zip のアップロードでも `config.json` の値が入りますが、名前が違う間はアップロードが止まるので、ここで入れます。
4. Clear the caches (run as the user PHP runs as). If you use `php artisan route:cache`, build it again.
   キャッシュを消します（PHP を動かしているユーザーで実行します）。`php artisan route:cache` を使っている場合は、作り直します。

   ```
   php artisan cache:clear
   php artisan view:clear
   php artisan route:clear
   ```
5. Open Project Management from the menu (`/admin/plugins/exment_pm`) and check your projects. Press **⚙ Settings → Update data structure** if it asks. **Admin settings → Plugins** now shows `ExmentPM` with the URI `exment_pm`.
   メニューからプロジェクト管理（`/admin/plugins/exment_pm`）を開き、プロジェクトが出ることを確かめます。言われたら **⚙ 設定 → データ構造の更新** を押します。**管理者設定 → プラグイン** には `ExmentPM`、URI は `exment_pm` と出ます。
6. Delete the old folder `storage/app/plugins/<OLD>` (and `<OLD>/` on the remote disk).
   古いフォルダ `storage/app/plugins/<OLD>` を消します（S3 などの `<OLD>/` も）。
7. If a cron job runs the batch by name, change `--name=<OLD>` to `--name=ExmentPM` (or use `--uuid=00da3e93-1a24-4f44-b993-d61367e43649`, which never changes).
   cron でバッチを名前で呼んでいる場合は、`--name=<OLD>` を `--name=ExmentPM` に変えます（`--uuid=00da3e93-1a24-4f44-b993-d61367e43649` なら変わりません）。
8. Optional: rewrite the old links stored in issue bodies, comments and wiki pages. See [Old links in stored text](#old-links-in-stored-text-optional--保存されている古いリンクの書き換えやらなくてもよい).
   やらなくてもよい作業: 課題の本文・コメント・Wiki に保存されている古いリンクを書き換えます。[保存されている古いリンクの書き換え](#old-links-in-stored-text-optional--保存されている古いリンクの書き換えやらなくてもよい) を見てください。

From now on, update as usual: upload the newer `ExmentPM-<version>.zip` in **Admin settings → Plugins**.
これからは今までどおり、新しい `ExmentPM-<版>.zip` を **管理者設定 → プラグイン** でアップロードして新しくします。

To go back: run the statement the other way round with the values you noted (`plugin_name = '<OLD>'`, the old `version`, `'$.uri', 'pm_portal'`; for PmPortal also `plugin_view_name = 'プロジェクト管理'`) `WHERE uuid = '...' AND plugin_name = 'ExmentPM'`, clear the caches, and keep using the old folder. This is why step 6 comes last.
元に戻すとき: 控えた値で同じ SQL を逆向きに実行し（`plugin_name = '<OLD>'`、`version` は前の版、`'$.uri', 'pm_portal'`。PmPortal のときは `plugin_view_name = 'プロジェクト管理'` も。条件は `WHERE uuid = '...' AND plugin_name = 'ExmentPM'`）、キャッシュを消して、古いフォルダを使い続けます。手順 6 を最後にしているのはこのためです。

### On Exment SaaS / Exment SaaS の場合

You do not need to do anything. Exceedone does the same change for each tenant (the files go to the tenant's plugin folder on S3 as well as to the server). The address changes for you too: `/plugins/exment_pm` instead of `/plugins/pm_portal`.
利用者の作業はありません。エクシード・ワンがテナントごとに同じ変更をします（ファイルはサーバーに加えて、テナントの S3 のプラグイン置き場にも置きます）。アドレスは同じように変わります（`/plugins/pm_portal` が `/plugins/exment_pm` になります）。

### Old links in stored text (optional) / 保存されている古いリンクの書き換え（やらなくてもよい）

Links to `/admin/plugins/pm_portal/...` that people wrote into issue bodies, comments and wiki pages are plain text in the database, so they keep the old address and end in 404. [`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) rewrites them to `/admin/plugins/exment_pm/...`. Mails already sent and bookmarks cannot be fixed.
課題の本文・コメント・Wiki に人が書いた `/admin/plugins/pm_portal/...` へのリンクは、データベースの中のただの文字なので、古いアドレスのまま残り、開くと 404 になります。[`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) で `/admin/plugins/exment_pm/...` に書き換えられます。送信済みのメールとブックマークは直せません。

The script is plain PHP (PDO); it does not start Exment. Take a database backup first. `--base` is the address of the Exment admin (the part before `/plugins/...`; no `/admin` on Exment SaaS). Only links of that Exment are rewritten; links to another Exment are left alone.
このスクリプトはふつうの PHP（PDO）で、Exment は起動しません。先にデータベースのバックアップを取ります。`--base` は Exment の管理画面のアドレス（`/plugins/...` の前の部分。Exment SaaS では `/admin` は付きません）です。その Exment へのリンクだけを書き換え、ほかの Exment へのリンクは変えません。

```
export DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...     # DB_PORT, DB_SSL_CA if needed

# 1. count only, changes nothing / 数えるだけ（何も変えない）
php tools/rewrite-old-links.php --base=https://example.com/admin

# 2. rewrite; the old texts are saved to the backup file / 書き換える（前の文章はバックアップのファイルに残す）
php tools/rewrite-old-links.php --base=https://example.com/admin --apply --backup=old-links-backup.json

# 3. undo / 元に戻す
php tools/rewrite-old-links.php --rollback=old-links-backup.json
```

It looks in the issue table, the wiki table and Exment's `comment` table (`--tables=a,b` to choose), and with `--bells` also in the text of bell notifications. It changes only the texts that contain an old link; `updated_at` and the edit history stay as they are.
見る場所は、課題のテーブル・Wiki のテーブル・Exment の `comment` テーブルです（`--tables=a,b` で選べます）。`--bells` を付けると、ベルのお知らせの文章も見ます。古いリンクがある文章だけを変え、`updated_at` と変更履歴は変えません。

## Development / 開発

- Write PHP that runs on PHP 8.2.
  PHP 8.2 で動く書き方にします。
- Raise `version` in `config.json` for each release, and never change `uuid`, `plugin_name` or `uri`. (`plugin_name` was changed in 1.3.0 and 1.4.0 and `uri` in 1.4.0; every install needed the manual steps above, and the old links broke.)
  出すたびに `config.json` の `version` を上げます。`uuid`・`plugin_name`・`uri` は変えません（`plugin_name` は 1.3.0 と 1.4.0 で、`uri` は 1.4.0 で変えましたが、入っている環境すべてで上の手作業が必要になり、古いリンクも切れました）。
- Naming rule for the official plugins: `plugin_name`, the folder, the namespace and the zip are `Exment<Name>` (`ExmentPM`), the `uri` is `exment_<name>` (`exment_pm`, always written in `config.json`), and the name in the plugin list is `Exment Plugin <Name>（日本語名）`. The full rule is in the [top-level README](../README.md#naming-rule-for-official-plugins).
  公式プラグインの名前の決まり: `plugin_name`・フォルダ・namespace・zip は `Exment<名前>`（`ExmentPM`）、`uri` は `exment_<名前>`（`exment_pm`。`config.json` に必ず書く）、一覧に出る名前は `Exment Plugin <名前>（日本語名）` です。くわしくは [いちばん上の README](../README.md#公式プラグインの名前の決まり) にあります。
- To release, raise `version` in `config.json`, merge to `main`, then push a tag `<Folder>-v<version>` (for example `git tag ExmentPM-v1.4.0 && git push origin ExmentPM-v1.4.0`). The release workflow builds the zip (top `config.json` last) and publishes it on Releases.
  出すときは、`config.json` の `version` を上げて `main` にマージし、`<フォルダ名>-v<版>` のタグ（例: `git tag ExmentPM-v1.4.0 && git push origin ExmentPM-v1.4.0`）を push します。release のワークフローが zip を作り（いちばん上の `config.json` を最後に入れる）、Releases に出します。
- After a release, update the download links (version numbers) in the top-level `README.md`.
  出したあとは、いちばん上の `README.md` のダウンロードのリンク（版の番号）を直します。
- When you add tables or columns, raise `PortalSchema::VERSION` and add the step to `PortalUpgrade`.
  テーブルや列を足したら、`PortalSchema::VERSION` を上げ、`PortalUpgrade` に手順を足します。
- UI text is in `ExmentPM/resources/lang/ja/pmportal.php`. A key used by the screen's script must also be listed in `resources/views/index.blade.php`.
  画面の文言は `ExmentPM/resources/lang/ja/pmportal.php` にあります。画面のスクリプトで使う文言は、`resources/views/index.blade.php` の一覧にも足します。
- After changing a blade file on a server, run `php artisan view:clear`.
  サーバーで blade を変えたら `php artisan view:clear` をします。

## License / ライセンス

[GPL-3.0](../LICENSE)
