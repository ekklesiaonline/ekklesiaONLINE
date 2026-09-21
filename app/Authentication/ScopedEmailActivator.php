<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Libraries\EmailFailureClassifier;
use App\Services\EmailScopeService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Actions\EmailActivator;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Exceptions\LogicException;
use CodeIgniter\Shield\Exceptions\RuntimeException;
/**
 * ScopedEmailActivator
 *
 * Shield registration action that sends the activation code email using the
 * scoped From identity (parish → diocese → ... → system) while keeping
 * transport on the system SMTP account.  The body of this method is copied
 * from Shield's EmailActivator::show() because Shield does not provide a
 * sender hook.  Keep in sync with upstream on Shield updates.
 */
class ScopedEmailActivator extends EmailActivator
{
    /**
     * Minimum seconds between activation email sends for one session.
     * Shield re-invokes show() on every GET to auth/a/show, so without a
     * cooldown each page reload would generate a fresh code and email.
     */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Shows the initial screen to the user and sends the activation email
     * using the scoped sender identity.
     */
    public function show(): string
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $userEmail = $user->email;
        if ($userEmail === null) {
            throw new LogicException(
                'Email Activation needs user email address. user_id: ' . $user->id,
            );
        }

        $sentAt = (int) (session()->get('activation_email_sent_at') ?? 0);
        if ($sentAt > 0 && (time() - $sentAt) < self::RESEND_COOLDOWN_SECONDS) {
            // Within the resend cooldown: keep the previously emailed code
            // valid and just re-render the code-entry form.
            return $this->view(setting('Auth.views')['action_email_activate_show'], ['user' => $user]);
        }

        $code = $this->createIdentity($user);

        /** @var IncomingRequest $request */
        $request = service('request');

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send via the credential mailer: System transport and a
        // System-aligned From mailbox, tenant display name and Reply-To.
        $scopeService = new EmailScopeService();
        $scope        = $scopeService->resolveScopeForPendingUser((int) $user->id);

        // When the registration-request row could not be created (e.g. the
        // selected parish is missing its diocese/province chain), fall back
        // to the parish captured in the session during registration.
        $sessionParishId = (int) (session()->get('pending_registration_parish_id') ?? 0);
        if (empty($scope['parish_id']) && $sessionParishId > 0) {
            $scope = $scopeService->resolveScope(['parish_id' => $sessionParishId]);
        }
        session()->remove('pending_registration_parish_id');

        $mailer = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);

        $mailer->setTo($userEmail);
        $mailer->setSubject(lang('Auth.emailActivateSubject'));
        $mailer->setMessage($this->view(
            setting('Auth.views')['action_email_activate_email'],
            ['code' => $code, 'user' => $user, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'date' => $date],
            ['debug' => false],
        ));

        if ($mailer->send(false) === false) {
            throw new RuntimeException(
                'Cannot send email for user: ' . $user->email
                    . ' [' . EmailFailureClassifier::classify((string) $mailer->printDebugger(['headers'])) . ']'
            );
        }

        // Clear the email
        $mailer->clear();
        session()->set('activation_email_sent_at', time());

        // Display the info page
        return $this->view(setting('Auth.views')['action_email_activate_show'], ['user' => $user]);
    }
}
