# Exment Plugin Project Management

Project and ticket management for Exment: a Backlog-like portal with issues, board, Gantt chart, wiki and files, built on Exment's own tables.
Exment のプロジェクト管理・チケット管理のプラグインです。Backlog に似たポータルで、課題・ボード・ガントチャート・Wiki・ファイルを Exment のテーブルの上に作ります。

| Folder | Plugin | Version | Type |
|---|---|---|---|
| [`PmPortal/`](PmPortal/) | プロジェクトポータル (project portal) | 1.0.0 | page, validator, event, batch |
| [`PmMigrate/`](PmMigrate/) | PM データ移行 (move a project to another Exment) | 1.0.1 | page |

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
- **Data move / データ移行** (PmMigrate): export a project with comments, history and attachments, and import it into another Exment
  プロジェクトをコメント・変更履歴・添付ごと書き出し、別の Exment に取り込む

## Install / 入れ方

1. Download `PmPortal-<version>.zip` (and `PmMigrate-<version>.zip` if needed) from **[Releases](https://github.com/exceedone/exment-plugins/releases)**. Do not unzip it.
   **[Releases](https://github.com/exceedone/exment-plugins/releases)** から `PmPortal-<版>.zip`（必要なら `PmMigrate-<版>.zip` も）をダウンロードします。展開はしません。
2. Upload the zip in **Admin settings → Plugins → Plugin upload**. On Exment SaaS, use **Admin → Plugin market**.
   **管理者設定 → プラグイン → プラグインアップロード** で zip を送ります。Exment SaaS では **管理 → プラグインマーケット** から入れます。
3. Open the portal (**Project management** in the menu) as an administrator and run the first-time setup. It creates the tables the portal uses.
   管理者でポータル（メニューの **プロジェクト管理**）を開き、初期設定をします。ポータルが使うテーブルが作られます。
4. After installing a newer version, press **⚙ Settings → Update data structure** if the portal asks for it.
   新しい版を入れたあと、ポータルに言われたら **⚙ 設定 → データ構造の更新** を押します。

## Development / 開発

- Write PHP that runs on PHP 8.2.
  PHP 8.2 で動く書き方にします。
- Raise `version` in `config.json` for each release, and never change `uuid` or `plugin_name`.
  出すたびに `config.json` の `version` を上げます。`uuid` と `plugin_name` は変えません。
- To release, raise `version` in `config.json`, merge to `main`, then push a tag `<Folder>-v<version>` (for example `git tag PmPortal-v1.1.0 && git push origin PmPortal-v1.1.0`). The release workflow builds the zip (top `config.json` last) and publishes it on Releases.
  出すときは、`config.json` の `version` を上げて `main` にマージし、`<フォルダ名>-v<版>` のタグ（例: `git tag PmPortal-v1.1.0 && git push origin PmPortal-v1.1.0`）を push します。release のワークフローが zip を作り（いちばん上の `config.json` を最後に入れる）、Releases に出します。
- When you add tables or columns, raise `PortalSchema::VERSION` and add the step to `PortalUpgrade`.
  テーブルや列を足したら、`PortalSchema::VERSION` を上げ、`PortalUpgrade` に手順を足します。
- UI text is in `PmPortal/resources/lang/ja/pmportal.php`. A key used by the screen's script must also be listed in `resources/views/index.blade.php`.
  画面の文言は `PmPortal/resources/lang/ja/pmportal.php` にあります。画面のスクリプトで使う文言は、`resources/views/index.blade.php` の一覧にも足します。
- After changing a blade file on a server, run `php artisan view:clear`.
  サーバーで blade を変えたら `php artisan view:clear` をします。

## License / ライセンス

[GPL-3.0](../LICENSE)
