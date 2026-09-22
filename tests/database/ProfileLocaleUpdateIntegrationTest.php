<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ProfileLocaleUpdateIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testProfileLocaleUpdateIsReflectedOnNextRenderedPage(): void
    {
        $this->ensureCoreTablesExist();

        $provinceId = $this->insertProvince();
        $dioceseId = $this->insertDiocese($provinceId);
        $parishId = $this->insertParish($dioceseId);
        $userId = $this->insertUser();
        $profileId = $this->insertProfile($userId, $parishId, 'en');

        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userId)
            {
                parent::__construct([
                    'id' => $userId,
                    'username' => 'locale.integration.user',
                    'email' => 'locale.integration@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
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

        $request = service('request');
        $request->setLocale('es');
        $request->setGlobal('post', [
            'first_name' => 'Locale',
            'last_name' => 'Integration',
            'locale' => 'fr',
        ]);

        $response = service('response');
        $logger = service('logger');

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, $response, $logger);
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $profile = $this->db->table($this->resolveTableName('profile'))->where('id', $profileId)->get()->getRowArray();
        $this->assertSame('fr', (string) ($profile['locale'] ?? ''), 'Profile locale was not updated to French.');

        $nextRequest = service('request');
        $nextRequest->setLocale('es');
        $nextResponse = service('response');

        $nextController = new \MemberPortal\Controllers\Profile();
        $nextController->initController($nextRequest, $nextResponse, $logger);
        $html = (string) $nextController->index();

        $this->assertStringContainsString('Mon profil', $html);
        $this->assertStringNotContainsString('Mi perfil', $html);
    }

    private function ensureCoreTablesExist(): void
    {
        $usersTable = $this->createTableName('users');
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $usersTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('users'), 'email', 'TEXT NULL');

        $parishesTable = $this->createTableName('parishes');
        if (!$this->tableExists('parishes')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $parishesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('parishes'), 'diocese_id', 'INTEGER NULL');

        $provincesTable = $this->createTableName('provinces');
        if (!$this->tableExists('provinces')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $provincesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                sort_order INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $provinceTable = $this->createTableName('province');
        if (!$this->tableExists('province')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $provinceTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                sort_order INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('province'), 'province_number', 'TEXT NULL');
        $this->ensureColumnExists($this->resolveTableName('province'), 'sort_order', 'INTEGER NULL');

        $diocesesTable = $this->createTableName('dioceses');
        if (!$this->tableExists('dioceses')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $diocesesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $dioceseTable = $this->createTableName('diocese');
        if (!$this->tableExists('diocese')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $dioceseTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('dioceses'), 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('dioceses'), 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->resolveTableName('diocese'), 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('diocese'), 'province_id', 'INTEGER NULL');

        $profilesTable = $this->createTableName('profile');
        if (!$this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $profilesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                middle_name TEXT NULL,
                last_name TEXT NULL,
                phone TEXT NULL,
                phone_hash TEXT NULL,
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
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $resolvedProfiles = $this->resolveTableName('profile');
        $this->ensureColumnExists($resolvedProfiles, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'middle_name', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'phone', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'phone_hash', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'address', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'city', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'state', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'zip', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'country', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'birth_date', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'baptism_date', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'spiritual_gifts', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'locale', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'profile_picture', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'diocese_id', 'INTEGER NULL');
        $prefixedProfiles = $this->createTableName('profile');
        $this->ensureColumnExists($prefixedProfiles, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'middle_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'phone', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'phone_hash', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'address', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'city', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'state', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'zip', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'country', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'birth_date', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'baptism_date', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'spiritual_gifts', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'profile_picture', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'locale', 'TEXT NULL');
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function insertProvince(): int
    {
        $this->db->table($this->resolveTableName('provinces'))->insert([
            'name' => 'Integration Province',
            'province_number' => 'P1',
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId): int
    {
        $this->db->table($this->resolveTableName('dioceses'))->insert([
            'province_id' => $provinceId,
            'name' => 'Integration Diocese',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId): int
    {
        $this->db->table($this->resolveTableName('parishes'))->insert([
            'name' => 'Locale Integration Parish',
            'diocese_id' => $dioceseId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => 'locale.integration.user',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId, string $locale): int
    {
        $profileTable = $this->resolveTableName('profile');
        $this->db->resetDataCache();
        $columns = array_map('strtolower', $this->db->getFieldNames($profileTable) ?: []);

        $payload = [
            'user_id' => $userId,
            'first_name' => 'Locale',
            'last_name' => 'Integration',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('locale', $columns, true)) {
            $payload['locale'] = $locale;
        }
        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        }

        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        }

        $this->db->table($profileTable)->insert($payload);

        return (int) $this->db->insertID();
    }

    private function resolveTableName(string $base): string
    {
        $prefixed = $this->createTableName($base);
        if ($this->db->getPrefix() !== '') {
            return $prefixed;
        }

        return $base;
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $hasColumn = false;
        try {
            $columns = $this->db->getFieldNames($table);
            $hasColumn = in_array($column, $columns, true);
        } catch (\Throwable $e) {
            // Fall through and attempt ALTER defensively.
        }

        if ($hasColumn) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            $this->db->resetDataCache();
        } catch (\Throwable $e) {
            // Ignore if the current DB driver cannot alter this table in test mode.
        }
    }
}
