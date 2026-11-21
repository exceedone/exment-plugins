# Exment Plugin Groupware

**[English](#english)** | **[日本語](#japanese)**

---

<a id="english"></a>

## What it does

The groupware of Exment (ex1task ISS00233). Two parts so far:

- **Web mail** (0.2.0, P2): a mail program in the browser, like Outlook or Gmail. See [Web mail](#web-mail).
- **Office** (0.1.0, P3): opens the Word (.docx), Excel (.xlsx) and PowerPoint (.pptx) files of the **file server** of Exment in a browser Office, [ibisOffice](https://github.com/ibisjp/ibis-office) by ibis inc.:

- The page of such a file gets an **Office で開く** (Open in Office) button. It opens the editor in a new tab.
- When the server has no LibreOffice (no PDF preview), the editor is shown **read-only** in the preview of the file page (0.2.2). It does not check the file out.
- **Save** in the editor adds a new version of the file (the version history of the file server keeps the older ones).
- While the file is open for editing it is **checked out**: nobody else can change it (ibisOffice has no editing together yet). It is checked in when the editor is closed. A user who may only view the file, or a file someone else has checked out, opens read-only.
- A save from an old version (someone saved in between) is refused, so nothing is overwritten.
- **Attachments of records** (0.3.0, ISS00416): other plugins can show a Word, Excel or PowerPoint file attached to a record read only, at `view/{uuid}` (Exment Plugin PM does, for the files of issues, wiki pages and projects). The file is read from its usual download address, so only those who may download it can see it. This part does not need the file server.
- **Editing attachments** (0.4.0, ISS00416): a user who may edit that record gets **編集する** (Edit) on that page (`edit/{uuid}`). **Save** replaces the content of the attachment and keeps its address, so links to it go on working. The content it had is kept as a file of its own on the same record (not listed as an attachment), and a comment on the record says who edited it, with a link to that earlier file. A save from content someone else replaced in between is refused.
  Only attachments (not the values of file columns) can be edited, by those who may edit the record (and, when `exment.file_delete_useronly` is on, only by the one who uploaded the file). A save takes up to 50 MB (or Exment's upload limit, if smaller).
  Two things to know: **an archived project of Exment Plugin PM does not stop editing** here, and on an Exment without older versions of attachments, **deleting the attachment does not delete the earlier contents** kept by edits: they stay readable from the links in the comments, for those who may read the record. On an Exment that has them (the 「差し替え」 of the attachment list, exceedone/exment PR #1866), each earlier content is also an older version of the attachment (「版 n」, up to `exment.document_version_limit`) and is deleted with it.
- **The menu "Mail"** (0.4.2): installing the plugin (or updating it to 0.4.2) makes one menu, "Mail", to the web mail (`plugins/exment_groupware/mail`). When the mail is not used (Feature management), the menu is hidden as its pages are. A site that has a menu to the mail already gets no second one, and a menu an administrator removed does not come back.
- **Where "Mail" is** (0.4.3): the menu is right below "File server" (or "HOME"), not below "Admin Setting". A site updated from 0.4.2 has it moved there, only when it is still where 0.4.2 put it (at the top level, below "Admin Setting"); a menu an administrator moved stays.
- **Feature management** (0.4.1): on an Exment with Feature management (Admin setting → Feature management), the web mail is a feature of its own (`exment_groupware.mail`): an administrator turns it on or off and chooses the role groups that may use it. A site that had the plugin before keeps it on; on a new site it starts off. The Office part follows the file server's feature. On an Exment without Feature management nothing changes.

| Folder | Plugin |
|---|---|
| [`ExmentGroupware/`](ExmentGroupware/) | Exment Plugin Groupware — plugin name `ExmentGroupware`, address `/admin/plugins/exment_groupware` |

## Requirements

- Exment 7.1 or later (checked on 7.1 and 7.2) and PHP 8.2. The web mail and the attachments of records work on any of them.
- To open the files of the **file server** in Office: an Exment with the file server (7.2.0 or later). Without it, that part does nothing; the rest works.
- On Exment 7.2.0 or later: the web mail is a feature of Feature management (see above), and the older contents of an edited attachment become its older versions (exceedone/exment PR #1866).
- The `zip` extension of PHP (to install ibisOffice).
- A browser with WebGPU: Chrome, Edge, or Safari 26 or later. The site must be on HTTPS (`http://localhost` works while developing).

## Install

1. Upload `ExmentGroupware-<version>.zip` in **Admin settings → Plugins**.
2. Open the plugin (**Admin settings → Plugins → Exment Plugin Groupware**, or `/admin/plugins/exment_groupware`) as a system administrator and press **ibisOffice 0.8.9 をインストールする**. The plugin downloads the release of ibisOffice from GitHub (about 40 MB, 125 MB unpacked), checks its SHA-256 (pinned in `IbisOffice.php`) and puts it in `public/vendor/exment_groupware/ibisoffice/0.8.9/` of the site, so that the editor comes from the same origin as Exment.

On a site with several web servers, or where `public/` cannot be written, serve the unpacked release elsewhere and set its address in the plugin's settings (**ibisOffice の場所（URL）**: the folder of `index.html`, for example `https://office.example.com/ibisoffice/0.8.9/`). The page then talks to the editor through messages.

### The web server

- Send `.wasm` as `application/wasm` (nginx and Apache do by default in recent versions).
- **Compress** `.wasm` and `.js` (gzip or Brotli). The wasm is 92 MB; compressed it is about a quarter of that. For nginx: `gzip_types application/wasm application/javascript text/javascript;`.
- The folder carries the version, so the files can be cached for a long time: `Cache-Control: public, max-age=31536000, immutable` under `/vendor/exment_groupware/ibisoffice/`.
- No COOP / COEP headers are needed. If the site sets a Content-Security-Policy, it needs `script-src 'self' 'wasm-unsafe-eval'`.

## ibisOffice and its licence

ibisOffice is software of ibis inc. under **AGPL-3.0-only** and a commercial licence. This plugin:

- uses ibisOffice **as it is released** (not modified), and does not carry it: it is downloaded from the release of ibis inc.;
- runs it in **its own iframe**; the page hands it only a few functions (save, open a link), as the embedding guide of ibisOffice describes;
- shows **"Powered by ibisOffice"** with a link to https://github.com/ibisjp/ibis-office on the editor page and on the plugin page (required by the [trademark rules](https://github.com/ibisjp/ibis-office/blob/main/docs/TRADEMARK.md)).

Before offering it on Exment SaaS, check with ibis inc. whether the AGPL terms are enough for that use (see ISS00233).

<a id="web-mail"></a>

## Web mail

Exment does not run a mail server. Like Outlook, it connects to the server the company already uses, over **IMAP** (read) and **SMTP** (send), so the same mailbox stays open in Outlook and on the phone. Nothing of the mail is copied into Exment; every screen reads the server.

- Folders, list (newest first, 50 a page), reading pane, search (subject, sender, recipient, body; Japanese works), unread / flagged filters, flags, move (also by dragging onto a folder), junk, delete to the trash, new folders.
- Write, reply, reply all, forward (with the attachments), Cc / Bcc, attachments (up to 25 MB a mail), simple formatting, signature, drafts. Ctrl+Enter sends.
- Several accounts per user. The unread count of each inbox is checked every minute.
- Japanese mail as it is: ISO-2022-JP and Shift_JIS, encoded names and file names.

| Service | Sign-in | Servers |
|---|---|---|
| Xserver | password (user name = the mail address) | `sv****.xserver.jp`, IMAP 993 / SMTP 465 (SSL) |
| KAGOYA | password (user name = the mail account name) | IMAP `m***.kagoya.net` 993, SMTP `smtp.kagoya.net` 465 (SSL) |
| Microsoft 365 | sign in with Microsoft (OAuth 2.0) | `outlook.office365.com` 993, `smtp.office365.com` 587 (STARTTLS) |
| Gmail / Google Workspace | sign in with Google (OAuth 2.0), or an app password | `imap.gmail.com` 993, `smtp.gmail.com` 465 |
| Others | password | any IMAP / SMTP server |

**Microsoft 365 and Google** need an app registered once by the system administrator, in the plugin's settings (**Admin settings → Plugins → Exment Plugin Groupware → settings**). The settings page shows the redirect URI to register (`…/admin/plugins/exment_groupware/mail/oauth/callback`).

- Microsoft: Azure portal → App registrations → a web app. Delegated permissions of Office 365 Exchange Online: `IMAP.AccessAsUser.All`, `SMTP.Send`, and `offline_access openid email profile`. SMTP AUTH must be allowed for the mailboxes. Tenant: empty = `organizations`.
- Google: Google Cloud console → an OAuth client ID (web application), scope `https://mail.google.com/`. For a Google Workspace used inside the company, an "internal" app needs no review by Google.

**Security.** Passwords and tokens are encrypted with the app key (`APP_KEY`). HTML mail is cleaned on the server and shown in a sandboxed iframe without scripts; images from outside are blocked until the user presses 画像を表示. Users can connect only to public addresses on the mail ports; for a mail server in the company network (on-premises Exment), the administrator turns on **社内のネットワークのメールサーバーを許可する** in the settings. Every call checks that the account belongs to the signed-in user.

The table `gw_mail_accounts` is made when the zip is uploaded (`database/migrations`).

---

<a id="japanese"></a>

## できること

Exment のグループウェア（ex1task ISS00233）です。今あるのは次の 2 つです。

- **Web メール**（0.2.0、P2）: Outlook・Gmail のような、ブラウザーのメールソフト。[Web メール](#web-mail-ja)を参照。
- **Office**（0.1.0、P3）: Exment の**ファイルサーバー**にある Word（.docx）・Excel（.xlsx）・PowerPoint（.pptx）のファイルを、株式会社アイビスのブラウザーで動く Office、[ibisOffice](https://github.com/ibisjp/ibis-office) で開きます。

- そのファイルの画面に **Office で開く** ボタンが出ます。押すと、新しいタブでエディターが開きます。
- サーバーに LibreOffice がない（PDF のプレビューを作れない）ときは、ファイルの画面のプレビューの欄に、エディターを**読み取り専用**で埋め込みます（0.2.2）。チェックアウトはしません。
- エディターで**保存**すると、ファイルの新しい版になります（前の版はファイルサーバーの版の履歴に残ります）。
- 編集のために開いている間は**チェックアウト**（編集中）になり、ほかの人は変更できません（ibisOffice にはまだ同時編集がないため）。エディターを閉じるとチェックインします。見るだけの権限の人や、ほかの人がチェックアウトしているファイルは、読み取り専用で開きます。
- 古い版から保存しようとしたとき（間にほかの人が保存したとき）は断るので、上書きで消えることはありません。
- **データの添付**（0.3.0、ISS00416）: データに添付した Word・Excel・PowerPoint を、ほかのプラグインの画面から読み取り専用で表示できます（`view/{uuid}`。Exment Plugin PM の課題・Wiki・プロジェクトのファイルで使います）。ファイルはいつものダウンロードの URL から読むので、ダウンロードできる人だけが見られます。ここはファイルサーバーがなくても動きます。
- **データの添付の編集**（0.4.0、ISS00416）: そのデータを編集できる人には、その画面に **編集する** が出ます（`edit/{uuid}`）。**保存**すると添付の中身が新しくなり、アドレスは変わらないので、そこへのリンクもそのまま使えます。前の中身は同じデータの別のファイルとして残し（添付の一覧には出ません）、データに「誰が編集したか」と前のファイルへのリンクをコメントで残します。開いたあとにほかの人が保存していたときは、保存を断ります。
  編集できるのは添付だけです（ファイルの列の値は対象外）。そのデータを編集できる人だけが編集でき、`exment.file_delete_useronly` が on のときは、ファイルを上げた人だけです。保存できる大きさは 50MB まで（Exment のアップロードの上限のほうが小さければ、そちら）です。
  気をつけること: **Exment Plugin PM のアーカイブ中のプロジェクトでも、ここからの編集は止まりません**。また、添付の古い版の仕組みがない Exment では、**添付を消しても、編集で残した前の中身は消えません**。データを見られる人は、コメントのリンクから読めたままです。仕組みがある Exment（添付の一覧の「差し替え」、exceedone/exment PR #1866）では、前の中身は添付の古い版（「版 n」、`exment.document_version_limit` まで）にもなり、添付を消すと一緒に消えます。
- **メニュー「メール」**（0.4.2）: プラグインを入れたとき（0.4.2 に上げたときも）、Web メール（`plugins/exment_groupware/mail`）へのメニュー「メール」を 1 つ作ります。機能の管理でメールを「使わない」にしたときは、ページと同じくメニューも隠れます。メールへのメニューがもうあるサイトには作らず、管理者が消したメニューは戻りません。
- **「メール」の場所**（0.4.3）: メニューは「管理者設定」の下ではなく、「ファイルサーバー」（なければ「HOME」）のすぐ下です。0.4.2 から上げたサイトでは、0.4.2 が作った場所（トップの、管理者設定より下）にあるときだけ動かします。管理者が動かしたメニューはそのままです。
- **機能の管理**（0.4.1）: 「機能の管理」（管理者設定）のある Exment では、Web メールが 1 つの機能（`exment_groupware.mail`）になり、管理者が使う・使わないと、使える役割グループを決めます。前からこのプラグインを入れているサイトでは「使う」のまま、新しいサイトでははじめ「使わない」です。Office はファイルサーバーの機能に従います。機能の管理のない Exment では、今までと同じです。

## 必要なもの

- Exment 7.1 以上（7.1・7.2 で確かめています）と PHP 8.2。Web メールとデータの添付は、どれでも動きます。
- **ファイルサーバー**のファイルを Office で開くには、ファイルサーバーのある Exment（7.2.0 以降）。ないときは、その部分だけが何もせず、ほかは動きます。
- Exment 7.2.0 以降では、Web メールは「機能の管理」の 1 つの機能になり（上の「機能の管理」）、添付を編集したときの前の中身は、その添付の前の版になります（exceedone/exment PR #1866）。
- PHP の `zip` の拡張（ibisOffice のインストールに使います）。
- WebGPU のあるブラウザー: Chrome・Edge、または Safari 26 以降。サイトは HTTPS（開発中は `http://localhost` でも動きます）。

## インストール

1. **管理者設定 → プラグイン** で `ExmentGroupware-<version>.zip` をアップロードします。
2. システム管理者でプラグインの画面（`/admin/plugins/exment_groupware`）を開き、**ibisOffice 0.8.9 をインストールする** を押します。GitHub のリリース（約 40 MB、展開すると 125 MB）をダウンロードし、SHA-256（`IbisOffice.php` に固定）を確かめてから、サイトの `public/vendor/exment_groupware/ibisoffice/0.8.9/` に置きます。Exment と同じオリジンからエディターを出すためです。

Web サーバーが複数台のときや、`public/` に書き込めないときは、展開したリリースをほかの場所で公開し、プラグインの設定の **ibisOffice の場所（URL）** に `index.html` のあるフォルダーの URL を入れます（例: `https://office.example.com/ibisoffice/0.8.9/`）。

### Web サーバーの設定

- `.wasm` は `application/wasm` で送ります（最近の nginx・Apache は標準でそうなっています）。
- `.wasm` と `.js` は**圧縮**して送ります（gzip か Brotli）。wasm は 92 MB で、圧縮すると約 4 分の 1 になります。nginx なら `gzip_types application/wasm application/javascript text/javascript;`。
- フォルダーに版が入っているので、長くキャッシュできます（`/vendor/exment_groupware/ibisoffice/` に `Cache-Control: public, max-age=31536000, immutable`）。
- COOP・COEP のヘッダーは要りません。Content-Security-Policy を付けているサイトは `script-src 'self' 'wasm-unsafe-eval'` が要ります。

## ibisOffice のライセンス

ibisOffice は株式会社アイビスのソフトウェアで、**AGPL-3.0-only** と商用ライセンスのデュアルライセンスです。このプラグインは:

- ibisOffice を**リリースのまま**（改変せず）使い、プラグインには含めません（アイビスのリリースからダウンロードします）。
- **別の iframe** で動かし、ページからは保存・リンクを開くなどの関数だけを渡します（ibisOffice の組み込みの説明のとおり）。
- エディターの画面とプラグインの画面に、https://github.com/ibisjp/ibis-office へのリンク付きで **「Powered by ibisOffice」** を表示します（[商標の決まり](https://github.com/ibisjp/ibis-office/blob/main/docs/TRADEMARK.md)で必須）。

Exment SaaS で提供する前に、その使い方が AGPL の条件で足りるかをアイビスに確認してください（ISS00233 を参照）。

<a id="web-mail-ja"></a>

## Web メール

Exment はメールサーバーを持ちません。Outlook と同じように、会社が今使っているメールサーバーに **IMAP**（受信）と **SMTP**（送信）でつなぎます。メールはサーバーに残るので、Outlook やスマホのメールと並べて使えます。Exment にメールをコピーしません（画面を開くたびにサーバーを読みます）。

- フォルダー・一覧（新しい順、1 ページ 50 件）・閲覧ウィンドウ・検索（件名・差出人・宛先・本文。日本語も可）・未読／フラグ付きの絞り込み・フラグ・移動（フォルダーへのドラッグも可）・迷惑メール・ごみ箱へ削除・フォルダーの作成
- 新規・返信・全員に返信・転送（添付ごと）・Cc／Bcc・添付（1 通 25 MB まで）・簡単な書式・署名・下書き。Ctrl+Enter で送信
- 1 人で複数のアカウント。受信トレイの未読数を 1 分ごとに確かめます
- 日本語のメール（ISO-2022-JP・Shift_JIS、エンコードされた名前・ファイル名）をそのまま読めます

| サービス | ログイン | サーバー |
|---|---|---|
| Xserver | パスワード（ユーザー名はメールアドレス） | `sv****.xserver.jp`、IMAP 993／SMTP 465（SSL） |
| KAGOYA | パスワード（ユーザー名はメールアカウント名） | IMAP `m***.kagoya.net` 993、SMTP `smtp.kagoya.net` 465（SSL） |
| Microsoft 365 | Microsoft でサインイン（OAuth 2.0） | `outlook.office365.com` 993、`smtp.office365.com` 587（STARTTLS） |
| Gmail・Google Workspace | Google でサインイン（OAuth 2.0）、またはアプリ パスワード | `imap.gmail.com` 993、`smtp.gmail.com` 465 |
| そのほか | パスワード | どの IMAP・SMTP サーバーでも |

**Microsoft 365・Google** は、システム管理者がアプリを一度登録し、プラグインの設定（**管理者設定 → プラグイン → Exment Plugin Groupware → 設定**）に入れます。設定の画面に、登録するリダイレクト URI（`…/admin/plugins/exment_groupware/mail/oauth/callback`）が出ます。

- Microsoft: Azure portal →「アプリの登録」→ Web アプリ。Office 365 Exchange Online の委任のアクセス許可 `IMAP.AccessAsUser.All`・`SMTP.Send` と `offline_access openid email profile`。メールボックスで SMTP 認証が許可されている必要があります。テナントは空なら `organizations`。
- Google: Google Cloud console → OAuth クライアント ID（ウェブ アプリケーション）、スコープ `https://mail.google.com/`。社内で使う Google Workspace なら「内部」のアプリにすれば Google の審査は要りません。

**セキュリティ**: パスワード・トークンはアプリの鍵（`APP_KEY`）で暗号化します。HTML のメールはサーバーで危ない部分を取り除き、スクリプトの動かない iframe（sandbox）で表示します。外部の画像は「画像を表示」を押すまで読み込みません。つなげるのはインターネットのアドレスのメールのポートだけです。社内のネットワークのメールサーバー（オンプレミスの Exment）につなぐときは、管理者が設定の **社内のネットワークのメールサーバーを許可する** をオンにします。どの操作でも、アカウントがログインしている本人のものかを確かめます。

テーブル `gw_mail_accounts` は zip をアップロードしたときに作られます（`database/migrations`）。
