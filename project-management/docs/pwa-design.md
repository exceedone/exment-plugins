# ExmentPM on phones: an installable web app, then Web Push (ISS02939 item 6)

Off at first: a site feature `exment_pm.pwa` (Feature management) for step 1, and
`exment_pm.web_push` for step 2, which needs step 1's service worker.

## Step 1: installable, an offline page, phone screens

### Where it lives

The portal is `/admin/plugins/exment_pm/`. Everything is a route of the plugin below it, so the
service worker's scope is the portal only (not the rest of Exment, not other plugins):

| route | |
|---|---|
| `GET …/exment_pm/manifest.webmanifest` | `name` (the site's name + 「プロジェクト管理」), `short_name`, `start_url` `…/exment_pm/?source=pwa`, `scope` `…/exment_pm/`, `display: standalone`, theme and background colours from the portal's own, icons 192 / 512 / maskable 512 |
| `GET …/exment_pm/sw.js` | the service worker, `Content-Type: text/javascript`, `Cache-Control: no-cache`, `Service-Worker-Allowed` not needed (its own folder) |
| `GET …/exment_pm/offline` | the page shown when offline (no data, the site's name, 「オフラインです」, a retry button) |
| `GET …/exment_pm/icon/{192,512,maskable}.png` | the app icons (PNG files in the plugin; a site may replace them later) |

These routes answer only while the feature is on (404 otherwise). The manifest and the icons
hold no data of anybody, but they are behind Exment's login like the rest of the portal; the
offline page and the service worker too (the browser fetches them with the login cookie).

The portal page is drawn inside Exment's layout, so `<link rel="manifest">`,
`<meta name="theme-color">` and `<link rel="apple-touch-icon">` are added to `<head>` by the
portal's script at start (Chrome, Edge and Safari read a manifest link added this way). The
service worker is registered there too, and taken off again (`unregister`) when the feature is
off.

### What the service worker does

- Installs: caches the offline page, the icons, and the portal's own versioned assets
  (`/exment-pm/<name>.<version>.js|css`, which never change once written).
- Page loads (navigations) inside the scope: **network first**. Only when the network fails is
  the cached offline page shown. Pages are never cached.
- The portal's versioned assets: cache first (they are immutable). Old versions are deleted on
  activate.
- **Everything else** (API answers, issues, comments, files, Exment's own pages): not touched; it
  goes to the network as without a service worker. No data of issues is kept on the phone.
- A new version waits until all the portal's tabs are closed (no `skipWaiting` in the middle of
  typing).

### Installing

- Android / desktop Chrome and Edge: the browser's own install. Also a 「ホーム画面に追加」 item in
  the portal's user menu while `beforeinstallprompt` was seen.
- iPhone / iPad: a short help in the same menu (Safari: share → ホーム画面に追加).
- Opened as an app (`display-mode: standalone`): no Exment header around it, a back button in
  the portal's own bar, and it keeps the login (Exment's session as in the browser).

### Phone screens

Checked at 390 × 844 and 360 × 800 (portrait), with fixes where weak: home, my issues, an issue,
writing a comment (keyboard open, the send button stays visible, safe area at the bottom),
the bells. The list of what was weak and what changed goes into the PR.

## Step 2: Web Push for the bells

- **Keys**: one VAPID key pair (P-256) made per site the first time the feature is turned on.
  The private key is kept encrypted (the data keys, else the app key, as the channels' secrets);
  the public key is given to the browsers. 「鍵を作り直す」 for the site administrator (all
  subscriptions must subscribe again).
- **Per person, opt-in**: 「この端末に通知する」 in the portal's user menu asks the browser's
  permission and saves the subscription. 「止める」 removes it. Each person sees and removes their
  own devices only.
- **Table** `pm_portal_push_subs`: user, endpoint (sha256 unique), `p256dh`, `auth`, the device
  name from the user agent, created, last sent, failures.
- **What is sent**: when Exment makes a bell (NotifyNavbar) for a person with subscriptions:
  the bell's subject (at most 120 characters) and the link to open. Encrypted for the device
  (RFC 8291, aes128gcm), signed with VAPID (RFC 8292, the site's URL as `sub`, no e-mail address).
  TTL 1 day. Done after the answer is sent (or by the batch), never in the request that made
  the bell.
- **Only to push services**: the endpoint must be https and one of the known push services
  (`fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.push.apple.com`,
  `*.notify.windows.com`); anything else is refused when subscribing (no requests to any other
  address from the server). 404 / 410 from the service removes the subscription; 5 failures in a
  row too.
- **Without a library**: PHP's openssl (ECDH on P-256, HKDF, AES-128-GCM, ES256); tested with the
  RFC 8291 example values and a fake push service.
- The service worker shows the notice (`showNotification`), a tap opens or focuses the portal at
  the link.

### Step 2 as built

- `PortalPush` (the feature, subscriptions, sending), `PortalPushCrypto` (RFC 8291 / 8292 with
  openssl), `PortalPushStore` (the rows). The key pair is in its own table `pm_portal_push_keys`
  (one row: the public key, the private key's PEM encrypted), next to `pm_portal_push_subs`.
- The feature is on only with `exment_pm.pwa` on too.
- The link of every notice is the portal with `?notices=1`: the bell's list (#221) opens.
  The service worker follows links inside the portal only.
- A bell is kept when it is written (NotifyNavbar `created`, after PortalNotify let it through)
  and sent at `terminating` (after the answer, or at the end of an artisan command such as the
  batch) or after the queue job that wrote it. A job of the sync queue (Exment's default; it
  runs inside the request) does not send: its bells wait for terminating too. At most 3 a person
  a request (a bulk edit makes many; the list has them all), and one flush stops after 20 s.
- The VAPID `sub` is APP_URL when it is https, else the request's address when that is https.
  With neither, nothing is sent, the menu item is not shown and the settings card says so.
  401 / 403 from a push service (the site's signature refused) are logged; the row is kept.
- The key pair is made on the first `POST …/push` (`action` key), under a cache lock, never on
  a page view; a page reads the public key only.
- A browser whose subscription the server does not keep for the person signed in (a shared
  browser, or removed on the server) unsubscribes when the portal opens.
- Routes: `POST …/push` (`action` key / subscribe / unsubscribe / state, the person's own browser, 30 a minute),
  `POST …/settings/push-keys` (「鍵を作り直す」, system administrators, on the settings page).
- At most 10 browsers a person; a new one replaces the oldest.
- Not yet: a list of one's devices (to remove another one), `pushsubscriptionchange`, a test
  in a real browser with a real push service.

## Tests

- Plain PHP: the manifest (scope, start_url, icons), the push encryption against RFC 8291's
  example, the VAPID JWT, the endpoint allowlist.
- Node: the service worker's routing (navigation → network first / offline page; assets → cache;
  APIs → untouched) with a fake `fetch` and `caches`.
- In a browser (A15): install on Android Chrome and as a desktop app, offline page, phone screens at
  390 / 360, then step 2 with a real browser push service.
