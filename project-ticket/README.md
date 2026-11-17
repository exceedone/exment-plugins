# Exment ProjectTicket

Project and ticket management for Exment (Backlog-like portal, issues, board, Gantt chart, wiki, files).
Exment のプロジェクト管理・チケット管理のプラグインです（Backlog に似たポータル・課題・ボード・ガントチャート・Wiki・ファイル）。

| Folder | Plugin | Version | Type |
|---|---|---|---|
| `PmPortal/` | プロジェクトポータル | 1.0.0 | page, validator, event, batch |
| `PmMigrate/` | データの移行（エクスポート・インポート） | 1.0.1 | |

## Where this source comes from / このソースの出どころ

- The plain source uploaded to the plugin market (https://exments.com) on 2026-11-09 (PmPortal) and 2026-11-10 (PmMigrate). It is identical to what runs on ex1task.exments.com.
  プラグインマーケット（https://exments.com）にアップロードされた平文のソース（PmPortal は 2026-11-09、PmMigrate は 2026-11-10）。ex1task.exments.com で動いているものと同じです。
- The market encodes the PHP files with ionCube for each tenant, so the files on a tenant cannot be edited. Change the source here, then upload a new version to the market.
  マーケットはテナントごとに PHP を ionCube で暗号化して配るので、テナントのファイルは直せません。ここでソースを直し、新しい版をマーケットにアップロードします。

## Changes not yet in the market / マーケットにまだ出していない変更

Applied directly to ex1task (tenant11) on 2026-11-18 (view files only). Release them as the next version.
2026-11-18 に ex1task（tenant11）へ直接入れた変更です（画面のファイルだけ）。次の版で出します。

- ISS00257: drop unused background warm-ups (hidden edit-form iframes, board/gantt pre-embeds) / 使われない裏の先読みをやめた
- ISS00287: UI polish (design tokens, list badges, sticky header, detail summary, back button, header order) / デザインの改善
