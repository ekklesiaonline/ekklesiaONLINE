<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed the visitor.converted_window_days system setting — the rolling
 * window (in days) used by the visitor dashboard "converted to members"
 * KPI tile. Editable from /system/settings; defaults to 30.
 */
class SeedVisitorConvertedWindowSetting extends Migration
{
    public function up()
    {
        $db = db_connect();

        try {
            if (! $db->tableExists('system_settings')) {
                return;
            }

            $exists = $db->table('system_settings')
                ->where('setting_key', 'visitor.converted_window_days')
                ->countAllResults() > 0;

            if ($exists) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $db->table('system_settings')->insert([
                'setting_key'   => 'visitor.converted_window_days',
                'setting_value' => '30',
                'data_type'     => 'integer',
                'category'      => 'visitors',
                'description'   => 'Rolling window in days for the visitor dashboard "converted to members" KPI tile.',
                'is_locked'     => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SeedVisitorConvertedWindowSetting failed: ' . $e->getMessage());
        }
    }

    public function down()
    {
        $db = db_connect();

        if (! $db->tableExists('system_settings')) {
            return;
        }

        $db->table('system_settings')
            ->where('setting_key', 'visitor.converted_window_days')
            ->delete();
    }
}
