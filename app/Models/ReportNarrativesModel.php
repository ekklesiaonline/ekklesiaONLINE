<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportNarrativesModel extends Model
{
    protected $table = 'report_narratives';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['report_id', 'section_title', 'section_type', 'content', 'sort_order'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $validationRules = [
        'report_id' => 'required|numeric',
        'section_title' => 'required|string|max_length[255]',
        'section_type' => 'required|in_list[narrative,program,ministry,initiative]',
        'content' => 'required|string',
    ];

    public function getByReport(int $reportId): array
    {
        return $this->where('report_id', $reportId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function reorderSections(int $reportId, array $sectionOrder): bool
    {
        foreach ($sectionOrder as $index => $sectionId) {
            $this->update($sectionId, ['sort_order' => $index]);
        }
        return true;
    }
}
