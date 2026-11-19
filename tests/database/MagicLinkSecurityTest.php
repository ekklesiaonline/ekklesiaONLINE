<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Magic-link hardening tests.
 *
 * Pins the invariants added to ScopedMagicLinkController:
 *  - POST login/magic-link never creates a token for a deactivated account
 *    (ban/dismiss sets users.active = 0) and returns the same generic page
 *    as unknown emails, so the mailbox check cannot enumerate accounts.
 *  - A send failure returns the same generic page as a success instead of
 *    an error redirect that would reveal account existence.
 *  - GET login/verify-magic-link refuses deactivated accounts before
 *    loginById() — no authenticated hop and no 'success' auth_logins row —
 *    while keeping single-use token deletion intact.
 *
 * @internal
 */
final class MagicLinkSecurityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * User ids seeded by this test. Kept small on purpose: we never
     * reset/drop the shared `users` table because that restarts the SQLite
     * autoincrement and new low ids would collide with stale rows other
     * tests left in user_id-keyed tables (e.g. leadership_position).
     *
     * @var list<int>
     */
    private array $seededUserIds = [];

    /**
     * Magic-link tokens seeded by this test (used to scope auth_logins
     * cleanup — failed attempts are recorded with user_id = null).
     *
     * @var list<string>
     */
    private array $seededTokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        helper(['form', 'security']);
        Services::reset(true);
        Services::injectMock('cache', new MockCache());

        CanonicalTestSchema::ensure($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->seededUserIds !== [] || $this->seededTokens !== []) {
            if ($this->seededUserIds !== []) {
                $this->db->table('auth_identities')->whereIn('user_id', $this->seededUserIds)->delete();
                $this->db->table('auth_logins')->whereIn('user_id', $this->seededUserIds)->delete();
                $this->db->table('auth_groups_users')->whereIn('user_id', $this->seededUserIds)->delete();
                $this->db->table('users')->whereIn('id', $this->seededUserIds)->delete();
            }
            if ($this->seededTokens !== []) {
                $this->db->table('auth_logins')->whereIn('identifier', $this->seededTokens)->delete();
                $this->seededTokens = [];
            }
            $this->seededUserIds = [];
        }

        Services::reset(true);
        parent::tearDown();
    }

    public function testInactiveUserGetsNoTokenAndGenericPage(): void
    {
        $userId = $this->seedUser('inactive@example.test', 0);

        $response = $this->magicLinkRequest('inactive@example.test');

        $response->assertOK();
        $response->assertSee(lang('Auth.checkYourEmail'));

        $this->assertSame(
            0,
            $this->countMagicLinkIdentities($userId),
            'POST login/magic-link must not create a magic-link identity for an inactive user.'
        );
    }

    public function testActiveUserGetsTokenAndGenericPage(): void
    {
        $userId = $this->seedUser('active@example.test', 1);
        $this->mockMailer(true);

        $response = $this->magicLinkRequest('active@example.test');

        $response->assertOK();
        $response->assertSee(lang('Auth.checkYourEmail'));

        $this->assertSame(
            1,
            $this->countMagicLinkIdentities($userId),
            'Active users must still receive a magic-link token.'
        );
    }

    public function testInactiveAndUnknownResponsesAreIndistinguishable(): void
    {
        $this->seedUser('gone@example.test', 0);

        $inactive = $this->magicLinkRequest('gone@example.test');
        $unknown  = $this->magicLinkRequest('nobody@example.test');

        $inactive->assertOK();
        $unknown->assertOK();
        $unknown->assertSee(lang('Auth.checkYourEmail'));

        $this->assertSame(
            $this->sanitizeBody($inactive->getBody()),
            $this->sanitizeBody($unknown->getBody()),
            'Inactive-account and unknown-email responses must be identical.'
        );
    }

    public function testSendFailureReturnsSameGenericPageAsSuccess(): void
    {
        $this->seedUser('send-ok@example.test', 1);

        $this->mockMailer(true);
        $success = $this->magicLinkRequest('send-ok@example.test');
        $success->assertOK();
        $success->assertSee(lang('Auth.checkYourEmail'));

        $this->mockMailer(false);
        $failure = $this->magicLinkRequest('send-ok@example.test');
        $failure->assertOK();
        $failure->assertSee(lang('Auth.checkYourEmail'));

        // A send failure must NOT redirect back to the form with an error —
        // that was the enumeration channel.
        $this->assertFalse($failure->isRedirect(), 'Send failure must render the generic page, not redirect.');
        $this->assertSame(
            $this->sanitizeBody($success->getBody()),
            $this->sanitizeBody($failure->getBody()),
            'Send-failure response must match the success response.'
        );
    }

    public function testInactiveUserMagicLinkVerifyDoesNotLogIn(): void
    {
        $userId = $this->seedUser('deactivated@example.test', 0);
        $token  = 'verifytokentestinactive';
        $this->seedMagicLinkIdentity($userId, $token);

        $response = $this->call('GET', 'login/verify-magic-link', ['token' => $token]);

        $this->assertFalse(auth()->loggedIn(), 'Deactivated user must not be logged in by a magic link.');
        $this->assertTrue($response->isRedirect(), 'Verify must redirect back to the magic-link form.');
        $this->assertStringContainsString(
            'login/magic-link',
            (string) $response->getRedirectUrl()
        );

        // Single-use semantics preserved: the token is consumed on lookup.
        $this->assertSame(
            0,
            $this->countMagicLinkIdentities($userId),
            'The magic-link token must still be deleted (single-use) for a rejected login.'
        );

        // A failed attempt is recorded; no 'success' row may appear.
        $this->assertSame(
            1,
            (int) $this->db->table('auth_logins')
                ->where('id_type', Session::ID_TYPE_MAGIC_LINK)
                ->where('identifier', $token)
                ->where('success', 0)
                ->countAllResults(),
            'The rejected verify must record a failed login attempt.'
        );
        $this->assertSame(
            0,
            (int) $this->db->table('auth_logins')
                ->where('id_type', Session::ID_TYPE_MAGIC_LINK)
                ->where('identifier', $token)
                ->where('success', 1)
                ->countAllResults(),
            'No successful login may be recorded for a deactivated account.'
        );
    }

    public function testExpiredTokenIsStillRejected(): void
    {
        $userId = $this->seedUser('expired@example.test', 1);
        $token  = 'verifytokentestexpired';
        $this->seedMagicLinkIdentity($userId, $token, time() - 60);

        $response = $this->call('GET', 'login/verify-magic-link', ['token' => $token]);

        $this->assertFalse(auth()->loggedIn());
        $this->assertTrue($response->isRedirect());
        $this->assertSame(0, $this->countMagicLinkIdentities($userId));
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function magicLinkRequest(string $email)
    {
        return $this->withHeaders([csrf_header() => csrf_hash()])
            ->call('POST', 'login/magic-link', ['email' => $email]);
    }

    private function seedUser(string $email, int $active): int
    {
        $this->db->table('users')->insert([
            'username'   => strstr($email, '@', true),
            'active'     => $active,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId                 = (int) $this->db->insertID();
        $this->seededUserIds[]  = $userId;

        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret'     => $email,
            'secret2'    => password_hash('TestPass123!', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function seedMagicLinkIdentity(int $userId, string $token, ?int $expires = null): void
    {
        $this->seededTokens[] = $token;
        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => Session::ID_TYPE_MAGIC_LINK,
            'secret'     => $token,
            'expires'    => date('Y-m-d H:i:s', $expires ?? (time() + 600)),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Fake the System mailer used by EmailScopeService::credentialMailer()
     * (it resolves to the shared service('email') instance).
     */
    private function mockMailer(bool $sendResult): \CodeIgniter\Email\Email
    {
        $mailer = $this->getMockBuilder(\CodeIgniter\Email\Email::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'initialize',
                'setFrom',
                'setReplyTo',
                'setTo',
                'setSubject',
                'setMessage',
                'send',
                'clear',
                'printDebugger',
            ])
            ->getMock();
        $mailer->method('send')->willReturn($sendResult);
        $mailer->method('printDebugger')->willReturn('mock debugger output');

        Services::injectMock('email', $mailer);

        return $mailer;
    }

    private function countMagicLinkIdentities(int $userId): int
    {
        return (int) $this->db->table('auth_identities')
            ->where('type', Session::ID_TYPE_MAGIC_LINK)
            ->where('user_id', $userId)
            ->countAllResults();
    }

    /**
     * CSRF tokens are regenerated per request, so the meta tag in the auth
     * layout differs between responses; strip it before comparing bodies.
     */
    private function sanitizeBody(string $body): string
    {
        return (string) preg_replace('/content="[a-zA-Z0-9]+"/', 'content="TOKEN"', $body);
    }
}
