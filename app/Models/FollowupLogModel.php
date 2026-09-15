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
        'guest_id'       => 'required|is_not_unique[guest.id]',
        'scheduled_date' => 'required|valid_date',
        'status'         => 'required|in_list[pending,sent,failed,skipped]',
        'action_id'      => 'permit_empty|is_not_unique[followup_action.id]',
        'assigned_to'    => 'permit_empty|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    private ?string $sequenceScopeColumn = null;

    private function resolveFollowupSequenceTable(): string
    {
        if ($this->tableQueryable('followup_sequence')) {
            return 'followup_sequence';
        }

        if ($this->tableQueryable('followup_sequences')) {
            return 'followup_sequences';
        }

        return 'followup_sequence';
    }

    private function resolveFollowupActionTable(): string
    {
        if ($this->tableQueryable('followup_action')) {
            return 'followup_action';
        }

        if ($this->tableQueryable('followup_actions')) {
            return 'followup_actions';
        }

        return 'followup_action';
    }

    private function tableQueryable(string $table): bool
    {
        try {
            $result = $this->db->table($table)
                ->select('1', false)
                ->limit(1)
                ->get();

            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableColumnSelectable(string $table, string $column): bool
    {
        try {
            $result = $this->db->table($table)
                ->select($column)
                ->limit(1)
                ->get();
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveSequenceActiveColumn(string $sequenceTable): string
    {
        if ($this->tableColumnSelectable($sequenceTable, 'is_active')) {
            return 'is_active';
        }

        if ($this->tableColumnSelectable($sequenceTable, 'active')) {
            return 'active';
        }

        return 'is_active';
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if ($this->tableColumnSelectable($table, $column)) {
            return true;
        }

        try {
            return $this->db->fieldExists($column, $table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getSequenceScopeColumn(): string
    {
        if ($this->sequenceScopeColumn !== null) {
            return $this->sequenceScopeColumn;
        }

        $sequenceTable = $this->resolveFollowupSequenceTable();

        if ($this->tableColumnSelectable($sequenceTable, 'parish_id')) {
            $this->sequenceScopeColumn = 'parish_id';
            return $this->sequenceScopeColumn;
        }

        if ($this->tableColumnSelectable($sequenceTable, 'parish_id')) {
            $this->sequenceScopeColumn = 'parish_id';
            return $this->sequenceScopeColumn;
        }

        $this->sequenceScopeColumn = 'parish_id';
        return $this->sequenceScopeColumn;
    }

    private function getLogDateColumn(): string
    {
        if ($this->tableHasColumn('followup_log', 'scheduled_date')) {
            return 'scheduled_date';
        }

        if ($this->tableHasColumn('followup_log', 'followup_date')) {
            return 'followup_date';
        }

        return 'scheduled_date';
    }

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

        $dateColumn = $this->getLogDateColumn();

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.is_automated, fa.template_id, p.first_name, p.last_name, p.email, p.phone, gfl.parish_id')
            ->join('person p',          'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.status', 'pending')
            ->where('gfl.' . $dateColumn . ' <=', $asOfDate)
            ->orderBy('gfl.' . $dateColumn, 'ASC')
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

        $dateColumn = $this->getLogDateColumn();

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.is_automated, fa.template_id, p.first_name, p.last_name, p.email, p.phone')
            ->join('person p',           'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.parish_id', $parishId)
            ->where('gfl.status', 'pending')
            ->where('gfl.' . $dateColumn . ' <=', $asOfDate)
            ->orderBy('gfl.' . $dateColumn, 'ASC')
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

        $dateColumn = $this->getLogDateColumn();

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, p.first_name, p.last_name, p.phone')
            ->join('person p',           'p.id = gfl.person_id', 'left')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->where('gfl.assigned_to', $userId)
            ->where('gfl.status', 'pending')
            ->where('gfl.' . $dateColumn . ' <=', $asOfDate)
            ->orderBy('gfl.' . $dateColumn, 'ASC')
            ->get()
            ->getResultArray();
    }

    // -----------------------------------------------------------------------
    // Status transitions
    // -----------------------------------------------------------------------

    /**
     * Count pending (not yet executed/skipped) followup log entries for a guest.
     * Used by Guests::delete() to warn when in-progress sequences would be cancelled.
     */
    public function countPendingForGuest(int $guestId): int
    {
        return (int) $this->where('guest_id', $guestId)
            ->where('status', 'pending')
            ->countAllResults();
    }

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

    /**
     * Mark a log entry as skipped.
     */
    public function markSkipped(int $logId, ?string $notes = null): bool
    {
        $data = ['status' => 'skipped'];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($logId, $data);
    }

    // -----------------------------------------------------------------------
    // Per-guest timeline
    // -----------------------------------------------------------------------

    /**
     * Return the full follow-up history for a guest, newest scheduled first.
     */
    public function getTimelineForGuest(int $guestId): array
    {
        return $this->getTimelineForPerson($guestId);
    }

    /**
     * Return the full follow-up history for a person, newest scheduled first.
     */
    public function getTimelineForPerson(int $personId): array
    {
        $dateColumn = $this->getLogDateColumn();

        return $this->db->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.week_number, fa.day_offset, ft.name AS template_name')
            ->join('followup_action fa',   'fa.id = gfl.action_id',   'left')
            ->join('followup_template ft', 'ft.id = fa.template_id',  'left')
            ->where('gfl.person_id', $personId)
            ->orderBy('gfl.' . $dateColumn, 'DESC')
            ->get()
            ->getResultArray();
    }

    // -----------------------------------------------------------------------
    // Bulk-schedule helper
    // -----------------------------------------------------------------------

    /**
     * Create log entries for every action in a sequence for a given guest.
     *
     * @param  int    $guestId
     * @param  int    $sequenceId
     * @param  string $visitDate        Y-m-d  — base date for computing scheduled_date
     * @param  string $guestTimezone    IANA timezone string (e.g. "America/New_York").
     *                                  When provided, scheduled_date is converted so the
     *                                  action fires at midnight in the guest's local zone.
     * @return int    Number of rows inserted
     */
    public function scheduleSequenceForGuest(int $guestId, int $sequenceId, string $visitDate, string $guestTimezone = ''): int
    {
        $actionTable = $this->resolveFollowupActionTable();
        $dateColumn = $this->getLogDateColumn();
        $hasActionIdColumn = $this->tableHasColumn('followup_log', 'action_id');
        $hasLegacyDateColumn = $this->tableHasColumn('followup_log', 'followup_date');

        $query = $this->db->table($actionTable)
            ->where('sequence_id', $sequenceId)
            ->orderBy('day_offset', 'ASC')
            ->get();

        if ($query === false) {
            return 0;
        }

        $actions = $query->getResultArray();

        if (empty($actions)) {
            return 0;
        }

        // Resolve timezone: use guest preference, fall back to server default.
        $tz = null;
        if ($guestTimezone !== '') {
            try {
                $tz = new \DateTimeZone($guestTimezone);
            } catch (\Exception $e) {
                $tz = null;
            }
        }

        $rows = [];

        foreach ($actions as $action) {
            if ($tz !== null) {
                // Create a DateTime at midnight in the guest's local zone, then
                // convert to UTC for storage so the cron comparison is timezone-safe.
                $localDate = new \DateTime($visitDate . ' 00:00:00', $tz);
                $localDate->modify('+' . (int) $action['day_offset'] . ' days');
                $localDate->setTimezone(new \DateTimeZone('UTC'));
                $scheduled = $localDate->format('Y-m-d');
            } else {
                $baseDate  = strtotime($visitDate);
                $scheduled = date('Y-m-d', strtotime('+' . $action['day_offset'] . ' days', $baseDate));
            }

            $rows[] = [
                'guest_id'       => $guestId,
                $dateColumn      => $scheduled,
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            if ($hasLegacyDateColumn && $dateColumn !== 'followup_date') {
                $rows[count($rows) - 1]['followup_date'] = $scheduled;
            }

            if ($hasActionIdColumn) {
                $rows[count($rows) - 1]['action_id'] = $action['id'];
            }
        }

        $inserted = $this->db->table('followup_log')->insertBatch($rows);
        if ($inserted === false) {
            return 0;
        }

        return count($rows);
    }

    // -----------------------------------------------------------------------
    // First-visit auto-scheduling
    // -----------------------------------------------------------------------

    /**
     * Schedule every action from every active sequence available to a parish
     * for a given guest, using the visit date as the base date.
     *
     * - Only runs for visit_type = 'first' (caller's responsibility to gate).
     * - Skips any action already present in guest_followup_log for this guest
     *   (dedup by guest_id + action_id), preventing double-scheduling if the
     *   method is called more than once.
     * - Also de-dupes within the current batch when the same action appears in
     *   multiple sequences.
     *
     * @param  int    $guestId        Guest whose log to populate
     * @param  int    $parishId       Parish used to scope active sequences
     * @param  string $visitDate      Y-m-d  — base date for computing scheduled_date
     * @param  string $guestTimezone  IANA timezone string (optional). When provided,
     *                                scheduled_date is adjusted to midnight in the
     *                                guest's local zone and stored as UTC.
     * @return int                    Number of new log rows inserted (0 when all deduped)
     */
    public function scheduleActiveSequencesForGuest(int $guestId, int $parishId, string $visitDate, string $guestTimezone = ''): int
    {
        $scopeColumn = $this->getSequenceScopeColumn();
        $sequenceTable = $this->resolveFollowupSequenceTable();
        $actionTable = $this->resolveFollowupActionTable();
        $activeColumn = $this->resolveSequenceActiveColumn($sequenceTable);
        $dateColumn = $this->getLogDateColumn();
        $hasActionIdColumn = $this->tableHasColumn('followup_log', 'action_id');
        $hasLegacyDateColumn = $this->tableHasColumn('followup_log', 'followup_date');
        $hasParishColumn = $this->tableHasColumn('followup_log', 'parish_id');

        // 1. Fetch all active sequences scoped to this parish (+ global ones)
        $sequencesQuery = $this->db->table($sequenceTable)
            ->groupStart()
            ->where($scopeColumn, $parishId)
            ->orWhere($scopeColumn . ' IS NULL', null, false)
            ->groupEnd()
            ->where($activeColumn, 1)
            ->get();

        if ($sequencesQuery === false) {
            return 0;
        }

        $sequences = $sequencesQuery->getResultArray();

        if (empty($sequences)) {
            return 0;
        }

        // 2. Load already-scheduled action IDs for this guest (dedup guard)
        $existingRows = [];
        if ($hasActionIdColumn) {
            $existingQuery = $this->db->table('followup_log')
                ->select('action_id')
                ->where('guest_id', $guestId)
                ->where('action_id !=', null)
                ->get();

            if ($existingQuery !== false) {
                $existingRows = $existingQuery->getResultArray();
            }
        }

        // Build a set of already-scheduled action IDs (int keys for fast lookup)
        $scheduledActionIds = array_flip(array_map('intval', array_column($existingRows, 'action_id')));

        // Resolve timezone object once for reuse across all actions.
        $tz = null;
        if ($guestTimezone !== '') {
            try {
                $tz = new \DateTimeZone($guestTimezone);
            } catch (\Exception $e) {
                $tz = null;
            }
        }

        $baseDate = strtotime($visitDate);
        $now      = date('Y-m-d H:i:s');
        $toInsert = [];

        // 3. Collect rows to insert, skipping any already scheduled
        foreach ($sequences as $sequence) {
            $actionQuery = $this->db->table($actionTable)
                ->where('sequence_id', (int) $sequence['id'])
                ->orderBy('day_offset', 'ASC')
                ->get();

            if ($actionQuery === false) {
                continue;
            }

            $actions = $actionQuery->getResultArray();

            foreach ($actions as $action) {
                $actionId = (int) $action['id'];

                // Skip if already in DB or already added to the current batch
                if (isset($scheduledActionIds[$actionId])) {
                    continue;
                }

                if ($tz !== null) {
                    $localDate = new \DateTime($visitDate . ' 00:00:00', $tz);
                    $localDate->modify('+' . (int) $action['day_offset'] . ' days');
                    $localDate->setTimezone(new \DateTimeZone('UTC'));
                    $scheduled = $localDate->format('Y-m-d');
                } else {
                    $scheduled = date('Y-m-d', strtotime('+' . (int) $action['day_offset'] . ' days', $baseDate));
                }

                $toInsert[] = [
                    'guest_id'       => $guestId,
                    $dateColumn      => $scheduled,
                    'status'         => 'pending',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if ($hasParishColumn) {
                    $toInsert[count($toInsert) - 1]['parish_id'] = $parishId;
                }

                if ($hasLegacyDateColumn && $dateColumn !== 'followup_date') {
                    $toInsert[count($toInsert) - 1]['followup_date'] = $scheduled;
                }

                if ($hasActionIdColumn) {
                    $toInsert[count($toInsert) - 1]['action_id'] = $actionId;
                }

                // Track within batch to prevent same action from two sequences
                $scheduledActionIds[$actionId] = true;
            }
        }

        if (empty($toInsert)) {
            return 0;
        }

        $inserted = $this->db->table('followup_log')->insertBatch($toInsert);
        if ($inserted === false) {
            return 0;
        }

        return count($toInsert);
    }

    /**
     * Schedule active follow-up sequences for a person visit.
     *
     * @param int    $personId      Person whose log to populate
     * @param int    $parishId      Parish used to scope active sequences
     * @param string $visitDate     Y-m-d base date
     * @param string $guestTimezone IANA timezone string (optional)
     * @return int Number of new log rows inserted
     */
    public function scheduleActiveSequencesForPerson(int $personId, int $parishId, string $visitDate, string $guestTimezone = ''): int
    {
        $scopeColumn = $this->getSequenceScopeColumn();
        $sequenceTable = $this->resolveFollowupSequenceTable();
        $actionTable = $this->resolveFollowupActionTable();
        $activeColumn = $this->resolveSequenceActiveColumn($sequenceTable);
        $dateColumn = $this->getLogDateColumn();
        $hasActionIdColumn = $this->tableHasColumn('followup_log', 'action_id');
        $hasLegacyDateColumn = $this->tableHasColumn('followup_log', 'followup_date');
        $hasParishColumn = $this->tableHasColumn('followup_log', 'parish_id');

        $sequencesQuery = $this->db->table($sequenceTable)
            ->groupStart()
            ->where($scopeColumn, $parishId)
            ->orWhere($scopeColumn . ' IS NULL', null, false)
            ->groupEnd()
            ->where($activeColumn, 1)
            ->get();

        if ($sequencesQuery === false) {
            return 0;
        }

        $sequences = $sequencesQuery->getResultArray();

        if (empty($sequences)) {
            return 0;
        }

        $existingRows = [];
        if ($hasActionIdColumn) {
            $existingQuery = $this->db->table('followup_log')
                ->select('action_id')
                ->where('person_id', $personId)
                ->where('action_id !=', null)
                ->get();

            if ($existingQuery !== false) {
                $existingRows = $existingQuery->getResultArray();
            }
        }

        $scheduledActionIds = array_flip(array_map('intval', array_column($existingRows, 'action_id')));

        $tz = null;
        if ($guestTimezone !== '') {
            try {
                $tz = new \DateTimeZone($guestTimezone);
            } catch (\Exception $e) {
                $tz = null;
            }
        }

        $baseDate = strtotime($visitDate);
        $now      = date('Y-m-d H:i:s');
        $toInsert = [];

        foreach ($sequences as $sequence) {
            $actionQuery = $this->db->table($actionTable)
                ->where('sequence_id', (int) $sequence['id'])
                ->orderBy('day_offset', 'ASC')
                ->get();

            if ($actionQuery === false) {
                continue;
            }

            $actions = $actionQuery->getResultArray();

            foreach ($actions as $action) {
                $actionId = (int) $action['id'];

                if (isset($scheduledActionIds[$actionId])) {
                    continue;
                }

                if ($tz !== null) {
                    $localDate = new \DateTime($visitDate . ' 00:00:00', $tz);
                    $localDate->modify('+' . (int) $action['day_offset'] . ' days');
                    $localDate->setTimezone(new \DateTimeZone('UTC'));
                    $scheduled = $localDate->format('Y-m-d');
                } else {
                    $scheduled = date('Y-m-d', strtotime('+' . (int) $action['day_offset'] . ' days', $baseDate));
                }

                $toInsert[] = [
                    'person_id'      => $personId,
                    $dateColumn      => $scheduled,
                    'status'         => 'pending',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if ($hasParishColumn) {
                    $toInsert[count($toInsert) - 1]['parish_id'] = $parishId;
                }

                if ($hasLegacyDateColumn && $dateColumn !== 'followup_date') {
                    $toInsert[count($toInsert) - 1]['followup_date'] = $scheduled;
                }

                if ($hasActionIdColumn) {
                    $toInsert[count($toInsert) - 1]['action_id'] = $actionId;
                }

                $scheduledActionIds[$actionId] = true;
            }
        }

        if (empty($toInsert)) {
            return 0;
        }

        $inserted = $this->db->table('followup_log')->insertBatch($toInsert);
        if ($inserted === false) {
            return 0;
        }

        return count($toInsert);
    }

}
