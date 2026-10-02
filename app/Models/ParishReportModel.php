<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Canonical annual parish report. Signatures must go through ParishReportApprovalService. */
class ParishReportModel extends Model
{
    protected $table = 'parish_report';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'parish_id', 'report_year', 'report_status', 'submitted_by', 'submitted_at',
        'report_revision', 'frozen_snapshot_json', 'snapshot_sha256',
        'clerk_certified_by', 'clerk_certified_at', 'warden_approved_by', 'warden_approved_at',
        'finalized_by', 'finalized_at', 'diocesan_approved_by', 'diocesan_approved_at',
    ];
    protected array $casts = [
        'parish_id' => '?int', 'report_year' => '?int', 'report_revision' => '?int',
        'submitted_by' => '?int', 'clerk_certified_by' => '?int', 'warden_approved_by' => '?int',
        'finalized_by' => '?int', 'diocesan_approved_by' => '?int',
    ];
    protected $validationRules = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'report_year' => 'required|is_natural_no_zero',
        'report_status' => 'required|in_list[draft,submitted,clerk_certified,warden_approved,clergy_finalized,diocesan_approved]',
    ];

    public function getByParishAndYear(int $parishId, int $year): array
    {
        return $this->where('parish_id', $parishId)->where('report_year', $year)
            ->orderBy('created_at', 'DESC')->findAll();
    }

    public function getByDiocese(int $dioceseId, ?int $year = null): array
    {
        $builder = $this->db->table('parish_report r')->select('r.*')
            ->join('parish p', 'p.id = r.parish_id')->where('p.diocese_id', $dioceseId);
        if ($year !== null) {
            $builder->where('r.report_year', $year);
        }
        return $builder->orderBy('r.created_at', 'DESC')->get()->getResultArray();
    }

    public function getByProvince(int $provinceId, ?int $year = null): array
    {
        $builder = $this->db->table('parish_report r')->select('r.*')
            ->join('parish p', 'p.id = r.parish_id')
            ->join('diocese d', 'd.id = p.diocese_id')->where('d.province_id', $provinceId);
        if ($year !== null) {
            $builder->where('r.report_year', $year);
        }
        return $builder->orderBy('r.created_at', 'DESC')->get()->getResultArray();
    }

    /** The immutable submitted evidence; do not substitute live storage after sign-off. */
    public function signedSnapshot(int $reportId): ?array
    {
        $report = $this->find($reportId);
        if (! is_array($report) || empty($report['frozen_snapshot_json'])) {
            return null;
        }
        $json = (string) $report['frozen_snapshot_json'];
        if (! hash_equals((string) ($report['snapshot_sha256'] ?? ''), hash('sha256', $json))) {
            return null;
        }
        $value = json_decode($json, true);
        return is_array($value) ? $value : null;
    }
}
