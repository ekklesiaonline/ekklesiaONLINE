<?php

namespace App\Services;

use App\Models\ParishSettingModel;
use App\Models\ParishIntegrationModel;
use App\Models\ParishProviderCredentialModel;

/**
 * ParishConfigService
 *
 * Manages parish-specific configuration, settings, integrations,
 * and provider credentials with encryption support.
 */
class ParishConfigService
{
    protected ParishSettingModel $settingModel;
    protected ParishIntegrationModel $integrationModel;
    protected ParishProviderCredentialModel $credentialModel;
    protected $encrypter;

    public function __construct()
    {
        $this->settingModel = model('ParishSettingModel');
        $this->integrationModel = model('ParishIntegrationModel');
        $this->credentialModel = model('ParishProviderCredentialModel');
        $this->encrypter = service('encrypter');
    }

    /**
     * Get a parish setting
     *
     * @param int $parishId Parish ID
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getSetting(int $parishId, string $key, mixed $default = null): mixed
    {
        $setting = $this->settingModel->getSetting($parishId, $key);

        if ($setting === null) {
            return $default;
        }

        // Decrypt if marked as encrypted
        if ($setting['is_encrypted'] ?? false) {
            try {
                return $this->encrypter->decrypt($setting);
            } catch (\Exception $e) {
                log_message('error', "Failed to decrypt setting {$key} for parish {$parishId}: {$e->getMessage()}");
                return $default;
            }
        }

        return $setting;
    }

    /**
     * Set a parish setting
     *
     * @param int $parishId Parish ID
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param bool $encrypt Whether to encrypt the value
     * @return bool
     */
    public function setSetting(int $parishId, string $key, mixed $value, bool $encrypt = false): bool
    {
        $valueToStore = $value;

        if ($encrypt) {
            $valueToStore = $this->encrypter->encrypt($value);
        }

        return $this->settingModel->setSetting($parishId, $key, $valueToStore, $encrypt);
    }

    /**
     * Get all settings for a parish
     *
     * @param int $parishId Parish ID
     * @return array
     */
    public function getParishSettings(int $parishId): array
    {
        $settings = $this->settingModel->getParishSettings($parishId);
        $decrypted = [];

        foreach ($settings as $setting) {
            $value = $setting['value'];

            if ($setting['is_encrypted'] ?? false) {
                try {
                    $value = $this->encrypter->decrypt($value);
                } catch (\Exception $e) {
                    log_message('error', "Failed to decrypt setting {$setting['key']} for parish {$parishId}: {$e->getMessage()}");
                }
            }

            $decrypted[$setting['key']] = $value;
        }

        return $decrypted;
    }

    /**
     * Delete a parish setting
     *
     * @param int $parishId Parish ID
     * @param string $key Setting key
     * @return bool
     */
    public function deleteSetting(int $parishId, string $key): bool
    {
        return $this->settingModel->deleteSetting($parishId, $key);
    }

    /**
     * Get active integrations for a parish
     *
     * @param int $parishId Parish ID
     * @return array
     */
    public function getActiveIntegrations(int $parishId): array
    {
        return $this->integrationModel->getActiveIntegrations($parishId);
    }

    /**
     * Get integration by provider
     *
     * @param int $parishId Parish ID
     * @param string $provider Provider name (e.g., 'mailgun', 'stripe')
     * @return array|null
     */
    public function getIntegration(int $parishId, string $provider): ?array
    {
        return $this->integrationModel->getByProvider($parishId, $provider);
    }

    /**
     * Create or update an integration
     *
     * @param int $parishId Parish ID
     * @param string $provider Provider name
     * @param string $integrationType Integration type (email, sms, payment, etc.)
     * @param array $data Additional integration data
     * @return int|string Integration ID
     */
    public function createIntegration(int $parishId, string $provider, string $integrationType, array $data = []): int|string
    {
        $existing = $this->integrationModel->getByProvider($parishId, $provider);

        $integrationData = array_merge([
            'parish_id' => $parishId,
            'provider' => $provider,
            'integration_type' => $integrationType,
            'is_active' => $data['is_active'] ?? false,
            'webhook_url' => $data['webhook_url'] ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], array_intersect_key($data, array_flip([
            'parish_id',
            'provider',
            'integration_type',
            'is_active',
            'webhook_url',
            'webhook_secret',
            'notes',
            'sync_status'
        ])));

        if ($existing) {
            $this->integrationModel->update($existing['id'], $integrationData);
            return $existing['id'];
        }

        return $this->integrationModel->insert($integrationData);
    }

    /**
     * Get or create credential for integration
     *
     * @param int $integrationId Integration ID
     * @param string $credentialKey Credential key
     * @param string|null $value Value to set if creating new
     * @return mixed Decrypted credential value or null
     */
    public function getCredential(int $integrationId, string $credentialKey, ?string $value = null): mixed
    {
        $credential = $this->credentialModel->getCredential($integrationId, $credentialKey);

        if ($credential) {
            try {
                return $this->encrypter->decrypt($credential['credential_value']);
            } catch (\Exception $e) {
                log_message('error', "Failed to decrypt credential {$credentialKey}: {$e->getMessage()}");
                return null;
            }
        }

        // Create new credential if value provided
        if ($value !== null) {
            $encrypted = $this->encrypter->encrypt($value);
            $this->credentialModel->insert([
                'integration_id' => $integrationId,
                'credential_key' => $credentialKey,
                'credential_value' => $encrypted,
                'is_encrypted' => true,
                'is_active' => true,
            ]);
            return $value;
        }

        return null;
    }

    /**
     * Set credential for integration
     *
     * @param int $integrationId Integration ID
     * @param string $credentialKey Credential key
     * @param string $value Credential value
     * @param int|null $rotationIntervalDays Optional rotation interval in days
     * @return bool
     */
    public function setCredential(int $integrationId, string $credentialKey, string $value, ?int $rotationIntervalDays = null): bool
    {
        $encrypted = $this->encrypter->encrypt($value);

        $data = [
            'integration_id' => $integrationId,
            'credential_key' => $credentialKey,
            'credential_value' => $encrypted,
            'is_encrypted' => true,
            'last_rotated' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ];

        if ($rotationIntervalDays !== null) {
            $data['rotation_interval_days'] = max(0, $rotationIntervalDays);
        }

        $existing = $this->credentialModel->getCredential($integrationId, $credentialKey);

        if ($existing) {
            return $this->credentialModel->update($existing['id'], $data);
        }

        return $this->credentialModel->insert($data) !== false;
    }

    /**
     * Rotate credential (archive old, create new)
     *
     * @param int $integrationId Integration ID
     * @param string $credentialKey Credential key
     * @param string $newValue New credential value
     * @return bool
     */
    public function rotateCredential(int $integrationId, string $credentialKey, string $newValue): bool
    {
        return $this->credentialModel->rotateCredential($integrationId, $credentialKey, $newValue);
    }

    /**
     * Get all active credentials for an integration
     *
     * @param int $integrationId Integration ID
     * @return array Decrypted credentials keyed by credential_key
     */
    public function getActiveCredentials(int $integrationId): array
    {
        $credentials = $this->credentialModel->getActiveCredentials($integrationId);
        $decrypted = [];

        foreach ($credentials as $credential) {
            try {
                $decrypted[$credential['credential_key']] = $this->encrypter->decrypt($credential['credential_value']);
            } catch (\Exception $e) {
                log_message('error', "Failed to decrypt credential {$credential['credential_key']}: {$e->getMessage()}");
            }
        }

        return $decrypted;
    }

    /**
     * Update integration sync status
     *
     * @param int $integrationId Integration ID
     * @param string $status Status (idle, syncing, error, paused)
     * @return bool
     */
    public function updateSyncStatus(int $integrationId, string $status): bool
    {
        return $this->integrationModel->updateSyncStatus($integrationId, $status);
    }

    /**
     * Get credentials needing rotation
     *
     * @param int $daysThreshold Days threshold (default 90)
     * @return array
     */
    public function getCredentialsNeedingRotation(int $daysThreshold = 90): array
    {
        return $this->credentialModel->getCredentialsNeedingRotation($daysThreshold);
    }

    /**
     * Check if parish has integration enabled
     *
     * @param int $parishId Parish ID
     * @param string $provider Provider name
     * @return bool
     */
    public function isIntegrationEnabled(int $parishId, string $provider): bool
    {
        $integration = $this->integrationModel->getByProvider($parishId, $provider);
        return $integration && $integration['is_active'];
    }
}
