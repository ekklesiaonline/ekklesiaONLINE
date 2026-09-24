<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * VisitorFollowupService
 *
 * Read-side queries for visitor follow-up workflows: per-person progress
 * badges, next pending action, status counts, single log rows, the
 * manual timeline, and the visitor-hub dashboard aggregates.
 * Writes stay on FollowupLogModel.
 */
class VisitorFollowupService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Done/pending follow-up counts keyed by person_id, for the
     * 4-state progress badge on visitor lists.
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
     * template metadata. Superset select covers both the visitor view
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
     * action/template/sequence. A linked call_task (log_id) marks the row
     * as a call-channel item — the dispatcher always sets it, and the
     * backfill migration linked the legacy orphans.
     *
     * @return list<array<string, mixed>>
     */
    public function timelineForPerson(int $personId): array
    {
        $rows = $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.week_number, fa.day_offset, fa.is_automated,
                      ft.name AS template_name, fs.name AS sequence_name,
                      ct.id AS call_task_id, ct.task_type AS call_task_type, ct.title AS call_task_title')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->join('followup_sequence fs', 'fs.id = fa.sequence_id', 'left')
            ->join('call_task ct', 'ct.log_id = gfl.id', 'left')
            ->where('gfl.person_id', $personId)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->orderBy('gfl.id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            if (! empty($row['call_task_id'])) {
                $row['_source'] = 'call_task';
                $row['channel'] = $row['channel'] ?? 'call';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Distinct persons with a pending follow-up entry in the parish.
     * Scoped to live, unconverted visitor-domain affiliations — the same
     * population the visitors list shows under followup_stage=pending.
     */
    public function pendingDistinctPersonsCount(int $parishId): int
    {
        return (int) ($this->db->table('followup_log gfl')
            ->select('COUNT(DISTINCT gfl.person_id) AS cnt')
            ->join('person pe', 'pe.id = gfl.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('parish_affiliation pa', 'pa.person_id = gfl.person_id AND pa.parish_id = gfl.parish_id AND pa.deleted_at IS NULL', 'inner')
            ->where('gfl.status', 'pending')
            ->where('gfl.parish_id', $parishId)
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
            ->get()->getRowArray()['cnt'] ?? 0);
    }

    /**
     * Visitors mid-sequence: not followed up, not converted, with at
     * least one completed and one pending follow-up entry.
     */
    public function inProgressVisitorsCount(int $parishId): int
    {
        return (int) $this->db->table('parish_affiliation pa')
            ->where('pa.parish_id', $parishId)
            ->where('pa.membership_status', 'visitor')
            ->where('pa.followed_up', 0)
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->where('pa.deleted_at IS NULL', null, false)
            ->whereIn('pa.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->whereIn('status', ['sent', 'skipped']);
            })
            ->whereIn('pa.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->where('status', 'pending');
            })
            ->countAllResults();
    }
}
