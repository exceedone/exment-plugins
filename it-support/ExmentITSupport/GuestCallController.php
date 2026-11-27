<?php

namespace App\Plugins\ExmentITSupport;

use Illuminate\Http\Request;

/**
 * The customer's side of a call (ISS02941): /support/call/<token> (the page) and its API.
 * No login: the link's token, and after the first opening the browser's own bind secret, both
 * in headers (X-Call-Token, X-Call-Bind). Writes only from the page itself (Exment's origin).
 */
class GuestCallController
{
    protected function gate(): void
    {
        if (!Its::isInstalled() || !GuestCall::enabled()) {
            abort(404);
        }
    }

    public function page(string $token)
    {
        $this->gate();
        $call = GuestCall::byToken($token);
        $nonce = base64_encode(random_bytes(16));
        $html = view(Its::view('public.call'), [
            'nonce' => $nonce,
            'gone' => !isset($call),
            'boot' => [
                'join' => PublicRoutes::url('call', 'api', 'join'),
                'signal' => PublicRoutes::url('call', 'api', 'signal'),
                'signals' => PublicRoutes::url('call', 'api', 'signals'),
                'end' => PublicRoutes::url('call', 'api', 'end'),
                'ice' => GuestCall::iceServers(Its::setting('call_ice', '')),
            ],
        ])->render();

        return response($html, isset($call) ? 200 : 404, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'unsafe-inline'; connect-src 'self'; media-src 'self' blob:; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function json(Request $request, bool $write, $fn)
    {
        $this->gate();
        try {
            if ($write && !GuestChat::fromWindow($request->header('Origin'), $request->header('Referer'), $request->header('Sec-Fetch-Site'), GuestChat::ownOrigins())) {
                abort(403);
            }

            return response()->json(array_merge(['ok' => true], $fn()), 200, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $code = $e->getStatusCode();
            $msg = $code === 409 ? 'このリンクは、もうほかのブラウザーで使われています。' : ($code === 403 ? 'このページからだけ送れます。' : 'このリンクは使えません（期限が切れたか、通話が終わりました）。');

            return response()->json(['ok' => false, 'message' => $msg], $code);
        }
    }

    protected function call(Request $request, bool $ended = false): object
    {
        return GuestCall::guestCall(strval($request->header('X-Call-Token')), strval($request->header('X-Call-Bind')), $ended);
    }

    public function join(Request $request)
    {
        return $this->json($request, true, function () use ($request) {
            $bind = strval($request->header('X-Call-Bind'));

            return GuestCall::bind(strval($request->header('X-Call-Token')), $bind !== '' ? $bind : null);
        });
    }

    public function signal(Request $request)
    {
        return $this->json($request, true, function () use ($request) {
            // the description as one JSON text, past the trimming of input strings
            $json = strval($request->input('payload_json', ''));
            $payload = $json !== '' ? json_decode($json, true) : null;
            if ($json !== '' && !is_array($payload)) {
                throw new \InvalidArgumentException('合図を読めませんでした。');
            }
            GuestCall::signal($this->call($request), 'guest', strval($request->input('type')), $payload);

            return [];
        });
    }

    public function signals(Request $request)
    {
        return $this->json($request, false, function () use ($request) {
            // an ended call is still read once (the agent's hangup)
            return GuestCall::signals($this->call($request, true), 'guest', intval($request->query('after', 0)));
        });
    }

    public function end(Request $request)
    {
        return $this->json($request, true, function () use ($request) {
            return GuestCall::end($this->call($request), 'guest');
        });
    }
}
