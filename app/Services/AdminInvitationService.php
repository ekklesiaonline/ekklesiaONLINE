<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use App\Services\EmailScopeService;

class AdminInvitationService
{
    public function send(string $recipientEmail, ?int $userId = null): void
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            $this->recordSent($userId);
            return;
        }

        try {
            $emailService = (new EmailScopeService())->systemMailer(['overrides' => ['mailType' => 'html']]);
        } catch (\Throwable $e) {
            log_message('error', '[AdminInvitationService] Failed to build system mailer: {message}', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('ADMIN_INVITATION_EMAIL_FAILED');
        }

        $emailService->setTo($recipientEmail);
        $emailService->setSubject(lang('System.adminInvitationSubject'));

        $passwordSetupUrl = base_url('login/magic-link');
        $emailService->setMessage(
            '<p>' . esc(lang('System.adminInvitationGreeting')) . '</p>'
                . '<p>' . esc(lang('System.adminInvitationBody')) . '</p>'
                . '<p><a href="' . esc($passwordSetupUrl, 'attr') . '">' . esc($passwordSetupUrl) . '</a></p>'
        );

        if ($emailService->send(false) === false) {
            log_message('error', '[AdminInvitationService] send failed; code={code}', [
                'code' => EmailFailureClassifier::classify((string) $emailService->printDebugger(['headers'])),
            ]);
            throw new \RuntimeException('ADMIN_INVITATION_EMAIL_FAILED');
        }

        $emailService->clear();
        $this->recordSent($userId);
    }

    private function recordSent(?int $userId): void
    {
        if ($userId === null || $userId <= 0 || !db_connect()->fieldExists('invited_at', 'auth_identities')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('auth_identities')->where('user_id', $userId)->where('type', 'email_password')->update([
            'invited_at' => $now,
            'invitation_sent_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
