// ExmentPM's service worker (ISS02939 item 6). CFG comes first (PortalPwa::worker).
// - pages of the portal (navigations in the scope): the network first; the offline page only
//   when the network fails. Pages are never kept.
// - the portal's versioned assets (they never change once written): the cache first.
// - everything else (APIs, issues, files, Exment's own pages): not touched.
'use strict';

var CACHE = 'exment-pm-' + CFG.version;
var SHELL = [CFG.offline].concat(CFG.icons);

function inScope(url) {
    return url.origin === self.location.origin && url.pathname.indexOf(CFG.scope) === 0;
}

function isAsset(url) {
    if (url.origin !== self.location.origin) { return false; }
    // the files under public/ (name.version.js) and the route's fallback (asset/name?v=)
    for (var i = 0; i < CFG.assets.length; i++) {
        if (url.pathname.indexOf(CFG.assets[i]) === 0) { return true; }
    }
    return url.pathname.indexOf(CFG.scope + 'asset/') === 0 && url.searchParams.has('v');
}

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE).then(function (cache) {
        return cache.addAll(SHELL.map(function (u) { return new Request(u, { credentials: 'same-origin' }); }));
    }));
    // no skipWaiting: a new version waits until the portal's tabs are closed
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) {
            return k.indexOf('exment-pm-') === 0 && k !== CACHE;
        }).map(function (k) { return caches.delete(k); }));
    }));
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') { return; }
    var url = new URL(req.url);
    if (req.mode === 'navigate' && inScope(url)) {
        event.respondWith(fetch(req).catch(function () {
            return caches.match(CFG.offline).then(function (r) {
                return r || new Response('offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            });
        }));
        return;
    }
    if (isAsset(url)) {
        event.respondWith(caches.open(CACHE).then(function (cache) {
            return cache.match(req).then(function (hit) {
                if (hit) { return hit; }
                return fetch(req).then(function (res) {
                    if (res && res.ok && res.type === 'basic') { cache.put(req, res.clone()); }
                    return res;
                });
            });
        }));
        return;
    }
    if (SHELL.indexOf(url.pathname) >= 0 && url.origin === self.location.origin) {
        event.respondWith(caches.match(url.pathname).then(function (hit) { return hit || fetch(req); }));
    }
    // anything else: not touched
});
