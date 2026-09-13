<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishSettingModel extends Model
{
    protected $table = 'parish_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;

    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'parish_id',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'parish_id' => 'required|is_natural_no_zero',
        'key' => 'required|min_length[1]|max_length[255]',
        'value' => 'permit_empty',
        'is_encrypted' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Get setting value by parish and key
     */
    public function getSetting(int $parishId, string $key, mixed $default = null): mixed
    {
        $result = $this->where('parish_id', $parishId)
            ->where('key', $key)
            ->first();

        if (!$result) {
            return $default;
        }

        return $result['value'] ?? $default;
    }

    /**
     * Get all settings for a parish
     */
    public function getParishSettings(int $parishId): array
    {
        return $this->where('parish_id', $parishId)->findAll();
    }

    /**
     * Set or update a setting
     */
    public function setSetting(int $parishId, string $key, mixed $value, bool $isEncrypted = false): bool
    {
        $existing = $this->where('parish_id', $parishId)
            ->where('key', $key)
            ->first();

        $data = [
            'parish_id' => $parishId,
            'key' => $key,
            'value' => $value,
            'is_encrypted' => $isEncrypted ? 1 : 0,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data) !== false;
    }

    /**
     * Delete setting by parish and key
     */
    public function deleteSetting(int $parishId, string $key): bool
    {
        return $this->where('parish_id', $parishId)
            ->where('key', $key)
            ->delete();
    }
}
