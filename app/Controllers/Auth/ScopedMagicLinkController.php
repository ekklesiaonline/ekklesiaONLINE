<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Libraries\EmailFailureClassifier;
use App\Services\EmailScopeService;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Controllers\MagicLinkController;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\LoginModel;
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
    public function loginAction(): RedirectResponse|string
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

        // Deactivated accounts (ban/dismiss sets users.active = 0) get no
        // usable link — and the identical response, so the mailbox check
        // cannot distinguish account existence from account state.
        if (! $user->active) {
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

            // Same generic page as the success path: a distinct send-failure
            // response would let callers enumerate which addresses belong to
            // registered, active accounts.
            return $this->displayMessage();
        }

        // Clear the email
        $email->clear();

        return $this->displayMessage();
    }

    /**
     * Handles the GET request from the email.
     *
     * Copied from the parent because Shield provides no hook between token
     * lookup and loginById(). Adds a deactivated/banned gate: ban and
     * dismiss flows set users.active = 0, and loginById() performs no such
     * check, which would otherwise grant a deactivated account one
     * authenticated hop (plus a 'success' row in auth_logins) until
     * RegistrationGateFilter evicts it on the next request. Single-use
     * delete and expiry semantics are unchanged.
     */
    public function verify(): RedirectResponse
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        if ($this->request->getUserAgent()->isRobot()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $token = $this->request->getGet('token');

        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        $identifier = $token ?? '';

        // No token found?
        if ($identity === null) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicTokenNotFound'));
        }

        // Delete the db entry so it cannot be used again.
        $identityModel->delete($identity->id);

        // Token expired?
        if (Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicLinkExpired'));
        }

        $user = $this->provider->findById($identity->user_id);

        // Reject deactivated or banned accounts before any login/action
        // path — the generic token error does not reveal account state.
        if (! $user instanceof User || ! $user->active || $user->isBanned()) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicTokenNotFound'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined
        if ($authenticator->hasAction($identity->user_id)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
        }

        // Start any login action that has been defined.
        if ($authenticator->startUpAction('login', $user) && $authenticator->hasAction($user->id)) {
            $this->recordLoginAttempt($identifier, true, $user->id);
            $authenticator->setPendingLoginMethod(Session::ID_TYPE_MAGIC_LINK);

            return redirect()->route('auth-action-show');
        }

        $authenticator->setPendingLoginMethod(Session::ID_TYPE_MAGIC_LINK);

        // Log the user in.
        $authenticator->loginById($identity->user_id);

        $user = $authenticator->getUser();

        $this->recordLoginAttempt($identifier, true, $user->id);

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Records a login attempt in auth_logins. Private in the parent, so the
     * same pattern is duplicated here for the verify() override.
     *
     * @param int|string|null $userId
     */
    private function recordLoginAttempt(
        string $identifier,
        bool $success,
        $userId = null,
    ): void {
        $loginModel = model(LoginModel::class);

        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId,
        );
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
            $cache->save($key, 1, 3600);
            return false;
        }

        $count = $cache->increment($key, 1);
        if ($count === false) {
            $count = (int) $cache->get($key) + 1;
            // Preserve the existing expiry so rejected requests cannot keep
            // extending the window indefinitely.
            $meta = $cache->getMetaData($key);
            $ttl  = is_array($meta) ? ($meta['expire'] ?? time() + 3600) : time() + 3600;
            $cache->save($key, $count, max(1, $ttl - time()));
        }

        return $count > 3;
    }
}
