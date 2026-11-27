<?php

namespace App\Plugins\ExmentSupportDesk\Mail;

/**
 * A mail problem whose message is safe to show an administrator: it never
 * carries the password or the raw server reply.
 */
class MailException extends \RuntimeException
{
}
