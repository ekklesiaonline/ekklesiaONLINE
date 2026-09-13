<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    protected $table            = 'attendance';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'service_id',
        'user_id',
        'attended',
        'communicant',
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';

    // Validation
    protected $validationRules      = [
        'service_id' => 'required|is_not_unique[service.id]',
        'user_id'    => 'required|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Record attendance using update-or-insert semantics for (service_id, user_id).
     *
     * @return array{success: bool, action: string, id: int|null}
     */
    public function recordAttendance(int $serviceId, int $userId, bool $attended = true, bool $communicant = false): array
    {
        // Communicant requires attendance.
        if (!$attended) {
            $communicant = false;
        }

        $existing = $this->where('service_id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        $data = [
            'service_id' => $serviceId,
            'user_id'    => $userId,
            'attended'   => $attended ? 1 : 0,
            'communicant' => $communicant ? 1 : 0,
        ];

        if ($existing) {
            $success = (bool) $this->update((int) $existing['id'], $data);

            return [
                'success' => $success,
                'action' => 'updated',
                'id' => (int) $existing['id'],
            ];
        }

        $insertId = $this->insert($data);

        return [
            'success' => $insertId !== false,
            'action' => 'inserted',
            'id' => $insertId !== false ? (int) $insertId : null,
        ];
    }

    /**
     * Backward-compatible alias used by existing modules.
     */
    public function markAttendance($serviceId, $userId, $attended = true, $communicant = false)
    {
        $result = $this->recordAttendance((int) $serviceId, (int) $userId, (bool) $attended, (bool) $communicant);

        return $result['success'];
    }

    /**
     * Get recent attendance entries for a service, including member details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentForService(int $serviceId, int $limit = 20): array
    {
        $limit = max(1, $limit);

        return $this->db->table('attendance a')
            ->select('a.id, a.service_id, a.user_id, a.attended, a.communicant, a.created_at')
            ->select('u.username')
            ->select('p.first_name, p.last_name')
            ->select('ai.secret as email')
            ->join('users u', 'u.id = a.user_id', 'inner')
            ->join('profile p', 'p.user_id = a.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = a.user_id AND ai.type = "email_password"', 'left')
            ->where('a.service_id', $serviceId)
            ->orderBy('a.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Synchronize attendance totals into service_metric for a service.
     */
    public function syncServiceMetrics(int $serviceId): bool
    {
        if (!$this->db->tableExists('service_metric')) {
            return true;
        }

        $counts = $this->db->table('attendance')
            ->select('COUNT(CASE WHEN attended = 1 THEN 1 END) AS present_count', false)
            ->select('COUNT(CASE WHEN attended = 1 AND communicant = 1 THEN 1 END) AS communion_count', false)
            ->where('service_id', $serviceId)
            ->get()
            ->getRowArray();

        $presentCount = (int) ($counts['present_count'] ?? 0);
        $communionCount = (int) ($counts['communion_count'] ?? 0);

        $existing = $this->db->table('service_metric')
            ->select('service_id')
            ->where('service_id', $serviceId)
            ->get()
            ->getRowArray();

        if ($existing) {
            return (bool) $this->db->table('service_metric')
                ->where('service_id', $serviceId)
                ->update([
                    'reported_present_count' => $presentCount,
                    'reported_communion_count' => $communionCount,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return (bool) $this->db->table('service_metric')->insert([
            'service_id' => $serviceId,
            'reported_present_count' => $presentCount,
            'reported_communion_count' => $communionCount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get service attendance with member details
     */
    public function getServiceAttendance($serviceId)
    {
        return $this->db->table('attendance a')
            ->select('a.*, p.first_name, p.last_name, ai.secret as email')
            ->join('profile p', 'p.user_id = a.user_id')
            ->join('users u', 'u.id = a.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('a.service_id', $serviceId)
            ->where('a.attended', 1)
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get member's attendance history
     */
    public function getMemberAttendance($userId, $limit = 10)
    {
        return $this->db->table('attendance a')
            ->select('a.*, s.service_date, s.service, s.liturgical_day')
            ->join('service s', 's.id = a.service_id')
            ->where('a.user_id', $userId)
            ->where('a.attended', 1)
            ->orderBy('s.service_date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Get attendance summary for member
     */
    public function getMemberAttendanceSummary($userId, $parishId, $startDate, $endDate)
    {
        return $this->db->table('attendance a')
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as attended_count, COUNT(s.id) as total_services')
            ->join('service s', 's.id = a.service_id')
            ->where('a.user_id', $userId)
            ->where('s.parish_id', $parishId)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->get()
            ->getRowArray();
    }

    public function getCommunionCountForMemberYear(int $userId, int $parishId, int $year): int
    {
        $startDate = sprintf('%d-01-01', $year);
        $endDate = sprintf('%d-12-31', $year);

        $row = $this->db->table('attendance a')
            ->select('COUNT(*) as total_communions')
            ->join('service s', 's.id = a.service_id')
            ->where('a.user_id', $userId)
            ->where('a.attended', 1)
            ->where('a.communicant', 1)
            ->where('s.parish_id', $parishId)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->get()
            ->getRowArray();

        return (int) ($row['total_communions'] ?? 0);
    }

    /**
     * Get attendance totals for reports page.
     */
    public function getAttendanceSummary($parishId, $startDate, $endDate)
    {
        $row = $this->db->table('attendance a')
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance, COUNT(DISTINCT s.id) as total_services, COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants')
            ->join('service s', 's.id = a.service_id')
            ->where('s.parish_id', $parishId)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->get()
            ->getRowArray();

        return [
            'total_attendance' => (int) ($row['total_attendance'] ?? 0),
            'total_services' => (int) ($row['total_services'] ?? 0),
            'total_communicants' => (int) ($row['total_communicants'] ?? 0),
        ];
    }

    /**
     * Get attendance averages across all parishes for Province Admin
     * Supports: today, week, month, year, sundays, weekdays, custom range
     */
    public function getAllParishesAttendanceAverage($period = 'month', $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('attendance a')
            ->select('
                COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance,
                COUNT(DISTINCT s.id) as total_services,
                COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants,
                COUNT(DISTINCT s.parish_id) as total_parishes
            ')
            ->join('service s', 's.id = a.service_id');

        // Apply date filters based on period
        switch ($period) {
            case 'today':
                $builder->where('s.service_date', date('Y-m-d'));
                break;
            case 'week':
                $builder->where('s.service_date >=', date('Y-m-d', strtotime('monday this week')));
                $builder->where('s.service_date <=', date('Y-m-d', strtotime('sunday this week')));
                break;
            case 'month':
                $builder->where('s.service_date >=', date('Y-m-01'));
                $builder->where('s.service_date <=', date('Y-m-t'));
                break;
            case 'year':
                $builder->where('s.service_date >=', date('Y-01-01'));
                $builder->where('s.service_date <=', date('Y-12-31'));
                break;
            case 'sundays':
                $builder->where('DAYOFWEEK(s.service_date)', 1); // Sunday
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                } else {
                    // Default to current year
                    $builder->where('s.service_date >=', date('Y-01-01'));
                    $builder->where('s.service_date <=', date('Y-12-31'));
                }
                break;
            case 'weekdays':
                $builder->whereIn('DAYOFWEEK(s.service_date)', [2, 3, 4, 5, 6, 7]); // Monday-Saturday
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                } else {
                    // Default to current year
                    $builder->where('s.service_date >=', date('Y-01-01'));
                    $builder->where('s.service_date <=', date('Y-12-31'));
                }
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                }
                break;
        }

        $row = $builder->get()->getRowArray();

        $totalServices = (int) ($row['total_services'] ?? 0);
        $totalAttendance = (int) ($row['total_attendance'] ?? 0);

        return [
            'total_attendance' => $totalAttendance,
            'total_services' => $totalServices,
            'total_communicants' => (int) ($row['total_communicants'] ?? 0),
            'total_parishes' => (int) ($row['total_parishes'] ?? 0),
            'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0,
            'period' => $period,
        ];
    }

    /**
     * Get attendance by parish for comparison
     */
    public function getAttendanceByParish($startDate, $endDate)
    {
        return $this->db->table('attendance a')
            ->select('
                c.id as parish_id,
                c.name as parish_name,
                COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance,
                COUNT(DISTINCT s.id) as total_services,
                COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants
            ')
            ->join('service s', 's.id = a.service_id')
            ->join('parish c', 'c.id = s.parish_id')
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->groupBy('c.id')
            ->orderBy('total_attendance', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get monthly attendance trend across all parishes.
     */
    public function getNetworkMonthlyTrend(int $months = 12): array
    {
        $months = max(1, $months);
        $startDate = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $endDate = date('Y-m-t');

        return $this->db->table('attendance a')
            ->select("DATE_FORMAT(s.service_date, '%Y-%m') as month_label")
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance')
            ->select('COUNT(DISTINCT s.id) as total_services')
            ->select('COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants')
            ->join('service s', 's.id = a.service_id')
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->groupBy("DATE_FORMAT(s.service_date, '%Y-%m')")
            ->orderBy('month_label', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get attendance averages for one parish.
     * Supports: today, week, month, year, sundays, weekdays, custom range
     */
    public function getParishAttendanceAverage(int $parishId, string $period = 'month', ?string $startDate = null, ?string $endDate = null): array
    {
        $builder = $this->db->table('attendance a')
            ->select('
                COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance,
                COUNT(DISTINCT s.id) as total_services,
                COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants
            ')
            ->join('service s', 's.id = a.service_id')
            ->where('s.parish_id', $parishId);

        switch ($period) {
            case 'today':
                $builder->where('s.service_date', date('Y-m-d'));
                break;
            case 'week':
                $builder->where('s.service_date >=', date('Y-m-d', strtotime('monday this week')));
                $builder->where('s.service_date <=', date('Y-m-d', strtotime('sunday this week')));
                break;
            case 'month':
                $builder->where('s.service_date >=', date('Y-m-01'));
                $builder->where('s.service_date <=', date('Y-m-t'));
                break;
            case 'year':
                $builder->where('s.service_date >=', date('Y-01-01'));
                $builder->where('s.service_date <=', date('Y-12-31'));
                break;
            case 'sundays':
                $builder->where('DAYOFWEEK(s.service_date)', 1);
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                } else {
                    $builder->where('s.service_date >=', date('Y-01-01'));
                    $builder->where('s.service_date <=', date('Y-12-31'));
                }
                break;
            case 'weekdays':
                $builder->whereIn('DAYOFWEEK(s.service_date)', [2, 3, 4, 5, 6, 7]);
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                } else {
                    $builder->where('s.service_date >=', date('Y-01-01'));
                    $builder->where('s.service_date <=', date('Y-12-31'));
                }
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                }
                break;
        }

        $row = $builder->get()->getRowArray();
        $totalServices = (int) ($row['total_services'] ?? 0);
        $totalAttendance = (int) ($row['total_attendance'] ?? 0);

        return [
            'total_attendance' => $totalAttendance,
            'total_services' => $totalServices,
            'total_communicants' => (int) ($row['total_communicants'] ?? 0),
            'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0,
            'period' => $period,
        ];
    }
}
