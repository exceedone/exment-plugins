<?php

namespace App\Plugins\ExmentSupportDesk;

/**
 * Packaging marker, not a plugin entry point.
 *
 * The Plugin Market refuses a ZIP without Plugin.php. This plugin declares
 * several types, so Exment loads PluginPage, PluginBatch and PluginEvent and
 * never asks for a class called Plugin.
 */
class Plugin
{
}
