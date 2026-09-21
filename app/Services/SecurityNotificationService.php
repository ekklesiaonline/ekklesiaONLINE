<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;

/**
 * SecurityNotificationService
 *
 * Sends security-event notification mail (e.g. "your password was changed")
 * through the credential mailer: System transport with a System-aligned
 * From mailbox, plus the recipient's tenant display name and Reply-To.
 *
 * Sends are best-effort: a failure is logged as a classified code and never
 * blocks the security operation that triggered the notice.
 */
class SecurityNotificationService
{
    /**
     * Notify the account holder that their password was changed.
     *
     * @return bool true when the message was handed to the transport
     */
    public function sendPasswordChangedNotice(int $userId, string $email): bool
    {
        $email = trim($email);
        if ($userId <= 0 || $email === '') {
            return false;
        }

        try {
            $scopeService = new EmailScopeService();
            $scope        = $scopeService->resolveScopeForUser($userId);
            $mailer       = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);

            $mailer->setTo($email);
            $mailer->setSubject(lang('Auth.passwordChangedSubject'));
            $mailer->setMessage(
                '<p>' . esc(lang('Auth.passwordChangedIntro')) . '</p>'
                    . '<p><strong>' . esc(lang('Auth.passwordChangedAt')) . '</strong> '
                    . esc(date('Y-m-d H:i:s T')) . '</p>'
                    . '<p>' . lang('Auth.passwordChangedNotYou') . '</p>'
            );

            $sent = $mailer->send(false) === true;
            if (! $sent) {
                log_message('warning', '[SecurityNotification] password-changed send failed; code={code}', [
                    'code' => EmailFailureClassifier::classify((string) $mailer->printDebugger(['headers'])),
                ]);
            }
        } catch (\Throwable $e) {
            log_message('warning', '[SecurityNotification] password-changed raised {class}', [
                'class' => $e::class,
            ]);
            $sent = false;
        }

        return $sent;
    }
}
