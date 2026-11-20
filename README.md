# Exment Official Plugins / Exment 公式プラグイン

**[English](#english)** | **[日本語](#japanese)**

---

<a id="english"></a>

## What this is

Official plugins for [Exment](https://github.com/exceedone/exment), made, tested and supported by Exceed One.

## Plugins

| Folder | Name | What it does | Status |
|---|---|---|---|
| [`project-management/`](project-management/) | Exment Plugin Project Management | Project and ticket management (issues, board, Gantt chart, wiki, files, read receipts, mail notification, data migration) | In use on Exment SaaS |
| [`it-support/`](it-support/) | Exment Plugin IT Support | IT support / help desk (mail intake, no-login progress links, customer portal, SLA, knowledge base, IT assets, alert intake, reports) | Released 1.0.0 — [ExmentITSupport-1.0.0.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentITSupport-v1.0.0/ExmentITSupport-1.0.0.zip) |

Exment Plugin Project Management is one Exment plugin:

| Folder | Plugin | What it does | Download (for your own server) |
|---|---|---|---|
| [`project-management/ExmentPM/`](project-management/ExmentPM/) | Exment Plugin PM（プロジェクト管理） — plugin name `ExmentPM` (was `PmPortal` until 1.2.0 and `ExmentPluginPM` in 1.3.0) | Projects and tickets, and moving a project to another Exment (**⚙ Settings → データ移行**) | [ExmentPM-1.4.0.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentPM-v1.4.0/ExmentPM-1.4.0.zip) |

Since 1.4.0 the plugin's internal name is `ExmentPM` (it was `PmPortal` until 1.2.0 and `ExmentPluginPM` in 1.3.0) and its address is `/admin/plugins/exment_pm` (it was `/admin/plugins/pm_portal`). The screens and your data do not change. **Links and bookmarks to the old address stop working** (Exment does not redirect them). **If you already use PmPortal or ExmentPluginPM, do not upload the new zip as it is** — Exment refuses it. Follow [Moving to ExmentPM](project-management/README.md#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) once; after that, updates are uploaded as usual.

Data migration used to be the separate PmMigrate plugin. Since 1.2.0 it is part of this plugin (**⚙ Settings → データ移行**, system administrators only), so PmMigrate is retired: after updating you can delete PmMigrate in **Admin settings → Plugins**. Its last version stays on [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1).

Details are in the [README of the set](project-management/README.md).

## Requirements

- Exment 7.0 or later (PHP 8.2)

## Install

The plugins are provided in two ways. Choose by where your Exment runs.

| Your Exment | Get the plugin from | How to install |
|---|---|---|
| Exment SaaS (`*.exments.com`) | Exment Plugin Market (https://exments.com) | [A. Exment SaaS](#a-exment-saas) |
| Your own server (on-premises, rental server), or you are a developer | GitHub Releases (https://github.com/exceedone/exment-plugins/releases) | [B. Your own server](#b-your-own-server) |

### A. Exment SaaS

In Exment, open **Admin → Plugin market → Install**.

### B. Your own server

**Download**

1. Open **[Releases](https://github.com/exceedone/exment-plugins/releases)** (right side of this page).
2. Under the newest release of the plugin (for example **ExmentPM 1.4.0**), click the zip in **Assets** (for example `ExmentPM-1.4.0.zip`). Do not unzip it.

Do not use the green **Code → Download ZIP** button: that is the whole repository, and Exment cannot read it.

**Upload**

1. Log in to Exment as an administrator.
2. Open **Admin settings → Plugins**.
3. In **Plugin upload**, choose the zip you downloaded and send it.
4. Follow the plugin's own README (for example the first-time setup of Project Management).

**Update to a newer version**

1. Download the newer zip from Releases and upload it the same way. The plugin is replaced; your data stays.
2. For Project Management (ExmentPM): open it and press **⚙ Settings → Update data structure** if it asks.
3. Coming from PmPortal (1.2.0 or older) or ExmentPluginPM (1.3.0)? The upload is refused with "UUID exists but the plugin name is wrong". Do [Moving to ExmentPM](project-management/README.md#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) first.

### GitHub and the market: what is different

| | GitHub (this repository) | Exment Plugin Market |
|---|---|---|
| What you get | A zip ready to upload (source code, readable) | A zip made for your Exment |
| PHP files | As is (readable) | Encoded with ionCube for your domain; works only there |

- An encoded zip from the market does not run on other servers. To run a plugin on your own server, use the zip from GitHub Releases.
- The same versions are published to both. A new version is released on GitHub first, then uploaded to the market.

## Plugin categories

| | Official | Certified (planned) | Other |
|---|---|---|---|
| Made by | Exceed One | Partners | Anyone |
| Server-side code on Exment SaaS | Allowed | Allowed after review | Not allowed |
| On-premises, rental servers | Allowed | Allowed | Allowed |

## Security

Please report security problems privately through [GitHub security advisories](https://github.com/exceedone/exment-plugins/security/advisories/new), not in public issues.

## License

[GPL-3.0](LICENSE), the same as Exment.

## For developers

Issues and pull requests are welcome.

### Branches

| Branch | Use |
|---|---|
| `main` | The latest release |
| `feature/*` | Work in progress, merged into `main` by pull request |

### Naming rule for official plugins

Every official plugin (a plugin in this repository) puts "Exment" first, so that it never collides with a plugin of a customer or another vendor. Exment does not check that an address (`uri`) is unique: when two plugins have the same one, the first plugin found answers and the other is silently unreachable.

| What | Rule | Example |
|---|---|---|
| Internal name: `plugin_name`, folder, PHP namespace, zip, release tag | `Exment<Name>` (no "Plugin") | `ExmentPM`, `App\Plugins\ExmentPM`, `ExmentPM-1.4.0.zip`, `ExmentPM-v1.4.0` |
| Address: `uri` in `config.json` (always write it) | `exment_<name>` | `exment_pm` → `/admin/plugins/exment_pm` |
| Name in the plugin list: `plugin_view_name` | `Exment Plugin <Name>（日本語名）` | `Exment Plugin PM（プロジェクト管理）` |

- Plugins of customers and other vendors must not use the `Exment` / `exment_` prefix.
- Exment turns `plugin_name` into the namespace and the folder with its own Pascal-case rule, and it must come back unchanged: `ExmentPM` stays `ExmentPM`. When `config.json` has no `uri`, Exment derives it from `plugin_name` (`ExmentPM` would become `exment_p_m`), so `uri` is always written.
- The `uri` must stay unique on an Exment. Besides the `uri`, Exment also matches an address against the Pascal case of every `plugin_name` (`exment_pm` → `ExmentPm`), so no plugin may be named like the Pascal case of another plugin's `uri`.
- Never change `uuid`. Changing `plugin_name` or `uri` of a released plugin needs manual work on every install and breaks stored links, so decide them before the first release.

Folders in this repository and the rule:

| Folder | `plugin_name` | `uri` | Name in the list | Follows the rule |
|---|---|---|---|---|
| `project-management/ExmentPM/` | `ExmentPM` | `exment_pm` | Exment Plugin PM（プロジェクト管理） | Yes (since 1.4.0) |

### CI

GitHub Actions (`.github/workflows/ci.yml`) checks every plugin folder (a folder with `Plugin.php`) on each pull request and on `main`.

**A pull request can be merged into `main` only after all four checks below pass** (`check (8.2)`, `check (8.3)`, `php-cs-fixer`, `phpstan`). Direct pushes and force pushes to `main` are not allowed (only administrators can bypass this in an emergency).

| Job | What it checks |
|---|---|
| `check` | PHP syntax with `php -l` on PHP 8.2 and 8.3, and `config.json` (`plugin_name`, `uuid`, `version`, `plugin_type`; unique `uuid` and `plugin_name`) |
| `php-cs-fixer` | Same coding style as Exment (`.php-cs-fixer.dist.php`) |
| `phpstan` | Static analysis against Exment 7 (`.github/ci/phpstan.neon`). Errors listed in `.github/ci/phpstan-baseline.neon` are known; only new errors fail |

### Run the checks on your PC

```
php .github/ci/check-config.php
php php-cs-fixer.phar fix --dry-run --diff --config=.php-cs-fixer.dist.php
cd .github/ci && composer update --ignore-platform-req='ext-*' && vendor/bin/phpstan analyse -c phpstan.neon
```

- Fix the style automatically: `php php-cs-fixer.phar fix --config=.php-cs-fixer.dist.php`
- If you fixed known errors or must accept new ones, refresh the baseline: `vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline phpstan-baseline.neon`

---

<a id="japanese"></a>

## このリポジトリについて

[Exment](https://github.com/exceedone/exment) の **公式プラグイン** を置くリポジトリです。エクシード・ワンが作り、テストし、サポートします。

## プラグイン

| フォルダ | 名前 | できること | 状態 |
|---|---|---|---|
| [`project-management/`](project-management/) | Exment Plugin Project Management | プロジェクト管理・チケット管理（課題・ボード・ガントチャート・Wiki・ファイル・既読・メール通知・データ移行） | Exment SaaS で利用中 |
| [`it-support/`](it-support/) | Exment Plugin IT Support | ITサポート・ヘルプデスク（メール受付・ログインなしの進み具合の共有・顧客ポータル・SLA・ナレッジ・IT 資産・監視からの起票・レポート） | 1.0.0 — [ExmentITSupport-1.0.0.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentITSupport-v1.0.0/ExmentITSupport-1.0.0.zip) |

Exment Plugin Project Management は、1 つの Exment プラグインです。

| フォルダ | プラグイン | できること | ダウンロード（自分のサーバー用） |
|---|---|---|---|
| [`project-management/ExmentPM/`](project-management/ExmentPM/) | Exment Plugin PM（プロジェクト管理） — プラグイン名 `ExmentPM`（1.2.0 までは `PmPortal`、1.3.0 は `ExmentPluginPM`） | プロジェクトとチケットの管理、プロジェクトを別の Exment に移す（**⚙ 設定 → データ移行**） | [ExmentPM-1.4.0.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentPM-v1.4.0/ExmentPM-1.4.0.zip) |

1.4.0 から、プラグインの内部の名前が `ExmentPM` に（1.2.0 までは `PmPortal`、1.3.0 は `ExmentPluginPM`）、アドレスが `/admin/plugins/exment_pm` に（前は `/admin/plugins/pm_portal`）なりました。画面とデータは変わりません。**古いアドレスへのリンクやブックマークは開けなくなります**（Exment は転送しません）。**すでに PmPortal か ExmentPluginPM を使っている場合は、新しい zip をそのままアップロードしないでください**（Exment が受け付けません）。先に 1 回だけ [ExmentPM への移行](project-management/README.md#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) をします。そのあとは、今までどおりアップロードで新しくできます。

データ移行は、前は別のプラグイン PmMigrate でした。1.2.0 からはこのプラグインの **⚙ 設定 → データ移行**（システム管理者だけ）に入ったので、PmMigrate は使い終わりです。プラグインを新しくしたあと、**管理者設定 → プラグイン** で PmMigrate を消してかまいません。PmMigrate の最後の版は [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1) に残っています。

くわしくは [このセットの README](project-management/README.md) を見てください。

## 動作環境

- Exment 7.0 以降（PHP 8.2）

## 入れ方

プラグインは 2 つの所から入手できます。Exment をどこで動かしているかで選びます。

| 使っている Exment | 入手先 | 入れ方 |
|---|---|---|
| Exment SaaS（`*.exments.com`） | Exment プラグインマーケット（https://exments.com） | [A. Exment SaaS の場合](#a-exment-saas-の場合) |
| 自分のサーバー（オンプレ・レンタルサーバー）、または開発者 | GitHub の Releases（https://github.com/exceedone/exment-plugins/releases） | [B. 自分のサーバーの場合](#b-自分のサーバーの場合) |

### A. Exment SaaS の場合

Exment の **管理 → プラグインマーケット → インストール** から入れます。

### B. 自分のサーバーの場合

**ダウンロード**

1. このページの右側にある **[Releases](https://github.com/exceedone/exment-plugins/releases)** を開きます。
2. 入れたいプラグインのいちばん新しい版（例: **ExmentPM 1.4.0**）の **Assets** にある zip（例: `ExmentPM-1.4.0.zip`）をクリックして保存します。zip は展開しません。

緑の **Code → Download ZIP** ボタンは使わないでください。リポジトリ全体の zip なので、Exment は読めません。

**アップロード**

1. Exment に管理者でログインします。
2. **管理者設定 → プラグイン** を開きます。
3. **プラグインアップロード** で、ダウンロードした zip を選んで送ります。
4. そのあとは、各プラグインの README に従います（例: プロジェクト管理の初期設定）。

**新しい版にする**

1. Releases から新しい版の zip をダウンロードし、同じようにアップロードします。プラグインが置きかわります。データは消えません。
2. プロジェクト管理（ExmentPM）の場合: 画面を開き、言われたら **⚙ 設定 → データ構造の更新** を押します。
3. PmPortal（1.2.0 まで）か ExmentPluginPM（1.3.0）から新しくする場合: アップロードは「UUIDは存在しますが、プラグイン名が正しくありません」と出て止まります。先に [ExmentPM への移行](project-management/README.md#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) をします。

### GitHub とマーケットの違い

| | GitHub（このリポジトリ） | Exment プラグインマーケット |
|---|---|---|
| 入手できるもの | そのままアップロードできる zip（中はソースコード） | 自分の Exment 用の zip |
| PHP のファイル | そのまま（読める） | ionCube で、自分のドメイン専用に暗号化（そのドメインでだけ動く） |

- マーケットの暗号化された zip は、ほかのサーバーでは動きません。自分のサーバーで動かすときは、GitHub の Releases の zip を使ってください。
- どちらも同じ版を出します。新しい版は、まず GitHub に出してから、マーケットに上げます。

## プラグインの区分

| | 公式 | 認定（予定） | そのほか |
|---|---|---|---|
| 作る人 | エクシード・ワン | パートナー | だれでも |
| Exment SaaS でのサーバー側の処理 | 使える | 審査のあと使える | 使えない |
| オンプレ・レンタルサーバー | 使える | 使える | 使える |

## セキュリティの問題

セキュリティの問題は、公開の Issue ではなく [GitHub のセキュリティアドバイザリー](https://github.com/exceedone/exment-plugins/security/advisories/new) から非公開で知らせてください。

## ライセンス

Exment と同じ [GPL-3.0](LICENSE) です。

## 開発する人へ

Issue・プルリクエストを歓迎します。

### ブランチ

| ブランチ | 用途 |
|---|---|
| `main` | 最新の版 |
| `feature/*` | 作業中。プルリクエストで `main` に入れる |

### 公式プラグインの名前の決まり

公式プラグイン（このリポジトリのプラグイン）は、どれも名前の先頭に「Exment」を付けます。お客様や他社のプラグインと重ならないようにするためです。Exment は、アドレス（`uri`）が重なっていないかを調べません。2 つのプラグインが同じ `uri` だと、先に見つかったほうだけが動き、もう片方は何も言われないまま開けなくなります。

| 対象 | 決まり | 例 |
|---|---|---|
| 内部の名前（`plugin_name`・フォルダ・PHP の namespace・zip・リリースのタグ） | `Exment<名前>`（「Plugin」は入れない） | `ExmentPM`、`App\Plugins\ExmentPM`、`ExmentPM-1.4.0.zip`、`ExmentPM-v1.4.0` |
| アドレス（`config.json` の `uri`。必ず書く） | `exment_<名前>` | `exment_pm` → `/admin/plugins/exment_pm` |
| プラグインの一覧に出る名前（`plugin_view_name`） | `Exment Plugin <名前>（日本語名）` | `Exment Plugin PM（プロジェクト管理）` |

- お客様や他社のプラグインでは、`Exment`・`exment_` で始まる名前を使わないでください。
- Exment は `plugin_name` を自分のパスカルケースの決まりで namespace とフォルダ名に直します。直しても同じ形のままになる名前にします（`ExmentPM` は `ExmentPM` のまま）。`config.json` に `uri` がないと、Exment は `plugin_name` からアドレスを作る（`ExmentPM` は `exment_p_m` になる）ので、`uri` は必ず書きます。
- `uri` は、その Exment の中で 1 つだけにします。Exment はアドレスを、`uri` のほかに、すべての `plugin_name` のパスカルケースとも比べます（`exment_pm` → `ExmentPm`）。ほかのプラグインの `uri` をパスカルケースにした名前のプラグインは作りません。
- `uuid` は変えません。出したあとで `plugin_name` や `uri` を変えると、入っている環境すべてで手作業が必要になり、保存されているリンクも切れます。最初に出す前に決めます。

このリポジトリのフォルダと、決まりに合っているか:

| フォルダ | `plugin_name` | `uri` | 一覧に出る名前 | 決まりに合っているか |
|---|---|---|---|---|
| `project-management/ExmentPM/` | `ExmentPM` | `exment_pm` | Exment Plugin PM（プロジェクト管理） | 合っている（1.4.0 から） |

### 自動チェック（CI）

GitHub Actions（`.github/workflows/ci.yml`）が、プルリクエストのたびと `main` で、すべてのプラグイン（`Plugin.php` があるフォルダ）をチェックします。

**下の 4 つのチェック（`check (8.2)`・`check (8.3)`・`php-cs-fixer`・`phpstan`）が全部通らないと、プルリクエストを `main` にマージできません。** `main` への直接の push と force push もできません（緊急のときだけ、管理者は例外にできます）。

| ジョブ | 内容 |
|---|---|
| `check` | PHP の文法（PHP 8.2 と 8.3 の `php -l`）と `config.json`（`plugin_name`・`uuid`・`version`・`plugin_type` があること、`uuid` と `plugin_name` が重ならないこと） |
| `php-cs-fixer` | Exment と同じ書き方（`.php-cs-fixer.dist.php`） |
| `phpstan` | Exment 7 に対する静的解析（`.github/ci/phpstan.neon`）。`.github/ci/phpstan-baseline.neon` にある誤りは既知のものです。新しい誤りだけで失敗します |

### 手元でチェックを動かす

```
php .github/ci/check-config.php
php php-cs-fixer.phar fix --dry-run --diff --config=.php-cs-fixer.dist.php
cd .github/ci && composer update --ignore-platform-req='ext-*' && vendor/bin/phpstan analyse -c phpstan.neon
```

- 書き方を自動で直す: `php php-cs-fixer.phar fix --config=.php-cs-fixer.dist.php`
- 既知の誤りを直したときや、やむを得ず新しい誤りを残すときは、baseline を作り直します: `vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline phpstan-baseline.neon`
