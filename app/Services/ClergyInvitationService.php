<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserIdentityModel;

/**
 * ClergyInvitationService
 *
 * Sends a diocesan clergy invitation: a one-click magic-link token email.
 * The invitee lands on the set-password flow (force_reset=1 identity) and
 * MemberAccessService flips profile.portal_access invited → enabled after a
 * successful login. Uses the same token pattern as
 * ScopedMagicLinkController::loginAction.
 */
class ClergyInvitationService
{
    /**
     * Issue a fresh magic-link token and send the invitation email.
     * Stamps auth_identities.invitation_sent_at on success.
     */
    public function send(int $userId, string $recipientEmail): void
    {
        $user = auth()->getProvider()->findById($userId);
        if ($user === null || $recipientEmail === '') {
            throw new \RuntimeException('CLERGY_INVITATION_TARGET_MISSING');
        }

        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            $this->recordSent($userId);
            return;
        }

        $scopeService = new EmailScopeService();
        $scope        = $scopeService->resolveScopeForUser($userId);
        $email        = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);

        $email->setTo($recipientEmail);
        $email->setSubject(lang('Clergy.invitationSubject'));
        $email->setMessage(view('App\Views\emails\clergy_invitation', [
            'token' => $token,
            'user'  => $user,
        ], ['debug' => false]));

        if ($email->send(false) === false) {
            log_message('error', '[ClergyInvitationService] send failed; code={code}', [
                'code' => EmailFailureClassifier::classify((string) $email->printDebugger(['headers'])),
            ]);
            throw new \RuntimeException('CLERGY_INVITATION_EMAIL_FAILED');
        }

        $email->clear();
        $this->recordSent($userId);
    }

    private function recordSent(int $userId): void
    {
        if (! db_connect()->fieldExists('invitation_sent_at', 'auth_identities')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->update([
                'invitation_sent_at' => $now,
                'updated_at'         => $now,
            ]);
    }
}
