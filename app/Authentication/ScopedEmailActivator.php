<?php

declare(strict_types=1);

namespace App\Authentication;

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

        $code = $this->createIdentity($user);

        /** @var IncomingRequest $request */
        $request = service('request');

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send the email with scoped From identity, system transport.
        $scopeService = new EmailScopeService();
        $scope        = $scopeService->resolveScopeForPendingUser((int) $user->id);
        $mailer       = $scopeService->systemMailer(['overrides' => ['mailType' => 'html']]);

        $sender = $scopeService->senderConfig($scope);
        if ($sender['from_email'] !== '') {
            $mailer->setFrom($sender['from_email'], $sender['from_name']);
        }

        $mailer->setTo($userEmail);
        $mailer->setSubject(lang('Auth.emailActivateSubject'));
        $mailer->setMessage($this->view(
            setting('Auth.views')['action_email_activate_email'],
            ['code' => $code, 'user' => $user, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'date' => $date],
            ['debug' => false],
        ));

        if ($mailer->send(false) === false) {
            throw new RuntimeException('Cannot send email for user: ' . $user->email . "\n" . $mailer->printDebugger(['headers']));
        }

        // Clear the email
        $mailer->clear();

        // Display the info page
        return $this->view(setting('Auth.views')['action_email_activate_show'], ['user' => $user]);
    }
}
