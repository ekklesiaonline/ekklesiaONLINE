<?php

namespace App\Services;

use App\Models\UserTrustedDeviceModel;
use CodeIgniter\HTTP\RequestInterface;

/**
 * TrustedDeviceService
 *
 * Implements durable "remember this device" trust, decoupled from the
 * short-lived user_session rows.
 *
 * Flow:
 *  - After a successful device-verification challenge (or another approved
 *    trust grant), issue() creates a `selector.validator` pair, stores only
 *    the SHA-256 hash of the validator, and sends the pair to the browser
 *    in a Secure/HttpOnly/SameSite=Lax cookie.
 *  - On later logins, validate() matches the cookie's selector, compares
 *    validator hashes with hash_equals(), and rejects expired/revoked rows.
 *
 * The raw validator is never stored server-side and is never logged.
 */
class TrustedDeviceService
{
    public const COOKIE_NAME = 'ekklesia_device';

    private UserTrustedDeviceModel $devices;

    public function __construct(?UserTrustedDeviceModel $devices = null)
    {
        $this->devices = $devices ?? model(UserTrustedDeviceModel::class);
    }

    /**
     * Configured trust lifetime in days (default 30).
     */
    public function trustTtlDays(): int
    {
        $days = (int) env('security.trustedDeviceTtlDays', 30);

        return $days > 0 ? $days : 30;
    }

    /**
     * Issue a new trusted-device cookie + DB row for the user.
     * Returns the new device row id, or null on failure.
     */
    public function issue(int $userId, string $userAgent = '', string $ip = ''): ?int
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->trustTtlDays() * 86400));

        $id = $this->devices->insert([
            'user_id'         => $userId,
            'selector'        => $selector,
            'token_hash'      => hash('sha256', $validator),
            'display_name'    => $this->describeAgent($userAgent),
            'last_ip_address' => $ip !== '' ? $ip : null,
            'first_seen_at'   => $now,
            'last_seen_at'    => $now,
            'expires_at'      => $expiresAt,
        ]);

        if (! is_int($id) || $id <= 0) {
            log_message('error', '[TrustedDevice] Failed to persist trusted device for user ' . $userId);
            return null;
        }

        $this->setCookie($selector . '.' . $validator, $this->trustTtlDays() * 86400);
        $this->audit('security.trusted_device.issued', $userId, ['device_id' => $id]);

        return $id;
    }

    /**
     * Validate the request's trusted-device cookie for the given user.
     * Returns the trusted-device row id when valid, null otherwise.
     * A valid check also refreshes last_seen_at / last_ip_address.
     */
    public function validate(RequestInterface $request, int $userId): ?int
    {
        $device = $this->resolveFromRequest($request, $userId);
        if ($device === null) {
            return null;
        }

        $this->devices->touch((int) $device['id'], (string) $request->getIPAddress());

        return (int) $device['id'];
    }

    /**
     * Resolve and validate the cookie, returning the full row when valid.
     * Unlike validate(), does not mutate last_seen metadata — used by the
     * security UI to mark "this device".
     *
     * @return array<string, mixed>|null
     */
    public function resolveFromRequest(RequestInterface $request, int $userId): ?array
    {
        if (! $request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            return null;
        }

        $cookie = (string) $request->getCookie(self::COOKIE_NAME);
        if ($cookie === '' || ! str_contains($cookie, '.')) {
            return null;
        }

        [$selector, $validator] = explode('.', $cookie, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        $device = $this->devices->findValidBySelector($userId, $selector);
        if ($device === null) {
            return null;
        }

        if (! hash_equals((string) $device['token_hash'], hash('sha256', $validator))) {
            return null;
        }

        return $device;
    }

    /**
     * Revoke one trusted device owned by the user. Returns true when a row
     * was actually revoked.
     */
    public function revoke(int $userId, int $deviceId, ?int $byUserId = null): bool
    {
        $device = $this->devices
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->find($deviceId);

        if ($device === null) {
            return false;
        }

        $this->devices->revoke($deviceId, $byUserId ?? $userId);
        $this->audit('security.trusted_device.revoked', $userId, ['device_id' => $deviceId]);

        return true;
    }

    /**
     * Revoke all trusted devices for a user (optionally keeping one).
     *
     * @return int number revoked
     */
    public function revokeAll(int $userId, ?int $exceptDeviceId = null, ?int $byUserId = null): int
    {
        $count = $this->devices->revokeAllForUser($userId, $exceptDeviceId, $byUserId ?? $userId);

        if ($count > 0) {
            $this->audit('security.trusted_device.revoked', $userId, [
                'count'          => $count,
                'except_current' => $exceptDeviceId !== null,
            ]);
        }

        return $count;
    }

    /**
     * Active trusted devices for the security UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->devices->activeForUser($userId);
    }

    /**
     * Expire the trusted-device cookie on the browser (forget this device).
     * Does not revoke the DB row — call revoke() for that.
     */
    public function clearCookie(): void
    {
        // Response::setCookie() interprets a positive numeric expire as a
        // duration in seconds; a non-positive value expires immediately.
        $this->setCookie('', 0);
    }

    /**
     * Forget the device presenting this request: revoke the matching row
     * (when valid) and expire the cookie.
     */
    public function forgetCurrent(RequestInterface $request, int $userId): void
    {
        $device = $this->resolveFromRequest($request, $userId);
        if ($device !== null) {
            $this->revoke($userId, (int) $device['id'], $userId);
        }

        $this->clearCookie();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @param int $expireSeconds lifetime in seconds; <= 0 expires the cookie
     */
    private function setCookie(string $value, int $expireSeconds): void
    {
        $request  = service('request');
        $response = service('response');

        $response->setCookie([
            'name'     => self::COOKIE_NAME,
            'value'    => $value,
            'expire'   => $expireSeconds,
            'path'     => '/',
            'secure'   => $request instanceof \CodeIgniter\HTTP\IncomingRequest
                && $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Produce a friendly "Safari on macOS"-style label from a user-agent
     * string. Best-effort; never returns the raw agent.
     */
    public function describeAgent(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $browser = match (true) {
            stripos($userAgent, 'Edg') !== false      => 'Edge',
            stripos($userAgent, 'Chrome') !== false   => 'Chrome',
            stripos($userAgent, 'Firefox') !== false  => 'Firefox',
            stripos($userAgent, 'Safari') !== false   => 'Safari',
            default                                    => 'Browser',
        };

        $platform = match (true) {
            stripos($userAgent, 'Windows') !== false  => 'Windows',
            stripos($userAgent, 'iPhone') !== false,
            stripos($userAgent, 'iPad') !== false     => 'iOS',
            stripos($userAgent, 'Android') !== false  => 'Android',
            stripos($userAgent, 'Mac OS') !== false,
            stripos($userAgent, 'Macintosh') !== false => 'macOS',
            stripos($userAgent, 'Linux') !== false    => 'Linux',
            default                                    => 'Unknown OS',
        };

        return $browser . ' on ' . $platform;
    }

    /**
     * Redacted audit hook — never include selectors, validators, hashes,
     * cookies, or user-agent strings.
     */
    private function audit(string $action, int $userId, array $meta = []): void
    {
        try {
            service('audit')->log($action, [
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => $meta,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[TrustedDevice] Audit failed: ' . $e->getMessage());
        }
    }
}
