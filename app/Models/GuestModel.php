<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class GuestModel extends Model
{
    protected $table            = 'guest';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'person_id',
        'first_name',
        'last_name',
        'email',
        'email_hash',
        'phone',
        'phone_hash',
        'address',
        'preferred_contact_method',
        'source',
        'interests',
        'opted_out_channels',
        'preferred_timezone',
        'visit_date',
        'service_attended',
        'followed_up',
        'converted_to_member_at',
        'converted_user_id',
        'converted_by_user_id',
        'notes',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id'                => 'required|is_not_unique[parish.id]',
        'first_name'               => 'required|min_length[2]|max_length[100]',
        'last_name'                => 'required|min_length[2]|max_length[100]',
        'email'                    => 'permit_empty|valid_email',
        'visit_date'               => 'required|valid_date',
        'preferred_contact_method' => 'permit_empty|in_list[email,text,call,mail]',
        'opted_out_channels'       => 'permit_empty',
        'preferred_timezone'       => 'permit_empty|max_length[50]',
        'source'                   => 'permit_empty|max_length[100]',
        'converted_user_id'        => 'permit_empty|is_not_unique[users.id]',
        'converted_by_user_id'     => 'permit_empty|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['encryptSensitiveFields'];
    protected $beforeUpdate = ['encryptSensitiveFields'];
    protected $afterFind    = ['decryptSensitiveFields'];

    /**
     * Get parish guests
     */
    public function getParishGuests($parishId, $followedUp = null, ?bool $converted = null)
    {
        $builder = $this->where('parish_id', $parishId);

        if ($followedUp !== null) {
            $builder->where('followed_up', $followedUp);
        }

        if ($converted === true) {
            $builder->where('converted_to_member_at IS NOT NULL', null, false);
        } elseif ($converted === false) {
            $builder->where('converted_to_member_at IS NULL', null, false);
        }

        return $builder->orderBy('visit_date', 'DESC')->findAll();
    }

    /**
     * Get guest with follow-up tasks
     */
    public function getGuestWithTasks($guestId)
    {
        $guest = $this->find($guestId);
        if (!$guest) {
            return null;
        }

        $tasks = $this->db->table('call_task ct')
            ->select('ct.*, p.first_name, p.last_name')
            ->join('profile p', 'p.user_id = ct.assigned_to', 'left')
            ->where('ct.guest_id', $guestId)
            ->orderBy('ct.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();

        $guest['tasks'] = $tasks;
        return $guest;
    }

    /**
     * Get recent guests
     */
    public function getRecentGuests($parishId, $limit = 10)
    {
        return $this->where('parish_id', $parishId)
            ->orderBy('visit_date', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function emailSearchHash(?string $email): ?string
    {
        return DataEncryption::emailIndex($email);
    }

    public function phoneSearchHash(?string $phone): ?string
    {
        return DataEncryption::phoneIndex($phone);
    }

    public function decryptGuestRow(array $row): array
    {
        if (array_key_exists('email', $row)) {
            $row['email'] = DataEncryption::decrypt($row['email']);
        }
        if (array_key_exists('phone', $row)) {
            $row['phone'] = DataEncryption::decrypt($row['phone']);
        }

        return $row;
    }

    public function getParishGuestCount(int $parishId, bool $excludeConverted = true): int
    {
        $builder = $this->where('parish_id', $parishId);

        if ($excludeConverted) {
            $builder->where('converted_to_member_at IS NULL', null, false);
        }

        return $builder->countAllResults();
    }

    public function markConvertedToMember(int $guestId, int $userId, ?int $convertedByUserId = null): bool
    {
        return $this->update($guestId, [
            'converted_to_member_at' => date('Y-m-d H:i:s'),
            'converted_user_id' => $userId,
            'converted_by_user_id' => $convertedByUserId,
        ]);
    }

    /**
     * Check whether a guest has opted out of a specific communication channel.
     *
     * @param  array  $guest   Decoded guest row (afterFind hook has already run)
     * @param  string $channel One of: email, text, call, mail
     * @return bool
     */
    public function isOptedOut(array $guest, string $channel): bool
    {
        $raw = $guest['opted_out_channels'] ?? null;
        if ($raw === null || $raw === '' || $raw === '[]') {
            return false;
        }

        // May already be decoded to array by a previous JSON pass.
        $channels = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (!is_array($channels)) {
            return false;
        }

        return in_array($channel, $channels, true);
    }

    /**
     * Find guests whose most recent visit falls within a date range.
     *
     * @param  int    $parishId
     * @param  string $dateFrom  Y-m-d
     * @param  string $dateTo    Y-m-d
     * @return array
     */
    public function findByVisitDateRange(int $parishId, string $dateFrom, string $dateTo): array
    {
        return $this->where('parish_id', $parishId)
            ->where('visit_date >=', $dateFrom)
            ->where('visit_date <=', $dateTo)
            ->orderBy('visit_date', 'DESC')
            ->findAll();
    }

    /**
     * Find guests by source (e.g., "connection card").
     */
    public function findBySource(int $parishId, string $source): array
    {
        return $this->where('parish_id', $parishId)
            ->where('source', $source)
            ->orderBy('visit_date', 'DESC')
            ->findAll();
    }

    protected function encryptSensitiveFields(array $payload): array
    {
        $fields = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if ($fields === []) {
            return $payload;
        }

        if (array_key_exists('email', $fields)) {
            $plainEmail = $fields['email'] !== null ? (string) $fields['email'] : null;
            $fields['email_hash'] = DataEncryption::emailIndex($plainEmail);
            $fields['email'] = DataEncryption::encrypt($plainEmail);
        }

        if (array_key_exists('phone', $fields)) {
            $plainPhone = $fields['phone'] !== null ? (string) $fields['phone'] : null;
            $fields['phone_hash'] = DataEncryption::phoneIndex($plainPhone);
            $fields['phone'] = DataEncryption::encrypt($plainPhone);
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
            $payload['data'] = array_map(fn(array $row): array => $this->decryptGuestRow($row), $payload['data']);
            return $payload;
        }

        if (is_array($payload['data'])) {
            $payload['data'] = $this->decryptGuestRow($payload['data']);
        }

        return $payload;
    }
}
