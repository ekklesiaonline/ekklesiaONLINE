<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reconcile orphaned call_task rows (log_id IS NULL) — legacy rows created
 * before the dispatcher always linked tasks to a followup_log entry.
 *
 * For each orphan we insert a synthetic followup_log row carrying the task's
 * person/parish/schedule/status and then point call_task.log_id at it, so the
 * per-person timeline reads from followup_log alone and the orphan merge
 * path can be retired.
 */
class BackfillCallTaskLogIds extends Migration
{
    public function up()
    {
        $db = db_connect();

        try {
            if (! $db->tableExists('call_task') || ! $db->tableExists('followup_log')) {
                return;
            }

            if (! $db->fieldExists('log_id', 'call_task')) {
                return;
            }

            $orphans = $db->table('call_task')
                ->where('log_id IS NULL', null, false)
                ->get()
                ->getResultArray();

            foreach ($orphans as $task) {
                $statusMap = [
                    'completed' => 'sent',
                    'pending'   => 'pending',
                    'cancelled' => 'skipped',
                    'failed'    => 'failed',
                ];
                $status = $statusMap[$task['status'] ?? ''] ?? 'pending';

                $db->table('followup_log')->insert([
                    'person_id'      => (int) $task['person_id'],
                    'parish_id'      => isset($task['parish_id']) ? (int) $task['parish_id'] : null,
                    'action_id'      => null,
                    'assigned_to'    => isset($task['assigned_to']) ? (int) $task['assigned_to'] : null,
                    'scheduled_date' => $task['scheduled_date'] ?? date('Y-m-d'),
                    'executed_at'    => $task['completed_at'] ?? null,
                    'status'         => $status,
                    'notes'          => $task['notes'] ?? null,
                    'created_at'     => $task['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

                $logId = (int) $db->insertID();
                if ($logId > 0) {
                    $db->table('call_task')
                        ->where('id', (int) $task['id'])
                        ->update(['log_id' => $logId]);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'BackfillCallTaskLogIds failed: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // The synthetic followup_log rows are kept; clearing call_task.log_id
        // would re-orphan tasks with no way to distinguish them from
        // legitimately linked rows. Intentionally a no-op.
    }
}
