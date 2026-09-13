<?php

namespace App\Models;

use CodeIgniter\Model;

class MemberStandingSummaryModel extends Model
{
    protected $table            = 'member_standing_summary';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'year',
        'is_active_member',
        'attendance_count',
        'communion_count',
        'has_pledged',
        'is_under_discipline',
        'is_good_standing',
        'last_updated',
    ];

    protected $validationRules = [
        'user_id' => 'required|is_not_unique[users.id]',
        'year' => 'required|integer|greater_than_equal_to[2000]',
    ];

    public function refreshForYear(int $year): int
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        $lastUpdated = date('Y-m-d H:i:s');
        $serviceDateColumn = $this->resolveServiceDateColumn();
        $useTrans = strtolower((string) ($this->db->DBDriver ?? '')) !== 'sqlite3';

        if ($useTrans) {
            $this->db->transStart();
        }

        $this->builder()->where('year', $year)->delete();

        $usersBuilder = $this->db->table('users u')
            ->select('u.id, u.active, COALESCE(p.is_confirmed, 0) AS is_confirmed', false)
            ->join('profile p', 'p.user_id = u.id', 'left');
        if ($this->columnExists('users', 'deleted_at')) {
            $usersBuilder->select('u.deleted_at')->where('u.deleted_at IS NULL', null, false);
        }
        $usersResult = $usersBuilder->get();
        if ($usersResult === false) {
            throw new \RuntimeException('Failed querying users for standing refresh.');
        }
        $users = $usersResult->getResultArray();

        $attendanceBuilder = $this->db->table('attendance a')
            ->select('a.user_id, SUM(CASE WHEN a.attended = 1 THEN 1 ELSE 0 END) AS attendance_count, SUM(CASE WHEN a.attended = 1 AND a.communicant = 1 THEN 1 ELSE 0 END) AS communion_count', false)
            ->join('service s', 's.id = a.service_id', 'inner');
        if ($serviceDateColumn !== null) {
            $attendanceBuilder
                ->where('s.' . $serviceDateColumn . ' >=', $startDate)
                ->where('s.' . $serviceDateColumn . ' <=', $endDate);
        }
        $attendanceBuilder->groupBy('a.user_id');
        $attendanceResult = $attendanceBuilder->get();
        if ($attendanceResult === false) {
            throw new \RuntimeException('Failed querying attendance/service standing aggregates.');
        }
        $attendanceRows = $attendanceResult->getResultArray();

        $attendanceByUser = [];
        foreach ($attendanceRows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $attendanceByUser[$uid] = [
                'attendance_count' => (int) ($row['attendance_count'] ?? 0),
                'communion_count' => (int) ($row['communion_count'] ?? 0),
            ];
        }

        $pledgeBuilder = $this->db->table('annual_pledge')
            ->distinct()
            ->select('user_id')
            ->where('pledge_year', $year)
            ->groupStart()
            ->where('fulfillment_status !=', 'cancelled')
            ->orWhere('fulfillment_status IS NULL', null, false)
            ->groupEnd();
        $pledgeResult = $pledgeBuilder->get();
        if ($pledgeResult === false) {
            throw new \RuntimeException('Failed querying annual pledges for standing refresh.');
        }
        $pledgeRows = $pledgeResult->getResultArray();

        $hasPledgedByUser = [];
        foreach ($pledgeRows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $hasPledgedByUser[$uid] = true;
            }
        }

        $disciplineBuilder = $this->db->table('member_discipline')
            ->distinct()
            ->select('user_id')
            ->where('start_date <=', $endDate)
            ->groupStart()
            ->where('end_date IS NULL', null, false)
            ->orWhere('end_date >=', $startDate)
            ->groupEnd();
        $disciplineResult = $disciplineBuilder->get();
        if ($disciplineResult === false) {
            throw new \RuntimeException('Failed querying member discipline for standing refresh.');
        }
        $disciplineRows = $disciplineResult->getResultArray();

        $isUnderDisciplineByUser = [];
        foreach ($disciplineRows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $isUnderDisciplineByUser[$uid] = true;
            }
        }

        $payload = [];
        foreach ($users as $user) {
            $uid = (int) ($user['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $isActiveMember = (int) ($user['active'] ?? 0) === 1;
            $isConfirmed = (int) ($user['is_confirmed'] ?? 0) === 1;
            $attendanceCount = (int) ($attendanceByUser[$uid]['attendance_count'] ?? 0);
            $communionCount = (int) ($attendanceByUser[$uid]['communion_count'] ?? 0);
            $hasPledged = isset($hasPledgedByUser[$uid]);
            $isUnderDiscipline = isset($isUnderDisciplineByUser[$uid]);

            $rowPayload = [
                'user_id' => $uid,
                'year' => $year,
                'is_active_member' => $isActiveMember ? 1 : 0,
                'attendance_count' => $attendanceCount,
                'communion_count' => $communionCount,
                'has_pledged' => $hasPledged ? 1 : 0,
                'is_under_discipline' => $isUnderDiscipline ? 1 : 0,
                'is_good_standing' => ($isActiveMember && $isConfirmed && $communionCount >= 3 && $hasPledged && ! $isUnderDiscipline) ? 1 : 0,
                'last_updated' => $lastUpdated,
            ];
            $rowPayload = $this->filterSummaryPayload($rowPayload);
            if ($rowPayload !== []) {
                $payload[] = $rowPayload;
            }
        }

        if ($payload !== []) {
            $this->builder()->insertBatch($payload);
        }

        $inserted = count($payload);

        if ($useTrans) {
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                $fallbackRow = $this->builder()
                    ->select('COUNT(*) AS c', false)
                    ->where('year', $year)
                    ->get()
                    ->getRowArray();
                $fallbackCount = (int) ($fallbackRow['c'] ?? 0);
                if ($fallbackCount <= 0) {
                    throw new \RuntimeException('Failed to refresh member standing summary for year ' . $year);
                }

                return $fallbackCount;
            }
        }

        return $inserted;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $columns = $this->db->getFieldNames($table);
            return in_array($column, $columns, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveServiceDateColumn(): ?string
    {
        if ($this->columnExists('service', 'service_date')) {
            return 'service_date';
        }

        if ($this->columnExists('service', 'date')) {
            return 'date';
        }

        return null;
    }

    public function updateSingleUserStanding(int $userId, ?int $year = null): bool
    {
        $year ??= (int) date('Y');
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        $serviceDateColumn = $this->resolveServiceDateColumn();
        
        $hasAttendanceTable = $this->tableExistsSafe('attendance');
        $hasServiceTable = $this->tableExistsSafe('service');
        $hasPledgeTable = $this->tableExistsSafe('annual_pledge');
        $hasDisciplineTable = $this->tableExistsSafe('member_discipline');

        $singleUserBuilder = $this->db->table('users u')
            ->select('u.id, u.active, COALESCE(p.is_confirmed, 0) AS is_confirmed', false)
            ->join('profile p', 'p.user_id = u.id', 'left')
            ->where('u.id', $userId);
        if ($this->columnExists('users', 'deleted_at')) {
            $singleUserBuilder->select('u.deleted_at');
        }
        $userRow = $singleUserBuilder
            ->get()
            ->getRowArray();

        if ($userRow === null || ((array_key_exists('deleted_at', $userRow) && $userRow['deleted_at'] !== null))) {
            $this->builder()->where('user_id', $userId)->where('year', $year)->delete();
            return false;
        }

        $attendanceCount = 0;
        $communionCount = 0;
        if ($hasAttendanceTable && $hasServiceTable) {
            $attendanceRow = $this->db->table('attendance a')
                ->select('SUM(CASE WHEN a.attended = 1 THEN 1 ELSE 0 END) AS attendance_count, SUM(CASE WHEN a.attended = 1 AND a.communicant = 1 THEN 1 ELSE 0 END) AS communion_count', false)
                ->join('service s', 's.id = a.service_id', 'inner')
                ->where('a.user_id', $userId);

            if ($serviceDateColumn !== null) {
                $attendanceRow = $attendanceRow
                    ->where('s.' . $serviceDateColumn . ' >=', $startDate)
                    ->where('s.' . $serviceDateColumn . ' <=', $endDate)
                    ->get()
                    ->getRowArray();
            } else {
                $attendanceRow = $attendanceRow->get()->getRowArray();
            }

            $attendanceCount = (int) ($attendanceRow['attendance_count'] ?? 0);
            $communionCount = (int) ($attendanceRow['communion_count'] ?? 0);
        }

        $hasPledged = false;
        if ($hasPledgeTable) {
            $hasPledged = $this->db->table('annual_pledge')
                ->where('user_id', $userId)
                ->where('pledge_year', $year)
                ->groupStart()
                ->where('fulfillment_status !=', 'cancelled')
                ->orWhere('fulfillment_status IS NULL', null, false)
                ->groupEnd()
                ->countAllResults() > 0;
        }

        $isUnderDiscipline = false;
        if ($hasDisciplineTable) {
            $isUnderDiscipline = $this->db->table('member_discipline')
                ->where('user_id', $userId)
                ->where('start_date <=', $endDate)
                ->groupStart()
                ->where('end_date IS NULL', null, false)
                ->orWhere('end_date >=', $startDate)
                ->groupEnd()
                ->countAllResults() > 0;
        }

        $isActiveMember = (int) ($userRow['active'] ?? 0) === 1;
        $isConfirmed = (int) ($userRow['is_confirmed'] ?? 0) === 1;
        $isGoodStanding = $isActiveMember
            && $isConfirmed
            && $communionCount >= 3
            && $hasPledged
            && ! $isUnderDiscipline;

        $lastUpdated = date('Y-m-d H:i:s');

        $this->builder()->where('user_id', $userId)->where('year', $year)->delete();

        $singlePayload = $this->filterSummaryPayload([
            'user_id' => $userId,
            'year' => $year,
            'is_active_member' => $isActiveMember ? 1 : 0,
            'attendance_count' => $attendanceCount,
            'communion_count' => $communionCount,
            'has_pledged' => $hasPledged ? 1 : 0,
            'is_under_discipline' => $isUnderDiscipline ? 1 : 0,
            'is_good_standing' => $isGoodStanding ? 1 : 0,
            'last_updated' => $lastUpdated,
        ]);

        $this->builder()->insert($singlePayload);

        return true;
    }

    private function tableExistsSafe(string $table): bool
    {
        try {
            return $this->db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterSummaryPayload(array $payload): array
    {
        $table = $this->db->getPrefix() !== '' ? $this->db->getPrefix() . $this->table : $this->table;
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($table));
        } catch (\Throwable $e) {
            return $payload;
        }

        if ($columns === []) {
            return $payload;
        }

        foreach (array_keys($payload) as $key) {
            if (! in_array(strtolower((string) $key), $columns, true)) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    public function getCurrentStandingCounts(?int $year = null): array
    {
        $year ??= (int) date('Y');

        $row = $this->builder()
            ->select('COUNT(*) AS total_members, SUM(CASE WHEN is_good_standing = 1 THEN 1 ELSE 0 END) AS good_standing_count, SUM(CASE WHEN is_good_standing = 0 THEN 1 ELSE 0 END) AS not_good_standing_count', false)
            ->where('year', $year)
            ->get()
            ->getRowArray();

        return [
            'year' => $year,
            'total_members' => (int) ($row['total_members'] ?? 0),
            'good_standing_count' => (int) ($row['good_standing_count'] ?? 0),
            'not_good_standing_count' => (int) ($row['not_good_standing_count'] ?? 0),
        ];
    }
}
