# Exment Plugin Groupware

**[English](#english)** | **[日本語](#japanese)**

---

<a id="english"></a>

**Changed in 0.5.0**

- The README says that a picture cannot be pasted into Office (ibisOffice 0.8.9 in the browser takes only text), and what to do instead: [Pasting a picture into Office](#pasting-a-picture-into-office).
- Calendar: ExmentPM's issues on the calendar (ISS02947, part 4). The new choice in the toolbar (off at first) shows **My issues**, **My projects** or one project: the issues with a start and a due date as a bar, the ones with a due date only as a mark on it, the milestones, and the dates of ExmentPM's recurring schedules that have no issue yet, as 「予定（まだ課題になっていない）」, with their moved and skipped dates. Each project has its own colour. An issue opens in ExmentPM; a date of a schedule gives **この回だけ変える** (ExmentPM's schedule page, for the project's manager). Read only, through Exment's permissions on the project and issue tables: a person sees only what they may read. Shown only when the site has ExmentPM's tables; the schedules only while ExmentPM makes their issues (its Feature management).
- Calendar: the rooms of a day also list a room taken out of use while it still has bookings that day, marked 「使わない」.
- The manual: a link 「マニュアル」 on the top page and in the side of the mail screen opens the manual of the plugin on exment.net in the language of the user (Japanese, Vietnamese, else English), and Exment's "?" in the header opens it on the plugin's pages (Exment 7.2.4 and later).
- A new version of this plugin may ask for a newer ibisOffice (ISS02827). Until now the site then said ibisOffice was not installed and Office stopped. Now Office keeps opening with the version already installed, and Feature management and the page of the plugin say a newer version is there, with **ibisOffice を X に更新する**. The update installs it as an install does (checked by its SHA-256) and then deletes the older folders.
- On an Exment without the menu groups (before 7.2.1), the menu **Mail** is no longer at the end of the left menu under the administrator settings: it goes just above them. Only the menu as the plugin made it moves; one an administrator moved or renamed stays. On Exment 7.2.1 and later it is in the group グループウェア, as before.
- Web mail: the dates of the mails are shown in the time zone the user chose in Exment (Exment 7.2.4; a user who chose none gets the time zone of the site). On an older Exment they are shown in the time zone of the browser, as before.
- The names and descriptions in Feature management are also in Vietnamese, Simplified Chinese, Korean, German and Spanish (`resources/lang/vi`, `zh_CN`, `ko`, `de`, `es`), shown in the language of each user (Exment 7.2.5). Another language reads English.
- Calendar: the ICS export address and outside calendars (ISS02947, part 5; Feature management → **カレンダーの連携（ICS）**, off at first). A person can make an ICS address of their calendar for Google Calendar or Outlook (events open to all with their title, any other as 「予定あり」 only), and read ICS addresses of other calendars into theirs every 15 minutes. See [Calendar: the ICS export and outside calendars](#calendar-links).

**Changed in 0.4.7**

- Exment calls the file server **File sharing** (ファイル共有) from 7.2.1, so this plugin does too: the description in Feature management and the page of the plugin. Nothing else changes.

**Changed in 0.4.6**

- A blank Word document took no typing: the blank of 0.4.4 and 0.4.5 (and of Exment's File sharing "New") had no paragraph, so the editor had nowhere to put the cursor (文字数 0). The blank now has one empty paragraph, as Word's own new document. A file that is still exactly the old blank opens as the fixed one and is repaired when it is saved.

**Changed in 0.4.5**

- Office is a row of its own in Feature management (`exment_groupware.office`; on for the sites that had the plugin, off on a new site). While ibisOffice is not installed, the row says what it needs (the PHP zip extension, about 125 MB in `public/`, no LibreOffice) and, on an Exment whose Feature management has row buttons, gives **ibisOffice を入れる**.
- On the page of a Word, Excel or PowerPoint file, a system administrator sees, where the preview would be, that ibisOffice could show and edit it here, with the way to install it. Others see the page as before.
- What would stop the install is said before it is tried: no zip extension, `public/` not writable, not enough space; and when GitHub cannot be reached. Each with the way round (serve ibisOffice elsewhere and set its URL).

**Changed in 0.4.4**

- An empty Office file (0 bytes, as Windows makes with New > Word document) opened in ibisOffice as a spreadsheet. It now opens as a blank document of the kind its name says (docx, xlsx, pptx), and the first save makes it a real document.
- Close (× at the top right) checks the file in and leaves the editor: a tab "Office で開く" opened is closed, otherwise the whole window goes back to the file page (Exment no longer opens inside the editor's frame). The read-only preview on the file page has no Close.
- The installer writes `.gz` copies of ibisOffice's large files and an `.htaccess` for Apache; see [The web server](#the-web-server) (and the nginx settings there).

## What it does

The groupware of Exment (ex1task ISS00233). Two parts so far:

- **Web mail** (0.2.0, P2): a mail program in the browser, like Outlook or Gmail. See [Web mail](#web-mail).
- **Office** (0.1.0, P3): opens the Word (.docx), Excel (.xlsx) and PowerPoint (.pptx) files of the **File sharing** of Exment in a browser Office, [ibisOffice](https://github.com/ibisjp/ibis-office) by ibis inc.:

- The page of such a file gets an **Office で開く** (Open in Office) button. It opens the editor in a new tab.
- When the server has no LibreOffice (no PDF preview), the editor is shown **read-only** in the preview of the file page (0.2.2). It does not check the file out.
- **Save** in the editor adds a new version of the file (the version history of File sharing keeps the older ones).
- While the file is open for editing it is **checked out**: nobody else can change it (ibisOffice has no editing together yet). It is checked in when the editor is closed. A user who may only view the file, or a file someone else has checked out, opens read-only.
- A save from an old version (someone saved in between) is refused, so nothing is overwritten.
- **Attachments of records** (0.3.0, ISS00416): other plugins can show a Word, Excel or PowerPoint file attached to a record read only, at `view/{uuid}` (Exment Plugin PM does, for the files of issues, wiki pages and projects). The file is read from its usual download address, so only those who may download it can see it. This part does not need File sharing.
- **Editing attachments** (0.4.0, ISS00416): a user who may edit that record gets **編集する** (Edit) on that page (`edit/{uuid}`). **Save** replaces the content of the attachment and keeps its address, so links to it go on working. The content it had is kept as a file of its own on the same record (not listed as an attachment), and a comment on the record says who edited it, with a link to that earlier file. A save from content someone else replaced in between is refused.
  Only attachments (not the values of file columns) can be edited, by those who may edit the record (and, when `exment.file_delete_useronly` is on, only by the one who uploaded the file). A save takes up to 50 MB (or Exment's upload limit, if smaller).
  Two things to know: **an archived project of Exment Plugin PM does not stop editing** here, and on an Exment without older versions of attachments, **deleting the attachment does not delete the earlier contents** kept by edits: they stay readable from the links in the comments, for those who may read the record. On an Exment that has them (the 「差し替え」 of the attachment list, exceedone/exment PR #1866), each earlier content is also an older version of the attachment (「版 n」, up to `exment.document_version_limit`) and is deleted with it.
- **The menu "Mail"** (0.4.2): installing the plugin (or updating it to 0.4.2) makes one menu, "Mail", to the web mail (`plugins/exment_groupware/mail`). When the mail is not used (Feature management), the menu is hidden as its pages are. A site that has a menu to the mail already gets no second one, and a menu an administrator removed does not come back.
- **Feature management** (0.4.1): on an Exment with Feature management (Admin setting → Feature management), the web mail is a feature of its own (`exment_groupware.mail`): an administrator turns it on or off and chooses the role groups that may use it. A site that had the plugin before keeps it on; on a new site it starts off. The Office part follows the feature of File sharing. On an Exment without Feature management nothing changes.

| Folder | Plugin |
|---|---|
| [`ExmentGroupware/`](ExmentGroupware/) | Exment Plugin Groupware — plugin name `ExmentGroupware`, address `/admin/plugins/exment_groupware` |

## Requirements

- Exment 7.1 or later (checked on 7.1 and 7.2) and PHP 8.2. The web mail and the attachments of records work on any of them.
- To open the files of the **File sharing** in Office: an Exment with File sharing (7.2.1 or later). Without it, that part does nothing; the rest works.
- On Exment 7.2.1 or later: the web mail is a feature of Feature management (see above), and the older contents of an edited attachment become its older versions (exceedone/exment PR #1866).
- The `zip` extension of PHP (to install ibisOffice).
- A browser with WebGPU: Chrome, Edge, or Safari 26 or later. The site must be on HTTPS (`http://localhost` works while developing).

## Install

1. Upload `ExmentGroupware-<version>.zip` in **Admin settings → Plugins**.
2. Open the plugin (**Admin settings → Plugins → Exment Plugin Groupware**, or `/admin/plugins/exment_groupware`) as a system administrator and press **ibisOffice 0.8.9 をインストールする**. The plugin downloads the release of ibisOffice from GitHub (about 40 MB, 125 MB unpacked), checks its SHA-256 (pinned in `IbisOffice.php`) and puts it in `public/vendor/exment_groupware/ibisoffice/0.8.9/` of the site, so that the editor comes from the same origin as Exment.

On a site with several web servers, or where `public/` cannot be written, serve the unpacked release elsewhere and set its address in the plugin's settings (**ibisOffice の場所（URL）**: the folder of `index.html`, for example `https://office.example.com/ibisoffice/0.8.9/`). The page then talks to the editor through messages.

### The web server

The editor is large (the `.wasm` is 92 MB, about 24 MB gzipped). Sent compressed and kept by the browser, it is downloaded once; otherwise every opening waits for it.

- **Apache** (rental servers such as XServer included): nothing to do. From 0.4.4 the installer writes `.gz` copies of the large files and an `.htaccess` in `public/vendor/exment_groupware/ibisoffice/`: the `.wasm` type, a year's cache, and the `.gz` copies to browsers that accept gzip (`mod_rewrite`, `mod_headers`, `mod_mime`; `mod_deflate` when the copies are missing). Each rule sits in an `IfModule`, so a missing module is skipped. The server must allow `.htaccess` there (`AllowOverride All`, as Exment's own `.htaccess` needs).
- **nginx** does not read `.htaccess`. Add this to the site:

```nginx
# inside the server { } of the site, before "location /"
location ^~ /vendor/exment_groupware/ibisoffice/ {
    gzip_static on;            # serve the .gz copies the installer wrote
    gzip on;                   # and compress anything without one
    gzip_types application/wasm text/javascript application/javascript text/css application/json image/svg+xml;
    types { application/wasm wasm; }
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable" always;
    # add_header here drops the ones of the server { }: repeat them
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    try_files $uri =404;
}
```

- **X-Frame-Options must be `SAMEORIGIN`, not `DENY`**: the editor is an `iframe` of the same site, and `DENY` makes the browser refuse it (the screen stays empty). The same goes for a Content-Security-Policy `frame-ancestors`: `'self'`.
- No COOP / COEP headers are needed. If the site sets a Content-Security-Policy, it needs `script-src 'self' 'wasm-unsafe-eval'`.
- It works without Docker, on a rental server: ibisOffice runs in the browser, and the server only hands out its files. No LibreOffice is needed.

## ibisOffice and its licence

ibisOffice is software of ibis inc. under **AGPL-3.0-only** and a commercial licence. This plugin:

- uses ibisOffice **as it is released** (not modified), and does not carry it: it is downloaded from the release of ibis inc.;
- runs it in **its own iframe**; the page hands it only a few functions (save, open a link), as the embedding guide of ibisOffice describes;
- shows **"Powered by ibisOffice"** with a link to https://github.com/ibisjp/ibis-office on the editor page and on the plugin page (required by the [trademark rules](https://github.com/ibisjp/ibis-office/blob/main/docs/TRADEMARK.md)).

Before offering it on Exment SaaS, check with ibis inc. whether the AGPL terms are enough for that use (see ISS00233).

### Pasting a picture into Office

In the browser version of ibisOffice that this plugin installs (0.8.9), **Ctrl+V of a picture (a screenshot, an image copied in another program) does nothing**, in a spreadsheet, a document or a slide. Text and cells paste as usual. The page of this plugin does not catch the paste: it reaches ibisOffice, whose browser version takes only the text of what is pasted for now. The page also has no way to hand ibisOffice a picture (its embedding interface has none).

Until ibisOffice takes pictures: save the picture as a file, insert it in the desktop version of the program, or attach the picture to the record or the folder beside the file.

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

<a id="calendar-links"></a>

## Calendar: the ICS export and outside calendars

Part 5 of the calendar (ISS02947). Off at first: Admin setting → Feature management → **カレンダーの連携（ICS）** (`exment_groupware.calendar_links`, needs the calendar). A person allowed it gets **連携** in the calendar's toolbar.

**The ICS export address.** In the 「連携」 dialog of the calendar, **URL を作る** makes an address `https://<site>/gw-calendar/ics/<token>.ics` that a calendar program (Google Calendar "From URL", Outlook, the iPhone) reads without logging in. The token is 64 random hex characters; only its SHA-256 is kept, so the address is shown once, when it is made. Once there is one, **新しい URL を作る** makes a new one and stops the old; **止める** stops it. A stopped or unknown address, a deleted person or a person no longer allowed the feature answers 404. At most 30 reads a minute for one address and 120 from one IP.

- It has the person's events and the events they attend, from 30 days back to 365 days ahead.
- **Who sees what**: the export is what somebody who is not logged in sees of that person's calendar, by the same rules as the page (Visibility, with no viewer). An event open to all (「全員」) has its title and place. Any other event (部署, 参加者だけ, 予定ありだけ, and an outside calendar shown busy) is a VEVENT of its time only: SUMMARY 「予定あり」, CLASS:PRIVATE, no UID of the event, no place. No event has its notes or attendees.
- **Repeats are expanded**: each occurrence is one VEVENT in the window (no RRULE, EXDATE or RECURRENCE-ID in the export), so what a calendar program shows is exactly the calendar's own occurrences, moved and cancelled ones included. Times are in UTC; an all-day event is written with its dates (VALUE=DATE, the end the day after its last day). Text is escaped and lines are folded at 75 octets (RFC 5545).

**Outside calendars (ICS addresses).** **カレンダーを足す**: a name, the ICS address (`https://`, `http://` or `webcal://`) and what the others see: 「予定あり」 only (the default) or the title and the place. Up to 10 per person.

- The address is encrypted with `APP_KEY` and never shown again: the page shows its host only. To change it, type a new one.
- It is read every 15 minutes, each link a little later than the others, by the plugin's batch (`php artisan exment:batch --name=ExmentGroupware --param1=calendar`) and after the answer of a calendar page (3 links at most, after the page is sent). **今すぐ読む** reads it now (once a minute).
- Read: GET with `If-None-Match` / `If-Modified-Since` (304: nothing changes), 15 seconds (for the whole fetch, redirects included) and 5 MB at most, up to 4 redirects. VEVENTs from 30 days back to 180 days ahead, with RRULE (the part the calendar reads; another rule shows its first occurrence), EXDATE, RDATE, RECURRENCE-ID (moved or cancelled occurrences), all day, TZID (IANA names, the common Windows names, a VTIMEZONE's X-LIC-LOCATION; else the site's time zone) and STATUS:CANCELLED. An event removed there is removed here. At most 5000 events a calendar. A COUNT above 1000 is cut to 1000 (the series ends at its 1000th occurrence), and a COUNT is kept as the UNTIL of its last date. A calendar whose text takes more than 10 seconds to read is not read (its events stay as they were). The link of a person deleted or no longer allowed the feature is not read (tried again a day later).
- The events are the person's (source=link) and cannot be changed here. The others see them as the link says.
- A failure is kept on the link (shown as words, never the address) and the next try waits longer (up to 4 hours). A calendar it cannot read is never an error of the page.
- **The address check**: the same as the web mail's (HostGuard). Only public addresses, and only the ports 80, 443, 8080 and 8443 (always); each redirect is checked again, and the connection goes to the address checked (no second lookup of the name; the address curl connected to is checked again). The plugin's setting **社内のネットワークのカレンダー（ICS の URL）を許可する** (`calendar_allow_private`, off at first; not the mail's setting) allows the internal network too (10/8, 172.16/12, 192.168/16, 100.64/10, fc00::/7). Never: loopback, link-local (169.254/16, fe80::/10: the cloud's metadata), 0/8, multicast, and the IPv6 ranges that carry IPv4 (NAT64, Teredo, 6to4).

The tables `gw_cal_links` and `gw_cal_feeds` are made when the zip is uploaded (`database/migrations`).

**Things to know.**

- **No proxy.** The outside calendars are fetched directly, never through an HTTP proxy (the server connects to the very address it checked, so a proxy would get round the check). A site that reaches the internet only through a proxy cannot read ICS links. The ICS export address is not affected.
- **No session, no IP filter.** The export address has no session (a calendar program keeps no cookies). It follows Exment's maintenance mode as Exment's public pages do (and, on Exment SaaS, the tenant's expiry). It is not behind the web IP filter of the admin pages (Admin setting → IP filter), since Google's or Microsoft's servers read it from outside: whoever has the address can read it, so keep it to yourself and make a new one if it leaks.
- **Many events in one window.** A view of the calendar (and the export) reads the repeats and the one-time events of a window separately, at most 5000 of each: with more one-time events than that in one window (many outside calendars), the earliest 5000 are shown. A window has at most 20000 occurrences: the person's own events first, then each outside calendar an equal share of the rest (the smaller ones first, what one leaves goes to the next), so one calendar of many repeats pushes out neither (a warning goes to the log).
- **A slow calendar server.** A link read after the answer of a calendar page keeps that PHP worker (PHP-FPM) busy until it is read, 15 seconds at most (plus 10 for a large calendar), at most once a minute for the site. The page itself is already sent.
- **Cached routes.** The ICS export address is a route the plugin adds while Exment boots (like ExmentITSupport's pages without login). When Laravel's routes are cached (`php artisan route:cache`), it is not there and answers 404. Do not cache the routes on a site that uses it (`php artisan route:clear` undoes it).

Coming: an export of one's own calendar with all its details, with a token of its own for the owner only (later); Google and Microsoft calendars read only (part 6), CalDAV (part 7), both ways (part 8).

---

<a id="japanese"></a>

**未リリース**

- Office に画像を貼り付けられないこと（ブラウザー版の ibisOffice 0.8.9 は文字だけを受け取ります）と、その代わりの方法を README に書きました（[Office に画像を貼り付ける](#office-に画像を貼り付ける)）。
- カレンダー：ExmentPM の課題をカレンダーに出せます（ISS02947 の 4）。ツールバーの新しい選択（初めは「課題を出さない」）で、**自分の課題**・**自分のプロジェクト**・1 つのプロジェクトを選びます。開始日と期限のある課題は帯で、期限だけの課題は期限の日に印で出します。マイルストーンと、ExmentPM の定期の予定のうち、まだ課題になっていない日も「予定（まだ課題になっていない）」として出します（動かした日・とばした日もそのとおり）。プロジェクトごとに色が分かれます。課題を押すと ExmentPM で開きます。定期の予定の日からは **この回だけ変える**（ExmentPM の定期の予定の画面。プロジェクトの管理者）へ進めます。読むだけで、プロジェクトと課題のテーブルの Exment の権限のとおりに、見てよいものだけを出します。ExmentPM のテーブルがあるサイトだけに出ます。定期の予定は、ExmentPM が課題を作る設定（機能の管理）のときだけです。
- カレンダー：その日の会議室・備品に、「使わない」にした会議室・備品も、その日に予約が残っていれば「使わない」と付けて出します。
- メニューのグループがない Exment（7.2.1 より前）で、「メール」のメニューが左のメニューの最後（管理者設定の下）に入らないようにしました。管理者設定のすぐ上に動かします。プラグインが作ったままのメニューだけを動かし、管理者が動かしたり名前を変えたりしたものはそのままです。7.2.1 からの Exment では、これまでどおりグループ「グループウェア」の中です。
- Web メール：メールの日付を、ユーザーが Exment で選んだタイムゾーンで出します（Exment 7.2.4。選んでいないユーザーはサイトのタイムゾーン）。それより前の Exment では、今までどおりブラウザのタイムゾーンで出します。
- 機能の管理に出る名前と説明に、ベトナム語・中国語（簡体字）・韓国語・ドイツ語・スペイン語を足しました（`resources/lang/vi`・`zh_CN`・`ko`・`de`・`es`）。ユーザーごとの言語で出ます（Exment 7.2.5）。ほかの言語では英語です。
- カレンダー：ICS の書き出しの URL と、ほかのカレンダーの読みこみ（ISS02947 の 5。機能の管理 → **カレンダーの連携（ICS）**、初めは使わない）。自分のカレンダーの ICS の URL を作って Google カレンダーや Outlook で見られます（「全員」の予定は件名と場所、それ以外は「予定あり」だけ）。ほかのカレンダーの ICS の URL を、15 分ごとに自分のカレンダーに読みこめます。[カレンダー：ICS の書き出しとほかのカレンダー](#calendar-links-ja) を参照。

**0.4.7 で変わったこと**

- Exment 7.2.1 から、ファイルサーバーの名前が「**ファイル共有**」になったので、このプラグインの機能の管理の説明とプラグインの画面もそろえました。ほかは変わりません。

**0.4.6 で変わったこと**

- 空の Word の文書に文字を打てませんでした。0.4.4・0.4.5（と Exment のファイル共有の「新規作成」）の空の文書に段落が 1 つもなく、エディターがカーソルを置けなかったためです（文字数 0）。Word の新しい文書と同じく、空の段落を 1 つ入れました。前の空の文書のままのファイルは、直した空の文書として開き、保存すると直ります。

**0.4.5 で変わったこと**

- 機能の管理に、Office を 1 行で出しました（`exment_groupware.office`。前からプラグインを入れていたサイトは「使う」、新しいサイトは「使わない」から）。ibisOffice が入っていないあいだは、要るもの（PHP の zip の拡張・public/ に約 125MB・LibreOffice は不要）を書き、行にボタンを出せる Exment では「ibisOffice を入れる」を出します。
- Word・Excel・PowerPoint のファイルの画面で、システム管理者には、プレビューの欄に「ibisOffice を入れると、ここで見る・編集ができます」と入れる画面への案内を出します。ほかの人の画面は今までどおりです。
- 入れられないとき（zip の拡張がない・public/ に書けない・空きが足りない・GitHub につながらない）は、その理由と、ほかの場所に置いて URL を設定する方法を出します。

**0.4.4 で変わったこと**

- 空の Office のファイル（0 バイト。Windows の「新規作成 → Word 文書」で作ったもの）が、ibisOffice で表計算として開いていました。名前の拡張子（docx・xlsx・pptx）の種類の新しい文書として開き、最初の保存で本物の文書になるようにしました。
- 右上の閉じる（×）で、ファイルをチェックインしてエディターを離れます。「Office で開く」で開いたタブは閉じ、そうでなければ画面全体がファイルの画面に戻ります（エディターの枠の中に Exment が開くことはなくなりました）。ファイルの画面のプレビュー（読み取り専用）には閉じるを出しません。
- インストールのときに、ibisOffice の大きいファイルの `.gz` の複製と Apache 用の `.htaccess` を書きます。[Web サーバーの設定](#web-サーバーの設定)（nginx の設定も）を見てください。

## Chat

Rooms of a group or of two people (ISS02945). Off at first: Admin setting → Feature management → **Chat** (`exment_groupware.chat`). The menu **チャット** opens it (`/admin/plugins/exment_groupware/chat`).

- A group room has a name and members chosen by its maker (its owner, who adds and removes members); anyone can leave. Two people have one direct room.
- Messages: line breaks, `code`, **bold**, links; `@name` of a member is lit for them, `@全員` / `@all` in a group room. Ctrl+Enter sends.
- Files: attach or paste (at most 10, 25 MB each). They are opened only by the members of the room.
- Read counts ("既読 n") under one's own messages; unread counts in the list of rooms.
- New messages are fetched every 3 seconds while the page is seen, every 30 seconds while it is hidden, and not at all after 30 minutes of nothing done (until the page is used again). No new server is needed (Exment SaaS too).
- Only the members of a room read it; another room or another room's file answers "not found".
- The people who use it need the plugin's permission too: a role group with **Plugin access** to Exment Plugin Groupware (Admin setting → Role groups). Without it Exment answers 403.
- Notices: a direct message, and a message that names you (or `@全員`), ring the bell; mail or Teams too as you chose for ExmentPM's notices (「通知」 menu of ExmentPM), when ExmentPM is there. 「知らせを止める」 turns a room's notices off for you.
- A room's owner chooses how long it keeps its messages (30 days to 5 years, or always). Older ones, with their files, are removed by the plugin's batch (`php artisan exment:batch --name=ExmentGroupware --param1=chat`, every 5 minutes with Exment's scheduler) and written to the operation log. On a site without the scheduler, opening the chat does it once a day.
- **Calls** (ISS02946, Feature management → **通話**, off at first): 「通話」 in a room starts a call of up to 4 people with voice; anyone can turn on a camera or share a screen. The browsers connect to each other (WebRTC, encrypted); nothing is recorded or kept on the server but the signals of the connection. The plugin's setting 「通話のサーバー（STUN / TURN）」 lists the servers that help two PCs find each other: empty, only PCs that reach each other directly (the same LAN or VPN) can talk; a public STUN server makes most networks work (the browser then tells it its IP address); strict company networks need a TURN server of your own.
- A call from a calendar event: 「通話」 on an event (the calendar's) opens the event's own chat room, made the first time with its attendees, and joins the call going on there or starts one. Only attendees who may see the event.
- Coming: a live connection (WebSocket). See [docs/chat-design.md](docs/chat-design.md).

## できること

Exment のグループウェア（ex1task ISS00233）です。今あるのは次の 2 つです。

- **Web メール**（0.2.0、P2）: Outlook・Gmail のような、ブラウザーのメールソフト。[Web メール](#web-mail-ja)を参照。
- **Office**（0.1.0、P3）: Exment の**ファイル共有**にある Word（.docx）・Excel（.xlsx）・PowerPoint（.pptx）のファイルを、株式会社アイビスのブラウザーで動く Office、[ibisOffice](https://github.com/ibisjp/ibis-office) で開きます。

- そのファイルの画面に **Office で開く** ボタンが出ます。押すと、新しいタブでエディターが開きます。
- サーバーに LibreOffice がない（PDF のプレビューを作れない）ときは、ファイルの画面のプレビューの欄に、エディターを**読み取り専用**で埋め込みます（0.2.2）。チェックアウトはしません。
- エディターで**保存**すると、ファイルの新しい版になります（前の版はファイル共有の版の履歴に残ります）。
- 編集のために開いている間は**チェックアウト**（編集中）になり、ほかの人は変更できません（ibisOffice にはまだ同時編集がないため）。エディターを閉じるとチェックインします。見るだけの権限の人や、ほかの人がチェックアウトしているファイルは、読み取り専用で開きます。
- 古い版から保存しようとしたとき（間にほかの人が保存したとき）は断るので、上書きで消えることはありません。
- **データの添付**（0.3.0、ISS00416）: データに添付した Word・Excel・PowerPoint を、ほかのプラグインの画面から読み取り専用で表示できます（`view/{uuid}`。Exment Plugin PM の課題・Wiki・プロジェクトのファイルで使います）。ファイルはいつものダウンロードの URL から読むので、ダウンロードできる人だけが見られます。ここはファイル共有がなくても動きます。
- **データの添付の編集**（0.4.0、ISS00416）: そのデータを編集できる人には、その画面に **編集する** が出ます（`edit/{uuid}`）。**保存**すると添付の中身が新しくなり、アドレスは変わらないので、そこへのリンクもそのまま使えます。前の中身は同じデータの別のファイルとして残し（添付の一覧には出ません）、データに「誰が編集したか」と前のファイルへのリンクをコメントで残します。開いたあとにほかの人が保存していたときは、保存を断ります。
  編集できるのは添付だけです（ファイルの列の値は対象外）。そのデータを編集できる人だけが編集でき、`exment.file_delete_useronly` が on のときは、ファイルを上げた人だけです。保存できる大きさは 50MB まで（Exment のアップロードの上限のほうが小さければ、そちら）です。
  気をつけること: **Exment Plugin PM のアーカイブ中のプロジェクトでも、ここからの編集は止まりません**。また、添付の古い版の仕組みがない Exment では、**添付を消しても、編集で残した前の中身は消えません**。データを見られる人は、コメントのリンクから読めたままです。仕組みがある Exment（添付の一覧の「差し替え」、exceedone/exment PR #1866）では、前の中身は添付の古い版（「版 n」、`exment.document_version_limit` まで）にもなり、添付を消すと一緒に消えます。
- **メニュー「メール」**（0.4.2）: プラグインを入れたとき（0.4.2 に上げたときも）、Web メール（`plugins/exment_groupware/mail`）へのメニュー「メール」を 1 つ作ります。機能の管理でメールを「使わない」にしたときは、ページと同じくメニューも隠れます。メールへのメニューがもうあるサイトには作らず、管理者が消したメニューは戻りません。
- **機能の管理**（0.4.1）: 「機能の管理」（管理者設定）のある Exment では、Web メールが 1 つの機能（`exment_groupware.mail`）になり、管理者が使う・使わないと、使える役割グループを決めます。前からこのプラグインを入れているサイトでは「使う」のまま、新しいサイトでははじめ「使わない」です。Office はファイル共有の機能に従います。機能の管理のない Exment では、今までと同じです。

## 必要なもの

- Exment 7.1 以上（7.1・7.2 で確かめています）と PHP 8.2。Web メールとデータの添付は、どれでも動きます。
- **ファイル共有**のファイルを Office で開くには、ファイル共有のある Exment（7.2.1 以降）。ないときは、その部分だけが何もせず、ほかは動きます。
- Exment 7.2.1 以降では、Web メールは「機能の管理」の 1 つの機能になり（上の「機能の管理」）、添付を編集したときの前の中身は、その添付の前の版になります（exceedone/exment PR #1866）。
- PHP の `zip` の拡張（ibisOffice のインストールに使います）。
- WebGPU のあるブラウザー: Chrome・Edge、または Safari 26 以降。サイトは HTTPS（開発中は `http://localhost` でも動きます）。

## インストール

1. **管理者設定 → プラグイン** で `ExmentGroupware-<version>.zip` をアップロードします。
2. システム管理者でプラグインの画面（`/admin/plugins/exment_groupware`）を開き、**ibisOffice 0.8.9 をインストールする** を押します。GitHub のリリース（約 40 MB、展開すると 125 MB）をダウンロードし、SHA-256（`IbisOffice.php` に固定）を確かめてから、サイトの `public/vendor/exment_groupware/ibisoffice/0.8.9/` に置きます。Exment と同じオリジンからエディターを出すためです。

Web サーバーが複数台のときや、`public/` に書き込めないときは、展開したリリースをほかの場所で公開し、プラグインの設定の **ibisOffice の場所（URL）** に `index.html` のあるフォルダーの URL を入れます（例: `https://office.example.com/ibisoffice/0.8.9/`）。

### Web サーバーの設定

エディターは大きいので（`.wasm` が 92 MB、gzip で約 24 MB）、圧縮して送り、ブラウザーに残してもらうと、ダウンロードは最初の 1 回だけになります。そうしないと、開くたびに待ちます。

- **Apache**（XServer などのレンタルサーバーも）: 何もしなくてかまいません。0.4.4 から、インストールのときに大きいファイルの `.gz` の複製と、`public/vendor/exment_groupware/ibisoffice/` の `.htaccess` を書きます。`.wasm` の種類・1 年のキャッシュ・gzip を受け取れるブラウザーへの `.gz` の複製（`mod_rewrite`・`mod_headers`・`mod_mime`。複製がないときは `mod_deflate`）。どれも `IfModule` の中なので、ないモジュールは飛ばされます。そこで `.htaccess` が使える設定（`AllowOverride All`。Exment 自身の `.htaccess` にも要ります）が必要です。
- **nginx** は `.htaccess` を読みません。サイトの設定に次を足します。

```nginx
# inside the server { } of the site, before "location /"
location ^~ /vendor/exment_groupware/ibisoffice/ {
    gzip_static on;            # serve the .gz copies the installer wrote
    gzip on;                   # and compress anything without one
    gzip_types application/wasm text/javascript application/javascript text/css application/json image/svg+xml;
    types { application/wasm wasm; }
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable" always;
    # add_header here drops the ones of the server { }: repeat them
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    try_files $uri =404;
}
```

- **X-Frame-Options は `DENY` ではなく `SAMEORIGIN` にします。** エディターは同じサイトの `iframe` なので、`DENY` だとブラウザーが表示を断ります（画面が空のまま）。Content-Security-Policy の `frame-ancestors` も `'self'` にします。
- COOP・COEP のヘッダーは要りません。Content-Security-Policy を付けているサイトは `script-src 'self' 'wasm-unsafe-eval'` が要ります。
- Docker なしのレンタルサーバーでも使えます。ibisOffice はブラウザーの中で動き、サーバーはファイルを渡すだけです。LibreOffice は要りません。

## ibisOffice のライセンス

ibisOffice は株式会社アイビスのソフトウェアで、**AGPL-3.0-only** と商用ライセンスのデュアルライセンスです。このプラグインは:

- ibisOffice を**リリースのまま**（改変せず）使い、プラグインには含めません（アイビスのリリースからダウンロードします）。
- **別の iframe** で動かし、ページからは保存・リンクを開くなどの関数だけを渡します（ibisOffice の組み込みの説明のとおり）。
- エディターの画面とプラグインの画面に、https://github.com/ibisjp/ibis-office へのリンク付きで **「Powered by ibisOffice」** を表示します（[商標の決まり](https://github.com/ibisjp/ibis-office/blob/main/docs/TRADEMARK.md)で必須）。

Exment SaaS で提供する前に、その使い方が AGPL の条件で足りるかをアイビスに確認してください（ISS00233 を参照）。

### Office に画像を貼り付ける

このプラグインが入れるブラウザー版の ibisOffice（0.8.9）では、**画像（スクリーンショット・ほかのアプリでコピーした画像）を Ctrl+V で貼り付けても何も起きません**（表計算・文書・スライドのどれでも）。文字やセルの貼り付けは今までどおりできます。プラグインの画面は貼り付けを止めていません。貼り付けは ibisOffice に届いていて、ブラウザー版の ibisOffice が今は貼り付けの文字だけを受け取るためです。プラグインの画面から ibisOffice に画像を渡す方法も（組み込みの仕組みに）ありません。

ibisOffice が画像を受け取れるようになるまでは、画像をファイルに保存してパソコンのアプリで入れるか、画像をレコードやフォルダーにファイルとして添付してください。

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

## チャット

グループや 2 人のルームで話します（ISS02945）。初めは使いません：管理者設定 → 機能の管理 → **チャット**（`exment_groupware.chat`）。メニューの **チャット** で開きます。

- グループのルームは、作った人（持ち主）が名前とメンバーを決めます（足す・外す）。だれでも出られます。2 人の間のルームは 1 つです。
- メッセージ：改行・`code`・**太字**・リンク。メンバーの `@名前` はその人に目立って見え、グループでは `@全員` も使えます。Ctrl+Enter で送ります。
- ファイル：付ける・貼りつける（10 個まで、1 つ 25 MB まで）。開けるのはそのルームのメンバーだけです。
- 自分のメッセージの下に「既読 n」、ルームの一覧に未読の数。
- 新しいメッセージは、ページを見ている間は 3 秒ごと、ほかのタブのときは 30 秒ごとに確かめ、30 分なにもしないと止めます（ページを動かすとまた確かめます）。新しいサーバーは要りません（Exment SaaS でも）。
- ルームはメンバーだけが読めます。ほかのルームやそのファイルは「見つからない」になります。
- 使う人には、プラグインの権限も要ります：Exment Plugin Groupware の「プラグインへのアクセス」のある役割グループ（管理者設定 → 役割グループ）。ないと Exment が 403 にします。
- 知らせ：1 対 1 のメッセージと、自分の名前（または `@全員`）のあるメッセージで、ベルが鳴ります。ExmentPM があれば、その「通知」で選んだとおりメールや Teams にも。「知らせを止める」でそのルームの知らせを止められます。
- ルームの持ち主は、メッセージを残す期間（30 日〜5 年、またはずっと）を決められます。古いものはファイルごと、プラグインのバッチ（`php artisan exment:batch --name=ExmentGroupware --param1=chat`、Exment のスケジューラーで 5 分ごと）が消し、操作ログに残ります。スケジューラーのないサイトでは、チャットを開いたときに 1 日 1 回消します。
- **通話**（ISS02946、機能の管理 → **通話**、初めは使わない）：ルームの「通話」で 4 人までの声の通話を始めます。だれでもカメラを使ったり、画面を共有したりできます。ブラウザーどうしで直接つなぎ（WebRTC、暗号化）、録音はしません。サーバーに残るのは、つなぐための合図だけです。プラグインの設定「通話のサーバー（STUN / TURN）」：空なら、直接つながる PC どうし（同じ LAN・VPN）だけ話せます。公開の STUN を入れると多くのネットでつながります（ブラウザーはそのサーバーに IP アドレスを知らせます）。きびしい会社のネットには、自社の TURN が要ります。
- カレンダーの予定から通話：予定の「通話」で、その予定のチャットのルーム（はじめてのときに出席者で作る）を開き、そこで続いている通話に入るか、新しく始めます。予定を見られる出席者だけです。
- これから：すぐ届くつなぎ（WebSocket）。[docs/chat-design.md](docs/chat-design.md)。

<a id="calendar-links-ja"></a>

## カレンダー：ICS の書き出しとほかのカレンダー

カレンダーの 5 つめ（ISS02947）です。初めは使いません：管理者設定 → 機能の管理 → **カレンダーの連携（ICS）**（`exment_groupware.calendar_links`。カレンダーが要ります）。使える人には、カレンダーのツールバーに **連携** が出ます。

**ICS の書き出しの URL**：カレンダーの「連携」の画面の **URL を作る** で `https://<サイト>/gw-calendar/ics/<トークン>.ics` を作ります。カレンダーのソフト（Google カレンダーの「URL で追加」・Outlook・iPhone）が、ログインなしで読みます。トークンはランダムな 16 進 64 文字で、残すのはその SHA-256 だけです。なので URL は、作ったときに 1 度だけ出ます。URL があるときは **新しい URL を作る** で新しい URL になり、前の URL は止まります。**止める** で止まります。止めた URL・ない URL・消されたユーザーや機能を使えなくなった人の URL は 404 です。1 つの URL は 1 分に 30 回、1 つの IP からは 1 分に 120 回まで読めます。

- その人の予定と、その人が参加する予定を、30 日前から 365 日先まで出します。
- **だれに何が見えるか**：ログインしていない人がその人のカレンダーを見たときと同じです（画面と同じ決まり、Visibility）。「全員」の予定は件名と場所が出ます。それ以外（部署・参加者だけ・予定ありだけ・「予定あり」と見せるほかのカレンダー）は時間だけの VEVENT です：SUMMARY は「予定あり」、CLASS:PRIVATE、予定の ID も場所もありません。メモと参加者は、どの予定でも出ません。
- **くりかえしは展開します**：期間の中の 1 回ずつが 1 つの VEVENT です（RRULE・EXDATE・RECURRENCE-ID は書きません）。なので、動かした回・やめた回も、カレンダーの画面と同じに出ます。時刻は UTC、終日の予定は日付（VALUE=DATE、終わりは最後の日の次の日）です。文字はエスケープし、行は 75 オクテットで折り返します（RFC 5545）。

**ほかのカレンダー（ICS の URL）**：**カレンダーを足す** で、名前・ICS の URL（`https://`・`http://`・`webcal://`）・ほかの人への見せ方（「予定あり」とだけ（はじめ）か、件名と場所も）を入れます。1 人 10 個までです。

- URL は `APP_KEY` で暗号化し、二度と表示しません。画面に出るのはホスト名だけです。変えるときは新しい URL を入れます。
- 15 分ごとに、リンクごとに少しずつずらして読みます。プラグインのバッチ（`php artisan exment:batch --name=ExmentGroupware --param1=calendar`）と、カレンダーの画面の答えのあと（ページを送ってから、3 つまで）です。**今すぐ読む** ですぐ読みます（1 分に 1 回）。
- 読み方：GET で `If-None-Match`・`If-Modified-Since` を付けます（304 なら何も変えません）。15 秒（転送もふくめて全部で）・5 MB まで、転送は 4 回まで。30 日前から 180 日先までの VEVENT を、RRULE（カレンダーが読める範囲。ほかの決まりは 1 回目だけ）・EXDATE・RDATE・RECURRENCE-ID（動かした回・やめた回）・終日・TZID（IANA の名前、よくある Windows の名前、VTIMEZONE の X-LIC-LOCATION。わからなければサイトのタイムゾーン）・STATUS:CANCELLED ごと読みます。あちらで消えた予定はこちらでも消えます。1 つのカレンダーで 5000 件までです。COUNT が 1000 より大きいときは 1000 にします（1000 回目でおわり）。COUNT は最後の日の UNTIL として残します。読むのに 10 秒より長くかかるカレンダーは読みこみません（前の予定のまま）。消されたユーザーや機能を使えなくなった人のリンクは読みません（1 日あとにまたたしかめます）。
- 読んだ予定はその人のもの（source=link）で、ここでは変えられません。ほかの人には、リンクの見せ方のとおりに見えます。
- 読めなかったときは、そのリンクに残し（URL ではなく、ことばで出ます）、次はもっと待ってから読みます（4 時間まで）。読めないカレンダーで、画面がエラーになることはありません。
- **URL のチェック**：Web メールと同じ HostGuard です。インターネットのアドレスだけで、ポートはいつも 80・443・8080・8443 番だけです。転送のたびにもう一度たしかめ、たしかめたアドレスにつなぎます（名前を引きなおさず、curl がつないだアドレスもたしかめます）。プラグインの設定 **社内のネットワークのカレンダー（ICS の URL）を許可する**（`calendar_allow_private`、初めはオフ。メールの設定とは別）をオンにすると、社内のネットワーク（10/8・172.16/12・192.168/16・100.64/10・fc00::/7）も使えます。ループバック・リンクローカル（169.254/16・fe80::/10。クラウドのメタデータ）・0/8・マルチキャスト・IPv4 を中に持つ IPv6（NAT64・Teredo・6to4）は、どのときも使えません。

テーブル `gw_cal_links`・`gw_cal_feeds` は zip をアップロードしたときに作られます（`database/migrations`）。

**気をつけること**

- **プロキシは使いません**：ほかのカレンダーは、HTTP のプロキシを通さずに直接読みます（たしかめたアドレスにそのままつなぐため。プロキシを通すと、そのチェックを回り道できてしまいます）。インターネットにプロキシを通してしか出られないサイトでは、ICS の URL を読めません。ICS の書き出しの URL には関係ありません。
- **セッションなし・IP フィルターなし**：書き出しの URL はセッションを使いません（カレンダーのソフトはクッキーを持たないため）。Exment のメンテナンスモードには、Exment の公開のページと同じように従います（Exment SaaS では、テナントの期限にも）。管理画面の Web の IP フィルター（管理者設定 → IP フィルター）はかかりません。Google や Microsoft のサーバーが外から読むためです。URL を知っている人はだれでも読めるので、人に教えず、もれたときは作りなおしてください。
- **1 つの期間にたくさんの予定**：カレンダーの画面（と書き出し）は、期間のくりかえしの予定と 1 回だけの予定を別々に、それぞれ 5000 件まで読みます。1 つの期間に 1 回だけの予定がそれより多いとき（ほかのカレンダーがたくさんあるときなど）は、早いほうから 5000 件を出します。1 つの期間に出すのは 20000 回までです。その人自身の予定が先で、残りをほかのカレンダーで同じだけ分けます（小さいほうから。使わなかった分は次のカレンダーへ）。くりかえしの多いカレンダーが 1 つあっても、自分の予定やほかのカレンダーの予定はおし出されません（ログに警告を残します）。
- **おそいカレンダーのサーバー**：カレンダーの画面の答えのあとに読むリンクは、読み終わるまでその PHP のワーカー（PHP-FPM）を使います。15 秒まで（大きなカレンダーではさらに 10 秒まで）、サイトで 1 分に 1 回までです。画面はもう送ってあります。
- **ルートのキャッシュ**：ICS の書き出しの URL は、Exment が起動するときにプラグインが足すルートです（ExmentITSupport のログインなしのページと同じ）。Laravel のルートをキャッシュしている（`php artisan route:cache`）と、この URL はなく 404 になります。使うサイトではルートをキャッシュしないでください（`php artisan route:clear` で元に戻ります）。

これから：自分のカレンダーを詳しいまま書き出す URL（本人だけの別のトークン。のちほど）、Google・Microsoft のカレンダーを読む（6）、CalDAV（7）、両方向（8）。
