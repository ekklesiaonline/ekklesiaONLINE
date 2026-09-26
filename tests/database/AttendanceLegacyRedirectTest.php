<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * The legacy full-roster attendance page (GET /attendance/service/{id})
 * redirects to the search-first manual flow so the page payload stays
 * bounded on large parishes.
 *
 * @internal
 */
final class AttendanceLegacyRedirectTest extends CIUnitTestCase
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
        $this->withRoutes([
            ['GET', 'attendance/service/(:num)', '\\Worship\\Controllers\\Attendance::service/$1'],
        ]);
    }

    protected function tearDown(): void
    {
        // Rows persist on the shared :memory: connection — leave none behind.
        foreach (['attendance', 'service', 'parish_affiliation', 'profile', 'users', 'parish'] as $table) {
            try {
                $this->db->table($table)->emptyTable();
            } catch (\Throwable $e) {
                // Table may not exist in this execution order.
            }
        }

        Services::reset(true);

        parent::tearDown();
    }

    public function testLegacyServiceAttendanceRedirectsToManual(): void
    {
        $parishId = $this->insertParish('Redirect Parish');
        $userId = $this->insertUser('attendance.redirect.user');
        $this->insertProfile($userId, $parishId);
        $this->insertAffiliation($userId, $parishId);
        $this->injectAttendanceAuth($userId, ['attendance.mark']);

        $serviceId = $this->insertService($parishId);

        $response = $this->get('attendance/service/' . $serviceId);

        $response->assertRedirectTo(base_url('attendance/manual?service_id=' . $serviceId));
    }

    public function testLegacyServiceAttendanceRedirectsToServicesWhenOutOfScope(): void
    {
        $parishId = $this->insertParish('Scope Parish');
        $otherParishId = $this->insertParish('Other Parish');
        $userId = $this->insertUser('attendance.scope.user');
        $this->insertProfile($userId, $parishId);
        $this->insertAffiliation($userId, $parishId);
        $this->injectAttendanceAuth($userId, ['attendance.mark']);

        $foreignServiceId = $this->insertService($otherParishId);

        $response = $this->get('attendance/service/' . $foreignServiceId);

        $response->assertRedirectTo(base_url('services'));
    }

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
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

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId): void
    {
        $payload = [
            'user_id' => $userId,
            'first_name' => 'Attendance',
            'last_name' => 'User',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $columns = array_map('strtolower', $this->db->getFieldNames('profile'));
        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        }

        $this->db->table('profile')->insert($payload);
    }

    private function insertAffiliation(int $userId, int $parishId): void
    {
        $payload = [
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'membership_status' => 'member',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table('parish_affiliation')->insert($payload);
    }

    private function insertService(int $parishId): int
    {
        $columns = array_map('strtolower', $this->db->getFieldNames('service'));

        $payload = [
            'parish_id' => $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('service_date', $columns, true)) {
            $payload['service_date'] = date('Y-m-d');
        }
        if (in_array('date', $columns, true)) {
            $payload['date'] = date('Y-m-d');
        }
        if (in_array('service', $columns, true)) {
            $payload['service'] = 'Sunday Worship';
        }

        $this->db->table('service')->insert($payload);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<int,string> $permissions
     */
    private function injectAttendanceAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class($userId, $permissions) extends User {
            /**
             * @param array<int,string> $permissions
             */
            public function __construct(private int $testUserId, private array $permissions)
            {
                parent::__construct([
                    'id' => $testUserId,
                    'username' => 'attendance.legacy.' . $testUserId,
                    'email' => 'attendance.legacy.' . $testUserId . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (!in_array($permission, $this->permissions, true)) {
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

    private function ensureTablesExist(): void
    {
        if (!$this->db->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->db->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS parish (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                diocese_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->db->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->db->tableExists('parish_affiliation')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS parish_affiliation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                membership_status TEXT DEFAULT "visitor",
                is_member INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!$this->db->tableExists('service')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS service (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                service_date TEXT NULL,
                service_time TEXT NULL,
                service TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }
}
