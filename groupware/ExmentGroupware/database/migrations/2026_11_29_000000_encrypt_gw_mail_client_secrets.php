<?php

use App\Plugins\ExmentGroupware\SecretOption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The web mail's OAuth client secrets (ms_client_secret, google_client_secret) were kept as plain
 * text in the plugin's settings up to 0.5.0. They are kept encrypted now (SecretOption: Exment's
 * data keys when the site has one, else the app key), and this encrypts the ones already saved.
 *
 * Idempotent: a value already encrypted is left as it is (SecretOption::seal), so running it
 * again, or after the settings were saved on the new version, changes nothing. A site where it
 * did not run still works: the mail reads a plain value as it is, and the next save of the
 * settings encrypts it.
 */
return new class extends Migration
{
    protected const PLUGIN_NAME = 'ExmentGroupware';

    public function up(): void
    {
        if (!class_exists(SecretOption::class)) {
            require_once __DIR__ . '/../../SecretOption.php';
        }

        if (!Schema::hasTable('plugins') || !Schema::hasColumn('plugins', 'custom_options')) {
            return;
        }
        $changed = false;
        foreach (DB::table('plugins')->where('plugin_name', static::PLUGIN_NAME)->get(['id', 'custom_options']) as $row) {
            $options = is_string($row->custom_options) ? json_decode($row->custom_options, true) : (array) $row->custom_options;
            if (!is_array($options)) {
                continue;
            }
            $sealed = SecretOption::sealOptions($options);
            if ($sealed === $options) {
                continue;
            }
            DB::table('plugins')->where('id', $row->id)->update(['custom_options' => json_encode($sealed, JSON_UNESCAPED_UNICODE)]);
            $changed = true;
        }
        if ($changed) {
            $this->clearCache();
        }
    }

    public function down(): void
    {
        // the secrets stay encrypted (0.5.0 read them as they were, so it cannot use them; enter them again there)
    }

    protected function clearCache(): void
    {
        try {
            if (class_exists(\Exceedone\Exment\Model\System::class)) {
                \Exceedone\Exment\Model\System::clearCache();
            }
        } catch (\Throwable $e) {
            // read again on the next cache
        }
    }
};
