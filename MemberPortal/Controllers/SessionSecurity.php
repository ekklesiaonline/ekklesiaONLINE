<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Services\DeviceVerificationService;
use App\Services\TrustedDeviceService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MemberPortal-owned session utilities and verification flow.
 */
class SessionSecurity extends BaseController
{
    private const VERIFY_CODE_RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Keepalive endpoint — resets the server-side idle timer.
     */
    public function keepalive(): ResponseInterface
    {
        if (!auth()->loggedIn()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Not authenticated.',
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Not authenticated.',
                ],
            ], ResponseInterface::HTTP_UNAUTHORIZED);
        }

        session()->set('_keepalive_ts', time());

        return $this->jsonResponse(['success' => true, 'ts' => time()]);
    }

    /**
     * Show the new-device verification challenge page.
     */
    public function deviceVerifyForm()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!session()->get('device_verification_required')) {
            return redirect()->to(base_url('dashboard'));
        }

        $expiresAt = (int) (session()->get('device_verification_expires_at') ?? 0);

        return view('MemberPortal\Views\Session/device_verify', [
            'title' => lang('Auth.verifyDeviceTitle'),
            'secondsRemaining' => max(0, $expiresAt - time()),
            'isExpired' => $expiresAt <= 0 || $expiresAt < time(),
            'mailFailed' => (bool) session()->get('device_verification_mail_failed'),
        ]);
    }

    /**
     * Validate the submitted verification code.
     */
    public function deviceVerifySubmit()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!session()->get('device_verification_required')) {
            return redirect()->to(base_url('dashboard'));
        }

        $code = preg_replace('/\D+/', '', (string) $this->request->getPost('code'));
        if (strlen($code) !== 6) {
            return redirect()->back()->withInput()->with('error', lang('Auth.verifyCodeMalformed'));
        }

        $expiresAt = (int) (session()->get('device_verification_expires_at') ?? 0);
        if ($expiresAt <= 0 || $expiresAt < time()) {
            return redirect()->back()->with('error', lang('Auth.verifyCodeExpiredRetry'));
        }

        $storedHash = (string) session()->get('device_verification_code_hash');
        $attempts   = (int) session()->get('device_verification_attempts');

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS || $storedHash === '') {
            $this->invalidateVerificationCode(true);
            return redirect()->back()->with('error', lang('Auth.verifyCodeLockedOut'));
        }

        if (!hash_equals($storedHash, hash('sha256', $code))) {
            $attempts++;
            session()->set('device_verification_attempts', $attempts);
            if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                $this->invalidateVerificationCode(true);
                return redirect()->back()->with('error', lang('Auth.verifyCodeLockedOut'));
            }
            return redirect()->back()->withInput()->with('error', lang('Auth.verifyCodeInvalid'));
        }

        session()->remove([
            'device_verification_required',
            'device_verification_code_hash',
            'device_verification_expires_at',
            'device_verification_attempts',
            'device_verification_sent_at',
            'device_verification_mail_failed',
        ]);
        session()->set('device_verified_at', time());

        $user   = auth()->user();
        $userId = (int) ($user->id ?? 0);

        $token = (string) session()->get('ekklesia_session_token');
        if ($token !== '') {
            model('UserSessionModel')->markVerifiedByToken($token);
        }

        // Trust this device: issue a durable selector+validator cookie so the
        // browser is not re-challenged after session rows are pruned.
        try {
            $userAgent = $this->request->getUserAgent() !== null
                ? (string) $this->request->getUserAgent()->getAgentString()
                : '';
            (new TrustedDeviceService())->issue($userId, $userAgent, (string) $this->request->getIPAddress());
        } catch (\Throwable $e) {
            log_message('warning', '[SessionSecurity] Trusted-device issue failed: ' . $e->getMessage());
        }

        $this->auditVerified($userId);

        // Return to the page the user originally requested, when recorded.
        $returnUrl = (string) (session()->get('device_verify_return_url') ?? '');
        session()->remove('device_verify_return_url');
        $target = $this->safeReturnUrl($returnUrl) ?? base_url('dashboard');

        // withCookies() carries the trusted-device cookie (set on the shared
        // response) onto this RedirectResponse so it actually reaches the browser.
        return redirect()->to($target)->withCookies()->with('success', lang('Auth.deviceVerifiedSuccess'));
    }

    /**
     * Invalidate the pending verification code so it can no longer be guessed.
     * Keeps `device_verification_required` set so the user must request a new code.
     */
    private function invalidateVerificationCode(bool $audit): void
    {
        session()->remove([
            'device_verification_code_hash',
            'device_verification_attempts',
        ]);
        session()->set('device_verification_expires_at', 0);

        if ($audit) {
            $this->audit()->log('security.device_verification.lockout', [
                'entity_type' => 'user',
                'entity_id'   => auth()->id(),
            ]);
        }
    }

    /**
     * Resend a fresh verification code email for the current session.
     */
    public function deviceVerifyResend()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!session()->get('device_verification_required')) {
            return redirect()->to(base_url('dashboard'));
        }

        $lastSentAt = (int) (session()->get('device_verification_sent_at') ?? 0);
        $waitFor    = self::VERIFY_CODE_RESEND_COOLDOWN_SECONDS - (time() - $lastSentAt);
        if ($waitFor > 0) {
            return redirect()->back()->with('error', lang('Auth.verifyResendCooldown', [$waitFor]));
        }

        $user      = auth()->user();
        $userAgent = $this->request->getUserAgent() !== null
            ? (string) $this->request->getUserAgent()->getAgentString()
            : '';
        $ip        = (string) $this->request->getIPAddress();

        $sent = (new DeviceVerificationService())->startChallenge(session(), $user, $ip, $userAgent);

        $this->auditResend((int) ($user->id ?? 0));

        $flash = $sent ? ['success', lang('Auth.verifyResendSent')] : ['error', lang('Auth.verifyEmailFailed')];

        return redirect()->back()->with($flash[0], $flash[1]);
    }

    // ------------------------------------------------------------------
    // Trusted-device management (Security page)
    // ------------------------------------------------------------------

    /**
     * Security page: list this user's trusted devices.
     */
    public function devices()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $userId  = (int) auth()->id();
        $service = new TrustedDeviceService();
        $current = $service->resolveFromRequest($this->request, $userId);

        return view('MemberPortal\Views\Session/devices', [
            'title'            => lang('Auth.trustedDevicesTitle'),
            'devices'          => $service->listForUser($userId),
            'currentDeviceId'  => $current !== null ? (int) $current['id'] : null,
        ]);
    }

    /**
     * Revoke a single trusted device.
     */
    public function revokeDevice(int $id)
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $userId  = (int) auth()->id();
        $service = new TrustedDeviceService();

        if (! $service->revoke($userId, $id, $userId)) {
            return redirect()->back()->with('error', lang('Auth.trustedDeviceNotFound'));
        }

        // If the user revoked the device they are on, expire its cookie too.
        $current = $service->resolveFromRequest($this->request, $userId);
        if ($current === null || (int) $current['id'] === $id) {
            $service->clearCookie();
        }

        return redirect()->back()->withCookies()->with('success', lang('Auth.trustedDeviceRevoked'));
    }

    /**
     * Revoke every trusted device except the one presenting this request.
     */
    public function revokeOtherDevices()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $userId  = (int) auth()->id();
        $service = new TrustedDeviceService();
        $current = $service->resolveFromRequest($this->request, $userId);

        $count = $service->revokeAll(
            $userId,
            $current !== null ? (int) $current['id'] : null,
            $userId
        );

        return redirect()->back()->with('success', lang('Auth.trustedDevicesRevokedCount', [$count]));
    }

    /**
     * "Forget this device": revoke the current trusted device (if any) and
     * expire its cookie. The user stays logged in; the next login from this
     * browser will be challenged again.
     */
    public function forgetDevice()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        (new TrustedDeviceService())->forgetCurrent($this->request, (int) auth()->id());

        return redirect()->back()->withCookies()->with('success', lang('Auth.trustedDeviceForgotten'));
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Allow only same-site relative paths as post-verification targets.
     */
    private function safeReturnUrl(string $url): ?string
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return null;
        }

        if (str_starts_with($url, '/session/device-verify')) {
            return null;
        }

        return base_url(ltrim($url, '/'));
    }

    private function auditVerified(int $userId): void
    {
        try {
            service('audit')->log('security.device_verification.verified', [
                'entity_type' => 'user',
                'entity_id'   => $userId,
            ]);
            service('audit')->log('auth.login.completed', [
                'entity_type' => 'user',
                'entity_id'   => $userId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[SessionSecurity] Verify audit failed: ' . $e->getMessage());
        }
    }

    private function auditResend(int $userId): void
    {
        try {
            service('audit')->log('security.device_verification.resend', [
                'entity_type' => 'user',
                'entity_id'   => $userId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[SessionSecurity] Resend audit failed: ' . $e->getMessage());
        }
    }
}
