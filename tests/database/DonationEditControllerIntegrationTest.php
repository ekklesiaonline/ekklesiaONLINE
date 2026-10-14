<?php

namespace Tests\Database;

use App\Libraries\DataEncryption;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Finance\Controllers\Donations;

/**
 * Donation edit and update controller integration tests:
 * tenant scoping, input validation, encrypted amount handling,
 * locked fund protection, and audit logging.
 *
 * @internal
 */
final class DonationEditControllerIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'finance']);
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

        $this->userId = $this->insertUser('donation.treasurer', 'treasurer@example.com');
        $this->insertProfile($this->userId, 301);
        $this->injectFinanceAuth($this->userId, ['finance.view', 'donation.view', 'donation.create', 'donation.edit', 'donation.delete']);

        session()->set('selected_parish_id', 301);
        $this->withRoutes([
            ['GET', 'donations/create', '\\Finance\\Controllers\\Donation::create'],
            ['POST', 'donations/store', '\\Finance\\Controllers\\Donation::store'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Edit Form
    // -----------------------------------------------------------------------

    public function testCreateBlocksEntryWhenParishHasNoUsableFunds(): void
    {
        $output = $this->makeController()->create();

        $this->assertIsString($output);
        $this->assertStringContainsString('Fund setup required', $output);
        $this->assertStringContainsString('Contact a Parish Administrator or Finance Officer', $output);
        $this->assertStringNotContainsString('<form action="' . base_url('donations/store') . '"', $output);
    }

    public function testCreateOffersFundSetupToFinanceManager(): void
    {
        $this->injectFinanceAuth($this->userId, ['finance.manage', 'donation.create']);

        $output = $this->makeController()->create();

        $this->assertIsString($output);
        $this->assertStringContainsString(base_url('funds/create'), $output);
        $this->assertStringNotContainsString('<form action="' . base_url('donations/store') . '"', $output);
    }

    public function testCreateRouteRendersBlockedStateWithoutUsableFund(): void
    {
        $response = $this->get('donations/create');

        $response->assertOK();
        $response->assertSee('Fund setup required');
        $this->assertStringNotContainsString('<form action="' . base_url('donations/store') . '"', $response->getBody());
    }

    public function testStoreRouteRejectsInactiveFund(): void
    {
        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fundId = $this->insertFund(301, 'Inactive Fund', 'unrestricted', null, 0);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])->withBodyFormat('form')
            ->post('donations/store', [
                'date'             => '2099-09-15',
                'amount'           => '150.00',
                'fund_id'          => $fundId,
                'chart_account_id' => $coaId,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', lang('Treasurer.validFundRequired'));
        $this->assertSame(0, $this->db->table('donation')->countAllResults());
    }

    public function testEditLoadsExistingDonationView(): void
    {
        $coaId  = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fundId = $this->insertFund(301, 'General Fund');
        $donId  = $this->insertDonation(301, $fundId, '500.00', $coaId);

        $output = $this->makeController()->edit($donId);

        $this->assertIsString($output);
        $this->assertStringContainsString('500.00', $output);
        $this->assertStringContainsString('General Fund', $output);
        $this->assertStringContainsString('Pledge Offerings', $output);
    }

    public function testEditBlocksCrossParishAccess(): void
    {
        $fundId  = $this->insertFund(302, 'Foreign Fund');
        $foreign = $this->insertDonation(302, $fundId, '300.00');

        $response = $this->makeController()->edit($foreign);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // -----------------------------------------------------------------------
    // Update Action
    // -----------------------------------------------------------------------

    public function testUpdatePersistsNewValuesAndLogsAudit(): void
    {
        $coaId1 = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $coaId2 = $this->insertChartAccount('4020', 'Plate Offerings', 'income', 'offering');
        $fund1  = $this->insertFund(301, 'General Fund');
        $fund2  = $this->insertFund(301, 'Building Fund');
        $donId  = $this->insertDonation(301, $fund1, '250.00', $coaId1);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '400.00',
            'fund_id'          => $fund2,
            'category'         => 'offering',
            'chart_account_id' => $coaId2,
            'payment_method'   => 'check',
            'notes'            => 'Updated check donation',
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame($fund2, (int) $row['fund_id']);
        $this->assertSame($coaId2, (int) $row['chart_account_id']);
        $this->assertSame('offering', $row['category']);
        $this->assertSame('check', $row['payment_method']);
        $this->assertSame('2026-09-15', $row['date']);

        // Amount must be encrypted in database and equal to 400.00 when decrypted
        $plain = DataEncryption::decrypt((string) $row['amount']);
        $this->assertSame('400.00', $plain);

        // Audit log contract: amount is sensitive and automatically redacted by AuditService
        $audit = $this->db->table('audit_log')->where('action', 'donation.update')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
        $this->assertSame($donId, (int) $audit['entity_id']);
        $this->assertStringContainsString('[REDACTED]', (string) $audit['new_values']);
        $this->assertStringContainsString('offering', (string) $audit['new_values']);
        $this->assertStringContainsString('2026-09-15', (string) $audit['new_values']);
    }

    public function testUpdateRejectsLockedFund(): void
    {
        $coaId  = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fund1  = $this->insertFund(301, 'General Fund');
        $locked = $this->insertFund(301, 'Locked Fund', 'unrestricted', null, 1, 1);
        $donId  = $this->insertDonation(301, $fund1, '100.00', $coaId);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '100.00',
            'fund_id'          => $locked,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Record remains untouched
        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame($fund1, (int) $row['fund_id']);
    }

    public function testEditIncludesCurrentInactiveFundButExcludesOtherUnusableFunds(): void
    {
        $currentInactive = $this->insertFund(301, 'Historical Fund', 'unrestricted', null, 0);
        $this->insertFund(301, 'Other Inactive Fund', 'unrestricted', null, 0);
        $this->insertFund(301, 'Other Locked Fund', 'unrestricted', null, 1, 1);
        $this->insertFund(301, 'Current Operating Fund');
        $donId = $this->insertDonation(301, $currentInactive, '100.00');

        $output = $this->makeController()->edit($donId);

        $this->assertStringContainsString('Historical Fund', $output);
        $this->assertStringContainsString('Inactive', $output);
        $this->assertStringContainsString('Current Operating Fund', $output);
        $this->assertStringNotContainsString('Other Inactive Fund', $output);
        $this->assertStringNotContainsString('Other Locked Fund', $output);
    }

    public function testUpdateCanRetainCurrentInactiveHistoricalFund(): void
    {
        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fundId = $this->insertFund(301, 'Historical Inactive', 'unrestricted', null, 0);
        $donId = $this->insertDonation(301, $fundId, '100.00', $coaId);

        $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '125.00',
            'fund_id'          => $fundId,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame($fundId, (int) $row['fund_id']);
        $this->assertSame('125.00', DataEncryption::decrypt((string) $row['amount']));
    }

    public function testUpdateCanRetainCurrentLockedHistoricalFund(): void
    {
        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fundId = $this->insertFund(301, 'Historical Locked', 'unrestricted', null, 1, 1);
        $donId = $this->insertDonation(301, $fundId, '100.00', $coaId);

        $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '125.00',
            'fund_id'          => $fundId,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame($fundId, (int) $row['fund_id']);
        $this->assertSame('125.00', DataEncryption::decrypt((string) $row['amount']));
    }

    public function testUpdateRejectsInactiveFund(): void
    {
        $coaId    = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fund     = $this->insertFund(301, 'General Fund');
        $inactive = $this->insertFund(301, 'Inactive Fund', 'unrestricted', null, 0);
        $donId    = $this->insertDonation(301, $fund, '100.00', $coaId);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '100.00',
            'fund_id'          => $inactive,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($fund, (int) $this->db->table('donation')->where('id', $donId)->get()->getRowArray()['fund_id']);
    }

    public function testUpdateDerivesRestrictionFromFund(): void
    {
        $coaId     = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $general   = $this->insertFund(301, 'General Fund');
        $restricted = $this->insertFund(301, 'Roof Fund', 'restricted', 'Roof restoration');
        $donId     = $this->insertDonation(301, $general, '100.00', $coaId);

        $this->makeController('POST', [
            'date'                => '2026-09-15',
            'amount'              => '100.00',
            'fund_id'             => $restricted,
            'chart_account_id'    => $coaId,
            'is_restricted'       => '0',
            'restriction_purpose' => 'Manipulated purpose',
        ])->update($donId);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame(1, (int) $row['is_restricted']);
        $this->assertSame('Roof restoration', $row['restriction_purpose']);
    }

    public function testUpdateUsesFundDefaultIncomeAccountWhenAccountIsOmitted(): void
    {
        $defaultAccount = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fund = $this->insertFund(301, 'General Fund');
        $this->db->table('fund')->where('id', $fund)->update(['chart_account_id' => $defaultAccount]);
        $donId = $this->insertDonation(301, $fund, '100.00');

        $this->makeController('POST', [
            'date'    => '2026-09-15',
            'amount'  => '100.00',
            'fund_id' => $fund,
        ])->update($donId);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame($defaultAccount, (int) $row['chart_account_id']);
        $this->assertSame('tithe', $row['category']);
    }

    public function testUpdateAllowsExplicitActiveIncomeAccountOverride(): void
    {
        $defaultAccount = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $overrideAccount = $this->insertChartAccount('4020', 'Plate Offerings', 'income', 'offering');
        $fund = $this->insertFund(301, 'General Fund');
        $this->db->table('fund')->where('id', $fund)->update(['chart_account_id' => $defaultAccount]);
        $donId = $this->insertDonation(301, $fund, '100.00', $defaultAccount);

        $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '100.00',
            'fund_id'          => $fund,
            'chart_account_id' => $overrideAccount,
        ])->update($donId);

        $row = $this->db->table('donation')->where('id', $donId)->get()->getRowArray();
        $this->assertSame($overrideAccount, (int) $row['chart_account_id']);
        $this->assertSame('offering', $row['category']);
    }

    public function testUpdateBlocksCrossParishAccess(): void
    {
        $coaId   = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $foreign = $this->insertFund(302, 'Foreign Fund');
        $donId   = $this->insertDonation(302, $foreign, '100.00', $coaId);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '999.00',
            'fund_id'          => $foreign,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $plain = DataEncryption::decrypt((string) $this->db->table('donation')->where('id', $donId)->get()->getRowArray()['amount']);
        $this->assertSame('100.00', $plain);
    }

    public function testUpdateValidationRejectsZeroOrNegativeAmount(): void
    {
        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fund  = $this->insertFund(301, 'General Fund');
        $donId = $this->insertDonation(301, $fund, '100.00', $coaId);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '-50.00',
            'fund_id'          => $fund,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $plain = DataEncryption::decrypt((string) $this->db->table('donation')->where('id', $donId)->get()->getRowArray()['amount']);
        $this->assertSame('100.00', $plain);
    }

    public function testUpdateRequiresDonationEditPermission(): void
    {
        $this->injectFinanceAuth($this->userId, ['donation.view']);

        $coaId = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $fund  = $this->insertFund(301, 'General Fund');
        $donId = $this->insertDonation(301, $fund, '100.00', $coaId);

        $response = $this->makeController('POST', [
            'date'             => '2026-09-15',
            'amount'           => '200.00',
            'fund_id'          => $fund,
            'chart_account_id' => $coaId,
        ])->update($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $plain = DataEncryption::decrypt((string) $this->db->table('donation')->where('id', $donId)->get()->getRowArray()['amount']);
        $this->assertSame('100.00', $plain);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeController(string $method = 'GET', array $post = []): Donations
    {
        $request = service('request');
        $request->setMethod($method);

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        $controller = new Donations();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
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
            'donation_receipts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donation_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                receipt_number TEXT NOT NULL,
                issued_by INTEGER NOT NULL,
                issued_at TEXT NOT NULL,
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
            'first_name' => 'Treasurer',
            'last_name'  => 'User',
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

    private function insertChartAccount(string $code, string $name, string $type = 'income', ?string $category = null): int
    {
        // Insert through the model so its cache invalidation clears any
        // coa_* entries cached by earlier tests sharing the file cache.
        return (int) model('ChartOfAccountModel')->insert([
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'category'     => $category,
            'is_active'    => 1,
            'allow_budget' => 1,
        ]);
    }

    private function insertDonation(int $parishId, int $fundId, string $amount, ?int $chartAccountId = null): int
    {
        $encrypted = DataEncryption::encrypt($amount);
        $this->db->table('donation')->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'chart_account_id' => $chartAccountId,
            'amount'           => $encrypted,
            'date'             => '2026-09-01',
            'category'         => 'tithe',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
            'deleted_at'       => null,
        ]);

        return (int) $this->db->insertID();
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
}
