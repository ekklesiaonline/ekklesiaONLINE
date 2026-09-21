<?php

namespace App\Models;

use CodeIgniter\Model;

class UserSessionModel extends Model
{
    protected $table      = 'user_session';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'is_new_device',
        'last_active',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * Maximum number of concurrent sessions allowed per user.
     */
    public function maxConcurrentSessions(): int
    {
        return (int) env('session.maxConcurrent', 3);
    }

    /**
     * Update last_active timestamp for a tracked session.
     */
    public function touchSession(string $token): void
    {
        $this->where('session_token', $token)
            ->set(['last_active' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Register a new session row for the given user.
     *
     * Prunes stale sessions first, then enforces the concurrent limit by
     * removing the oldest sessions if necessary. Returns true when the
     * current device fingerprint has not been seen before for this user
     * (signals a "new device" login).
     *
     * When $knownDevice is true (valid trusted-device cookie or an explicit
     * development bypass), the fingerprint check is skipped and the session
     * is registered as a known device.
     */
    public function registerSession(
        int    $userId,
        string $token,
        string $ip,
        string $userAgent,
        string $fingerprint,
        bool   $knownDevice = false
    ): bool {
        $now = date('Y-m-d H:i:s');

        // Remove sessions idle longer than the PHP session expiration.
        $sessionCfg = config('Session');
        $ttl        = (int) ($sessionCfg->expiration ?? 7200);
        $this->pruneStaleForUser($userId, $ttl);

        // Is this fingerprint new for this user?
        $isNewDevice = ! $knownDevice
            && $this->where('user_id', $userId)
                ->where('device_fingerprint', $fingerprint)
                ->countAllResults() === 0;

        // Enforce concurrent session cap: delete oldest sessions over limit.
        $max    = $this->maxConcurrentSessions();
        $active = $this->where('user_id', $userId)
            ->orderBy('last_active', 'ASC')
            ->findAll();

        if (count($active) >= $max) {
            $excess = count($active) - $max + 1;
            foreach (array_slice($active, 0, $excess) as $old) {
                $this->delete($old['id']);
            }
        }

        $this->insert([
            'user_id'            => $userId,
            'session_token'      => $token,
            'ip_address'         => $ip,
            'user_agent'         => $userAgent,
            'device_fingerprint' => $fingerprint,
            'is_new_device'      => $isNewDevice ? 1 : 0,
            'last_active'        => $now,
            'created_at'         => $now,
        ]);

        return $isNewDevice;
    }

    /**
     * Delete sessions for a specific user that have been idle beyond $ttlSeconds.
     */
    public function pruneStaleForUser(int $userId, int $ttlSeconds = 1800): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - $ttlSeconds);
        $this->where('user_id', $userId)
            ->where('last_active <', $cutoff)
            ->delete();
    }

    /**
     * Delete all session rows for a user (used on explicit logout).
     */
    public function removeAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }

    /**
     * Delete a single session row by token.
     */
    public function removeByToken(string $token): void
    {
        $this->where('session_token', $token)->delete();
    }

    /**
     * Mark a session row as verified after challenge completion.
     */
    public function markVerifiedByToken(string $token): void
    {
        $this->where('session_token', $token)
            ->set(['is_new_device' => 0])
            ->update();
    }
}
