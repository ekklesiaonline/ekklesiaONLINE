<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishProviderCredentialModel extends Model
{
    protected $table = 'parish_provider_credential';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;

    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'integration_id',
        'credential_key',
        'credential_value',
        'is_encrypted',
        'last_rotated',
        'rotation_interval_days',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'integration_id' => 'required|is_natural_no_zero',
        'credential_key' => 'required|min_length[1]|max_length[100]',
        'credential_value' => 'required',
        'is_encrypted' => 'permit_empty|in_list[0,1]',
        'rotation_interval_days' => 'permit_empty|is_natural',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Get active credentials for an integration
     */
    public function getActiveCredentials(int $integrationId): array
    {
        return $this->where('integration_id', $integrationId)
            ->where('is_active', true)
            ->findAll();
    }

    /**
     * Get credential by key from integration
     */
    public function getCredential(int $integrationId, string $credentialKey): ?array
    {
        return $this->where('integration_id', $integrationId)
            ->where('credential_key', $credentialKey)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Rotate credential (archive old, activate new)
     */
    public function rotateCredential(int $integrationId, string $credentialKey, string $newValue): bool
    {
        // Deactivate old credential
        $oldCred = $this->where('integration_id', $integrationId)
            ->where('credential_key', $credentialKey)
            ->where('is_active', true)
            ->first();

        if ($oldCred) {
            $this->update($oldCred['id'], ['is_active' => false]);
        }

        // Create new credential
        return $this->insert([
            'integration_id' => $integrationId,
            'credential_key' => $credentialKey,
            'credential_value' => $newValue,
            'is_encrypted' => true,
            'last_rotated' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]) !== false;
    }

    /**
     * Get credentials needing rotation
     */
    public function getCredentialsNeedingRotation(int $daysThreshold = 90): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$daysThreshold} days"));

        return $this->where('is_active', true)
            ->where('rotation_interval_days >', 0)
            ->where('last_rotated <', $threshold, false)
            ->findAll();
    }
}
