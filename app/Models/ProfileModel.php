<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use App\Services\MembershipStatusService;

class ProfileModel extends BaseTenantModel
{
    protected $table            = 'profile';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    // Profiles are one-per-user, not parish-scoped. A parish admin/province admin
    // may not yet have a parish_id, and scoping by parish_id would hide their own row.
    protected bool $tenantScoped = false;

    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'person_id',
        'parish_id',
        'province_id',
        'diocese_id',
        'profile_picture',
        'first_name',
        'middle_name',
        'nickname',
        'last_name',
        'phone',
        'phone_country_code',
        'phone_hash',
        'address',
        'address_line_2',
        'city',
        'state',
        'zip',
        'country',
        'social_media',
        'birth_date',
        'baptism_date',
        'confirmation_date',
        'is_confirmed',
        'membership_status',
        'is_dependent',
        'portal_access',
        'portal_enabled_at',
        'spiritual_gifts',
        'locale',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'user_id'    => 'required|is_unique[profile.user_id,id,{id}]',
        'parish_id'  => 'permit_empty|is_not_unique[parish.id]',
        'province_id' => 'permit_empty|is_not_unique[province.id]',
        'diocese_id' => 'permit_empty|is_not_unique[diocese.id]',
        'first_name' => 'permit_empty|max_length[100]',
        'nickname'   => 'permit_empty|max_length[100]',
        'last_name'  => 'permit_empty|max_length[100]',
        'phone'      => 'permit_empty|max_length[25]',
        'phone_country_code' => 'permit_empty|valid_calling_code',
        'address'    => 'permit_empty|string',
        'address_line_2' => 'permit_empty|string',
        'city'       => 'permit_empty|max_length[100]',
        'state'      => 'permit_empty|max_length[100]',
        'zip'        => 'permit_empty|max_length[20]',
        'country'    => 'permit_empty|valid_country',
        'email'      => 'permit_empty|valid_email',
        'locale'     => 'permit_empty|in_list[en,es,fr]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['encryptSensitiveFields', 'filterUnavailableColumns'];
    protected $beforeUpdate = ['encryptSensitiveFields', 'filterUnavailableColumns'];
    protected $afterFind    = ['decryptSensitiveFields'];

    protected function initialize()
    {
        parent::initialize();

        $table = $this->resolveExistingProfileTable();
        if ($table !== null) {
            $this->table = $table;
        }
    }

    /**
     * Get profile with user data. Optionally restrict to profiles whose user
     * has an active affiliation with the given parish.
     */
    public function getProfileWithUser($profileId, ?int $parishId = null, bool $activeOnly = true)
    {
        $parishId ??= $this->currentParishId();

        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null || $parishId <= 0) {
            return null;
        }

        $builder = $this->db->table($profileTable . ' p')
            ->select('p.*, u.username, u.active, ai.secret as email, ai.force_reset')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('p.id', $profileId)
            ->where('p.parish_id', $parishId)
            ->where('p.deleted_at', null)
            ->join('parish_affiliation pa', 'pa.linked_user_id = p.user_id', 'inner')
            ->where('pa.parish_id', $parishId);

        if ($activeOnly) {
            $builder->where('pa.is_member', 1);
        }

        $row = $builder->get()->getRowArray();

        return $row ? $this->decryptProfileRow($row) : null;
    }

    /**
     * Get all profile for a parish with user data
     */
    public function getParishProfiles($parishId, $status = null)
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.*, u.username, u.active, ai.secret as email, ai.force_reset, mca.membership_status as affiliation_status, mca.is_primary')
            ->join($profileTable . ' p', 'p.user_id = mca.linked_user_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('p.deleted_at', null)
            ->where('agu.id IS NULL'); // Exclude province/diocesan admin users

        if ($status) {
            $builder->where('mca.membership_status', $status);
        }

        $rows = $builder->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn(array $row): array => $this->decryptProfileRow($row), $rows);
    }

    /**
     * Get members by status count
     */
    public function getStatusCounts($parishId)
    {
        $rows = $this->db->table('parish_affiliation mca')
            ->select('mca.membership_status, COUNT(*) as count')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL') // Exclude province/diocesan admin users
            ->groupBy('mca.membership_status')
            ->get()
            ->getResultArray();

        return $this->normalizeStatusCountRows($rows);
    }

    /**
     * Get member count for a parish, excluding province/diocesan admin users.
     */
    public function getParishMemberCount($parishId, ?string $status = null): int
    {
        $builder = $this->db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.linked_user_id) as count')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL'); // Exclude province/diocesan admin users

        if ($status !== null) {
            $builder->where('mca.membership_status', $status);
        }

        $row = $builder->get()->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    /**
     * Search members
     */
    public function searchMembers($parishId, $keyword)
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.*, u.username, ai.secret as email, ai.force_reset, mca.membership_status as affiliation_status, mca.is_primary')
            ->join($profileTable . ' p', 'p.user_id = mca.linked_user_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('p.deleted_at', null)
            ->where('agu.id IS NULL') // Exclude province/diocesan admin users
            ->limit(25);

        $phoneHash = DataEncryption::phoneIndex($keyword);
        $builder->groupStart()
            ->like('p.first_name', $keyword)
            ->orLike('p.last_name', $keyword)
            ->orLike('ai.secret', $keyword);

        if ($this->hasProfileColumn('nickname')) {
            $builder->orLike('p.nickname', $keyword);
        }

        if ($phoneHash !== null) {
            $builder->orWhere('p.phone_hash', $phoneHash);
        }

        $builder->groupEnd();

        $rows = $builder
            ->get()
            ->getResultArray();

        $rows = array_map(fn(array $row): array => $this->decryptProfileRow($row), $rows);

        if ($phoneHash === null) {
            $needle = mb_strtolower(trim((string) $keyword));
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $phone = mb_strtolower((string) ($row['phone'] ?? ''));
                if ($needle === '' || $phone === '') {
                    return true;
                }

                return str_contains($phone, $needle)
                    || str_contains(($phone !== '' ? preg_replace('/\D+/', '', $phone) : ''), preg_replace('/\D+/', '', $needle));
            }));
        }

        return array_slice($rows, 0, 10);
    }

    protected function encryptSensitiveFields(array $payload): array
    {
        $fields = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if ($fields === []) {
            return $payload;
        }

        if (array_key_exists('phone', $fields)) {
            $plainPhone = $fields['phone'] !== null ? (string) $fields['phone'] : null;
            $fields['phone_hash'] = DataEncryption::phoneIndex($plainPhone);
            $fields['phone'] = DataEncryption::encrypt($plainPhone);
        }

        if (array_key_exists('address', $fields)) {
            $plainAddress = $fields['address'] !== null ? (string) $fields['address'] : null;
            $fields['address'] = DataEncryption::encrypt($plainAddress);
        }

        if (array_key_exists('address_line_2', $fields)) {
            $plainAddress2 = $fields['address_line_2'] !== null ? (string) $fields['address_line_2'] : null;
            $fields['address_line_2'] = DataEncryption::encrypt($plainAddress2);
        }

        $payload['data'] = $fields;

        return $payload;
    }

    protected function decryptSensitiveFields(array $payload): array
    {
        if (!isset($payload['data'])) {
            return $payload;
        }

        if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
            $payload['data'] = array_map(fn(array $row): array => $this->decryptProfileRow($row), $payload['data']);
            return $payload;
        }

        if (is_array($payload['data'])) {
            $payload['data'] = $this->decryptProfileRow($payload['data']);
        }

        return $payload;
    }

    protected function filterUnavailableColumns(array $payload): array
    {
        $fields = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if ($fields === []) {
            return $payload;
        }

        $availableColumns = $this->getProfileColumns();
        if ($availableColumns === []) {
            return $payload;
        }

        foreach (array_keys($fields) as $field) {
            if (!in_array(strtolower((string) $field), $availableColumns, true)) {
                unset($fields[$field]);
            }
        }

        $payload['data'] = $fields;

        return $payload;
    }

    private function decryptProfileRow(array $row): array
    {
        if (array_key_exists('phone', $row)) {
            $row['phone'] = DataEncryption::decrypt($row['phone']);
        }
        if (array_key_exists('address', $row)) {
            $row['address'] = DataEncryption::decrypt($row['address']);
        }
        if (array_key_exists('address_line_2', $row)) {
            $row['address_line_2'] = DataEncryption::decrypt($row['address_line_2']);
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStatusCountRows(array $rows): array
    {
        $bucket = [];
        $validStatuses = MembershipStatusService::affiliationStatuses();

        foreach ($rows as $row) {
            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));
            if (!in_array($status, $validStatuses, true)) {
                $status = MembershipStatusService::profileToAffiliation($status);
            }
            if (!in_array($status, $validStatuses, true)) {
                $status = 'visitor';
            }

            $bucket[$status] = (int) ($bucket[$status] ?? 0) + (int) ($row['count'] ?? 0);
        }

        $normalized = [];
        foreach ($bucket as $status => $count) {
            $normalized[] = [
                'membership_status' => $status,
                'count' => $count,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    private function getProfileColumns(): array
    {
        $table = $this->resolveExistingProfileTable();
        if ($table === null) {
            return [];
        }

        try {
            $this->db->resetDataCache();

            return array_map('strtolower', $this->db->getFieldNames($table));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasProfileColumn(string $column): bool
    {
        return in_array(strtolower($column), $this->getProfileColumns(), true);
    }

    private function resolveExistingProfileTable(): ?string
    {
        $candidates = [
            $this->db->getPrefix() . 'profile',
            'profile',
            $this->db->getPrefix() . 'profiles',
            'profiles',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $this->db->resetDataCache();
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Table doesn't exist.
            }
        }

        return null;
    }

    /**
     * Get member status counts across all parishes (Province Admin)
     */
    public function getAllParishesStatusCounts()
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        $rows = $this->db->table($profileTable . ' p')
            ->select('p.membership_status, COUNT(*) as count')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('agu.id IS NULL') // Exclude province/diocesan admin users
            ->groupBy('p.membership_status')
            ->get()
            ->getResultArray();

        return $this->normalizeStatusCountRows($rows);
    }

    /**
     * Get member counts by parish (Province Admin)
     */
    public function getMemberCountsByParish()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return [];
        }

        return $this->db->table('parish_affiliation pa')
            ->select('c.id as parish_id, c.name as parish_name,
                      COUNT(DISTINCT pa.person_id) as total_members,
                      COUNT(DISTINCT CASE WHEN pa.is_member = 1 AND pa.membership_status = "member" THEN pa.person_id END) as active_members,
                      COUNT(DISTINCT CASE WHEN pa.membership_status IN ("inactive", "transferred", "deceased") THEN pa.person_id END) as inactive_members,
                      COUNT(DISTINCT CASE WHEN pa.membership_status IN ("visitor", "regular_attender") THEN pa.person_id END) as visitors,
                      0 as pending_members,
                      COUNT(DISTINCT CASE WHEN pa.membership_status = "regular_attender" THEN pa.person_id END) as regular_attenders', false)
            ->join('parish c', 'c.id = pa.parish_id')
            ->where('pa.person_id IS NOT NULL')
            ->where('pa.deleted_at', null)
            ->groupBy('c.id')
            ->orderBy('total_members', 'DESC')
            ->get()
            ->getResultArray();
    }

}
