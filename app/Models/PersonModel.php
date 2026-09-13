<?php

namespace App\Models;

use App\Services\PersonContactService;
use CodeIgniter\Model;

class PersonModel extends Model
{
    protected $table            = 'person';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'first_name',
        'last_name',
        'email',
        'email_hash',
        'phone',
        'phone_hash',
        'birthdate',
        'gender',
        'address',
        'preferred_contact_method',
        'preferred_timezone',
        'opted_out_channels',
        'source',
        'is_active',
    ];

    protected $beforeInsert = ['prepareContactFields'];
    protected $beforeUpdate = ['prepareContactFields'];
    protected $afterFind    = ['decryptContactFields'];

    protected function prepareContactFields(array $payload): array
    {
        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return $payload;
        }

        $data = $payload['data'];

        if (isset($data['email'])) {
            $plain = (string) $data['email'];
            $data['email_hash'] = PersonContactService::hash($plain);
            $data['email']      = PersonContactService::encrypt($plain) ?? $plain;
        }

        if (isset($data['phone'])) {
            $plain = (string) $data['phone'];
            $data['phone_hash'] = PersonContactService::hash($plain);
            $data['phone']      = PersonContactService::encrypt($plain) ?? $plain;
        }

        if (isset($data['first_name'])) {
            $data['first_name'] = trim((string) $data['first_name']);
        }

        if (isset($data['last_name'])) {
            $data['last_name'] = trim((string) $data['last_name']);
        }

        $data['source']   ??= 'system';
        $data['is_active'] ??= 1;

        $payload['data'] = $data;

        return $payload;
    }

    protected function decryptContactFields(array $payload): array
    {
        if (! isset($payload['data'])) {
            return $payload;
        }

        if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
            $payload['data'] = array_map(fn (array $row): array => $this->decryptPersonRow($row), $payload['data']);

            return $payload;
        }

        if (is_array($payload['data'])) {
            $payload['data'] = $this->decryptPersonRow($payload['data']);
        }

        return $payload;
    }

    private function decryptPersonRow(array $row): array
    {
        if (array_key_exists('email', $row)) {
            $row['email'] = PersonContactService::decrypt((string) ($row['email'] ?? ''));
        }

        if (array_key_exists('phone', $row)) {
            $row['phone'] = PersonContactService::decrypt((string) ($row['phone'] ?? ''));
        }

        return $row;
    }

    public function findByEmailHash(string $hash): ?array
    {
        return $this->where('email_hash', $hash)->first();
    }

    public function findByPhoneHash(string $hash): ?array
    {
        return $this->where('phone_hash', $hash)->first();
    }

    /**
     * @return list<array>
     */
    public function findByParish(int $parishId, ?int $limit = null, int $offset = 0): array
    {
        return $this->select('person.*')
            ->join('parish_affiliation', 'parish_affiliation.person_id = person.id', 'INNER')
            ->where('parish_affiliation.parish_id', $parishId)
            ->findAll($limit, $offset);
    }
}
