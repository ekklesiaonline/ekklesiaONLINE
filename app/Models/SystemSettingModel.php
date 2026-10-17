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

    /**
     * Request-level value cache. Settings change rarely, and this model is
     * usually a shared instance per request, so memoizing reads removes
     * repeated round-trips for hot keys (e.g. maintenance mode, password policy).
     */
    private array $valueCache = [];
    private ?array $allCache = null;
    private array $categoryCache = [];
    private array $lockCache = [];

    public function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->valueCache)) {
            $value = $this->valueCache[$key];

            return $value['exists'] ? $value['value'] : $default;
        }

        $row = $this->where('setting_key', $key)->first();

        if (!$row) {
            $this->valueCache[$key] = ['exists' => false, 'value' => null];

            return $default;
        }

        $value = $this->castValue($row['setting_value'], $row['data_type']);
        $this->valueCache[$key] = ['exists' => true, 'value' => $value];

        return $value;
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
            $result = $this->update($existing['id'], $data);
        } else {
            $result = $this->insert($data) !== false;
        }

        $this->clearCache($key);

        return $result;
    }

    public function getByCategory(string $category): array
    {
        if (isset($this->categoryCache[$category])) {
            return $this->categoryCache[$category];
        }

        $rows = $this->where('category', $category)->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['data_type']);
        }

        $this->categoryCache[$category] = $settings;

        return $settings;
    }

    public function getAll(): array
    {
        if ($this->allCache !== null) {
            return $this->allCache;
        }

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

        $this->allCache = $settings;

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
        if (array_key_exists($key, $this->lockCache)) {
            return $this->lockCache[$key];
        }

        $row = $this->where('setting_key', $key)->first();
        $locked = $row ? (bool) $row['is_locked'] : false;
        $this->lockCache[$key] = $locked;

        return $locked;
    }

    private function clearCache(?string $key = null): void
    {
        $this->allCache = null;
        $this->categoryCache = [];
        $this->lockCache = [];

        if ($key !== null) {
            unset($this->valueCache[$key]);

            return;
        }

        $this->valueCache = [];
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
