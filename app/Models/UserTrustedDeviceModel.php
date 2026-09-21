<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserTrustedDeviceModel
 *
 * Durable "remember this device" records. Only the SHA-256 hash of the
 * cookie validator is stored — raw device tokens never touch the database.
 * Rows expire (expires_at) and can be revoked (revoked_at / revoked_by).
 */
class UserTrustedDeviceModel extends Model
{
    protected $table            = 'user_trusted_device';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'selector',
        'token_hash',
        'display_name',
        'last_ip_address',
        'first_seen_at',
        'last_seen_at',
        'expires_at',
        'revoked_at',
        'revoked_by',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'         => '?int',
        'user_id'    => '?int',
        'revoked_by' => '?int',
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Find a usable trusted-device row for a user by cookie selector.
     * Excludes expired and revoked records.
     *
     * @return array<string, mixed>|null
     */
    public function findValidBySelector(int $userId, string $selector): ?array
    {
        return $this->where('user_id', $userId)
            ->where('selector', $selector)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Update last_seen_at / last_ip_address for a trusted device.
     */
    public function touch(int $id, string $ip): void
    {
        $this->update($id, [
            'last_seen_at'    => date('Y-m-d H:i:s'),
            'last_ip_address' => $ip !== '' ? $ip : null,
        ]);
    }

    /**
     * Revoke a single trusted device.
     */
    public function revoke(int $id, ?int $byUserId = null): void
    {
        $this->update($id, [
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_by' => $byUserId,
        ]);
    }

    /**
     * Revoke all trusted devices for a user, optionally keeping one
     * (e.g. the device performing a "revoke others" action).
     *
     * @return int number of rows revoked
     */
    public function revokeAllForUser(int $userId, ?int $exceptId = null, ?int $byUserId = null): int
    {
        $builder = $this->where('user_id', $userId)
            ->where('revoked_at', null);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->set([
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_by' => $byUserId,
        ])->update() ? $this->db->affectedRows() : 0;
    }

    /**
     * List active (non-revoked, non-expired) trusted devices for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForUser(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('last_seen_at', 'DESC')
            ->findAll();
    }

    /**
     * Permanently delete expired or long-revoked rows (housekeeping).
     */
    public function purgeExpired(): int
    {
        $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();

        return $this->db->affectedRows();
    }
}
