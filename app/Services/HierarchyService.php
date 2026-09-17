<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * HierarchyService
 *
 * Read-side lookups for the province → diocese → parish hierarchy:
 * option lists for forms, parent-child validity checks, and name
 * resolution. Shared by member-portal, diocesan, and system views.
 */
class HierarchyService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function provincesList(): array
    {
        return $this->db->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Dioceses grouped by province_id for cascading selects.
     *
     * @return array<int, non-empty-list<array{id: int, name: string}>>
     */
    public function diocesesByProvince(): array
    {
        $rows = $this->db->table('diocese')
            ->select('id, province_id, name')
            ->orderBy('province_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $provinceId = (string) ((int) ($row['province_id'] ?? 0));
            if ($provinceId === '0') {
                continue;
            }

            $grouped[$provinceId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        return $grouped;
    }

    /**
     * Parishes grouped by diocese_id for cascading selects.
     *
     * @return array<int, non-empty-list<array{id: int, name: string, parish_ueid: string}>>
     */
    public function parishesByDiocese(): array
    {
        $rows = $this->db->table('parish')
            ->select('id, diocese_id, name, parish_ueid')
            ->orderBy('diocese_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $dioceseId = (string) ((int) ($row['diocese_id'] ?? 0));
            if ($dioceseId === '0') {
                continue;
            }

            $grouped[$dioceseId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'parish_ueid' => (string) ($row['parish_ueid'] ?? ''),
            ];
        }

        return $grouped;
    }

    /**
     * Dioceses within one province, name-ordered.
     *
     * @return list<array<string, mixed>>
     */
    public function diocesesInProvince(int $provinceId): array
    {
        return $this->db->table('diocese')
            ->where('province_id', $provinceId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Province a diocese belongs to, or null when unresolvable.
     */
    public function provinceIdForDiocese(int $dioceseId): ?int
    {
        $diocese = $this->db->table('diocese')
            ->select('province_id')
            ->where('id', $dioceseId)
            ->get()
            ->getRowArray();

        return ! empty($diocese['province_id']) ? (int) $diocese['province_id'] : null;
    }

    public function dioceseInProvince(int $dioceseId, int $provinceId): bool
    {
        return $this->db->table('diocese')
            ->where('id', $dioceseId)
            ->where('province_id', $provinceId)
            ->countAllResults() > 0;
    }

    public function parishInDiocese(int $parishId, int $dioceseId): bool
    {
        return $this->db->table('parish')
            ->where('id', $parishId)
            ->where('diocese_id', $dioceseId)
            ->countAllResults() > 0;
    }

    /**
     * Display name for a hierarchy entity (province|diocese|parish).
     */
    public function nameFor(string $scope, int $id): string
    {
        if ($id <= 0 || ! in_array($scope, ['province', 'diocese', 'parish'], true)) {
            return '';
        }

        $row = $this->db->table($scope)->select('name')->where('id', $id)->get()->getRowArray();

        return (string) ($row['name'] ?? '');
    }

    /**
     * All parish_people snapshot rows for a user, enriched with the
     * parish/diocese/province hierarchy. Flags the row matching the
     * primary parish. Returns [] when the snapshot table or its
     * parish_id column is unavailable.
     *
     * @return list<array<string, mixed>>
     */
    public function parishAffiliationsForUser(int $userId, int $primaryParishId): array
    {
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames('parish_people'));
            if (! in_array('parish_id', $columns, true)) {
                return [];
            }

            $rows = $this->db->table('parish_people pp')
                ->select('pp.id, pp.parish_id, p.diocese_id, d.province_id, pp.membership_status, p.name as parish_name')
                ->join('parish p', 'p.id = pp.parish_id', 'left')
                ->join('diocese d', 'd.id = p.diocese_id', 'left')
                ->where('pp.user_id', $userId)
                ->orderBy('p.name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['is_primary'] = ((int) $row['parish_id']) === $primaryParishId;
        }
        unset($row);

        return $rows;
    }

    /**
     * Lowercased column names of the parish table.
     *
     * @return list<string>
     */
    public function parishColumns(): array
    {
        try {
            return array_map('strtolower', $this->db->getFieldNames('parish'));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Parish row including whichever hierarchy columns exist.
     *
     * @return array<string, mixed>|null
     */
    public function parishRow(int $parishId): ?array
    {
        $columns = $this->parishColumns();
        $select = ['id'];
        foreach (['diocese_id', 'province_id', 'name'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $row = $this->db->table('parish')
            ->select(implode(', ', $select))
            ->where('id', $parishId)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }
}
