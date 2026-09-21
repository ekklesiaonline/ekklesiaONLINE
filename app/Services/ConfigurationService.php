<?php

namespace App\Services;

use App\Models\DioceseSettingModel;
use App\Models\OrganisationSettingModel;
use App\Models\ParishSettingModel;
use App\Models\ProvinceSettingModel;
use App\Models\SystemSettingModel;

/**
 * Hierarchical Configuration Service
 *
 * Manages settings across organisational scopes with fallback:
 * Parish → Diocese → Province → Organisation → System → caller-supplied default
 */
class ConfigurationService
{
    private ParishSettingModel      $parishModel;
    private DioceseSettingModel     $dioceseModel;
    private ProvinceSettingModel    $provinceModel;
    private OrganisationSettingModel $organisationModel;
    private SystemSettingModel      $systemModel;

    public function __construct(
        ?ParishSettingModel $parishModel = null,
        ?DioceseSettingModel $dioceseModel = null,
        ?ProvinceSettingModel $provinceModel = null,
        ?OrganisationSettingModel $organisationModel = null,
        ?SystemSettingModel $systemModel = null
    ) {
        $this->parishModel      = $parishModel      ?? model(ParishSettingModel::class);
        $this->dioceseModel     = $dioceseModel     ?? model(DioceseSettingModel::class);
        $this->provinceModel    = $provinceModel    ?? model(ProvinceSettingModel::class);
        $this->organisationModel = $organisationModel ?? model(OrganisationSettingModel::class);
        $this->systemModel      = $systemModel      ?? model(SystemSettingModel::class);
    }

    /**
     * Get a setting with hierarchical fallback.
     *
     * Lookup order: parish → diocese → province → organisation → system → default
     *
     * @param string            $key      Setting key
     * @param array<string,int> $scope    May contain parish_id, diocese_id, province_id
     * @param mixed             $default  Value to return if not found at any tier
     */
    public function getSettingForScope(string $key, array $scope = [], $default = null)
    {
        $parishId  = (int) ($scope['parish_id'] ?? 0) ?: null;
        $dioceseId = (int) ($scope['diocese_id'] ?? 0) ?: null;
        $provinceId = (int) ($scope['province_id'] ?? 0) ?: null;

        if ($parishId !== null) {
            $value = $this->parishModel->getSettingDecrypted($parishId, $key);
            if ($value !== null) {
                return $value;
            }
        }

        if ($dioceseId !== null) {
            $value = $this->dioceseModel->getValue($dioceseId, $key);
            if ($value !== null) {
                return $value;
            }
        }

        if ($provinceId !== null) {
            $value = $this->provinceModel->getValue($provinceId, $key);
            if ($value !== null) {
                return $value;
            }
        }

        $value = $this->organisationModel->getValue($key);
        if ($value !== null) {
            return $value;
        }

        $value = $this->systemModel->getValue($key);
        if ($value !== null) {
            return $value;
        }

        return $default;
    }

    /**
     * Read a setting from exactly one tier, with no hierarchy fallback.
     * Used when a block of settings must come from a single tier (SMTP
     * credentials must never mix host/user/password across tiers).
     *
     * @param string   $tier 'parish'|'diocese'|'province'|'organisation'|'system'
     */
    public function getSettingForTier(string $tier, ?int $id, string $key, $default = null)
    {
        return match ($tier) {
            'parish'       => $id !== null ? $this->parishModel->getSettingDecrypted($id, $key, $default) : $default,
            'diocese'      => $id !== null ? $this->dioceseModel->getValue($id, $key, $default) : $default,
            'province'     => $id !== null ? $this->provinceModel->getValue($id, $key, $default) : $default,
            'organisation' => $this->organisationModel->getValue($key, $default),
            'system'       => $this->systemModel->getValue($key, $default),
            default        => $default,
        };
    }

    /**
     * Backward-compatible single-key lookup without parish scope.
     */
    public function getSetting(string $key, ?int $dioceseId = null, ?int $provinceId = null, $default = null)
    {
        return $this->getSettingForScope($key, [
            'diocese_id'  => $dioceseId,
            'province_id' => $provinceId,
        ], $default);
    }

    /**
     * Get all settings as a merged key/value map for a scope.
     *
     * Merge order (later overrides earlier): system → organisation → province
     * → diocese → parish
     *
     * @param array<string,int> $scope May contain parish_id, diocese_id, province_id
     */
    public function getAllSettingsForScope(array $scope = []): array
    {
        $parishId  = (int) ($scope['parish_id'] ?? 0) ?: null;
        $dioceseId = (int) ($scope['diocese_id'] ?? 0) ?: null;
        $provinceId = (int) ($scope['province_id'] ?? 0) ?: null;

        $merged = [];

        $merged = array_merge($merged, $this->systemModel->getAll());
        $merged = array_merge($merged, $this->organisationModel->getAll());

        if ($provinceId !== null) {
            $merged = array_merge($merged, $this->provinceModel->getAll($provinceId));
        }

        if ($dioceseId !== null) {
            $merged = array_merge($merged, $this->dioceseModel->getAll($dioceseId));
        }

        if ($parishId !== null) {
            $merged = array_merge($merged, $this->parishModel->getParishSettingsMap($parishId));
        }

        return $merged;
    }

    /**
     * Backward-compatible merge without parish scope.
     */
    public function getAllSettings(?int $dioceseId = null, ?int $provinceId = null): array
    {
        return $this->getAllSettingsForScope([
            'diocese_id'  => $dioceseId,
            'province_id' => $provinceId,
        ]);
    }

    public function setParishSetting(int $parishId, string $key, $value, bool $isEncrypted = false): bool
    {
        return $this->parishModel->setSetting($parishId, $key, $value, $isEncrypted);
    }

    public function setDioceseSetting(int $dioceseId, string $key, $value, bool $isEncrypted = false): bool
    {
        return $this->dioceseModel->setValue($dioceseId, $key, $value, $isEncrypted);
    }

    public function setProvinceSetting(int $provinceId, string $key, $value, bool $isEncrypted = false): bool
    {
        return $this->provinceModel->setValue($provinceId, $key, $value, $isEncrypted);
    }

    public function setOrganisationSetting(string $key, $value, bool $isEncrypted = false): bool
    {
        return $this->organisationModel->setValue($key, $value, $isEncrypted);
    }

    public function setSystemSetting(string $key, $value, string $dataType = 'string', ?string $category = null, ?string $description = null): bool
    {
        return $this->systemModel->setValue($key, $value, $dataType, $category, $description);
    }

    /**
     * Lock a system-level setting so lower tiers cannot override it.
     */
    public function isSettingLocked(string $key): bool
    {
        return $this->systemModel->isLocked($key);
    }

    public function lockSetting(string $key): bool
    {
        return $this->setLockFlag($key, true);
    }

    public function unlockSetting(string $key): bool
    {
        return $this->setLockFlag($key, false);
    }

    private function setLockFlag(string $key, bool $locked): bool
    {
        $row = $this->systemModel->where('setting_key', $key)->first();

        if (!$row) {
            return false;
        }

        return $this->systemModel->update($row['id'], ['is_locked' => $locked ? 1 : 0]);
    }

    /**
     * Settings by category are read from the System tier, which retains
     * a category column for operational/platform settings.
     */
    public function getSettingsByCategory(string $category): array
    {
        return $this->systemModel->getByCategory($category);
    }

    public function getAllOrganisationSettings(): array
    {
        return $this->organisationModel->getAll();
    }

    public function getAllDioceseSettings(int $dioceseId): array
    {
        return $this->dioceseModel->getAll($dioceseId);
    }

    public function getAllProvinceSettings(int $provinceId): array
    {
        return $this->provinceModel->getAll($provinceId);
    }

    public function getAllSystemSettings(): array
    {
        return $this->systemModel->getAll();
    }
}
