# Exment Plugin IT Support（ITサポート）

**[日本語](#japanese)** | **[English](#english)**

---

<a id="japanese"></a>

## これは何か

ITサポート・ヘルプデスクの Exment プラグインです。お客様からの依頼（メール・受付フォーム・電話）と、監視やエラーログで見つけた問題を「チケット」で管理し、**進み具合をお客様とログインなしで共有**します。

| フォルダ | プラグイン | ダウンロード |
|---|---|---|
| [`ExmentITSupport/`](ExmentITSupport/) | Exment Plugin IT Support（ITサポート） — 内部名 `ExmentITSupport`・URL `/admin/plugins/exment_it_support` | [ExmentITSupport-1.0.0.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentITSupport-v1.0.0/ExmentITSupport-1.0.0.zip) |

- 動く Exment: 7.0 以上（PHP 8.2）
- 追加の PHP 拡張は要りません（IMAP もメールの解析もプラグインの中で行います）
- AWS など特定のクラウドに頼りません。メールは IMAP、保存は Exment のファイル保存を使います

## できること

| 分野 | できること |
|---|---|
| 担当者の画面 | 専用の**受付箱**（左に受付箱の種類と件数・中にチケット一覧・右に会話と項目の 3 列）。チケットを切り替えてもページを読み直さない。キーボード操作（`J`/`K` 上下・`R` 返信・`N` 社内メモ・`Ctrl+Enter` 送る・`A` 自分が担当・`E` 解決・`?` 一覧）。下書きの自動保存・ダークモード・スマホ表示 |
| 受付 | **メール**（IMAP で 5 分ごとに受信。ISO-2022-JP・Shift_JIS・添付ファイルに対応）、**受付フォーム**（ログインなし・FAQ を先に案内）、**電話・来訪**（担当者が登録）、**監視・エラーログ**（API。同じエラーは 1 件にまとめて回数を数える）、**IT 資産の期限**（保守・ライセンス・ドメイン・証明書の期限が近づくと自動で起票） |
| やりとり | お客様への返信はメールで届き、お客様がそのまま返信すると同じチケットに入る（件名の番号と Message-ID の両方で照合）。引用部分は自動で取り除く。**社内メモ**はお客様に見えない。**定型文**（差し込み可）、**ナレッジの候補**を返信欄の横に表示 |
| 二重対応の防止 | ほかの担当者が同じチケットの返事を書いていると、画面に表示 |
| お客様との共有 | **ログインなしのリンク**（チケット 1 件だけが見える・推測できない番号・期限と取り消し・開いた記録）。大事なチケットは**確認のコード**（登録のメールに 6 桁）。**自分の依頼の一覧**はメールアドレスを入れるとリンクが届く。ログインするお客様は**顧客ポータル**（会社の管理者は会社のチケットすべて） |
| SLA | お客様ごとの SLA（最初の返事・解決の目安を優先度ごとに）。**営業時間と祝日で数える**（日本の祝日は自動で入る）。**お客様の返事待ちの間は時計を止める**。期限が近い・超えたら担当者に通知 |
| 自動化 | 種類ごとの担当者、または対応中が少ない人に自動で割り当て。返事待ちのお願いメール → 自動で解決 → 自動で完了。解決したら満足度（5 段階）を聞く |
| ナレッジ（FAQ） | 公開した記事はお客様の FAQ ページと受付フォームに出る。チケットからワンクリックで下書き。「役に立った」の数と閲覧数 |
| IT 資産 | お客様の PC・サーバー・ライセンスなどを登録し、チケットとつなぐ |
| レポート | 受付・解決・SLA 達成率・最初の返事までの時間・満足度・受付の方法・種類・担当者ごと。**お客様ごとの作業時間と契約時間の比較**（月の報告に）・CSV |
| 検索 | 件名・本文・やりとり・メール・番号の**部分一致**、スペースで AND、タグは AND / OR を選べる |
| カスタマイズ | すべて Exment の標準テーブルなので、**項目・画面・一覧・ワークフロー・API を Exment の画面で自由に変えられる**。チケットのボード（カンバン）も付いています |

## 他社のヘルプデスクとのちがい

ITreview の「ヘルプデスクツール」カテゴリー（2026 年 11 月、51 製品・409 件）の上位製品（Zendesk・ChatPlus・Service Cloud・メールワイズ・楽楽自動応対・LMIS・Re:lation・Tayori・Jira Service Management など）の機能と口コミを調べ、多かった不満に答える形で作りました。

| 口コミで多かった不満 | このプラグイン |
|---|---|
| 設定・構築が難しい（いちばん多い） | ボタン 1 回のセットアップで、テーブル・メニュー・権限・SLA・種類・定型文・FAQ の例まで入る。すぐ使える |
| 型が決まっていて変えられない・API 連携が個別開発 | Exment のテーブルなので、項目・画面・ワークフロー・REST API をノーコードで変えられる |
| 部分一致で探せない・タグの AND/OR がない | 部分一致・やりとりの中まで検索・タグの AND/OR |
| ユーザー数課金・ストレージ課金・AI が別料金 | OSS のプラグイン。担当者の人数で料金が増えない。ファイルは自社の Exment に保存 |
| 日本語の資料・国内サポートが少ない | 画面・メール・祝日・ISO-2022-JP のメールまで日本向け |
| ダッシュボードが手動更新 | レポートは開いたときにその場で計算 |
| 対応時間の自動計測が実際と合わない | 作業時間は返事ごとに担当者が入れる（お客様ごとに合計して契約時間と比べる） |
| 二重返信・対応漏れ | 返事を書いている人の表示・「お客様から返事」の受付箱・SLA の通知 |
| お客様に進み具合を伝えにくい（多くの製品はメールだけ、またはポータルへのログインが必要） | **ログインなしのリンク**と**ログインありのポータル**の両方。どちらでも同じチケット |
| IT 資産と結びつかない（資産管理があるのは一部の製品だけ） | IT 資産のテーブルと、期限の自動起票 |
| 監視のアラートで問い合わせが埋まる | 同じエラーは 1 件にまとめて回数を数える。最初はお客様に見せない |

## 入れ方

1. **管理者設定 → プラグイン** で zip をアップロードします。
2. メニューの **ITサポート**（または `/admin/plugins/exment_it_support`）を開き、**セットアップする** を押します。
3. **設定** で、受付用のメールボックス（IMAP）と窓口のアドレスを入れて保存し、**IMAP につながるか試す** を押します。メールの送信は Exment の **システム設定 → メール** を使います。
4. 担当者を役割グループ **ITサポート 担当者** に入れます。ログインするお客様は **ITサポート お客様** に入れ、**お客様の担当者** の「Exment のユーザー」でつなぎます。
5. お客様には窓口の URL（`https://あなたの Exment/support`）を案内します。

自動処理（メールの受信・SLA・返事待ち・完了・資産の期限）は 5 分ごとに動きます。Exment のスケジューラー（`php artisan schedule:run` を cron で毎分）が動いている必要があります。

### 機能の管理（機能の管理の入った Exment で）

Exment の **管理者設定 → 機能の管理** に、ITサポートの入口が 2 つ出ます。オフの入口は、URL を直接開いても「見つかりません」になり、裏の処理も動きません。データは消えません。

| 機能 | 中身 |
| --- | --- |
| ITサポート：受付箱 | 担当者の画面（受付箱・ダッシュボード・設定・セットアップ）、5 分ごとの処理、保存のときのチケットの決まり。使える役割グループを選べます（システム管理者はいつでも使えます）。**セットアップの前に、まずこれをオンにします** |
| ITサポート：お客様の窓口 | ログインなしの `/support`、メールに入れる進み具合のリンク、メールの受信と `/support/intake/mail`、監視からの起票 `/support/intake/alert`、Exment の「自分の依頼」。**受付箱がオンのときだけ動きます** |

機能は入口の門で、ITサポートの **設定**（メールを受信する・お客様の窓口を使う など）はその中の細かい設定です。両方がオンのときに動きます。機能の管理のない Exment では、今までどおり全部使えます。機能の管理の入った版に上げたとき、前から ITサポートを使っていたサイトでは 2 つともオンのまま残ります（新しく入れたサイトでは、はじめはオフです）。

### 監視・エラーログから起票する

```sh
curl -X POST https://あなたの Exment/support/intake/alert \
  -H "X-ITS-Key: （設定画面のキー）" \
  -H "Content-Type: application/json" \
  -d '{"title":"Disk full on web01","source":"web01.example.co.jp","severity":"error","detail":"/var 98%"}'
```

| 項目 | 意味 |
|---|---|
| `title`（必須） | 件名 |
| `source` | 発生元。ドメインがお客様の「メールのドメイン」と合えば、そのお客様のチケットになる |
| `severity` | critical / error / warning … → 優先度 |
| `fingerprint` | 同じエラーをまとめる印（なければ発生元と件名の数字を除いたものから作る） |
| `customer` | お客様コード |

Postfix のパイプなど、元のメールをそのまま送れる仕組みからは `/support/intake/mail` に POST できます（同じキー）。

## 安全のための決まり

- **パスワードをメールで送らない**。お客様のリンクは推測できない長い番号で、**データベースにはハッシュだけ**を保存します（メールに同じリンクを入れるため、アプリの鍵で暗号化した写しも持ちます。データベースだけが漏れてもリンクは開けません）。
- リンクで見えるのは**そのチケット 1 件だけ**。社内メモ・ほかのお客様・担当者のメールアドレスは出ません。期限切れ・取り消し・まちがいは同じ「見つかりません」になります。
- 確認のコードは 10 分・5 回まで・1 回きり。リンク・フォームは IP ごとに回数を制限。CSRF・`noindex`・`X-Frame-Options: DENY`。
- 「自分の依頼を見る」は、登録があってもなくても同じ表示（だれがお客様かを調べられないように）。
- 件名にチケット番号を書いても、**依頼した人・CC・同じ会社の人**でなければ、そのチケットには入りません（新しいチケットになります）。SPF・DKIM・DMARC が失敗したメールは「なりすましの疑い」で、お客様に見せない状態で受け付けます。
- 自動返信・バウンス・自分のアドレスからのメールは取り込みません（メールのループを防ぐ）。
- 満足度のリンクは、開いただけでは保存しません（メールのリンクを自動で開くセキュリティ製品が勝手に評価しないように）。
- パスワードらしき文字がチケットにあると、担当者の画面に注意を出します。

### ログインなしのページについて

Exment は、プラグインの `config.json` からはログインなしのページを作れないようにしています（安全のため）。このプラグインは、ヘルプデスクにはアカウントのないお客様の入口が必要なので、Exment が起動するときに `/support/...` のページを自分で登録します（`PublicRoutes.php`）。Exment の公開フォーム（`/publicform`）と同じ考え方で、URL の秘密が開けるものだけを見せます。

## AI について（有料・別の仕組み）

分類・返信の下書き・過去の事例の検索・電話の AI 応対は、計画（ex1task ISS00213・ISS00235）どおり**有料の別の仕組み**にします。このプラグインは **AI がなくてもすべての機能が動きます**。

## 今後

- 返信の承認（新人の返事を先輩が確認してから送る）
- LINE・Teams・Slack からの受付と通知
- 英語・ベトナム語の画面
- AI（有料）: 分類・下書き・類似チケット・電話応対

---

<a id="english"></a>

## What this is

An IT support / help desk plugin for Exment. Requests from customers (mail, a public form, phone) and problems found by monitoring are managed as tickets, and **the progress is shared with the customer, with or without login**.

- Exment 7.0 or later (PHP 8.2). No extra PHP extension: the IMAP client and the MIME parser are part of the plugin.
- No dependency on a particular cloud: IMAP for mail, Exment's own file storage for attachments.

**Features**: a dedicated three-pane agent desk (queues, ticket list, conversation and properties) that switches tickets without reloading, with keyboard shortcuts, drafts and dark mode; mail intake (IMAP, Japanese encodings, attachments, threading by ticket number and Message-ID, quoted text stripped), public request form with FAQ suggestions, phone intake, monitor / error-log intake with de-duplication, asset expiry tickets; replies by mail, internal notes, canned replies, knowledge suggestions, "someone else is replying" warning; secret no-login links (one ticket per link, hashed, expiring, revocable, logged) with optional one-time codes, a "my requests" link by mail, and a logged-in customer portal; SLA per customer and priority counted in business hours and Japanese holidays, paused while waiting for the customer; auto assignment, reminders, auto resolve/close, satisfaction survey; reports including work time per customer against the contract; partial-match search with AND/OR tags. Every table is an ordinary Exment custom table, so fields, views, workflows and the API are all customisable without code.

**Install**: upload the zip in **Admin settings → Plugins**, open **ITサポート** and press **セットアップする**, then fill in the mailbox in **設定**. Agents go in the role group **ITサポート 担当者**, logged-in customers in **ITサポート お客様**. Customers use `https://your-exment/support`. The automation runs every five minutes through Exment's scheduler.

**No-login pages**: Exment does not let a plugin's `config.json` open a route without login, on purpose. A help desk needs a door for people without an account, so this plugin registers `/support/...` itself while Exment boots (`PublicRoutes.php`), the same idea as Exment's public form: the secret in the URL opens one thing and nothing else.

**AI** (classification, reply drafts, similar tickets, phone agent) is planned as a separate paid service; everything here works without it.
