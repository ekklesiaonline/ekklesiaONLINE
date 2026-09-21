<?php

namespace App\Models;

use CodeIgniter\Model;

class DioceseSettingModel extends Model
{
    protected $table = 'diocese_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['diocese_id', 'key', 'value', 'is_encrypted'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'diocese_id' => 'required|numeric',
        'key'        => 'required|string|max_length[255]',
        'value'      => 'permit_empty',
        'is_encrypted' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Get a setting value with optional decryption.
     */
    public function getValue(int $dioceseId, string $key, $default = null)
    {
        $row = $this->where('diocese_id', $dioceseId)
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
    public function setValue(int $dioceseId, string $key, $value, bool $isEncrypted = false): bool
    {
        $existing = $this->where('diocese_id', $dioceseId)
            ->where('key', $key)
            ->first();

        $data = [
            'diocese_id'   => $dioceseId,
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
     * Get all settings for a diocese.
     */
    public function getAll(int $dioceseId): array
    {
        $rows = $this->where('diocese_id', $dioceseId)->findAll();
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
            try {
                $value = service('encrypter')->decrypt($value);
            } catch (\Exception $e) {
                log_message('error', '[DioceseSettingModel] Failed to decrypt key {key}: {message}', [
                    'key'     => $row['key'],
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $value;
    }
}
