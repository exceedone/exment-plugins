<?php

namespace App\Plugins\ExmentSupportDesk;

use Illuminate\Support\Facades\Route;

/**
 * The pages customers open without logging in: /support/...
 *
 * Exment gives plugin routes the admin login on purpose and lets config.json
 * take away only middlewares that cost time, never the login. A help desk
 * needs a door for people who have no account, so these routes are
 * registered here, from PluginPage's constructor, which Exment runs while it
 * boots (ExmentServiceProvider::load). They sit next to Exment's own public
 * form (/publicform) and use the same idea: the URL carries the secret, the
 * page shows only what that secret opens.
 *
 * What protects them: a token that opens one ticket or one address's list
 * (Tokens), CSRF and the session from the "adminweb" group, a rate limit per
 * IP, noindex, and nothing internal on the page (notes, other tickets,
 * agents' addresses).
 *
 * The intake endpoints take a shared key instead of a session; they are
 * machine-to-machine.
 */
class PublicRoutes
{
    const PREFIX = 'support';

    /** @var bool */
    protected static $registered = false;

    public static function register(): void
    {
        if (static::$registered || app()->routesAreCached()) {
            return;
        }
        static::$registered = true;

        $c = PublicController::class;

        Route::group(['prefix' => static::PREFIX, 'middleware' => ['adminweb']], function () use ($c) {
            Route::get('/', $c . '@home');
            Route::get('request', $c . '@requestForm');
            Route::post('request', $c . '@postRequest');
            Route::get('faq', $c . '@faq');
            Route::get('faq/search', $c . '@faqSearch');
            Route::get('faq/{id}', $c . '@faqShow')->where('id', '[0-9]+');
            Route::post('faq/{id}/vote', $c . '@faqVote')->where('id', '[0-9]+');
            Route::get('mine', $c . '@mineForm');
            Route::post('mine', $c . '@postMine');
            Route::get('mine/{token}', $c . '@mineList');
            // the approval of a request (Catalog): an Exment login, the approver only
            Route::get('approval/{id}', $c . '@approvalPage')->where('id', '[0-9]+');
            Route::post('approval/{id}', $c . '@postApproval')->where('id', '[0-9]+');
            Route::get('t/{token}', $c . '@ticket');
            Route::post('t/{token}/reply', $c . '@postReply');
            Route::post('t/{token}/otp/send', $c . '@postOtpSend');
            Route::post('t/{token}/otp', $c . '@postOtp');
            Route::post('t/{token}/rate', $c . '@postRate');
            Route::post('t/{token}/solved', $c . '@postSolved');
            Route::post('t/{token}/call-request', $c . '@postCallRequest');
            Route::get('t/{token}/file/{uuid}', $c . '@file');
        });

        // The chat window on a website (ISS02941): its script and its page, and its API with
        // no session and no CSRF (the window is in another site's page), a guest token instead.
        $g = GuestChatController::class;
        Route::group(['prefix' => static::PREFIX . '/chat'], function () use ($g) {
            Route::get('{key}.js', $g . '@script')->where('key', '[0-9a-f]{24}');
            Route::get('{key}', $g . '@window')->where('key', '[0-9a-f]{24}');
            Route::post('api/start', $g . '@start');
            Route::get('api/lines', $g . '@lines');
            Route::post('api/post', $g . '@post');
        });

        // A call with a customer by a link (ISS02941): the page, and its API with no session and no
        // CSRF; the link's token and the browser's own secret in headers instead.
        $k = GuestCallController::class;
        Route::group(['prefix' => static::PREFIX . '/call'], function () use ($k) {
            Route::get('{token}', $k . '@page')->where('token', '[0-9a-f]{64}');
            Route::post('api/join', $k . '@join');
            Route::post('api/signal', $k . '@signal');
            Route::get('api/signals', $k . '@signals');
            Route::post('api/end', $k . '@end');
        });

        // Machine to machine: no session, no CSRF, a key instead.
        Route::group(['prefix' => static::PREFIX . '/intake'], function () use ($c) {
            Route::post('alert', $c . '@intakeAlert');
            Route::post('mail', $c . '@intakeMail');
        });
    }

    public static function url(string ...$parts): string
    {
        return url(implode('/', array_merge([static::PREFIX], $parts)));
    }
}
