<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Services\Plugin\PluginBatchBase;

/**
 * Every five minutes (config.json batch_cron): read the mailbox, update the
 * SLA, chase and close tickets, warn about assets. See Automation.
 */
class PluginBatch extends PluginBatchBase
{
    public function execute()
    {
        if (!Its::isInstalled() || !Feature::enabled(Feature::DESK)) {
            return true;
        }
        Schema::ensureCurrent();
        Automation::run();

        return true;
    }
}
