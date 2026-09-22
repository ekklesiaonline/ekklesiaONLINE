<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Authentication\AuthenticatorInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Regression test for the "scope_type must be one of: parish,diocese,province"
 * error when saving a new leadership position from a parish admin's portal.
 *
 * @internal
 */
final class LeadershipPositionsScopeIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form']);
        Services::reset(true);
        $this->ensureTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testStoringParishScopeLeadershipPositionSucceeds(): void
    {
        $dioceseId = $this->insertDiocese('Leadership Test Diocese');
        $parishId = $this->insertParish($dioceseId, 'Leadership Test Parish');

        $adminId = $this->insertUser('leadership.scope.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId);

        $memberId = $this->insertUser('leadership.scope.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBodyFormat('form')->post('leadership/store', [
            'user_id' => $memberId,
            'scope_type' => 'parish',
            'title' => 'Parish Warden',
        ]);

        $this->assertSame('/leadership', parse_url((string) $response->getRedirectUrl(), PHP_URL_PATH), (string) $response->getRedirectUrl());
        $this->assertNull(session('error'));

        $stored = $this->db->table('leadership_position')
            ->where('user_id', $memberId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($stored);
        $this->assertSame('parish', $stored['scope_type']);
        $this->assertSame($parishId, (int) $stored['parish_id']);
    }

    private function withCsrfHeaders(array $headers): array
    {
        $headers[csrf_header()] = csrf_hash();

        return $headers;
    }

    private function injectLeadershipAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'leadership.scope.user.' . $idForTest,
                    'email' => 'leadership.scope.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission !== 'member.edit') {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('parishadmin', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
            {
                parent::__construct($config);
            }

            public function getAuthenticator(): AuthenticatorInterface
            {
                return new class($this->fakeUser) implements AuthenticatorInterface {
                    public function __construct(private User $fakeUser) {}

                    public function attempt(array $credentials): Result
                    {
                        return new Result(['success' => false, 'reason' => 'not-used-in-test']);
                    }

                    public function check(array $credentials): Result
                    {
                        return new Result(['success' => false, 'reason' => 'not-used-in-test']);
                    }

                    public function loggedIn(): bool
                    {
                        return true;
                    }

                    public function login(User $user): void {}

                    public function loginById($userId): void {}

                    public function logout(): void {}

                    public function getUser(): ?User
                    {
                        return $this->fakeUser;
                    }

                    public function recordActiveDate(): void {}

                    public function isPending(): bool
                    {
                        return false;
                    }

                    public function getPendingMessage(): string
                    {
                        return '';
                    }

                    public function startUpAction(string $type, User $user): bool
                    {
                        return false;
                    }
                };
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): ?int
            {
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertDiocese(string $name): int
    {
        $provinceId = $this->insertProvince($name . ' Province');

        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('province')->insert([
            'name' => $name,
            'province_number' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => strtolower($username) . '@example.test',
            'secret2' => password_hash('StrongPass123', PASSWORD_DEFAULT),
            'force_reset' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function upsertProfile(int $userId, int $parishId, int $dioceseId): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureTablesExist(): void
    {
        $tables = $this->db->listTables();
        $prefix = $this->db->getPrefix();

        if (!in_array($prefix . 'province', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'province (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'diocese', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'diocese (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'parish', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                diocese_id INTEGER NULL,
                name TEXT NOT NULL,
                email TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'auth_identities', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                secret TEXT NULL,
                secret2 TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'profile', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                province_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'leadership_position', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'leadership_position (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                scope_type TEXT NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                province_id INTEGER NULL,
                title TEXT NOT NULL,
                role_code TEXT NULL,
                employment_type TEXT NULL,
                role_category TEXT NULL,
                clergy_order TEXT NULL,
                active INTEGER DEFAULT 1,
                started_at TEXT NULL,
                ended_at TEXT NULL,
                notes TEXT NULL,
                appointed_by_user_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }
}
