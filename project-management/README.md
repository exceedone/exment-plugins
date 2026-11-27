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

- The 「← 課題一覧」 button (and 「← Wiki 一覧」, and the one in the bar that stays on scroll) is filled with the site's colour, white text, darker under the pointer, a filled circle on a phone.
  「← 課題一覧」のボタン（「← Wiki 一覧」と、スクロールしても残る行のものも）を、サイトの色で塗り、白い文字にしました。マウスを乗せると濃くなり、スマホでは塗った丸になります。

**Changed in 1.7.24 / 1.7.24 で変わったこと**

- The rows of the issue list are easier to tell apart (ISS02944): a child row in the tree is white like its parent (a thin guide, └ and the indent say it is a child); an unread row has a blue ● before a bold subject and the blue left edge, with no tint; the pointer gives a light neutral grey, and a ticked row its own colour, so unread, pointed at and ticked stay distinguishable in every mix, with the accent of the site and in a dark theme. A screen reader reads 「未読」.
  課題の一覧の行を見分けやすくしました（ISS02944）：ツリーの子の行は親と同じ白にし（細い線と └ と字下げで子とわかります）、未読の行は件名の前の青い ●・太字・左の青い線で示し、色は付けません。マウスを乗せた行は薄い灰色、チェックした行は別の色です。未読・マウス・チェックをどう重ねても見分けられ、サイトの色やダークでも同じです。読み上げでは「未読」と読みます。

**Changed in 1.7.23 / 1.7.23 で変わったこと**

- An issue has a quiet 「← 課題一覧」 button before its trail and in the bar that stays on scroll (an icon only on a phone). It goes back where the issue was opened from - the list, the board, the gantt or the home (「← ボード」 and so on) - and to the list from anywhere else; back in the list, the filters, sort and page are as they were and the issue just left lights up for a moment, in view.
  課題の画面のパンくずの前と、スクロールしても残る行に、目立たない「← 課題一覧」のボタンを付けました（スマホではアイコンだけ）。課題を開いた画面（一覧・ボード・ガント・ホーム）に戻り、ボタンの文字もそれに合わせます（「← ボード」など）。それ以外から開いたときは一覧に戻ります。一覧では、絞り込み・並び順・ページはそのままで、今まで見ていた課題が少しの間光り、見える所に出ます。

**Changed in 1.7.22 / 1.7.22 で変わったこと**

- Git link, step 1 (off at first; see 「Git とつなぐ」 below): a project can receive webhooks from GitHub, GitLab and Gitea; commits and pull requests naming an issue's key show on that issue's timeline, and the issue has 「ブランチ名をコピー」.
  Git とのつなぎの 1 段目（最初は使いません。下の「Git とつなぐ」）：プロジェクトが GitHub・GitLab・Gitea の Webhook を受け、課題のキーを書いたコミットとプルリクエストを、その課題の履歴に出します。課題に「ブランチ名をコピー」を足しました。
- The attachment preview's buttons and labels were shown as "undefined" outside Japanese; they are now in every language.
  添付のプレビューのボタンと見出しが、日本語のほかの言葉で「undefined」と出ていたのを直しました（全部の言葉に入れました）。

**Changed in 1.7.21 / 1.7.21 で変わったこと**

- The editors say they take Markdown (MD-1): under the comment box, the issue body and a wiki page, a small 「Markdown が使えます」 mark opens a cheat sheet (見出し, 太字, リスト, 番号, 引用, コード, リンク, 表, チェックボックス, and how to paste an image); a line puts its Markdown at the caret. The boxes' placeholder and the toolbar's tooltips show the Markdown. The comment box has 書く｜プレビュー as a pair. 「並べて表示」 puts a live preview beside the text on a screen 900px or wider (each person's choice, kept in their Exment settings; off at first). The preview is drawn the way the comment or the body is shown.
  書く欄が Markdown を使えることがわかるようにしました（MD-1）。コメント欄・課題の本文・Wiki のページの下に「Markdown が使えます」の目印を出し、押すと書き方の早見表（見出し・太字・リスト・番号・引用・コード・リンク・表・チェックボックス・画像の貼り付け方）が出ます。行を押すと、その書き方をカーソルの所に入れます。欄の薄い字と道具のボタンの説明にも Markdown を出します。コメント欄は「書く｜プレビュー」の 2 つのボタンにしました。「並べて表示」で、幅 900px 以上の画面では右にプレビューを並べ、書くとすぐ変わります（1 人ずつ Exment の設定に覚えます。はじめは OFF）。プレビューは、本当の表示と同じ描き方です。

- The trail above an issue names its parents too (プロジェクト管理 › project › 課題 › parent keys › this key): each a link with its key (and a short subject on a wide screen, the whole one in the tooltip), up to the issue levels; with more than two, the middle folds into 「…」, which opens them; a phone shows the direct parent only; a parent the reader may not open reads 「見られない課題」. The parents come with the issue (one query a level for all the issues of the page).
  課題の上のパンくずに、親課題も出すようにしました（プロジェクト管理 › プロジェクト › 課題 › 親のキー › この課題）。それぞれキーのリンクで、広い画面では短い件名も、マウスを乗せると件名すべてが出ます。課題の階層の深さまで出し、3 つ以上あるときは間を「…」にまとめます（押すと出ます）。スマホではすぐ上の親だけです。見られない親は「見られない課題」と出します。親は課題といっしょに読みます（ページの課題すべてに、階層 1 つにつき 1 回のクエリ）。

- Attachments open more easily: next to every attachment link (the issue's files, links in comments, the wiki) there are 「ダウンロード」 and 「オンラインで開く」 (and 「オンラインで編集」 with Groupware's Office editing). 「オンラインで開く」 shows pictures, PDF, text, CSV, Excel (with sheet tabs) and Word (text) in a window, up to 10 MB; links in comments also get a small preview card with 「▼ 開く」 and 「⤢ 大きく見る」. File links now always open in a new tab, outside the page loader.
  添付を開きやすくしました。添付のリンク（課題のファイル・コメントの中のリンク・Wiki）の横に「ダウンロード」「オンラインで開く」（グループウェアの Office の編集があれば「オンラインで編集」も）を出します。「オンラインで開く」は、画像・PDF・テキスト・CSV・Excel（シートのタブつき）・Word（文字）を窓で見られます（10 MB まで）。コメントの中のリンクには、小さなプレビュー（「▼ 開く」「⤢ 大きく見る」）も出ます。ファイルのリンクは、いつも新しいタブで、ページの読みこみの外で開くようにしました。

**Changed in 1.7.20 / 1.7.20 で変わったこと**

- The comment dock's 「ほかの項目も変える」 also has 管理者 and レビュアー (the same picker as 担当者) and 難易度, beside 副担当者, each where the issue table has the column; saved with the comment, in the history like the other fields.
  コメント欄の「ほかの項目も変える」に、副担当者に加えて、管理者・レビュアー（担当者と同じ選び方）と難易度を足しました。課題のテーブルにその列があるときだけ出します。コメントといっしょに保存し、履歴に残ります。

**Changed in 1.7.19 / 1.7.19 で変わったこと**

- The icons on the chips of 「知らせる人」 are read again when the comment dock opens (at most once a minute), so a person's quiet hours and muted projects show as they are now, not as they were when the page was opened (ISS02931). A small read (`GET {id}/notify-channels`, a new route); no issue is read.
  コメント欄を開いたときに、「知らせる人」のチップのアイコンを読み直すようにしました（1 分に 1 回まで）。ひとりひとりの「通知しない時間」やミュートしたプロジェクトが、ページを開いたときではなく今のとおりに出ます（ISS02931）。小さな読み出し（新しいルート `GET {id}/notify-channels`）で、課題は読みません。
- Hints shown once, where they fit (the button map): on the project home, for its manager, when the site's reminders are on and 10 or more open issues have a due date - 「リマインドを使うと、期限の前に担当者に知らせます」 with the way to the reminders; on the issue list, when 50 or more issues are listed and there is no saved condition yet - 「保存した条件」. Each closes with ✕ (or when used) and does not come back.
  場面に合うときだけ、1 回だけ出すヒントを足しました（ボタンの地図）：プロジェクトのホームで、マネージャーに、サイトのリマインドが使えて、期限のある未完了の課題が 10 件以上あるとき「リマインドを使うと…」と設定へのリンク。課題の一覧で、50 件以上並んでいて、保存した条件がまだないとき「保存した条件」。✕ で閉じるか使うと、もう出ません。
- Every project in one gantt (ISS00244, Exment 7.3 with exment#2298): the dashboard's 「すべてのプロジェクトのガント」 opens the projects' gantt view with all the issues the user may read, a project per group, with the diamond at the project's end date. The view is grouped by the parent record for this page only (not saved). On an Exment without the core's grouping by parent (7.2, or 7.3 before exment#2298) the link is not shown and the page goes back to the dashboard.
  すべてのプロジェクトを 1 つのガントで見られるようにしました（ISS00244、Exment 7.3 と exment#2298）。ダッシュボードの「すべてのプロジェクトのガント」で、見られる課題をプロジェクトごとにまとめて出し、プロジェクトの期限に ◆ を出します。プロジェクトのガントのビューを、このページのときだけ親データでまとめます（保存しません）。core が親データでまとめられない Exment（7.2、または exment#2298 より前の 7.3）では、リンクは出ず、ページはダッシュボードに戻ります。
- The top bar has one ⚙ 設定 menu (the button map): 通知の受け取り方, 全体の設定 (administrators) and the manual are in it instead of three items of their own. 「アップグレードが必要」 stays on the bar.
  上のバーに「⚙ 設定」のメニューを 1 つだけ置きました（ボタンの地図）。「通知の受け取り方」「全体の設定」（管理者だけ）「マニュアル」は、それぞれのボタンでなく、このメニューの中にあります。「アップグレードが必要」は今までどおりバーに出します。
- The issue list's own actions are in one 「⋯ ほかの操作」 menu (the button map): the four downloads (Excel / CSV, every column or the list's) and 「まとめて変更（表示中の課題を選ぶ）」, which ticks every issue on screen and opens the bulk change bar. The separate download link is gone from the toolbar.
  課題の一覧の操作を「⋯ ほかの操作」の 1 つのメニューにまとめました（ボタンの地図）：4 つのダウンロード（Excel・CSV、すべての項目か一覧の項目）と「まとめて変更（表示中の課題を選ぶ）」（表示中の課題すべてにチェックを付け、まとめて変更の行を出します）。ツールバーのダウンロードのリンクはなくしました。
- Each screen says what its buttons do (the button map): the box of the `?` key starts with 「この画面のボタン」 for the screen on show (home, issues, board, gantt, burndown, wiki, files, an issue), above the keys. The command palette (Ctrl+K) lists it too.
  画面ごとに、ボタンの説明を出すようにしました（ボタンの地図）。`?` のキーで出る窓の上に、今の画面（ホーム・課題・ボード・ガント・バーンダウン・Wiki・ファイル・課題の画面）の「この画面のボタン」を出します。コマンドの窓（Ctrl+K）からも開けます。
- Small fixes from the screen review: on a phone, the menus 「保存した条件」 and 「列」 of the issue list open at the left of their row instead of running off the left edge; the copy button under each key shows on the row the pointer is on (touch screens keep it on every row).
  画面の見直しで見つけた小さな直し：スマホで、課題の一覧の「保存した条件」と「列」のメニューが左にはみ出さないようにしました。キーの下のコピーのボタンは、マウスを乗せた行だけに出します（タッチの画面では今までどおり全部の行に出ます）。
- A comment sent also by mail or Teams keeps who got it, on a quiet line under the comment (「メールで知らせた: 山田、佐藤」 / 「Teams で知らせた: …」) (ISS02931). Written when the comment is posted; no notice of its own. Kept in exment_stars (types nt:mail, nt:teams), no new table.
  メールや Teams でも送ったコメントの下に、だれに送ったかを小さく出すようにしました（「メールで知らせた: 山田、佐藤」「Teams で知らせた: …」、ISS02931）。コメントを書いたときに残し、これ自体の通知は送りません。exment_stars（種類 nt:mail・nt:teams）に残し、新しいテーブルは作りません。
- Where each person gets a notice (bell, mail, Teams) is read in one place, `PortalNotifyPrefs::channelsFor()`, per person and event; for now it is each person's choice of the 「通知」 menu, as before. The settings per event and per project come next behind it.
  通知をどこに届けるか（ベル・メール・Teams）を、1 か所（`PortalNotifyPrefs::channelsFor()`）で、人と出来事ごとに決めるようにしました。今は前と同じく、ひとりひとりの「通知」のメニューの選び方です。出来事ごと・プロジェクトごとの設定は、このあとここに足します。
- かんたん / くわしく on an issue (the button map): 「かんたん」 in the line under the title shows only the subject, the status, the assignees, the due date, the text and the comments; 「くわしく」 brings back the rest (the other fields, children, attachments, related issues, who read it). くわしく until a person chooses かんたん, so nothing changes for anyone who does not; a one-time hint beside the switch (「表示を かんたん にできます」, closed with ✕) says it is there. Each person's choice is kept in their browser.
  課題の画面に「かんたん／くわしく」の切り替えを付けました（ボタンの地図）。タイトルの下の行の「かんたん」で、件名・状態・担当者・期限・本文・コメントだけにします。「くわしく」で、ほかの項目・子課題・添付・関連課題・読んだ人も出します。自分で「かんたん」を選ぶまでは「くわしく」のままなので、今の見え方は変わりません。切り替えの横に 1 回だけ「表示を かんたん にできます」と出します（✕ で閉じます）。どちらにしたかは、1 人ずつブラウザーに覚えます。
- Simple by default (schema 30): a new project starts with the basics only - issues, the board, comments and files. The Wiki and the chart (gantt and burndown) start off and are switched on in the project's 使うタブ. Every project there already is keeps what it has: the upgrade writes an empty switch as on before the default of the column turns off. Run the upgrade (設定 → アップグレード) after updating.
  はじめはかんたんに（スキーマ 30）：新しいプロジェクトは、課題・ボード・コメント・ファイルだけで始まります。Wiki とガント（バーンダウン）は OFF で始まり、プロジェクトの「使うタブ」で ON にします。今あるプロジェクトはそのままです（アップグレードで、空の設定を ON で保存してから、列の初期値を OFF にします）。更新のあと、アップグレード（設定 → アップグレード）を実行してください。
- Where each person's notice arrives (ISS02931): in the comment dock, each chip of 「知らせる人」 shows small icons for mail and Teams when that person gets the notice there too (their own 「通知の受け取り方」, or the site's default), with the words in its title, and a line under the chips names who gets it by mail and by Teams. Ticking 「メールでも知らせる」 or the new 「Teams でも知らせる」 (only where Exment can send to Teams) adds it for everyone of this one notice. After the post, the message says who was sent a mail and who a Teams message (「メールで知らせた: 山田」).
  コメント欄の「知らせる人」のチップに、その人にメールや Teams でも届くとき（その人の「通知の受け取り方」か、サイトの初期値）、小さなアイコンを出すようにしました。名前の上にマウスを乗せると言葉でも出ます。チップの下に、メールで届く人と Teams で届く人を 1 行で出します。「メールでも知らせる」と、新しい「Teams でも知らせる」（Exment が Teams に送れるときだけ）を付けると、その 1 回だけ全員に足します。送ったあとに、だれにメール・Teams で送ったかを出します（「メールで知らせた: 山田」）。
- The command palette (UX L2): Ctrl+K (⌘K on a Mac) on a project opens one box with every action of the screen and its key: the tabs, adding an issue, the search, and on an issue: next status, change the status, take it (you become an assignee, with undo), edit, comment, watch, favorite, copy the link, the one before / after. Typing narrows the list; a key or words of a subject also list the issues of the project. Only what is on the screen is offered (a tab that is switched off is not). The key list (?) shows Ctrl+K. Exment itself binds no Ctrl+K.
  コマンドの窓（UX L2）：プロジェクトの画面で Ctrl+K（Mac は ⌘K）を押すと、その画面でできることがキーといっしょに 1 つの窓に並びます。タブ・課題を追加・検索、課題の画面では 次の状態にする・状態を変える・自分を担当者にする（元に戻せます）・編集・コメント・ウォッチ・お気に入り・リンクをコピー・前の課題／次の課題。文字を打つと絞り込み、課題のキーや件名を打つとそのプロジェクトの課題も出ます。画面にないもの（使わないタブなど）は出しません。キーの一覧（?）にも Ctrl+K を足しました。Exment 本体は Ctrl+K を使っていません。
- The issue's actions in one place (the button map): the head keeps 「次の状態にする」 and 「編集」 as buttons; 子課題を追加, 注目, お気に入り and ウォッチ move into the ⋯ menu (ほかの操作), above quote, copy, link, mail, move and delete. What is on (注目, お気に入り, ウォッチ) shows as a small icon on the ⋯ button. The buttons have a title that says what they do.
  課題の操作を 1 か所にまとめました（ボタンの地図）。上の行のボタンは「次の状態にする」と「編集」だけにし、子課題を追加・注目・お気に入り・ウォッチは「⋯ ほかの操作」の中へ移しました（引用・コピー・リンク・メール・移動・削除の上）。注目・お気に入り・ウォッチが ON のときは、「⋯」のボタンに小さいアイコンが出ます。ボタンに説明（マウスを乗せると出る）を付けました。
- An issue can have an alias (ISS00248), for example EXMENT_AI_P1.1: letters, digits and `- . _ ~`, unique across every project whatever the case. Set with the tag button beside the key on the issue; the list shows the number of the key and, when an issue of the project has one, a 「キー別名」 column that sorts naturally; an address (`#issue-<alias>`, `/view/<alias>`) and the search find an issue by its alias, and by the key it had before it was moved to another project. A new table, pm_portal_issue_aliases.
  課題にキー別名（例：EXMENT_AI_P1.1）を付けられるようにしました（ISS00248）。英字・数字と「- . _ ~」だけで、全部のプロジェクトで 1 つだけ（大文字・小文字は同じと見ます）。課題の画面のキーの横のボタンで付けます。課題の一覧のキーは番号だけにし、キー別名のある課題があれば、そのとなりに並べ替えできる「キー別名」の列を出します。アドレス（`#issue-<別名>`・`/view/<別名>`）と検索で、キー別名でも、別のプロジェクトへ移す前のキーでも、課題が見つかります。新しいテーブル pm_portal_issue_aliases。
- Recurring schedules (ISS00432, stage 2a; off at first: 「プロジェクト管理：繰り返しの予定」 in Feature management). A project's manager opens 「繰り返しの予定」 in the project's settings: yearly, monthly (a day, the last day, the n-th weekday), weekly, daily, given dates, or an RRULE; on a day off the business day before or after, as it is, or not at all. So many days before each date, a template issue of the project is copied into a new issue due on it (`php artisan exment:batch --name=ExmentPM --param1=schedule`, and on page loads); one date can be moved or skipped. New tables pm_portal_schedules, pm_portal_schedule_exceptions, pm_portal_schedule_items.
  繰り返しの予定（ISS00432 の段 2a。初めは使いません。機能の管理の「プロジェクト管理：繰り返しの予定」）。プロジェクトの管理者が、プロジェクトの設定の「繰り返しの予定」で、毎年・毎月（日にち・最後の日・第何曜日）・毎週・毎日・決まった日付・RRULE で決めます。休みの日に当たったら、前の営業日・次の営業日・そのまま・作らない、から選べます。それぞれの日の何日か前に、ひな形の課題を写して、その日が期限の課題を作ります（`php artisan exment:batch --name=ExmentPM --param1=schedule`、ポータルを開いたときも）。1 回分だけ日付を動かす・休みにすることもできます。新しいテーブル pm_portal_schedules・pm_portal_schedule_exceptions・pm_portal_schedule_items。
- An issue body, a comment and a wiki page take up to 200,000 characters (more than Backlog's 100,000): the form's counter counts to it, a text longer is said before it is sent (the text stays), and the portal's saves refuse more. A save refused on its way by a firewall in front of the site (a 403 that is not Exment's, e.g. AWS WAF on localhost or ../ in the body) is said so, not as a missing permission.
  課題の本文・コメント・Wiki は 200,000 文字まで書けるようにしました（Backlog の 100,000 文字より多く）。入力欄の文字数はそこまで数え、こえたときは送る前に知らせます（書いた内容は残ります）。ポータルの保存でも、それより長いものはことわります。サイトの前のファイアウォール（AWS WAF など）が、本文の localhost や ../ で保存をことわったときは、「権限がありません」でなく、そのことを知らせます。
- The portal speaks the language of each user (Exment 7.2.5): its strings are now also in English, Vietnamese, Simplified Chinese, Korean, German and Spanish (`ExmentPM/resources/lang/<locale>/pmportal.php`, the same keys as `ja`). A language the plugin does not ship reads English, and a line missing in a language reads English, then Japanese. The names the setup and the upgrade write into the data (statuses, issue types, priorities, resolutions, notifications, column help, menu — the `schema` lines) stay Japanese, because the upgrade finds its rows again by those names. `project-management/tools/test-languages.php` checks the files (keys and placeholders as in `ja`) and the language each line is read in.
  ポータルが、ユーザーごとの言語で出るようになりました（Exment 7.2.5）。文言に、英語・ベトナム語・中国語（簡体字）・韓国語・ドイツ語・スペイン語を足しました（`ExmentPM/resources/lang/<言語>/pmportal.php`、キーは `ja` と同じ）。プラグインにない言語は英語で、ある言語で足りない文言は英語、その次に日本語で出ます。セットアップとアップグレードがデータに書く名前（状態・種別・優先度・完了理由・通知・列の説明・メニュー。`schema` の文言）は、今までどおり日本語です。アップグレードはその名前で行を探し直すためです。`project-management/tools/test-languages.php` で、ファイル（キーと置き換えの印が `ja` と同じか）と、どの言語で読むかを試せます。
- Tighter checks: the type templates of a new issue only for a project the user may read; the filters of the pickers only by a column that points at another table; "mail this issue" at most 30 times an hour a person; a link of the body that starts with "//" is not taken as a page of this site.
  チェックを強めました：新しい課題の種別のテンプレートは、見られるプロジェクトの分だけ。選ぶ画面の絞り込みは、ほかのテーブルを指す列だけ。「この課題をメールで送る」は 1 人 1 時間に 30 回まで。本文の「//」で始まるリンクは、このサイトのページとして扱わない。
- The download of the issue list makes CSV or Excel (.xlsx), with every column of an issue (people by name, the creator and the updater, the description, the related issues …) or the columns of the list, in the list's filters and order. The file is made by the server, with the caller's permissions; in Excel the dates are dates, the head row is frozen and has a filter.
  課題の一覧のダウンロードで、CSV と Excel（.xlsx）を選べるようにしました。すべての項目（担当者などは名前、作成者・更新者・詳細・関連課題も）か、一覧と同じ項目を、今の絞り込みと並び順で出します。ファイルはサーバーで、使う人の権限で作ります。Excel では日付を日付として出し、見出しの行を固定してフィルターを付けます。
- Opening a project you are not a member of (or one that is not there) shows a page that says so, with the way back to the top, instead of Exment's error page. The message of the migration that asks to replace the masters now names the option as the screen does.
  参加していないプロジェクト（またはないプロジェクト）を開いたときは、Exment のエラーの画面でなく、そのことと、トップへ戻るボタンを出すようにしました。移行で「置き換え」を選ぶように出るメッセージを、画面の選択肢と同じ言葉にしました。
- The tree of the issue list is easier to work: the ▼/▶ is a 28px target (36px on a touch screen); the right-click menu of a row in the tree opens or folds the row, everything below it, or the whole tree; on a focused ▼/▶, ← folds, → opens and ↑/↓ move to the next one.
  課題の一覧のツリーを使いやすくしました。▼／▶ の押せる範囲を 28px（タッチの画面は 36px）に広げました。ツリーの行の右クリックのメニューで、その行・その下のすべて・ツリー全体を開いたりたたんだりできます。▼／▶ を選んで ← でたたむ、→ で開く、↑／↓ でとなりへ動けます。
- A new page lists the issues of every project the user can read (「すべてのプロジェクトの課題」, linked from the head of 「自分の課題」 on the top page): filters by project, assignee (「自分」 too), status (「完了以外」 too), priority, type — each with several values — the due date and words of the subject; sorted by due date, priority, update or number; 50 a page, filtered and paged on the server; the conditions are in the address and can be saved by name in the browser.
  見られるすべてのプロジェクトの課題を 1 つの一覧で見られるページを足しました（トップの「自分の課題」の見出しから）。プロジェクト・担当者（「自分」も）・状態（「完了以外」も）・優先度・種別をそれぞれ複数選んで、期限・件名のことばでも絞り込めます。期限・優先度・更新日時・番号で並べ替えられます。1 ページ 50 件で、絞り込みとページ分けはサーバーで行います。条件はアドレスに入り、名前を付けてブラウザーに保存できます。
- The list across the projects downloads as Excel or CSV too (every column or the list's, with the project of each issue first), with its filters and order.
  すべてのプロジェクトの課題も、Excel・CSV でダウンロードできます（すべての項目か一覧と同じ項目。先頭にプロジェクト）。今の絞り込みと並び順で出します。
- The Wiki tree folds too, with the same ▼/▶, right-click menu and keys; the folded pages are remembered, and the page shown is never hidden. The right-click menu of an issue row has 「子課題を追加」, 「親課題を変える（編集を開く）」 and 「課題のリンクをコピー」.
  Wiki のツリーも、同じ ▼／▶・右クリックのメニュー・キーで開いたりたたんだりできます（たたんだページは覚えます。開いているページは隠れません）。課題の行の右クリックのメニューに「子課題を追加」「親課題を変える（編集を開く）」「課題のリンクをコピー」を足しました。
- Favorite issues: 「お気に入り」 on the issue's detail and in the right-click menu of a row marks an issue for oneself only (nobody else sees it, nor how many have it). 「お気に入りの課題」 on the top page, or 「お気に入りだけ」 on the list across the projects, lists them.
  お気に入りの課題: 課題の詳細の「お気に入り」と、行の右クリックのメニューで、自分だけの印を付けられます（ほかの人には見えず、数も出ません）。トップの「お気に入りの課題」か、すべてのプロジェクトの課題の「お気に入りだけ」で一覧にできます。
- More of Markdown in issue bodies, comments and wiki pages: lists inside list items (by indent), `+` bullets, numbered lists that start at another number (`3.`, `1)`), quotes of several lines and quotes inside quotes, `__bold__` / `_italic_` / `***both***` (not inside words such as snake_case), link titles (`[text](url "title")`), `<https://...>`, table column alignment (`:---:`), backslash escapes (`\*`, `\#` ...), and the language of a code fence (```` ```js ````) is no longer shown as its first line. Everything typed is still escaped first: HTML and scripts stay text, and every link opens in a new tab with `rel="noopener"`.
  課題の本文・コメント・Wiki の Markdown で書けることを増やしました：項目の中のリスト（字下げ）、`+` の箇条書き、ほかの番号から始まる番号つきリスト（`3.`・`1)`）、何行もの引用と引用の中の引用、`__太字__`・`_斜体_`・`***両方***`（snake_case のような語の中ではそのまま）、リンクのタイトル（`[文字](URL "タイトル")`）、`<https://...>`、表の列のそろえ（`:---:`）、バックスラッシュでの打ち消し（`\*`・`\#` など）。コードの囲み（```` ```js ````）の言語名は、コードの 1 行目に出なくなりました。書いたものは今までどおり先にエスケープします（HTML・スクリプトは文字のまま、リンクは新しいタブで `rel="noopener"`）。
- A file that is not a picture, pasted or dropped on the body of a saved issue, is attached to the issue and a link to it is put at the caret (as the comment box already did). A picture goes as before. On a new issue or a wiki page, a message says to attach it with 「ファイル」.
  保存してある課題の本文に、画像でないファイルを貼る・落とすと、その課題に添付して、本文にリンクを入れます（コメント欄は前からできていました）。画像は今までどおりです。新しい課題と Wiki では、「ファイル」から添付するよう案内します。
- The form of an issue shows the time of the due date right under the start and due dates (it was folded in 「その他の項目」, and was not found when an issue was added).
  課題のフォームで、期限日の時刻を、開始日・期限日のすぐ下に出すようにしました（「その他の項目」の中にたたまれていて、課題を追加するときに見つからなかった）。
- A person named on a project (manager, member, viewer) or on an issue (assignee) gets its share on every save, also when they are not in a role group of ExmentPM yet: before, a project made before its people were put in the role groups could not be opened by them later. For the projects and issues there are, run once `php artisan exment:batch --name=ExmentPM --param1=shares` (`--param3=dry` only counts).
  プロジェクトの管理者・メンバー・閲覧者や、課題の担当者に入れた人には、保存のたびに共有を付けるようにしました（役割グループに入る前の人にも）。前は、役割グループに入れる前に作ったプロジェクトを、あとから開けませんでした。今あるプロジェクトと課題には、1 回だけ `php artisan exment:batch --name=ExmentPM --param1=shares` を実行してください（`--param3=dry` で数えるだけ）。
- The issue screen is easier to read (UX proposal S1, S2, S17, S19, M1). **One info box**: from 1200px wide (it was 1800px) the issue's fields stand in one box beside the text, status, assignees, due date and priority first, and the strip under the title keeps only who read it and the way to the comments. **In-place edit**: a value of the box (assignees, priority, milestone, category, start, due date with its time, progress, hours, roles) opens its editor in place when clicked, saved through the same row API as the comment box, with 今日・明日・来週 for the due date, 自分にする for the assignees, and 元に戻す in the message for a few seconds; fields not set yet are listed under 未設定 to set them. **Phones**: the comments come right after the text, the info, children, files and related boxes come after them, folded, with their counts; the strip keeps the status, the assignees and the due date. **Comment box**: the status stays beside the comment, the other fields fold under 「ほかの項目も変える」 (closed at first), and the due time sits beside the due date. **Breadcrumbs** (プロジェクト管理 › project › 課題 › key) replace the green 「課題一覧へ」 buttons at the top and the foot. Labels: 基本情報 → 課題の情報, 課題を変更 → ほかの項目も変える.
  課題の画面を読みやすくしました（UX の案 S1・S2・S17・S19・M1）。**情報は 1 つの箱に**：幅 1200px 以上（これまでは 1800px 以上）で、課題の項目を本文の右の 1 つの箱にまとめ、上から状態・担当者・期限日・優先度の順に出します。タイトルの下の帯には、読んだ人とコメントへのリンクだけを残します。**その場で変える**：箱の値（担当者・優先度・マイルストーン・カテゴリー・開始日・期限日と時刻・進捗率・時間・役割）を押すと、その場で変えられます。保存はコメント欄と同じしくみ（行の API）です。期限日には「今日・明日・来週」、担当者には「自分にする」があり、保存のあと数秒「元に戻す」を出します。まだ値のない項目は「未設定」の行から設定できます。**スマホ**：コメントを本文のすぐ下に出し、課題の情報・子課題・添付・関連課題はそのあとに、閉じた状態（件数つき）で出します。帯には状態・担当者・期限日だけを出します。**コメント欄**：状態はコメントのすぐ下に残し、ほかの項目は「ほかの項目も変える」の中（はじめは閉じています）。期限の時刻は期限日のとなりに置きました。**パンくず**（プロジェクト管理 › プロジェクト › 課題 › キー）を出し、上と下の緑の「課題一覧へ」はなくしました。言葉：基本情報 → 課題の情報、課題を変更 → ほかの項目も変える。
- The issue screen is easier to read (UX proposal S1, S2, S17, S19, M1). **One info box**: from 1200px wide (it was 1800px) the issue's fields stand in one box beside the text, status, assignees, due date and priority first, and the strip under the title keeps only who read it and the way to the comments. **In-place edit**: a value of the box (assignees, priority, milestone, category, start, due date with its time, progress, hours, roles) opens its editor in place when clicked, saved through the same row API as the comment box, with 今日・明日・来週 for the due date, 自分にする for the assignees, and 元に戻す in the message for a few seconds; fields not set yet are listed under 未設定 to set them. **Phones**: the comments come right after the text, the info, children, files and related boxes come after them, folded, with their counts; the strip keeps the status, the assignees and the due date. **Comment box**: the status stays beside the comment, the other fields fold under 「ほかの項目も変える」 (closed at first), and the due time sits beside the due date. **Breadcrumbs** (プロジェクト管理 › project › 課題 › key) replace the green 「課題一覧へ」 buttons at the top and the foot. Labels: 基本情報 → 課題の情報, 課題を変更 → ほかの項目も変える. An issue opened straight from outside (Exment's bell "データがコメントされました", a mail) with no comment in its address, while it was unread, scrolls to the newest comment of somebody else and marks it, as a comment link does.
  課題の画面を読みやすくしました（UX の案 S1・S2・S17・S19・M1）。**情報は 1 つの箱に**：幅 1200px 以上（これまでは 1800px 以上）で、課題の項目を本文の右の 1 つの箱にまとめ、上から状態・担当者・期限日・優先度の順に出します。タイトルの下の帯には、読んだ人とコメントへのリンクだけを残します。**その場で変える**：箱の値（担当者・優先度・マイルストーン・カテゴリー・開始日・期限日と時刻・進捗率・時間・役割）を押すと、その場で変えられます。保存はコメント欄と同じしくみ（行の API）です。期限日には「今日・明日・来週」、担当者には「自分にする」があり、保存のあと数秒「元に戻す」を出します。まだ値のない項目は「未設定」の行から設定できます。**スマホ**：コメントを本文のすぐ下に出し、課題の情報・子課題・添付・関連課題はそのあとに、閉じた状態（件数つき）で出します。帯には状態・担当者・期限日だけを出します。**コメント欄**：状態はコメントのすぐ下に残し、ほかの項目は「ほかの項目も変える」の中（はじめは閉じています）。期限の時刻は期限日のとなりに置きました。**パンくず**（プロジェクト管理 › プロジェクト › 課題 › キー）を出し、上と下の緑の「課題一覧へ」はなくしました。言葉：基本情報 → 課題の情報、課題を変更 → ほかの項目も変える。外から（Exment のベルの「データがコメントされました」やメールから）まだ読んでいない課題を直接開いたとき、アドレスにコメントの指定がなければ、ほかの人のいちばん新しいコメントまで移って目立たせます（コメントのリンクと同じ）。
- Custom attributes per project (ISS02939): a project adds its own fields to its issues - text, number (unit, decimals), date, one choice, several choices, users - in the project settings 「カスタム属性」 (its manager). They show in the issue form and details, as list columns with filters and sorting, in bulk edit and in the Excel/CSV export; a move keeps the ones the other project has too. The values are kept on the issue (the column pm_attributes, JSON), so the history shows each change. Off at first: the project switch 「カスタム属性」.
  プロジェクトごとのカスタム属性（ISS02939）：プロジェクトの課題に、項目を足せます（文字・数（単位・小数）・日付・1 つ選ぶ・いくつか選ぶ・ユーザー）。プロジェクトの設定の「カスタム属性」で、責任者が決めます。課題の入力画面・詳細・一覧の列（絞り込み・並べ替え）・まとめて変更・Excel／CSV に出ます。別のプロジェクトへ移すと、同じ名前と種類の項目は値を引き継ぎます。値は課題に保管するので、履歴に変更が残ります。初めは使いません（プロジェクトのスイッチ「カスタム属性」）。
- Chat notifications per project (ISS02939): new issues, status changes and comments go to Slack, a Teams channel, LINE WORKS, Chatwork or a webhook (JSON, optionally signed with HMAC-SHA256), chosen per channel in the project settings 「チャットへのお知らせ」 by the manager. Off at first: Feature management 「プロジェクト管理：チャットへのお知らせ」. URLs and tokens are encrypted and never shown; more than 6 issues changed by one action come as one message; nothing is sent from batches or imports. The reminders' channels (ISS00432) are here too: a channel ticked 「期限のリマインド」 gets the reminders, and the channels a project's reminder page had are moved here (each keeps what it was sent, so nothing comes twice); the reminder page links to this tab.
  プロジェクトごとのチャットへのお知らせ（ISS02939）：課題の登録・状態の変更・コメントを、Slack・Teams のチャンネル・LINE WORKS・Chatwork・Webhook（JSON、HMAC-SHA256 の署名も）に知らせます。プロジェクトの設定の「チャットへのお知らせ」で、責任者がチャンネルごとに選びます。初めは使いません（機能の管理の「プロジェクト管理：チャットへのお知らせ」）。URL とトークンは暗号にして保管し、画面に出しません。1 回の操作で 6 件より多く変わったときはまとめて 1 通、バッチや取り込みからは送りません。リマインド（ISS00432）のチャンネルもここで決めます。「期限のリマインド」にチェックを入れたチャンネルにリマインドを送ります。リマインドの画面にあったチャンネルは、ここに移します（送ったものは覚えているので、2 回は送りません）。
- Issues linked to Exment records (ISS02939): a project chooses which Exment tables its issues may link to (project settings 「関連するデータ」, none at first, so nothing shows). The issue detail gets a 「関連するデータ」 box with a search picker of records the user may read, each shown with its title and a link (or 「見られないデータ」). On a linked record's own page, a 「関連する課題」 box lists the issues the user may read. The plugin now also has the type "script" for that small script, which only runs on record pages of linked tables.
  課題と Exment のデータをつなげます（ISS02939）：プロジェクトの設定の「関連するデータ」で、つなげるテーブル（お客様・契約・資産など）を選びます（初めは選ばれていないので、何も出ません）。課題の詳細の「関連するデータ」で、見られるデータを探してつなげ、名前とリンクを出します（見られないものは「見られないデータ」）。つながったデータの画面にも「関連する課題」を出します。そのための小さいスクリプトのため、プラグインの種類に「スクリプト」を足しました（つなげたテーブルのデータの画面だけで動きます）。
- Morning digest (ISS02939): once a day, at the time each person chose, one message by mail or Teams with their issues due today or overdue, issues newly assigned to them, @mentions of them, and the status changes of issues they watch. Off twice over: the site turns it on (Feature management 「プロジェクト管理：朝のまとめ」), and each person in their notice settings. An empty digest is not sent; quiet hours do not stop it. With the AI of the AI auto-fill and the plugin option 「朝のまとめに AI のおすすめを入れる」 (off), one line says which issue to start with. Batch: `php artisan exment:batch --name=ExmentPM --param1=digest` every 15 minutes.
  朝のまとめ（ISS02939）：1 日 1 回、ひとりひとりが決めた時刻に、今日が期限・期限切れの課題、新しく担当になった課題、自分あてのメンション、ウォッチしている課題の状態の変更を、メールか Teams で 1 通にまとめて送ります。サイトが「機能の管理」でオンにし、ひとりひとりが「通知の受け取り方」でオンにします（どちらも初めはオフ）。何もない日は送りません。夜は送らない時間でも、決めた時刻に送ります。AI 自動入力の AI があり、プラグインの設定「朝のまとめに AI のおすすめを入れる」（初めはオフ）なら、最初にやる課題を 1 行で出します。

- Moving around (UX proposal S3, S4, S5, S7, S8, M2, M3). **One 「課題の追加」**: the button in the top bar; inside a project it opens that project's form at once and ▾ beside it picks another project; the green button in the tab row is gone, and the key `a` presses the top bar's. **Settings**: the top bar's 「設定」 is now 「全体の設定」 (the portal; administrators); a project's settings open from the gear beside its name; in them 「利用する機能」 is 「使うタブ」, second in the list. **「通知」 → 「通知の受け取り方」** with a sliders icon (it is a setting; the bell is Exment's); the Gantt tab gets its own icon. **Switching projects**: click the project's name to open the list of projects; the next project opens on the same tab. **Phones**: the top bar is one line (☰ for the other entries, the project, 🔍, the bell when Exment's header hides it, ＋); the project head is one line with ⓘ for the dates, manager, note and people; the tabs are one row that scrolls sideways, without the tab row's search and settings tab. The top bar stays on one line from 1200px (the three entries on its right keep only their icons below 1600px).
  移動を楽にしました（UX の案 S3・S4・S5・S7・S8・M2・M3）。**「課題の追加」は 1 つに**：上のバーのボタンだけにしました。プロジェクトの中では、そのプロジェクトの作成画面をすぐ開き、となりの ▾ でほかのプロジェクトを選べます。タブの行の緑のボタンはなくし、キーの `a` は上のバーのボタンを押します。**設定**：上のバーの「設定」は「全体の設定」（ポータル全体、管理者だけ）にしました。プロジェクトの設定は、プロジェクト名のとなりの歯車から開きます。設定の中の「利用する機能」は「使うタブ」にして、上から 2 番目に置きました。**「通知」→「通知の受け取り方」**：設定なので、アイコンを鈴から変えました（鈴は Exment のお知らせ）。ガントのタブのアイコンも変えました。**プロジェクトの切り替え**：プロジェクト名を押すとプロジェクトの一覧が開き、同じタブのまま別のプロジェクトへ行けます。**スマホ**：上のバーを 1 行にしました（☰ でほかの項目、プロジェクト、🔍、Exment のヘッダーに鈴がないときは鈴、＋）。プロジェクトの見出しは 1 行で、期間・マネージャー・説明・メンバーは ⓘ で出します。タブは横に流れる 1 行にし、タブの行の検索と設定のタブは出しません。上のバーは 1200px 以上で 1 行に収めます（1600px より狭いときは、右の 3 つはアイコンだけ）。

- The list, the board and the Gantt chart (UX proposal S9, S10, S11, S12, S18, M4, M7, M8). **List**: a cell that can be changed in place shows a dotted edge and a pencil on hover; one press on the pencil opens the editor (double-click still works); a first visit is told once under the list. **Status**: the status of an issue (the info box and the strip) opens a menu of every status of the project, sent like 「次の状態にする」 (the comment, which notifies); the key `s` opens it. **自分が担当**: the list's chip 「自分の課題」 is 「自分が担当」 and, while it is on, links to the dashboard for every project; the dashboard's column 「自分」 is 「自分が担当」. **Dashboard**: project names get more room (the key column is narrower, a name may take two lines) and the column heads say what they count on hover. **Board**: the quick add shows in the column under the pointer only (not on touch screens); while there is more to the right the edge fades and ‹ › move sideways; on a phone one status column at a time, with a row of the columns to jump to (only inside the portal). **Gantt on a phone**: a note that it scrolls sideways, and 「期限の順にリストで見る」 (the open issues by due date).
  一覧・ボード・ガントを使いやすくしました（UX の案 S9・S10・S11・S12・S18・M4・M7・M8）。**一覧**：その場で変えられるセルは、マウスを乗せると点線と鉛筆が出ます。鉛筆を 1 回押すと変えられます（ダブルクリックも使えます）。はじめて開いたときに一度だけ、一覧の下に案内を出します。**状態**：課題の状態（右の箱と帯）を押すと、プロジェクトの状態の一覧が出て、選ぶと「次の状態にする」と同じしくみ（コメント、お知らせつき）で変わります。キー `s` でも開きます。**自分が担当**：一覧のチップ「自分の課題」は「自分が担当」にし、押しているあいだは「すべてのプロジェクトで見る」（ダッシュボード）へのリンクを出します。ダッシュボードの列「自分」も「自分が担当」にしました。**ダッシュボード**：プロジェクト名が切れにくいようにしました（キーの列をせまく、名前は 2 行まで）。列の見出しにマウスを乗せると、何を数えているかを出します。**ボード**：「クイック追加」はマウスを乗せた列だけに出します（タッチの画面では出しません）。右に続きがあるときは右の端を薄くし、‹ › で横に動けます。スマホでは状態の列を 1 つずつ見せ、上の列の一覧から選べます（ポータルの中だけ）。**スマホのガント**：横に動かせることと、「期限の順にリストで見る」（完了以外の課題を期限の順に）を出します。

- Words, colours, keys and speed (UX proposal S13, S14, S15, S16, M5, M9, M10). **「最近の更新」** of a project is no longer built with the page: the home reads it afterwards, ten at a time, with 「もっと見る」 for the next ten (new route `GET {id}/feed?before=&limit=`). Each source is read newest first by its kind and kept for this project, so no query names every issue of the project; the answer is kept (cache) until a revision, comment or file is added, and with `APP_DEBUG` the answer carries a `Server-Timing` header. The dashboard's 「最近の更新」 shows ten and the rest behind 「もっと見る」, and is kept the same way. On Exment 7.3 the new index `revisions (revisionable_type, created_at)` makes the read cheap. **Colours**: with Exment's modern design on, the portal takes the site's accent, text and line colours (`--exm-ui-*`); with the classic design it keeps its green. The green written straight into the styles (about 60 places) now goes through the variables. Ways back (‹ プロジェクト一覧へ) are quiet grey buttons. **Keys**: `?` lists them; in the list `j` / `k` move and `o` opens; on an issue `e` edits, `c` writes a comment, `[` / `]` go to the issue before / after, `s` changes the status. **Words (ja)**: 課題を追加, 期限, 作成者, 送信, 最新にする, 更新日時, プロジェクトの設定, ほかの操作, ほかの項目, コメントと履歴, 知らせる人, 読んだ人, 予定時間 / 実績時間, 期限切れ, 「n 日過ぎ」; the Japanese written in the views moved to the language file. **When there is nothing yet**: a project with no issue shows the first steps (add an issue, invite members, see the statuses); a list emptied by its filters says so with 「絞り込みを消す」; the dashboard without projects offers to make one and the manual, and a newcomer sees 「はじめの 3 つ」 (closed for good per browser).
  言葉・色・キー・速さ（UX の案 S13・S14・S15・S16・M5・M9・M10）。**「最近の更新」**は、ページといっしょには作らず、ホームを出したあとに 10 件ずつ読みます（「もっと見る」で次の 10 件。経路 `GET {id}/feed?before=&limit=` を足しました）。種類ごとに新しい順に読んでこのプロジェクトの分だけを残すので、プロジェクトの全部の課題を名指しする問い合わせはしません。履歴・コメント・ファイルが増えるまでは、読んだ結果をとっておきます。`APP_DEBUG` のときは `Server-Timing` を付けます。ダッシュボードの「最近の更新」も、はじめは 10 件で残りは「もっと見る」の中、同じようにとっておきます。Exment 7.3 の索引 `revisions (revisionable_type, created_at)` があると、さらに軽くなります。**色**：Exment のモダンのデザインでは、サイトの色（`--exm-ui-*`）を使います。クラシックでは今までの緑のままです。直接書かれていた緑（約 60 か所）も変数にしました。戻るボタン（‹ プロジェクト一覧へ）は灰色の静かなボタンにしました。**キー**：`?` で一覧を出します。一覧では `j` / `k` で上下、`o` で開く。課題の画面では `e` 編集、`c` コメント、`[` / `]` 前後の課題、`s` 状態を変える。**言葉**：課題を追加・期限・作成者・送信・最新にする・更新日時・プロジェクトの設定・ほかの操作・ほかの項目・コメントと履歴・知らせる人・読んだ人・予定時間 / 実績時間・期限切れ・「n 日過ぎ」。画面に直接書かれていた日本語は言葉のファイルに移しました。**何もないとき**：課題がまだないプロジェクトでは、はじめにすること（課題を追加・メンバーを招く・状態を見る）を出します。絞り込みで 0 件のときは「絞り込みを消す」を出します。プロジェクトがないダッシュボードでは、プロジェクトを作るとマニュアルへのリンクを出し、はじめての人には「はじめの 3 つ」を出します（「もう出さない」で、そのブラウザーでは出しません）。
- A change in the history of an issue can be hidden (a value put in by mistake): 「この変更を隠す」 under the change, for the one who made it and for the manager of the project. It is kept in Exment's revisions, marked deleted (who and when); the portal's history and recent updates no longer show it.
  課題の履歴の変更を隠せるようにしました（まちがえて入れた値など）。変更の下の「この変更を隠す」で、変更した本人とプロジェクトの管理者が使えます。Exment の変更履歴には、消した印（だれが・いつ）を付けて残します。ポータルの履歴と最近の更新には出しません。
- A person who is in a project only through a share (for example a manager given by a role group) gets the check for new updates of the project and the read marks of issues: those requests answered 403 for them (and so will the recent updates once they are loaded after the page).
  共有だけでプロジェクトに入っている人（役割グループで管理者になった人など）でも、プロジェクトの更新の確認と課題の既読の印が動くようにしました（その読み込みが 403 になっていました。ページのあとに読む「最近の更新」も同じです）。
  **For the whole of 200,000, raise Exment's own limit of a text column** in `.env`: `EXMENT_CHAR_LENGTH_LIMIT_COUNT=200000` (63,999 when not set; the portal counts to the lower of the two). The comments are sent as JSON (a Japanese character is about 3 bytes there), so the web server's body limit (nginx `client_max_body_size`, 1 MB by default) takes a 200,000-character comment.
  **200,000 文字まで書くには、Exment の文字の上限も上げてください**：`.env` に `EXMENT_CHAR_LENGTH_LIMIT_COUNT=200000`（書いていないと 63,999。ポータルは小さいほうまで数えます）。コメントは JSON で送るので（日本語 1 文字 約 3 バイト）、Web サーバーの上限（nginx の client_max_body_size、既定 1 MB）のままでも 200,000 文字のコメントが送れます。
- Stage 2b of the schedules: 「予定のカレンダー」 (a month, a year) of a project's schedules, the days off shaded; 「ここから先を変える」 on a date (the schedule ends the day before, a new one starts on it, the issues made from it on go with the new one); 「会社の休みと休日出勤」 for administrators (the site's days off and working days, used with the holidays of Japan wherever business days are counted). A new table, pm_portal_calendar_days.
  繰り返しの予定の段 2b：プロジェクトの「予定のカレンダー」（月・年。休みの日に色）、日付ごとの「ここから先を変える」（今の予定はその前の日で終わり、その日から新しい予定。その日からあとに作った課題は新しい予定のもの）、管理者の「会社の休みと休日出勤」（サイト全体。営業日を数えるところで、日本の祝日といっしょに使います）。新しいテーブル pm_portal_calendar_days。
- The filters of the issue list (assignee, type, priority, milestone, category) take several values: 「複数選ぶ…」 at the end of each opens a list to tick; the filter then shows how many are chosen. The URL and the saved conditions keep them.
  課題の一覧の絞り込み（担当者・種別・優先度・マイルストーン・カテゴリー）で、いくつも選べるようにしました。それぞれのいちばん下の「複数選ぶ…」で一覧が開き、チェックを付けて選びます。選んだ数が絞り込みに出ます。URL と保存した条件にも残ります。
- Reminders before the due date (ISS00432, off at first: 「プロジェクト管理：期限のリマインド」 in Feature management, or the plugin setting on an Exment without it). A bell, then the mail or Teams each person chose: so many days before (in business days if wanted; a day off moves it to the business day before), on the morning of the due date, every business day once overdue; to the assignees, sub-assignees, managers or watchers who can read the issue. Set in the plugin settings; sent by `php artisan exment:batch --name=ExmentPM --param1=remind` (every 15 minutes from cron; `--param3=dry` only counts) and on page loads. Never twice (a new table, pm_portal_reminder_logs).
  期限のリマインドを足しました（ISS00432。初めは使いません。機能の管理の「プロジェクト管理：期限のリマインド」か、機能の管理のない Exment ではプラグインの設定で使うにします）。ベルと、ひとりひとりが選んだ方法（メール・Teams）で、期限の何日前（営業日で数えることもできます。休みの日に当たったら前の営業日）・期限の日の朝・期限を過ぎたら営業日に毎日、知らせます。送る相手は、その課題を見られる担当者・副担当者・責任者・ウォッチしている人です。決まりはプラグインの設定で決めます。cron で `php artisan exment:batch --name=ExmentPM --param1=remind` を 15 分ごとに動かしてください（`--param3=dry` で数えるだけ）。ポータルを開いたときにも送ります。同じ知らせは 2 回送りません（新しいテーブル pm_portal_reminder_logs）。
- The reminders of a project (ISS00432, stage 1b): its manager opens 「リマインド」 in the project's settings to turn them off for the project, give it a rule of its own, and send them to Slack or Teams channels (incoming webhooks) or LINE WORKS channels (Exment 7.3) as well. The webhook URLs are kept encrypted and never shown again; an address of the server itself or of the internal network is refused. A new table, pm_portal_reminder_rules.
  プロジェクトごとのリマインド（ISS00432 の段 1b）：プロジェクトの管理者は、プロジェクトの設定の「リマインド」で、このプロジェクトでは送らない・このプロジェクトの決まりを使う・Slack や Teams のチャンネル（受信 Webhook）や LINE WORKS のチャンネル（Exment 7.3）にも送る、を決められます。Webhook の URL は暗号にして保存し、二度と画面に出しません。サーバー自身や社内のアドレスは受け付けません。新しいテーブル pm_portal_reminder_rules。

- A shorter issue form (UX proposal S6, M11, M12). **New issue**: under the title and the text it asks for the assignees, the due date (and its time) and the priority; the status (it starts at the first one), milestone, category, start and the other fields are folded under 「ほかの項目」, which has a search box when it holds more than six. A required field is never folded. The edit form keeps its layout. **Phones**: the buttons of the form's head wrap to a line of their own, so 「保存」 is not pushed off the screen. **Who to tell** (the comment box): the assignees and the author of the issue come first, then the people picked before in this project; nobody is switched on by itself.
  課題のフォームを短くしました（UX の案 S6・M11・M12）。**新しい課題**：タイトルと説明の下は、担当者・期限日（と時刻）・優先度だけにし、状態（はじめの状態のまま）・マイルストーン・カテゴリー・開始日などは「ほかの項目」の中に入れました。6 つより多いときは、その中を名前で探せます。必須の項目はたたみません。編集のフォームは今までどおりです。**スマホ**：フォームの上のボタンを 2 行目に折り返し、「保存」が画面の外に出ないようにしました。**知らせる人**（コメント欄）：課題の担当者と作成者を先頭に、次にこのプロジェクトで前に選んだ人を出します。はじめから選ばれている人はいません。

**Changed in 1.7.18 / 1.7.18 で変わったこと**

- The issue screen and the list, after trying them out (ISS02931). **Project settings**: a 「プロジェクトの設定」 button beside the project's name opens the settings tab, for the people who may change them (the same rule as the tab). **Sub-assignees, managers, reviewers and difficulty** (schema 27) are on the issue's summary row beside the assignees, set or not, each with a pencil to change it in place; before, they were shown only once set, in the side box, and set only in the form under "more". The list can show them as columns (表示する列, off at first, kept per browser) and filter by them (役割: a person in one role, and 難易度; in the URL and the saved filters like the other filters). **Hours and notices on the issue side**: the side box always shows 予定 / 実績 and 通知 (お知らせ先, the watch count, メールで知らせる), and the hours and お知らせ先 have a pencil too. The pencils save through the same row API as the comment box, with the same checks. **Comment links**: every comment has a リンク button that copies its address (`#issue-KEY?c=<id>`; a bare `#comment-<id>` works too and is turned into it); opening it scrolls to the comment and marks it, and the bells, mails and Teams cards of a new comment link to the comment itself. New route `GET {id}/comment/{comment_id}`. Works on Exment 7.2 and 7.3; an install without the schema 27 columns simply does not show them.
  使ってみて気づいたことを直しました（ISS02931）。**プロジェクトの設定**：プロジェクト名のとなりに「プロジェクトの設定」ボタンを置きました（設定を変えられる人だけ。設定タブと同じ決まり）。**副担当者・管理者・レビュアー・難易度**（スキーマ 27）を、課題の上の行の担当者のとなりに、空でも出すようにし、鉛筆のボタンでその場で変えられるようにしました。これまでは、値があるときだけ右の欄に出て、変えるのはフォームの「もっと見る」の中だけでした。課題一覧では列として出せます（「表示する列」、初めは出さない・ブラウザーごとに覚える）。絞り込みも足しました（「役割」：その役割の人、「難易度」。URL と保存した条件に残ります）。**課題の側に時間と通知**：右の欄に、予定 / 実績と「通知」（お知らせ先・ウォッチの人数・メールで知らせる）をいつも出し、時間とお知らせ先も鉛筆で変えられます。保存は、コメントの欄と同じしくみ（行の API）で、同じ確認をします。**コメントのリンク**：コメントごとに「リンク」ボタンを置き、URL（`#issue-KEY?c=<id>`。`#comment-<id>` でも開けます）をコピーできます。開くと、そのコメントまで移って目立たせます。新しいコメントのお知らせ（ベル・メール・Teams）も、そのコメントへのリンクにしました。経路 `GET {id}/comment/{comment_id}` を足しました。Exment 7.2・7.3 で動きます。スキーマ 27 の項目がないときは出しません。
- The date, date-and-time and time fields of the portal get their calendar again on Exment 7.3. Exment 7.3 (admin-core 2.0) uses flatpickr instead of the old picker, and the fields had become plain text boxes (they still took a typed date). Where the old picker is there (Exment 7.2), nothing changes. On flatpickr, a date is cleared by emptying the field.
  Exment 7.3 で、ポータルの日付・日時・時刻の入力に、カレンダーがまた出るようにしました。Exment 7.3（admin-core 2.0）は、前の日付の選び方のかわりに flatpickr を使うため、ふつうの文字の入力になっていました（日付を打ちこむことはできました）。前の選び方がある Exment 7.2 では、何も変わりません。flatpickr では、入力を空にすると日付が消えます。
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

## Git link (webhook) / Git とつなぐ

Off at first: turn on **Git link (webhook)** in **Admin settings → Feature management**, and **Git** in the project's features. Then the manager connects a repository in **project settings → Git**: GitHub, GitLab or Gitea, `owner/name`. Put the webhook address and the secret shown there into the repository's webhook (events: push and pull / merge requests; content type JSON). Commits and pull requests that name an issue key (`PROJ-12`, or the issue's own key such as `ISS00001`, as **Copy branch name** makes it) or an issue's alias then show on that issue. With **Mark done on merge** on for a repository, a merged pull request that says `fixes KEY`, `closes KEY` or `resolves KEY` moves that issue to the project's first done status, with a line in its history (never an issue already done, only once per pull request, not for "not fixes KEY", and not when the issue table has a workflow: its steps are kept). The first 20 issues named, in the order written, are linked and may be closed. Nothing is sent to the Git server; the secret is shown once only (make a new one if it is lost).
最初は使いません。**管理者設定 → 機能の管理** で **Git とつなぐ（Webhook）** を、プロジェクトの機能で **Git** をオンにします。プロジェクトの管理者が **プロジェクトの設定 → Git** でリポジトリー（GitHub・GitLab・Gitea、`owner/name`）をつなぎ、出てきた Webhook のアドレスと秘密の文字を、リポジトリーの Webhook に入れます（イベントは push とプルリクエスト／マージリクエスト、形式は JSON）。課題のキー（`PROJ-12`、または `ISS00001` のような課題の番号。「ブランチ名をコピー」もこれを使います）や別名を書いたコミット・プルリクエストが、その課題の履歴に出ます。リポジトリーごとの **マージで処理済みにする** をオンにすると、`fixes キー`・`closes キー`・`resolves キー` を書いたプルリクエストがマージされたとき、その課題をプロジェクトのはじめの「完了」の状態にして、履歴に 1 行のこします（もう完了の課題はそのまま、1 つのプルリクエストで 1 回だけ。「not fixes キー」では閉じません。課題のテーブルにワークフローがあるときは、ワークフローを飛ばさないよう閉じません）。つながる・閉じるのは、書いた順のはじめの 20 の課題です。Git のサーバーへは何も送りません。秘密の文字は 1 回だけ出します（なくしたら作り直します）。

The webhook is `POST /pm-git/hook/<key>`, outside the admin pages. A firewall in front of the site may refuse its bodies (commit messages and links): on **Exment SaaS**, the AWS WAF needs one exception for the path `/pm-git/hook/` (the operator adds it).
Webhook は管理画面の外の `POST /pm-git/hook/<キー>` です。サイトの前のファイアウォールが本文（コミットのメッセージやリンク）でことわることがあります。**Exment SaaS** では、AWS WAF に `/pm-git/hook/` の例外が 1 つ要ります（運営側が足します）。

## Phone app (PWA) / スマホのアプリ

Off at first: turn on **Phone app (PWA)** in **Admin settings → Feature management**. Then Project Management can be added to the home screen of a phone or a computer (the browser's install, or **⚙ → Add to home screen**; on iPhone, Safari's Share → Add to Home Screen) and opens like an app. Without a connection it shows an offline page. The service worker covers the portal's address only (`…/plugins/exment_pm/`), keeps no issue data on the device (pages always come from the server), and only keeps the portal's own script and style files. Turned off again, the worker is removed the next time the portal is opened.
最初は使いません。**管理者設定 → 機能の管理** で **スマホのアプリ（PWA）** をオンにすると、プロジェクト管理をスマホやパソコンの「ホーム画面に追加」で、アプリのように使えます（ブラウザーのインストール、または **⚙ → ホーム画面に追加**。iPhone は Safari の共有 → ホーム画面に追加）。電波がないときは「オフラインです」の画面を出します。しくみ（サービスワーカー）が動くのはプロジェクト管理のアドレス（`…/plugins/exment_pm/`）だけで、課題のデータは端末に残しません（画面はいつもサーバーから）。残すのはプロジェクト管理のスクリプトとスタイルのファイルだけです。オフにもどすと、つぎにプロジェクト管理を開いたときに外します。
## Notices / お知らせ

The bell on the portal's top bar opens the person's own newest 30 notices, unread first: a menu on a wide screen, the whole screen on a phone (Exment's own list is a table made for a desktop). A tap opens the issue (and marks the notice read, through Exment), **Mark all read** marks the person's own notices read, and **All notifications** opens Exment's list.
ポータルの上のバーのベルで、自分のお知らせの新しい 30 件を、まだ読んでいないものから出します。広い画面ではメニュー、スマホでは画面いっぱいです（Exment のお知らせの一覧はパソコン向けの表です）。押すと課題を開き（Exment を通るので既読になります）、**すべて既読** で自分のお知らせを既読にし、**すべての通知** で Exment の一覧を開きます。

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
