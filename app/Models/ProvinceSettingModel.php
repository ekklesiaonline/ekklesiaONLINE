<?php

namespace App\Models;

use CodeIgniter\Model;

class ProvinceSettingModel extends Model
{
    protected $table = 'province_setting';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['province_id', 'setting_key', 'setting_value', 'data_type', 'description'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'province_id' => 'required|numeric',
        'setting_key' => 'required|string|max_length[100]',
        'data_type' => 'required|in_list[string,integer,boolean,json,text]',
    ];

    /**
     * Get a setting value with type casting
     */
    public function getValue(int $provinceId, string $key, $default = null)
    {
        $row = $this->where('province_id', $provinceId)
            ->where('setting_key', $key)
            ->first();

        if (!$row) {
            return $default;
        }

        return $this->castValue($row['setting_value'], $row['data_type']);
    }

    /**
     * Set a setting value
     */
    public function setValue(int $provinceId, string $key, $value, string $dataType = 'string', string $description = null): bool
    {
        $existing = $this->where('province_id', $provinceId)
            ->where('setting_key', $key)
            ->first();

        $data = [
            'province_id' => $provinceId,
            'setting_key' => $key,
            'setting_value' => $this->encodeValue($value, $dataType),
            'data_type' => $dataType,
            'description' => $description ?? $existing['description'] ?? null,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Get all settings for a province
     */
    public function getAll(int $provinceId): array
    {
        $rows = $this->where('province_id', $provinceId)->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['data_type']);
        }
        return $settings;
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
