# Exment Plugin Project Management

Project and ticket management for Exment: a Backlog-like portal with issues, board, Gantt chart, wiki and files, built on Exment's own tables.
Exment のプロジェクト管理・チケット管理のプラグインです。Backlog に似たポータルで、課題・ボード・ガントチャート・Wiki・ファイルを Exment のテーブルの上に作ります。

| Folder | Plugin | Version | Type |
|---|---|---|---|
| [`ExmentPluginPM/`](ExmentPluginPM/) | Exment Plugin PM（プロジェクト管理） | 1.3.0 | page, validator, event, batch |

| Name / 名前 | Value / 値 |
|---|---|
| Name in the plugin list / プラグインの一覧に出る名前 | Exment Plugin PM（プロジェクト管理） |
| Plugin name (`plugin_name`), folder, zip / 内部の名前・フォルダ・zip | `ExmentPluginPM`（1.2.0 までは `PmPortal`） |
| PHP namespace | `App\Plugins\ExmentPluginPM` |
| Address / アドレス | `/admin/plugins/pm_portal`（unchanged / 変えていません） |
| Screen title and menu / 画面の見出しとメニュー | プロジェクト管理（unchanged / 変えていません） |

Until 1.2.0 the plugin was called `PmPortal`. If you already use PmPortal, see [Moving from PmPortal to ExmentPluginPM](#moving-from-pmportal-to-exmentpluginpm--pmportal-から-exmentpluginpm-への移行) below.
1.2.0 までは `PmPortal` という名前でした。すでに PmPortal を使っている場合は、下の [PmPortal から ExmentPluginPM への移行](#moving-from-pmportal-to-exmentpluginpm--pmportal-から-exmentpluginpm-への移行) を見てください。

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

1. Download `ExmentPluginPM-<version>.zip` from **[Releases](https://github.com/exceedone/exment-plugins/releases)**. Do not unzip it. (PmMigrate is no longer needed.)
   **[Releases](https://github.com/exceedone/exment-plugins/releases)** から `ExmentPluginPM-<版>.zip` をダウンロードします。展開はしません（PmMigrate はもう要りません）。
2. Upload the zip in **Admin settings → Plugins → Plugin upload**. On Exment SaaS, use **Admin → Plugin market**.
   **管理者設定 → プラグイン → プラグインアップロード** で zip を送ります。Exment SaaS では **管理 → プラグインマーケット** から入れます。
3. Open Project Management (**プロジェクト管理** in the menu) as an administrator and run the first-time setup. It creates the tables the portal uses.
   管理者でプロジェクト管理（メニューの **プロジェクト管理**）を開き、初期設定をします。ポータルが使うテーブルが作られます。
4. After installing a newer version, press **⚙ Settings → Update data structure** if the screen asks for it.
   新しい版を入れたあと、画面に言われたら **⚙ 設定 → データ構造の更新** を押します。

## Moving from PmPortal to ExmentPluginPM / PmPortal から ExmentPluginPM への移行

Only for an Exment that already has **PmPortal** (1.2.0 or older). A new install does not need this.
すでに **PmPortal**（1.2.0 まで）が入っている Exment だけの作業です。新しく入れる場合は要りません。

Why: Exment treats an upload as an update only when both `plugin_name` and `uuid` are the same as an installed plugin. The `uuid` is unchanged and the name is new, so uploading the ExmentPluginPM zip on top of PmPortal stops with "UUID exists but the plugin name is wrong" and changes nothing. The steps below rename the installed plugin in place. The row in the `plugins` table keeps its id, so the settings, the menu, the role group permissions, the notifications and all data stay as they are.
理由: Exment がアップロードを「更新」として扱うのは、`plugin_name` と `uuid` の両方が、入っているプラグインと同じときだけです。`uuid` は同じで名前だけ新しいので、PmPortal の上に ExmentPluginPM の zip をアップロードすると「UUIDは存在しますが、プラグイン名が正しくありません」と出て止まります（何も変わりません）。下の手順は、入っているプラグインの名前だけをその場で変えます。`plugins` テーブルの行の id は同じままなので、設定・メニュー・役割グループの権限・通知・データはそのまま残ります。

Do not delete or disable PmPortal and then upload the new zip: that makes a new plugin, and the settings, the menu entry and the permissions are lost.
PmPortal を消したり無効にしたりしてから新しい zip をアップロードしないでください。別の新しいプラグインになり、設定・メニュー・権限が消えます。

### On your own server / 自分のサーバーの場合

You need shell access to the server and to the database. Keep the order: files first, then the database. (If the name is changed while the new folder is missing, the portal answers 404 and Exment makes an empty `ExmentPluginPM` folder.)
サーバーとデータベースに入れる人が作業します。順番を守ります（先にファイル、あとでデータベース）。新しいフォルダがないまま名前を変えると、ポータルは 404 になり、Exment が空の `ExmentPluginPM` フォルダを作ります。

1. Back up the database and the folder `storage/app/plugins/PmPortal`.
   データベースと `storage/app/plugins/PmPortal` フォルダのバックアップを取ります。
2. Unzip `ExmentPluginPM-<version>.zip` into a new folder `storage/app/plugins/ExmentPluginPM` (so that `storage/app/plugins/ExmentPluginPM/config.json` exists). Give it the same owner as the `PmPortal` folder. Leave `PmPortal` where it is for now.
   `ExmentPluginPM-<版>.zip` を、新しいフォルダ `storage/app/plugins/ExmentPluginPM` に展開します（`storage/app/plugins/ExmentPluginPM/config.json` ができる形）。持ち主は `PmPortal` フォルダと同じにします。`PmPortal` フォルダはまだ消しません。

   ```
   cd /path/to/exment/storage/app/plugins
   mkdir ExmentPluginPM && unzip -q /path/to/ExmentPluginPM-1.3.0.zip -d ExmentPluginPM
   chown -R --reference=PmPortal ExmentPluginPM
   ```

   If the plugins are kept on S3 or another remote disk (`EXMENT_DRIVER_PLUGIN`), put the same files under `ExmentPluginPM/` there too.
   プラグインを S3 などに置いている場合（`EXMENT_DRIVER_PLUGIN`）は、そこにも同じファイルを `ExmentPluginPM/` として置きます。
3. Rename the plugin in the database. Connect with `utf8mb4` (for example `mysql --default-character-set=utf8mb4`), or the Japanese name is stored broken. The statement must change exactly 1 row.
   データベースでプラグインの名前を変えます。`utf8mb4` でつなぎます（例: `mysql --default-character-set=utf8mb4`）。そうしないと日本語の名前が文字化けして入ります。変わる行は 1 行だけです。

   ```sql
   UPDATE plugins
      SET plugin_name = 'ExmentPluginPM',
          plugin_view_name = 'Exment Plugin PM（プロジェクト管理）',
          version = '1.3.0',
          updated_at = NOW()
    WHERE uuid = '00da3e93-1a24-4f44-b993-d61367e43649'
      AND plugin_name = 'PmPortal';
   ```
4. Clear the caches (run as the user PHP runs as).
   キャッシュを消します（PHP を動かしているユーザーで実行します）。

   ```
   php artisan cache:clear
   php artisan view:clear
   ```
5. Open Project Management (`/admin/plugins/pm_portal`, the address is the same) and check your projects. Press **⚙ Settings → Update data structure** if it asks. **Admin settings → Plugins** now shows `ExmentPluginPM`.
   プロジェクト管理（`/admin/plugins/pm_portal`、アドレスは同じ）を開き、プロジェクトが出ることを確かめます。言われたら **⚙ 設定 → データ構造の更新** を押します。**管理者設定 → プラグイン** には `ExmentPluginPM` と出ます。
6. Delete the old folder `storage/app/plugins/PmPortal` (and `PmPortal/` on the remote disk).
   古いフォルダ `storage/app/plugins/PmPortal` を消します（S3 などの `PmPortal/` も）。
7. If a cron job runs the batch by name, change `--name=PmPortal` to `--name=ExmentPluginPM` (or use `--uuid=00da3e93-1a24-4f44-b993-d61367e43649`, which never changes).
   cron でバッチを名前で呼んでいる場合は、`--name=PmPortal` を `--name=ExmentPluginPM` に変えます（`--uuid=00da3e93-1a24-4f44-b993-d61367e43649` なら変わりません）。

From now on, update as usual: upload the newer `ExmentPluginPM-<version>.zip` in **Admin settings → Plugins**.
これからは今までどおり、新しい `ExmentPluginPM-<版>.zip` を **管理者設定 → プラグイン** でアップロードして新しくします。

To go back: run the same statement the other way round (`plugin_name = 'PmPortal'`, `plugin_view_name = 'プロジェクト管理'`, the old `version`), clear the caches, and keep using the `PmPortal` folder. This is why step 6 comes last.
元に戻すとき: 同じ SQL を逆向きに実行し（`plugin_name = 'PmPortal'`、`plugin_view_name = 'プロジェクト管理'`、`version` は前の版）、キャッシュを消して、`PmPortal` フォルダを使い続けます。手順 6 を最後にしているのはこのためです。

### On Exment SaaS / Exment SaaS の場合

You do not need to do anything. Exceedone does the same rename for each tenant (the files go to the tenant's plugin folder on S3 as well as to the server).
利用者の作業はありません。エクシード・ワンがテナントごとに同じ名前の変更をします（ファイルはサーバーに加えて、テナントの S3 のプラグイン置き場にも置きます）。

## Development / 開発

- Write PHP that runs on PHP 8.2.
  PHP 8.2 で動く書き方にします。
- Raise `version` in `config.json` for each release, and never change `uuid`, `plugin_name` or `uri`. (`plugin_name` was changed once, in 1.3.0, and every install needed the manual steps above.)
  出すたびに `config.json` の `version` を上げます。`uuid`・`plugin_name`・`uri` は変えません（`plugin_name` は 1.3.0 で 1 回だけ変えましたが、入っている環境すべてで上の手作業が必要になりました）。
- Naming rule for the official plugins: the name in the plugin list is `Exment Plugin ○○（日本語名）`; `plugin_name`, the folder and the zip are `ExmentPlugin○○`, so that they do not collide with other vendors' plugins. Exment builds the namespace as `App\Plugins\<plugin_name>`. When `config.json` has no `uri`, Exment makes the address from the snake case of `plugin_name` (`ExmentPluginPM` would become `exment_plugin_p_m`), so always write `uri` in `config.json`.
  公式プラグインの名前の決まり: 一覧に出る名前は `Exment Plugin ○○（日本語名）`、`plugin_name`・フォルダ・zip は `ExmentPlugin○○` にします（他社のプラグインと重ならないように）。Exment は namespace を `App\Plugins\<plugin_name>` にします。`config.json` に `uri` がないと、アドレスを `plugin_name` のスネークケースから作る（`ExmentPluginPM` は `exment_plugin_p_m` になる）ので、`config.json` には必ず `uri` を書きます。
- To release, raise `version` in `config.json`, merge to `main`, then push a tag `<Folder>-v<version>` (for example `git tag ExmentPluginPM-v1.3.0 && git push origin ExmentPluginPM-v1.3.0`). The release workflow builds the zip (top `config.json` last) and publishes it on Releases.
  出すときは、`config.json` の `version` を上げて `main` にマージし、`<フォルダ名>-v<版>` のタグ（例: `git tag ExmentPluginPM-v1.3.0 && git push origin ExmentPluginPM-v1.3.0`）を push します。release のワークフローが zip を作り（いちばん上の `config.json` を最後に入れる）、Releases に出します。
- After a release, update the download links (version numbers) in the top-level `README.md`.
  出したあとは、いちばん上の `README.md` のダウンロードのリンク（版の番号）を直します。
- When you add tables or columns, raise `PortalSchema::VERSION` and add the step to `PortalUpgrade`.
  テーブルや列を足したら、`PortalSchema::VERSION` を上げ、`PortalUpgrade` に手順を足します。
- UI text is in `ExmentPluginPM/resources/lang/ja/pmportal.php`. A key used by the screen's script must also be listed in `resources/views/index.blade.php`.
  画面の文言は `ExmentPluginPM/resources/lang/ja/pmportal.php` にあります。画面のスクリプトで使う文言は、`resources/views/index.blade.php` の一覧にも足します。
- After changing a blade file on a server, run `php artisan view:clear`.
  サーバーで blade を変えたら `php artisan view:clear` をします。

## License / ライセンス

[GPL-3.0](../LICENSE)
