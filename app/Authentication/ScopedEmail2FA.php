<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Libraries\EmailFailureClassifier;
use App\Services\EmailScopeService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Actions\Email2FA;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Exceptions\RuntimeException;
use CodeIgniter\Shield\Models\UserIdentityModel;

/**
 * ScopedEmail2FA
 *
 * Login second-factor (email code) whose challenge is scoped to a
 * configurable audience instead of every account.
 *
 * Scoping: Shield calls appliesTo() (ConditionalActionInterface) from
 * Session::actionAppliesToUser() at every decision point — startUpAction(),
 * setAuthAction() and getActionTypes() — so returning false both prevents a
 * new challenge identity being created and causes stale email_2fa
 * identities to be ignored. The scope is read from
 * SystemSettingService::getLoginEmail2faScope() ('security.login_2fa_scope'):
 *   - 'admins' (default): challenge only users in the four admin groups
 *     (systemadmin, provinceadmin, dioceseadmin, parishadmin). Lay members
 *     log in without a second factor.
 *   - 'all': challenge every user at login.
 *   - 'off': challenge nobody (killswitch; pending identities are ignored).
 *
 * Mail: handle() below is copied from Email2FA::handle() because Shield
 * provides no sender hook — the 2FA code is credential-bearing mail and
 * project rules require EmailScopeService::credentialMailer() (System
 * transport, tenant display name/Reply-To). Keep in sync with upstream on
 * Shield updates.
 *
 * verify() mirrors ScopedEmailActivator::verify(): a per-session wrong-code
 * cap invalidates the challenge identity after MAX_VERIFY_ATTEMPTS misses.
 * On success it stamps SESSION_VERIFIED_USER_ID in the session: Shield's
 * checkAction() deletes the email_2fa identity, so the durable "this login
 * passed 2FA" signal for SystemAdminService::isUserMfaEnabled() is the
 * session flag, not a database row.
 */
class ScopedEmail2FA extends Email2FA implements ConditionalActionInterface
{
    /**
     * Session key holding the user_id that completed the email-2FA
     * challenge during this session's login.
     */
    public const SESSION_VERIFIED_USER_ID = 'login_2fa_verified_user_id';

    /** Maximum wrong-code entries before the challenge code is invalidated. */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /** Maximum 2FA code emails per recipient address per rolling hour. */
    private const SEND_MAX_PER_HOUR = 5;

    /** Admin-tier groups challenged when the scope is 'admins'. */
    private const ADMIN_GROUPS = [
        'systemadmin',
        'provinceadmin',
        'dioceseadmin',
        'parishadmin',
    ];

    /**
     * Decides whether this action applies to the given user.
     *
     * Called by Shield while starting or discovering pending actions; must
     * stay deterministic and free of side effects.
     */
    public function appliesTo(User $user): bool
    {
        return match (service('systemSettings')->getLoginEmail2faScope()) {
            'all'    => true,
            'admins' => $user->inGroup(...self::ADMIN_GROUPS),
            default  => false,
        };
    }

    /**
     * Shows the challenge screen. Upstream show() always rotates the code;
     * when the send cap is exhausted and a code is already pending, keep it
     * so the last emailed code stays valid (handle() will not send another).
     */
    public function show(): string
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        if (! ($this->sendLimitExhausted((string) $user->email) && $this->getUserIdentity($user) instanceof UserIdentity)) {
            $this->createIdentity($user);
        }

        return $this->view(setting('Auth.views')['action_email_2fa'], ['user' => $user]);
    }

    /**
     * Sends the 6-digit code via the scoped credential mailer.
     *
     * Body copied from Shield's Email2FA::handle() (no sender hook exists);
     * adds a per-recipient hourly send cap. When the cap trips, the last
     * emailed code remains the current identity, so the verify form is
     * re-rendered without sending.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        $email = $request->getPost('email');

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        if (empty($email) || $email !== $user->email) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.invalidEmail', [$email]));
        }

        if ($this->sendLimitReached((string) $user->email)) {
            // Keep the previously emailed code valid; just re-render the
            // code-entry form without issuing another send.
            return $this->view(setting('Auth.views')['action_email_2fa_verify']);
        }

        $identity = $this->getUserIdentity($user);

        if (! $identity instanceof UserIdentity) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.need2FA'));
        }

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = \CodeIgniter\I18n\Time::now()->toDateTimeString();

        // Credential-bearing mail: System transport with a System-aligned
        // From mailbox, tenant display name and Reply-To.
        $scopeService = new EmailScopeService();
        $scope        = $scopeService->resolveScopeForUser((int) $user->id);
        $mailer       = $scopeService->credentialMailer($scope, ['overrides' => ['mailType' => 'html']]);

        $mailer->setTo($user->email);
        $mailer->setSubject(lang('Auth.email2FASubject'));
        $mailer->setMessage($this->view(
            setting('Auth.views')['action_email_2fa_email'],
            ['code' => $identity->secret, 'user' => $user, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'date' => $date],
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

        return $this->view(setting('Auth.views')['action_email_2fa_verify']);
    }

    /**
     * Verifies the posted code with a per-session attempt cap, then stamps
     * the 2FA-verified session marker on success.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function verify(IncomingRequest $request)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $postedToken = $request->getPost('token');

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $identity    = $this->getUserIdentity($user);
        $attemptsKey = 'login_2fa_attempts_' . (int) $user->id;

        if ($identity === null || ! $authenticator->checkAction($identity, (string) $postedToken)) {
            // A missing identity (already invalidated or never issued)
            // rejects without incrementing — only real misses count.
            if ($identity !== null) {
                $attempts = (int) (session()->get($attemptsKey) ?? 0) + 1;
                session()->set($attemptsKey, $attempts);

                if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                    model(UserIdentityModel::class)->delete($identity->id);
                    session()->remove($attemptsKey);
                }
            }

            session()->setFlashdata('error', lang('Auth.invalid2FAToken'));

            return $this->view(setting('Auth.views')['action_email_2fa_verify']);
        }

        session()->remove($attemptsKey);
        session()->set(self::SESSION_VERIFIED_USER_ID, (int) $user->id);

        // Shield issues the remember-me token in attempt(), before this
        // action runs. In-scope users get no remember-me: purge it so the
        // 2FA proof cannot outlive this session.
        \App\Filters\Login2faSessionFilter::purgeRememberTokens((int) $user->id);

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Returns the pending email_2fa identity for the user.
     * (Email2FA::getIdentity() is private upstream.)
     */
    private function getUserIdentity(User $user): ?UserIdentity
    {
        return model(UserIdentityModel::class)->getIdentityByType(
            $user,
            $this->getType(),
        );
    }

    /**
     * Per-recipient send cap: at most SEND_MAX_PER_HOUR code emails per
     * address per rolling hour, regardless of source IP. Mirrors
     * ScopedMagicLinkController::magicLinkSendLimitReached().
     */
    private function sendLimitReached(string $email): bool
    {
        $cache = \Config\Services::cache();
        $key   = 'login_2fa_rl_' . md5(strtolower($email));
        $now   = time();

        // Store a fixed window deadline alongside the counter so retries
        // (including rejected ones) never extend the window. increment()
        // is avoided because FileHandler resets the TTL on every call.
        $entry = $cache->get($key);
        if (! is_array($entry) || (int) ($entry['reset'] ?? 0) <= $now) {
            $cache->save($key, ['count' => 1, 'reset' => $now + 3600], 3600);

            return false;
        }

        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        $cache->save($key, $entry, max(1, (int) $entry['reset'] - $now));

        return $entry['count'] > self::SEND_MAX_PER_HOUR;
    }

    /**
     * Read-only check of the send cap (does not consume an allowance slot).
     */
    private function sendLimitExhausted(string $email): bool
    {
        $entry = \Config\Services::cache()->get('login_2fa_rl_' . md5(strtolower($email)));

        return is_array($entry)
            && (int) ($entry['reset'] ?? 0) > time()
            && (int) ($entry['count'] ?? 0) >= self::SEND_MAX_PER_HOUR;
    }
}
