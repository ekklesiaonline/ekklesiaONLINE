<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\CallTaskModel;
use CodeIgniter\Database\BaseConnection;

/**
 * CallTaskSyncService
 *
 * Keeps call_task rows (the human work queue for the 'call' and 'manual'
 * follow-up channels) in step with their canonical followup_log entries.
 *
 * A task is materialized as soon as a call/manual log entry is scheduled,
 * so the queue shows pending, due and overdue work without waiting for
 * the followup:send cron. Manual resolutions (mark done / skip /
 * trigger-now / conversion / delete) transition the linked task — or
 * create it in a terminal state when it was never queued — so queue
 * history always reflects what actually happened.
 */
class CallTaskSyncService
{
    public const HUMAN_CHANNELS = ['call', 'manual'];

    private BaseConnection $db;
    private CallTaskModel  $tasks;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db    = $db ?? db_connect();
        $this->tasks = model(CallTaskModel::class);
    }

    /**
     * Create a task for a call/manual-channel log entry when none exists.
     * A task already linked to the log (any status) is never duplicated —
     * a resolved task is not resurrected.
     *
     * @param string $status 'pending' | 'completed' | 'cancelled'
     * @return int|null Task id (new or existing); null when the log is not
     *                  a human-channel action or the visitor opted out.
     */
    public function materializeForLog(int $logId, string $status = 'pending', ?string $notes = null): ?int
    {
        $existing = $this->tasks->where('log_id', $logId)->first();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $ctx = $this->contextForLog($logId);
        if ($ctx === null) {
            return null;
        }

        // Opt-out only blocks queueing new work; terminal records written by
        // resolveForLog() describe work that already happened and stay.
        if ($status === 'pending' && (new VisitorDirectoryService())->isOptedOut($ctx['person'], (string) $ctx['log']['channel'])) {
            return null;
        }

        $inserted = $this->tasks->insert($this->payload($ctx, $status, $notes), true);

        return $inserted === false ? null : (int) $inserted;
    }

    /**
     * Resolve the task for a log entry: transitions a still-pending task,
     * or creates it in the terminal state when it was never queued (e.g.
     * the action was completed manually before dispatch ran).
     *
     * @param string $status 'completed' | 'cancelled'
     */
    public function resolveForLog(int $logId, string $status, ?string $notes = null): void
    {
        $task = $this->tasks->where('log_id', $logId)->first();

        if (! is_array($task)) {
            $this->materializeForLog($logId, $status, $notes);
            return;
        }

        if ($task['status'] !== 'pending') {
            return;
        }

        if ($status === 'completed') {
            $this->tasks->markCompleted((int) $task['id']);
        } else {
            $this->tasks->markCancelled((int) $task['id']);
        }

        if ($notes !== null && $notes !== '') {
            $this->tasks->update((int) $task['id'], ['notes' => $notes]);
        }
    }

    /**
     * Cancel pending tasks linked to the given log ids. Used by mass-skip
     * paths (member conversion, visitor delete) after the logs are marked.
     *
     * @param list<int> $logIds
     */
    public function cancelForLogs(array $logIds): void
    {
        $logIds = array_values(array_filter(array_map('intval', $logIds), static fn(int $id): bool => $id > 0));
        if ($logIds === []) {
            return;
        }

        $this->db->table('call_task')
            ->whereIn('log_id', $logIds)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Collect the ids of a person's still-pending followup_log entries so a
     * mass-skip path can cancel their tasks before/after marking the logs.
     *
     * @return list<int>
     */
    public function pendingLogIdsForPerson(int $personId, ?int $parishId = null): array
    {
        $builder = $this->db->table('followup_log')
            ->select('id')
            ->where('person_id', $personId)
            ->where('status', 'pending');

        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }

        return array_map('intval', array_column($builder->get()->getResultArray(), 'id'));
    }

    /**
     * Keep a still-pending task's scheduled_date in step with its log.
     */
    public function rescheduleForLog(int $logId, string $scheduledDate): void
    {
        $this->db->table('call_task')
            ->where('log_id', $logId)
            ->where('status', 'pending')
            ->update(['scheduled_date' => $scheduledDate, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Load the log row joined to its action channel/instructions plus the
     * person and parish context needed to build a task row. Returns null
     * for non-human channels or logs without an action.
     *
     * @return array{log: array, person: array, parish: array}|null
     */
    private function contextForLog(int $logId): ?array
    {
        $row = $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.instructions')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'inner')
            ->where('gfl.id', $logId)
            ->get()
            ->getRowArray();

        if (! is_array($row) || ! in_array((string) ($row['channel'] ?? ''), self::HUMAN_CHANNELS, true)) {
            return null;
        }

        $person = $this->db->table('person')->where('id', (int) $row['person_id'])->get()->getRowArray() ?: [];
        $parish = $this->db->table('parish')->where('id', (int) $row['parish_id'])->get()->getRowArray() ?: [];

        return ['log' => $row, 'person' => $person, 'parish' => $parish];
    }

    /**
     * Build the call_task payload from the log/action/person/parish context.
     * Mirrors FollowupDispatchService::dispatchCall/dispatchManual.
     */
    private function payload(array $ctx, string $status, ?string $notes): array
    {
        $log    = $ctx['log'];
        $person = $ctx['person'];
        $parish = $ctx['parish'];
        $phone  = $this->decryptField($person['phone'] ?? null);

        $title = null;
        if (($log['channel'] ?? '') === 'manual') {
            $instructions = trim((string) ($log['instructions'] ?? ''));
            if ($instructions !== '') {
                $title = str_replace(
                    ['{first_name}', '{last_name}', '{parish_name}', '{visit_date}', '{clergy_name}', '{phone}'],
                    [
                        esc((string) ($person['first_name'] ?? '')),
                        esc((string) ($person['last_name'] ?? '')),
                        esc((string) ($parish['name'] ?? '')),
                        esc((string) ($log['scheduled_date'] ?? '')),
                        '',
                        esc($phone),
                    ],
                    $instructions
                );
            }
        }

        $row = [
            'parish_id'      => (int) $log['parish_id'],
            'person_id'      => (int) ($log['person_id'] ?? 0) ?: null,
            'log_id'         => (int) $log['id'],
            'assigned_to'    => (int) ($log['assigned_to'] ?? 0) ?: null,
            'task_type'      => (string) $log['channel'],
            'title'          => $title,
            'phone'          => $phone !== '' ? $phone : null,
            'notes'          => $notes ?? 'Auto-generated from followup sequence.',
            'status'         => $status,
            'scheduled_date' => (string) ($log['scheduled_date'] ?? '') !== '' ? (string) $log['scheduled_date'] : date('Y-m-d'),
        ];

        if ($status === 'completed') {
            $row['completed_at'] = (string) ($log['executed_at'] ?? '') !== ''
                ? (string) $log['executed_at']
                : date('Y-m-d H:i:s');
        }

        return $row;
    }

    private function decryptField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (DataEncryption::isEncrypted($value)
            ? (DataEncryption::decrypt($value) ?? '')
            : $value);
    }
}
