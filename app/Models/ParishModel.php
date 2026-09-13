<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class ParishModel extends Model
{
    protected $table            = 'parish';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'parish_ueid',
        'logo',
        'banner',
        'diocese_id',
        'street',
        'mailing_street',
        'city',
        'mailing_city',
        'state',
        'mailing_state',
        'zip',
        'mailing_zip',
        'county',
        'mailing_county',
        'country',
        'mailing_country',
        'tax_id',
        'address',
        'phone',
        'email',
        'website',
        'public_token',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'name'  => 'required|min_length[3]|max_length[255]',
        'parish_ueid' => 'permit_empty|max_length[32]',
        'diocese_id' => 'permit_empty|is_not_unique[diocese.id]',
        'street' => 'permit_empty|max_length[255]',
        'mailing_street' => 'permit_empty|max_length[255]',
        'city' => 'permit_empty|max_length[100]',
        'mailing_city' => 'permit_empty|max_length[100]',
        'state' => 'permit_empty|max_length[50]',
        'mailing_state' => 'permit_empty|max_length[50]',
        'zip' => 'permit_empty|max_length[20]',
        'mailing_zip' => 'permit_empty|max_length[20]',
        'county' => 'permit_empty|max_length[100]',
        'mailing_county' => 'permit_empty|max_length[100]',
        'country' => 'permit_empty|max_length[100]',
        'mailing_country' => 'permit_empty|max_length[100]',
        'tax_id' => 'permit_empty|max_length[50]',
        'email' => 'permit_empty|valid_email|max_length[255]',
        'phone' => 'permit_empty|max_length[50]',
        'banner' => 'permit_empty|max_length[255]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get parish with member count
     *
     * @param int|null    $parishId   Return single parish row when provided.
     * @param int|null    $provinceId Restrict list to parishes in this province.
     * @param int|null    $dioceseId  Restrict list to parishes in this diocese
     *                                (takes priority over $provinceId).
     */
    public function getWithMemberCount($parishId = null, ?int $provinceId = null, ?int $dioceseId = null)
    {
        $builder = $this->db->table('parish c')
            ->select('c.*, d.name as diocese_name, pz.name as province_name, pz.province_number, COUNT(DISTINCT mca.linked_user_id) as member_count')
            ->join('diocese d', 'd.id = c.diocese_id', 'left')
            ->join('province pz', 'pz.id = d.province_id', 'left')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1 AND mca.deleted_at IS NULL', 'left', false)
            ->join('auth_groups_users agu', 'agu.user_id = mca.linked_user_id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('agu.id IS NULL') // Exclude province/diocesan admin users from parish member counts
            ->where('c.deleted_at', null)
            ->groupBy('c.id');

        if ($parishId) {
            $builder->where('c.id', $parishId);
            return $builder->get()->getRowArray();
        }

        if ($dioceseId) {
            $builder->where('c.diocese_id', $dioceseId);
        } elseif ($provinceId) {
            $builder->where('d.province_id', $provinceId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Paginated variant of getWithMemberCount.
     *
     * Returns ['parish' => array, 'total' => int, 'per_page' => int, 'current_page' => int, 'total_pages' => int]
     *
     * @param int       $perPage    Records per page (default 25)
     * @param int       $page       1-based page number (default 1)
     * @param int|null  $provinceId Filter by province
     * @param int|null  $dioceseId  Filter by diocese (takes priority over provinceId)
     */
    public function paginateWithMemberCount(
        int $perPage = 25,
        int $page = 1,
        ?int $provinceId = null,
        ?int $dioceseId = null,
        ?string $search = null,
        ?string $letter = null,
        bool $mailingDiffersOnly = false
    ): array {
        $dioceseId  = ($dioceseId  > 0) ? $dioceseId  : null;
        $provinceId = ($provinceId > 0) ? $provinceId : null;
        $search     = ($search !== null) ? trim($search) : '';
        $letter     = ($letter  !== null) ? strtoupper(trim($letter)) : '';

        $builder = $this->db->table('parish c')
            ->select('c.*, d.name as diocese_name, pz.name as province_name, pz.province_number, COUNT(DISTINCT mca.linked_user_id) as member_count')
            ->join('diocese d', 'd.id = c.diocese_id', 'left')
            ->join('province pz', 'pz.id = d.province_id', 'left')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1 AND mca.deleted_at IS NULL', 'left', false)
            ->join('auth_groups_users agu', 'agu.user_id = mca.linked_user_id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('agu.id IS NULL')
            ->where('c.deleted_at', null)
            ->groupBy('c.id');

        $countBuilder = $this->db->table('parish c')
            ->select('c.id')
            ->join('diocese d', 'd.id = c.diocese_id', 'left')
            ->join('province pz', 'pz.id = d.province_id', 'left')
            ->where('c.deleted_at', null);

        if ($dioceseId) {
            $builder->where('c.diocese_id', $dioceseId);
            $countBuilder->where('c.diocese_id', $dioceseId);
        } elseif ($provinceId) {
            $builder->where('d.province_id', $provinceId);
            $countBuilder->where('d.province_id', $provinceId);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.email', $search)
                ->orLike('c.parish_ueid', $search)
                ->groupEnd();
            $countBuilder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.email', $search)
                ->orLike('c.parish_ueid', $search)
                ->groupEnd();
        } elseif ($letter !== '') {
            $builder->like('c.name', $letter, 'after');
            $countBuilder->like('c.name', $letter, 'after');
        }

        if ($mailingDiffersOnly) {
            $builder->groupStart()
                ->where('TRIM(COALESCE(c.street, "")) <> TRIM(COALESCE(c.mailing_street, ""))')
                ->orWhere('TRIM(COALESCE(c.city, "")) <> TRIM(COALESCE(c.mailing_city, ""))')
                ->orWhere('TRIM(COALESCE(c.state, "")) <> TRIM(COALESCE(c.mailing_state, ""))')
                ->orWhere('TRIM(COALESCE(c.zip, "")) <> TRIM(COALESCE(c.mailing_zip, ""))')
                ->orWhere('TRIM(COALESCE(c.county, "")) <> TRIM(COALESCE(c.mailing_county, ""))')
                ->orWhere('TRIM(COALESCE(c.country, "")) <> TRIM(COALESCE(c.mailing_country, ""))')
                ->groupEnd();

            $countBuilder->groupStart()
                ->where('TRIM(COALESCE(c.street, "")) <> TRIM(COALESCE(c.mailing_street, ""))')
                ->orWhere('TRIM(COALESCE(c.city, "")) <> TRIM(COALESCE(c.mailing_city, ""))')
                ->orWhere('TRIM(COALESCE(c.state, "")) <> TRIM(COALESCE(c.mailing_state, ""))')
                ->orWhere('TRIM(COALESCE(c.zip, "")) <> TRIM(COALESCE(c.mailing_zip, ""))')
                ->orWhere('TRIM(COALESCE(c.county, "")) <> TRIM(COALESCE(c.mailing_county, ""))')
                ->orWhere('TRIM(COALESCE(c.country, "")) <> TRIM(COALESCE(c.mailing_country, ""))')
                ->groupEnd();
        }

        $total = $countBuilder->countAllResults();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $parishes = $builder->orderBy('c.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'parish'     => $parishes,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $totalPages,
        ];
    }

    public function findWithHierarchy(int $parishId): ?array
    {
        $builder = $this->db->table('parish c')
            ->where('c.id', $parishId)
            ->where('c.deleted_at', null);

        $hasDioceseId = false;
        try {
            $hasDioceseId = in_array('diocese_id', $this->db->getFieldNames('parish'), true);
        } catch (\Throwable $e) {
            $hasDioceseId = false;
        }

        if ($hasDioceseId) {
            $builder->select('c.*, d.name as diocese_name, d.province_id, pz.name as province_name, pz.province_number')
                ->join('diocese d', 'd.id = c.diocese_id', 'left')
                ->join('province pz', 'pz.id = d.province_id', 'left');
        } else {
            $builder->select('c.*, NULL as diocese_name, NULL as province_id, NULL as province_name, NULL as province_number', false);
        }

        $row = $builder->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Get parish staff (admins + leaders) for a parish.
     */
    public function getParishStaff(int $parishId): array
    {
        $staffRoles = ['parishadmin', 'clergy', 'treasurer', 'volunteer_coordinator'];

        $rows = $this->db->table('profile p')
            ->select('p.user_id, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, p.phone, ai.secret as email, agu.group as role')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu_super', 'agu_super.user_id = p.user_id AND agu_super.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->join('parish c', 'c.id = p.parish_id', 'inner')
            ->where('c.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('p.parish_id', $parishId)
            ->whereIn('agu.group', $staffRoles)
            ->where('agu_super.id IS NULL') // Exclude province/diocesan admin users
            ->orderBy('agu.group', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $row): array {
            $row['phone'] = DataEncryption::decrypt($row['phone'] ?? null);
            return $row;
        }, $rows);
    }

    /**
     * Get all parish members with their current groups.
     */
    public function getParishMembersWithGroups(int $parishId): array
    {
        return $this->db->table('profile p')
            ->select('p.user_id, ANY_VALUE(COALESCE(p.nickname, p.first_name)) as first_name, ANY_VALUE(p.last_name) as last_name, ANY_VALUE(ai.secret) as email, GROUP_CONCAT(DISTINCT agu.group ORDER BY agu.group SEPARATOR ",") as group_list')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id', 'left')
            ->join('auth_groups_users agu_super', 'agu_super.user_id = p.user_id AND agu_super.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('p.parish_id', $parishId)
            ->where('agu_super.id IS NULL') // Exclude province/diocesan admin users
            ->groupBy('p.user_id')
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
