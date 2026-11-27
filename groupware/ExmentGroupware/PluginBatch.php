<?php

namespace App\Plugins\ExmentGroupware;

use Exceedone\Exment\Services\Plugin\PluginBatchBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The plugin's scheduled work (config.json batch_cron: every 5 minutes with Exment's scheduler):
 *
 *   php artisan exment:batch --name=ExmentGroupware                     everything below
 *   php artisan exment:batch --name=ExmentGroupware --param1=calendar   the calendar's reminders and outside calendars (ISS02947)
 *   php artisan exment:batch --name=ExmentGroupware --param1=chat       the chat's retention (ISS02945)
 *
 * Each part runs only when its tables are there, and a failure of one does not stop the other.
 */
class PluginBatch extends PluginBatchBase
{
    public function execute()
    {
        $options = (array) ($this->pluginOptions->command_options ?? []);
        $target = strtolower(strval(array_get($options, 'param1') ?? 'all'));
        if (in_array($target, ['all', 'calendar'], true)) {
            $this->calendar();
        }
        if (in_array($target, ['all', 'chat'], true)) {
            $this->chat();
        }
        return true;
    }

    protected function calendar(): void
    {
        $boot = __DIR__ . '/Calendar/Bootstrap.php';
        if (!is_file($boot)) {
            return;
        }
        try {
            require_once $boot;
            if (class_exists(Calendar\Reminders::class) && Calendar\Reminders::ready() && FeatureGate::allowedOnSite(FeatureGate::CALENDAR)) {
                $r = Calendar\Reminders::run(null, $this->plugin->getFullUrl('cal'));
                $this->line('calendar: ' . $r['sent'] . ' reminder(s) sent');
            }
        } catch (\Throwable $e) {
            Log::error('ExmentGroupware batch, calendar: ' . get_class($e) . ': ' . $e->getMessage());
        }
        // the outside calendars whose time has come (part 5): a failure is kept on its link
        try {
            if (class_exists(Calendar\Links::class) && Calendar\Links::ready()
                && FeatureGate::allowedOnSite(FeatureGate::CALENDAR) && FeatureGate::allowedOnSite(FeatureGate::CALENDAR_LINKS)) {
                Mail\HostGuard::allowPrivateWeb(boolval($this->plugin->getCustomOption('calendar_allow_private')));
                // Google and Microsoft calendars (part 6) with the site's app of the settings
                $r = Calendar\Links::run(Calendar\Links::BATCH_LINKS, null, 240, new Calendar\CloudSync(new Calendar\CloudOAuth($this->plugin)));
                $this->line('calendar: ' . $r['read'] . ' outside calendar(s) read, ' . $r['failed'] . ' failed');
            }
        } catch (\Throwable $e) {
            Log::error('ExmentGroupware batch, calendar links: ' . get_class($e));
        }
    }

    protected function chat(): void
    {
        try {
            if (Schema::hasTable('gw_chat_rooms') && class_exists(Chat\ChatRetention::class)) {
                $n = Chat\ChatRetention::run(true);
                $this->line('chat: ' . intval($n) . ' removed by the retention');
            }
        } catch (\Throwable $e) {
            Log::error('ExmentGroupware batch, chat: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    protected function line(string $text): void
    {
        if (app()->runningInConsole()) {
            echo $text, PHP_EOL;
        }
    }
}
