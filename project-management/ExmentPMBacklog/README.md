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

- **Unreleased**：Backlog のカスタム属性を、ExmentPM のカスタム属性（ExmentPM のスキーマ 31 から）に入れます。Backlog のプロジェクトのカスタム属性が、同じ名前の項目になり（文字列・文章 → 文字、数値 → 数、日付 → 日付、単一リスト・ラジオ → 1 つ選ぶ、複数リスト・チェックボックス → いくつか選ぶ）、プロジェクトの「カスタム属性」をオンにします。リストに項目が増えたら足します。Exment で消した項目はもどしません。Backlog のものでない項目の値は残します。必須にはしません。これまでの「カスタム属性」の文字（custom_fields）も、今までどおり書きます。
  Backlog's custom fields go into ExmentPM's custom attributes (ExmentPM schema 31 and later): each field of the Backlog project becomes an attribute of the same name and a fitting kind, and the project's 「カスタム属性」 is turned on. New list items are added; an attribute deleted in Exment stays deleted; values of attributes that are not Backlog's are kept; none is made required. The old text (custom_fields) is still written.

## 0.2.0 で足したもの（P2・P3）

- 同期が失敗しはじめたとき、システム管理者に Exment の通知（ベル）で知らせます。直ったときにももう一度知らせます（失敗のたびには知らせません）
- 添付ファイル（20MB まで）を課題の添付に取り込みます。本文・コメントの `![image][ファイル名]` は、その添付を指すように書き換えます
- 1 日 1 回、Backlog の全部の課題番号と見比べます。なくなった課題は
  - Backlog で削除されていれば、印を付けて課題にコメントを残します（Exment からは消しません）
  - 別のプロジェクトに移されていて、そのプロジェクトにもつながりがあれば、そのつながりの次の同期で Exment の課題も移ります。つながりがなければ、印とコメントだけです
- 社内用ツール（backlog_sync）の state.json・mapping.json を設定画面から引き継げます。取り込み済みの課題・コメント・添付は二重に入りません

## 変更 / Changes

- Unreleased / 未リリース: Exment 7.3.0 以降では、つながりを Exment の「データ同期」に移せます（一覧の「データ同期に移す」、1 回だけ・もう移したものはそのまま）。移したつながりは、データ同期が決まった時間・試し実行・記録・失敗のお知らせを受け持ち、課題・コメント・添付の書き方は今までどおりです。取り込んだ課題の対応（pmbl_links）をそのまま使うので、二重には入りません。移したつながりは、このプラグインのバッチでは動かしません。API キーは Exment が暗号化して持ちます。Exment 7.2 では今までどおり動きます。On Exment 7.3.0 or later a connection can be moved to Exment's "Data sync" (once; one moved before is left as it is): the data sync runs it (schedule, dry run, runs, failure notice), the issues, comments and attachments are written as before, and the links of the issues are kept, so nothing is imported twice. The plugin's batch leaves a moved connection alone. On Exment 7.2 nothing changes.
- Unreleased / 未リリース: エラーのメッセージ（実行の記録・ログ・お知らせ・画面）から、API キーなどの秘密の値を取り除くようにしました。Secret values (such as the API key) are taken out of the error messages (the runs, the log, the notices, the screen).
- Unreleased / 未リリース: 動かすつながりがないときも、バッチは成功（終了コード 0）で終わる（前は 1 で、5 分ごとにエラーのログが出ていた）。つながりが失敗したときは 1 のまま。The batch ends with 0 when no connection is due (it was 1, logged as an error every five minutes); 1 when a connection failed.
- 0.2.1: Backlog で詳細を変えたコメントは「詳細: 変更しました」とだけ書く（前と後の全文を写さない。1 件で数 MB になっていた）
- 0.2.0: 失敗の通知、添付、消えた・移した課題、backlog_sync からの引き継ぎ
- 0.1.0: 最初の版（P1）。設定・割り付け・今すぐ同期・決まった間隔の同期・課題とコメント
