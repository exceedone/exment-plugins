{{-- Exment Plugin Groupware: Web mail. Folders | list | reading pane, and the composer. Everything is read from the mail server through mail/api. --}}
<div class="gwm" id="gwm">
<style>
.gwm{--line:#e3e6ea;--line2:#eef0f3;--ink:#1f2328;--sub:#5b6470;--faint:#8b939e;--box:#fff;--box2:#f7f8fa;--hover:#f1f4f8;--sel:#e6effc;--acc:#2563c9;--acc-soft:#eaf1fd;--red:#d23c3c;--amber:#b7791f;color:var(--ink);font-size:13px}
.gwm *{box-sizing:border-box}
.gwm-app{display:grid;grid-template-columns:224px 380px minmax(0,1fr);height:var(--gwm-h,640px);background:var(--box);border:1px solid var(--line);border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.05)}
.gwm-app>*{min-height:0;min-width:0}
.gwm button{font:inherit}
.gwm .ib{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:30px;padding:0 10px;border:1px solid transparent;border-radius:7px;background:transparent;color:var(--sub);cursor:pointer;white-space:nowrap}
.gwm .ib:hover{background:var(--hover);color:var(--ink)}
.gwm .ib:disabled{opacity:.45;cursor:default;background:transparent}
.gwm .ib.on{color:var(--acc)}
.gwm .ib.danger:hover{color:var(--red)}
.gwm .ib.sq{width:30px;padding:0}
.gwm .btnp{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;border:0;border-radius:8px;background:var(--acc);color:#fff;font-weight:600;cursor:pointer}
.gwm .btnp:hover{filter:brightness(1.06)}
.gwm .btnp:disabled{opacity:.6;cursor:default}
.gwm .btns{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:1px solid var(--line);border-radius:8px;background:var(--box);color:var(--ink);cursor:pointer}
.gwm .btns:hover{background:var(--hover)}
/* left */
.gwm-side{background:var(--box2);border-right:1px solid var(--line);display:flex;flex-direction:column}
.gwm-new{margin:12px 12px 8px}
.gwm-new .btnp{width:100%;justify-content:center;height:38px}
.gwm-tree{flex:1;overflow:auto;padding-bottom:8px}
.gwm-acct{display:flex;align-items:center;gap:8px;margin:8px 8px 2px;padding:6px 8px;border-radius:7px;cursor:pointer;font-weight:600;color:var(--ink);user-select:none}
.gwm-acct:hover{background:var(--hover)}
.gwm-acct .em{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-acct .pv{font-size:10.5px;font-weight:600;color:var(--faint);border:1px solid var(--line);border-radius:4px;padding:0 4px}
.gwm-acct .warn{color:var(--amber)}
.gwm-f{display:flex;align-items:center;gap:9px;margin:1px 8px;padding:6px 10px;border-radius:7px;color:var(--ink);cursor:pointer;user-select:none}
.gwm-f i.fa{width:16px;text-align:center;color:var(--faint)}
.gwm-f .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-f:hover{background:var(--hover)}
.gwm-f.on{background:var(--sel);color:var(--acc);font-weight:600}
.gwm-f.on i.fa{color:var(--acc)}
.gwm-f.dis{color:var(--faint);cursor:default}
.gwm-f .c{font-size:11.5px;font-weight:700;color:var(--acc)}
.gwm-f.drop{outline:2px dashed var(--acc);outline-offset:-2px}
.gwm-sidefoot{border-top:1px solid var(--line);padding:8px;display:flex;flex-direction:column;gap:2px}
.gwm-sidefoot a,.gwm-sidefoot button{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:7px;color:var(--sub);background:none;border:0;text-align:left;cursor:pointer;font-size:12.5px}
.gwm-sidefoot a:hover,.gwm-sidefoot button:hover{background:var(--hover);color:var(--ink);text-decoration:none}
/* list */
.gwm-list{display:flex;flex-direction:column;border-right:1px solid var(--line)}
.gwm-lhead{padding:10px 10px 0;border-bottom:1px solid var(--line)}
.gwm-search{position:relative}
.gwm-search i{position:absolute;left:10px;top:9px;color:var(--faint)}
.gwm-search input{width:100%;height:32px;border:1px solid var(--line);border-radius:8px;padding:0 30px;background:var(--box2);outline:none}
.gwm-search input:focus{border-color:var(--acc);background:#fff}
.gwm-search .x{position:absolute;right:4px;top:3px;display:none}
.gwm-search.has .x{display:inline-flex}
.gwm-ltitle{display:flex;align-items:center;gap:6px;margin:8px 2px 0}
.gwm-ltitle b{font-size:15px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-tabs{display:flex;gap:2px;margin-top:4px}
.gwm-tab{padding:7px 10px;border:0;background:none;color:var(--sub);cursor:pointer;border-bottom:2px solid transparent;font-weight:600;font-size:12.5px}
.gwm-tab.on{color:var(--acc);border-bottom-color:var(--acc)}
.gwm-bulk{display:none;align-items:center;gap:2px;padding:4px 6px;border-bottom:1px solid var(--line);background:var(--acc-soft)}
.gwm-bulk.on{display:flex}
.gwm-bulk .n{font-size:12px;font-weight:600;color:var(--acc);margin:0 6px}
.gwm-items{flex:1;overflow:auto;position:relative}
.gwm-it{position:relative;display:flex;gap:10px;padding:10px 12px 10px 10px;border-bottom:1px solid var(--line2);cursor:pointer}
.gwm-it:hover{background:var(--hover)}
.gwm-it.sel{background:var(--sel)}
.gwm-it.unread::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--acc)}
.gwm-it .ck{flex:0 0 18px;padding-top:9px}
.gwm-it .ck input{margin:0;cursor:pointer}
.gwm-av{flex:0 0 34px;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:13px}
.gwm-av.l{flex-basis:40px;width:40px;height:40px;font-size:15px}
.gwm-im{flex:1;min-width:0}
.gwm-r1{display:flex;align-items:center;gap:6px;min-width:0}
.gwm-r1 .who{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ink)}
.gwm-it.unread .who,.gwm-it.unread .subj{font-weight:700}
.gwm-r1 .t{font-size:11.5px;color:var(--faint);white-space:nowrap}
.gwm-it.unread .t{color:var(--acc);font-weight:600}
.gwm-r2{display:flex;align-items:center;gap:6px;margin-top:2px}
.gwm-r2 .subj{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sub)}
.gwm-it.unread .subj{color:var(--ink)}
.gwm-r2 .fa{color:var(--faint);font-size:12px}
.gwm-r2 .fa-flag{color:var(--red)}
.gwm-it .qa{position:absolute;right:8px;bottom:6px;display:none;gap:0;background:var(--box);border:1px solid var(--line);border-radius:7px;box-shadow:0 2px 6px rgba(16,24,40,.08)}
.gwm-it:hover .qa{display:flex}
.gwm-it .qa .ib{height:26px;width:28px;padding:0}
.gwm-lfoot{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-top:1px solid var(--line);font-size:12px;color:var(--sub)}
.gwm-empty{display:grid;place-items:center;height:100%;color:var(--faint);text-align:center;padding:30px;line-height:1.7}
.gwm-empty i{font-size:42px;opacity:.3;display:block;margin-bottom:10px}
.gwm-load{position:absolute;inset:0;background:rgba(255,255,255,.6);display:none;place-items:center;z-index:2}
.gwm-load.on{display:grid}
.gwm-err{margin:10px;padding:10px 12px;border-radius:8px;background:#fdecec;color:#a52a2a;border:1px solid #f5c2c2;line-height:1.6}
.gwm-err a{color:inherit;text-decoration:underline}
/* read */
.gwm-read{display:flex;flex-direction:column;background:var(--box)}
.gwm-rbar{display:flex;align-items:center;gap:2px;padding:6px 10px;border-bottom:1px solid var(--line);flex-wrap:wrap}
.gwm-rbar .sp{flex:1}
.gwm-rbar select{height:30px;border:1px solid var(--line);border-radius:7px;background:var(--box);color:var(--sub);max-width:160px}
.gwm-rhead{padding:14px 20px 10px}
.gwm-subject{font-size:18px;font-weight:700;line-height:1.4;margin:0 0 12px;word-break:break-word}
.gwm-from{display:flex;gap:12px;align-items:flex-start}
.gwm-from .meta{flex:1;min-width:0}
.gwm-from .nm{font-weight:700}
.gwm-from .ad{color:var(--sub);font-size:12px}
.gwm-from .rc{color:var(--sub);font-size:12px;margin-top:2px;word-break:break-word}
.gwm-from .dt{color:var(--faint);font-size:12px;white-space:nowrap}
.gwm-banner{margin:0 20px 8px;padding:8px 12px;border-radius:8px;background:#fff7e6;border:1px solid #f5d79e;color:#7a5400;font-size:12.5px;display:flex;align-items:center;gap:10px}
.gwm-banner button{margin-left:auto}
.gwm-atts{display:flex;flex-wrap:wrap;gap:8px;padding:0 20px 10px}
.gwm-att{display:flex;align-items:center;gap:8px;max-width:260px;padding:6px 10px;border:1px solid var(--line);border-radius:8px;color:var(--ink);background:var(--box2);text-decoration:none}
.gwm-att:hover{border-color:var(--acc);text-decoration:none;color:var(--ink)}
.gwm-att .n{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-att .s{color:var(--faint);font-size:11px;white-space:nowrap}
.gwm-att i.fa{color:var(--faint)}
.gwm-body{flex:1;min-height:0;border-top:1px solid var(--line2)}
.gwm-body iframe{width:100%;height:100%;border:0;display:block;background:#fff}
/* composer */
.gwm-comp{position:fixed;right:24px;bottom:0;width:min(720px,calc(100vw - 32px));height:min(640px,calc(100vh - 70px));background:var(--box);border:1px solid var(--line);border-radius:12px 12px 0 0;box-shadow:0 12px 40px rgba(16,24,40,.25);display:none;flex-direction:column;z-index:1040}
.gwm-comp.on{display:flex}
.gwm-comp.min{height:44px}
.gwm-comp.min>*:not(.gwm-chead){display:none!important}
.gwm-comp.max{right:5vw;width:90vw;height:calc(100vh - 70px)}
.gwm-chead{display:flex;align-items:center;gap:4px;padding:6px 8px 6px 16px;background:#1f2937;color:#fff;border-radius:12px 12px 0 0;cursor:pointer}
.gwm-chead b{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}
.gwm-chead .ib{color:#cbd5e1;height:28px;width:28px;padding:0}
.gwm-chead .ib:hover{background:rgba(255,255,255,.12);color:#fff}
.gwm-cf{display:flex;align-items:center;gap:8px;padding:0 14px;border-bottom:1px solid var(--line2);min-height:38px}
.gwm-cf label{flex:0 0 52px;margin:0;color:var(--sub);font-weight:500}
.gwm-cf input,.gwm-cf select{flex:1;min-width:0;border:0;outline:none;height:36px;background:transparent}
.gwm-cf .lk{color:var(--sub);cursor:pointer;font-size:12px;white-space:nowrap}
.gwm-cf .lk:hover{color:var(--acc)}
.gwm-tools{display:flex;align-items:center;gap:1px;padding:4px 10px;border-bottom:1px solid var(--line2);flex-wrap:wrap}
.gwm-tools .ib{height:28px;width:30px;padding:0}
.gwm-tools .sep{width:1px;height:18px;background:var(--line);margin:0 4px}
.gwm-ed{flex:1;min-height:0;overflow:auto;padding:12px 16px;outline:none;line-height:1.6;word-break:break-word}
.gwm-ed blockquote{margin:0 0 0 .8ex;border-left:2px solid #cfd5dc;padding-left:1ex;color:#444}
.gwm-ed img{max-width:100%}
.gwm-ed .gwm-qh{color:var(--sub);margin-top:14px}
.gwm-catts{display:flex;flex-wrap:wrap;gap:6px;padding:6px 14px 0}
.gwm-catts:empty{display:none}
.gwm-chip{display:inline-flex;align-items:center;gap:6px;max-width:260px;padding:4px 4px 4px 10px;border:1px solid var(--line);border-radius:999px;background:var(--box2);font-size:12px}
.gwm-chip .n{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-chip .s{color:var(--faint)}
.gwm-chip button{border:0;background:none;color:var(--faint);cursor:pointer;width:20px;height:20px;border-radius:50%}
.gwm-chip button:hover{background:var(--hover);color:var(--red)}
.gwm-cfoot{display:flex;align-items:center;gap:8px;padding:10px 14px;border-top:1px solid var(--line2)}
.gwm-cfoot .sp{flex:1}
.gwm-cfoot .msg{font-size:12px;color:var(--sub);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwm-cfoot .msg.err{color:var(--red)}
.gwm-comp.dragover .gwm-ed{outline:2px dashed var(--acc);outline-offset:-6px}
.gwm-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#1f2937;color:#fff;padding:9px 16px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:1050;display:none;align-items:center;gap:12px;max-width:90vw}
.gwm-toast.on{display:flex}
.gwm-toast.err{background:#a52a2a}
.gwm-toast button{background:none;border:0;color:#93c5fd;font-weight:600;cursor:pointer}
.gwm .mob{display:none}
@media (max-width:1180px){.gwm-app{grid-template-columns:200px 320px minmax(0,1fr)}}
@media (max-width:860px){
  .gwm-app{grid-template-columns:1fr}
  .gwm-app>.gwm-side,.gwm-app>.gwm-read{display:none}
  .gwm.v-side .gwm-side{display:flex}.gwm.v-side .gwm-list{display:none}
  .gwm.v-read .gwm-read{display:flex}.gwm.v-read .gwm-list{display:none}
  .gwm .mob{display:inline-flex}
  .gwm-comp,.gwm-comp.max{right:0;width:100vw;height:calc(100vh - 50px)}
}
</style>

<div class="gwm-app" id="gwm-app">
    <aside class="gwm-side">
        <div class="gwm-new"><button type="button" class="btnp" data-act="compose"><i class="fa fa-pencil" aria-hidden="true"></i> 新しいメール</button></div>
        <nav class="gwm-tree" id="gwm-tree" aria-label="フォルダー"></nav>
        <div class="gwm-sidefoot">
            <button type="button" data-act="new-folder"><i class="fa fa-folder-o" aria-hidden="true"></i> フォルダーを作る</button>
            <a href="{{ $accounts_url }}"><i class="fa fa-cog" aria-hidden="true"></i> アカウントの設定</a>
        </div>
    </aside>

    <section class="gwm-list" aria-label="メールの一覧">
        <div class="gwm-lhead">
            <div class="gwm-search" id="gwm-search">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" id="gwm-q" placeholder="件名・差出人・宛先・本文を検索" autocomplete="off" aria-label="検索">
                <button type="button" class="ib sq x" data-act="clear-search" title="検索をやめる"><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="gwm-ltitle">
                <button type="button" class="ib sq mob" data-act="show-side" title="フォルダー"><i class="fa fa-bars" aria-hidden="true"></i></button>
                <b id="gwm-ftitle">受信トレイ</b>
                <button type="button" class="ib sq" data-act="refresh" title="最新にする"><i class="fa fa-refresh" aria-hidden="true"></i></button>
            </div>
            <div class="gwm-tabs" role="tablist">
                <button type="button" class="gwm-tab on" data-filter="all">すべて</button>
                <button type="button" class="gwm-tab" data-filter="unread">未読</button>
                <button type="button" class="gwm-tab" data-filter="flagged">フラグ付き</button>
            </div>
        </div>
        <div class="gwm-bulk" id="gwm-bulk">
            <input type="checkbox" id="gwm-all" title="すべて選ぶ" style="margin:0 6px">
            <span class="n" id="gwm-bulkn"></span>
            <button type="button" class="ib sq" data-act="bulk-read" title="既読にする"><i class="fa fa-envelope-open-o" aria-hidden="true"></i></button>
            <button type="button" class="ib sq" data-act="bulk-unread" title="未読にする"><i class="fa fa-envelope-o" aria-hidden="true"></i></button>
            <button type="button" class="ib sq" data-act="bulk-flag" title="フラグを付ける"><i class="fa fa-flag-o" aria-hidden="true"></i></button>
            <button type="button" class="ib sq" data-act="bulk-junk" title="迷惑メールへ"><i class="fa fa-ban" aria-hidden="true"></i></button>
            <button type="button" class="ib sq danger" data-act="bulk-delete" title="削除"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
            <span style="flex:1"></span>
            <button type="button" class="ib sq" data-act="bulk-clear" title="選ぶのをやめる"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="gwm-items" id="gwm-items"><div class="gwm-load on"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div></div>
        <div class="gwm-lfoot">
            <span id="gwm-count"></span>
            <span>
                <button type="button" class="ib sq" data-act="prev" title="前のページ"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                <span id="gwm-pageinfo"></span>
                <button type="button" class="ib sq" data-act="next" title="次のページ"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
            </span>
        </div>
    </section>

    <section class="gwm-read" id="gwm-read" aria-label="メール">
        <div class="gwm-empty"><div><i class="fa fa-envelope-o" aria-hidden="true"></i>メールを選ぶと、ここに表示されます。</div></div>
    </section>
</div>

<div class="gwm-comp" id="gwm-comp" role="dialog" aria-label="メールを書く">
    <div class="gwm-chead" data-act="comp-min">
        <b id="gwm-ctitle">新しいメール</b>
        <button type="button" class="ib" data-act="comp-min" title="小さくする"><i class="fa fa-minus" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-act="comp-max" title="大きくする"><i class="fa fa-expand" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-act="comp-close" title="閉じる（下書きに保存）"><i class="fa fa-times" aria-hidden="true"></i></button>
    </div>
    <div class="gwm-cf"><label for="gwm-cfrom">差出人</label><select id="gwm-cfrom"></select></div>
    <div class="gwm-cf"><label for="gwm-cto">宛先</label><input id="gwm-cto" type="text" autocomplete="off" list="gwm-addrs" placeholder="名前 <mail@example.com>、カンマ区切りで複数"><span class="lk" data-act="show-cc">Cc</span><span class="lk" data-act="show-bcc">Bcc</span></div>
    <div class="gwm-cf" id="gwm-rcc" style="display:none"><label for="gwm-ccc">Cc</label><input id="gwm-ccc" type="text" autocomplete="off" list="gwm-addrs"></div>
    <div class="gwm-cf" id="gwm-rbcc" style="display:none"><label for="gwm-cbcc">Bcc</label><input id="gwm-cbcc" type="text" autocomplete="off" list="gwm-addrs"></div>
    <div class="gwm-cf"><label for="gwm-csubj">件名</label><input id="gwm-csubj" type="text" autocomplete="off"></div>
    <div class="gwm-tools" id="gwm-tools">
        <button type="button" class="ib" data-cmd="bold" title="太字（Ctrl+B）"><i class="fa fa-bold" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="italic" title="斜体（Ctrl+I）"><i class="fa fa-italic" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="underline" title="下線（Ctrl+U）"><i class="fa fa-underline" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="strikeThrough" title="取り消し線"><i class="fa fa-strikethrough" aria-hidden="true"></i></button>
        <span class="sep"></span>
        <button type="button" class="ib" data-cmd="foreColor" data-val="#d23c3c" title="赤い文字"><i class="fa fa-font" style="color:#d23c3c" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="hiliteColor" data-val="#fff3a3" title="マーカー"><i class="fa fa-paint-brush" aria-hidden="true"></i></button>
        <span class="sep"></span>
        <button type="button" class="ib" data-cmd="insertUnorderedList" title="箇条書き"><i class="fa fa-list-ul" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="insertOrderedList" title="番号付き"><i class="fa fa-list-ol" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="indent" title="字下げ"><i class="fa fa-indent" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="outdent" title="字下げを戻す"><i class="fa fa-outdent" aria-hidden="true"></i></button>
        <span class="sep"></span>
        <button type="button" class="ib" data-cmd="createLink" title="リンク"><i class="fa fa-link" aria-hidden="true"></i></button>
        <button type="button" class="ib" data-cmd="removeFormat" title="書式をなくす"><i class="fa fa-eraser" aria-hidden="true"></i></button>
    </div>
    <div class="gwm-ed" id="gwm-ed" contenteditable="true" role="textbox" aria-multiline="true" aria-label="本文"></div>
    <div class="gwm-catts" id="gwm-catts"></div>
    <div class="gwm-cfoot">
        <button type="button" class="btnp" data-act="send" id="gwm-send"><i class="fa fa-paper-plane" aria-hidden="true"></i> 送信</button>
        <label class="ib sq" title="ファイルを添付" style="margin:0"><i class="fa fa-paperclip" aria-hidden="true"></i><input type="file" id="gwm-file" multiple style="display:none"></label>
        <button type="button" class="ib" data-act="save-draft" title="下書きに保存"><i class="fa fa-floppy-o" aria-hidden="true"></i> 下書き</button>
        <span class="msg" id="gwm-cmsg"></span>
        <span class="sp"></span>
        <button type="button" class="ib sq danger" data-act="discard" title="破棄"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
    </div>
</div>
<datalist id="gwm-addrs"></datalist>
<div class="gwm-toast" id="gwm-toast" role="status"><span id="gwm-toastmsg"></span><button type="button" id="gwm-toastbtn" style="display:none"></button></div>
</div>

<script>
var GWM_CFG = @json($cfg);
</script>
@verbatim
<script>
(function () {
'use strict';
var C = window.GWM_CFG, root = document.getElementById('gwm');
if (!root) { return; }
if (window.__gwmTimer) { clearInterval(window.__gwmTimer); }

var S = {
    accounts: C.accounts, acct: null, folders: {}, folder: 'INBOX', page: 1, pages: 1, q: '', filter: 'all',
    items: [], sel: {}, cur: null, msg: null, remote: false, comp: null, uidnext: {}, addrs: {}
};
var $ = function (id) { return document.getElementById(id); };

// ---- helpers ------------------------------------------------------------
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function api(path, params, post) {
    var url = C.base + '/api/' + path;
    var o = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf }, credentials: 'same-origin' };
    if (post) {
        var fd = params instanceof FormData ? params : new FormData();
        if (!(params instanceof FormData)) { Object.keys(params || {}).forEach(function (k) { var v = params[k]; if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else if (v != null) { fd.append(k, v); } }); }
        fd.append('_token', C.csrf);
        o.method = 'POST'; o.body = fd;
    } else if (params) {
        url += '?' + Object.keys(params).filter(function (k) { return params[k] != null && params[k] !== ''; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    }
    return fetch(url, o).then(function (r) {
        return r.json().catch(function () { return { ok: false, message: r.status === 419 ? 'ページの有効期限が切れました。ページを読み込み直してください。' : 'サーバーの応答を読めませんでした（' + r.status + '）。' }; });
    }).catch(function () { return { ok: false, message: 'サーバーにつながりません。' }; });
}
function aurl(path, params) {
    return C.base + '/api/' + path + '?' + Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
}
var toastTimer;
function toast(text, err, btn, fn) {
    var t = $('gwm-toast'); $('gwm-toastmsg').textContent = text; t.className = 'gwm-toast on' + (err ? ' err' : '');
    var b = $('gwm-toastbtn'); if (btn) { b.style.display = ''; b.textContent = btn; b.onclick = function () { t.className = 'gwm-toast'; fn(); }; } else { b.style.display = 'none'; }
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.className = 'gwm-toast'; }, err ? 8000 : 4000);
}
var COLORS = ['#2563c9', '#0f8a6c', '#b45309', '#9333ea', '#c2410c', '#0e7490', '#be185d', '#4d7c0f', '#6d28d9', '#a16207'];
function avatar(name, email, big) {
    var s = (name || email || '?').trim(); var h = 0; for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) | 0; }
    var ch = s.replace(/^["'\s]+/, '').charAt(0).toUpperCase();
    return '<span class="gwm-av' + (big ? ' l' : '') + '" style="background:' + COLORS[Math.abs(h) % COLORS.length] + '" aria-hidden="true">' + esc(ch) + '</span>';
}
// The parts of a moment (year, month, day, hour, minute, day of the week) in the time zone of the user
// (GWM_CFG.timezone, from Exment). Without one, or in a browser that cannot do it: the browser's time zone.
var TZ_FMT = null;
try {
    if (GWM_CFG.timezone) {
        TZ_FMT = new Intl.DateTimeFormat('en-US', { timeZone: GWM_CFG.timezone, hourCycle: 'h23', weekday: 'short',
            year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric' });
    }
} catch (e) { TZ_FMT = null; }
function parts(d) {
    if (TZ_FMT) {
        try {
            var o = {};
            TZ_FMT.formatToParts(d).forEach(function (x) { o[x.type] = x.value; });
            return { y: Number(o.year), m: Number(o.month), d: Number(o.day), h: Number(o.hour) % 24, i: Number(o.minute),
                w: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(o.weekday) };
        } catch (e) { /* the browser's time zone below */ }
    }
    return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), i: d.getMinutes(), w: d.getDay() };
}
function when(iso, full) {
    if (!iso) { return ''; }
    var at = new Date(iso);
    if (isNaN(at.getTime())) { return ''; }
    var d = parts(at), n = parts(new Date()), p = function (x) { return (x < 10 ? '0' : '') + x; };
    var hm = p(d.h) + ':' + p(d.i);
    var wd = '日月火水木金土'.charAt(d.w);
    if (full) { return d.y + '/' + d.m + '/' + d.d + '（' + wd + '） ' + hm; }
    if (d.y === n.y && d.m === n.m && d.d === n.d) { return hm; }
    if (d.y === n.y) { return d.m + '/' + d.d + '（' + wd + '）'; }
    return d.y + '/' + d.m + '/' + d.d;
}
function size(n) { return n < 1024 ? n + ' B' : n < 1048576 ? Math.round(n / 1024) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; }
function who(list) { return (list || []).map(function (a) { return a.name ? a.name + ' <' + a.email + '>' : a.email; }).join(', '); }
function whoShort(list) { return (list || []).map(function (a) { return a.name || a.email; }).join(', '); }
function acct() { for (var i = 0; i < S.accounts.length; i++) { if (S.accounts[i].id === S.acct) { return S.accounts[i]; } } return S.accounts[0]; }
function remember(list) { (list || []).forEach(function (a) { if (a && a.email) { S.addrs[a.email] = a.name ? a.name + ' <' + a.email + '>' : a.email; } }); }
function roleIcon(r) { return { inbox: 'fa-inbox', drafts: 'fa-file-text-o', sent: 'fa-paper-plane-o', junk: 'fa-ban', trash: 'fa-trash-o', archive: 'fa-archive' }[r] || 'fa-folder-o'; }
function folderOf(name) { var fs = S.folders[S.acct] || []; for (var i = 0; i < fs.length; i++) { if (fs[i].name === name) { return fs[i]; } } return null; }
function role() { var f = folderOf(S.folder); return f ? f.role : (S.folder.toUpperCase() === 'INBOX' ? 'inbox' : null); }

// ---- layout -------------------------------------------------------------
function fit() {
    var app = $('gwm-app'); if (!app) { return; }
    var top = app.getBoundingClientRect().top + window.scrollY;
    app.style.setProperty('--gwm-h', Math.max(420, window.innerHeight - top - 16) + 'px');
}
// Exment moves between pages without reloading (pjax): listeners on document and window are
// replaced, not added again, each time this page is shown.
function listen(target, type, fn) {
    var key = '__gwm_' + type;
    if (target[key]) { target.removeEventListener(type, target[key]); }
    target[key] = fn; target.addEventListener(type, fn);
}
listen(window, 'resize', fit);
function view(v) { root.classList.remove('v-side', 'v-read'); if (v) { root.classList.add('v-' + v); } }

// ---- folders ------------------------------------------------------------
function renderTree() {
    var h = '';
    S.accounts.forEach(function (a) {
        var open = a.id === S.acct;
        var unread = 0; (S.folders[a.id] || []).forEach(function (f) { if (f.role === 'inbox') { unread = f.unread; } });
        if (a._unread != null) { unread = a._unread; }
        h += '<div class="gwm-acct" data-acct="' + a.id + '" title="' + esc(a.email) + '"><i class="fa fa-caret-' + (open ? 'down' : 'right') + '" aria-hidden="true"></i><span class="em">' + esc(a.name || a.email) + '</span>' +
            (a.last_error || a.needs_signin ? '<i class="fa fa-exclamation-triangle warn" title="' + esc(a.needs_signin ? 'サインインが必要です' : a.last_error) + '"></i>' : '') +
            (!open && unread ? '<span class="gwm-f"><span class="c">' + unread + '</span></span>' : '') + '<span class="pv">' + esc(a.provider_label) + '</span></div>';
        if (!open) { return; }
        var fs = S.folders[a.id];
        if (!fs) { h += '<div class="gwm-f dis"><i class="fa fa-spinner fa-spin"></i><span class="nm">読み込み中…</span></div>'; return; }
        fs.forEach(function (f) {
            h += '<div class="gwm-f' + (f.name === S.folder ? ' on' : '') + (f.selectable ? '' : ' dis') + '" data-folder="' + esc(f.name) + '" style="padding-left:' + (10 + f.depth * 14) + 'px" title="' + esc(f.label) + '">' +
                '<i class="fa ' + roleIcon(f.role) + '" aria-hidden="true"></i><span class="nm">' + esc(f.label) + '</span>' +
                (f.unread && f.role !== 'sent' && f.role !== 'trash' ? '<span class="c">' + f.unread + '</span>' : (f.role === 'drafts' && f.total ? '<span class="c" style="color:var(--faint)">' + f.total + '</span>' : '')) + '</div>';
        });
    });
    $('gwm-tree').innerHTML = h;
    var f = folderOf(S.folder);
    $('gwm-ftitle').textContent = S.q ? '「' + S.q + '」の検索結果' : (f ? f.label : S.folder);
    document.title = document.title.replace(/^\(\d+\) /, '');
    var inbox = (S.folders[S.acct] || []).filter(function (x) { return x.role === 'inbox'; })[0];
    if (inbox && inbox.unread) { document.title = '(' + inbox.unread + ') ' + document.title; }
}
function loadFolders() {
    var id = S.acct;
    return api(id + '/folders').then(function (r) {
        if (id !== S.acct) { return; }
        if (!r.ok) { S.folders[id] = []; renderTree(); listError(r.message); return; }
        S.folders[id] = r.folders; renderTree();
    });
}
function openAccount(id) {
    S.acct = id; S.folder = 'INBOX'; S.page = 1; S.q = ''; $('gwm-q').value = ''; $('gwm-search').classList.remove('has');
    S.sel = {}; closeMessage();
    try { localStorage.setItem('gwm.acct', String(id)); } catch (e) {}
    renderTree();
    var a = acct();
    if (a.needs_signin) { listError('このアカウントはサインインが必要です。<a data-nopjax href="' + esc(C.base + '/account/' + a.id + '/signin') + '">サインインする</a>', true); return; }
    loadFolders().then(function () { var fs = S.folders[S.acct] || []; var inbox = fs.filter(function (f) { return f.role === 'inbox'; })[0]; S.folder = inbox ? inbox.name : 'INBOX'; renderTree(); });
    loadList();
}

// ---- list ---------------------------------------------------------------
function listError(m, html) { $('gwm-items').innerHTML = '<div class="gwm-err">' + (html ? m : esc(m)) + (html ? '' : '<br><a href="' + esc(C.accountsUrl) + '">アカウントの設定を見る</a>') + '</div>'; $('gwm-count').textContent = ''; $('gwm-pageinfo').textContent = ''; }
var listSeq = 0;
function loadList(quiet) {
    var seq = ++listSeq, box = $('gwm-items');
    if (!quiet) { var l = box.querySelector('.gwm-load'); if (l) { l.classList.add('on'); } else { box.insertAdjacentHTML('afterbegin', '<div class="gwm-load on"><i class="fa fa-spinner fa-spin"></i></div>'); } }
    return api(S.acct + '/messages', { folder: S.folder, page: S.page, q: S.q, filter: S.filter }).then(function (r) {
        if (seq !== listSeq) { return; }
        if (!r.ok) { listError(r.message); return; }
        S.items = r.items; S.pages = r.pages; S.page = r.page; S.total = r.total;
        r.items.forEach(function (m) { remember([{ email: m.from, name: m.from_name }]); remember(m.to); });
        fillAddrs();
        renderList(r.total);
    });
}
function renderList(total) {
    var isSent = role() === 'sent' || role() === 'drafts';
    if (!S.items.length) {
        $('gwm-items').innerHTML = '<div class="gwm-empty"><div><i class="fa ' + (S.q ? 'fa-search' : 'fa-inbox') + '"></i>' + (S.q ? '見つかりませんでした。' : S.filter === 'unread' ? '未読のメールはありません。' : S.filter === 'flagged' ? 'フラグ付きのメールはありません。' : 'このフォルダーにメールはありません。') + '</div></div>';
    } else {
        var h = '';
        S.items.forEach(function (m) {
            var name = isSent ? (whoShort(m.to) || '（宛先なし）') : (m.from_name || m.from || '（差出人なし）');
            var mail = isSent ? ((m.to[0] || {}).email || '') : m.from;
            h += '<div class="gwm-it' + (m.seen ? '' : ' unread') + (S.cur === m.uid ? ' sel' : '') + '" data-uid="' + m.uid + '" draggable="true">' +
                '<span class="ck"><input type="checkbox" data-ck="' + m.uid + '"' + (S.sel[m.uid] ? ' checked' : '') + ' aria-label="選ぶ"></span>' +
                avatar(name, mail) + '<div class="gwm-im"><div class="gwm-r1"><span class="who" title="' + esc(mail) + '">' + (m.draft ? '<span style="color:var(--red)">［下書き］</span>' : '') + esc(name) + '</span><span class="t">' + esc(when(m.date)) + '</span></div>' +
                '<div class="gwm-r2"><span class="subj">' + esc(m.subject || '（件名なし）') + '</span>' + (m.answered ? '<i class="fa fa-reply" title="返信済み"></i>' : '') + (m.attachment ? '<i class="fa fa-paperclip" title="添付あり"></i>' : '') + (m.flagged ? '<i class="fa fa-flag" title="フラグ"></i>' : '') + '</div></div>' +
                '<span class="qa"><button type="button" class="ib" data-q="seen" title="' + (m.seen ? '未読にする' : '既読にする') + '"><i class="fa ' + (m.seen ? 'fa-envelope-o' : 'fa-envelope-open-o') + '"></i></button>' +
                '<button type="button" class="ib" data-q="flag" title="フラグ"><i class="fa ' + (m.flagged ? 'fa-flag' : 'fa-flag-o') + '"></i></button>' +
                '<button type="button" class="ib danger" data-q="delete" title="削除"><i class="fa fa-trash-o"></i></button></span></div>';
        });
        $('gwm-items').innerHTML = h;
    }
    if (total != null) { S.total = total; }
    if (S.total != null) { S.total = Math.max(S.items.length, S.total); }
    $('gwm-count').textContent = S.total != null ? S.total + ' 件' : '';
    $('gwm-pageinfo').textContent = S.pages > 1 ? S.page + ' / ' + S.pages : '';
    renderBulk();
}
function renderBulk() {
    var n = Object.keys(S.sel).length;
    $('gwm-bulk').classList.toggle('on', n > 0);
    $('gwm-bulkn').textContent = n + ' 件を選択';
    $('gwm-all').checked = n > 0 && n === S.items.length;
}
function itemOf(uid) { for (var i = 0; i < S.items.length; i++) { if (S.items[i].uid === uid) { return S.items[i]; } } return null; }

// ---- actions on mail ------------------------------------------------------
function changeFolderCounts(delta) {
    var f = folderOf(S.folder); if (f) { f.unread = Math.max(0, (f.unread || 0) + delta); renderTree(); }
}
function setFlag(uids, flag, on) {
    uids.forEach(function (u) { var m = itemOf(u); if (!m) { return; } if (flag === 'seen' && m.seen !== on) { changeFolderCounts(on ? -1 : 1); } m[flag === 'seen' ? 'seen' : 'flagged'] = on; });
    renderList(); if (S.msg && uids.indexOf(S.msg.uid) >= 0) { S.msg[flag === 'seen' ? 'seen' : 'flagged'] = on; renderMessage(); }
    return api(S.acct + '/flag', { folder: S.folder, uids: uids, flag: flag, on: on ? 1 : 0 }, true).then(function (r) { if (!r.ok) { toast(r.message, true); loadList(true); } });
}
function removeFromList(uids) {
    var next = null;
    if (S.cur && uids.indexOf(S.cur) >= 0) {
        var idx = S.items.findIndex(function (m) { return m.uid === S.cur; });
        var rest = S.items.filter(function (m) { return uids.indexOf(m.uid) < 0; });
        next = rest[Math.min(idx, rest.length - 1)] || null;
    }
    uids.forEach(function (u) { var m = itemOf(u); if (m && !m.seen) { changeFolderCounts(-1); } delete S.sel[u]; });
    S.items = S.items.filter(function (m) { return uids.indexOf(m.uid) < 0; });
    renderList();
    if (S.cur && uids.indexOf(S.cur) >= 0) { if (next && window.innerWidth > 860) { openMessage(next.uid); } else { closeMessage(); } }
}
function del(uids, forever) {
    if (!uids.length) { return; }
    var inTrash = role() === 'trash';
    if ((forever || inTrash) && !confirm(uids.length + ' 件のメールを完全に削除します。元に戻せません。よろしいですか？')) { return; }
    removeFromList(uids);
    api(S.acct + '/delete', { folder: S.folder, uids: uids, forever: forever ? 1 : 0 }, true).then(function (r) {
        if (!r.ok) { toast(r.message, true); loadList(true); return; }
        toast(r.result === 'trash' ? uids.length + ' 件をごみ箱に移動しました。' : uids.length + ' 件を削除しました。');
        loadFolders();
    });
}
function move(uids, target, label) {
    if (!uids.length) { return; }
    removeFromList(uids);
    api(S.acct + '/move', { folder: S.folder, uids: uids, target: target }, true).then(function (r) {
        if (!r.ok) { toast(r.message, true); loadList(true); return; }
        toast(uids.length + ' 件を「' + label + '」に移動しました。');
        loadFolders();
    });
}
function selected() { return Object.keys(S.sel).map(Number); }

// ---- reading ------------------------------------------------------------
function closeMessage() { S.cur = null; S.msg = null; $('gwm-read').innerHTML = '<div class="gwm-empty"><div><i class="fa fa-envelope-o"></i>メールを選ぶと、ここに表示されます。</div></div>'; if (root.classList.contains('v-read')) { view(null); } }
var msgSeq = 0;
function openMessage(uid) {
    var seq = ++msgSeq; S.cur = uid; S.remote = false;
    document.querySelectorAll('#gwm-items .gwm-it').forEach(function (el) { el.classList.toggle('sel', Number(el.getAttribute('data-uid')) === uid); });
    $('gwm-read').innerHTML = '<div class="gwm-empty"><div><i class="fa fa-spinner fa-spin"></i></div></div>';
    view('read');
    var m = itemOf(uid);
    api(S.acct + '/message', { folder: S.folder, uid: uid }).then(function (r) {
        if (seq !== msgSeq) { return; }
        if (!r.ok) { $('gwm-read').innerHTML = '<div class="gwm-err">' + esc(r.message) + '</div>'; return; }
        S.msg = r.message;
        remember([{ email: r.message.from, name: r.message.from_name }]); remember(r.message.to); remember(r.message.cc); fillAddrs();
        if (m && !m.seen) { m.seen = true; changeFolderCounts(-1); renderList(); }
        renderMessage();
    });
}
function folderOptions(skip) {
    return (S.folders[S.acct] || []).filter(function (f) { return f.selectable && f.name !== skip; }).map(function (f) { return '<option value="' + esc(f.name) + '">' + esc((f.depth ? '  '.repeat(f.depth) : '') + f.label) + '</option>'; }).join('');
}
function renderMessage() {
    var m = S.msg; if (!m) { return; }
    var isDraft = m.draft || role() === 'drafts';
    var h = '<div class="gwm-rbar">' +
        '<button type="button" class="ib sq mob" data-act="back" title="一覧に戻る"><i class="fa fa-arrow-left"></i></button>' +
        (isDraft ? '<button type="button" class="ib on" data-act="edit-draft"><i class="fa fa-pencil"></i> 編集して送る</button>' :
        '<button type="button" class="ib" data-act="reply"><i class="fa fa-reply"></i> 返信</button>' +
        '<button type="button" class="ib" data-act="reply-all"><i class="fa fa-reply-all"></i> 全員に返信</button>' +
        '<button type="button" class="ib" data-act="forward"><i class="fa fa-share"></i> 転送</button>') +
        '<span class="sp"></span>' +
        '<button type="button" class="ib sq danger" data-act="delete" title="削除（Delete）"><i class="fa fa-trash-o"></i></button>' +
        (role() !== 'junk' ? '<button type="button" class="ib sq" data-act="junk" title="迷惑メールへ"><i class="fa fa-ban"></i></button>' : '<button type="button" class="ib sq" data-act="notjunk" title="迷惑メールではない（受信トレイへ）"><i class="fa fa-inbox"></i></button>') +
        '<button type="button" class="ib sq" data-act="unread" title="未読にする"><i class="fa fa-envelope-o"></i></button>' +
        '<button type="button" class="ib sq' + (m.flagged ? ' on' : '') + '" data-act="flag" title="フラグ"><i class="fa ' + (m.flagged ? 'fa-flag' : 'fa-flag-o') + '"></i></button>' +
        '<select data-act="move" title="移動"><option value="">移動…</option>' + folderOptions(S.folder) + '</select>' +
        '<a class="ib sq" data-nopjax download href="' + esc(aurl(S.acct + '/eml', { folder: S.folder, uid: m.uid })) + '" title="メールを保存（.eml）"><i class="fa fa-download"></i></a>' +
        '</div>' +
        '<div class="gwm-rhead"><h2 class="gwm-subject">' + esc(m.subject || '（件名なし）') + '</h2>' +
        '<div class="gwm-from">' + avatar(m.from_name, m.from, true) + '<div class="meta"><div><span class="nm">' + esc(m.from_name || m.from) + '</span> <span class="ad">&lt;' + esc(m.from) + '&gt;</span></div>' +
        '<div class="rc">宛先: ' + esc(who(m.to) || '—') + (m.cc.length ? '<br>Cc: ' + esc(who(m.cc)) : '') + (m.bcc.length ? '<br>Bcc: ' + esc(who(m.bcc)) : '') + '</div></div>' +
        '<span class="dt">' + esc(when(m.date, true)) + '</span></div></div>';
    if (m.remote_content && !S.remote) { h += '<div class="gwm-banner"><i class="fa fa-shield"></i><span>プライバシーのため、外部の画像を止めています。</span><button type="button" class="btns" data-act="show-remote" style="height:28px">画像を表示</button></div>'; }
    if (m.attachments.length) {
        h += '<div class="gwm-atts">' + m.attachments.map(function (a) {
            var icon = /^image\//.test(a.type) ? 'fa-file-image-o' : /pdf/.test(a.type) ? 'fa-file-pdf-o' : /word|document/.test(a.type) ? 'fa-file-word-o' : /sheet|excel/.test(a.type) ? 'fa-file-excel-o' : /presentation|powerpoint/.test(a.type) ? 'fa-file-powerpoint-o' : /zip|compressed/.test(a.type) ? 'fa-file-archive-o' : 'fa-file-o';
            return '<a class="gwm-att" data-nopjax download href="' + esc(aurl(S.acct + '/attachment', { folder: S.folder, uid: m.uid, index: a.index })) + '" title="' + esc(a.name) + ' をダウンロード"><i class="fa ' + icon + '"></i><span class="n">' + esc(a.name) + '</span><span class="s">' + size(a.size) + '</span></a>';
        }).join('') + '</div>';
    }
    h += '<div class="gwm-body"><iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="本文"></iframe></div>';
    $('gwm-read').innerHTML = h;
    var remote = S.remote ? ' https: http:' : '';
    var doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:' + remote + '; style-src \'unsafe-inline\'' + remote + '; font-src data:' + remote + '; media-src data:">' +
        '<base target="_blank"><style>html,body{margin:0}body{padding:16px 20px;font:14px/1.6 -apple-system,"Segoe UI","Hiragino Sans","Hiragino Kaku Gothic ProN",Meiryo,sans-serif;color:#1f2328;word-wrap:break-word}img{max-width:100%;height:auto}blockquote{margin:0 0 0 .8ex;border-left:2px solid #cfd5dc;padding-left:1ex;color:#444}pre{white-space:pre-wrap}</style></head><body>' + m.html + '</body></html>';
    $('gwm-read').querySelector('iframe').srcdoc = doc;
}

// ---- composer -----------------------------------------------------------
function fillAddrs() { var dl = $('gwm-addrs'); if (!dl) { return; } dl.innerHTML = Object.keys(S.addrs).slice(0, 300).map(function (k) { return '<option value="' + esc(S.addrs[k]) + '">'; }).join(''); }
function sigHtml(a) { return a && a.signature ? '<div class="gwm-sig"><br>' + esc(a.signature).replace(/\n/g, '<br>') + '</div>' : ''; }
function compose(o) {
    o = o || {};
    if (S.comp && S.comp.dirty && !confirm('書きかけのメールがあります。破棄して新しく書きますか？')) { return; }
    var a = acct();
    S.comp = { mode: o.mode || 'new', reply_folder: o.reply_folder || '', reply_uid: o.reply_uid || '', in_reply_to: o.in_reply_to || '', references: o.references || '', draft_uid: o.draft_uid || '', source_folder: o.source_folder || '', source_uid: o.source_uid || '', keep: o.keep || [], files: [], dirty: false };
    $('gwm-cfrom').innerHTML = S.accounts.map(function (x) { return '<option value="' + x.id + '"' + (x.id === (o.account || a.id) ? ' selected' : '') + '>' + esc((x.from_name ? x.from_name + ' ' : '') + '<' + x.email + '>') + '</option>'; }).join('');
    $('gwm-cto').value = o.to || ''; $('gwm-ccc').value = o.cc || ''; $('gwm-cbcc').value = o.bcc || '';
    $('gwm-rcc').style.display = o.cc ? '' : 'none'; $('gwm-rbcc').style.display = o.bcc ? '' : 'none';
    $('gwm-csubj').value = o.subject || '';
    $('gwm-ed').innerHTML = o.html != null ? o.html : '<div><br></div>' + sigHtml(a);
    $('gwm-ctitle').textContent = o.title || '新しいメール';
    $('gwm-cmsg').textContent = ''; $('gwm-cmsg').className = 'msg';
    renderChips();
    var c = $('gwm-comp'); c.classList.add('on'); c.classList.remove('min');
    setTimeout(function () { if (!$('gwm-cto').value) { $('gwm-cto').focus(); } else { var ed = $('gwm-ed'); ed.focus(); var r = document.createRange(); r.setStart(ed, 0); r.collapse(true); var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); } }, 30);
}
function renderChips() {
    var c = S.comp, h = '';
    c.keep.forEach(function (k, i) { h += '<span class="gwm-chip"><i class="fa fa-paperclip"></i><span class="n">' + esc(k.name) + '</span><span class="s">' + size(k.size) + '</span><button type="button" data-unkeep="' + i + '" title="外す">×</button></span>'; });
    c.files.forEach(function (f, i) { h += '<span class="gwm-chip"><i class="fa fa-paperclip"></i><span class="n">' + esc(f.name) + '</span><span class="s">' + size(f.size) + '</span><button type="button" data-unfile="' + i + '" title="外す">×</button></span>'; });
    $('gwm-catts').innerHTML = h;
}
function addFiles(list) {
    var total = S.comp.files.concat(S.comp.keep).reduce(function (s, f) { return s + f.size; }, 0);
    Array.prototype.forEach.call(list, function (f) {
        if (total + f.size > C.maxAttach) { toast('添付は合わせて ' + Math.round(C.maxAttach / 1048576) + ' MB までです（' + f.name + '）。大きいファイルはファイル共有の共有リンクで送ってください。', true); return; }
        total += f.size; S.comp.files.push(f);
    });
    S.comp.dirty = true; renderChips();
}
function quoteHead(m) { return '<div class="gwm-qh">' + esc(when(m.date, true)) + '、' + esc(m.from_name ? m.from_name + ' <' + m.from + '>' : m.from) + ' のメール:</div>'; }
function replyTo(all) {
    var m = S.msg, a = acct(), me = a.email.toLowerCase();
    var to = (m.reply_to.length ? m.reply_to : [{ email: m.from, name: m.from_name }]);
    if (role() === 'sent') { to = m.to; }
    var cc = [];
    if (all) {
        var seen = {}; to.forEach(function (x) { seen[x.email] = 1; }); seen[me] = 1;
        m.to.concat(m.cc).forEach(function (x) { if (!seen[x.email]) { seen[x.email] = 1; cc.push(x); } });
    }
    var subj = /^(re|ｒｅ|返信)\s*[:：]/i.test(m.subject) ? m.subject : 'Re: ' + m.subject;
    compose({ mode: 'reply', title: subj, to: who(to), cc: who(cc), subject: subj, reply_folder: S.folder, reply_uid: m.uid, in_reply_to: m.message_id, references: m.references.concat([m.message_id]).join(' '),
        html: '<div><br></div>' + sigHtml(a) + quoteHead(m) + '<blockquote>' + m.quote_html + '</blockquote>' });
}
function forward() {
    var m = S.msg, a = acct();
    var subj = /^(fwd?|転送)\s*[:：]/i.test(m.subject) ? m.subject : 'Fwd: ' + m.subject;
    var head = '<div class="gwm-qh">---------- 転送するメール ----------<br>差出人: ' + esc(m.from_name ? m.from_name + ' <' + m.from + '>' : m.from) + '<br>日時: ' + esc(when(m.date, true)) + '<br>件名: ' + esc(m.subject) + '<br>宛先: ' + esc(who(m.to)) + (m.cc.length ? '<br>Cc: ' + esc(who(m.cc)) : '') + '</div>';
    compose({ mode: 'forward', title: subj, subject: subj, source_folder: S.folder, source_uid: m.uid, keep: m.attachments.slice(), html: '<div><br></div>' + sigHtml(a) + head + '<br>' + m.quote_html });
}
function editDraft() {
    var m = S.msg;
    compose({ mode: 'draft', title: m.subject || '下書き', to: who(m.to), cc: who(m.cc), bcc: who(m.bcc), subject: m.subject, draft_uid: m.uid, source_folder: S.folder, source_uid: m.uid, keep: m.attachments.slice(), html: m.quote_html,
        in_reply_to: m.references.length ? m.references[m.references.length - 1] : '', references: m.references.join(' ') });
}
function compData() {
    var c = S.comp, fd = new FormData();
    fd.append('to', $('gwm-cto').value); fd.append('cc', $('gwm-ccc').value); fd.append('bcc', $('gwm-cbcc').value);
    fd.append('subject', $('gwm-csubj').value); fd.append('html', $('gwm-ed').innerHTML);
    ['in_reply_to', 'references', 'reply_folder', 'reply_uid', 'draft_uid', 'source_folder', 'source_uid'].forEach(function (k) { if (c[k]) { fd.append(k, c[k]); } });
    c.keep.forEach(function (k) { fd.append('keep[]', k.index); });
    c.files.forEach(function (f) { fd.append('files[]', f, f.name); });
    return fd;
}
function compBusy(on, text) { $('gwm-send').disabled = on; $('gwm-cmsg').className = 'msg'; $('gwm-cmsg').textContent = text || ''; }
function send() {
    if (!$('gwm-cto').value.trim() && !$('gwm-ccc').value.trim() && !$('gwm-cbcc').value.trim()) { $('gwm-cmsg').className = 'msg err'; $('gwm-cmsg').textContent = '宛先を入れてください。'; $('gwm-cto').focus(); return; }
    if (!$('gwm-csubj').value.trim() && !confirm('件名がありません。このまま送りますか？')) { return; }
    var id = Number($('gwm-cfrom').value);
    compBusy(true, '送信しています…');
    api(id + '/send', compData(), true).then(function (r) {
        compBusy(false);
        if (!r.ok) { $('gwm-cmsg').className = 'msg err'; $('gwm-cmsg').textContent = r.message; $('gwm-cmsg').title = r.message; return; }
        var wasDraft = S.comp.draft_uid;
        S.comp = null; $('gwm-comp').classList.remove('on');
        toast(r.message);
        if (S.msg && S.msg.uid && !wasDraft && id === S.acct) { var it = itemOf(S.msg.uid); if (it) { it.answered = true; renderList(); } }
        if (id === S.acct && (role() === 'sent' || role() === 'drafts')) { loadList(true); }
        if (id === S.acct) { loadFolders(); }
    });
}
function saveDraft(close) {
    if (!S.comp) { return Promise.resolve(); }
    var id = Number($('gwm-cfrom').value);
    compBusy(true, '下書きに保存しています…');
    return api(id + '/draft', compData(), true).then(function (r) {
        compBusy(false);
        if (!r.ok) { $('gwm-cmsg').className = 'msg err'; $('gwm-cmsg').textContent = r.message; return; }
        if (S.comp) {
            S.comp.draft_uid = r.draft_uid || ''; S.comp.dirty = false;
            // The new draft carries the attachments now.
            S.comp.source_folder = r.folder; S.comp.source_uid = r.draft_uid || '';
            var n = 0; S.comp.keep = S.comp.keep.concat(S.comp.files.map(function (f) { return { name: f.name, size: f.size }; })).map(function (k) { return { name: k.name, size: k.size, index: n++ }; }); S.comp.files = []; renderChips();
        }
        $('gwm-cmsg').textContent = '下書きに保存しました（' + when(new Date().toISOString()) + '）';
        if (close) { S.comp = null; $('gwm-comp').classList.remove('on'); toast('下書きに保存しました。'); }
        if (id === S.acct && role() === 'drafts') { loadList(true); }
        if (id === S.acct) { loadFolders(); }
    });
}
function closeComposer() {
    if (S.comp && S.comp.dirty) { saveDraft(true); return; }
    S.comp = null; $('gwm-comp').classList.remove('on');
}

// ---- events -------------------------------------------------------------
root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-act],[data-acct],[data-folder],[data-uid],[data-q],[data-ck],[data-unkeep],[data-unfile],[data-filter],[data-cmd]');
    if (!t || !root.contains(t)) { return; }
    if (t.hasAttribute('data-ck')) { var u = Number(t.getAttribute('data-ck')); if (t.checked) { S.sel[u] = 1; } else { delete S.sel[u]; } renderBulk(); e.stopPropagation(); return; }
    if (t.hasAttribute('data-unkeep')) { S.comp.keep.splice(Number(t.getAttribute('data-unkeep')), 1); S.comp.dirty = true; renderChips(); return; }
    if (t.hasAttribute('data-unfile')) { S.comp.files.splice(Number(t.getAttribute('data-unfile')), 1); S.comp.dirty = true; renderChips(); return; }
    if (t.hasAttribute('data-cmd')) {
        e.preventDefault(); var cmd = t.getAttribute('data-cmd'), val = t.getAttribute('data-val');
        if (cmd === 'createLink') { val = prompt('リンク先の URL', 'https://'); if (!val || !/^(https?:|mailto:)/i.test(val)) { return; } }
        document.execCommand('styleWithCSS', false, true); document.execCommand(cmd, false, val || null); $('gwm-ed').focus(); S.comp.dirty = true; return;
    }
    if (t.hasAttribute('data-q')) {
        e.stopPropagation(); var it = t.closest('[data-uid]'), uid = Number(it.getAttribute('data-uid')), m = itemOf(uid), q = t.getAttribute('data-q');
        if (q === 'seen') { setFlag([uid], 'seen', !m.seen); } else if (q === 'flag') { setFlag([uid], 'flagged', !m.flagged); } else if (q === 'delete') { del([uid]); }
        return;
    }
    if (t.hasAttribute('data-filter')) { S.filter = t.getAttribute('data-filter'); S.page = 1; root.querySelectorAll('.gwm-tab').forEach(function (x) { x.classList.toggle('on', x === t); }); loadList(); return; }
    if (t.hasAttribute('data-acct')) { var id = Number(t.getAttribute('data-acct')); if (id !== S.acct) { openAccount(id); } return; }
    if (t.hasAttribute('data-folder')) { if (t.classList.contains('dis')) { return; } S.folder = t.getAttribute('data-folder'); S.page = 1; S.sel = {}; S.q = ''; $('gwm-q').value = ''; $('gwm-search').classList.remove('has'); closeMessage(); renderTree(); view(null); loadList(); return; }
    if (t.hasAttribute('data-uid') && !t.hasAttribute('data-act')) { if (e.target.closest('.ck')) { return; } if (e.ctrlKey || e.metaKey) { var cu = Number(t.getAttribute('data-uid')); if (S.sel[cu]) { delete S.sel[cu]; } else { S.sel[cu] = 1; } renderList(); return; } openMessage(Number(t.getAttribute('data-uid'))); return; }
    var act = t.getAttribute('data-act'), m2 = S.msg;
    switch (act) {
        case 'compose': compose(); break;
        case 'refresh': loadFolders(); loadList(); break;
        case 'clear-search': S.q = ''; $('gwm-q').value = ''; $('gwm-search').classList.remove('has'); S.page = 1; renderTree(); loadList(); break;
        case 'prev': if (S.page > 1) { S.page--; loadList(); } break;
        case 'next': if (S.page < S.pages) { S.page++; loadList(); } break;
        case 'show-side': view('side'); break;
        case 'back': view(null); break;
        case 'reply': replyTo(false); break;
        case 'reply-all': replyTo(true); break;
        case 'forward': forward(); break;
        case 'edit-draft': editDraft(); break;
        case 'delete': if (m2) { del([m2.uid]); } break;
        case 'junk': if (m2) { move([m2.uid], '@junk', '迷惑メール'); } break;
        case 'notjunk': if (m2) { move([m2.uid], '@inbox', '受信トレイ'); } break;
        case 'unread': if (m2) { setFlag([m2.uid], 'seen', false); closeMessage(); } break;
        case 'flag': if (m2) { setFlag([m2.uid], 'flagged', !m2.flagged); } break;
        case 'show-remote': S.remote = true; renderMessage(); break;
        case 'bulk-read': setFlag(selected(), 'seen', true); break;
        case 'bulk-unread': setFlag(selected(), 'seen', false); break;
        case 'bulk-flag': setFlag(selected(), 'flagged', true); break;
        case 'bulk-junk': move(selected(), '@junk', '迷惑メール'); break;
        case 'bulk-delete': del(selected()); break;
        case 'bulk-clear': S.sel = {}; renderList(); break;
        case 'new-folder':
            var name = prompt('新しいフォルダーの名前'); if (!name) { break; }
            api(S.acct + '/folder', { name: name }, true).then(function (r) { if (!r.ok) { toast(r.message, true); return; } toast('フォルダー「' + name + '」を作りました。'); loadFolders(); });
            break;
        case 'show-cc': $('gwm-rcc').style.display = ''; $('gwm-ccc').focus(); break;
        case 'show-bcc': $('gwm-rbcc').style.display = ''; $('gwm-cbcc').focus(); break;
        case 'send': send(); break;
        case 'save-draft': saveDraft(false); break;
        case 'discard':
            if (S.comp && (S.comp.dirty || S.comp.draft_uid) && !confirm('このメールを破棄しますか？')) { break; }
            if (S.comp && S.comp.draft_uid) { api(Number($('gwm-cfrom').value) + '/delete', { folder: S.comp.source_folder, uids: [S.comp.draft_uid], forever: 1 }, true); }
            S.comp = null; $('gwm-comp').classList.remove('on'); break;
        case 'comp-min': $('gwm-comp').classList.toggle('min'); e.stopPropagation(); break;
        case 'comp-max': $('gwm-comp').classList.toggle('max'); $('gwm-comp').classList.remove('min'); e.stopPropagation(); break;
        case 'comp-close': closeComposer(); e.stopPropagation(); break;
    }
});
root.addEventListener('change', function (e) {
    var t = e.target;
    if (t.id === 'gwm-all') { S.sel = {}; if (t.checked) { S.items.forEach(function (m) { S.sel[m.uid] = 1; }); } renderList(); return; }
    if (t.id === 'gwm-file') { addFiles(t.files); t.value = ''; return; }
    if (t.getAttribute('data-act') === 'move' && t.value && S.msg) { var f = folderOf(t.value); move([S.msg.uid], t.value, f ? f.label : t.value); }
});
root.addEventListener('input', function (e) { if (S.comp && e.target.closest('#gwm-comp')) { S.comp.dirty = true; } });
var qTimer;
$('gwm-q').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(qTimer); doSearch(); } if (e.key === 'Escape') { this.value = ''; doSearch(); } });
$('gwm-q').addEventListener('input', function () { clearTimeout(qTimer); qTimer = setTimeout(doSearch, 600); });
function doSearch() { var q = $('gwm-q').value.trim(); if (q === S.q) { return; } S.q = q; S.page = 1; S.sel = {}; $('gwm-search').classList.toggle('has', q !== ''); renderTree(); loadList(); }

// Drag a mail onto a folder to move it; drop files onto the composer to attach them.
root.addEventListener('dragstart', function (e) { var it = e.target.closest && e.target.closest('.gwm-it'); if (!it) { return; } var u = Number(it.getAttribute('data-uid')); var uids = S.sel[u] ? selected() : [u]; e.dataTransfer.setData('text/x-gwm', JSON.stringify(uids)); e.dataTransfer.effectAllowed = 'move'; });
root.addEventListener('dragover', function (e) {
    var f = e.target.closest && e.target.closest('.gwm-f[data-folder]');
    if (f && Array.prototype.indexOf.call(e.dataTransfer.types, 'text/x-gwm') >= 0) { e.preventDefault(); root.querySelectorAll('.gwm-f.drop').forEach(function (x) { x.classList.remove('drop'); }); f.classList.add('drop'); return; }
    var c = e.target.closest && e.target.closest('#gwm-comp'); if (c && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') >= 0) { e.preventDefault(); c.classList.add('dragover'); }
});
root.addEventListener('dragleave', function (e) { var f = e.target.closest && e.target.closest('.gwm-f'); if (f) { f.classList.remove('drop'); } if (e.target.id === 'gwm-comp' || e.target.id === 'gwm-ed') { $('gwm-comp').classList.remove('dragover'); } });
root.addEventListener('drop', function (e) {
    var f = e.target.closest && e.target.closest('.gwm-f[data-folder]');
    root.querySelectorAll('.gwm-f.drop').forEach(function (x) { x.classList.remove('drop'); });
    if (f) { var d = e.dataTransfer.getData('text/x-gwm'); if (d) { e.preventDefault(); var name = f.getAttribute('data-folder'); if (name !== S.folder) { var fo = folderOf(name); move(JSON.parse(d), name, fo ? fo.label : name); } } return; }
    var c = e.target.closest && e.target.closest('#gwm-comp'); if (c && e.dataTransfer.files.length) { e.preventDefault(); c.classList.remove('dragover'); addFiles(e.dataTransfer.files); }
});
$('gwm-ed').addEventListener('paste', function (e) {
    // Pasted files become attachments; pasted rich text keeps only safe formatting (the server cleans it again).
    if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length) { e.preventDefault(); addFiles(e.clipboardData.files); }
});

listen(document, 'keydown', function (e) {
    if (!document.getElementById('gwm')) { return; }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && S.comp && e.target.closest && e.target.closest('#gwm-comp')) { e.preventDefault(); send(); return; }
    if (e.key === 'Escape' && S.comp && e.target.closest && e.target.closest('#gwm-comp')) { closeComposer(); return; }
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable || e.ctrlKey || e.metaKey || e.altKey) { return; }
    var idx = S.items.findIndex(function (m) { return m.uid === S.cur; });
    if (e.key === 'Delete' && S.cur) { e.preventDefault(); del([S.cur]); }
    else if ((e.key === 'j' || e.key === 'ArrowDown') && S.items.length) { e.preventDefault(); var n = S.items[Math.min(idx + 1, S.items.length - 1)]; if (n) { openMessage(n.uid); } }
    else if ((e.key === 'k' || e.key === 'ArrowUp') && S.items.length && idx > 0) { e.preventDefault(); openMessage(S.items[idx - 1].uid); }
    else if (e.key === 'r' && S.msg) { e.preventDefault(); replyTo(false); }
    else if (e.key === 'a' && S.msg) { e.preventDefault(); replyTo(true); }
    else if (e.key === 'f' && S.msg) { e.preventDefault(); forward(); }
    else if (e.key === 'n' || e.key === 'c') { e.preventDefault(); compose(); }
    else if (e.key === '/') { e.preventDefault(); $('gwm-q').focus(); }
});
listen(window, 'beforeunload', function (e) { if (S.comp && S.comp.dirty && document.getElementById('gwm')) { e.preventDefault(); e.returnValue = ''; } });

// New mail: every minute, the unread count of each inbox.
function poll() {
    if (!document.getElementById('gwm')) { clearInterval(window.__gwmTimer); return; }
    if (document.hidden) { return; }
    api('poll').then(function (r) {
        if (!r.ok) { return; }
        Object.keys(r.accounts).forEach(function (k) {
            var id = Number(k), st = r.accounts[k], a = S.accounts.filter(function (x) { return x.id === id; })[0];
            if (!a || st.error) { return; }
            var before = S.uidnext[id]; S.uidnext[id] = st.uidnext; a._unread = st.unread;
            if (id === S.acct) {
                a._unread = null;
                var inbox = (S.folders[id] || []).filter(function (f) { return f.role === 'inbox'; })[0];
                if (inbox) { inbox.unread = st.unread; }
                if (before && st.uidnext > before && role() === 'inbox' && S.page === 1 && !S.q) { loadList(true); toast('新しいメールが届きました。'); }
            }
        });
        renderTree();
    });
}

// ---- start --------------------------------------------------------------
fit(); setTimeout(fit, 300);
var want = Number(new URLSearchParams(location.search).get('account')) || 0;
try { if (!want) { want = Number(localStorage.getItem('gwm.acct')) || 0; } } catch (e) {}
var first = S.accounts.filter(function (a) { return a.id === want; })[0] || S.accounts[0];
openAccount(first.id);
window.__gwmTimer = setInterval(poll, 60000);
setTimeout(poll, 3000);
})();
</script>
@endverbatim
