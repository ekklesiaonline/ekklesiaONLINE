<?php

namespace Tests\Database;

use App\Controllers\SystemChartOfAccounts;
use App\Models\ChartOfAccountModel;
use App\Services\ChartAccountCategoryService;
use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * System Administrator Chart of Accounts CRUD Integration Test.
 *
 * @internal
 */
final class SystemChartOfAccountsIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = db_connect();
        SystemSettingsTestHelper::createCoreAuthTables($db);
        SystemSettingsTestHelper::createAuditLogTable($db);
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'coa']);
        Services::reset(true);
        Factories::reset();

        CanonicalTestSchema::ensure($this->db);

        foreach ([
            'chart_of_accounts',
            'chart_account_category',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        (new ChartAccountCategoryService($this->db))->seedDefaults();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        Factories::reset();
        parent::tearDown();
    }

    public function testIndexRedirectsWhenNotAuthenticated(): void
    {
        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('/'), $response->getHeaderLine('Location'));
    }

    public function testIndexRendersForSystemAdmin(): void
    {
        $userId = $this->insertUser('system.coa.index', 'system.coa.index@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $model = new ChartOfAccountModel($this->db);
        $model->insert([
            'code' => '4010',
            'name' => 'Test Pledge Offerings',
            'account_type' => 'income',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 4010,
            'category_id' => $this->categoryIdForKey('income.without_restrictions.contributions'),
            'category' => 'income.without_restrictions.contributions',
        ]);

        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertIsString($response);
        $this->assertStringContainsString('4010', $response);
        $this->assertStringContainsString(lang('ChartAccounts.systemScopeNotice'), $response);
    }

    public function testCreateRendersForSystemAdmin(): void
    {
        $userId = $this->insertUser('system.coa.create', 'system.coa.create@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $controller = $this->makeController('get');
        $response = $controller->create();

        $this->assertIsString($response);
        $this->assertStringContainsString(lang('ChartAccounts.pageHeadingSystemCreate'), $response);
    }

    public function testStoreCreatesSystemAccount(): void
    {
        $userId = $this->insertUser('system.coa.store', 'system.coa.store@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $categoryId = $this->categoryIdForKey('expense.management.utilities');

        $controller = $this->makeController('post', [
            'code' => '8999',
            'name' => 'System Test Utilities',
            'account_type' => 'expense',
            'description' => 'Test system account',
            'sort_order' => 8999,
            'allow_budget' => '1',
            'is_active' => '1',
            'parent_account_id' => '',
            'category_id' => (string) $categoryId,
        ]);

        $response = $controller->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/chart-of-accounts'), $response->getHeaderLine('Location'));

        $model = new ChartOfAccountModel($this->db);
        $account = $model->where('code', '8999')->first();
        $this->assertNotNull($account);
        $this->assertSame('system', $account['scope_type']);
        $this->assertSame(0, (int) $account['scope_id']);
        $this->assertSame('expense', $account['account_type']);
        $this->assertSame('expense.management.utilities', $account['category']);
    }

    public function testStoreRejectsDuplicateSystemCode(): void
    {
        $userId = $this->insertUser('system.coa.duplicate', 'system.coa.duplicate@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $categoryId = $this->categoryIdForKey('expense.management.utilities');

        $model = new ChartOfAccountModel($this->db);
        $model->insert([
            'code' => '8999',
            'name' => 'Existing Utilities',
            'account_type' => 'expense',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 8999,
            'category_id' => $categoryId,
            'category' => 'expense.management.utilities',
        ]);

        $controller = $this->makeController('post', [
            'code' => '8999',
            'name' => 'Duplicate Utilities',
            'account_type' => 'expense',
            'sort_order' => 8999,
            'category_id' => (string) $categoryId,
        ]);

        $response = $controller->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testStoreCreatesHierarchicalSubAccount(): void
    {
        $userId = $this->insertUser('system.coa.hierarchy', 'system.coa.hierarchy@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $model = new ChartOfAccountModel($this->db);
        $parentId = $model->insert([
            'code' => '8110',
            'name' => 'Electricity & Gas',
            'account_type' => 'expense',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 8110,
            'category_id' => $this->categoryIdForKey('expense.management.utilities'),
            'category' => 'expense.management.utilities',
        ], true);

        $categoryId = $this->categoryIdForKey('expense.management.utilities');

        $controller = $this->makeController('post', [
            'code' => '8110-01',
            'name' => 'Sanctuary Electricity',
            'account_type' => 'expense',
            'sort_order' => 8111,
            'parent_account_id' => (string) $parentId,
            'category_id' => (string) $categoryId,
        ]);

        $response = $controller->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/chart-of-accounts'), $response->getHeaderLine('Location'));

        $child = $model->where('code', '8110-01')->first();
        $this->assertNotNull($child);
        $this->assertSame((int) $parentId, (int) $child['parent_account_id']);
        $this->assertSame('system', $child['scope_type']);
    }

    public function testUpdateEditsSystemAccount(): void
    {
        $userId = $this->insertUser('system.coa.update', 'system.coa.update@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $model = new ChartOfAccountModel($this->db);
        $accountId = $model->insert([
            'code' => '8998',
            'name' => 'Original Name',
            'account_type' => 'expense',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 8998,
            'category_id' => $this->categoryIdForKey('expense.management.utilities'),
            'category' => 'expense.management.utilities',
        ], true);

        $categoryId = $this->categoryIdForKey('expense.management.facilities');

        $controller = $this->makeController('post', [
            'code' => '8998-UPD',
            'name' => 'Updated Name',
            'account_type' => 'expense',
            'sort_order' => 8998,
            'category_id' => (string) $categoryId,
        ]);

        $response = $controller->update($accountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/chart-of-accounts'), $response->getHeaderLine('Location'));

        $updated = $model->find($accountId);
        $this->assertSame('8998-UPD', $updated['code']);
        $this->assertSame('Updated Name', $updated['name']);
        $this->assertSame('expense.management.facilities', $updated['category']);
    }

    public function testToggleChangesActiveStatus(): void
    {
        $userId = $this->insertUser('system.coa.toggle', 'system.coa.toggle@example.org');
        $this->insertProfile($userId, null, null, 'System', 'CoA');
        $this->injectSystemAdminAuth($userId);

        $model = new ChartOfAccountModel($this->db);
        $accountId = $model->insert([
            'code' => '8997',
            'name' => 'Toggle Test',
            'account_type' => 'expense',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 8997,
            'category_id' => $this->categoryIdForKey('expense.management.utilities'),
            'category' => 'expense.management.utilities',
        ], true);

        $controller = $this->makeController('post');
        $response = $controller->toggle($accountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/chart-of-accounts'), $response->getHeaderLine('Location'));

        $toggled = $model->find($accountId);
        $this->assertSame(0, (int) $toggled['is_active']);
    }

    public function testNonSystemAdminCannotAccess(): void
    {
        $userId = $this->insertUser('diocesan.coa', 'diocesan.coa@example.org');
        $this->insertProfile($userId, null, 10, 'Diocesan', 'CoA');
        $this->injectDiocesanAdminAuth($userId);

        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('/'), $response->getHeaderLine('Location'));
    }

    public function testModelSystemMutationGuard(): void
    {
        $model = new ChartOfAccountModel($this->db);

        $systemAcc = [
            'id' => 1,
            'code' => '4010',
            'name' => 'Pledge Offerings',
            'scope_type' => 'system',
            'scope_id' => 0,
        ];

        $this->assertTrue($model->isSystemAccount($systemAcc));
        $this->assertTrue($model->canMutateSystemAccount($systemAcc));
        $this->assertFalse($model->canMutateAccount($systemAcc, 'diocese', 10));
        $this->assertFalse($model->canMutateAccount($systemAcc, 'parish', 101));
    }

    public function testNewSystemAccountIsInheritedByDioceseAndParish(): void
    {
        $model = new ChartOfAccountModel($this->db);

        $model->insert([
            'code' => '8995',
            'name' => 'Inherited System Account',
            'account_type' => 'expense',
            'scope_type' => 'system',
            'scope_id' => 0,
            'is_active' => 1,
            'sort_order' => 8995,
            'category_id' => $this->categoryIdForKey('expense.management.utilities'),
            'category' => 'expense.management.utilities',
        ]);

        $dioceseAccounts = $model->getActiveForDiocese(10);
        $dioceseCodes = array_column($dioceseAccounts, 'code');
        $this->assertContains('8995', $dioceseCodes);

        $parishAccounts = $model->getActiveForParish(101, 10);
        $parishCodes = array_column($parishAccounts, 'code');
        $this->assertContains('8995', $parishCodes);
    }

    private function makeController(string $method, array $postData = []): SystemChartOfAccounts
    {
        $request = service('request');
        $request->setMethod($method);

        if ($postData !== []) {
            $request->setGlobal('post', $postData);
            $request->setGlobal('request', $postData);
        }

        $controller = new SystemChartOfAccounts();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function injectSystemAdminAuth(int $userId): void
    {
        $fakeUser = $this->makeFakeUser($userId, 'systemadmin', ['system.admin']);
        $auth = $this->makeFakeAuth($fakeUser, $userId);
        Services::injectMock('auth', $auth);
    }

    private function injectDiocesanAdminAuth(int $userId): void
    {
        $fakeUser = $this->makeFakeUser($userId, 'dioceseadmin', ['diocese.admin']);
        $auth = $this->makeFakeAuth($fakeUser, $userId);
        Services::injectMock('auth', $auth);
    }

    private function makeFakeUser(int $userId, string $group, array $allowedPermissions): User
    {
        return new class($userId, $group, $allowedPermissions) extends User {
            public function __construct(private int $userIdForTest, private string $groupForTest, private array $allowedPermissionsForTest)
            {
                $this->attributes['id'] = $userIdForTest;
                $this->attributes['username'] = $this->groupForTest . '.' . $userIdForTest;
                $this->attributes['email'] = $this->groupForTest . '.' . $userIdForTest . '@example.org';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (!in_array($permission, $this->allowedPermissionsForTest, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array($this->groupForTest, $groups, true);
            }
        };
    }

    private function makeFakeAuth(User $fakeUser, int $userId): Auth
    {
        return new class($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
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
    }

    private function categoryIdForKey(string $key): int
    {
        $row = $this->db->table('chart_account_category')->where('category_key', $key)->get()->getRowArray();

        return $row ? (int) $row['id'] : 0;
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
