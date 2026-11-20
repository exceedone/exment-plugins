<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Office - Exment</title>
<style>
html, body { margin: 0; height: 100%; font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", sans-serif; background: #f4f5f7; color: #222; }
.gw-bar { height: 44px; display: flex; align-items: center; gap: 10px; padding: 0 12px; background: #fff; border-bottom: 1px solid #dde1e6; box-sizing: border-box; }
.gw-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gw-state { font-size: 12px; color: #5f6b7a; white-space: nowrap; }
.gw-state.is-warn { color: #a15c00; }
.gw-state.is-error { color: #c62828; }
.gw-spacer { flex: 1; }
.gw-btn { font: inherit; font-size: 13px; padding: 4px 12px; border: 1px solid #c6ccd4; border-radius: 4px; background: #fff; color: inherit; cursor: pointer; text-decoration: none; }
.gw-btn:hover { background: #f0f2f5; }
.gw-powered { font-size: 11px; color: #5f6b7a; white-space: nowrap; }
.gw-powered a { color: inherit; }
.gw-frame { position: absolute; top: 44px; left: 0; right: 0; bottom: 0; }
.gw-frame iframe { width: 100%; height: 100%; border: 0; display: block; background: #fff; }
.gw-message { max-width: 640px; margin: 80px auto; padding: 24px; background: #fff; border: 1px solid #dde1e6; border-radius: 6px; line-height: 1.7; }
@media (prefers-color-scheme: dark) {
  html, body { background: #1e1f22; color: #e6e6e6; }
  .gw-bar, .gw-message { background: #2b2d31; border-color: #3a3d42; }
  .gw-btn { background: #2b2d31; border-color: #4a4e55; }
  .gw-btn:hover { background: #34373c; }
  .gw-state, .gw-powered { color: #a8b0ba; }
}
</style>
</head>
<body>
<div class="gw-bar">
    @if ($view)
        <a class="gw-btn" href="{{ $edit_url }}" target="_blank" rel="noopener" data-gw="edit">Office で開く（編集）</a>
    @else
        <a class="gw-btn" href="{{ $back_url }}" data-gw="back">← ファイルへ戻る</a>
    @endif
    <span class="gw-name" data-gw="name"></span>
    <span class="gw-state" data-gw="state">読み込み中…</span>
    <span class="gw-spacer"></span>
    {{-- required by the trademark rules of ibisOffice (TRADEMARK.md) --}}
    <span class="gw-powered">Powered by <a href="{{ $project_url }}" target="_blank" rel="noopener">ibisOffice</a> {{ $version }} (AGPL-3.0)</span>
</div>

@if (!$available)
    <div class="gw-message">
        <p><strong>ibisOffice がまだ使えません。</strong></p>
        <p>システム管理者が <a href="{{ $settings_url }}">Exment Plugin Groupware の画面</a> で ibisOffice をインストールすると、Word・Excel・PowerPoint をブラウザーで開けるようになります。</p>
    </div>
@else
    <div class="gw-frame" data-gw="frame" hidden></div>
    <div class="gw-message" data-gw="message" hidden></div>
    <script type="module">
    const cfg = {!! json_encode([
        'editorBase' => $editor_base,
        'hostModule' => $host_module,
        'officeOrigin' => $office_origin,
        'infoUrl' => $info_url,
        'backUrl' => $back_url,
        'userName' => $user_name,
        'locale' => $locale,
        'view' => $view,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (k) => document.querySelector('[data-gw="' + k + '"]');

    function state(text, level) {
        const el = $('state');
        el.textContent = text;
        el.className = 'gw-state' + (level ? ' is-' + level : '');
    }
    function stop(html) {
        state('開けません', 'error');
        const m = $('message');
        m.innerHTML = html;
        m.hidden = false;
        $('frame').hidden = true;
    }
    async function call(url, method, body) {
        const res = await fetch(url, {
            method: method || 'GET',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        let json = {};
        try { json = await res.json(); } catch (e) { json = {}; }
        return { status: res.status, ok: res.ok && json.result !== false, json: json };
    }

    let info = null;
    let checkedOutHere = false;
    let dirty = false;

    function checkinOnLeave() {
        if (!checkedOutHere) { return; }
        checkedOutHere = false;
        const fd = new FormData();
        fd.append('_token', token);
        navigator.sendBeacon(info.urls.checkin, fd);
    }

    async function closeEditor() {
        if (checkedOutHere) {
            checkedOutHere = false;
            await call(info.urls.checkin, 'POST', new FormData());
        }
        location.href = info.urls.page;
    }

    async function start() {
        if (!('gpu' in navigator)) {
            stop('<p><strong>このブラウザーでは Office を開けません。</strong></p><p>ibisOffice は WebGPU を使います。Chrome・Edge、または Safari 26 以降で開いてください。ファイルは <a href="' + cfg.backUrl + '">ファイルの画面</a> からダウンロードできます。</p>');
            return;
        }

        const r = await call(cfg.infoUrl);
        if (!r.ok) {
            stop('<p>' + (r.json.message || 'ファイルを開けません。') + '</p>');
            return;
        }
        info = r.json;
        $('name').textContent = info.name;
        document.title = info.name + ' - Office';

        // the editor has no editing together yet: lock the file while it is open for editing
        let readOnly = !info.can_edit || cfg.view;
        let readOnlyMessage = '';
        if (info.can_edit && !info.checked_out_by_me && !cfg.view) {
            const c = await call(info.urls.checkout, 'POST', new FormData());
            if (c.ok) {
                checkedOutHere = true;
            } else {
                readOnly = true;
                readOnlyMessage = c.json.message || '';
            }
        }
        if (cfg.view) {
            readOnlyMessage = 'プレビューです。編集は「Office で開く（編集）」から。';
        } else if (!info.can_edit) {
            readOnlyMessage = info.checked_out_by
                ? info.checked_out_by + ' さんが編集中のため、読み取り専用で開いています。'
                : '編集の権限がないため、読み取り専用で開いています。';
        }

        const frameBox = $('frame');
        const iframe = document.createElement('iframe');
        iframe.title = info.name;
        iframe.allow = 'clipboard-read; clipboard-write; fullscreen';
        let src = cfg.editorBase + 'index.html?host=1';
        if (cfg.officeOrigin) { src += '&origin=' + encodeURIComponent(location.origin); }
        iframe.src = src;
        frameBox.appendChild(iframe);
        frameBox.hidden = false;

        let mod;
        try {
            mod = await import(cfg.hostModule);
        } catch (e) {
            stop('<p>ibisOffice を読み込めませんでした。システム管理者にインストールを確かめてもらってください。</p>');
            return;
        }

        let version = String(info.version);
        const attach = {
            options: {
                locale: cfg.locale,
                user: { name: cfg.userName || 'Exment' },
                ui: { closeButton: true, downloadButton: false, startWindow: false },
                features: { localFiles: false },
            },
            host: {
                async saveFile({ bytes, baseVersion }) {
                    const fd = new FormData();
                    fd.append('file', new Blob([bytes]), info.name);
                    fd.append('base_version', baseVersion || version);
                    fd.append('comment', 'Office（ibisOffice）で編集');
                    state('保存中…');
                    const s = await call(info.urls.version, 'POST', fd);
                    if (s.ok) {
                        version = String(s.json.version);
                        state('v' + s.json.version_no + ' を保存しました');
                        return { ok: true, version: version };
                    }
                    const reasons = { 409: 'conflict', 423: 'locked', 403: 'noPermission', 404: 'notFound', 413: 'tooLarge', 401: 'unauthorized', 419: 'unauthorized' };
                    state('保存できませんでした', 'error');
                    return { ok: false, reason: reasons[s.status] || 'other', message: s.json.message || '保存できませんでした。' };
                },
                async openUrl({ url }) {
                    if (/^(https?:|mailto:)/i.test(url)) { window.open(url, '_blank', 'noopener'); }
                    return { ok: true };
                },
            },
            onEvent(event) {
                if (event.type === 'dirty') {
                    dirty = !!event.dirty;
                    if (dirty) { state('保存していない変更があります', 'warn'); }
                } else if (event.type === 'closeRequested') {
                    closeEditor();
                } else if (event.type === 'error') {
                    state(event.message || 'エラー', 'error');
                }
            },
        };
        if (cfg.officeOrigin) { attach.officeOrigin = cfg.officeOrigin; }

        let handle;
        try {
            handle = await mod.attachIframe(iframe, attach);
        } catch (e) {
            stop('<p>Office を開けませんでした（' + (e && e.reason ? e.reason : e) + '）。</p>');
            return;
        }

        const d = await fetch(info.urls.download, { credentials: 'same-origin' });
        if (!d.ok) {
            stop('<p>ファイルを読み込めませんでした。</p>');
            return;
        }
        await handle.load({
            name: info.name,
            bytes: await d.arrayBuffer(),
            version: version,
            readOnly: readOnly,
            readOnlyMessage: readOnlyMessage || undefined,
        });
        state(readOnly ? '読み取り専用' : ('v' + info.version_no + '・編集中（ほかの人は変更できません）'), readOnly ? 'warn' : '');

        window.addEventListener('beforeunload', (e) => {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });
        if (cfg.view) {
            return;
        }
        window.addEventListener('pagehide', checkinOnLeave);
        $('back').addEventListener('click', (e) => {
            if (dirty && !confirm('保存していない変更があります。閉じますか？')) { e.preventDefault(); return; }
            e.preventDefault();
            closeEditor();
        });
    }

    start().catch((e) => stop('<p>Office を開けませんでした。</p><p>' + String(e && e.message ? e.message : e).replace(/[<>&]/g, '') + '</p>'));
    </script>
@endif
</body>
</html>
