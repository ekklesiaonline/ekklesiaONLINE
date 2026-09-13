<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportClegyModel extends Model
{
    protected $table = 'report_clergy';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['report_id', 'user_id', 'position', 'ordination_date', 'assignment_date', 'status', 'notes'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $validationRules = [
        'report_id' => 'required|numeric',
        'user_id' => 'required|numeric',
        'position' => 'required|string|max_length[100]',
        'status' => 'required|in_list[active,retired,sabbatical,deceased]',
    ];

    public function getByReport(int $reportId): array
    {
        return $this->where('report_id', $reportId)
            ->join('users u', 'u.id = report_clergy.user_id', 'left')
            ->join('profile p', 'p.user_id = u.id', 'left')
            ->select('report_clergy.*, p.first_name, p.last_name, u.email')
            ->orderBy('report_clergy.assignment_date', 'DESC')
            ->findAll();
    }
}
