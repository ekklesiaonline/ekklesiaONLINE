<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * GuestFollowupService
 *
 * Read-side queries for guest follow-up workflows: per-person progress
 * badges, next pending action, status counts, single log rows, the
 * manual timeline (including orphaned legacy call_task rows), and the
 * guest-hub dashboard aggregates. Writes stay on FollowupLogModel.
 */
class GuestFollowupService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Done/pending follow-up counts keyed by person_id, for the
     * 4-state progress badge on guest lists.
     *
     * @param list<int> $personIds
     * @return array<int, array{done: int, pending: int}>
     */
    public function progressMapForPersons(array $personIds): array
    {
        $personIds = array_values(array_filter(array_map('intval', $personIds), static fn(int $id): bool => $id > 0));
        if ($personIds === []) {
            return [];
        }

        $rows = $this->db->table('followup_log')
            ->select('person_id, SUM(status IN ("sent","skipped")) AS done_count, SUM(status = "pending") AS pending_count')
            ->whereIn('person_id', $personIds)
            ->groupBy('person_id')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['person_id']] = [
                'done'    => (int) $row['done_count'],
                'pending' => (int) $row['pending_count'],
            ];
        }

        return $map;
    }

    /**
     * Next pending follow-up action for a person, with channel and
     * template metadata. Superset select covers both the guest view
     * card and the followup-status JSON endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function nextPendingForPerson(int $personId): ?array
    {
        $row = $this->db->table('followup_log gfl')
            ->select('gfl.id, gfl.scheduled_date, gfl.status, fa.channel, fa.week_number, fa.day_offset, fa.template_id, ft.name AS template_name')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->where('gfl.person_id', $personId)
            ->where('gfl.status', 'pending')
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Follow-up log status totals for a person.
     *
     * @return array{pending: int, sent: int, failed: int, skipped: int}
     */
    public function statusCountsForPerson(int $personId): array
    {
        $counts = $this->db->table('followup_log')
            ->select('status, COUNT(*) AS total')
            ->where('person_id', $personId)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $summary = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($counts as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    /**
     * Single followup_log row.
     *
     * @return array<string, mixed>|null
     */
    public function logRow(int $logId): ?array
    {
        $row = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Full follow-up timeline for a person: followup_log rows joined to
     * action/template/sequence, merged with orphaned legacy call_task
     * rows (log_id IS NULL) normalized into the same shape.
     *
     * @return list<array<string, mixed>>
     */
    public function timelineForPerson(int $personId): array
    {
        $timeline = $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.week_number, fa.day_offset, fa.is_automated,
                      ft.name AS template_name, fs.name AS sequence_name')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->join('followup_sequence fs', 'fs.id = fa.sequence_id', 'left')
            ->where('gfl.person_id', $personId)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->orderBy('gfl.id', 'ASC')
            ->get()
            ->getResultArray();

        $orphanedCallTasks = $this->db->table('call_task')
            ->where('person_id', $personId)
            ->where('log_id IS NULL', null, false)
            ->where('status !=', 'cancelled')
            ->orderBy('scheduled_date', 'ASC')
            ->get()
            ->getResultArray();

        if ($orphanedCallTasks === []) {
            return $timeline;
        }

        $callTaskRows = array_map(static function (array $task): array {
            return [
                'id'                => (int) $task['id'],
                'guest_id'          => (int) $task['guest_id'],
                'action_id'         => null,
                'scheduled_date'    => $task['scheduled_date'] ?? null,
                'status'            => $task['status'] === 'completed' ? 'sent' : $task['status'],
                'executed_at'       => $task['completed_at'] ?? null,
                'notes'             => $task['notes'] ?? null,
                'response_metadata' => null,
                'channel'           => 'call',
                'week_number'       => null,
                'day_offset'        => null,
                'is_automated'      => 0,
                'template_name'     => null,
                'sequence_name'     => null,
                '_source'           => 'call_task',
            ];
        }, $orphanedCallTasks);

        $timeline = array_merge($timeline, $callTaskRows);
        usort($timeline, static function (array $a, array $b): int {
            $cmp = strcmp($a['scheduled_date'] ?? '', $b['scheduled_date'] ?? '');
            return $cmp !== 0 ? $cmp : ((int) ($a['id'] ?? 0)) - ((int) ($b['id'] ?? 0));
        });

        return $timeline;
    }

    /**
     * Distinct persons with a pending follow-up entry in the parish.
     */
    public function pendingDistinctPersonsCount(int $parishId): int
    {
        return (int) ($this->db->table('followup_log gfl')
            ->select('COUNT(DISTINCT gfl.person_id) AS cnt')
            ->where('gfl.status', 'pending')
            ->where('gfl.parish_id', $parishId)
            ->get()->getRowArray()['cnt'] ?? 0);
    }

    /**
     * Guests mid-sequence: not followed up, not converted, with at
     * least one completed and one pending follow-up entry.
     */
    public function inProgressGuestsCount(int $parishId): int
    {
        return (int) $this->db->table('guest g')
            ->where('g.parish_id', $parishId)
            ->where('g.followed_up', 0)
            ->where('g.converted_to_member_at IS NULL', null, false)
            ->whereIn('g.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->whereIn('status', ['sent', 'skipped']);
            })
            ->whereIn('g.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->where('status', 'pending');
            })
            ->countAllResults();
    }
}
