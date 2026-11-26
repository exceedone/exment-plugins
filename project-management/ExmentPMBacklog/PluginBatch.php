<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Services\Plugin\PluginBatchBase;

/**
 * Every five minutes (batch_cron): run each connection whose time has come.
 *
 *   php artisan exment:batch --name=ExmentPMBacklog                 the due ones
 *   php artisan exment:batch --name=ExmentPMBacklog --param1=<id>   that one now
 *   php artisan exment:batch --name=ExmentPMBacklog --param1=<id> --param2=<limit>
 *
 * Each connection has its own interval and its own lock, so a long first
 * import of one project neither runs twice nor holds the others back past
 * their own turn.
 */
class PluginBatch extends PluginBatchBase
{
    /**
     * 0 (success), also when no connection is due; 1 when a connection failed. Not true:
     * exment:batch turns true into the exit code 1.
     *
     * @return int
     */
    public function execute()
    {
        $options = (array) ($this->pluginOptions->command_options ?? []);
        $only = intval(array_get($options, 'param1') ?: 0);
        $limit = intval(array_get($options, 'param2') ?: Syncer::DEFAULT_LIMIT);

        PluginPage::ensureTables();
        $ran = 0;
        $failed = 0;
        foreach (Connections::all() as $conn) {
            if ($only ? intval($conn->id) !== $only : !Connections::due($conn)) {
                continue;
            }
            // moved to the data sync of Exment 7.3: run there, not here (never both)
            if (!empty($conn->sync_mapping_id) && PluginPage::hasSync()) {
                continue;
            }
            $r = Syncer::runConnection($conn, $only ? 'manual' : 'schedule', max(1, $limit));
            $c = $r['counts'] + ['add' => 0, 'update' => 0, 'comment' => 0, 'error' => 0];
            $this->line(Lang::t('batch_run', [
                'name' => $conn->name,
                'status' => Lang::t('status_' . $r['status']) . ($r['message'] !== '' ? ' ' . $r['message'] : ''),
                'add' => $c['add'], 'update' => $c['update'], 'comment' => $c['comment'], 'error' => $c['error'],
            ]));
            $ran++;
            if ($r['status'] === 'failed') {
                $failed++;
            }
        }
        if ($ran === 0) {
            $this->line(Lang::t('batch_none'));
        }

        return $failed > 0 ? 1 : 0;
    }

    protected function line(string $text): void
    {
        if (app()->runningInConsole()) {
            echo $text, PHP_EOL;
        }
    }
}
