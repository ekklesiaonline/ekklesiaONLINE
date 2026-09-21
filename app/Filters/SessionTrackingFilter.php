<?php

namespace App\Filters;

use App\Services\DeviceVerificationPolicy;
use App\Services\DeviceVerificationService;
use App\Services\MemberAccessService;
use App\Models\UserSessionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SessionTrackingFilter
 *
 * Tracks authenticated sessions in the `user_sessions` table to support:
 *  - Concurrent session limits (default: 3 simultaneous sessions per user)
 *  - New-device detection with email notification
 *  - Accurate last_active timestamps for session audit
 *
 * Runs as a `before` global filter on every authenticated request.
 * Returns null immediately for unauthenticated requests.
 */
class SessionTrackingFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        // First successful authenticated request finalizes portal activation.
        (new MemberAccessService())->activatePortalAccessAfterSuccessfulLogin((int) $user->id);

        $session = service('session');
        $token   = $session->get('ekklesia_session_token');
        $model   = model(UserSessionModel::class);

        if ($token !== null) {
            // Token present: check DB (handles edge case where DB was reset).
            if ($model->where('session_token', $token)->countAllResults() > 0) {
                $model->touchSession($token);
                return null;
            }
            // Token in session but missing from DB — fall through to register it.
        }

        // New session: generate a stable token that survives CI4's ID regeneration.
        $token = bin2hex(random_bytes(32));
        $session->set('ekklesia_session_token', $token);

        $requestAgent = service('request')->getUserAgent();
        $userAgent    = $requestAgent ? (string) $requestAgent->getAgentString() : '';
        $ip          = (string) $request->getIPAddress();
        $fingerprint = hash('sha256', $userAgent);

        $policy = new DeviceVerificationPolicy();

        if ($policy->shouldBypass()) {
            // Development/testing bypass: register the session as a known device
            // WITHOUT creating a challenge or sending an email. Enforcement is
            // skipped by DeviceVerificationFilter via the same policy.
            $model->registerSession(
                (int) $user->id,
                $token,
                $ip,
                $userAgent,
                $fingerprint,
                true
            );
            $session->set('device_verified_at', time());
            $this->auditBypass((int) $user->id);

            return null;
        }

        $isNewDevice = $model->registerSession(
            (int) $user->id,
            $token,
            $ip,
            $userAgent,
            $fingerprint,
            $this->isTrustedDevice($request, (int) $user->id)
        );

        if ($isNewDevice) {
            (new DeviceVerificationService())->startChallenge($session, $user, $ip, $userAgent);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Check whether the request carries a valid trusted-device cookie for
     * this user. A valid trusted device suppresses the "new device"
     * classification entirely.
     */
    private function isTrustedDevice(RequestInterface $request, int $userId): bool
    {
        try {
            return (new \App\Services\TrustedDeviceService())->validate($request, $userId) !== null;
        } catch (\Throwable $e) {
            log_message('warning', '[SessionTracking] Trusted-device check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a redacted audit event when the development bypass is used.
     * Only reachable in testing/development (DeviceVerificationPolicy).
     */
    private function auditBypass(int $userId): void
    {
        try {
            service('audit')->log('security.device_verification.bypassed', [
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => ['environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production'],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[SessionTracking] Bypass audit failed: ' . $e->getMessage());
        }
    }

}
