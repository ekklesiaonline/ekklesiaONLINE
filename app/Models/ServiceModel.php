<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table            = 'service';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'service_date',
        'liturgical_day',
        'service',
        'place',
        'service_time',
        'preacher',
        'celebrant',
        'notes',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id'    => 'required|is_not_unique[parish.id]',
        'service_date' => 'required|valid_date',
        'service'      => 'permit_empty',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get service with attendance counts
     */
    public function getServiceWithAttendance($serviceId)
    {
        $service = $this->find($serviceId);
        if (!$service) {
            return null;
        }

        $counts = $this->db->table('attendance')
            ->select('COUNT(*) as total_attended, SUM(communicant) as total_communicants')
            ->where('service_id', $serviceId)
            ->where('attended', 1)
            ->get()
            ->getRowArray();

        $service['total_attended'] = $counts['total_attended'] ?? 0;
        $service['total_communicants'] = $counts['total_communicants'] ?? 0;

        return $service;
    }

    /**
     * Get parish services with attendance
     */
    public function getParishServices($parishId, $limit = null, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('service s')
            ->select('s.*, COALESCE(sm.reported_present_count, 0) as total_attended, COALESCE(sm.reported_present_count, 0) as attendance_count, COALESCE(sm.reported_communion_count, 0) as total_communicants')
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->where('s.parish_id', $parishId)
            ->orderBy('s.service_date', 'DESC')
            ->orderBy('s.service_time', 'DESC');

        if ($startDate) {
            $builder->where('s.service_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('s.service_date <=', $endDate);
        }

        if ($limit) {
            $builder->limit($limit);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Paginated parish services for list views.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getParishServicesPaginated(int $parishId, int $perPage = 20, int $page = 1, ?string $startDate = null, ?string $endDate = null): array
    {
        $builder = $this->db->table('service s')
            ->select('s.*, COALESCE(sm.reported_present_count, 0) as total_attended, COALESCE(sm.reported_present_count, 0) as attendance_count, COALESCE(sm.reported_communion_count, 0) as total_communicants')
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->where('s.parish_id', $parishId);

        if ($startDate !== null && $startDate !== '') {
            $builder->where('s.service_date >=', $startDate);
        }

        if ($endDate !== null && $endDate !== '') {
            $builder->where('s.service_date <=', $endDate);
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $builder
            ->orderBy('s.service_date', 'DESC')
            ->orderBy('s.service_time', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Get attendance statistics
     */
    public function getAttendanceStats($parishId, $startDate, $endDate)
    {
        return $this->db->table('service s')
            ->select('s.service_date, s.service, COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attended')
            ->join('attendance a', 'a.service_id = s.id', 'left')
            ->where('s.parish_id', $parishId)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->groupBy('s.id')
            ->orderBy('s.service_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Backward-compatible alias used by Attendance controller.
     */
    public function getServiceAttendanceStats($parishId, $startDate, $endDate)
    {
        return $this->getAttendanceStats($parishId, $startDate, $endDate);
    }
}
