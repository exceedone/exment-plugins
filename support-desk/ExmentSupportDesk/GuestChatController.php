<?php

namespace App\Plugins\ExmentSupportDesk;

use Illuminate\Http\Request;

/**
 * The chat window on a website (ISS02941): its script, its page in the iframe, and its API.
 * All on Exment's own address (no CORS headers): the iframe calls the API as its own origin. The
 * API has no session and no CSRF (the iframe sits on another site, where cookies may be
 * blocked); the guest token goes in the X-Chat-Token header instead.
 */
class GuestChatController
{
    protected function gate(): void
    {
        if (!Its::isInstalled() || !GuestChat::enabled()) {
            abort(404);
        }
    }

    /** The script a site pastes: a button that opens the window (an iframe of this site). */
    public function script(string $key)
    {
        $this->gate();
        if (!GuestChat::validKey($key) || GuestChat::box($key) === null) {
            abort(404);
        }
        $src = json_encode(PublicRoutes::url('chat', $key), JSON_UNESCAPED_SLASHES);
        $js = <<<JS
(function () {
  if (window.__sdChat) { return; }
  window.__sdChat = true;
  var b = document.createElement('button');
  b.type = 'button';
  b.textContent = 'お問い合わせ（チャット）';
  b.setAttribute('aria-label', 'チャットでお問い合わせ');
  b.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:2147483000;border:0;border-radius:22px;padding:10px 16px;background:#2563c9;color:#fff;font:600 14px sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.2);cursor:pointer';
  var f = null;
  b.onclick = function () {
    if (!f) {
      f = document.createElement('iframe');
      f.src = {$src};
      f.title = 'チャット';
      f.style.cssText = 'position:fixed;right:18px;bottom:70px;width:min(360px,calc(100vw - 36px));height:min(520px,calc(100vh - 100px));border:1px solid #ddd;border-radius:12px;z-index:2147483000;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.2)';
      document.body.appendChild(f);
    } else {
      f.style.display = f.style.display === 'none' ? '' : 'none';
    }
  };
  (document.body ? Promise.resolve() : new Promise(function (r) { document.addEventListener('DOMContentLoaded', r); })).then(function () { document.body.appendChild(b); });
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /** The window in the iframe: framed only by the box's sites (any when it has none). */
    public function window(string $key)
    {
        $this->gate();
        $box = GuestChat::validKey($key) ? GuestChat::box($key) : null;
        if (!isset($box)) {
            abort(404);
        }
        $nonce = base64_encode(random_bytes(16));
        // the site's reCAPTCHA: its script and frame are Google's
        $robot = GuestChat::robot();
        $google = $robot !== null ? ' https://www.google.com https://www.gstatic.com https://www.recaptcha.net' : '';
        $html = view(Its::view('public.chat'), [
            'nonce' => $nonce,
            'boxName' => strval(array_get($box->value, 'name')),
            'boot' => [
                'key' => $key,
                'start' => PublicRoutes::url('chat', 'api', 'start'),
                'lines' => PublicRoutes::url('chat', 'api', 'lines'),
                'post' => PublicRoutes::url('chat', 'api', 'post'),
                'online' => GuestChat::onlineFor($box),
                'max' => GuestChat::MAX_BODY,
                'robot' => $robot,
            ],
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-{$nonce}'{$google}; frame-src{$google}" . ($google === '' ? " 'none'" : '') . "; style-src 'unsafe-inline'; connect-src 'self'{$google}; img-src 'self' data:{$google}; base-uri 'none'; form-action 'none'; frame-ancestors " . GuestChat::frameAncestors(array_get($box->value, 'chat_sites')),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function json($fn)
    {
        try {
            return response()->json(array_merge(['ok' => true], $fn()), 200, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $code = $e->getStatusCode();

            return response()->json(['ok' => false, 'message' => $code === 429 ? 'しばらく待ってから送ってください。' : ($code === 403 ? 'この窓からだけ送れます。' : 'このチャットは使えません。')], $code);
        }
    }

    /** Writes only from the window itself (Exment's own origin): not a form or no-cors POST of another site. */
    protected function own(Request $request): void
    {
        if (!GuestChat::fromWindow($request->header('Origin'), $request->header('Referer'), $request->header('Sec-Fetch-Site'), GuestChat::ownOrigins())) {
            abort(403);
        }
    }

    public function start(Request $request)
    {
        $this->gate();

        return $this->json(function () use ($request) {
            // inside json(): a write from elsewhere is a JSON 403, as the window shows it
            $this->own($request);
            return GuestChat::start(strval($request->input('key')), strval($request->input('name')), strval($request->input('email')), strval($request->input('body')), strval($request->ip()), strval($request->input('robot', '')));
        });
    }

    public function lines(Request $request)
    {
        $this->gate();

        return $this->json(function () use ($request) {
            return GuestChat::lines(strval($request->header('X-Chat-Token')), intval($request->query('after', 0)));
        });
    }

    public function post(Request $request)
    {
        $this->gate();

        return $this->json(function () use ($request) {
            // inside json(): a write from elsewhere is a JSON 403, as the window shows it
            $this->own($request);
            return GuestChat::post(strval($request->header('X-Chat-Token')), strval($request->input('body')));
        });
    }
}
