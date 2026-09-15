<?php

namespace App\Models;

use CodeIgniter\Model;

class UserRegistrationRequestModel extends Model
{
    protected $table = 'user_registration_request';
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
        'requested_parish_id',
        'parish_id',
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
        'requested_parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'status' => 'required|in_list[pending_profile,pending_review,approved,dismissed,banned]',
    ];

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
            $this->db->getPrefix() . 'user_registration_request',
            'user_registration_request',
            $this->db->getPrefix() . 'user_registration_requests',
            'user_registration_requests',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

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

        $this->resolvedRequestedParishColumn = 'parish_id';

        return $this->resolvedRequestedParishColumn;
    }

    private function resolveExistingProfileTable(): ?string
    {
        if ($this->resolvedProfileTable !== null) {
            return $this->resolvedProfileTable;
        }

        $candidates = [
            $this->db->getPrefix() . 'profile',
            'profile',
            $this->db->getPrefix() . 'profiles',
            'profiles',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    $this->resolvedProfileTable = $table;
                    return $this->resolvedProfileTable;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
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

    public function countPendingByDiocese(int $dioceseId): int
    {
        return (int) $this->db->table($this->table . ' urr')
            ->where('urr.diocese_id', $dioceseId)
            ->where('urr.status', 'pending_review')
            ->where($this->excludeTopTierAdminSql(), null, false)
            ->countAllResults();
    }

    private function excludeTopTierAdminSql(): string
    {
        $authGroupsTable = $this->db->protectIdentifiers('auth_groups_users', true, false);

        return "NOT EXISTS (SELECT 1 FROM {$authGroupsTable} agu WHERE agu.user_id = urr.user_id AND agu.group IN ('systemadmin', 'provinceadmin', 'dioceseadmin'))";
    }
}
