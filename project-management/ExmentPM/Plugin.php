<?php

namespace App\Plugins\ExmentPM;

/**
 * The page of this plugin under the class name of a single-type plugin.
 *
 * config.json declares several types, so Exment normally loads PluginPage, PluginValidator,
 * PluginEvent, PluginBatch and PluginSetting and never asks for a class called Plugin. On a site
 * whose plugins.plugin_types row lists one type only (files replaced without uploading the zip),
 * Exment builds a class called Plugin for the page on every request
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
     * @param \ExmentAdminCore\Admin\Form\EmbeddedForm $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        (new PluginSetting($this->plugin))->setCustomOptionForm($form);
    }
}
