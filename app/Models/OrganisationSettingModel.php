<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganisationSettingModel extends Model
{
    protected $table = 'organisation_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['setting_key', 'setting_value', 'data_type', 'category', 'description', 'is_locked'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'setting_key' => 'required|string|max_length[100]',
        'data_type' => 'required|in_list[string,integer,boolean,json,text]',
    ];

    /**
     * Get a global setting value with type casting
     */
    public function getValue(string $key, $default = null)
    {
        $row = $this->where('setting_key', $key)->first();

        if (!$row) {
            return $default;
        }

        return $this->castValue($row['setting_value'], $row['data_type']);
    }

    /**
     * Set a global setting value
     */
    public function setValue(string $key, $value, string $dataType = 'string', string $category = null, string $description = null, bool $isLocked = false): bool
    {
        $existing = $this->where('setting_key', $key)->first();

        $data = [
            'setting_key' => $key,
            'setting_value' => $this->encodeValue($value, $dataType),
            'data_type' => $dataType,
            'category' => $category,
            'description' => $description ?? $existing['description'] ?? null,
            'is_locked' => $isLocked ? 1 : 0,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Get all settings for a category
     */
    public function getByCategory(string $category): array
    {
        $rows = $this->where('category', $category)->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['data_type']);
        }
        return $settings;
    }

    /**
     * Get all global settings
     */
    public function getAll(): array
    {
        $rows = $this->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['data_type']);
        }
        return $settings;
    }

    /**
     * Check if setting is locked
     */
    public function isLocked(string $key): bool
    {
        $row = $this->where('setting_key', $key)->first();
        return $row ? (bool) $row['is_locked'] : false;
    }

    /**
     * Lock a setting to prevent lower-level overrides
     */
    public function lockSetting(string $key): bool
    {
        return $this->where('setting_key', $key)->set(['is_locked' => 1])->update();
    }

    /**
     * Unlock a setting
     */
    public function unlockSetting(string $key): bool
    {
        return $this->where('setting_key', $key)->set(['is_locked' => 0])->update();
    }

    private function castValue($value, string $type)
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'json' => json_decode($value, true),
            'text', 'string' => (string) $value,
            default => $value,
        };
    }

    private function encodeValue($value, string $type): string
    {
        return match ($type) {
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
