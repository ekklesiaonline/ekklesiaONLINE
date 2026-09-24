<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfill call_task rows for existing call/manual-channel followup_log
 * entries that never produced a task.
 *
 * Tasks are now materialized when a human-channel action is scheduled
 * (FollowupLogModel) and resolved alongside the log, but rows created
 * before that invariant — actions completed or skipped manually, or
 * scheduled before the queue existed — have no task. Status mapping:
 *   pending → pending, sent → completed, skipped → cancelled.
 * 'failed' logs get no task (the dispatch attempt failed; nothing queued).
 * Pending logs are only backfilled while their affiliation is still live,
 * matching the gate getPendingDue() applies for dispatch.
 */
class BackfillHumanChannelTasks extends Migration
{
    public function up()
    {
        $db = db_connect();

        try {
            foreach (['call_task', 'followup_log', 'followup_action', 'parish_affiliation'] as $table) {
                if (! $db->tableExists($table)) {
                    return;
                }
            }

            $today   = $db->escape(date('Y-m-d'));
            $paTable = $db->prefixTable('parish_affiliation');

            $rows = $db->table('followup_log gfl')
                ->select('gfl.id, gfl.status')
                ->join('followup_action fa', 'fa.id = gfl.action_id', 'inner')
                ->join('call_task ct', 'ct.log_id = gfl.id', 'left')
                ->whereIn('fa.channel', ['call', 'manual'])
                ->whereIn('gfl.status', ['pending', 'sent', 'skipped'])
                ->where('ct.id IS NULL', null, false)
                ->groupStart()
                    ->where('gfl.status !=', 'pending')
                    ->orWhere(
                        "gfl.status = 'pending' AND EXISTS ("
                        . "SELECT 1 FROM {$paTable} pa"
                        . " WHERE pa.person_id = gfl.person_id"
                        . " AND pa.parish_id = gfl.parish_id"
                        . " AND pa.deleted_at IS NULL"
                        . " AND (pa.end_date IS NULL OR pa.end_date >= {$today})"
                        . ')',
                        null,
                        false
                    )
                ->groupEnd()
                ->get()
                ->getResultArray();

            $statusMap = ['pending' => 'pending', 'sent' => 'completed', 'skipped' => 'cancelled'];
            $sync      = new \App\Services\CallTaskSyncService($db);

            foreach ($rows as $row) {
                $sync->materializeForLog((int) $row['id'], $statusMap[$row['status']] ?? 'pending');
            }
        } catch (\Throwable $e) {
            log_message('error', 'BackfillHumanChannelTasks failed: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // Backfilled tasks are kept — deleting them would remove queue
        // history with no way to distinguish them from real tasks.
    }
}
