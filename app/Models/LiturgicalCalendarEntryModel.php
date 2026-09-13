<?php

namespace App\Models;

use CodeIgniter\Model;

class LiturgicalCalendarEntryModel extends Model
{
    protected $table            = 'liturgical_calendar_entry';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'owner_province_id',
        'owner_diocese_id',
        'created_by_user_id',
        'source_tier',
        'is_system_template',
        'code',
        'name',
        'season',
        'observance_type',
        'sort_order',
        'liturgical_color',
        'season_icon',
        'observance_icon',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected function initialize()
    {
        parent::initialize();

        $table = $this->resolveExistingTable();
        if ($table !== null) {
            $this->table = $table;
        }
    }

    public function getSeasonsForDropdown($parishId): array
    {
        $builder = $this->builder();
        $builder->select('season, MIN(sort_order) AS min_sort_order');
        $builder->where('is_active', 1);
        $this->applyParishScope($builder, $parishId);
        $builder->groupBy('season');
        $builder->orderBy('min_sort_order', 'ASC');

        $rows = $builder->get()->getResultArray();

        return array_values(array_map(static fn(array $row): string => (string) $row['season'], $rows));
    }

    public function getDaysBySeason($parishId, string $season): array
    {
        $builder = $this->builder();
        $builder->select('id, parish_id, code, name, season, observance_type, sort_order, liturgical_color, season_icon, observance_icon');
        $builder->where('is_active', 1);
        $builder->where('season', $season);
        $this->applyParishScope($builder, $parishId);
        $builder->orderBy('sort_order', 'ASC');

        $rows = $builder->get()->getResultArray();

        return $this->preferParishSpecificEntries($rows);
    }

    public function findByName($parishId, string $name): ?array
    {
        $builder = $this->builder();
        $builder->select('id, parish_id, code, name, season, observance_type, sort_order, liturgical_color, season_icon, observance_icon');
        $builder->where('is_active', 1);
        $builder->where('name', $name);
        $this->applyParishScope($builder, $parishId);
        $builder->orderBy('sort_order', 'ASC');

        $rows = $builder->get()->getResultArray();
        $rows = $this->preferParishSpecificEntries($rows);

        return $rows[0] ?? null;
    }

    private function applyParishScope($builder, $parishId): void
    {
        if (!empty($parishId)) {
            $builder->groupStart()
                ->where('parish_id', (int) $parishId)
                ->orWhere('parish_id', null)
                ->groupEnd();
            return;
        }

        $builder->where('parish_id', null);
    }

    private function preferParishSpecificEntries(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            $keyA = strtolower(trim((string) ($a['season'] ?? ''))) . '|' . strtolower(trim((string) ($a['name'] ?? ''))) . '|' . strtolower(trim((string) ($a['observance_type'] ?? '')));
            $keyB = strtolower(trim((string) ($b['season'] ?? ''))) . '|' . strtolower(trim((string) ($b['name'] ?? ''))) . '|' . strtolower(trim((string) ($b['observance_type'] ?? '')));

            if ($keyA === $keyB) {
                if ($a['parish_id'] === $b['parish_id']) {
                    return ((int) $a['sort_order']) <=> ((int) $b['sort_order']);
                }

                return $a['parish_id'] === null ? 1 : -1;
            }

            return strcmp($keyA, $keyB);
        });

        $deduped = [];
        foreach ($rows as $row) {
            $dedupeKey = strtolower(trim((string) ($row['season'] ?? ''))) . '|' . strtolower(trim((string) ($row['name'] ?? ''))) . '|' . strtolower(trim((string) ($row['observance_type'] ?? '')));
            if (!isset($deduped[$dedupeKey])) {
                $deduped[$dedupeKey] = $row;
            }
        }

        $result = array_values($deduped);
        usort($result, static fn(array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));

        return $result;
    }

    private function resolveExistingTable(): ?string
    {
        $candidates = [
            $this->db->getPrefix() . 'liturgical_calendar_entry',
            'liturgical_calendar_entry',
            $this->db->getPrefix() . 'liturgical_calendar_entries',
            'liturgical_calendar_entries',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }
}
