<?php

namespace App\Models;

use CodeIgniter\Model;

class VolunteerScheduleModel extends Model
{
    protected $table            = 'volunteer_schedule';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'team_id',
        'service_id',
        'user_id',
        'service_date',
        'service_time_slot',
        'status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'team_id'           => 'required|is_not_unique[team.id]',
        'user_id'           => 'required|is_not_unique[users.id]',
        'service_date'      => 'required|valid_date',
        'status'            => 'required|in_list[scheduled,confirmed,cancelled]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get user's schedule
     */
    public function getUserSchedule($userId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('volunteer_schedule vs')
            ->select('vs.*, vt.name as team_name, s.liturgical_day, s.service')
            ->join('team vt', 'vt.id = vs.team_id')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->where('vs.user_id', $userId);

        if ($startDate) {
            $builder->where('vs.service_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('vs.service_date <=', $endDate);
        }

        return $builder->orderBy('vs.service_date', 'ASC')->get()->getResultArray();
    }

    /**
     * Get team schedule
     */
    public function getTeamSchedule($teamId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('volunteer_schedule vs')
            ->select('vs.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, s.liturgical_day, s.service')
            ->join('profile p', 'p.user_id = vs.user_id')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->where('vs.team_id', $teamId);

        if ($startDate) {
            $builder->where('vs.service_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('vs.service_date <=', $endDate);
        }

        return $builder->orderBy('vs.service_date', 'ASC')->get()->getResultArray();
    }

    /**
     * Backward-compatible method used by controller.
     */
    public function getTeamSchedules($teamId, $limit = 10)
    {
        return $this->db->table('volunteer_schedule vs')
            ->select('vs.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, s.liturgical_day, s.service, vt.name as team_name')
            ->join('profile p', 'p.user_id = vs.user_id', 'left')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->join('team vt', 'vt.id = vs.team_id')
            ->where('vs.team_id', $teamId)
            ->orderBy('vs.service_date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Backward-compatible method used by controller.
     */
    public function getParishSchedules($parishId, $limit = 20)
    {
        return $this->db->table('volunteer_schedule vs')
            ->select('vs.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, s.liturgical_day, s.service, vt.name as team_name')
            ->join('team vt', 'vt.id = vs.team_id')
            ->join('profile p', 'p.user_id = vs.user_id', 'left')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->where('vt.parish_id', $parishId)
            ->orderBy('vs.service_date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Paginated schedule list scoped to parish with optional filters.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getParishSchedulesPaginated(int $parishId, int $perPage = 20, int $page = 1, ?int $teamId = null, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $countBuilder = $this->db->table('volunteer_schedule vs')
            ->select('vs.id')
            ->join('team vt', 'vt.id = vs.team_id')
            ->where('vt.parish_id', $parishId);

        $rowBuilder = $this->db->table('volunteer_schedule vs')
            ->select('vs.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, s.liturgical_day, s.service, vt.name as team_name')
            ->join('team vt', 'vt.id = vs.team_id')
            ->join('profile p', 'p.user_id = vs.user_id', 'left')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->where('vt.parish_id', $parishId);

        if ($teamId !== null && $teamId > 0) {
            $countBuilder->where('vs.team_id', $teamId);
            $rowBuilder->where('vs.team_id', $teamId);
        }

        if ($status !== null && $status !== '') {
            $countBuilder->where('vs.status', $status);
            $rowBuilder->where('vs.status', $status);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $countBuilder->where('vs.service_date >=', $dateFrom);
            $rowBuilder->where('vs.service_date >=', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $countBuilder->where('vs.service_date <=', $dateTo);
            $rowBuilder->where('vs.service_date <=', $dateTo);
        }

        $total = (int) $countBuilder->countAllResults();

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $rows = $rowBuilder
            ->orderBy('vs.service_date', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'rows'  => $rows,
            'total' => $total,
        ];
    }

    /**
     * Fetch all schedule rows for a parish within a date range, indexed by date string.
     * Used by the weekly calendar view.
     *
     * @return array<string, array<int, array<string, mixed>>>  keyed by 'Y-m-d'
     */
    public function getParishSchedulesForWeek(int $parishId, string $dateFrom, string $dateTo, ?int $teamId = null, ?string $status = null): array
    {
        $builder = $this->db->table('volunteer_schedule vs')
            ->select('vs.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, vt.name as team_name, s.service as service_json, s.service_time as service_time')
            ->join('team vt', 'vt.id = vs.team_id')
            ->join('profile p', 'p.user_id = vs.user_id', 'left')
            ->join('service s', 's.id = vs.service_id', 'left')
            ->where('vt.parish_id', $parishId)
            ->where('vs.service_date >=', $dateFrom)
            ->where('vs.service_date <=', $dateTo);

        if ($teamId !== null && $teamId > 0) {
            $builder->where('vs.team_id', $teamId);
        }

        if ($status !== null && $status !== '') {
            $builder->where('vs.status', $status);
        }

        $rows = $builder->orderBy('vs.service_date', 'ASC')->orderBy('vs.service_time_slot', 'ASC')->get()->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) ($row['service_date'] ?? '')][] = $row;
        }

        return $indexed;
    }

    /**
     * Returns all non-cancelled schedule rows whose date equals $targetDate,
     * joined with the volunteer's profile (email via auth_identities), team name,
     * and parish name.
     *
     * Used by the `volunteers:remind` Spark command.
     *
     * @param  string   $targetDate  'Y-m-d' — the service date to match (e.g. today + N days)
     * @param  int|null $parishId    When set, limit to that parish only
     * @return array<int, array<string, mixed>>
     */
    public function getSchedulesDueForReminder(string $targetDate, ?int $parishId = null): array
    {
        $builder = $this->db->table('volunteer_schedule vs')
            ->select('vs.id, vs.service_date, vs.service_time_slot, vs.status, vs.team_id, vs.user_id,
                      vt.name AS team_name, vt.parish_id,
                      c.name AS parish_name,
                      COALESCE(p.nickname, p.first_name) AS first_name, p.last_name,
                      ai.secret AS email')
            ->join('team vt', 'vt.id = vs.team_id')
            ->join('parish c', 'c.id = vt.parish_id', 'left')
            ->join('profile p', 'p.user_id = vs.user_id', 'left')
            ->join('users u', 'u.id = vs.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('vs.service_date', $targetDate)
            ->whereNotIn('vs.status', ['cancelled'])
            ->where('u.active', 1);

        if ($parishId !== null && $parishId > 0) {
            $builder->where('vt.parish_id', $parishId);
        }

        return $builder->orderBy('vt.parish_id', 'ASC')
            ->orderBy('vs.service_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Detects a conflicting volunteer assignment for the same user/date/time slot.
     * Cancelled rows are ignored. Use $excludeId when updating an existing row.
     */
    public function hasUserSlotConflict(int $userId, string $date, ?string $timeSlot, ?int $excludeId = null): bool
    {
        $normalizedSlot = trim((string) ($timeSlot ?? ''));

        $builder = $this->where('user_id', $userId)
            ->where('service_date', $date)
            ->where('status !=', 'cancelled');

        if ($normalizedSlot === '') {
            $builder->groupStart()
                ->where('service_time_slot', null)
                ->orWhere('service_time_slot', '')
                ->groupEnd();
        } else {
            $builder->where('service_time_slot', $normalizedSlot);
        }

        if ($excludeId !== null && $excludeId > 0) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->first() !== null;
    }
}
