<?php

namespace App\Filters;

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
    private const VERIFY_CODE_TTL_SECONDS = 600;

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

        $isNewDevice = $model->registerSession(
            (int) $user->id,
            $token,
            $ip,
            $userAgent,
            $fingerprint
        );

        if ($isNewDevice) {
            $verificationCode = $this->createVerificationChallenge($session);
            $this->notifyNewDevice($user, $ip, $userAgent, $verificationCode);
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
     * Send a new-device login notification email to the user.
     * Failures are logged as warnings and do not interrupt the request.
     */
    private function notifyNewDevice(mixed $user, string $ip, string $userAgent, string $verificationCode): void
    {
        try {
            $email = $user->email ?? null;
            if (empty($email)) {
                return;
            }

            $emailService = service('email');
            $emailService->setTo($email);
            $emailService->setSubject('Verify New Device Login - EkklesiaRD');
            $emailService->setMessage($this->buildEmailBody($ip, $userAgent, $verificationCode));
            $emailService->send(false);
        } catch (\Throwable $e) {
            log_message('warning', '[SessionTracking] New-device email failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new one-time verification challenge for this session.
     */
    private function createVerificationChallenge(mixed $session): string
    {
        $code = (string) random_int(100000, 999999);

        $session->set([
            'device_verification_required'  => true,
            'device_verification_code_hash' => hash('sha256', $code),
            'device_verification_expires_at' => time() + self::VERIFY_CODE_TTL_SECONDS,
            'device_verification_attempts'  => 0,
            'device_verification_sent_at'   => time(),
        ]);

        return $code;
    }

    /**
     * Build the HTML email body for a new-device alert.
     */
    private function buildEmailBody(string $ip, string $userAgent, string $verificationCode): string
    {
        $time        = date('Y-m-d H:i:s T');
        $safeIp      = esc($ip);
        $safeAgent   = esc($userAgent);
        $safeCode    = esc($verificationCode);
        $expiryMin   = (int) (self::VERIFY_CODE_TTL_SECONDS / 60);

        return <<<HTML
        <p>A login was detected from a new device on your <strong>EkklesiaRD</strong> account.</p>
        <p>To continue this session, enter this verification code:</p>
        <p style="font-size:1.5rem;font-weight:700;letter-spacing:0.15em;">{$safeCode}</p>
        <p>This code expires in {$expiryMin} minutes.</p>
        <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
          <tr><td><strong>Time:</strong></td><td>{$time}</td></tr>
          <tr><td><strong>IP Address:</strong></td><td>{$safeIp}</td></tr>
          <tr><td><strong>Browser / Device:</strong></td><td>{$safeAgent}</td></tr>
        </table>
        <p>If this was you, complete verification to continue.</p>
        <p>If you did not log in from this device, please <strong>change your password immediately</strong>
           and contact your parish administrator.</p>
        HTML;
    }
}
