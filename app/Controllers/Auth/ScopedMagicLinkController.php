<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Libraries\EmailFailureClassifier;
use App\Services\EmailScopeService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Controllers\MagicLinkController;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

/**
 * ScopedMagicLinkController
 *
 * Overrides Shield's magic-link login email send to use the scoped From
 * identity (resolved from the user's parish/diocese/province) while keeping
 * transport on the system SMTP account.  The loginAction body is copied from
 * the parent because Shield does not provide a hook around the send call.
 */
class ScopedMagicLinkController extends MagicLinkController
{
    public function loginAction(): RedirectResponse
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        // Validate email format
        $rules = $this->getValidationRules();
        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->route('magic-link')->with('errors', $this->validator->getErrors());
        }

        // Check if the user exists. Never reveal whether the address is
        // registered: unknown emails get the same success page and return
        // early so no token is created or email attempted.
        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($user === null) {
            return $this->displayMessage();
        }

        if ($this->magicLinkSendLimitReached((string) $user->email)) {
            // Still show the generic success page; silently drop the send.
            return $this->displayMessage();
        }

        $identityModel = model(UserIdentityModel::class);

        // Delete any previous magic-link identities
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        // Generate the code and save it as an identity
        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        /** @var IncomingRequest $request */
        $request = service('request');

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send the user an email with the code — System transport with a
        // System-aligned From mailbox, tenant display name and Reply-To.
        $scopeService = new EmailScopeService();
        $scope        = $scopeService->resolveScopeForUser((int) $user->id);
        $email        = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);

        $email->setTo($user->email);
        $email->setSubject(lang('Auth.magicLinkSubject'));
        $email->setMessage($this->view(
            setting('Auth.views')['magic-link-email'],
            ['token' => $token, 'user' => $user, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'date' => $date],
            ['debug' => false],
        ));

        if ($email->send(false) === false) {
            log_message('error', '[ScopedMagicLink] send failed; code={code}', [
                'code' => EmailFailureClassifier::classify((string) $email->printDebugger(['headers'])),
            ]);

            return redirect()->route('magic-link')->with('error', lang('Auth.unableSendEmailToUser', [$user->email]));
        }

        // Clear the email
        $email->clear();

        return $this->displayMessage();
    }

    /**
     * Per-recipient send cap: at most 3 magic-link emails per address per
     * hour, regardless of source IP. Prevents mail bombing a victim mailbox
     * by rotating IPs, which the per-IP LoginRateLimitFilter cannot stop.
     */
    private function magicLinkSendLimitReached(string $email): bool
    {
        $cache = \Config\Services::cache();
        $key   = 'magic_link_rl_' . md5(strtolower($email));

        // Single-op increment keeps concurrent sends from double-spending the
        // same allowance slot; on backends without increment support fall back
        // to a plain counter.
        if ($cache->get($key) === null) {
            $cache->save($key, 1, HOUR);
            return false;
        }

        $count = $cache->increment($key, 1);
        if ($count === false) {
            $count = (int) ($cache->get($key) ?? 0) + 1;
            $cache->save($key, $count, HOUR);
        }

        return $count > 3;
    }
}
