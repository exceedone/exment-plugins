<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Services\Plugin\PluginBatchBase;

/**
 * Every five minutes (config.json batch_cron): read the mailbox, update the
 * SLA, chase and close tickets, warn about assets. See Automation.
 */
class PluginBatch extends PluginBatchBase
{
    /**
     * 0 (success) also when there is nothing to do: exment:batch turns true into the exit
     * code 1. An error is thrown, and fails the batch.
     *
     * @return int
     */
    public function execute()
    {
        if (!Its::isInstalled() || !Feature::on(Feature::DESK)) {
            return 0;
        }
        Schema::ensureCurrent();
        Automation::run();

        return 0;
    }
}
