<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\ScopedEmail2FA;
use App\Controllers\SystemAdminService;
use App\Models\SystemSettingModel;
use App\Services\SystemSettingService;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Email-2FA gate and scoped login-challenge tests.
 *
 * Pins the invariants behind:
 *  - SystemAdminService::isUserMfaEnabled(): reads Shield's real identity
 *    type ('email_2fa', Session::ID_TYPE_EMAIL_2FA) — the legacy '2fa'
 *    literal never matched any identity Shield writes — and treats the
 *    SESSION_VERIFIED_USER_ID flag stamped by ScopedEmail2FA::verify() as
 *    proof of a completed challenge (the identity is deleted on success).
 *  - ScopedEmail2FA::appliesTo(): the security.login_2fa_scope setting
 *    scopes the login challenge to the four admin groups by default,
 *    'all' challenges everyone, 'off' disables, and unrecognized values
 *    fail back to 'admins'.
 *  - The wiring end to end: an admin password login or magic-link verify
 *    is diverted to the auth-action flow (auth/a/show) with an email_2fa
 *    identity created, while a member logs straight in.
 *
 * @internal
 */
final class LoginEmail2FATest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * User ids seeded by this test. Kept small on purpose: we never
     * reset/drop the shared `users` table because that restarts the SQLite
     * autoincrement and new low ids would collide with stale rows other
     * tests left in user_id-keyed tables.
     *
     * @var list<int>
     */
    private array $seededUserIds = [];

    /**
     * Magic-link tokens seeded by this test (scopes auth_logins cleanup —
     * failed attempts are recorded with user_id = null).
     *
     * @var list<string>
     */
    private array $seededTokens = [];

    /**
     * Anonymous-class harness exposing the private trait method under test.
     */
    private object $gate;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['form', 'security']);
        Services::reset(true);
        Services::injectMock('cache', new MockCache());

        CanonicalTestSchema::ensure($this->db);

        $this->gate = new class {
            use SystemAdminService;

            public function mfaEnabled(int $userId): bool
            {
                return $this->isUserMfaEnabled($userId);
            }
        };
    }

    protected function tearDown(): void
    {
        if ($this->seededUserIds !== []) {
            $this->db->table('auth_identities')->whereIn('user_id', $this->seededUserIds)->delete();
            $this->db->table('auth_logins')->whereIn('user_id', $this->seededUserIds)->delete();
            $this->db->table('auth_groups_users')->whereIn('user_id', $this->seededUserIds)->delete();
            $this->db->table('users')->whereIn('id', $this->seededUserIds)->delete();
            $this->seededUserIds = [];
        }
        if ($this->seededTokens !== []) {
            $this->db->table('auth_logins')->whereIn('identifier', $this->seededTokens)->delete();
            $this->seededTokens = [];
        }
        $this->db->table('system_settings')->where('setting_key', 'security.login_2fa_scope')->delete();
        session()->remove(ScopedEmail2FA::SESSION_VERIFIED_USER_ID);

        Services::reset(true);
        parent::tearDown();
    }

    // -------------------------------------------------------------------
    // isUserMfaEnabled gate
    // -------------------------------------------------------------------

    public function testEmail2faIdentitySatisfiesMfaGate(): void
    {
        $userId = $this->seedUser('mfa@example.test');

        $this->assertFalse(
            $this->gate->mfaEnabled($userId),
            'A user with no email_2fa identity must not satisfy the MFA gate.'
        );

        $this->seedIdentity($userId, Session::ID_TYPE_EMAIL_2FA, '123456');

        $this->assertTrue(
            $this->gate->mfaEnabled($userId),
            'An email_2fa identity must satisfy the MFA gate.'
        );
    }

    public function testOtherIdentityTypesDoNotSatisfyMfaGate(): void
    {
        $userId = $this->seedUser('no-mfa@example.test');

        $this->seedIdentity($userId, Session::ID_TYPE_EMAIL_PASSWORD, 'no-mfa@example.test');
        $this->seedIdentity($userId, '2fa', 'legacy');

        $this->assertFalse(
            $this->gate->mfaEnabled($userId),
            'Non-email_2fa identity types (including the legacy 2fa literal) must not satisfy the gate.'
        );
    }

    public function testSessionVerifiedFlagSatisfiesMfaGate(): void
    {
        $userId  = $this->seedUser('verified@example.test');
        $otherId = $this->seedUser('other@example.test');

        session()->set(ScopedEmail2FA::SESSION_VERIFIED_USER_ID, $userId);

        $this->assertTrue(
            $this->gate->mfaEnabled($userId),
            'A session that completed the email-2FA challenge must satisfy the gate.'
        );
        $this->assertFalse(
            $this->gate->mfaEnabled($otherId),
            'The verified flag must be bound to the specific user id.'
        );
    }

    // -------------------------------------------------------------------
    // ScopedEmail2FA::appliesTo scoping decision
    // -------------------------------------------------------------------

    public function testAuthConfigWiresScopedEmail2faAsLoginAction(): void
    {
        $this->assertSame(
            ScopedEmail2FA::class,
            config('Auth')->actions['login'] ?? null,
            'Auth.actions[login] must point at ScopedEmail2FA.'
        );
    }

    public function testDefaultScopeChallengesAdminGroupsOnly(): void
    {
        $action = new ScopedEmail2FA();

        foreach (['systemadmin', 'provinceadmin', 'dioceseadmin', 'parishadmin'] as $group) {
            $adminId = $this->seedUser("{$group}@example.test");
            $this->seedGroup($adminId, $group);

            $this->assertTrue(
                $action->appliesTo(auth()->getProvider()->findById($adminId)),
                "Default 'admins' scope must challenge {$group}."
            );
        }

        $memberId = $this->seedUser('member@example.test');
        $this->seedGroup($memberId, 'member');

        $this->assertFalse(
            $action->appliesTo(auth()->getProvider()->findById($memberId)),
            "Default 'admins' scope must not challenge lay members."
        );
    }

    public function testScopeOffDisablesChallengeForEveryone(): void
    {
        $this->setLogin2faScope('off');
        $action = new ScopedEmail2FA();

        $adminId = $this->seedUser('off-admin@example.test');
        $this->seedGroup($adminId, 'systemadmin');

        $this->assertFalse($action->appliesTo(auth()->getProvider()->findById($adminId)));
    }

    public function testScopeAllChallengesEveryone(): void
    {
        $this->setLogin2faScope('all');
        $action = new ScopedEmail2FA();

        $memberId = $this->seedUser('all-member@example.test');
        $this->seedGroup($memberId, 'member');

        $this->assertTrue($action->appliesTo(auth()->getProvider()->findById($memberId)));
    }

    public function testInvalidScopeFallsBackToAdmins(): void
    {
        $this->setLogin2faScope('bogus');
        $action = new ScopedEmail2FA();

        $adminId  = $this->seedUser('bogus-admin@example.test');
        $memberId = $this->seedUser('bogus-member@example.test');
        $this->seedGroup($adminId, 'systemadmin');
        $this->seedGroup($memberId, 'member');

        $this->assertTrue($action->appliesTo(auth()->getProvider()->findById($adminId)));
        $this->assertFalse($action->appliesTo(auth()->getProvider()->findById($memberId)));
    }

    // -------------------------------------------------------------------
    // End-to-end login flows
    // -------------------------------------------------------------------

    public function testAdminPasswordLoginStartsEmail2faChallenge(): void
    {
        $userId = $this->seedUser('admin-login@example.test');
        $this->seedGroup($userId, 'systemadmin');
        $this->seedPasswordIdentity($userId, 'admin-login@example.test');

        $response = $this->loginRequest('admin-login@example.test');

        $this->assertFalse(auth()->loggedIn(), 'Admin must not be fully logged in before the 2FA step.');
        $this->assertTrue($response->isRedirect(), 'Admin login must divert to the auth-action flow.');
        $this->assertStringContainsString('auth/a/show', (string) $response->getRedirectUrl());
        $this->assertSame(
            1,
            $this->countIdentities($userId, Session::ID_TYPE_EMAIL_2FA),
            'The login action must create an email_2fa challenge identity.'
        );
    }

    public function testMemberPasswordLoginSkipsChallenge(): void
    {
        $userId = $this->seedUser('member-login@example.test');
        $this->seedGroup($userId, 'member');
        $this->seedPasswordIdentity($userId, 'member-login@example.test');

        $response = $this->loginRequest('member-login@example.test');

        $this->assertTrue(auth()->loggedIn(), 'Member login must complete without a 2FA step.');
        $this->assertTrue($response->isRedirect());
        $this->assertStringNotContainsString('auth/a/show', (string) $response->getRedirectUrl());
        $this->assertSame(0, $this->countIdentities($userId, Session::ID_TYPE_EMAIL_2FA));
    }

    public function testAdminMagicLinkVerifyStartsEmail2faChallenge(): void
    {
        $userId = $this->seedUser('admin-magic@example.test');
        $this->seedGroup($userId, 'dioceseadmin');
        $token = 'magicadmintoken2fa';
        $this->seedMagicLinkIdentity($userId, $token);

        $response = $this->call('GET', 'login/verify-magic-link', ['token' => $token]);

        $this->assertFalse(auth()->loggedIn(), 'Admin magic-link login must pause for the 2FA step.');
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('auth/a/show', (string) $response->getRedirectUrl());
        $this->assertSame(
            1,
            $this->countIdentities($userId, Session::ID_TYPE_EMAIL_2FA),
            'The magic-link verify must start the email-2FA challenge for admins.'
        );
        // The magic-link token is single-use and consumed by the verify.
        $this->assertSame(0, $this->countIdentities($userId, Session::ID_TYPE_MAGIC_LINK));
    }

    public function testMemberMagicLinkVerifyLogsInDirectly(): void
    {
        $userId = $this->seedUser('member-magic@example.test');
        $this->seedGroup($userId, 'member');
        $token = 'magicmembertoken2fa';
        $this->seedMagicLinkIdentity($userId, $token);

        $response = $this->call('GET', 'login/verify-magic-link', ['token' => $token]);

        $this->assertTrue(auth()->loggedIn(), 'Member magic-link login must complete without 2FA.');
        $this->assertTrue($response->isRedirect());
        $this->assertStringNotContainsString('auth/a/show', (string) $response->getRedirectUrl());
        $this->assertSame(0, $this->countIdentities($userId, Session::ID_TYPE_EMAIL_2FA));
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function loginRequest(string $email)
    {
        return $this->withHeaders([csrf_header() => csrf_hash()])
            ->call('POST', 'login', ['email' => $email, 'password' => 'TestPass123!']);
    }

    private function seedUser(string $email, int $active = 1): int
    {
        $this->db->table('users')->insert([
            'username'   => strstr($email, '@', true),
            'email'      => $email,
            'active'     => $active,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId                = (int) $this->db->insertID();
        $this->seededUserIds[] = $userId;

        return $userId;
    }

    private function seedGroup(int $userId, string $group): void
    {
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedIdentity(int $userId, string $type, string $secret): void
    {
        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'secret'     => $secret,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedPasswordIdentity(int $userId, string $email): void
    {
        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret'     => $email,
            'secret2'    => password_hash('TestPass123!', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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
     * Write the scope override then inject a fresh SystemSettingService so
     * the model's request-level value cache cannot go stale between
     * scenarios within one test.
     */
    private function setLogin2faScope(?string $scope): void
    {
        $this->db->table('system_settings')->where('setting_key', 'security.login_2fa_scope')->delete();

        if ($scope !== null) {
            $this->db->table('system_settings')->insert([
                'setting_key'   => 'security.login_2fa_scope',
                'setting_value' => $scope,
                'data_type'     => 'string',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        Services::injectMock('systemSettings', new SystemSettingService(new SystemSettingModel()));
    }

    private function countIdentities(int $userId, string $type): int
    {
        return (int) $this->db->table('auth_identities')
            ->where('type', $type)
            ->where('user_id', $userId)
            ->countAllResults();
    }
}
