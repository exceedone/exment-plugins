<?php

namespace App\Plugins\ExmentPluginPM\Migrate;

/**
 * Data migration, originally written by TTM as the separate PmMigrate plugin
 * and folded into PmPortal in 1.2.0.
 *
 * A failure whose message is meant for the person at the screen.
 *
 * Anything else that is thrown is logged and answered with a fixed sentence:
 * a QueryException carries the SQL, the host and the database name.
 */
class MigrateException extends \RuntimeException
{
}
