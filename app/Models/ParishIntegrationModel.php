<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishIntegrationModel extends Model
{
    protected $table = 'parish_integration';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;

    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'parish_id',
        'provider',
        'integration_type',
        'is_active',
        'webhook_url',
        'webhook_secret',
        'last_sync',
        'sync_status',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'parish_id' => 'required|is_natural_no_zero',
        'provider' => 'required|min_length[1]|max_length[100]',
        'integration_type' => 'required|in_list[email,sms,payment,forms,calendar,other]',
        'is_active' => 'permit_empty|in_list[0,1]',
        'webhook_url' => 'permit_empty|valid_url',
        'webhook_secret' => 'permit_empty|max_length[255]',
        'sync_status' => 'permit_empty|in_list[idle,syncing,error,paused]',
    ];

    /**
     * Get active integrations for a parish
     */
    public function getActiveIntegrations(int $parishId): array
    {
        return $this->where('parish_id', $parishId)
            ->where('is_active', true)
            ->findAll();
    }

    /**
     * Get integration by provider for a parish
     */
    public function getByProvider(int $parishId, string $provider): ?array
    {
        return $this->where('parish_id', $parishId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Get integrations by type for a parish
     */
    public function getByType(int $parishId, string $integrationType): array
    {
        return $this->where('parish_id', $parishId)
            ->where('integration_type', $integrationType)
            ->findAll();
    }

    /**
     * Update sync status and timestamp
     */
    public function updateSyncStatus(int $integrationId, string $status): bool
    {
        return $this->update($integrationId, [
            'sync_status' => $status,
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get integrations that need rotation (credentialsexp rotation interval exceeded)
     */
    public function getIntegrationsNeedingRotation(int $parishId, int $daysThreshold = 90): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$daysThreshold} days"));

        return $this->where('parish_id', $parishId)
            ->where('is_active', true)
            ->where('last_sync <', $threshold, false)
            ->findAll();
    }
}
