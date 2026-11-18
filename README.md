# Exment Official Plugins / Exment 公式プラグイン

Official plugins for [Exment](https://github.com/exceedone/exment), made, tested and supported by Exceed One.

[Exment](https://github.com/exceedone/exment) の **公式プラグイン** を置くリポジトリです。エクシード・ワンが作り、テストし、サポートします。

## Plugins / プラグイン

| Folder | Name | Description | Status |
|---|---|---|---|
| [`project-management/`](project-management/) | Exment Plugin Project Management | Project and ticket management (issues, board, Gantt chart, wiki, files, read receipts, mail notification) / プロジェクト管理・チケット管理（課題・ボード・ガントチャート・Wiki・ファイル・既読・メール通知） | In use on Exment SaaS / Exment SaaS で利用中 |

## Requirements / 動作環境

- Exment 7.0 or later (PHP 8.2) / Exment 7.0 以降（PHP 8.2）

## Where to get / 入手先

The plugins are provided in two ways. / プラグインは 2 つの所から入手できます。

| | GitHub (this repository) / GitHub（このリポジトリ） | Exment Plugin Market / Exment プラグインマーケット |
|---|---|---|
| URL | https://github.com/exceedone/exment-plugins | https://exments.com |
| What you get / 入手できるもの | Source code / ソースコード | A zip made for your Exment / 自分の Exment 用の zip |
| PHP files / PHP のファイル | As is (readable) / そのまま（読める） | Encoded with ionCube for your domain; works only there / ionCube で、自分のドメイン専用に暗号化（そのドメインでだけ動く） |
| For / 向いている人 | On-premises and rental servers, developers / オンプレ・レンタルサーバー・開発者 | Exment SaaS (`*.exments.com`) / Exment SaaS |
| How to install / 入れ方 | Below / 下の「入れ方」 | In Exment: **Admin → Plugin market → Install** / Exment の **管理 → プラグインマーケット → インストール** |

- The same versions are published to both. A new version is released on GitHub first, then uploaded to the market.
  どちらも同じ版を出します。新しい版は、まず GitHub に出してから、マーケットに上げます。
- An encoded zip from the market does not run on other servers. To run a plugin on your own server, use the source from GitHub.
  マーケットの暗号化された zip は、ほかのサーバーでは動きません。自分のサーバーで動かすときは、GitHub のソースを使ってください。

## Install / 入れ方

1. Zip the contents of a plugin folder, with `config.json` at the top of the zip (for example `PmPortal/`).
   プラグインのフォルダ（例: `PmPortal/`）の中身を zip にします。`config.json` が zip のいちばん上に来るようにします。
2. In Exment, open **Admin → Plugins → Upload** and choose the zip.
   Exment の **管理 → プラグイン → アップロード** で zip を選びます。
3. Follow the plugin's own README (for example the first-time setup of the project portal).
   そのあとは、各プラグインの README に従います（例: プロジェクトポータルの初期設定）。

On Exment SaaS (`*.exments.com`), install the plugins from **Admin → Plugin market** instead.
Exment SaaS（`*.exments.com`）では、**管理 → プラグインマーケット** から入れます。

## Branches / ブランチ

| Branch | Use / 用途 |
|---|---|
| `main` | The latest release / 最新の版 |
| `feature/*` | Work in progress, merged into `main` by pull request / 作業中。プルリクエストで `main` に入れる |

## Plugin categories / プラグインの区分

| | Official / 公式 | Certified / 認定 (planned / 予定) | Other / そのほか |
|---|---|---|---|
| Made by / 作る人 | Exceed One | Partners / パートナー | Anyone / だれでも |
| Server-side code on Exment SaaS / SaaS でのサーバー側の処理 | Allowed / 使える | Allowed after review / 審査のあと使える | Not allowed / 使えない |
| On-premises, rental servers / オンプレ・レンタルサーバー | Allowed / 使える | Allowed / 使える | Allowed / 使える |

## Contributing / 貢献

Issues and pull requests are welcome. Please report security problems privately through [GitHub security advisories](https://github.com/exceedone/exment-plugins/security/advisories/new), not in public issues.

Issue・プルリクエストを歓迎します。セキュリティの問題は、公開の Issue ではなく [GitHub のセキュリティアドバイザリー](https://github.com/exceedone/exment-plugins/security/advisories/new) から非公開で知らせてください。

## CI / 自動チェック

GitHub Actions (`.github/workflows/ci.yml`) checks every plugin folder (a folder with `Plugin.php`) on each pull request and on `main`.
PR を出すと、GitHub Actions がすべてのプラグイン（`Plugin.php` があるフォルダ）をチェックします。

| Job | What / 内容 |
|---|---|
| `check` | PHP syntax with `php -l` on PHP 8.2 and 8.3, and `config.json` (`plugin_name`, `uuid`, `version`, `plugin_type`; unique `uuid` and `plugin_name`) / PHP の文法と `config.json` |
| `php-cs-fixer` | Same coding style as Exment (`.php-cs-fixer.dist.php`) / Exment と同じ書き方 |
| `phpstan` | Static analysis against Exment 7 (`.github/ci/phpstan.neon`). Errors listed in `.github/ci/phpstan-baseline.neon` are known; only new errors fail / Exment 7 に対する静的解析。baseline にある既知のもの以外の、新しい誤りだけで失敗します |

Run on your PC / 手元で動かす:

```
php .github/ci/check-config.php
php php-cs-fixer.phar fix --dry-run --diff --config=.php-cs-fixer.dist.php
cd .github/ci && composer update --ignore-platform-req='ext-*' && vendor/bin/phpstan analyse -c phpstan.neon
```

Fix the style automatically: `php php-cs-fixer.phar fix --config=.php-cs-fixer.dist.php`. If you fixed known errors or must accept new ones, refresh the baseline: `vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline phpstan-baseline.neon`.
書き方は `fix` で自動で直せます。既知の誤りを直したときや、やむを得ず残すときは、baseline を作り直してください。

## License / ライセンス

[GPL-3.0](LICENSE), the same as Exment. / Exment と同じ GPL-3.0 です。
