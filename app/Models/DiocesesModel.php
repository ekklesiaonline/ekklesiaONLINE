<?php

namespace App\Models;

use CodeIgniter\Model;

class DiocesesModel extends Model
{
    protected $table = 'diocese';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['province_id', 'name', 'alias_norm'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[255]|is_unique[diocese.name,id,{id}]',
        'province_id' => 'required|integer|is_not_unique[province.id]',
        'alias_norm' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Diocese name is required.',
            'min_length' => 'Diocese name must be at least 3 characters.',
            'max_length' => 'Diocese name cannot exceed 255 characters.',
            'is_unique' => 'A diocese with this name already exists.',
        ],
        'province_id' => [
            'required' => 'Province is required.',
            'integer' => 'Province must be a valid number.',
            'is_not_unique' => 'Selected province does not exist.',
        ],
    ];

    /**
     * Get dioceses with province info
     */
    public function getDiocesesWithProvince(?int $provinceId = null, ?int $dioceseId = null, $limit = null, $offset = 0)
    {
        $builder = $this->db->table('diocese d')
            ->select('d.*, p.name as province_name, COUNT(DISTINCT c.id) as parish_count, COUNT(DISTINCT CASE WHEN mca.linked_user_id IS NOT NULL AND agu.id IS NULL THEN mca.linked_user_id END) as member_count')
            ->join('province p', 'p.id = d.province_id', 'left')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1', 'left')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->groupBy('d.id')
            ->orderBy('p.name', 'ASC')
            ->orderBy('d.name', 'ASC');

        if ($provinceId !== null) {
            $builder->where('d.province_id', $provinceId);
        }

        if ($dioceseId !== null) {
            $builder->where('d.id', $dioceseId);
        }

        if ($limit) {
            $builder->limit($limit, $offset);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Get diocese with detailed info including admins
     */
    public function getDioceseWithDetails(int $dioceseId, ?int $provinceId = null)
    {
        $diocese = $this->db->table('diocese d')
            ->select('d.*, p.name as province_name')
            ->join('province p', 'p.id = d.province_id', 'left')
            ->where('d.id', $dioceseId);

        if ($provinceId !== null) {
            $diocese->where('d.province_id', $provinceId);
        }

        $diocese = $diocese->get()->getRowArray();

        if (!$diocese) {
            return null;
        }

        // Get diocesan admins and their parishes
        $diocese['admins'] = $this->db->table('auth_groups_users agu')
            ->select('u.id, ANY_VALUE(COALESCE(p.nickname, p.first_name)) as first_name, ANY_VALUE(p.last_name) as last_name, ANY_VALUE(ai.secret) as user_email, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") as parishes')
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->join('profile p', 'p.user_id = u.id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = p.parish_id', 'left')
            ->where('agu.group', 'dioceseadmin')
            ->where('c.diocese_id', $dioceseId)
            ->groupBy('u.id')
            ->get()
            ->getResultArray();

        // Get parishes in this diocese
        $diocese['parish'] = $this->db->table('parish c')
            ->select('c.*, COUNT(DISTINCT CASE WHEN mca.linked_user_id IS NOT NULL AND agu.id IS NULL THEN mca.linked_user_id END) as member_count')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1', 'left')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('c.diocese_id', $dioceseId)
            ->groupBy('c.id')
            ->get()
            ->getResultArray();

        return $diocese;
    }

    /**
     * Get all dioceses for a province
     */
    public function getDiocesesByProvince($provinceId)
    {
        return $this->where('province_id', $provinceId)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
