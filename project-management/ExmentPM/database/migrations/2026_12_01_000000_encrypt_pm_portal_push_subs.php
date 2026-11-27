<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Web Push: a browser's subscription is kept encrypted, as the site's private key (Exment's
     * data keys when the site has them, else the application key). The endpoint is encrypted in
     * its own column (text); the browser's keys move to p256dh_enc and auth_enc (text), the old
     * plain columns are emptied. endpoint_hash stays as it is: the lookups and the unique key.
     *
     * Idempotent: a row whose keys are already in the encrypted columns is left alone, and an
     * endpoint is encrypted only while it is still a plain https address.
     */
    public function up(): void
    {
        $table = 'pm_portal_push_subs';
        if (!\Schema::hasTable($table)) {
            return;
        }
        if (!\Schema::hasColumn($table, 'p256dh_enc')) {
            \Schema::table($table, function ($t) {
                $t->text('p256dh_enc')->nullable();
                $t->text('auth_enc')->nullable();
            });
        }
        foreach (DB::table($table)->whereNull('p256dh_enc')->orderBy('id')->get() as $row) {
            $endpoint = strval($row->endpoint);
            DB::table($table)->where('id', $row->id)->update([
                'endpoint' => strpos($endpoint, 'https://') === 0 ? $this->seal($endpoint) : $endpoint,
                'p256dh_enc' => $this->seal(strval($row->p256dh)),
                'auth_enc' => $this->seal(strval($row->auth)),
                'p256dh' => '',
                'auth' => '',
            ]);
        }
    }

    /** As PortalChannels::encrypt (the plugin's classes are not loaded in a migration). */
    protected function seal(string $plain): string
    {
        $encrypted = 'Exceedone\Exment\Services\Encryption\EncryptedValue';
        $dataKeys = 'Exceedone\Exment\Services\Encryption\DataKeys';
        if (class_exists($encrypted) && class_exists($dataKeys)) {
            try {
                if (call_user_func([$dataKeys, 'hasKey'])) {
                    return strval(call_user_func([$encrypted, 'encrypt'], $plain));
                }
            } catch (\Throwable $e) {
                // the application key below
            }
        }

        return 'app:' . encrypt($plain);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing: the values stay encrypted (the code before this reads them as broken rows, which
     * it removes; the browsers subscribe again).
     */
    public function down(): void
    {
    }
};
