<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The channels of a project's reminders (pm_portal_reminder_rules.channels, ISS00432) go to the
     * project's chat channels (pm_portal_channels, ISS02939), as channels that take reminders:
     * one place for the channels of a project. The URLs stay encrypted as they are. Each keeps
     * its index there (legacy_index), so a reminder it was sent before is not sent again.
     */
    public function up(): void
    {
        if (!\Schema::hasTable('pm_portal_reminder_rules') || !\Schema::hasTable('pm_portal_channels')
            || !\Schema::hasColumn('pm_portal_reminder_rules', 'channels')) {
            return;
        }
        $now = now();
        foreach (DB::table('pm_portal_reminder_rules')->whereNotNull('channels')->get() as $rule) {
            $channels = json_decode(strval($rule->channels), true);
            if (!is_array($channels) || empty($channels)) {
                continue;
            }
            foreach (array_values($channels) as $i => $c) {
                $type = is_array($c) ? strval($c['type'] ?? '') : '';
                if (!in_array($type, ['slack', 'teams', 'line_works'], true)) {
                    continue;
                }
                $done = DB::table('pm_portal_channels')->where('project_id', $rule->project_id)->where('legacy_index', $i)->exists();
                if ($done) {
                    continue;
                }
                DB::table('pm_portal_channels')->insert([
                    'project_id' => $rule->project_id,
                    'type' => $type,
                    'label' => mb_substr(strval($c['label'] ?? ''), 0, 40),
                    'secret' => $type === 'line_works' ? null : (strval($c['url'] ?? '') ?: null),
                    'target' => $type === 'line_works' ? (strval($c['channel'] ?? '') ?: null) : null,
                    'events' => json_encode(['reminder']),
                    'enabled' => 1,
                    'legacy_index' => $i,
                    'updated_user_id' => $rule->updated_user_id ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('pm_portal_reminder_rules')->where('id', $rule->id)->update(['channels' => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // the channels stay where they are now
    }
};
