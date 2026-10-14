<?php

namespace Tests\Database;

use App\Services\FinanceReadinessService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Finance\Controllers\FundFlags as Funds;

/**
 * Fund management controller: parish isolation, validation, balances,
 * lock/activate flags, history-protected type changes and audit logging.
 *
 * @internal
 */
final class FundsControllerIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach (['parish', 'users', 'profile', 'audit_log'] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
        foreach (['user_registration_requests', 'user_session', 'user_trusted_device'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->emptyTable();
            }
        }

        $this->createFinanceTables();

        $this->seedParish(301, 'Parish 301');
        $this->seedParish(302, 'Parish 302');

        $this->userId = $this->insertUser('fund.officer', 'fund@example.com');
        $this->insertProfile($this->userId, 301);
        $this->injectFinanceAuth($this->userId, ['finance.view', 'finance.manage']);

        session()->set('selected_parish_id', 301);
        $this->withRoutes([
            ['POST', 'funds/lock/(:num)', '\\Finance\\Controllers\\FundFlags::lock/$1'],
            ['POST', 'funds/deactivate/(:num)', '\\Finance\\Controllers\\FundFlags::deactivate/$1'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------------

    public function testIndexListsOnlyOperationalParishFundsWithBalances(): void
    {
        $mine  = $this->insertFund(301, 'Mission Outreach');
        $other = $this->insertFund(302, 'Foreign Secret Fund');
        $this->insertDonation(301, $mine, '100.00');
        $this->insertDonation(301, $mine, '999.00', true); // soft-deleted: must not count
        $this->insertExpense(301, $mine, 30.0);

        $output = $this->makeController()->index();

        $this->assertIsString($output);
        $this->assertStringContainsString('Mission Outreach', $output);
        $this->assertStringNotContainsString('Foreign Secret Fund', $output);
        $this->assertStringContainsString('70.00', $output);
        $this->assertStringNotContainsString('1,069.00', $output);
        $this->assertNotNull($other);
    }

    public function testIndexHidesInactiveFundsByDefault(): void
    {
        $this->insertFund(301, 'Active Fund');
        $this->insertFund(301, 'Dormant Fund', 'unrestricted', null, 0);

        $default = $this->makeController()->index();
        $this->assertStringContainsString('Active Fund', $default);
        $this->assertStringNotContainsString('Dormant Fund', $default);

        $this->setRequestGet(['status' => 'all']);
        $all = $this->makeController()->index();
        $this->assertStringContainsString('Dormant Fund', $all);
    }

    public function testFinanceReadinessDistinguishesMissingInactiveLockedAndUsableFunds(): void
    {
        $this->seedParish(303, 'Parish 303');
        $this->seedParish(304, 'Parish 304');
        $this->seedParish(305, 'Parish 305');
        $this->insertFund(303, 'Inactive Fund', 'unrestricted', null, 0);
        $this->insertFund(304, 'Locked Fund', 'unrestricted', null, 1, 1);
        $this->insertFund(305, 'Operating Fund');

        $service = new FinanceReadinessService($this->db);

        $this->assertSame('no_funds', $service->readinessForParish(302)['status']);
        $this->assertSame('no_active_funds', $service->readinessForParish(303)['status']);
        $this->assertSame('all_active_funds_locked', $service->readinessForParish(304)['status']);
        $this->assertSame('ready', $service->readinessForParish(305)['status']);
    }

    public function testDefaultFundProvisioningIsIdempotentAndAudited(): void
    {
        $service = new FinanceReadinessService($this->db);

        $first = $service->provisionDefaultFund(302, $this->userId);
        $second = $service->provisionDefaultFund(302, $this->userId);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, $this->db->table('fund')->where('parish_id', 302)->countAllResults());

        $fund = $this->db->table('fund')->where('parish_id', 302)->get()->getRowArray();
        $this->assertSame('Unrestricted Operating Fund', $fund['name']);
        $this->assertSame('unrestricted', $fund['fund_type']);
        $this->assertSame(1, (int) $fund['is_active']);
        $this->assertSame(0, (int) $fund['is_locked']);
        $this->assertSame($this->userId, (int) $fund['created_by']);
        $this->assertSame(1, $this->db->table('audit_log')->where('action', 'fund.provision_default')->countAllResults());
    }

    public function testDefaultProvisioningDoesNotOverrideExistingInactiveFund(): void
    {
        $this->insertFund(302, 'Archived Fund', 'unrestricted', null, 0);

        $result = (new FinanceReadinessService($this->db))->provisionDefaultFund(302, $this->userId);

        $this->assertFalse($result['created']);
        $this->assertSame(1, $this->db->table('fund')->where('parish_id', 302)->countAllResults());
    }

    // -----------------------------------------------------------------------
    // Create
    // -----------------------------------------------------------------------

    public function testStoreCreatesParishScopedFundAndLogsAudit(): void
    {
        $response = $this->makeController('POST', [
            'name'                => 'Roof Restoration',
            'description'         => 'Cathedral roof',
            'fund_type'           => 'restricted',
            'restriction_purpose' => 'Roof only',
        ])->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $fund = $this->db->table('fund')->where('name', 'Roof Restoration')->get()->getRowArray();
        $this->assertNotNull($fund);
        $this->assertSame(301, (int) $fund['parish_id']);
        $this->assertSame('restricted', $fund['fund_type']);
        $this->assertSame('Roof only', $fund['restriction_purpose']);
        $this->assertSame(1, (int) $fund['is_active']);
        $this->assertSame(0, (int) $fund['is_locked']);
        $this->assertSame($this->userId, (int) $fund['created_by']);

        $audit = $this->db->table('audit_log')->where('action', 'fund.create')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
        $this->assertSame((int) $fund['id'], (int) $audit['entity_id']);
    }

    public function testStoreClearsPurposeForUnrestrictedFund(): void
    {
        $this->makeController('POST', [
            'name'                => 'General Operations',
            'fund_type'           => 'unrestricted',
            'restriction_purpose' => 'should be dropped',
        ])->store();

        $fund = $this->db->table('fund')->where('name', 'General Operations')->get()->getRowArray();
        $this->assertNotNull($fund);
        $this->assertNull($fund['restriction_purpose']);
    }

    public function testStoreRequiresPurposeForRestrictedFund(): void
    {
        $response = $this->makeController('POST', [
            'name'      => 'No Purpose Fund',
            'fund_type' => 'permanent',
        ])->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(0, $this->db->table('fund')->where('name', 'No Purpose Fund')->countAllResults());
    }

    public function testStoreRejectsDuplicateNameWithinParishButAllowsOtherParish(): void
    {
        $this->insertFund(301, 'Youth Fund');
        $this->insertFund(302, 'Seminary Fund');

        $this->makeController('POST', ['name' => 'Youth Fund', 'fund_type' => 'unrestricted'])->store();
        $this->assertSame(1, $this->db->table('fund')->where('parish_id', 301)->where('name', 'Youth Fund')->countAllResults());

        // Same name used in another parish is fine.
        $this->makeController('POST', ['name' => 'Seminary Fund', 'fund_type' => 'unrestricted'])->store();
        $this->assertSame(1, $this->db->table('fund')->where('parish_id', 301)->where('name', 'Seminary Fund')->countAllResults());
    }

    public function testStoreRequiresManagePermission(): void
    {
        $this->injectFinanceAuth($this->userId, ['finance.view']);

        $this->makeController('POST', ['name' => 'Sneaky Fund', 'fund_type' => 'unrestricted'])->store();

        $this->assertSame(0, $this->db->table('fund')->where('name', 'Sneaky Fund')->countAllResults());
    }

    // -----------------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------------

    public function testUpdateChangesFundDetailsAndLogsAudit(): void
    {
        $id = $this->insertFund(301, 'Old Name');

        $response = $this->makeController('POST', [
            'name'      => 'New Name',
            'fund_type' => 'unrestricted',
        ])->update($id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('New Name', $this->db->table('fund')->where('id', $id)->get()->getRowArray()['name']);
        $this->assertNotNull($this->db->table('audit_log')->where('action', 'fund.update')->get()->getRowArray());
    }

    public function testUpdateBlocksTypeChangeWhenFundHasTransactions(): void
    {
        $id = $this->insertFund(301, 'Has History');
        $this->insertExpense(301, $id, 10.0);

        $this->makeController('POST', [
            'name'                => 'Has History',
            'fund_type'           => 'restricted',
            'restriction_purpose' => 'Whatever',
        ])->update($id);

        $this->assertSame('unrestricted', $this->db->table('fund')->where('id', $id)->get()->getRowArray()['fund_type']);
    }

    public function testUpdateAllowsRenameOfFundWithTransactions(): void
    {
        $id = $this->insertFund(301, 'Has History');
        $this->insertExpense(301, $id, 10.0);

        $this->makeController('POST', ['name' => 'Renamed History', 'fund_type' => 'unrestricted'])->update($id);

        $this->assertSame('Renamed History', $this->db->table('fund')->where('id', $id)->get()->getRowArray()['name']);
    }

    public function testEditAndUpdateBlockCrossParishAccess(): void
    {
        $foreign = $this->insertFund(302, 'Foreign Fund');

        $this->assertInstanceOf(RedirectResponse::class, $this->makeController()->edit($foreign));

        $this->makeController('POST', ['name' => 'Hijacked', 'fund_type' => 'unrestricted'])->update($foreign);
        $this->assertSame('Foreign Fund', $this->db->table('fund')->where('id', $foreign)->get()->getRowArray()['name']);
    }

    public function testLockedFundCannotBeEdited(): void
    {
        $id = $this->insertFund(301, 'Frozen', 'unrestricted', null, 1, 1);

        $this->assertInstanceOf(RedirectResponse::class, $this->makeController()->edit($id));

        $this->makeController('POST', ['name' => 'Thawed Name', 'fund_type' => 'unrestricted'])->update($id);
        $this->assertSame('Frozen', $this->db->table('fund')->where('id', $id)->get()->getRowArray()['name']);
    }

    // -----------------------------------------------------------------------
    // Status flags
    // -----------------------------------------------------------------------

    public function testLockUnlockActivateDeactivateToggleFlagsAndAudit(): void
    {
        $id = $this->insertFund(301, 'Toggle Fund');
        $this->insertFund(301, 'Backup Fund');

        $this->makeController('POST')->lock($id);
        $this->assertSame(1, (int) $this->fundRow($id)['is_locked']);

        $this->makeController('POST')->unlock($id);
        $this->assertSame(0, (int) $this->fundRow($id)['is_locked']);

        $this->makeController('POST')->deactivate($id);
        $this->assertSame(0, (int) $this->fundRow($id)['is_active']);

        $this->makeController('POST')->activate($id);
        $this->assertSame(1, (int) $this->fundRow($id)['is_active']);

        foreach (['fund.lock', 'fund.unlock', 'fund.deactivate', 'fund.activate'] as $action) {
            $this->assertSame(1, $this->db->table('audit_log')->where('action', $action)->countAllResults(), $action);
        }
    }

    public function testCannotLockLastUsableFund(): void
    {
        $id = $this->insertFund(301, 'Only Usable Fund');
        $this->insertFund(301, 'Inactive Fund', 'unrestricted', null, 0);
        $this->insertFund(301, 'Locked Fund', 'unrestricted', null, 1, 1);

        $this->makeController('POST')->lock($id);

        $this->assertSame(0, (int) $this->fundRow($id)['is_locked']);
        $this->assertSame(0, $this->db->table('audit_log')->where('action', 'fund.lock')->countAllResults());
    }

    public function testCannotDeactivateLastUsableFund(): void
    {
        $id = $this->insertFund(301, 'Only Usable Fund');

        $this->makeController('POST')->deactivate($id);

        $this->assertSame(1, (int) $this->fundRow($id)['is_active']);
        $this->assertSame(0, $this->db->table('audit_log')->where('action', 'fund.deactivate')->countAllResults());
    }

    public function testLockRouteBlocksLastUsableFund(): void
    {
        $id = $this->insertFund(301, 'Route Only Fund');

        $response = $this->withHeaders([csrf_header() => csrf_hash()])->withBodyFormat('form')
            ->post('funds/lock/' . $id);

        $response->assertRedirectTo('/funds');
        $this->assertSame(0, (int) $this->fundRow($id)['is_locked']);
    }

    public function testDeactivateRouteSucceedsWhenAnotherUsableFundExists(): void
    {
        $id = $this->insertFund(301, 'Route Target Fund');
        $this->insertFund(301, 'Route Backup Fund');

        $response = $this->withHeaders([csrf_header() => csrf_hash()])->withBodyFormat('form')
            ->post('funds/deactivate/' . $id);

        $response->assertRedirectTo('/funds');
        $this->assertSame(0, (int) $this->fundRow($id)['is_active']);
        $this->assertSame(1, $this->db->table('audit_log')->where('action', 'fund.deactivate')->where('entity_id', $id)->countAllResults());
    }

    public function testFlagChangesBlockCrossParishAccess(): void
    {
        $foreign = $this->insertFund(302, 'Foreign Fund');

        $this->makeController('POST')->lock($foreign);
        $this->makeController('POST')->deactivate($foreign);

        $row = $this->fundRow($foreign);
        $this->assertSame(0, (int) $row['is_locked']);
        $this->assertSame(1, (int) $row['is_active']);
    }

    // -----------------------------------------------------------------------
    // Chart of Accounts Linkage
    // -----------------------------------------------------------------------

    public function testStoreWithChartAccountIdPersistsAndAudits(): void
    {
        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income');

        $response = $this->makeController('POST', [
            'name'             => 'Pledge Fund',
            'fund_type'        => 'unrestricted',
            'chart_account_id' => $coaId,
        ])->store();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $fund = $this->db->table('fund')->where('name', 'Pledge Fund')->get()->getRowArray();
        $this->assertNotNull($fund);
        $this->assertSame($coaId, (int) $fund['chart_account_id']);

        $audit = $this->db->table('audit_log')->where('action', 'fund.create')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertStringContainsString((string) $coaId, (string) $audit['new_values']);
    }

    public function testStoreRejectsNonIncomeDefaultAccount(): void
    {
        $expenseAccountId = $this->insertChartAccount('7010', 'Program Expense', 'expense');

        $this->makeController('POST', [
            'name'             => 'Invalid Default Fund',
            'fund_type'        => 'unrestricted',
            'chart_account_id' => $expenseAccountId,
        ])->store();

        $this->assertSame(0, $this->db->table('fund')->where('name', 'Invalid Default Fund')->countAllResults());
    }

    public function testUpdateWithChartAccountIdPersistsAndAudits(): void
    {
        $id    = $this->insertFund(301, 'Unlinked Fund');
        $coaId = $this->insertChartAccount('4020', 'Plate Offerings', 'income');

        $response = $this->makeController('POST', [
            'name'             => 'Unlinked Fund',
            'fund_type'        => 'unrestricted',
            'chart_account_id' => $coaId,
        ])->update($id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $fund = $this->db->table('fund')->where('id', $id)->get()->getRowArray();
        $this->assertSame($coaId, (int) $fund['chart_account_id']);

        $audit = $this->db->table('audit_log')->where('action', 'fund.update')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertStringContainsString((string) $coaId, (string) $audit['new_values']);
    }

    // -----------------------------------------------------------------------
    // Export CSV
    // -----------------------------------------------------------------------

    public function testExportStreamsCsvAndAudits(): void
    {
        $fundId = $this->insertFund(301, 'Exportable Fund');
        $this->insertDonation(301, $fundId, '250.00');
        $this->insertExpense(301, $fundId, 50.0);

        $response = $this->makeController()->export();

        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();

        $this->assertStringContainsString('Exportable Fund', $body);
        $this->assertStringContainsString('250.00', $body);
        $this->assertStringContainsString('50.00', $body);
        $this->assertStringContainsString('200.00', $body);

        $audit = $this->db->table('audit_log')->where('action', 'fund.export_csv')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
    }

    // -----------------------------------------------------------------------
    // View Ledger
    // -----------------------------------------------------------------------

    public function testViewDisplaysFundLedgerDonationsAndExpenses(): void
    {
        $fundId = $this->insertFund(301, 'Ledger Fund', 'restricted', 'Organ repair');
        $this->insertDonation(301, $fundId, '500.00');
        $this->insertExpense(301, $fundId, 120.0);

        $output = $this->makeController()->view($fundId);

        $this->assertIsString($output);
        $this->assertStringContainsString('Ledger Fund', $output);
        $this->assertStringContainsString('Organ repair', $output);
        $this->assertStringContainsString('500.00', $output);
        $this->assertStringContainsString('120.00', $output);
        $this->assertStringContainsString('380.00', $output);
    }

    public function testViewBlocksCrossParishAccess(): void
    {
        $foreign = $this->insertFund(302, 'Foreign Fund');

        $response = $this->makeController()->view($foreign);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeController(string $method = 'GET', array $post = []): Funds
    {
        $request = service('request');
        $request->setMethod($method);

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        $controller = new Funds();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function setRequestGet(array $get): void
    {
        service('request')->setGlobal('get', $get);
    }

    private function fundRow(int $id): array
    {
        return $this->db->table('fund')->where('id', $id)->get()->getRowArray();
    }

    private function createFinanceTables(): void
    {
        $prefix = $this->db->getPrefix();

        $definitions = [
            'fund' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                fund_type TEXT NOT NULL DEFAULT \'unrestricted\',
                restriction_purpose TEXT NULL,
                chart_account_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                is_locked INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'donation' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                fund_id INTEGER NULL,
                user_id INTEGER NULL,
                amount TEXT NOT NULL,
                date TEXT NOT NULL,
                category TEXT NOT NULL,
                chart_account_id INTEGER NULL,
                payment_method TEXT NULL,
                notes TEXT NULL,
                is_restricted INTEGER NULL,
                restriction_purpose TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'chart_of_accounts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL,
                name TEXT NOT NULL,
                account_type TEXT NOT NULL,
                category TEXT NULL,
                description TEXT NULL,
                allow_budget INTEGER NOT NULL DEFAULT 1,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'expense' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                fund_id INTEGER NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                category TEXT NOT NULL,
                chart_account_id INTEGER NULL,
                date TEXT NOT NULL,
                approved_by INTEGER NULL,
                notes TEXT NULL,
                is_capital INTEGER NULL,
                receipt_path TEXT NULL,
                receipt_original_name TEXT NULL,
                receipt_mime_type TEXT NULL,
                receipt_file_size INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
        ];

        foreach ($definitions as $table => $definition) {
            $this->db->query('DROP TABLE IF EXISTS "' . $prefix . $table . '"');
            $this->db->query('CREATE TABLE "' . $prefix . $table . '" ' . $definition);
        }
    }

    private function seedParish(int $id, string $name): void
    {
        $this->db->table('parish')->insert([
            'id'         => $id,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'first_name' => 'Fund',
            'last_name'  => 'Officer',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertFund(
        int $parishId,
        string $name,
        string $type = 'unrestricted',
        ?string $purpose = null,
        int $active = 1,
        int $locked = 0
    ): int {
        $this->db->table('fund')->insert([
            'parish_id'           => $parishId,
            'name'                => $name,
            'fund_type'           => $type,
            'restriction_purpose' => $purpose,
            'is_active'           => $active,
            'is_locked'           => $locked,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDonation(int $parishId, int $fundId, string $amount, bool $deleted = false): void
    {
        $this->db->table('donation')->insert([
            'parish_id'  => $parishId,
            'fund_id'    => $fundId,
            'amount'     => $amount,
            'date'       => '2026-09-01',
            'category'   => 'offering',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => $deleted ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function insertExpense(int $parishId, int $fundId, float $amount): void
    {
        $this->db->table('expense')->insert([
            'parish_id'   => $parishId,
            'fund_id'     => $fundId,
            'description' => 'Test expense',
            'amount'      => $amount,
            'category'    => 'other',
            'date'        => '2026-09-02',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function injectFinanceAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
            public function __construct(private int $userIdForTest, private array $perms)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'finance.' . $userIdForTest;
                $this->attributes['email']    = 'finance.' . $userIdForTest . '@example.com';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (in_array($permission, $this->perms, true)) {
                        return true;
                    }
                }

                return false;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('finance', $groups, true);
            }
        };

        $auth = new class ($fakeUser, $userId) extends Auth {
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

        Services::injectMock('auth', $auth);
    }

    private function insertChartAccount(string $code, string $name, string $type = 'income'): int
    {
        $this->db->table('chart_of_accounts')->insert([
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'is_active'    => 1,
            'allow_budget' => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}
