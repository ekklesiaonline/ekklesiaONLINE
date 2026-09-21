<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class ProvinceSettingModel extends Model
{
    protected $table = 'province_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['province_id', 'key', 'value', 'is_encrypted'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'province_id'  => 'required|numeric',
        'key'          => 'required|string|max_length[255]',
        'value'        => 'permit_empty',
        'is_encrypted' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Get a setting value with optional decryption.
     */
    public function getValue(int $provinceId, string $key, $default = null)
    {
        $row = $this->where('province_id', $provinceId)
            ->where('key', $key)
            ->first();

        if (!$row) {
            return $default;
        }

        return $this->castStoredValue($row);
    }

    /**
     * Set or update a setting value.
     */
    public function setValue(int $provinceId, string $key, $value, bool $isEncrypted = false): bool
    {
        $existing = $this->where('province_id', $provinceId)
            ->where('key', $key)
            ->first();

        $data = [
            'province_id'   => $provinceId,
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
     * Get all settings for a province.
     */
    public function getAll(int $provinceId): array
    {
        $rows = $this->where('province_id', $provinceId)->findAll();
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
                log_message('error', '[ProvinceSettingModel] Failed to decrypt key {key}: {message}', [
                    'key'     => $row['key'],
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $value;
    }
}
