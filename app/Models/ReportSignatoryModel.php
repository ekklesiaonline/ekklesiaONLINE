<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportSignatoryModel extends Model
{
    protected $table = 'report_signatory';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['report_id', 'user_id', 'signatory_role', 'signed_at', 'digital_signature'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $validationRules = [
        'report_id' => 'required|numeric',
        'user_id' => 'required|numeric',
        'signatory_role' => 'required|string|max_length[100]',
    ];

    public function getByReport(int $reportId): array
    {
        return $this->where('report_id', $reportId)
            ->join('users u', 'u.id = report_signatory.user_id', 'left')
            ->join('profile p', 'p.user_id = u.id', 'left')
            ->select('report_signatory.*, p.first_name, p.last_name, u.email')
            ->orderBy('report_signatory.created_at', 'ASC')
            ->findAll();
    }

    public function signReport(int $reportId, int $userId, string $role, string $signature = null): bool
    {
        return $this->insert([
            'report_id' => $reportId,
            'user_id' => $userId,
            'signatory_role' => $role,
            'signed_at' => date('Y-m-d H:i:s'),
            'digital_signature' => $signature,
        ]);
    }

    public function isFullySigned(int $reportId): bool
    {
        $required = $this->where('report_id', $reportId)
            ->where('signed_at IS NULL', null, false)
            ->countAllResults();

        return $required === 0;
    }
}
