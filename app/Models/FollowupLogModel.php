<?php

namespace App\Models;

use CodeIgniter\Model;

class FollowupLogModel extends Model
{
    protected $table            = 'followup_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'action_id',
        'assigned_to',
        'scheduled_date',
        'executed_at',
        'status',
        'notes',
        'response_metadata',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'person_id'      => 'required|is_not_unique[person.id]',
        'scheduled_date' => 'required|valid_date',
        'status'         => 'required|in_list[pending,sent,failed,skipped]',
        'action_id'      => 'permit_empty|is_not_unique[followup_action.id]',
        'assigned_to'    => 'permit_empty|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // -----------------------------------------------------------------------
    // Pending / dispatcher queries
    // -----------------------------------------------------------------------

    /**
     * Return all pending log entries whose scheduled_date is on or before $asOfDate.
     * Used by the cron dispatcher to find actions due for execution.
     *
     * @param  string $asOfDate  Y-m-d (defaults to today)
     * @return array
     */
    public function getPendingDue(string $asOfDate = ''): array
    {
        if ($asOfDate === '') {
            $asOfDate = date('Y-m-d');
        }

        $paTable = $this->db->prefixTable('parish_affiliation');
        $today   = $this->db->escape($asOfDate);

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.template_id, fa.instructions, p.first_name, p.last_name, p.email, p.phone, p.opted_out_channels, p.preferred_timezone, p.interests, gfl.parish_id')
            ->join('person p',          'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.status', 'pending')
            ->where('gfl.scheduled_date <=', $asOfDate)
            ->where('p.deleted_at IS NULL', null, false)
            ->where("EXISTS (SELECT 1 FROM {$paTable} pa WHERE pa.person_id = gfl.person_id AND pa.parish_id = gfl.parish_id AND pa.deleted_at IS NULL AND (pa.end_date IS NULL OR pa.end_date >= {$today}))", null, false)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return pending log entries for a specific parish scoped by date.
     *
     * @param  int    $parishId
     * @param  string $asOfDate  Y-m-d (defaults to today)
     * @return array
     */
    public function getPendingDueForParish(int $parishId, string $asOfDate = ''): array
    {
        if ($asOfDate === '') {
            $asOfDate = date('Y-m-d');
        }

        $paTable = $this->db->prefixTable('parish_affiliation');
        $today   = $this->db->escape($asOfDate);

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.template_id, fa.instructions, p.first_name, p.last_name, p.email, p.phone, p.opted_out_channels, p.preferred_timezone, p.interests')
            ->join('person p',           'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.parish_id', $parishId)
            ->where('gfl.status', 'pending')
            ->where('gfl.scheduled_date <=', $asOfDate)
            ->where('p.deleted_at IS NULL', null, false)
            ->where("EXISTS (SELECT 1 FROM {$paTable} pa WHERE pa.person_id = gfl.person_id AND pa.parish_id = gfl.parish_id AND pa.deleted_at IS NULL AND (pa.end_date IS NULL OR pa.end_date >= {$today}))", null, false)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return pending entries assigned to a specific user (human tasks).
     */
    public function getPendingForAssignee(int $userId, string $asOfDate = ''): array
    {
        if ($asOfDate === '') {
            $asOfDate = date('Y-m-d');
        }

        $paTable = $this->db->prefixTable('parish_affiliation');
        $today   = $this->db->escape($asOfDate);

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.instructions, p.first_name, p.last_name, p.phone')
            ->join('person p',           'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.assigned_to', $userId)
            ->where('gfl.status', 'pending')
            ->where('gfl.scheduled_date <=', $asOfDate)
            ->where('p.deleted_at IS NULL', null, false)
            ->where("EXISTS (SELECT 1 FROM {$paTable} pa WHERE pa.person_id = gfl.person_id AND pa.parish_id = gfl.parish_id AND pa.deleted_at IS NULL AND (pa.end_date IS NULL OR pa.end_date >= {$today}))", null, false)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    // -----------------------------------------------------------------------
    // Status transitions
    // -----------------------------------------------------------------------

    /**
     * Count pending followup log entries for a person.
     */
    public function countPendingForPerson(int $personId): int
    {
        return (int) $this->where('person_id', $personId)
            ->where('status', 'pending')
            ->countAllResults();
    }

    // -----------------------------------------------------------------------

    /**
     * Mark a log entry as executed (status = sent).
     *
     * @param  int   $logId
     * @param  array $responseMetadata  Optional channel receipt data (Twilio SID, etc.)
     * @return bool
     */
    public function markExecuted(int $logId, array $responseMetadata = []): bool
    {
        $data = [
            'status'      => 'sent',
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        if ($responseMetadata !== []) {
            $data['response_metadata'] = json_encode($responseMetadata);
        }

        return $this->update($logId, $data);
    }

    /**
     * Mark a log entry as skipped (e.g. channel opt-out).
     */
    public function markSkipped(int $logId, array $responseMetadata = []): bool
    {
        $data = [
            'status'      => 'skipped',
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        if ($responseMetadata !== []) {
            $data['response_metadata'] = json_encode($responseMetadata);
        }

        return $this->update($logId, $data);
    }

    /**
     * Mark a log entry as failed.
     */
    public function markFailed(int $logId, array $responseMetadata = []): bool
    {
        $data = ['status' => 'failed'];

        if ($responseMetadata !== []) {
            $data['response_metadata'] = json_encode($responseMetadata);
        }

        return $this->update($logId, $data);
    }

    // -----------------------------------------------------------------------
    // Per-visitor timeline
    // -----------------------------------------------------------------------

    /**
     * Return the full follow-up history for a person, newest scheduled first.
     */
    public function getTimelineForPerson(int $personId): array
    {
        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.week_number, fa.day_offset, ft.name AS template_name')
            ->join('followup_action fa',   'fa.id = gfl.action_id',   'left')
            ->join('followup_template ft', 'ft.id = fa.template_id',  'left')
            ->where('gfl.person_id', $personId)
            ->orderBy('gfl.scheduled_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    // -----------------------------------------------------------------------
    // Bulk-schedule helper
    // -----------------------------------------------------------------------

    /**
     * Create log entries for every action in a sequence for a given person.
     *
     * @param  int    $personId
     * @param  int    $parishId          Parish scope stored on each log row
     * @param  int    $sequenceId
     * @param  string $visitDate        Y-m-d  — base date for computing scheduled_date
     * @param  string $visitorTimezone    IANA timezone string (e.g. "America/New_York").
     *                                  When provided, scheduled_date is converted so the
     *                                  action fires at midnight in the visitor's local zone.
     * @return int    Number of rows inserted
     */
    public function scheduleSequenceForPerson(int $personId, int $parishId, int $sequenceId, string $visitDate, string $visitorTimezone = ''): int
    {
        $actions = $this->db->table('followup_action')
            ->where('sequence_id', $sequenceId)
            ->orderBy('day_offset', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($actions)) {
            return 0;
        }

        $tz = $this->resolveTimezone($visitorTimezone);
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('followup_log')
            ->select('action_id')
            ->where('person_id', $personId)
            ->where('action_id !=', null)
            ->get()
            ->getResultArray();
        $existingActionIds = array_flip(array_map('intval', array_column($existing, 'action_id')));

        $rows = [];
        foreach ($actions as $action) {
            $actionId = (int) $action['id'];
            if (isset($existingActionIds[$actionId])) {
                continue;
            }
            $rows[] = [
                'person_id'      => $personId,
                'parish_id'      => $parishId,
                'action_id'      => $actionId,
                'assigned_to'    => (int) ($action['assigned_to'] ?? 0) ?: null,
                'scheduled_date' => $this->computeScheduledDate($visitDate, (int) $action['day_offset'], $tz),
                'status'         => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        $inserted = $this->db->table('followup_log')->insertBatch($rows);
        if ($inserted === false) {
            return 0;
        }

        $this->materializeHumanTasks($personId, $parishId);

        return count($rows);
    }

    // -----------------------------------------------------------------------
    // First-visit auto-scheduling
    // -----------------------------------------------------------------------

    /**
     * Schedule active follow-up sequences for a person visit.
     *
     * @param int    $personId      Person whose log to populate
     * @param int    $parishId      Parish used to scope active sequences
     * @param string $visitDate     Y-m-d base date
     * @param string $visitorTimezone IANA timezone string (optional)
     * @return int Number of new log rows inserted
     */
    public function scheduleActiveSequencesForPerson(int $personId, int $parishId, string $visitDate, string $visitorTimezone = ''): int
    {
        $sequence = model('FollowupSequenceModel')->findDefaultForParish($parishId);

        if (!$sequence) {
            return 0;
        }

        return $this->scheduleSequenceForPerson(
            $personId,
            $parishId,
            (int) $sequence['id'],
            $visitDate,
            $visitorTimezone
        );
    }

    /**
     * Materialize call_task rows for the person's pending call/manual-channel
     * log entries that lack one — the human work queue tracks the obligation
     * from schedule time, so pending and overdue calls are visible even
     * before the followup:send cron dispatches them.
     */
    private function materializeHumanTasks(int $personId, int $parishId): void
    {
        $ids = $this->db->table('followup_log gfl')
            ->select('gfl.id')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'inner')
            ->join('call_task ct', 'ct.log_id = gfl.id', 'left')
            ->where('gfl.person_id', $personId)
            ->where('gfl.parish_id', $parishId)
            ->where('gfl.status', 'pending')
            ->whereIn('fa.channel', ['call', 'manual'])
            ->where('ct.id IS NULL', null, false)
            ->get()
            ->getResultArray();

        if ($ids === []) {
            return;
        }

        $sync = new \App\Services\CallTaskSyncService($this->db);
        foreach ($ids as $row) {
            $sync->materializeForLog((int) $row['id']);
        }
    }

    /**
     * Parse an IANA timezone string; returns null for empty/invalid input.
     */
    private function resolveTimezone(string $visitorTimezone): ?\DateTimeZone
    {
        if ($visitorTimezone === '') {
            return null;
        }

        try {
            return new \DateTimeZone($visitorTimezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * scheduled_date = visitDate + dayOffset, converted to UTC when the
     * visitor's timezone is known so the cron comparison stays zone-safe.
     */
    private function computeScheduledDate(string $visitDate, int $dayOffset, ?\DateTimeZone $tz): string
    {
        if ($tz !== null) {
            $localDate = new \DateTime($visitDate . ' 00:00:00', $tz);
            $localDate->modify('+' . $dayOffset . ' days');
            $localDate->setTimezone(new \DateTimeZone('UTC'));

            return $localDate->format('Y-m-d');
        }

        return date('Y-m-d', strtotime('+' . $dayOffset . ' days', strtotime($visitDate)));
    }
}
