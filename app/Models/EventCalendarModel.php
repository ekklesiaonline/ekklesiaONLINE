<?php

namespace App\Models;

use CodeIgniter\Model;

class EventCalendarModel extends Model
{
    protected $table            = 'event_calendar';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'church_id',
        'province_id',
        'parish_id',
        'diocese_id',
        'scope_level',
        'name',
        'color_hex',
        'is_active',
        'sort_order',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'scope_level' => 'required|in_list[national,province,diocese,parish]',
        'name' => 'required|max_length[120]',
        'color_hex' => 'required|regex_match[/^#[0-9A-Fa-f]{6}$/]',
    ];

    public function getAssignableCalendars(int $parishId, int $dioceseId, bool $allowDioceseScope, ?int $provinceId = null, ?int $churchId = null): array
    {
        $hasIsActive    = $this->columnExists('is_active');
        $hasScopeLevel  = $this->columnExists('scope_level');
        $hasChurchId    = $this->columnExists('church_id');
        $hasProvinceId  = $this->columnExists('province_id');
        $hasParishId    = $this->columnExists('parish_id');
        $hasDioceseId   = $this->columnExists('diocese_id');
        $hasSortOrder   = $this->columnExists('sort_order');

        $builder = $this->builder();

        if ($hasIsActive) {
            $builder->where('is_active', 1);
        }

        if ($hasScopeLevel && $hasParishId) {
            $builder->groupStart();

            if ($parishId > 0) {
                $builder->groupStart()
                    ->where('scope_level', 'parish')
                    ->where('parish_id', $parishId)
                    ->groupEnd();
            }

            if ($allowDioceseScope && $dioceseId > 0 && $hasDioceseId) {
                $builder->orGroupStart()
                    ->where('scope_level', 'diocese')
                    ->where('diocese_id', $dioceseId)
                    ->groupEnd();
            }

            if ($provinceId !== null && $provinceId > 0 && $hasProvinceId) {
                $builder->orGroupStart()
                    ->where('scope_level', 'province')
                    ->where('province_id', $provinceId)
                    ->groupEnd();
            }

            if ($churchId !== null && $churchId > 0 && $hasChurchId) {
                $builder->orGroupStart()
                    ->where('scope_level', 'national')
                    ->where('church_id', $churchId)
                    ->groupEnd();
            }

            $builder->groupEnd();
        } elseif ($hasParishId && $parishId > 0) {
            $builder->where('parish_id', $parishId);
        }

        if ($hasScopeLevel) {
            $builder->orderBy('scope_level', 'ASC');
        }
        if ($hasSortOrder) {
            $builder->orderBy('sort_order', 'ASC');
        }

        return $builder
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function columnExists(string $column): bool
    {
        try {
            return $this->db->fieldExists($column, $this->table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
