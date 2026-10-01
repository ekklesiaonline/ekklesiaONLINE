<?php

namespace App\Models;

use CodeIgniter\Model;

class UserRegistrationRequestModel extends Model
{
    protected $table = 'user_registration_requests';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'user_id',
        'province_id',
        'diocese_id',
        'parish_id',
        'requested_parish_id',
        'status',
        'requested_role',
        'reviewed_by',
        'review_notes',
        'profile_completed_at',
        'reviewed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|is_not_unique[users.id]',
        'province_id' => 'required|is_not_unique[province.id]',
        'diocese_id' => 'permit_empty|is_not_unique[diocese.id]',
        'parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'requested_parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'status' => 'required|in_list[pending_profile,pending_review,approved,dismissed,banned]',
    ];
    protected $cleanValidationRules = true;

    private ?string $resolvedRequestedParishColumn = null;
    private ?string $resolvedProfileTable = null;

    protected function initialize()
    {
        parent::initialize();

        $table = $this->resolveExistingTable();
        if ($table !== null) {
            $this->table = $table;
        }
    }

    private function resolveExistingTable(): ?string
    {
        $candidates = [
            'user_registration_requests',
            'user_registration_request',
        ];

        foreach ($candidates as $unprefixed) {
            if ($this->db->tableExists($unprefixed)) {
                return $unprefixed;
            }
        }

        return null;
    }

    private function requestedParishColumn(): string
    {
        if ($this->resolvedRequestedParishColumn !== null) {
            return $this->resolvedRequestedParishColumn;
        }

        try {
            if ($this->db->fieldExists('requested_parish_id', $this->table)) {
                $this->resolvedRequestedParishColumn = 'requested_parish_id';
                return $this->resolvedRequestedParishColumn;
            }

            if ($this->db->fieldExists('parish_id', $this->table)) {
                $this->resolvedRequestedParishColumn = 'parish_id';
                return $this->resolvedRequestedParishColumn;
            }
        } catch (\Throwable $e) {
            // Fall through to default.
        }

        $this->resolvedRequestedParishColumn = 'requested_parish_id';

        return $this->resolvedRequestedParishColumn;
    }

    private function resolveExistingProfileTable(): ?string
    {
        if ($this->resolvedProfileTable !== null) {
            return $this->resolvedProfileTable;
        }

        $candidates = [
            'profile',
            'profiles',
        ];

        foreach ($candidates as $table) {
            if ($this->db->tableExists($table)) {
                $this->resolvedProfileTable = $table;
                return $this->resolvedProfileTable;
            }
        }

        return null;
    }

    public function getPendingByDiocese(int $dioceseId, int $limit = 20): array
    {
        $requestedParishColumn = $this->requestedParishColumn();
        $profileTable = $this->resolveExistingProfileTable();

        $builder = $this->db->table($this->table . ' urr')
            ->select('urr.*, urr.' . $requestedParishColumn . ' AS requested_parish_id, ai.secret as email, c.name as requested_parish_name')
            ->join('auth_identities ai', 'ai.user_id = urr.user_id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = urr.' . $requestedParishColumn, 'left')
            ->where('urr.diocese_id', $dioceseId)
            ->where('urr.status', 'pending_review')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->orderBy('urr.profile_completed_at', 'DESC')
            ->orderBy('urr.id', 'DESC')
            ->limit($limit);

        if ($profileTable !== null) {
            $builder
                ->select('p.first_name, p.last_name')
                ->join($profileTable . ' p', 'p.user_id = urr.user_id', 'left');
        }

        return $builder->get()->getResultArray();
    }

    public function getPendingByParish(int $parishId, int $limit = 20): array
    {
        $requestedParishColumn = $this->requestedParishColumn();
        $profileTable = $this->resolveExistingProfileTable();

        $builder = $this->db->table($this->table . ' urr')
            ->select('urr.*, urr.' . $requestedParishColumn . ' AS requested_parish_id, ai.secret as email, c.name as requested_parish_name')
            ->join('auth_identities ai', 'ai.user_id = urr.user_id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = urr.' . $requestedParishColumn, 'left')
            ->where('urr.' . $requestedParishColumn, $parishId)
            ->where('urr.status', 'pending_review')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->orderBy('urr.profile_completed_at', 'DESC')
            ->orderBy('urr.id', 'DESC')
            ->limit($limit);

        if ($profileTable !== null) {
            $builder
                ->select('p.first_name, p.last_name')
                ->join($profileTable . ' p', 'p.user_id = urr.user_id', 'left');
        }

        return $builder->get()->getResultArray();
    }

    public function countPendingByParish(int $parishId): int
    {
        $requestedParishColumn = $this->requestedParishColumn();

        return (int) $this->db->table($this->table . ' urr')
            ->where('urr.' . $requestedParishColumn, $parishId)
            ->where('urr.status', 'pending_review')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->countAllResults();
    }

    public function countPendingByDiocese(int $dioceseId): int
    {
        return (int) $this->db->table($this->table . ' urr')
            ->where('urr.diocese_id', $dioceseId)
            ->where('urr.status', 'pending_review')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->countAllResults();
    }

    public function getIncompleteByParish(int $parishId, int $limit = 50): array
    {
        $requestedParishColumn = $this->requestedParishColumn();
        $profileTable = $this->resolveExistingProfileTable();

        $builder = $this->db->table($this->table . ' urr')
            ->select('urr.*, urr.' . $requestedParishColumn . ' AS requested_parish_id, u.username, ai.secret as email, c.name as requested_parish_name')
            ->join('users u', 'u.id = urr.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = urr.user_id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = urr.' . $requestedParishColumn, 'left')
            ->where('urr.' . $requestedParishColumn, $parishId)
            ->where('urr.status', 'pending_profile')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->orderBy('urr.created_at', 'DESC')
            ->orderBy('urr.id', 'DESC')
            ->limit($limit);

        if ($profileTable !== null) {
            $builder
                ->select('p.first_name, p.last_name, p.phone')
                ->join($profileTable . ' p', 'p.user_id = urr.user_id', 'left');
        }

        return $builder->get()->getResultArray();
    }

    public function countIncompleteByParish(int $parishId): int
    {
        $requestedParishColumn = $this->requestedParishColumn();

        return (int) $this->db->table($this->table . ' urr')
            ->where('urr.' . $requestedParishColumn, $parishId)
            ->where('urr.status', 'pending_profile')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->countAllResults();
    }

    public function getIncompleteByDiocese(int $dioceseId, int $limit = 50): array
    {
        $requestedParishColumn = $this->requestedParishColumn();
        $profileTable = $this->resolveExistingProfileTable();

        $builder = $this->db->table($this->table . ' urr')
            ->select('urr.*, urr.' . $requestedParishColumn . ' AS requested_parish_id, u.username, ai.secret as email, c.name as requested_parish_name')
            ->join('users u', 'u.id = urr.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = urr.user_id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = urr.' . $requestedParishColumn, 'left')
            ->where('urr.diocese_id', $dioceseId)
            ->where('urr.status', 'pending_profile')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->orderBy('urr.created_at', 'DESC')
            ->orderBy('urr.id', 'DESC')
            ->limit($limit);

        if ($profileTable !== null) {
            $builder
                ->select('p.first_name, p.last_name, p.phone')
                ->join($profileTable . ' p', 'p.user_id = urr.user_id', 'left');
        }

        return $builder->get()->getResultArray();
    }

    public function countIncompleteByDiocese(int $dioceseId): int
    {
        return (int) $this->db->table($this->table . ' urr')
            ->where('urr.diocese_id', $dioceseId)
            ->where('urr.status', 'pending_profile')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->countAllResults();
    }

    private function excludeTopTierAdminSql(): string
    {
        $authGroupsTable = $this->db->protectIdentifiers('auth_groups_users', true, false);
        $groupCol = $this->db->protectIdentifiers('group', false, true);

        return "NOT EXISTS (SELECT 1 FROM {$authGroupsTable} agu WHERE agu.user_id = urr.user_id AND agu.{$groupCol} IN ('systemadmin', 'provinceadmin', 'dioceseadmin'))";
    }
}
