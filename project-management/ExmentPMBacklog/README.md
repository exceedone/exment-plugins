# Exment Plugin PM Backlog 連携（ExmentPMBacklog）

Backlog のプロジェクトの課題とコメントを、Exment PM のプロジェクトへ決まった間隔で取り込みます。Backlog が正で、片方向です（ISS00113）。

Takes the issues and comments of a Backlog project into an Exment PM project at a set interval. One way, Backlog is the master.

## 要るもの / Requirements

- Exment Plugin PM（ExmentPM）1.7.7 以降（スキーマ 29。「移行元の担当者」を使います）
- 決まった時間に動かすには、サーバーで Exment のスケジュール（`php artisan schedule:run` を毎分）が動いていること。動いていなくても、「今すぐ同期」とコマンドは使えます

## 使い方 / Use

1. プラグインを入れる（プラグインのテーブル `pmbl_connections`・`pmbl_links`・`pmbl_runs` ができます）
2. 「Exment Plugin PM Backlog 連携」を開き、「つながりを追加」
   - Backlog のスペースの URL、API キー（暗号化して保存し、画面には出しません）、プロジェクトキー、取り込み先の Exment のプロジェクト、間隔（初期値 30 分）
3. 「読み直して、足りない状態・種別・カテゴリー・マイルストーンを作る」で割り付け。ユーザーはメールアドレスで合わせ、合わない人は画面で選ぶ
4. 「今すぐ同期」（1 回 100 件まで）で試し、よければ「決まった間隔で自動に取り込む」をオン

コマンド:

```
php artisan exment:batch --name=ExmentPMBacklog                       # 時間の来たつながり
php artisan exment:batch --name=ExmentPMBacklog --param1=<id> --param2=<件数>   # そのつながりを今
```

## 動き / How it works

- 前回から変わった課題だけを取ります（Backlog は日付でしか絞れないので、同じ日のものは更新日時で見分けます）。1 回 300 件まで、残りは次の回に続きます
- 課題・コメントを Exment の中から直接書きます。**通知は出しません**（コメントの @ も知らせません）
- 作成日時・更新日時・登録者は Backlog のものにします
- Exment にいない担当者は空にし、名前を「移行元の担当者」に残します。プロジェクトのメンバーでない担当者も同じです
- 子課題が無効のプロジェクトなど、親課題をつなげないときは親なしで取り込み、記録に残します
- 1 件の失敗はその課題だけ。次の回にもう一度取ります
- 同じつながりは同時に動きません（ロックは 60 分で外れます）。失敗が続くと間隔を広げます（最大 1 日）
- 動いた記録は 200 回分を残し、設定画面に出します

## まだないもの（P2・P3） / Not yet

- 失敗したときの管理者への通知
- 添付ファイル、本文の画像
- Backlog で消した課題・別のプロジェクトに移した課題の印
- 社内用ツール（backlog_sync）の割り付けと対応の取り込み

## 変更 / Changes

- 0.1.0: 最初の版（P1）。設定・割り付け・今すぐ同期・決まった間隔の同期・課題とコメント
