<?php

namespace App\Services;

use App\Models\DioceseSettingModel;
use App\Models\OrganisationSettingModel;
use App\Models\ProvinceSettingModel;

/**
 * Hierarchical Configuration Service
 * 
 * Manages settings across organisational scopes with fallback:
 * Parish/Diocese → Diocese → Province (if applicable) → Organisation (global)
 */
class ConfigurationService
{
    private OrganisationSettingModel $organisationModel;
    private ProvinceSettingModel $provinceModel;
    private DioceseSettingModel $dioceseModel;

    public function __construct(
        ?OrganisationSettingModel $organisationModel = null,
        ?ProvinceSettingModel $provinceModel = null,
        ?DioceseSettingModel $dioceseModel = null
    ) {
        $this->organisationModel = $organisationModel ?? model(OrganisationSettingModel::class);
        $this->provinceModel = $provinceModel ?? model(ProvinceSettingModel::class);
        $this->dioceseModel = $dioceseModel ?? model(DioceseSettingModel::class);
    }

    /**
     * Get a setting with hierarchical fallback
     * 
     * Lookup order:
     * 1. Diocese-specific setting
     * 2. Province-specific setting (if applicable)
     * 3. Organisation global setting
     */
    public function getSetting(string $key, ?int $dioceseId = null, ?int $provinceId = null, $default = null)
    {
        // Try diocese first
        if ($dioceseId !== null) {
            $value = $this->dioceseModel->getValue($dioceseId, $key);
            if ($value !== null) {
                return $value;
            }
        }

        // Try province next
        if ($provinceId !== null) {
            $value = $this->provinceModel->getValue($provinceId, $key);
            if ($value !== null) {
                return $value;
            }
        }

        // Fall back to organisation global
        $value = $this->organisationModel->getValue($key);
        if ($value !== null) {
            return $value;
        }

        return $default;
    }

    /**
     * Get all settings with hierarchical merge
     * Diocese settings override province, province override organisation
     */
    public function getAllSettings(?int $dioceseId = null, ?int $provinceId = null): array
    {
        $merged = [];

        // Start with organisation settings
        $merged = array_merge($merged, $this->organisationModel->getAll());

        // Merge province settings (override organisation)
        if ($provinceId !== null) {
            $merged = array_merge($merged, $this->provinceModel->getAll($provinceId));
        }

        // Merge diocese settings (override all)
        if ($dioceseId !== null) {
            $merged = array_merge($merged, $this->dioceseModel->getAll($dioceseId));
        }

        return $merged;
    }

    /**
     * Set a diocese setting
     */
    public function setDioceseSetting(int $dioceseId, string $key, $value, string $dataType = 'string', string $description = null): bool
    {
        return $this->dioceseModel->setValue($dioceseId, $key, $value, $dataType, $description);
    }

    /**
     * Set a province setting
     */
    public function setProvinceSetting(int $provinceId, string $key, $value, string $dataType = 'string', string $description = null): bool
    {
        return $this->provinceModel->setValue($provinceId, $key, $value, $dataType, $description);
    }

    /**
     * Set a global/organisation setting
     */
    public function setOrganisationSetting(string $key, $value, string $dataType = 'string', string $category = null, string $description = null): bool
    {
        return $this->organisationModel->setValue($key, $value, $dataType, $category, $description);
    }

    /**
     * Check if a global setting is locked (prevents override at lower levels)
     */
    public function isSettingLocked(string $key): bool
    {
        return $this->organisationModel->isLocked($key);
    }

    /**
     * Lock a global setting to prevent diocese/province overrides
     */
    public function lockSetting(string $key): bool
    {
        return $this->organisationModel->lockSetting($key);
    }

    /**
     * Unlock a global setting
     */
    public function unlockSetting(string $key): bool
    {
        return $this->organisationModel->unlockSetting($key);
    }

    /**
     * Get settings by category (global settings only)
     */
    public function getSettingsByCategory(string $category): array
    {
        return $this->organisationModel->getByCategory($category);
    }

    /**
     * Get all organisation-level settings with their metadata
     */
    public function getAllOrganisationSettings(): array
    {
        return $this->organisationModel->findAll();
    }

    /**
     * Get all diocese-level settings
     */
    public function getAllDioceseSettings(int $dioceseId): array
    {
        return $this->dioceseModel->findAll();
    }

    /**
     * Get all province-level settings
     */
    public function getAllProvinceSettings(int $provinceId): array
    {
        return $this->provinceModel->findAll();
    }
}
