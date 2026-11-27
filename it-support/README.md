# Exment Plugin IT Support（ITサポート）

**[日本語](#japanese)** | **[English](#english)**

---

<a id="japanese"></a>

## これは何か

ITサポート・ヘルプデスクの Exment プラグインです。お客様からの依頼（メール・受付フォーム・電話）と、監視やエラーログで見つけた問題を「チケット」で管理し、**進み具合をお客様とログインなしで共有**します。

| フォルダ | プラグイン | ダウンロード |
|---|---|---|
| [`ExmentITSupport/`](ExmentITSupport/) | Exment Plugin IT Support（ITサポート） — 内部名 `ExmentITSupport`・URL `/admin/plugins/exment_it_support` | [ExmentITSupport-1.0.5.zip](https://github.com/exceedone/exment-plugins/releases/download/ExmentITSupport-v1.0.5/ExmentITSupport-1.0.5.zip) |

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

**未リリース / Unreleased**: `plugins.plugin_types` が 1 つの種類だけのサイト（zip をアップロードせずにファイルを入れかえたサイト）でも、すべての画面がエラーになることがなくなりました（`Plugin` がページそのものになりました）。バッチとイベントには行の更新が要るので、ファイルを入れかえるのではなく、zip をアップロードしてください。A site whose `plugins.plugin_types` lists one type only (files replaced without uploading the zip) no longer fails on every page (`Plugin` is now the page itself); the batch and the event still need the row updated, so upload the zip rather than replacing the files.

左のメニューは **ITサポート** 1 つだけです（開くと受付箱）。チケット表・ナレッジ・お客様・連絡先・IT 資産・定型文は受付箱の上のメニューから、すべてのテーブル（チケットの種類・SLA も）は **設定** の「テーブル・権限」から開きます。1.0.6 までの「ITサポート」のフォルダー（受付箱とテーブル 8 つ）は、1.0.7 に上げたあと、最初に受付箱を開いたとき（またはセットアップの「テーブルを更新」）に 1 つにまとまります。管理者が自分で足したメニューは残ります。

1.0.9 からは、セットアップの前でも左のメニューに **ITサポート** を出します（Exment 7.2.1 では分類「ITサポート」の下）。見えるのはシステム管理者だけで、開くとセットアップの画面です。ほかの人には、セットアップが終わるまで出ません。セットアップのあとも同じメニュー 1 つです（`it-support/tools/test-menu-before-setup.php` で試せます）。

**1.1.0 から / Since 1.1.0**: 上のメニューの右に「マニュアル」を足しました。ユーザーの言語（日本語・ベトナム語、ほかは英語）で、exment.net のこのプラグインのマニュアルを新しいタブで開きます。Exment 7.2.4 以降では、画面の右上の「？」も、このプラグインの画面ではこのマニュアルを開きます。
5 分ごとのバッチは、することがないとき（セットアップの前・受付箱を使わないとき）も成功（終了コード 0）で終わります。前は 1 で、そのたびにエラーのログが出ていました。エラーのときは今までどおり失敗です。

**1.1.0 から / Since 1.1.0**: 「申請メニュー」を足しました（設定の「自動で進める」の「申請メニュー」、初めは使わない）。お客様の窓口の受付フォームに、アカウントの問題・パスワードの再設定・PC の貸し出し・ソフトのインストール・アクセスの申請などを並べ、それぞれの項目（「項目名|種類|必須|選択肢」を 1 行に 1 つ）で受け付けます。設定の「ひな形を入れる」で 5 つのひな形が入ります。「承認がいる」と「承認する人」を決めた申請は、保留のまま承認を待ち、承認する人にベルとメールで知らせます。承認の画面（`/support/approval/{番号}`、Exment へのログインが要ります）で承認すると対応を始め、却下すると完了にして依頼した人にメールで知らせます。承認する人は、ITサポートの担当者でなくてかまいません（Exment のユーザーであれば）。`it-support/tools/test-catalog.php` で試せます。

**1.1.0 から / Since 1.1.0**: メールの送る元のアドレスは、窓口のアドレス → `MAIL_FROM_ADDRESS` → Exment の「システム設定 → メール」の送信元 → 最初のシステム管理者のメールアドレス、の順に決めます（担当者への知らせのメールにも付けます）。どれもないときは、設定の画面に出します。担当者の列を「複数」にしたサイトでは、知らせは担当者みんなに届きます。

**1.1.0 から / Since 1.1.0**: 「問題」を足しました（設定の「自動で進める」の「問題」、初めは使わない）。同じ原因のチケットを 1 つの問題（PRB-00001 のような番号）にまとめ、起きていること・原因・回避のしかた・解決のしかたを書きます。受付箱のチケットの右の「問題」から、番号でつなぐ・このチケットから作る・外すができます。問題の画面で、つないだ開いているチケットをまとめて「解決」にし（社内のメモに解決のしかたを書きます。お客様へのメールは送りません）、原因・回避・解決からナレッジの下書きを作れます。上のメニューに「問題」が出ます。`it-support/tools/test-problems.php` で試せます。

**1.1.0 から / Since 1.1.0**: SLA のエスカレーションを段階に分けられるようにしました（設定の「SLA のエスカレーション」、初めは使わない）。期限までの時間の 75%（変えられます）で担当者へ、期限を過ぎたらリーダーへ、さらに 60 分（変えられます）過ぎたら管理者へ、それぞれ 1 回だけ知らせます。知らせ方は、画面のベル・メール・Teams のワークフロー・LINE WORKS（Exment 7.3 以降）・Webhook（JSON）から選びます。優先度が変わるなどで期限が変わると、はじめからやり直します。使うあいだは、今までの「まもなく期限」「超過」の 1 回の知らせのかわりになります。

**1.1.0 から / Since 1.1.0**: The two entries in Feature management are also in Vietnamese, Simplified Chinese, Korean, German and Spanish (`resources/lang/vi`, `zh_CN`, `ko`, `de`, `es`), shown in the language of each user (Exment 7.2.5); another language reads English.
機能の管理に出る 2 つの入口の名前と説明に、ベトナム語・中国語（簡体字）・韓国語・ドイツ語・スペイン語を足しました。ユーザーごとの言語で出ます（Exment 7.2.5）。ほかの言語では英語です。

自動処理（メールの受信・SLA・返事待ち・完了・資産の期限）は 5 分ごとに動きます。Exment のスケジューラー（`php artisan schedule:run` を cron で毎分）が動いている必要があります。

### 機能の管理（機能の管理の入った Exment で）

Exment の **管理者設定 → 機能の管理** に、ITサポートの入口が 2 つ出ます。オフの入口は、URL を直接開いても「見つかりません」になり、裏の処理も動きません。データは消えません。

| 機能 | 中身 |
| --- | --- |
| ITサポート：受付箱 | 担当者の画面（受付箱・ダッシュボード・設定・セットアップ）、5 分ごとの処理、保存のときのチケットの決まり。使える役割グループを選べます（システム管理者はいつでも使えます）。**セットアップの前に、まずこれをオンにします** |
| ITサポート：お客様の窓口 | ログインなしの `/support`、メールに入れる進み具合のリンク、メールの受信と `/support/intake/mail`、監視からの起票 `/support/intake/alert`、Exment の「自分の依頼」。**受付箱がオンのときだけ動きます** |

機能は入口の門で、ITサポートの **設定**（メールを受信する・お客様の窓口を使う など）はその中の細かい設定です。両方がオンのときに動きます。機能の管理のない Exment では、今までどおり全部使えます。機能の管理の入った版に上げたとき、前から ITサポートを使っていたサイトでは 2 つともオンのまま残ります（新しく入れたサイトでは、はじめはオフです）。

受付箱を「使わない」にすると、メニューの「ITサポート」も消えます。チケットなどのテーブルはデータとして残り、Exment のふつうのテーブルとして URL（`/admin/data/its_ticket` など）や **管理者設定 → カスタムテーブル** から開けます。メニューに出したいときは、**管理者設定 → メニュー** でテーブルを足してください。見せたくないときは、役割グループでテーブルの権限を外してください。

### 監視の道具とつなぐ（監視・エラーログから起票する）

**1.1.0 から / Since 1.1.0**（ISS02928）: Zabbix・Mackerel・Prometheus Alertmanager・Azure Monitor の知らせを、そのまま受けられるようにしました。送り元ごとのキー、復旧の知らせで閉じる、IT 資産につなぐ、ばたつきをまとめる、も足しました。

送り先の URL は 1 つです：`https://あなたの Exment/support/intake/alert`。受付箱と「お客様の窓口」の機能がオンのときだけ動きます。

#### 1. 送り元を作る
1. ITサポートの **設定** →「監視・エラーログから起票」の **送り元** で、監視の道具を選び、名前を入れて **送り元を作る** を押します。
2. **キー** と **署名の秘密** が 1 回だけ出ます。控えてください（IT Support にはハッシュだけが残ります。なくしたら **キーを作り直す**）。
3. 送り元の設定を決めて、**使う** にして **保存** します。作ったばかりの送り元は「使わない」です。

| 設定 | 中身 | はじめ |
|---|---|---|
| 使う | このキーで受けるか | 使わない |
| 復旧の知らせ | 「復旧しました」を書くだけ／書いて「解決」にする／書いて「完了」にする | 書くだけ |
| ばたつき | 復旧（解決・完了）のあと、この分のうちにまた起きたら、新しいチケットにせず同じチケットを開け直す（タグ「ばたつき」）。0 でしない | 30 分 |
| IT 資産 | ホスト名・IP アドレスで IT 資産を探して、チケットにつなぐ。資産にお客様があれば、チケットのお客様にもする | つなぐ |
| 署名 | 署名のない知らせを受けない（汎用と Zabbix だけ。下の 7） | 署名なしも受ける |
| 送り元の IP | ここに書いたアドレス・範囲（`192.0.2.0/24`）からだけ受ける。空はどこからでも | 空 |

- キーの送り方は 4 つです。道具に合うものを使います：ヘッダー `X-ITS-Key: キー`、`Authorization: Bearer キー`、URL の Basic 認証（`https://its:キー@あなたの Exment/support/intake/alert`）、URL の `?key=キー`。URL に入れるときは、アクセスログに残ることがあるので、送り元の IP もしぼってください。
- 送り元を **消す** と、そのキーはすぐ使えなくなります。できたチケットは残ります。
- 同じ問題（下の「同じ問題とみなすもの」）は 1 件にまとめ、回数を数えます（2・10・100・1000 回目にメモ）。できたチケットは、はじめはお客様に見せません。
- 「復旧しました」のメモは、ばたつきの分（1 分より短いときは 1 分）に 1 回だけ書きます。開いているチケットがない復旧の知らせは、何もしません。

#### 2. Zabbix（6.0 以降）
1. Zabbix の **通知 → メディアタイプ → 作成**：タイプ **Webhook**。スクリプトに `it-support/tools/zabbix/exment-it-support-webhook.js` の中身を貼ります。
2. パラメーター（名前 = 値）：`its_url` = 上の URL、`its_key` = キー、`its_secret` = 署名の秘密（署名を使わないときは空）、`event_value` = `{EVENT.VALUE}`、`event_id` = `{EVENT.ID}`、`trigger_id` = `{TRIGGER.ID}`、`subject` = `{ALERT.SUBJECT}`、`message` = `{ALERT.MESSAGE}`、`host` = `{HOST.HOST}`、`host_ip` = `{HOST.IP}`、`event_severity` = `{EVENT.SEVERITY}`、`event_nseverity` = `{EVENT.NSEVERITY}`、`event_tags` = `{EVENT.TAGS}`、`event_opdata` = `{EVENT.OPDATA}`、`event_url` = `{$ZABBIX.URL}/tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}`（`{$ZABBIX.URL}` はグローバルマクロで作ります）。お客様が決まっているときは `customer` = お客様コード。
3. **タグの処理**（Process tags）をオンにすると、問題にタグ `its_ticket` = チケット番号が付きます。
4. メッセージのテンプレートに「問題」と「問題の復旧」を作ります。ユーザーのメディアにこのメディアタイプを足し、アクションの「実行内容」と「復旧時の実行内容」で送ります。
- 同じ問題とみなすもの：トリガーの ID。

#### 3. Mackerel
1. Mackerel の **チャンネル → 通知グループ** で **Webhook** のチャンネルを作ります。
2. URL に `https://its:キー@あなたの Exment/support/intake/alert`（Basic 認証）か `…/support/intake/alert?key=キー` を入れます。Mackerel の Webhook には署名がありません。送り元の IP に Mackerel の送信元のアドレスを書くと安全です。
3. 通知する内容は「アラート」です。ほかの知らせ（テストなど）は受けても何もしません。
- 同じ問題とみなすもの：ホスト（外形監視などホストがないときはアラートの種類）と、監視の名前。`status` が `ok`、または `isOpen` が `false` の知らせを復旧とみなします。

#### 4. Prometheus Alertmanager
```yaml
receivers:
  - name: exment-its
    webhook_configs:
      - url: https://あなたの Exment/support/intake/alert
        send_resolved: true
        http_config:
          authorization:
            type: Bearer
            credentials: キー
```
- 1 回の知らせに入っている、いくつかのアラート（50 まで）を 1 つずつ扱います。
- 同じ問題とみなすもの：アラートの `fingerprint`（ラベルから作られる印）。ホストは `instance`（ポートを除く）、`host`・`hostname`・`nodename` のラベル。件名は注釈の `summary`、なければ `alertname`。重要度はラベルの `severity`、お客様はラベルの `customer`。

#### 5. Azure Monitor
1. **アクショングループ** に **Webhook** のアクションを足します。URI は `https://あなたの Exment/support/intake/alert?key=キー`。**共通アラートスキーマを有効にする** を「はい」にします。
2. アラートルールで、そのアクショングループを使います。
- 同じ問題とみなすもの：アラートルールと対象のリソース。`monitorCondition` が `Resolved` の知らせを復旧とみなします。重要度は Sev0 → 緊急、Sev1 → 高、Sev2 → 中、Sev3・Sev4 → 低。ホストは `configurationItems` の 1 つめ。

#### 6. 汎用（JSON）・アプリのログ
```sh
curl -X POST https://あなたの Exment/support/intake/alert \
  -H "X-ITS-Key: キー" \
  -H "Content-Type: application/json" \
  -d '{"title":"Disk full on web01","source":"web01.example.co.jp","severity":"error","detail":"/var 98%"}'
```

| 項目 | 意味 |
|---|---|
| `title`（必須） | 件名 |
| `source` | 発生元（ホスト名・URL）。ドメインがお客様の「メールのドメイン」と合えば、そのお客様のチケットになる。IT 資産を探すのにも使う |
| `ip` | IP アドレス（IT 資産を探す） |
| `severity` | critical / error / warning / info … → 優先度 |
| `status` | `resolved`・`ok`・`recovered` などで復旧 |
| `fingerprint` | 同じ問題をまとめる印（なければ、発生元と件名の数字を除いたものから作る） |
| `customer` | お客様コード |
| `url` | 監視の画面への URL（http・https だけ） |

#### 7. 署名（汎用・Zabbix）
送り元の「署名」をオンにすると、次の 2 つのヘッダーがない知らせ、合わない知らせ、5 分より前・後の知らせを受けません。
- `X-ITS-Timestamp`: 送る時刻（UNIX 時間の秒）
- `X-ITS-Signature`: `sha256=` と、「時刻 + `.` + 本文」を署名の秘密で HMAC-SHA256 した 16 進数

#### 8. IT 資産につなぐ
IT 資産の「名前」がホスト名か、ホスト名の最初の部分（`web01.example.co.jp` の `web01`）と同じとき、または「場所・IP アドレス」に IP アドレスかホスト名が 1 語として書いてあるとき、その資産をチケットにつなぎます。

#### 前からの共有のキー
1.0.9 までの 1 つのキー（設定の「共有のキー」）は、メールの受け口 `/support/intake/mail` に使います。前から使っているサイトでは、アラートも今までどおり受けます（復旧などの新しい動きはしません）。**共有のキーではアラートを受けない** で止められます。新しくセットアップしたサイトでは、はじめから受けません。

Postfix のパイプなど、元のメールをそのまま送れる仕組みからは `/support/intake/mail` に POST できます（共有のキー）。

### 監視の道具から IT 資産を取り込む（Exment 7.3 のデータ同期）

**1.1.0 から / Since 1.1.0**（ISS02928）: Exment 7.3 の「データ同期」があれば、**Mackerel** と **Zabbix** のホストを IT 資産に取り込めます（7.2 では出てきません）。データ同期の画面で、接続（Mackerel は API キー、Zabbix は URL と API トークン。どちらも読むだけの権限で足ります）と、同期の設定（IT 資産のテーブルへ）を作ります。

- ホストは、すでにある IT 資産に **つなぐだけ** が初めの設定です。探す順は、前につないだ資産 → シリアル番号 → 管理番号 → 名前（FQDN か、その最初の部分）→ IP アドレス。2 つ以上あてはまるときは、つながずに同期の結果に書きます（人が直します）。
- 「新しい IT 資産を作る」にすると、あてはまる資産がないホストの資産を作ります。お客様は「グループ=お客様コード」の表で決められます（Zabbix はホストグループ、Mackerel はサービス）。
- 書くのは道具が知っていること（ホスト名・IP アドレス・OS・機種・シリアル・同期の状態など）だけです。お客様・使う人・メモなど、人が書く項目は変えません。
- 道具から消えたホスト（Zabbix で無効、Mackerel で退役も）の資産は、消さずに「同期の状態：引退」の印をつけます。

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

**Install**: upload the zip in **Admin settings → Plugins**, open **ITサポート** and press **セットアップする**, then fill in the mailbox in **設定**. Agents go in the role group **ITサポート 担当者**, logged-in customers in **ITサポート お客様**. Customers use `https://your-exment/support`. The left menu has one entry, **ITサポート**, which opens the desk; the tables are opened from the desk's top bar and from **設定** (1.0.7 folds the folder of 1.0.6 into that one entry the first time the desk is opened, keeping any menu an administrator added). The automation runs every five minutes through Exment's scheduler.

**Feature management** (Exment versions that have it): IT Support adds two features, *desk* and *customer portal* (the portal needs the desk). New installs start with both off; sites that had the plugin before keep them on. Turning the desk off stops the desk, the portal and the batch, and its menu entry, but the tables (tickets and so on) stay as data and can still be opened as ordinary Exment tables (by URL such as `/admin/data/its_ticket`, from the custom table list, or from a menu an administrator adds); to hide them, remove the table permissions in the role groups.

**Monitoring tools** (since 1.1.0, ISS02928): `/support/intake/alert` reads what Zabbix (the media type in `it-support/tools/zabbix/`), Mackerel, Prometheus Alertmanager and Azure Monitor (common alert schema) send, as well as the generic JSON. Each sender has its own key (shown once, kept as a hash, turned off or deleted alone), sent as `X-ITS-Key`, a bearer token, the password of basic auth or `?key=`, and may be limited to its addresses and, for the generic JSON and Zabbix, an HMAC-SHA256 signature (`X-ITS-Timestamp`, `X-ITS-Signature: sha256=hex(hmac(secret, timestamp + "." + body))`). A new sender is off until an administrator turns it on. A recovery writes "recovered" on the ticket of the same problem and, if the sender is set so, resolves or closes it; a problem back within the sender's minutes after its ticket ended opens the same ticket again (flapping); the IT asset is found by host name or IP address. The old shared key keeps working for `/support/intake/mail` and, on sites that had it, for alerts as before. `php it-support/tools/test-alert-intake.php` checks the tools' sample bodies.

**No-login pages**: Exment does not let a plugin's `config.json` open a route without login, on purpose. A help desk needs a door for people without an account, so this plugin registers `/support/...` itself while Exment boots (`PublicRoutes.php`), the same idea as Exment's public form: the secret in the URL opens one thing and nothing else.

**AI** (classification, reply drafts, similar tickets, phone agent) is planned as a separate paid service; everything here works without it.

## Website chat behind a proxy

The chat window limits new chats per client IP and per box (the box's 「チャット：1 つの IP から 1 時間に始められる数」, default 5, and 「チャット：この受付箱で 1 時間に始められる数（全体）」, default 60).
The IP is Laravel's `request()->ip()`, which follows the site's trusted proxies (Exment's TrustProxies, `ADMIN_TRUST_PROXY_IPS` in .env); a raw `X-Forwarded-For` is never read. Behind a load balancer (Exment SaaS: AWS ALB) set the trusted proxies to the balancer, or every customer has the balancer's IP and the per-IP limit becomes one for the whole site; the per-box limit bounds a burst either way.

## プロキシのうしろの Web サイトのチャット

チャットの窓は、新しいチャットを IP ごとと受付箱ごとに数えて止めます（受付箱の「チャット：1 つの IP から 1 時間に始められる数」初めは 5、「チャット：この受付箱で 1 時間に始められる数（全体）」初めは 60）。
IP は Laravel の `request()->ip()` で、サイトの信頼するプロキシ（Exment の TrustProxies、.env の `ADMIN_TRUST_PROXY_IPS`）にしたがいます。`X-Forwarded-For` をそのまま読むことはしません。ロードバランサーのうしろ（Exment SaaS は AWS ALB）では、信頼するプロキシにロードバランサーを入れてください。入れないと、お客様がみなロードバランサーの IP になり、IP ごとの数がサイト全体のものになります。受付箱ごとの数は、どちらでも上限になります。
