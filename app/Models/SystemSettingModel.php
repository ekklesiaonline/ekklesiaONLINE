<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class SystemSettingModel extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'data_type',
        'category',
        'description',
        'is_locked',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'setting_key' => 'required|max_length[100]',
        'data_type' => 'required|in_list[string,integer,boolean,json,text]',
    ];

    public function getValue(string $key, mixed $default = null): mixed
    {
        $row = $this->where('setting_key', $key)->first();

        if (!$row) {
            return $default;
        }

        return $this->castValue($row['setting_value'], $row['data_type']);
    }

    public function setValue(string $key, mixed $value, string $dataType = 'string', ?string $category = null, ?string $description = null): bool
    {
        $existing = $this->where('setting_key', $key)->first();

        $data = [
            'setting_key' => $key,
            'setting_value' => $this->encodeValue($value, $dataType),
            'data_type' => $dataType,
            'category' => $category,
            'description' => $description,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data) !== false;
    }

    public function getByCategory(string $category): array
    {
        $rows = $this->where('category', $category)->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['data_type']);
        }

        return $settings;
    }

    public function getAll(): array
    {
        $rows = $this->orderBy('category', 'ASC')->orderBy('setting_key', 'ASC')->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['data_type']),
                'data_type' => $row['data_type'],
                'category' => $row['category'],
                'description' => $row['description'],
                'is_locked' => (bool) $row['is_locked'],
            ];
        }

        return $settings;
    }

    public function getGroupedByCategory(): array
    {
        $rows = $this->orderBy('category', 'ASC')->orderBy('setting_key', 'ASC')->findAll();
        $grouped = [];
        foreach ($rows as $row) {
            $category = $row['category'] ?? 'general';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['data_type']),
                'data_type' => $row['data_type'],
                'description' => $row['description'],
                'is_locked' => (bool) $row['is_locked'],
            ];
        }

        return $grouped;
    }

    public function isLocked(string $key): bool
    {
        $row = $this->where('setting_key', $key)->first();

        return $row ? (bool) $row['is_locked'] : false;
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && DataEncryption::isEncrypted($value)) {
            $value = DataEncryption::decrypt($value);
            if ($value === null) {
                return null;
            }
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'json' => json_decode((string) $value, true),
            'text', 'string' => (string) $value,
            default => $value,
        };
    }

    private function encodeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
