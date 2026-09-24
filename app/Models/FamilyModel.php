<?php

namespace App\Models;

use App\Libraries\DataEncryption;

class FamilyModel extends BaseTenantModel
{
    protected $table            = 'family';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'family_name',
        'head_of_household_id',
        'address',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id'   => 'required|is_not_unique[parish.id]',
        'family_name' => 'required|min_length[3]|max_length[255]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get family with members
     */
    public function getFamilyWithMembers($familyId)
    {
        $parishId = $this->currentParishId();
        $family   = $this->find($familyId);

        if (! $family || (int) ($family['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        $profileColumns = array_map('strtolower', $this->getColumnsForTable('profile'));
        $hasMiddleName = in_array('middle_name', $profileColumns, true);
        $hasPortalAccess = in_array('portal_access', $profileColumns, true);
        $hasPortalEnabledAt = in_array('portal_enabled_at', $profileColumns, true);

        // Get head of household name if set
        if ($family['head_of_household_id']) {
            $head = $this->db->table('profile p')
                ->select('p.first_name, p.last_name')
                ->where('p.user_id', $family['head_of_household_id'])
                ->where('p.parish_id', $parishId)
                ->where('p.deleted_at', null)
                ->get()
                ->getRowArray();
            if ($head) {
                $family['head_name'] = $head['first_name'] . ' ' . $head['last_name'];
            }
        }

        $select = [
            'fm.id as family_member_id',
            'fm.user_id',
            'fm.relationship',
            'p.first_name',
            $hasMiddleName ? 'p.middle_name' : 'NULL as middle_name',
            'p.last_name',
            'p.phone',
            'pa.membership_status',
            $hasPortalAccess ? 'p.portal_access' : 'NULL as portal_access',
            $hasPortalEnabledAt ? 'p.portal_enabled_at' : 'NULL as portal_enabled_at',
            'CONCAT(p.first_name, " ", p.last_name) as full_name',
            'ai.secret as email',
        ];

        $members = $this->db->table('family_member fm')
            ->select(implode(', ', $select), false)
            ->join('family f', 'f.id = fm.family_id')
            ->join('profile p', 'p.user_id = fm.user_id')
            ->join('users u', 'u.id = fm.user_id')
            ->join('parish_affiliation pa', 'pa.linked_user_id = fm.user_id AND pa.parish_id = f.parish_id AND pa.deleted_at IS NULL', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('f.parish_id', $parishId)
            ->where('f.deleted_at', null)
            ->where('fm.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('fm.family_id', $familyId)
            ->get()
            ->getResultArray();

        $members = array_map(static function (array $row): array {
            $row['phone'] = DataEncryption::decrypt($row['phone'] ?? null);
            return $row;
        }, $members);

        $family['members'] = $members;
        return $family;
    }

    /**
     * @return array<int,string>
     */
    private function getColumnsForTable(string $baseTable): array
    {
        $prefixed = $this->db->getPrefix() . $baseTable;
        $candidates = array_values(array_unique([$prefixed, $baseTable]));

        foreach ($candidates as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $columns;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return [];
    }

    /**
     * Get all families for a parish with member counts
     */
    public function getParishFamiliesWithCounts($parishId)
    {
        return $this->db->table('family f')
            ->select('f.*, 
                      CONCAT(p.first_name, " ", p.last_name) as head_name,
                      COUNT(fm.id) as member_count')
            ->join('profile p', 'p.user_id = f.head_of_household_id', 'left')
            ->join('family_member fm', 'fm.family_id = f.id', 'left')
            ->where('f.parish_id', $parishId)
            ->where('f.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('fm.deleted_at', null)
            ->groupBy('f.id')
            ->orderBy('f.family_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get all families for a parish
     */
    public function getParishFamilies($parishId)
    {
        $builder = $this->builder();
        $columns = $this->getColumnsForTable('family');

        if (in_array('parish_id', $columns, true)) {
            $builder->where('parish_id', $parishId);
        }

        if (in_array('deleted_at', $columns, true)) {
            $builder->where('deleted_at', null);
        }

        return $builder->orderBy('family_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
