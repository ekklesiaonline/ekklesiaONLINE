<?php

use App\Controllers\BaseController;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

class ElevatedAdminScopeLabelTestController extends BaseController
{
    public function scopeForTest($user): ?array
    {
        return $this->resolveElevatedAdminScopeLabel($user);
    }
}

/**
 * Covers the /profile "pending" + "no parish affiliation" bug for elevated
 * admins: system/province/diocese admins have no single parish_id by design,
 * so BaseController::resolveElevatedAdminScopeLabel() must resolve what they
 * actually administer instead of the page falling back to "no affiliation".
 *
 * @internal
 */
final class ElevatedAdminScopeLabelIntegrationTest extends CIUnitTestCase
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

    public function testSystemAdminScopeIsSystemWide(): void
    {
        $userId = $this->insertUser('scope.system', 'scope.system@example.org');
        $this->insertProfile($userId, null, null);

        $controller = new ElevatedAdminScopeLabelTestController();
        $scope = $controller->scopeForTest($this->fakeUser($userId, 'systemadmin'));

        $this->assertNotNull($scope);
        $this->assertSame('system', $scope['level']);
        $this->assertSame(lang('Profile.systemAdminScope'), $scope['name']);
    }

    public function testDioceseAdminScopeResolvesDioceseNameFromProfile(): void
    {
        $provinceId = $this->insertProvince('Scope Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Scope Diocese');
        $userId = $this->insertUser('scope.diocese', 'scope.diocese@example.org');
        $this->insertProfile($userId, $provinceId, $dioceseId);

        $controller = new ElevatedAdminScopeLabelTestController();
        $scope = $controller->scopeForTest($this->fakeUser($userId, 'dioceseadmin'));

        $this->assertNotNull($scope);
        $this->assertSame('diocese', $scope['level']);
        $this->assertSame('Scope Diocese', $scope['name']);
    }

    public function testProvinceAdminScopeResolvesProvinceNameFromProfile(): void
    {
        $provinceId = $this->insertProvince('Scope Province Two');
        $userId = $this->insertUser('scope.province', 'scope.province@example.org');
        $this->insertProfile($userId, $provinceId, null);

        $controller = new ElevatedAdminScopeLabelTestController();
        $scope = $controller->scopeForTest($this->fakeUser($userId, 'provinceadmin'));

        $this->assertNotNull($scope);
        $this->assertSame('province', $scope['level']);
        $this->assertSame('Scope Province Two', $scope['name']);
    }

    public function testRegularMemberHasNoAdminScope(): void
    {
        $userId = $this->insertUser('scope.member', 'scope.member@example.org');
        $this->insertProfile($userId, null, null);

        $controller = new ElevatedAdminScopeLabelTestController();
        $scope = $controller->scopeForTest($this->fakeUser($userId, 'member'));

        $this->assertNull($scope);
    }

    private function fakeUser(int $userId, string $group): User
    {
        return new class($userId, $group) extends User {
            public function __construct(private int $userIdForTest, private string $groupForTest)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'scope.user.' . $userIdForTest,
                    'email' => 'scope.user.' . $userIdForTest . '@example.org',
                ]);
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array($this->groupForTest, $groups, true);
            }
        };
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProvince(string $name = 'Test Province'): int
    {
        $this->db->table($this->resolveTableName('province'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId, string $name = 'Test Diocese'): int
    {
        $this->db->table($this->resolveTableName('diocese'))->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, ?int $provinceId, ?int $dioceseId): void
    {
        $table = $this->resolveTableName('profile');
        $payload = [
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $columns = array_map('strtolower', $this->db->getFieldNames($table));
        $payload = array_intersect_key($payload, array_flip($columns));

        $this->db->table($table)->insert($payload);
    }

    private function ensureCoreTablesExist(): void
    {
        $this->ensureUsersTable();
        $this->ensureProvincesTable();
        $this->ensureDiocesesTable();
        $this->ensureProfilesTable();
    }

    private function ensureUsersTable(): void
    {
        $this->ensureSimpleTable('users', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureProvincesTable(): void
    {
        $this->ensureSimpleTable('province', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureDiocesesTable(): void
    {
        $this->ensureSimpleTable('diocese', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureProfilesTable(): void
    {
        $this->ensureSimpleTable('profile', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parish_id INTEGER NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureSimpleTable(string $base, string $schema): void
    {
        $prefixed = $this->createTableName($base);
        if (!$this->db->tableExists($prefixed)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefixed . ' ' . $schema);
        }

        if (!$this->db->tableExists($base)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $base . ' ' . $schema);
        }
    }

    private function resolveTableName(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->createTableName($base);
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }
}
