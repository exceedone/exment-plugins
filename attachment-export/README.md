# Exment Plugin Attachment Export

The attachments of the records of a list, in one zip.
データの一覧から、表示しているデータの添付ファイルを 1 つの zip でダウンロードします。

| Name / 名前 | Value / 値 |
|---|---|
| Name in the plugin list / プラグインの一覧に出る名前 | Exment Plugin Attachment Export（添付ファイルの一括ダウンロード） |
| Plugin name (`plugin_name`), folder, zip / 内部の名前・フォルダ・zip | `ExmentAttachmentExport` |
| Type / 種類 | export（エクスポート） |
| Needs / 要るもの | Exment 7 (PHP 8.2 or later, the PHP zip extension) |

**Unreleased / 未リリース**

- First version (ISS00356): made for the move of a customer to Exment SaaS, where a plugin of a customer's own cannot run on the server; it does what their plugin did, as an official plugin. 最初の版。

## What it does / できること

- In a table's **Import / Export** (the tables chosen in the plugin's settings, `target_tables`), **添付ファイルを zip でダウンロード**
- For the records the list shows (its filter and its search): the files attached to each record and the files of its file and image columns
- One folder a record in the zip: `<label>_<id>/`. Two files of the same name in a folder are numbered (`a (2).pdf`). `_README.txt` says how many files and how large
- Only the records the user may see are in the list, so only their files are in the zip
- Files on a cloud disk (S3) are copied to a temporary place for the zip and deleted after it

一覧で絞りこんだデータの、データの添付とファイル列・画像列のファイルを、データごとのフォルダー（`<見出し>_<ID>/`）に入れて 1 つの zip にします。見られるデータのファイルだけが入ります。

## Settings / 設定

Admin setting → Plugins → this plugin:

| Setting | Default | |
|---|---|---|
| 1 回の zip のファイルの数の上限 (`max_files`) | 2000 | past it the zip stops and `_README.txt` says so |
| 1 回の zip の大きさの上限（MB） (`max_megabytes`) | 1024 | the zip is made in a temporary place of the server, up to this size |

When the zip stops at a limit, filter the list and download in parts. 上限で止まったときは、一覧を絞りこんで分けてダウンロードしてください。

## Exment SaaS

A server-side (PHP) plugin of a customer does not run on Exment SaaS; this one is official, delivered from the market like the other official plugins. No outside connection, no shell.
