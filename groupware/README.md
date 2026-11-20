# Exment Plugin Groupware

**[English](#english)** | **[日本語](#japanese)**

---

<a id="english"></a>

## What it does

The groupware of Exment (ex1task ISS00233). This first version, 0.1.0, opens the Word (.docx), Excel (.xlsx) and PowerPoint (.pptx) files of the **file server** of Exment in a browser Office, [ibisOffice](https://github.com/ibisjp/ibis-office) by ibis inc.:

- The page of such a file gets an **Office で開く** (Open in Office) button. It opens the editor in a new tab.
- **Save** in the editor adds a new version of the file (the version history of the file server keeps the older ones).
- While the file is open for editing it is **checked out**: nobody else can change it (ibisOffice has no editing together yet). It is checked in when the editor is closed. A user who may only view the file, or a file someone else has checked out, opens read-only.
- A save from an old version (someone saved in between) is refused, so nothing is overwritten.

| Folder | Plugin |
|---|---|
| [`ExmentGroupware/`](ExmentGroupware/) | Exment Plugin Groupware — plugin name `ExmentGroupware`, address `/admin/plugins/exment_groupware` |

## Requirements

- Exment with the file server (the `feature/file-server` branch, exceedone/exment PR #1834, or a release that has it). Without it the plugin does nothing.
- PHP 8.2 with the `zip` extension (to install ibisOffice).
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

---

<a id="japanese"></a>

## できること

Exment のグループウェア（ex1task ISS00233）です。最初の版 0.1.0 では、Exment の**ファイルサーバー**にある Word（.docx）・Excel（.xlsx）・PowerPoint（.pptx）のファイルを、株式会社アイビスのブラウザーで動く Office、[ibisOffice](https://github.com/ibisjp/ibis-office) で開きます。

- そのファイルの画面に **Office で開く** ボタンが出ます。押すと、新しいタブでエディターが開きます。
- エディターで**保存**すると、ファイルの新しい版になります（前の版はファイルサーバーの版の履歴に残ります）。
- 編集のために開いている間は**チェックアウト**（編集中）になり、ほかの人は変更できません（ibisOffice にはまだ同時編集がないため）。エディターを閉じるとチェックインします。見るだけの権限の人や、ほかの人がチェックアウトしているファイルは、読み取り専用で開きます。
- 古い版から保存しようとしたとき（間にほかの人が保存したとき）は断るので、上書きで消えることはありません。

## 必要なもの

- ファイルサーバーのある Exment（`feature/file-server` ブランチ、exceedone/exment PR #1834、またはそれを含むリリース）。ないときは何もしません。
- PHP 8.2 と `zip` の拡張（ibisOffice のインストールに使います）。
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
