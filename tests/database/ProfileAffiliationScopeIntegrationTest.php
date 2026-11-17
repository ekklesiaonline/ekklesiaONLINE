<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Security regression: POST /profile/update previously honored a raw
 * `affiliations[]` array, letting a self-registered member attach themselves
 * to ANY parish with a self-chosen membership_status (including 'member').
 * Self-service secondary affiliations must be pinned to 'visitor' and the
 * parish must resolve to a real parish row.
 *
 * @internal
 */
final class ProfileAffiliationScopeIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->ensureCoreTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testPostedMemberStatusIsPinnedToVisitor(): void
    {
        $parishA = $this->insertParish('Home Parish A');
        $parishB = $this->insertParish('Foreign Parish B');
        $userId = $this->insertUser('aff.scope.member');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => (string) $parishB, 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $row = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishB)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row, 'Secondary affiliation was not written.');
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''),
            'Self-service affiliation must not carry member status.');
    }

    public function testUnknownParishIdIsRejected(): void
    {
        $parishA = $this->insertParish('Home Parish C');
        $userId = $this->insertUser('aff.scope.badparish');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => '999999', 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $count = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', 999999)
            ->countAllResults();

        $this->assertSame(0, $count, 'No affiliation row may be written for a nonexistent parish.');
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function injectMemberAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $uid)
            {
                parent::__construct([
                    'id' => $uid,
                    'username' => 'aff.scope.user.' . $uid,
                    'email' => 'aff.scope.' . $uid . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                // member tier: dashboard.view + profile.edit only.
                return in_array('dashboard.view', $permissions, true)
                    || in_array('profile.edit', $permissions, true);
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('member', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userId)
            {
                parent::__construct($config);
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
                return $this->userId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertParish(string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId): int
    {
        $this->db->table($this->resolveTableName('profile'))->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'first_name' => 'Attach',
            'last_name' => 'Test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertPrimaryAffiliation(int $userId, int $parishId): void
    {
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => null,
            'membership_status' => 'member',
            'is_primary' => 1,
            'source' => 'system',
            'start_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureCoreTablesExist(): void
    {
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('users') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('parish') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('profile') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                middle_name TEXT NULL,
                last_name TEXT NULL,
                phone TEXT NULL,
                address TEXT NULL,
                city TEXT NULL,
                state TEXT NULL,
                zip TEXT NULL,
                country TEXT NULL,
                birth_date TEXT NULL,
                baptism_date TEXT NULL,
                spiritual_gifts TEXT NULL,
                locale TEXT NULL,
                profile_picture TEXT NULL,
                person_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
        $this->ensureColumnExists($this->resolveTableName('profile'), 'person_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'locale', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'spiritual_gifts', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'baptism_date', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'birth_date', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'profile_picture', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'phone_country_code', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('profile'), 'address_line_2', 'TEXT NULL');

        if (!$this->tableExists('parish_affiliation')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('parish_affiliation') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                membership_status TEXT NULL,
                is_primary INTEGER DEFAULT 0,
                pathway_stage TEXT NULL,
                source TEXT NULL,
                start_date TEXT NULL,
                end_date TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->tableExists('person')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('person') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NULL,
                last_name TEXT NULL,
                phone TEXT NULL,
                address TEXT NULL,
                birthdate TEXT NULL,
                email TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->tableExists('user_person')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->createTableName('user_person') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                person_id INTEGER NOT NULL,
                is_primary INTEGER DEFAULT 0,
                linked_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function resolveTableName(string $base): string
    {
        return $this->db->getPrefix() !== '' ? $this->db->getPrefix() . $base : $base;
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        try {
            $columns = $this->db->getFieldNames($table);
            if (in_array($column, $columns, true)) {
                return;
            }
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            $this->db->resetDataCache();
        } catch (\Throwable $e) {
        }
    }
}
