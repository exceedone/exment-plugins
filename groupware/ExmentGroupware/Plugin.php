<?php

namespace App\Plugins\ExmentGroupware;

/**
 * The page of this plugin under the class name of a single-type plugin.
 *
 * config.json declares two types (page, batch), so Exment normally loads PluginPage, PluginBatch
 * and PluginSetting and never asks for a class called Plugin. A site whose plugin files were
 * replaced without uploading the zip still has plugins.plugin_types = "page" from before 0.5.0.
 * Exment then builds a class called Plugin for the page on every request
 * (ExmentServiceProvider::load() calls _getLoadView() on each page plugin) and for the settings
 * form of the plugin screen. This class is that page, the same as PluginPage, with the settings
 * form of PluginSetting, so such a site keeps working until the row is updated.
 *
 * The file is also the Plugin.php that the Plugin Market's upload check requires in the zip.
 */
class Plugin extends PluginPage
{
    /**
     * @return bool
     */
    public function useCustomOption()
    {
        return (new PluginSetting($this->plugin))->useCustomOption();
    }

    /**
     * @param \ExmentAdminCore\Admin\Form $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        (new PluginSetting($this->plugin))->setCustomOptionForm($form);
    }
}
