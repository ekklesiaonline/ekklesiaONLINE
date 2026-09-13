<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportMembershipModel extends Model
{
    protected $table = 'report_membership';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['report_id', 'category', 'count', 'notes'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $validationRules = [
        'report_id' => 'required|numeric',
        'category' => 'required|in_list[adult_members,youth_members,children,inactive_members,transfers_in,transfers_out,confirmations,weddings,funerals]',
        'count' => 'required|numeric',
    ];

    public function getByReport(int $reportId): array
    {
        return $this->where('report_id', $reportId)
            ->orderBy('category')
            ->findAll();
    }
}
