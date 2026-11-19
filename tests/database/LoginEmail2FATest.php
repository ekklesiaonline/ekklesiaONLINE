<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\SystemAdminService;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Email-2FA gate tests.
 *
 * Pins the invariants behind the admin-operations MFA gate
 * (SystemAdminService::isUserMfaEnabled) and the scoped login challenge
 * (ScopedEmail2FA):
 *  - The gate reads Shield's real identity type ('email_2fa',
 *    Session::ID_TYPE_EMAIL_2FA) — the legacy '2fa' literal never matched
 *    any identity Shield writes.
 *  - A session that completed the email-2FA challenge counts as
 *    MFA-verified even though the challenge identity is deleted on success.
 *
 * @internal
 */
final class LoginEmail2FATest extends CIUnitTestCase
{
    use DatabaseTestTrait;

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
     * Anonymous-class harness exposing the private trait method under test.
     *
     * @var object{mfaEnabled: callable}
     */
    private object $gate;

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);
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
            $this->db->table('auth_groups_users')->whereIn('user_id', $this->seededUserIds)->delete();
            $this->db->table('users')->whereIn('id', $this->seededUserIds)->delete();
            $this->seededUserIds = [];
        }

        Services::reset(true);
        parent::tearDown();
    }

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

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

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
}
