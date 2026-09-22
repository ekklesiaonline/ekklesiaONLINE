<?php

use App\Controllers\SystemAudit;
use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class SystemAuditControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = db_connect();
        SystemSettingsTestHelper::createCoreAuthTables($db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($db);
        SystemSettingsTestHelper::createAuditLogTable($db);
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);
        putenv('monitoring.auditDbEnabled=true');
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        Factories::reset('config');

        parent::tearDown();
    }

    public function testIndexRendersForSystemAdmin(): void
    {
        $userId = $this->insertUser('system.audit.viewer', 'system.audit.viewer@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Audit');
        $this->injectSystemAdminAuth($userId);

        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertIsString($response);
        $this->assertStringContainsString(lang('System.sectionAuditLog'), $response);
    }

    public function testDataReturnsPaginatedJsonAndRespectsFilters(): void
    {
        $userId = $this->insertUser('system.audit.data', 'system.audit.data@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Audit');
        $this->injectSystemAdminAuth($userId);

        $this->seedAuditRows();

        $_GET = [
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'action' => 'system.province',
        ];

        $controller = $this->makeController('get');
        $response = $controller->data();

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame(1, (int) $json['draw']);
        $this->assertSame(1, (int) $json['recordsFiltered']);
        $this->assertSame('system.province.created', $json['data'][0]['action']);
    }

    public function testDataFiltersByDateRange(): void
    {
        $userId = $this->insertUser('system.audit.date', 'system.audit.date@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Audit');
        $this->injectSystemAdminAuth($userId);

        $this->seedAuditRows();

        $_GET = [
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'date_from' => date('Y-m-d', strtotime('+1 day')),
            'date_to' => date('Y-m-d', strtotime('+2 days')),
        ];

        $controller = $this->makeController('get');
        $response = $controller->data();

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame(0, (int) $json['recordsFiltered']);
    }

    private function seedAuditRows(): void
    {
        $now = date('Y-m-d H:i:s');

        db_connect()->table('audit_log')->insert([
            'user_id' => 1,
            'action' => 'system.province.created',
            'entity_type' => 'province',
            'entity_id' => 10,
            'created_at' => $now,
        ]);

        db_connect()->table('audit_log')->insert([
            'user_id' => 1,
            'action' => 'auth.login.failed',
            'entity_type' => 'user',
            'entity_id' => 0,
            'created_at' => $now,
        ]);
    }

    private function makeController(string $method): SystemAudit
    {
        $request = service('request');
        $request->setMethod($method);

        $controller = new SystemAudit();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function injectSystemAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'system.admin.' . $userIdForTest;
                $this->attributes['email']      = 'system.admin.' . $userIdForTest . '@example.org';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'system.admin') {
                        continue;
                    }

                    return false;
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('systemadmin', $groups, true);
            }
        };

        $auth = new class($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
                // Avoid parent constructor side-effects.
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'email' => $email,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $this->db->insertID();
        $this->db->table($this->resolveTableName('auth_identities'))->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $userId;
    }

    private function insertProfile(int $userId, ?int $provinceId, ?int $dioceseId, string $firstName, string $lastName): void
    {
        $this->db->table($this->resolveTableName('profile'))->insert([
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveTableName(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->db->getPrefix() . $base;
    }
}
