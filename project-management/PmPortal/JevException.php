<?php

namespace App\Plugins\PmPortal;

/**
 * A TypeSafe call that did not produce usable answers.
 *
 * The reason is a short machine word, never free text from the remote side,
 * so the user-facing message can be looked up in the lang file
 * (ai.err_<reason>) and the log keeps something greppable:
 *
 *   not_configured  no API key anywhere
 *   unreachable     connection refused / timed out
 *   unauthorized    401 or 403
 *   rejected        422, the request body was not accepted
 *   rate_limited    429
 *   overloaded      529
 *   bad_response    anything else, or a 200 without an answers block, or
 *                   answers this code could not read
 *   daily_cap       not sent at all: the project's calls for today are spent
 */
class JevException extends \RuntimeException
{
    /** @var string */
    public $reason;

    /** @var int HTTP status of the failed call, 0 when there was none */
    public $status;

    public function __construct(string $reason, int $status = 0, ?\Throwable $previous = null)
    {
        $this->reason = $reason;
        $this->status = $status;

        // The local cause, when there is one, goes into the message so the
        // log can tell a DNS failure from a timeout. It is cURL's own text
        // about the connection - no header, so never the key.
        $cause = isset($previous) ? ' - ' . mb_strimwidth(strval($previous->getMessage()), 0, 160, '...') : '';

        parent::__construct(
            'TypeSafe request failed: ' . $reason . ($status > 0 ? ' (HTTP ' . $status . ')' : '') . $cause,
            $status,
            $previous
        );
    }

    /**
     * The lang key under "ai" that explains this to the person who clicked.
     */
    public function langKey(): string
    {
        return 'ai.err_' . $this->reason;
    }
}
