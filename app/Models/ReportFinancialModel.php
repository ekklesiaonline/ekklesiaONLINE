<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportFinancialModel extends Model
{
    protected $table = 'report_financial';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['report_id', 'category', 'amount', 'currency', 'notes'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $validationRules = [
        'report_id' => 'required|numeric',
        'category' => 'required|string|max_length[100]',
        'amount' => 'required|numeric',
        'currency' => 'required|string|max_length[3]',
    ];

    public function getByReport(int $reportId): array
    {
        return $this->where('report_id', $reportId)
            ->orderBy('category')
            ->findAll();
    }

    public function getTotalByReport(int $reportId, string $currency = 'DOP'): float
    {
        $row = $this->where('report_id', $reportId)
            ->where('currency', $currency)
            ->selectSum('amount')
            ->first();

        return (float) ($row['amount'] ?? 0);
    }
}
