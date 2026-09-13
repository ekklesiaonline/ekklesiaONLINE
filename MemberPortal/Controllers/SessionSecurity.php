<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MemberPortal-owned session utilities and verification flow.
 */
class SessionSecurity extends BaseController
{
    private const VERIFY_CODE_TTL_SECONDS = 600;
    private const VERIFY_CODE_RESEND_COOLDOWN_SECONDS = 60;

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
            'title' => 'Verify New Device',
            'secondsRemaining' => max(0, $expiresAt - time()),
            'isExpired' => $expiresAt <= 0 || $expiresAt < time(),
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
            return redirect()->back()->withInput()->with('error', 'Enter the 6-digit verification code.');
        }

        $expiresAt = (int) (session()->get('device_verification_expires_at') ?? 0);
        if ($expiresAt <= 0 || $expiresAt < time()) {
            return redirect()->back()->with('error', 'Verification code expired. Request a new code.');
        }

        $storedHash = (string) session()->get('device_verification_code_hash');
        $attempts   = (int) session()->get('device_verification_attempts');

        if (!hash_equals($storedHash, hash('sha256', $code))) {
            session()->set('device_verification_attempts', $attempts + 1);
            return redirect()->back()->withInput()->with('error', 'Invalid verification code. Please try again.');
        }

        session()->remove([
            'device_verification_required',
            'device_verification_code_hash',
            'device_verification_expires_at',
            'device_verification_attempts',
            'device_verification_sent_at',
        ]);
        session()->set('device_verified_at', time());

        $token = (string) session()->get('ekklesia_session_token');
        if ($token !== '') {
            model('UserSessionModel')->markVerifiedByToken($token);
        }

        return redirect()->to(base_url('dashboard'))->with('success', 'Device verified successfully.');
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
            return redirect()->back()->with('error', 'Please wait ' . $waitFor . ' seconds before requesting another code.');
        }

        $code = (string) random_int(100000, 999999);
        session()->set([
            'device_verification_code_hash'  => hash('sha256', $code),
            'device_verification_expires_at' => time() + self::VERIFY_CODE_TTL_SECONDS,
            'device_verification_attempts'   => 0,
            'device_verification_sent_at'    => time(),
        ]);

        $this->sendVerificationCodeEmail($code);

        return redirect()->back()->with('success', 'A new verification code was sent to your email.');
    }

    /**
     * Send the verification code email for the active logged-in user.
     */
    private function sendVerificationCodeEmail(string $code): void
    {
        $user = auth()->user();
        if ($user === null || empty($user->email)) {
            return;
        }

        $request   = service('request');
        $userAgent = method_exists($request, 'getUserAgent') && $request->getUserAgent() !== null
            ? (string) $request->getUserAgent()->getAgentString()
            : 'Unknown device';
        $ip        = (string) $request->getIPAddress();

        try {
            $emailService = service('email');
            $emailService->setTo((string) $user->email);
            $emailService->setSubject('Verify New Device Login - EkklesiaRD');
            $emailService->setMessage($this->buildVerificationEmailBody($ip, $userAgent, $code));
            $emailService->send(false);
        } catch (\Throwable $e) {
            log_message('warning', '[SessionSecurity] Verification email failed: ' . $e->getMessage());
        }
    }

    private function buildVerificationEmailBody(string $ip, string $userAgent, string $code): string
    {
        $safeIp    = esc($ip);
        $safeAgent = esc($userAgent);
        $safeCode  = esc($code);

        return <<<HTML
        <p>Use this verification code to confirm your new device login:</p>
        <p style="font-size:1.5rem;font-weight:700;letter-spacing:0.15em;">{$safeCode}</p>
        <p>This code expires in 10 minutes.</p>
        <p><strong>IP:</strong> {$safeIp}<br><strong>Device:</strong> {$safeAgent}</p>
        HTML;
    }
}
