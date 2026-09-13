<?php

namespace App\Services;

class AdminInvitationService
{
    public function send(string $recipientEmail, ?int $userId = null): void
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            $this->recordSent($userId);
            return;
        }

        helper('email');

        $emailService = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $emailService->setTo($recipientEmail);
        $emailService->setSubject(lang('System.adminInvitationSubject'));

        $passwordSetupUrl = base_url('login/magic-link');
        $emailService->setMessage(
            '<p>' . esc(lang('System.adminInvitationGreeting')) . '</p>'
                . '<p>' . esc(lang('System.adminInvitationBody')) . '</p>'
                . '<p><a href="' . esc($passwordSetupUrl, 'attr') . '">' . esc($passwordSetupUrl) . '</a></p>'
        );

        if ($emailService->send(false) === false) {
            log_message('error', $emailService->printDebugger(['headers']));
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
