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

Exment Plugin Project Management is one Exment plugin:

| Folder | Plugin | What it does | Download (for your own server) |
|---|---|---|---|
| [`project-management/PmPortal/`](project-management/PmPortal/) | PmPortal — プロジェクト管理 (Exment Project Management) | Projects and tickets, and moving a project to another Exment (**⚙ Settings → データ移行**) | [PmPortal-1.2.0.zip](https://github.com/exceedone/exment-plugins/releases/download/PmPortal-v1.2.0/PmPortal-1.2.0.zip) |

Data migration used to be the separate PmMigrate plugin. Since PmPortal 1.2.0 it is part of PmPortal (**⚙ Settings → データ移行**, system administrators only), so PmMigrate is retired: after updating PmPortal you can delete PmMigrate in **Admin settings → Plugins**. Its last version stays on [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1).

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
2. Under the newest release of the plugin (for example **PmPortal 1.2.0**), click the zip in **Assets** (for example `PmPortal-1.2.0.zip`). Do not unzip it.

Do not use the green **Code → Download ZIP** button: that is the whole repository, and Exment cannot read it.

**Upload**

1. Log in to Exment as an administrator.
2. Open **Admin settings → Plugins**.
3. In **Plugin upload**, choose the zip you downloaded and send it.
4. Follow the plugin's own README (for example the first-time setup of Project Management).

**Update to a newer version**

1. Download the newer zip from Releases and upload it the same way. The plugin is replaced; your data stays.
2. For Project Management (PmPortal): open it and press **⚙ Settings → Update data structure** if it asks.

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

Exment Plugin Project Management は、1 つの Exment プラグインです。

| フォルダ | プラグイン | できること | ダウンロード（自分のサーバー用） |
|---|---|---|---|
| [`project-management/PmPortal/`](project-management/PmPortal/) | PmPortal — プロジェクト管理 (Exment Project Management) | プロジェクトとチケットの管理、プロジェクトを別の Exment に移す（**⚙ 設定 → データ移行**） | [PmPortal-1.2.0.zip](https://github.com/exceedone/exment-plugins/releases/download/PmPortal-v1.2.0/PmPortal-1.2.0.zip) |

データ移行は、前は別のプラグイン PmMigrate でした。PmPortal 1.2.0 からは PmPortal の **⚙ 設定 → データ移行**（システム管理者だけ）に入ったので、PmMigrate は使い終わりです。PmPortal を新しくしたあと、**管理者設定 → プラグイン** で PmMigrate を消してかまいません。PmMigrate の最後の版は [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1) に残っています。

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
2. 入れたいプラグインのいちばん新しい版（例: **PmPortal 1.2.0**）の **Assets** にある zip（例: `PmPortal-1.2.0.zip`）をクリックして保存します。zip は展開しません。

緑の **Code → Download ZIP** ボタンは使わないでください。リポジトリ全体の zip なので、Exment は読めません。

**アップロード**

1. Exment に管理者でログインします。
2. **管理者設定 → プラグイン** を開きます。
3. **プラグインアップロード** で、ダウンロードした zip を選んで送ります。
4. そのあとは、各プラグインの README に従います（例: プロジェクト管理の初期設定）。

**新しい版にする**

1. Releases から新しい版の zip をダウンロードし、同じようにアップロードします。プラグインが置きかわります。データは消えません。
2. プロジェクト管理（PmPortal）の場合: 画面を開き、言われたら **⚙ 設定 → データ構造の更新** を押します。

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
