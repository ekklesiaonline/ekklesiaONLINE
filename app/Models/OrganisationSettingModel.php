<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class OrganisationSettingModel extends Model
{
    protected $table = 'organisation_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['key', 'value', 'is_encrypted'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'key'          => 'required|string|max_length[255]',
        'value'        => 'permit_empty',
        'is_encrypted' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Get a global setting value with optional decryption.
     */
    public function getValue(string $key, $default = null)
    {
        $row = $this->where('key', $key)->first();

        if (!$row) {
            return $default;
        }

        return $this->castStoredValue($row);
    }

    /**
     * Set or update a global setting value.
     */
    public function setValue(string $key, $value, bool $isEncrypted = false): bool
    {
        $existing = $this->where('key', $key)->first();

        $data = [
            'key'          => $key,
            'value'        => $value,
            'is_encrypted' => $isEncrypted ? 1 : 0,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data) !== false;
    }

    /**
     * Get all global settings.
     */
    public function getAll(): array
    {
        $rows = $this->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $this->castStoredValue($row);
        }

        return $settings;
    }

    /**
     * Cast/decrypt a stored row's value.
     */
    private function castStoredValue(array $row)
    {
        $value = $row['value'];

        if (! empty($row['is_encrypted']) && $value !== null && $value !== '') {
            if (DataEncryption::isEncrypted((string) $value)) {
                return DataEncryption::decrypt((string) $value);
            }

            try {
                return service('encrypter')->decrypt($value);
            } catch (\Exception $e) {
                log_message('error', '[OrganisationSettingModel] Failed to decrypt key {key}: {message}', [
                    'key'     => $row['key'],
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $value;
    }
}
