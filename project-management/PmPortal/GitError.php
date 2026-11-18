<?php

namespace App\Plugins\PmPortal;

/**
 * A Git failure whose message was written here, and is therefore safe to put
 * in front of whoever clicked.
 *
 * It exists to tell those apart from every other throwable, because the
 * others carry infrastructure in their text. Measured on this deployment: a
 * Git server that is down answers
 *
 *   cURL error 7: Failed to connect to 127.0.0.1 port 3000 after 2093 ms:
 *   Could not connect to server (...) for
 *   http://127.0.0.1:3000/api/v1/repos/pm/demo/keys
 *
 * - the API host, its port, the API path and the organisation name, straight
 * into last_error and from there into the project settings tab of anybody who
 * can open it. The token is not in it only because it travels as a header.
 *
 * So: a driver throws this for anything a user can act on, and
 * GitProvisioner::publicError() replaces anything else with a generic line
 * plus a reference it has logged. Catch sites must never print a raw
 * getMessage().
 *
 * The message is the Japanese sentence a person reads; $reason is a short
 * machine word for the JSON answer's `error`, which a client may branch on
 * and never shows.
 */
class GitError extends \Exception
{
    /** @var string */
    public $reason;

    public function __construct(string $message = '', string $reason = 'git_failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->reason = $reason;
    }
}
