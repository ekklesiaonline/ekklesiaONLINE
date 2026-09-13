<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishReportModel extends Model
{
    protected $table = 'parish_report';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['parish_id', 'diocese_id', 'province_id', 'report_type', 'report_year', 'report_period', 'title', 'description', 'generated_by', 'status', 'file_path', 'data_hash', 'submitted_at', 'approved_by', 'approved_at', 'published_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'report_type' => 'required|in_list[membership,financial,clergy,narrative,programs,custom]',
        'report_year' => 'permit_empty|numeric',
        'title' => 'required|string|max_length[255]',
        'generated_by' => 'required|numeric',
        'status' => 'required|in_list[draft,submitted,approved,published,archived]',
    ];

    /**
     * Get reports by parish and year
     */
    public function getByParishAndYear(int $parishId, int $year): array
    {
        return $this->where('parish_id', $parishId)
            ->where('report_year', $year)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get reports by diocese (aggregated from parishes)
     */
    public function getByDiocese(int $dioceseId, int $year = null): array
    {
        $builder = $this->where('diocese_id', $dioceseId);
        if ($year !== null) {
            $builder->where('report_year', $year);
        }
        return $builder->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * Get reports by province
     */
    public function getByProvince(int $provinceId, int $year = null): array
    {
        $builder = $this->where('province_id', $provinceId);
        if ($year !== null) {
            $builder->where('report_year', $year);
        }
        return $builder->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * Mark report as submitted for review
     */
    public function submitForReview(int $reportId): bool
    {
        return $this->update($reportId, [
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Approve a report
     */
    public function approveReport(int $reportId, int $approvingUserId, string $reason = null): bool
    {
        return $this->update($reportId, [
            'status' => 'approved',
            'approved_by' => $approvingUserId,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Publish an approved report
     */
    public function publishReport(int $reportId): bool
    {
        return $this->update($reportId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
