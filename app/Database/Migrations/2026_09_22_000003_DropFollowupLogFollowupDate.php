<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drop followup_log.followup_date — legacy dual-write column. The canonical
 * scheduled-date column is scheduled_date; FollowupLogModel no longer probes
 * or dual-writes the retired column.
 */
class DropFollowupLogFollowupDate extends Migration
{
    public function up()
    {
        $db = db_connect();

        try {
            if ($db->fieldExists('followup_date', 'followup_log')) {
                // Backfill scheduled_date from the legacy column before
                // dropping it, so rows written by old dual-write code keep
                // their intended schedule.
                $db->query(
                    'UPDATE ' . $db->prefixTable('followup_log')
                    . ' SET scheduled_date = followup_date'
                    . ' WHERE scheduled_date IS NULL AND followup_date IS NOT NULL'
                );

                $this->forge->dropColumn('followup_log', 'followup_date');
            }
        } catch (\Throwable $e) {
            log_message('error', 'DropFollowupLogFollowupDate failed: ' . $e->getMessage());
        }
    }

    public function down()
    {
        $db = db_connect();

        try {
            if (! $db->fieldExists('followup_date', 'followup_log')) {
                $this->forge->addColumn('followup_log', [
                    'followup_date' => [
                        'type' => 'DATE',
                        'null' => true,
                    ],
                ]);

                $db->query(
                    'UPDATE ' . $db->prefixTable('followup_log')
                    . ' SET followup_date = scheduled_date WHERE followup_date IS NULL'
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
