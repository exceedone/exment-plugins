<?php

namespace App\Plugins\PmMigrate;

/**
 * A failure whose message is meant for the person at the screen.
 *
 * Anything else that is thrown is logged and answered with a fixed sentence:
 * a QueryException carries the SQL, the host and the database name.
 */
class MigrateException extends \RuntimeException
{
}
