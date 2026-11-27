<?php

namespace App\Plugins\ExmentGroupware;

/**
 * Packaging marker, not a plugin entry point.
 *
 * The Plugin Market refuses an upload whose ZIP has no Plugin.php. Since the batch (ISS02947)
 * this plugin declares two types, so Exment loads PluginPage, PluginBatch and PluginSetting and
 * never asks for a class called Plugin. The file exists to satisfy the upload check (and replaces
 * the page class of before on an updated site) and is otherwise unused.
 */
class Plugin
{
}
