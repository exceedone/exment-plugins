# Exment Plugin Project Management

Project and ticket management for Exment: a portal with issues, board, Gantt chart, wiki and files, built on Exment's own tables.
Exment のプロジェクト管理・チケット管理のプラグインです。課題・ボード・ガントチャート・Wiki・ファイルをまとめたポータルを、Exment のテーブルの上に作ります。

| Folder | Plugin | Version | Type |
|---|---|---|---|
| [`ExmentPM/`](ExmentPM/) | Exment Plugin PM（プロジェクト管理） | 1.7.17 | page, validator, event, batch |

| Name / 名前 | Value / 値 |
|---|---|
| Name in the plugin list / プラグインの一覧に出る名前 | Exment Plugin PM（プロジェクト管理） |
| Plugin name (`plugin_name`), folder, zip / 内部の名前・フォルダ・zip | `ExmentPM`（1.2.0 までは `PmPortal`、1.3.0 は `ExmentPluginPM`） |
| PHP namespace | `App\Plugins\ExmentPM` |
| Address / アドレス | `/admin/plugins/exment_pm`（1.3.0 までは `/admin/plugins/pm_portal`） |
| Screen title and menu / 画面の見出しとメニュー | プロジェクト管理 |

**Unreleased / 未リリース**

- More of Markdown in issue bodies, comments and wiki pages: lists inside list items (by indent), `+` bullets, numbered lists that start at another number (`3.`, `1)`), quotes of several lines and quotes inside quotes, `__bold__` / `_italic_` / `***both***` (not inside words such as snake_case), link titles (`[text](url "title")`), `<https://...>`, table column alignment (`:---:`), backslash escapes (`\*`, `\#` ...), and the language of a code fence (```` ```js ````) is no longer shown as its first line. Everything typed is still escaped first: HTML and scripts stay text, and every link opens in a new tab with `rel="noopener"`.
  課題の本文・コメント・Wiki の Markdown で書けることを増やしました：項目の中のリスト（字下げ）、`+` の箇条書き、ほかの番号から始まる番号つきリスト（`3.`・`1)`）、何行もの引用と引用の中の引用、`__太字__`・`_斜体_`・`***両方***`（snake_case のような語の中ではそのまま）、リンクのタイトル（`[文字](URL "タイトル")`）、`<https://...>`、表の列のそろえ（`:---:`）、バックスラッシュでの打ち消し（`\*`・`\#` など）。コードの囲み（```` ```js ````）の言語名は、コードの 1 行目に出なくなりました。書いたものは今までどおり先にエスケープします（HTML・スクリプトは文字のまま、リンクは新しいタブで `rel="noopener"`）。

**Changed in 1.7.18 / 1.7.18 で変わったこと**

- A viewer of a project (somebody who may not add an issue there) no longer gets an error in the browser console each time the project page opens: the form to add an issue was fetched ahead to open faster, and refused. It is no longer fetched for somebody who may not add an issue. The label 「子の進捗」 of the second progress number of a parent issue (list and issue detail) was missing; it is shown again. The Files tab of a project with no workspace of the file server yet (or one the user may not open) showed only a folder icon; it shows its message again ("no workspace yet" and who makes one, or "no permission").
  プロジェクトを見るだけの人（課題を追加できない人）がプロジェクトの画面を開くたびに、ブラウザーの console にエラーが出ていました。課題の追加のフォームを先に読み込もうとして、断られていたためです。課題を追加できない人には、先に読み込まないようにしました。子課題がある課題の 2 つめの進捗の数字に、見出し「子の進捗」が出ていなかったのを直しました（一覧と課題の詳しい画面）。ファイルサーバーのワークスペースがまだないプロジェクト（または開く権限がないとき）の「ファイル」タブに、フォルダーのアイコンしか出ていなかったのを直し、メッセージ（まだワークスペースがないこと・だれが作るか、または権限がないこと）を出すようにしました。

- Issues nest up to five levels (an issue, its child, the child of that one ...), no longer one level of sub-tasks. How many is a setting of the plugin (plugin management → ExmentPM → 課題 → 課題の階層の数, 2 to 10, 5 by default). A parent is refused only when it would make a loop (an issue under one of its own) or put an issue - or the deepest one under it - deeper than the levels; the message says which. An issue with children may now be moved under another issue in the same project and its children go with it, as long as they still fit; moving an issue with children to another project stays refused. The parent picker offers only the issues that would be accepted. Lowering the setting leaves the issues already deeper as they are; only a new parent is checked. The list's tree shows every level (indent, fold), and an issue with children shows "子の進捗 N%" next to its own progress: the average of the issues under it that have no children of their own, a done one counting 100%. It is shown only, never saved. The gantt shows the deeper levels with Exment 7.2.6 (before, the issues under a child are listed after the others, as until now). The board is unchanged (its cards show the parent issue). The json endpoints, the Web API and the Backlog sync are checked by the same rule.
  課題を 5 段まで重ねられるようになりました（課題・その子・さらにその子…）。これまでは子課題の 1 段だけでした。何段までかは、プラグインの設定で変えられます（プラグイン管理 → ExmentPM → 課題 → 課題の階層の数、2〜10、初期値 5）。親にできないのは、親子が循環するとき（自分の下にある課題の下に置く）と、その課題（または下にある一番深い課題）が段の数をこえるときだけです。メッセージで、どちらかを伝えます。子課題がある課題も、同じプロジェクトの別の課題の下に移せるようになりました。子課題もいっしょに移ります（段の数に収まるときだけ）。子課題がある課題を別のプロジェクトに移すのは、今までどおりできません。親課題を選ぶ画面には、親にできる課題だけが出ます。設定の段の数を減らしても、今ある深い課題はそのままです（新しく親を付けるときだけ確かめます）。一覧のツリーはすべての段を出し（字下げ・開く／閉じる）、子課題がある課題には、自分の進捗率のとなりに「子の進捗 N%」を出します。下にある、子のない課題の進捗率の平均です（完了の状態は 100%）。表示だけで、保存はしません。ガントチャートは、Exment 7.2.6 から深い段も字下げして出します（それより前の Exment では、孫課題からあとは今までどおり最後にまとめて出ます）。ボードは変わりません（カードに親課題が出ます）。json のエンドポイント・Web API・Backlog の同期も、同じ決まりで確かめます。
- The notices of an issue by mail and by Teams (ISS02863). A new issue, a change and a comment already ring the bells; each person can now also get them **by mail, by Teams, or both** - their own choice in the new **通知** menu of the portal's top bar, or the default of the plugin setting **受け取り方の初めの値** (bell only at first, as before). The mail and the Teams card look the same: the title with the issue key in bold (課題が更新されました（KEY）), the link to the issue, the subject in bold, what changed as a numbered list. The mail is HTML (with a text part) or text, as Exment's mail setting says. A writer's "メールでも知らせる" still mails every person of the bells, in the same layout. Teams needs Exment 7.2.6 or later with **Teams notifications (personal chat)** on in Feature management and the address of the Teams workflow in the system setting; on an older Exment only mail is offered.
  課題の通知を、ひとりひとりがメール・Teams・両方で受け取れるようにしました（ポータルの上の「通知」、初めの値はプラグインの設定）。メールと Teams は同じ形です（見出しに課題のキー・リンク・件名・変わったことの番号つきのリスト）。Teams には Exment 7.2.6 以降の「Teams の通知（個人のチャット）」が要ります。
- An issue opened from the list no longer comes back bold (unread) when going back to the list: a refresh of the list that was fetched before the "read" mark reached the server put the old mark back until the page was reloaded. What the reader just marked (opened, or marked read / unread by hand) stays for a minute over a refresh; after that the server's answer counts again.
  一覧から課題を開いたあと、一覧に戻ると太字（未読）のままになることがありました。「読んだ」の印がサーバーに届く前に取った一覧の更新が、古い印に戻していたためです（ページを読み直すと直っていました）。自分が付けた印（開いた・手で既読／未読にした）は、1 分のあいだ、一覧の更新があってもそのまま残します。そのあとは、サーバーの答えに従います。
- Issues marked read (opened, or by the right-click menu) no longer come back unread after the page is reloaded. The mark waited in the queue for background work, behind the prefetches, and was dropped when the page was reloaded or left before its turn. It now goes right after the click's own requests, the marks of one right-click go in one request, and a mark not yet answered when the page goes away is sent with the browser's beacon. New route `POST {id}/reads` (several issues at once).
  既読にした課題（開いた・右クリックで既読にした）が、ページを読み直すと未読に戻ることがありました。既読の印が、先読みなどの後回しの処理のうしろで順番を待っていて、その前にページを読み直したり移ったりすると送られずに消えていたためです。クリックした操作の通信のすぐあとに送るようにし、右クリックでまとめて既読・未読にしたときは 1 回で送ります。送り終わる前にページを離れたときは、ブラウザーの beacon で送ります。
- The Files tab can show the project's workspace of Exment's file server (folders, upload, share links, versions, search, copy link, open in Office) instead of only the list of attachments. It is a feature of Feature management, "プロジェクト管理のファイル（ファイルサーバー）" (`exment_pm.files`), off until a system administrator turns it on; it needs the file server (Exment 7.2.0 or later, the feature `file_server`). The project's manager makes a workspace for the project in the project settings (ファイルサーバー; on a site where only system administrators make workspaces, an administrator makes one and gives the manager the manage right, and the manager picks it) or picks one they manage. One workspace per project. Its top permissions follow the project: the manager manages, the members and member organizations edit, the viewers and viewer organizations view; they are set again each time the project's people change (and with the button 権限をプロジェクトに合わせ直す). A folder given its own permissions in the file server keeps them. The tab shows the file server's own pages in a frame; ファイルサーバーで開く opens it full size. The attachments of the issues, the wiki and the project stay listed under it and are not moved. Unlinking leaves the workspace and its files. New table `pm_portal_file_workspaces` (a migration of the plugin).
  「ファイル」タブで、Exment のファイルサーバーのワークスペース（フォルダー・アップロード・共有リンク・版・検索・リンクのコピー・Office で開く）を使えるようになりました。機能の管理の「プロジェクト管理のファイル（ファイルサーバー）」（`exment_pm.files`）で、システム管理者がオンにするまでは使いません。ファイルサーバー（Exment 7.2.0 以降の機能 `file_server`）を使うことが条件です。プロジェクトの管理者が、プロジェクトの設定の「ファイルサーバー」で、プロジェクトのワークスペースを作るか、自分が管理しているものを選びます（ワークスペースを作れるのがシステム管理者だけのサイトでは、システム管理者が作って管理者に管理の権限を付け、管理者が選びます）。1 つのプロジェクトに 1 つです。いちばん上の権限は、プロジェクトに合わせます：管理者は管理、メンバーとメンバー組織は編集、閲覧者と参照組織は閲覧。プロジェクトの人が変わるたびに付け直します（「権限をプロジェクトに合わせ直す」ボタンでも）。ファイルサーバーでフォルダーに個別に付けた権限は、そのままです。タブには、ファイルサーバーの画面をそのまま出します（「ファイルサーバーで開く」で大きく開けます）。課題・Wiki・プロジェクトの添付ファイルは、その下に今までどおり出し、移しません。結び付けを外しても、ワークスペースとファイルは消えません。プラグインのテーブル `pm_portal_file_workspaces` を足します（プラグインのマイグレーション）。
- The manual: a link 「マニュアル」 at the right of the top bar opens the manual of the plugin on exment.net in the language of the user (Japanese, Vietnamese, else English) in a new tab, and Exment's "?" in the header opens it on the portal's pages (Exment 7.2.4 and later).
  上のバーの右に「マニュアル」を足しました。ユーザーの言語（日本語・ベトナム語、ほかは英語）で、exment.net のこのプラグインのマニュアルを新しいタブで開きます。Exment 7.2.4 以降では、画面の右上の「？」も、ポータルの画面ではこのマニュアルを開きます。

**Changed in 1.7.17 / 1.7.17 で変わったこと**

- Whoever holds a writing role on a project - the manager, a member, or a member through an organization (`member_orgs`) - can now edit every issue of that project in the portal: subject, description, status, progress, attachments, bulk edits. Until now only the person who opened an issue and its assignees could; every other member could read it and nothing more, because Exment hands the right to write an issue to the people its share columns name, one issue at a time (wiki pages already follow their project the same way). **This widens who can edit.** A viewer still only reads, somebody who is not on the project still cannot open the issue, an archived project stays read-only, and a workflow lock still refuses. The role group must allow editing issues at all (`pm_member_group` does). Exment's own data screen (`/admin/data/pm_issue/…`) and the Web API keep Exment's own rule: the opener and the assignees.
  プロジェクトで書く役割を持つ人（管理者・メンバー・組織で入っているメンバー `member_orgs`）は、ポータルで、そのプロジェクトのどの課題も編集できるようになりました（件名・詳細・状態・進み具合・添付・まとめて編集）。これまでは、課題を作った人と担当者だけが編集でき、ほかのメンバーは読むだけでした。Exment は、課題を書く権利を、共有の列に名前がある人に 1 件ずつ渡すためです（Wiki のページは、もう同じようにプロジェクトに従っています）。**編集できる人が広がる変更です。** 閲覧者は今までどおり読むだけ、プロジェクトに入っていない人は課題を開けません。アーカイブしたプロジェクトは読むだけ、ワークフローでロックされた課題は編集できません。役割グループが課題の編集を許していること（`pm_member_group` は許しています）が条件です。Exment 本体のデータ画面（`/admin/data/pm_issue/…`）と Web API は、本体の決まりのまま（作った人と担当者）です。
- Times are shown in the time zone each user chose in Exment (Exment 7.2.4, `UserTimezone`): the updated time of the list, the timeline, comments and the activity feed of an issue, the dashboard's feed and its day headers, the created / updated line of the form, the message of a save refused as stale, the Git request time and the team dates of the settings. "Today" - what a due date is late or soon against, on the project and on the dashboard - and the clock a due time is compared with are the user's day and clock. A date and time column added to the issues opens in the form in the user's time zone and is saved back in the site's. What is stored does not change, and the version a save quotes (`updated_at`) stays the stored time. A date alone (start, due) and a time alone (due time) are never converted. The burndown chart and the archive of finished issues keep counting the days of the site. For a user in the time zone of the site, and on an Exment before 7.2.4, every screen is exactly as before.
  時刻を、ユーザーが Exment で選んだタイムゾーンで出します（Exment 7.2.4 の `UserTimezone`）。対象は、一覧の更新日時、課題の履歴・コメント・最近の動き、ダッシュボードの動きとその日付の見出し、フォームの「作成・更新」の行、保存がぶつかったときのメッセージ、Git の申し込みの時刻、設定のチームの日付です。「今日」（期限が過ぎた・もうすぐ、の基準。プロジェクトとダッシュボード）と、期限の時刻とくらべる時計は、ユーザーの日付と時計になります。課題に足した「日付と時刻」の列は、フォームではユーザーのタイムゾーンで開き、保存するときにサイトのタイムゾーンに戻します。保存される値は変わりません。保存のときに送る版（`updated_at`）も、保存された時刻のままです。日付だけ（開始日・期限日）と時刻だけ（期限の時刻）は変えません。バーンダウンと、終わった課題のアーカイブは、今までどおりサイトの日付で数えます。サイトと同じタイムゾーンのユーザーと、7.2.4 より前の Exment では、どの画面も今までと同じです。

**Changed in 1.7.16 / 1.7.16 で変わったこと**

- Fixed: after the project's data was read again in place (a save in the issue form, the refresh button, a change made by somebody else or through the API), the issue list kept its old rows - a subject changed in the form still showed the old text after 「課題一覧へ」 - and the screen stopped following later changes until the page was reloaded. The refreshed data is packed like the rows the page comes with (1.7.5), and only the page's own rows were unpacked. Now both are. A save in the form also puts the saved values (subject, status, type, assignees, dates …) into the list row at once, from the answer of the save, so the list is right even when the form is left before the data is read again. No extra request, and the list opens as fast as before (2,400 issues: first rows in about 2.3 s, back to the list in about 0.1 s, before and after).
  直し：プロジェクトのデータをその場で読み直したあと（課題のフォームで保存・更新ボタン・ほかの人や API による変更）、課題一覧が古い行のままになっていました。フォームで件名を変えて「課題一覧へ」を押しても、古い件名が出ていました。そのあとの変更も、ページを読み込み直すまで画面に出ませんでした。読み直したデータは、ページに入っている行と同じように詰めて送られます（1.7.5）が、元に戻していたのはページの行だけでした。両方とも戻すようにしました。フォームで保存したときは、保存の返事にある値（件名・状態・種別・担当者・日付など）を、その場で一覧の行に入れます。データを読み直す前にフォームを閉じても、一覧は正しく出ます。リクエストは増えず、一覧を開く速さは前と同じです（課題 2,400 件：最初の行が出るまで約 2.3 秒、一覧に戻るのは約 0.1 秒。直す前も後も同じ）。
- The portal's script is split by feature into `ExmentPM/resources/js/src/` (list, issue detail, wiki, files, forms, routing, embedded board and Gantt …) and built into one file, `resources/js/script.js`, with `node project-management/tools/build-pm-script.js`. The built code is the same as before, so nothing changes on screen; it is still copied to `public/exment-pm/` and versioned by content (1.7.12). CI fails when `script.js` is not the build of the sources, or when `node --check` finds a syntax error in any of them.
  ポータルのスクリプトを、機能ごとのファイル（`ExmentPM/resources/js/src/`：一覧・課題の詳細・Wiki・ファイル・フォーム・画面の切り替え・ボードとガントの埋め込みなど）に分け、`node project-management/tools/build-pm-script.js` で 1 つのファイル `resources/js/script.js` にまとめるようにしました。まとめた中身は前と同じなので、画面は変わりません。これまでどおり `public/exment-pm/` に置いて読みます（1.7.12）。まとめたファイルが元のファイルと合わないときと、どれかのファイルに文法のまちがいがあるとき（`node --check`）は、CI が失敗します。
- The Kanban view 「カンバン（状態別）」 of a new install shows 10 cards per status column (was 30); "load more" brings the rest. A board with many statuses (one set per project) built about 30 × the columns cards and took seconds. An install already set up keeps its own setting.
  新しく入れたときのカンバン「カンバン（状態別）」は、1 列に 10 枚（前は 30 枚）。残りは「もっと見る」で出ます。状態が多い（プロジェクトごとにある）と、30 × 列の数のカードを作って数秒かかっていました。セットアップ済みのサイトの設定はそのままです。
- The avatar of a user without an image (initials and colour) comes from Exment's `InitialsAvatar` when the core has it (Exment 7.2.1), so a person looks the same in Exment and in the portal; an older core uses the plugin's own copy, the same rules.
  画像のないユーザーのアバター（頭文字と色）は、本体に `InitialsAvatar` があればそれを使います（Exment 7.2.1）。本体とポータルで同じ人は同じ見た目です。古い本体では、プラグインの中の同じ決まりで描きます。

**Changed in 1.7.15 / 1.7.15 で変わったこと**

- Before 初期セットアップ, the left menu has the entry 「プロジェクト管理」 (in the group 「プロジェクト」 with Exment 7.2.1) for the system administrators; it opens the setup. Others see nothing until the setup, which keeps one entry. A site already set up does not change. `project-management/tools/test-menu-before-setup.php` tests it.
  初期セットアップの前でも、左のメニューに「プロジェクト管理」を出します（Exment 7.2.1 では分類「プロジェクト」の下）。見えるのはシステム管理者だけで、開くとセットアップの画面です。ほかの人には、セットアップが終わるまで出ません。セットアップのあともメニューは 1 つです。セットアップ済みのサイトは変わりません。

**Changed in 1.7.14 / 1.7.14 で変わったこと**

- The issue form shows the eight fields Backlog shows (status, assignee, priority, milestone, category, version, start and due date); the others fold under 「その他の項目」, which opens by itself when one of them has a value or an error, and stays open for those who left it open. A field's help is behind the (?) next to its name.
  課題の編集で、いつも出すのは Backlog と同じ 8 つ（状態・担当者・優先度・マイルストーン・カテゴリー・発生バージョン・開始日・期限日）にし、残りは「その他の項目」にたたみました。値か入力エラーがあれば自動で開き、開いた人には次も開いて出します。項目の説明は名前の横の (?) に入れました。
- `JapaneseHolidays` and `BusinessCalendar` (the holidays of Japan and the business-day calendar) for the reminders and recurring schedules to come. Not used yet; `php project-management/tools/test-business-calendar.php` tests them.
  これから作るリマインドと繰り返しの予定のための、日本の祝日と営業日のカレンダー（まだ使っていません）。

**Changed in 1.7.13 / 1.7.13 で変わったこと**

- With Exment 7.2.1 the menu entry goes under the group "プロジェクト" of the left menu (the feature map of exment.net), made when missing (`Menu::groupParentId()` of the core). A new install puts it there; an update moves it only while it is still at the top level (a migration that runs when the plugin is updated), so an entry an administrator moved stays. An older Exment keeps it at the top level.
  Exment 7.2.1 では、メニューの「プロジェクト管理」を左のメニューの分類「プロジェクト」（exment.net の機能マップ）の下に置きます（なければ作る。本体の `Menu::groupParentId()`）。新しく入れたときはそこに作り、更新では、いちばん上にあるときだけ移します（プラグインの更新で走る migration）。管理者が動かしたメニューはそのままです。古い Exment では今までどおりいちばん上です。

**Changed in 1.7.12 / 1.7.12 で変わったこと**

- The script and the style are copied to `public/exment-pm/` once per version and linked from there, so the web server hands them out without starting PHP (2ms instead of ~0.15s locally; 1.7-1.9s on ex1task when they came with other requests). If `public/` cannot be written, or `exment.pmportal.public_assets` is false, the route serves them as before.
  スクリプトとスタイルを版ごとに `public/exment-pm/` に置き、そこから読むようにしました。PHP を通らないので、ほかの読み込みと重なっても遅くなりません。書けないときは、これまでどおりプラグインの経路から読みます。
- The stamp check and the read mark skip Exment's admin page set-up (as the light API does): ~0.2s → ~0.15s each locally.
  更新の確認と既読の印は、管理画面の準備を飛ばすようにしました（軽い API と同じ）。
- A hover card is asked for once per page.
  マウスを重ねたときのカードは、1 ページで 1 回だけ読みます。

**Changed in 1.7.10 / 1.7.10 で変わったこと**

- The archive cutoff counts from the start of the day instead of this second, so the archive summary and the burndown that 1.7.9 caches are actually read from the cache: their key held the cutoff, which changed on every load.
  アーカイブの区切りを「今の時刻」ではなく「その日の始まり」から数えるようにしました。1.7.9 のキャッシュの鍵に区切りの時刻が入っていて、毎回ちがう鍵になり、アーカイブの件数とバーンダウンがキャッシュから読まれていませんでした。

**Changed in 1.7.9 / 1.7.9 で変わったこと**

- The counts that group or filter on index columns MariaDB computes from each issue's JSON are kept in Laravel's cache until an issue changes: the project page's archive summary and burndown, and the dashboard's per-project counts and its 自分の課題 / 登録した課題 lists. The key is a cheap stamp (count and newest `updated_at` of the issues), the day and the reader, so a change shows at once and one user's counts are never another's. On ex1task these were ~0.35s, ~0.16s and ~0.5s.
  課題の JSON から計算する列で集計・絞り込みをしているところを、課題が変わるまでキャッシュに置くようにしました（プロジェクトのアーカイブの件数・バーンダウン、トップのプロジェクトごとの件数・自分の課題・登録した課題）。課題の件数と最新の更新日時・日付・見ている人で区別するので、変更はすぐに反映され、人ごとの件数が混ざることもありません。

**Changed in 1.7.8 / 1.7.8 で変わったこと**

- Faster lists on MariaDB: the issue lists no longer select the index columns (`column_*`), which the model never reads. On ex1task a project list query went from 1.6s to 0.11s; the dashboard runs the same kind of query three times.
  課題の一覧の読み込みを速くしました（MariaDB）。使っていない索引の列（`column_*`）を読まないようにしました。ex1task でプロジェクトの一覧の読み込みが 1.6 秒から 0.11 秒になりました。ダッシュボードも同じ読み込みを 3 回しています。
- The issue screens sent with the project page stay under 400KB (`exment.pmportal.embed_details_bytes`); the biggest are left out and fetched when opened. One issue with a long imported thread had made a 6MB page.
  プロジェクトの画面と一緒に送る課題の詳細を、合わせて 400KB までにしました。大きいものから外し、開いたときに読みます。取り込んだ長いやりとりの課題 1 件で、画面が 6MB になっていました。

**Changed in 1.7.7 / 1.7.7 で変わったこと**

- Schema 29: issues get 移行元の担当者 (`source_assignees`), the source's assignees by name. An import can only set the assignees to people who are users here; the names of the others are kept here, indexed, so the API and MCP can find the issues of one name and set the assignee later. The issue detail shows the name, greyed, while the assignee is empty. Run データ構造の更新 after updating.
  スキーマ 29: 課題に「移行元の担当者」（`source_assignees`）を足しました。取り込みでは、Exment にユーザーがいる人しか担当者に入れられません。ほかの人の名前をここに残します。検索の対象なので、API や MCP で名前から課題を探し、あとで担当者を入れられます。担当者が空のあいだは、課題の詳細の担当者の欄に名前を薄く出します。更新したあと「データ構造の更新」を押してください。

**Changed in 1.7.6 / 1.7.6 で変わったこと**

- With Exment 7.2.1 or later, opening a notification of an issue or a wiki page opens it in the portal (`#issue-ISS00239`), and `${value_url}` in Exment's notification mails is the portal's address too. The notification itself stays one click away (通知の内容を見る).
  Exment 7.2.1 以降では、課題や Wiki の通知を開くと、通知の画面でなく、ポータルのその課題（`#issue-ISS00239`）を開きます。Exment の通知のメールの `${value_url}` も、ポータルのアドレスになります。通知の内容は「通知の内容を見る」で見られます。
- The portal's own notifications (a comment, a confirmation) end with the issue's address, so their page links to the issue.
  ポータルが出す通知（コメント・確認）の本文の最後に、課題のアドレスを入れました。通知の画面から課題を開けます。

**Changed in 1.7.5 / 1.7.5 で変わったこと**

- A project with many open issues opens faster. The page carries its issue list packed: no addresses, project key or shown time per row (the screen rebuilds them from the id), no empty values, and Japanese as itself instead of `\uXXXX`. With 2,400 open issues the page went from 2.0MB and 1.6s to 0.9MB and 1.2s (local). Building the rows takes less than half the time.
  開いている課題が多いプロジェクトを、速く開くようにしました。ページに入れる課題の一覧を小さくしました（行ごとのアドレス・プロジェクトキー・表示用の日時は入れず、画面で番号から組み立てる。空の値は入れない。日本語を `\uXXXX` にしない）。開いている課題が 2,400 件で、ページが 2.0MB・1.6 秒から 0.9MB・1.2 秒になりました（手元）。行を作る時間も半分以下です。
- A 「プロジェクト一覧へ」 button at the left of the project's header, on every tab, back to the portal's list of projects. It looks like the issue detail's 「課題一覧へ」 and sits in the same place.
  プロジェクトの見出しの左に「プロジェクト一覧へ」を付けました（どのタブでも）。課題の詳細の「課題一覧へ」と同じ形・同じ場所です。
- Settings → Statuses names the status the list's 「完了以外」 hides: the last one in the order that counts as done (ISS00255). The other done statuses (処理済み and so on) stay visible, and the note says which. A done status added after 完了 now shows up here at once, instead of as a 完了以外 that hides nothing. The note follows every change of order, name or 完了扱い.
  設定の「状態」に、課題一覧の「完了以外」で隠れる状態（並びで最後の完了扱いの状態）を出すようにしました（ISS00255）。ほかの完了扱いの状態（処理済みなど）は「完了以外」でも表示されることも書きます。完了の後ろに完了扱いの状態を足してしまったとき、「完了以外」で何も隠れなくなるのを、ここですぐに気づけます。並び順・名前・完了扱いを変えると、その場で書き直します。

**Changed in 1.7.4 / 1.7.4 で変わったこと**

- Opening a project from the project list (or the dashboard, or the top bar) goes to the tab last used in it, instead of always to Home. Most people work on the issue list and clicked over to it every time. Kept per person and per project in the browser; for a project not opened yet, the tab last used in any project. A link with a tab or an issue in it still opens that. Settings is not kept.
  プロジェクト一覧（ダッシュボード・上のバーからも）でプロジェクトを開くと、いつもホームでなく、そのプロジェクトで前に使ったタブを開くようにしました。ほとんどの人は課題の一覧で作業するので、毎回クリックしていました。人ごと・プロジェクトごとにブラウザーに覚えます。まだ開いていないプロジェクトでは、最後にどこかのプロジェクトで使ったタブです。タブや課題を指したリンクは、今までどおりそこを開きます。設定は覚えません。

**Changed in 1.7.3 / 1.7.3 で変わったこと**

- Addresses by the project code (ISS00440): a project opens at `/admin/plugins/exment_pm/EXMENT_CUSTOMER` instead of its number, and an issue at `#issue-ISS00239` (its issue key) instead of `#issue-EXMENT_CUSTOMER-239`. Every link, "copy URL", the bell and the mails use this form. Older addresses still open and are sent on to the new one: the number, the code in either case, the old `#issue-` form, and a code from before the project was renamed (the old codes are kept). A project the user may not open answers as before.
  プロジェクトのアドレスを番号でなくプロジェクトキーに（`/admin/plugins/exment_pm/EXMENT_CUSTOMER`）、課題を `#issue-EXMENT_CUSTOMER-239` でなく課題キーに（`#issue-ISS00239`）しました（ISS00440）。リンク・「リンクをコピー」・ベル・メールも、この形です。前のアドレス（番号・小文字のキー・前の `#issue-` の形・名前を変える前のプロジェクトキー）も開き、新しいアドレスに移ります（前のキーは残しておきます）。見られないプロジェクトの答えは、今までと同じです。
- A copy button by the issue key, like Backlog's, on the issue page and on each row of the list: a click copies "ISS00239 subject", Shift+click the key alone, as text and as a link (a paste into Teams, OneNote, Outlook or Word is a link to the issue).
  課題の画面と一覧の行の課題キーの横に、Backlog のようなコピーのボタンを付けました。押すと「ISS00239 件名」を、Shift を押しながらだと課題キーだけをコピーします。文字とリンクの両方で入るので、Teams・OneNote・Outlook・Word に貼るとリンクになります。
- An issue key written in a description or a comment (ISS00239) is a link to the issue, when it is an issue of the project the reader may open.
  説明やコメントに書いた課題キー（ISS00239）は、その課題へのリンクになります（同じプロジェクトの、見られる課題だけ）。
- The short links of 1.6.1 (`/view/KEY`, `/projects/KEY`) in earlier mails did not open; they do now.
  1.6.1 の短いリンク（`/view/KEY`・`/projects/KEY`）が開かなかったのを直しました（前のメールのリンクも開きます）。

**Changed in 1.7.2 / 1.7.2 で変わったこと**

- The 基本情報 (fields) of the issue edit screen in two columns: on a wide screen it showed four narrow ones, with inputs about 150px wide and help of five lines or more. Fields read together stand on one row (status and assignee, start and due date, the two hour counts...), and the label is level with its input. One column when the screen is narrow.
  課題の編集画面の基本情報を 2 列にしました。広い画面では 4 列になり、入力欄が 150px ほど、説明が 5 行以上になっていました。一緒に見る項目を同じ行に並べ（状態と担当者、開始日と期限日、予定と実績の工数など）、名前を入力欄の高さにそろえました。狭い画面では 1 列です。

**Changed in 1.7.1 / 1.7.1 で変わったこと**

- Word, Excel and PowerPoint attachments can be edited on the screen (ISS00416): with Exment Plugin Groupware 0.4.0 or later, the window that **表示** opens has **編集する** (Edit) for those who may edit the issue (or wiki page, or project). Saving replaces the attachment's content; the earlier content stays linked from a comment. Closing the window with unsaved changes asks first.
  Exment Plugin Groupware 0.4.0 以降があると、**表示** で開いた窓に、その課題（Wiki・プロジェクト）を編集できる人には **編集する** が出て、Word・Excel・PowerPoint をその場で編集・保存できます（ISS00416）。保存すると添付の中身が新しくなり、前の中身はコメントのリンクから開けます。保存していない変更があるときは、窓を閉じる前に確かめます。

**Changed in 1.7.0 / 1.7.0 で変わったこと**

- Word, Excel and PowerPoint attachments open on the screen (ISS00416): the files of issues, comments, wiki pages and the project's file list get a **表示** (View) button that shows the file read only in a window on the page, without downloading it. It uses Exment Plugin Groupware 0.3.0 or later (ibisOffice, a browser with WebGPU); without it, the files download as before. The file is read from its usual download address, so nobody sees more than before.
  課題・コメント・Wiki・プロジェクトのファイルの Word・Excel・PowerPoint に **表示** ボタンが付き、ダウンロードせずに画面の中で読み取り専用で開けるようになりました（ISS00416）。Exment Plugin Groupware 0.3.0 以降（ibisOffice、WebGPU のあるブラウザー）を使います。入っていないときは今までどおりダウンロードです。ファイルはいつものダウンロードの URL から読むので、見られる人は変わりません。

**Changed in 1.6.6 / 1.6.6 で変わったこと**

- Faster project data (ISS00257): each change in the history is read once per request, even when both the activity feed and the issue screens show it; the names it mentions (people, statuses, types...) are read with one query per table; and finding where a long text was changed compares bytes instead of characters. On ex1task the project data takes about 340ms instead of 420ms, and the activity feed 14 queries instead of 38.
  プロジェクトのデータが速くなりました（ISS00257）。変更の履歴は、最近の動きと課題の詳細の両方に出るときも 1 回だけ読み解きます。履歴に出る名前（担当者・状態・種別など）は、表ごとに 1 回のクエリでまとめて読みます。長い本文のどこが変わったかを探すのも速くしました。ex1task では、プロジェクトのデータが約 420ms から約 340ms に、最近の動きのクエリが 38 から 14 になりました。

**Changed in 1.6.5 / 1.6.5 で変わったこと**

- Faster after a save (ISS00257): saving an issue or a wiki page, posting a comment that changes the status, uploading a file or deleting an issue no longer reloads the whole page. The portal fetches the project's data once and draws the list and the detail again in place (on ex1task, about 400KB of data instead of a 724KB page and everything it loads after). If the data cannot be fetched, it reloads as before.
  保存したあとが速くなりました（ISS00257）。課題・Wiki の保存、状態を変えるコメント、ファイルのアップロード、課題の削除のあと、ページ全体を読み直さず、プロジェクトのデータだけを取り直して、一覧と詳細をその場で描き直します（ex1task では、724KB のページとそのあとの読み込みの代わりに、約 400KB のデータ 1 回）。データが取れないときは、今までどおり読み直します。
- A short link `/view/KEY-12` for a moved issue only counts KEY when it is a project you can open.
  短いリンク `/view/KEY-12` で移した課題を開くときの KEY は、自分が開けるプロジェクトのキーだけを見ます。
- Note for 1.6.4: an install that already ran データ構造の更新 to schema 28 with 1.6.2 or 1.6.3 does not run the member role group tidy again. If an administrator had added permissions to that group, check it in the role group settings.
  1.6.4 の補足: 1.6.2・1.6.3 でスキーマ 28 まで更新した環境では、メンバーのロールグループの整理はもう一度は流れません。そのグループに管理者が権限を足していた場合は、ロールグループの設定で確かめてください。

**Changed in 1.6.4 / 1.6.4 で変わったこと**

- When a new version is waiting for **データ構造の更新**, administrators see **データ構造の更新があります** at the top of the portal; it opens the settings.
  新しい版で **データ構造の更新** がまだのときは、管理者にだけ、画面の上に **データ構造の更新があります** と出します。押すと設定の画面が開きます。
- The tidy of the member role group (1.6.2) also covers a group an administrator added permissions to. Press **データ構造の更新** once after updating.
  1.6.2 のメンバーのロールグループの権限の整理が、管理者が権限を足したグループにも効くようにしました。更新したあと、**データ構造の更新** を 1 回押します。
- A short link `/view/KEY-12` opens issue 12 only when KEY is a project's key.
  短いリンク `/view/KEY-12` は、KEY がどれかのプロジェクトのキーのときだけ課題 12 を開きます。

**Changed in 1.6.3 / 1.6.3 で変わったこと**

- The project menu (**プロジェクト ▼**) is wider, and a long project name wraps instead of being cut off. Keys get a wider column there and on the dashboard.
  プロジェクトのメニュー（**プロジェクト ▼**）を広げ、長いプロジェクト名は「…」で切らずに折り返すようにしました。キーの欄も、メニューとダッシュボードで広げました。

**Changed in 1.6.2 / 1.6.2 で変わったこと**

- The member role group's permissions are tidied (schema 28). Press **データ構造の更新** once after updating. People are added to a project on its member settings, as before.
  メンバーのロールグループの権限を整理しました（スキーマ 28）。更新したあと、**データ構造の更新** を 1 回押します。プロジェクトに人を足すのは、今までどおりメンバーの設定からです。

**Changed in 1.6.1 / 1.6.1 で変わったこと**

- Short links like Backlog's: `/plugins/exment_pm/view/EXMENT_CLOUD-407` opens an issue and `/plugins/exment_pm/projects/EXMENT_CLOUD` a project (on a self-hosted Exment, `/admin/plugins/exment_pm/...`). An issue also opens by its issue_key (`view/ISS00252`) and, if imported, by its key at the source (`view/MIGRE_TEST_2026-2`). The mails and **リンクをコピー** (in the issue's … menu, and on a comment) give the short form. The old `/15#issue-...` links keep working. No データ構造の更新 needed.
  Backlog のような短いリンクにしました。`/plugins/exment_pm/view/EXMENT_CLOUD-407` で課題が、`/plugins/exment_pm/projects/EXMENT_CLOUD` でプロジェクトが開きます（自分のサーバーの Exment では `/admin/plugins/exment_pm/...`）。課題キー（`view/ISS00252`）や、取り込んだ課題は移行元のキー（`view/MIGRE_TEST_2026-2`）でも開けます。メールと **リンクをコピー**（課題の「…」のメニューとコメント）は短い形になります。今までの `/15#issue-...` のリンクもそのまま開けます。データ構造の更新はいりません。

**Changed in 1.6.0 / 1.6.0 で変わったこと**

- More roles on an issue (schema 27, ISS00252): **副担当者** (sub-assignees), **管理者** (managers), **レビュアー** (reviewers) and **難易度** (difficulty: 易しい・普通・難しい). They are on the edit form under the assignees, and the issue detail shows them with the **起票者** (the author; for an imported issue, the author at the source). Sub-assignees, managers and reviewers get the issue's update notices like the assignees, and have to be on the project. Watchers stay the **お知らせ先** column and the ウォッチ button. Press **データ構造の更新** in the plugin settings once after updating.
  課題の役割を足しました（スキーマ 27、ISS00252）。**副担当者**・**管理者**・**レビュアー**・**難易度**（易しい・普通・難しい）です。編集の画面では担当者の下に並び、課題の詳細には **起票者**（取り込んだ課題は移行元の登録者）と一緒に出ます。副担当者・管理者・レビュアーにも、担当者と同じく更新のお知らせが届きます。プロジェクトに参加している人だけを選べます。ウォッチャーは、今までどおり **お知らせ先** とウォッチのボタンです。更新したあと、プラグインの設定で **データ構造の更新** を 1 回押します。

**Changed in 1.5.0 / 1.5.0 で変わったこと**

- Room for what a Backlog import brings (schema 26). Issues get the source key and URL, who raised and last changed them at the source and when, and the source's custom attributes; the issue detail shows them under 移行元 and カスタム属性. Milestones get an archived flag, wiki pages get tags and their source. All empty for anything not imported. Press **データ構造の更新** in the plugin settings once after updating.
  Backlog から取り込むときの入れ先を足しました（スキーマ 26）。課題に、移行元の課題キー・URL・登録者・登録日時・更新者・更新日時と、カスタム属性が入ります。課題の詳細の「移行元」「カスタム属性」に出ます。マイルストーンに「アーカイブ済み」、Wiki に「タグ」と移行元が入ります。取り込んでいないものは空のままです。更新したあと、プラグインの設定で **データ構造の更新** を 1 回押します。

**Changed in 1.4.1 / 1.4.1 で変わったこと**

- The left menu has a single entry, **プロジェクト管理**, which opens the portal. A new install no longer creates the other entries (プロジェクトポータル, the settings link, マスタ and the template's table entries). The settings and the master lists open from **⚙ 設定** at the top of the portal (administrators only).
  左のメニューは **プロジェクト管理** 1 つだけになりました（開くとポータル）。新しく入れたときに、ほかのメニュー（プロジェクトポータル・設定・マスタ・テンプレートの表のメニュー）を作りません。設定とマスタは、ポータルの上の **⚙ 設定** から開きます（管理者だけ）。
- An install that already has the old entries: press **⚙ 設定 → データ構造の更新** once. It removes only the plugin's own entries, found by their names, and moves プロジェクト管理 to the top of the menu. An entry an administrator added stays (and so does the folder it is in). The screens and the data do not change. The **PM データ移行** entry (PmMigrate) no longer shows once the PmMigrate plugin is deleted.
  古いメニューがある環境では、**⚙ 設定 → データ構造の更新** を 1 回押します。プラグインが作ったメニューだけを名前で見つけて外し、プロジェクト管理をメニューのいちばん上の段に移します。管理者が足したメニュー（と、それが入っているフォルダ）は残ります。画面とデータは変わりません。**PM データ移行** のメニュー（PmMigrate）は、PmMigrate プラグインを消すと出なくなります。

**Changed in 1.4.0 / 1.4.0 で変わったこと**

- The address is now `/admin/plugins/exment_pm` (on Exment SaaS `/plugins/exment_pm`). **Links and bookmarks to the old address `/admin/plugins/pm_portal/...` stop working ("404 not found")**: links in mails already sent, in issue bodies, comments and wiki pages, and browser bookmarks. Exment does not redirect them. The menu entry, the bell notifications and new mails use the new address by themselves. Links stored in issue bodies, comments and wiki pages can be rewritten once with [`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) (optional).
  アドレスが `/admin/plugins/exment_pm`（Exment SaaS では `/plugins/exment_pm`）になりました。**古いアドレス `/admin/plugins/pm_portal/...` へのリンクやブックマークは開けなくなります（「404 not found」）**。送信済みのメールのリンク、課題の本文・コメント・Wiki に書いたリンク、ブラウザのブックマークです。Exment は転送しません。メニュー・ベルのお知らせ・新しく送るメールは、何もしなくても新しいアドレスになります。課題の本文・コメント・Wiki に保存されたリンクは、[`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) で 1 回書き換えられます（やらなくてもかまいません）。
- The internal name is now `ExmentPM` (it was `ExmentPluginPM` in 1.3.0 and `PmPortal` before). An Exment that already has the plugin needs the steps in [Moving to ExmentPM](#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) once.
  内部の名前が `ExmentPM` になりました（1.3.0 は `ExmentPluginPM`、その前は `PmPortal`）。すでにプラグインが入っている Exment では、[ExmentPM への移行](#moving-to-exmentpm-from-pmportal-or-exmentpluginpm--exmentpm-への移行) を 1 回だけします。
- The screens say プロジェクト管理 instead of ポータル (for example **プロジェクト管理の設定**, was ポータル設定).
  画面の「ポータル」という言い方を「プロジェクト管理」に直しました（例: **プロジェクト管理の設定**。前は「ポータル設定」）。

Data migration was the separate PmMigrate plugin until 1.2.0; it is now part of this plugin. PmMigrate is retired: after updating you can delete it in **Admin settings → Plugins** (its last version stays on [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1)).
データ移行は、1.2.0 までは別のプラグイン PmMigrate でしたが、今はこのプラグインに入っています。PmMigrate は使い終わりです。プラグインを新しくしたあと、**管理者設定 → プラグイン** で消してかまいません（最後の版は [Releases](https://github.com/exceedone/exment-plugins/releases/tag/PmMigrate-v1.0.1) に残っています）。

## Features / できること

- **Issues / 課題**: list with filters and quick chips, parent/child tree, Excel-style inline edit, bulk update, comments, attachments, Markdown bodies with pasted images
  一覧（絞り込み・クイックフィルター）、親子のツリー表示、一覧での直接編集、一括更新、コメント、添付、Markdown の本文（画像の貼り付け）
- **Read receipts and unread / 既読・未読**: who has read an issue, unread issues tinted in the list, right-click to mark read or unread
  誰が読んだか、まだ見ていない課題の色分け、右クリックで既読・未読
- **Notifications / 通知**: in-app bells, and optional mail on a new issue or comment, or with the "mail this issue" button (sent with Exment's mail settings)
  ベルのお知らせ、課題の登録・コメント・「メールで知らせる」ボタンでのメール（Exment のメール設定で送る）
- **Board, Gantt chart, milestones, burndown / ボード・ガントチャート・マイルストーン・バーンダウン**
- **Wiki and files / Wiki・ファイル共有**
- **Members and roles / メンバーと権限**: project manager, members and viewers, on Exment's role groups
  プロジェクトの管理者・メンバー・閲覧者（Exment の役割グループを使う）
- **Data migration / データ移行** (**⚙ Settings → データ移行**, system administrators only): export projects with comments, history, attachments, stars and review requests to a ZIP, and import it into another Exment (check first, then write)
  プロジェクトをコメント・変更履歴・添付・スター・レビュー依頼ごと ZIP に書き出し、別の Exment に取り込む（先に確認だけして、それから書き込む）。**⚙ 設定 → データ移行** から、システム管理者だけが使えます
  The data migration feature was originally written by TTM (as the PmMigrate plugin).
  データ移行の機能は、もとは TTM さんが（PmMigrate プラグインとして）作りました。

## Install / 入れ方

1. Download `ExmentPM-<version>.zip` from **[Releases](https://github.com/exceedone/exment-plugins/releases)**. Do not unzip it. (PmMigrate is no longer needed.)
   **[Releases](https://github.com/exceedone/exment-plugins/releases)** から `ExmentPM-<版>.zip` をダウンロードします。展開はしません（PmMigrate はもう要りません）。
2. Upload the zip in **Admin settings → Plugins → Plugin upload**. On Exment SaaS (`*.exments.com`) there is nothing to upload: from Exment 7.2.1 the tenant administrator presses **Use (install)** in **Admin settings → Feature management**, which also does the first-time setup, and the operator updates it from then on (data structure update included).
   **管理者設定 → プラグイン → プラグインアップロード** で zip を送ります。Exment SaaS（`*.exments.com`）では、アップロードは要りません。Exment 7.2.1 から、テナントの管理者が **管理者設定 → 機能の管理** で **使う（インストールする）** を押すと、初期セットアップまで済みます。そのあとは運営側が、データ構造の更新も含めて新しくします。
3. Open Project Management (**プロジェクト管理** in the menu) as an administrator and run the first-time setup. It creates the tables the portal uses.
   管理者でプロジェクト管理（メニューの **プロジェクト管理**）を開き、初期設定をします。ポータルが使うテーブルが作られます。
4. After installing a newer version, press **⚙ Settings → Update data structure** if the screen asks for it.
   新しい版を入れたあと、画面に言われたら **⚙ 設定 → データ構造の更新** を押します。

## Moving to ExmentPM (from PmPortal or ExmentPluginPM) / ExmentPM への移行

Only for an Exment that already has **PmPortal** (1.2.0 or older) or **ExmentPluginPM** (1.3.0). A new install does not need this. Both go straight to ExmentPM with the same steps; below, `<OLD>` is the name you have now (`PmPortal` or `ExmentPluginPM`).
すでに **PmPortal**（1.2.0 まで）か **ExmentPluginPM**（1.3.0）が入っている Exment だけの作業です。新しく入れる場合は要りません。どちらからでも、同じ手順で直接 ExmentPM にできます。下の `<OLD>` は、今入っている名前（`PmPortal` か `ExmentPluginPM`）です。

Why: Exment treats an upload as an update only when both `plugin_name` and `uuid` are the same as an installed plugin. The `uuid` is unchanged and the name is new, so uploading the ExmentPM zip on top of the old plugin stops with "UUID exists but the plugin name is wrong" and changes nothing. The steps below rename the installed plugin in place and set its new address. The row in the `plugins` table keeps its id, so the settings, the menu, the role group permissions, the notifications and all data stay as they are.
理由: Exment がアップロードを「更新」として扱うのは、`plugin_name` と `uuid` の両方が、入っているプラグインと同じときだけです。`uuid` は同じで名前だけ新しいので、古いプラグインの上に ExmentPM の zip をアップロードすると「UUIDは存在しますが、プラグイン名が正しくありません」と出て止まります（何も変わりません）。下の手順は、入っているプラグインの名前をその場で変え、新しいアドレスを入れます。`plugins` テーブルの行の id は同じままなので、設定・メニュー・役割グループの権限・通知・データはそのまま残ります。

Do not delete or disable the old plugin and then upload the new zip: that makes a new plugin, and the settings, the menu entry and the permissions are lost.
古いプラグインを消したり無効にしたりしてから新しい zip をアップロードしないでください。別の新しいプラグインになり、設定・メニュー・権限が消えます。

What changes for users: the address becomes `/admin/plugins/exment_pm`. Links and bookmarks to `/admin/plugins/pm_portal/...` stop working; tell your users before you start.
利用者に変わること: アドレスが `/admin/plugins/exment_pm` になります。`/admin/plugins/pm_portal/...` へのリンクやブックマークは開けなくなるので、作業の前に利用者に知らせてください。

### On your own server / 自分のサーバーの場合

You need shell access to the server and to the database. Keep the order: files first, then the database. (If the name is changed while the new folder is missing, the screen answers 404 and Exment makes an empty `ExmentPM` folder.)
サーバーとデータベースに入れる人が作業します。順番を守ります（先にファイル、あとでデータベース）。新しいフォルダがないまま名前を変えると、画面は 404 になり、Exment が空の `ExmentPM` フォルダを作ります。

1. Back up the database and the folder `storage/app/plugins/<OLD>`.
   データベースと `storage/app/plugins/<OLD>` フォルダのバックアップを取ります。
2. Unzip `ExmentPM-<version>.zip` into a new folder `storage/app/plugins/ExmentPM` (so that `storage/app/plugins/ExmentPM/config.json` exists). Give it the same owner as the old folder. Leave the old folder where it is for now.
   `ExmentPM-<版>.zip` を、新しいフォルダ `storage/app/plugins/ExmentPM` に展開します（`storage/app/plugins/ExmentPM/config.json` ができる形）。持ち主は古いフォルダと同じにします。古いフォルダはまだ消しません。

   ```
   cd /path/to/exment/storage/app/plugins
   mkdir ExmentPM && unzip -q /path/to/ExmentPM-1.4.0.zip -d ExmentPM
   chown -R --reference=<OLD> ExmentPM
   ```

   If the plugins are kept on S3 or another remote disk (`EXMENT_DRIVER_PLUGIN`), put the same files under `ExmentPM/` there too.
   プラグインを S3 などに置いている場合（`EXMENT_DRIVER_PLUGIN`）は、そこにも同じファイルを `ExmentPM/` として置きます。
3. Rename the plugin and set the new address in the database. Connect with `utf8mb4` (for example `mysql --default-character-set=utf8mb4`), or the Japanese name is stored broken. The statement must change exactly 1 row. Note the old `plugin_name` and `version` first; you need them to go back.
   データベースで、プラグインの名前を変え、新しいアドレスを入れます。`utf8mb4` でつなぎます（例: `mysql --default-character-set=utf8mb4`）。そうしないと日本語の名前が文字化けして入ります。変わる行は 1 行だけです。元に戻すときに使うので、先に今の `plugin_name` と `version` を控えます。

   ```sql
   SELECT id, plugin_name, version, JSON_EXTRACT(options, '$.uri') AS uri
     FROM plugins WHERE uuid = '00da3e93-1a24-4f44-b993-d61367e43649';

   UPDATE plugins
      SET plugin_name = 'ExmentPM',
          plugin_view_name = 'Exment Plugin PM（プロジェクト管理）',
          version = '1.4.0',
          options = JSON_SET(COALESCE(options, '{}'), '$.uri', 'exment_pm'),
          updated_at = NOW()
    WHERE uuid = '00da3e93-1a24-4f44-b993-d61367e43649'
      AND plugin_name IN ('PmPortal', 'ExmentPluginPM');
   ```

   The address is the `uri` key in the `options` column. Uploading a zip also sets it from `config.json`, but the upload is refused while the name differs, so it is set here.
   アドレスは `options` 列の `uri` です。zip のアップロードでも `config.json` の値が入りますが、名前が違う間はアップロードが止まるので、ここで入れます。
4. Clear the caches (run as the user PHP runs as). If you use `php artisan route:cache`, build it again.
   キャッシュを消します（PHP を動かしているユーザーで実行します）。`php artisan route:cache` を使っている場合は、作り直します。

   ```
   php artisan cache:clear
   php artisan view:clear
   php artisan route:clear
   ```
5. Open Project Management from the menu (`/admin/plugins/exment_pm`) and check your projects. Press **⚙ Settings → Update data structure** if it asks. **Admin settings → Plugins** now shows `ExmentPM` with the URI `exment_pm`.
   メニューからプロジェクト管理（`/admin/plugins/exment_pm`）を開き、プロジェクトが出ることを確かめます。言われたら **⚙ 設定 → データ構造の更新** を押します。**管理者設定 → プラグイン** には `ExmentPM`、URI は `exment_pm` と出ます。
6. Delete the old folder `storage/app/plugins/<OLD>` (and `<OLD>/` on the remote disk).
   古いフォルダ `storage/app/plugins/<OLD>` を消します（S3 などの `<OLD>/` も）。
7. If a cron job runs the batch by name, change `--name=<OLD>` to `--name=ExmentPM` (or use `--uuid=00da3e93-1a24-4f44-b993-d61367e43649`, which never changes).
   cron でバッチを名前で呼んでいる場合は、`--name=<OLD>` を `--name=ExmentPM` に変えます（`--uuid=00da3e93-1a24-4f44-b993-d61367e43649` なら変わりません）。
8. Optional: rewrite the old links stored in issue bodies, comments and wiki pages. See [Old links in stored text](#old-links-in-stored-text-optional--保存されている古いリンクの書き換えやらなくてもよい).
   やらなくてもよい作業: 課題の本文・コメント・Wiki に保存されている古いリンクを書き換えます。[保存されている古いリンクの書き換え](#old-links-in-stored-text-optional--保存されている古いリンクの書き換えやらなくてもよい) を見てください。

From now on, update as usual: upload the newer `ExmentPM-<version>.zip` in **Admin settings → Plugins**.
これからは今までどおり、新しい `ExmentPM-<版>.zip` を **管理者設定 → プラグイン** でアップロードして新しくします。

To go back: run the statement the other way round with the values you noted (`plugin_name = '<OLD>'`, the old `version`, `'$.uri', 'pm_portal'`; for PmPortal also `plugin_view_name = 'プロジェクト管理'`) `WHERE uuid = '...' AND plugin_name = 'ExmentPM'`, clear the caches, and keep using the old folder. This is why step 6 comes last.
元に戻すとき: 控えた値で同じ SQL を逆向きに実行し（`plugin_name = '<OLD>'`、`version` は前の版、`'$.uri', 'pm_portal'`。PmPortal のときは `plugin_view_name = 'プロジェクト管理'` も。条件は `WHERE uuid = '...' AND plugin_name = 'ExmentPM'`）、キャッシュを消して、古いフォルダを使い続けます。手順 6 を最後にしているのはこのためです。

### On Exment SaaS / Exment SaaS の場合

You do not need to do anything. Exceedone does the same change for each tenant (the files go to the tenant's plugin folder on S3 as well as to the server). The address changes for you too: `/plugins/exment_pm` instead of `/plugins/pm_portal`.
利用者の作業はありません。エクシード・ワンがテナントごとに同じ変更をします（ファイルはサーバーに加えて、テナントの S3 のプラグイン置き場にも置きます）。アドレスは同じように変わります（`/plugins/pm_portal` が `/plugins/exment_pm` になります）。

### Old links in stored text (optional) / 保存されている古いリンクの書き換え（やらなくてもよい）

Links to `/admin/plugins/pm_portal/...` that people wrote into issue bodies, comments and wiki pages are plain text in the database, so they keep the old address and end in 404. [`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) rewrites them to `/admin/plugins/exment_pm/...`. Mails already sent and bookmarks cannot be fixed.
課題の本文・コメント・Wiki に人が書いた `/admin/plugins/pm_portal/...` へのリンクは、データベースの中のただの文字なので、古いアドレスのまま残り、開くと 404 になります。[`tools/rewrite-old-links.php`](tools/rewrite-old-links.php) で `/admin/plugins/exment_pm/...` に書き換えられます。送信済みのメールとブックマークは直せません。

The script is plain PHP (PDO); it does not start Exment. Take a database backup first. `--base` is the address of the Exment admin (the part before `/plugins/...`; no `/admin` on Exment SaaS). Only links of that Exment are rewritten; links to another Exment are left alone.
このスクリプトはふつうの PHP（PDO）で、Exment は起動しません。先にデータベースのバックアップを取ります。`--base` は Exment の管理画面のアドレス（`/plugins/...` の前の部分。Exment SaaS では `/admin` は付きません）です。その Exment へのリンクだけを書き換え、ほかの Exment へのリンクは変えません。

```
export DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...     # DB_PORT, DB_SSL_CA if needed

# 1. count only, changes nothing / 数えるだけ（何も変えない）
php tools/rewrite-old-links.php --base=https://example.com/admin

# 2. rewrite; the old texts are saved to the backup file / 書き換える（前の文章はバックアップのファイルに残す）
php tools/rewrite-old-links.php --base=https://example.com/admin --apply --backup=old-links-backup.json

# 3. undo / 元に戻す
php tools/rewrite-old-links.php --rollback=old-links-backup.json
```

It looks in the issue table, the wiki table and Exment's `comment` table (`--tables=a,b` to choose), and with `--bells` also in the text of bell notifications. It changes only the texts that contain an old link; `updated_at` and the edit history stay as they are.
見る場所は、課題のテーブル・Wiki のテーブル・Exment の `comment` テーブルです（`--tables=a,b` で選べます）。`--bells` を付けると、ベルのお知らせの文章も見ます。古いリンクがある文章だけを変え、`updated_at` と変更履歴は変えません。

## Development / 開発

- Write PHP that runs on PHP 8.2.
  PHP 8.2 で動く書き方にします。
- Raise `version` in `config.json` for each release, and never change `uuid`, `plugin_name` or `uri`. (`plugin_name` was changed in 1.3.0 and 1.4.0 and `uri` in 1.4.0; every install needed the manual steps above, and the old links broke.)
  出すたびに `config.json` の `version` を上げます。`uuid`・`plugin_name`・`uri` は変えません（`plugin_name` は 1.3.0 と 1.4.0 で、`uri` は 1.4.0 で変えましたが、入っている環境すべてで上の手作業が必要になり、古いリンクも切れました）。
- Naming rule for the official plugins: `plugin_name`, the folder, the namespace and the zip are `Exment<Name>` (`ExmentPM`), the `uri` is `exment_<name>` (`exment_pm`, always written in `config.json`), and the name in the plugin list is `Exment Plugin <Name>（日本語名）`. The full rule is in the [top-level README](../README.md#naming-rule-for-official-plugins).
  公式プラグインの名前の決まり: `plugin_name`・フォルダ・namespace・zip は `Exment<名前>`（`ExmentPM`）、`uri` は `exment_<名前>`（`exment_pm`。`config.json` に必ず書く）、一覧に出る名前は `Exment Plugin <名前>（日本語名）` です。くわしくは [いちばん上の README](../README.md#公式プラグインの名前の決まり) にあります。
- To release, raise `version` in `config.json`, merge to `main`, then push a tag `<Folder>-v<version>` (for example `git tag ExmentPM-v1.4.0 && git push origin ExmentPM-v1.4.0`). The release workflow builds the zip (top `config.json` last) and publishes it on Releases.
  出すときは、`config.json` の `version` を上げて `main` にマージし、`<フォルダ名>-v<版>` のタグ（例: `git tag ExmentPM-v1.4.0 && git push origin ExmentPM-v1.4.0`）を push します。release のワークフローが zip を作り（いちばん上の `config.json` を最後に入れる）、Releases に出します。
- After a release, update the download links (version numbers) in the top-level `README.md`.
  出したあとは、いちばん上の `README.md` のダウンロードのリンク（版の番号）を直します。
- When you add tables or columns, raise `PortalSchema::VERSION` and add the step to `PortalUpgrade`.
  テーブルや列を足したら、`PortalSchema::VERSION` を上げ、`PortalUpgrade` に手順を足します。
- UI text is in `ExmentPM/resources/lang/ja/pmportal.php`. A key used by the screen's script must also be listed in `resources/views/index.blade.php`.
  画面の文言は `ExmentPM/resources/lang/ja/pmportal.php` にあります。画面のスクリプトで使う文言は、`resources/views/index.blade.php` の一覧にも足します。
- After changing a blade file on a server, run `php artisan view:clear`.
  サーバーで blade を変えたら `php artisan view:clear` をします。

## License / ライセンス

[GPL-3.0](../LICENSE)
