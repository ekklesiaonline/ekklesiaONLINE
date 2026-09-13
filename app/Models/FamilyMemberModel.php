<?php

namespace App\Models;

use CodeIgniter\Model;

class FamilyMemberModel extends Model
{
    public const RELATIONSHIP_VALUES = ['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'];

    protected $table            = 'family_member';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'family_id',
        'user_id',
        'relationship',
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';

    // Validation
    protected $validationRules      = [
        'family_id' => 'required|is_not_unique[family.id]',
        'user_id'   => 'required|is_not_unique[users.id]',
        'relationship' => 'permit_empty|in_list[Head,Spouse,Child,Parent,Sibling,Other]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get user's family
     */
    public function getUserFamily($userId)
    {
        $member = $this->where('user_id', $userId)->first();
        if (!$member) {
            return null;
        }

        return $this->db->table('family f')
            ->select('f.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name')
            ->join('profile p', 'p.user_id = f.head_of_household_id', 'left')
            ->where('f.id', $member['family_id'])
            ->get()
            ->getRowArray();
    }

    public function relationExists(int $familyId, int $userId): bool
    {
        return $this->where('family_id', $familyId)
            ->where('user_id', $userId)
            ->first() !== null;
    }

    public function isUserInFamily(int $familyId, int $userId): bool
    {
        return $this->where('family_id', $familyId)
            ->where('user_id', $userId)
            ->first() !== null;
    }

    public function userHasAnotherFamily(int $userId, ?int $excludeFamilyId = null): bool
    {
        $builder = $this->where('user_id', $userId);

        if ($excludeFamilyId !== null) {
            $builder->where('family_id !=', $excludeFamilyId);
        }

        return $builder->first() !== null;
    }

    public function normalizeRelationship(?string $relationship): ?string
    {
        $value = trim((string) $relationship);
        if ($value === '') {
            return null;
        }

        if (! in_array($value, self::RELATIONSHIP_VALUES, true)) {
            return null;
        }

        return $value;
    }
}
