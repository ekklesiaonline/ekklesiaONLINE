<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use CodeIgniter\Session\Session;

/**
 * DeviceVerificationService
 *
 * Owns the new-device verification challenge lifecycle:
 *  - startChallenge(): generates the 6-digit code, stores its SHA-256 hash
 *    in the PHP session, and sends the localized email via System transport.
 *  - The email send result is always checked and audited; a failed send
 *    leaves the challenge in place and flags the session so the verify
 *    form can show a delivery warning with a working resend path.
 *
 * Verification codes are never logged or audited — only lifecycle events.
 */
class DeviceVerificationService
{
    public const CODE_TTL_SECONDS = 600;

    /**
     * Create a fresh challenge on the session and email the code.
     *
     * @param mixed $user authenticated Shield user (needs ->email)
     * @return bool true when the email was handed to the transport
     */
    public function startChallenge(Session $session, mixed $user, string $ip, string $userAgent): bool
    {
        $code = (string) random_int(100000, 999999);

        $session->set([
            'device_verification_required'   => true,
            'device_verification_code_hash'  => hash('sha256', $code),
            'device_verification_expires_at' => time() + self::CODE_TTL_SECONDS,
            'device_verification_attempts'   => 0,
            'device_verification_sent_at'    => time(),
        ]);

        $userId = (int) ($user->id ?? 0);
        $this->audit('security.device_verification.challenge_issued', $userId);

        $sent = $this->sendCodeEmail($user, $ip, $userAgent, $code);
        $session->set('device_verification_mail_failed', ! $sent);

        return $sent;
    }

    /**
     * Send the verification-code email through System transport with a
     * System-aligned From mailbox and the user's tenant display
     * name/Reply-To. Returns the transport result; failures are logged
     * (classified code only) and audited.
     */
    public function sendCodeEmail(mixed $user, string $ip, string $userAgent, string $code): bool
    {
        $email  = $user->email ?? null;
        $userId = (int) ($user->id ?? 0);

        if (empty($email)) {
            return false;
        }

        try {
            $friendlyDevice = (new TrustedDeviceService())->describeAgent($userAgent);

            $scopeService = new EmailScopeService();
            $scope        = $userId > 0 ? $scopeService->resolveScopeForUser($userId) : [];
            $emailService = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);
            $emailService->setTo((string) $email);
            $emailService->setSubject(lang('Auth.deviceVerifyEmailSubject'));
            $emailService->setMessage($this->buildEmailBody($ip, $userAgent, $friendlyDevice, $code));

            $sent = $emailService->send(false) === true;
            if (! $sent) {
                log_message('warning', '[DeviceVerification] send failed; code={code}', [
                    'code' => EmailFailureClassifier::classify((string) $emailService->printDebugger(['headers'])),
                ]);
            }
        } catch (\Throwable $e) {
            log_message('warning', '[DeviceVerification] Email failed: {class}', [
                'class' => $e::class,
            ]);
            $sent = false;
        }

        $this->audit(
            $sent ? 'security.device_verification.email_sent' : 'security.device_verification.email_failed',
            $userId
        );

        return $sent;
    }

    /**
     * Redacted audit hook — never include codes, hashes, tokens, or email
     * bodies.
     */
    private function audit(string $action, int $userId): void
    {
        try {
            service('audit')->log($action, [
                'entity_type' => 'user',
                'entity_id'   => $userId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[DeviceVerification] Audit failed: ' . $e->getMessage());
        }
    }

    /**
     * Localized HTML body for the new-device challenge email.
     */
    private function buildEmailBody(string $ip, string $userAgent, ?string $friendlyDevice, string $code): string
    {
        $safeIp     = esc($ip);
        $safeAgent  = esc($friendlyDevice ?? $userAgent);
        $safeCode   = esc($code);
        $expiryMin  = (int) (self::CODE_TTL_SECONDS / 60);
        $time       = date('Y-m-d H:i:s T');

        return '<p>' . esc(lang('Auth.deviceVerifyEmailIntro')) . '</p>'
            . '<p style="font-size:1.5rem;font-weight:700;letter-spacing:0.15em;">' . $safeCode . '</p>'
            . '<p>' . esc(lang('Auth.deviceVerifyEmailCodeExpiry', [$expiryMin])) . '</p>'
            . '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
            . '<tr><td><strong>' . esc(lang('Auth.deviceVerifyEmailTime')) . '</strong></td><td>' . $time . '</td></tr>'
            . '<tr><td><strong>' . esc(lang('Auth.deviceVerifyEmailIp')) . '</strong></td><td>' . $safeIp . '</td></tr>'
            . '<tr><td><strong>' . esc(lang('Auth.deviceVerifyEmailDevice')) . '</strong></td><td>' . $safeAgent . '</td></tr>'
            . '</table>'
            . '<p>' . esc(lang('Auth.deviceVerifyEmailConfirm')) . '</p>'
            . '<p>' . lang('Auth.deviceVerifyEmailNotYou') . '</p>';
    }
}
