<?php

declare(strict_types=1);

use App\Authentication\ScopedEmailActivator;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * Behavioral tests for the per-recipient activation-email send cap in
 * ScopedEmailActivator::show().
 *
 * @internal
 */
final class ScopedEmailActivatorTest extends CIUnitTestCase
{
    private const RECIPIENT = 'Victim@Example.Test';

    /** Default daily cap (env('security.activationEmailMaxPerDay') unset). */
    private const DAILY_CAP = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Services::injectMock('cache', new MockCache());
        // The code-entry form view resolves url_to('auth-action-verify'),
        // so the route collection must be populated before the auth
        // service mock replaces the real one.
        Services::routes()->loadRoutes();
        session()->remove('activation_email_sent_at');
    }

    protected function tearDown(): void
    {
        $this->resetServices();
        parent::tearDown();
    }

    public function testShowSkipsSendAndStillRendersFormWhenRecipientCapReached(): void
    {
        $this->mockPendingUser(self::RECIPIENT);

        // Exhaust the per-recipient daily allowance (the Nth send already
        // happened within the rolling window).
        $cache = Services::cache();
        $key   = 'activation_email_rl_' . md5(strtolower(self::RECIPIENT));
        $cache->save($key, ['count' => self::DAILY_CAP, 'reset' => time() + 86400], 86400);

        $activator = $this->newActivator();

        $output = $activator->show();

        // The activation form is still rendered — the cap is invisible.
        $this->assertStringContainsString('name="token"', $output);
        $this->assertStringContainsString('auth/a/verify', $output);

        // No new code identity was created, so the mailer was never reached.
        $this->assertSame(0, $activator->identityCreates);

        // The rejected attempt still counts against the allowance.
        $this->assertSame(self::DAILY_CAP + 1, $cache->get($key)['count']);
    }

    public function testShowBelowCapProceedsToIssueCode(): void
    {
        $this->mockPendingUser(self::RECIPIENT);

        $activator = $this->newActivator();

        // A fresh recipient is below the cap, so show() proceeds past the
        // cap check and issues a code. Everything after createIdentity
        // (scope resolution, mailer build, SMTP send) is environment-bound
        // and may throw; what matters is that the send path was entered.
        try {
            $activator->show();
        } catch (Throwable) {
            // Mailer/DB failures are fine — the send attempt itself is
            // what this assertion covers.
        }

        $this->assertSame(1, $activator->identityCreates);
    }

    /**
     * Pending-user fixture: a detached Shield User entity behind a mocked
     * session authenticator, so no DB round-trip is needed.
     */
    private function mockPendingUser(string $email): User
    {
        $user        = new User();
        $user->id    = 4242;
        $user->email = $email;

        $authenticator = $this->createMock(Session::class);
        $authenticator->method('getPendingUser')->willReturn($user);

        $auth = $this->createMock(Auth::class);
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('getAuthenticator')->willReturn($authenticator);

        Services::injectMock('auth', $auth);

        return $user;
    }

    /**
     * Test double that counts createIdentity() calls. The mailer is only
     * ever built after createIdentity() succeeds, so a zero count proves
     * the send was skipped entirely.
     */
    private function newActivator(): ScopedEmailActivator
    {
        return new class extends ScopedEmailActivator {
            public int $identityCreates = 0;

            public function createIdentity(User $user): string
            {
                $this->identityCreates++;

                return '123456';
            }
        };
    }
}
