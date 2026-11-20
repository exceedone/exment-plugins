<?php

namespace App\Plugins\ExmentPM;

/**
 * Packaging marker, not a plugin entry point.
 *
 * The Plugin Market refuses an upload whose ZIP has no Plugin.php. This plugin
 * declares several types, so Exment loads PluginPage, PluginValidator,
 * PluginEvent and PluginBatch and never asks for a class called Plugin. The
 * file exists to satisfy the upload check and is otherwise unused.
 */
class Plugin
{
}
