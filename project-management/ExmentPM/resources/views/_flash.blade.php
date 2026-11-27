{{-- Exment's messages on the portal's screens (included by _topbar and the settings page).

     1. The messages Exment leaves for the next page (PortalFlash): above all 「ページの有効期限が
        切れました。…」, which Exment flashes when a form was sent with an expired or missing token
        and it went back to the page. Drawn in the portal's own boxes, under the top bar, and
        Exment's copy of the same messages in its content frame is hidden, so each shows once.
     2. A request of the portal's scripts refused because the page expired (419, Exment's answer
        to a script): a bar that stays, with the reason and 再読み込み, instead of a toast that is
        gone in two seconds or nothing at all. --}}
@php
    $ppFlash = \App\Plugins\ExmentPM\PortalFlash::messages();
    $ppFlashIcons = ['error' => 'fa-ban', 'warning' => 'fa-warning', 'success' => 'fa-check', 'info' => 'fa-info-circle'];
    $ppFlashUrl = parse_url(\App\Plugins\ExmentPM\PortalDefinition::url(), PHP_URL_PATH);
    $ppFlashLang = [
        'expired' => strval(\App\Plugins\ExmentPM\PortalDefinition::trans('http_error.expired')),
        'reload' => strval(\App\Plugins\ExmentPM\PortalDefinition::trans('flash.reload')),
        'close' => strval(\App\Plugins\ExmentPM\PortalDefinition::trans('flash.close')),
        'base' => is_string($ppFlashUrl) ? rtrim($ppFlashUrl, '/') : '',
    ];
@endphp
<style>
.pp-flash-list{margin:0 0 10px;}
.pp-flash{display:flex;align-items:flex-start;gap:10px;border:1px solid;border-radius:6px;padding:8px 12px;margin-bottom:8px;
    font-size:13px;line-height:1.6;background:#fff;color:var(--bl-ink,#33434f);}
.pp-flash > .fa{margin-top:4px;flex:none;}
.pp-flash .tx{flex:1 1 auto;min-width:0;white-space:pre-line;overflow-wrap:anywhere;}
.pp-flash .tx b{display:block;}
.pp-flash .cl{flex:none;border:0;background:none;cursor:pointer;color:inherit;opacity:.6;font-size:16px;line-height:1;padding:2px 4px;}
.pp-flash .cl:hover{opacity:1;}
.pp-flash.error{background:#fde8e6;border-color:#e5544b;}
.pp-flash.error > .fa{color:#c0392b;}
.pp-flash.warning{background:#fff6e0;border-color:#eda62a;}
.pp-flash.warning > .fa{color:#b97a0c;}
.pp-flash.success{background:#e8f6f0;border-color:var(--bl-accent,var(--exm-ui-accent,#4cae93));}
.pp-flash.success > .fa{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));}
.pp-flash.info{background:#eaf3fb;border-color:#5b9bd5;}
.pp-flash.info > .fa{color:#2f6fa8;}
/* the same messages, drawn by Exment above the portal: once is enough */
section.content:has(.pp-flash-list) > .alert{display:none;}
#pp-expired{position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:2000;display:flex;align-items:center;gap:10px;
    max-width:calc(100vw - 24px);box-sizing:border-box;background:#fde8e6;border:1px solid #e5544b;border-radius:8px;
    box-shadow:0 8px 22px rgba(23,31,37,.18);padding:10px 12px 10px 14px;font-size:13px;line-height:1.5;color:#33434f;}
#pp-expired[hidden]{display:none;}
#pp-expired > .fa{color:#c0392b;flex:none;}
#pp-expired .tx{flex:1 1 auto;min-width:0;}
#pp-expired .rl{flex:none;border:0;border-radius:5px;background:#c0392b;color:#fff;font:inherit;font-weight:700;padding:5px 12px;cursor:pointer;}
#pp-expired .rl:hover{background:#a93226;}
#pp-expired .cl{flex:none;border:0;background:none;cursor:pointer;color:#6b7a88;font-size:16px;line-height:1;padding:2px 4px;}
</style>
@if(count($ppFlash) > 0)
<div class="pp-flash-list">
    @foreach($ppFlash as $m)
    <div class="pp-flash {{ $m['type'] }}" role="{{ $m['type'] === 'error' || $m['type'] === 'warning' ? 'alert' : 'status' }}">
        <i class="fa {{ $ppFlashIcons[$m['type']] ?? 'fa-info-circle' }}" aria-hidden="true"></i>
        <div class="tx">@if($m['title'] !== '')<b>{{ $m['title'] }}</b>@endif{{ $m['message'] }}</div>
        <button type="button" class="cl" title="{{ $ppFlashLang['close'] }}" aria-label="{{ $ppFlashLang['close'] }}" onclick="this.parentNode.remove();">&times;</button>
    </div>
    @endforeach
</div>
@endif
<script>
(function () {
    // Every screen of the portal includes this, and Exment's page loader runs it again on each
    // screen it loads: the words are renewed, the hooks are set once.
    var L = @json($ppFlashLang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    window.ppExpiredLang = L;
    if (window.ppExpired) { return; }

    // A request to the portal itself (its pages and its light API), not one of Exment's own
    // scripts on the page, which answer a 419 their own way.
    function isPortal(url) {
        var base = (window.ppExpiredLang || {}).base;
        if (!base) { return false; }
        try {
            var u = new URL(String(url || ''), window.location.href);
            if (u.origin !== window.location.origin) { return false; }
            return u.pathname === base || u.pathname.indexOf(base + '/') === 0;
        } catch (e) { return false; }
    }

    // The bar: what happened and 再読み込み. It stays until it is closed or the page reloads;
    // a reload asks first when a form has unsaved work (the portal's own beforeunload).
    function ppExpired() {
        var T = window.ppExpiredLang || {};
        var el = document.getElementById('pp-expired');
        if (!el) {
            el = document.createElement('div');
            el.id = 'pp-expired';
            el.setAttribute('role', 'alert');
            var icon = document.createElement('i');
            icon.className = 'fa fa-exclamation-circle';
            icon.setAttribute('aria-hidden', 'true');
            var tx = document.createElement('span');
            tx.className = 'tx';
            var rl = document.createElement('button');
            rl.type = 'button';
            rl.className = 'rl';
            rl.addEventListener('click', function () { window.location.reload(); });
            var cl = document.createElement('button');
            cl.type = 'button';
            cl.className = 'cl';
            cl.textContent = '×';
            cl.addEventListener('click', function () { el.hidden = true; });
            el.appendChild(icon);
            el.appendChild(tx);
            el.appendChild(rl);
            el.appendChild(cl);
            document.body.appendChild(el);
        }
        el.querySelector('.tx').textContent = T.expired || '';
        el.querySelector('.rl').textContent = T.reload || '';
        var cl2 = el.querySelector('.cl');
        cl2.title = T.close || '';
        cl2.setAttribute('aria-label', T.close || '');
        el.hidden = false;
        return el;
    }
    window.ppExpired = ppExpired;

    // jQuery requests ($.ajax, $.post): every one of the portal, also those that fail quietly.
    if (window.jQuery) {
        window.jQuery(document).ajaxError(function (ev, xhr, settings) {
            if (xhr && xhr.status === 419 && isPortal(settings && settings.url)) { ppExpired(); }
        });
    }
    // fetch: watched, never changed - the caller gets the same answer and words it as before.
    if (typeof window.fetch === 'function') {
        var fetch0 = window.fetch;
        window.fetch = function (input, init) {
            var p = fetch0.apply(this, arguments);
            var url = typeof input === 'string' ? input : (input && input.url) || String(input || '');
            if (isPortal(url) && p && typeof p.then === 'function') {
                p.then(function (r) { if (r && r.status === 419) { ppExpired(); } }, function () { /* the caller handles it */ });
            }
            return p;
        };
    }
})();
</script>
