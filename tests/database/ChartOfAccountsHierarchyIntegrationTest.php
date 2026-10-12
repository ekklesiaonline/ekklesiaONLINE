<?php

namespace Tests\Database;

use App\Database\Seeds\ChartOfAccountsSeeder;
use App\Models\ChartOfAccountModel;
use App\Models\ParishModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * 3-Tier Chart of Accounts Hierarchy Integration Test:
 * - Tier 1: Canonical Master (The Episcopal Church - TEC), immutable, system-scoped.
 * - Tier 2: Diocesan Expansion (bound to specific diocese and inherited by its parishes).
 * - Tier 3: Parish Expansion (bound to specific parish only).
 *
 * @internal
 */
final class ChartOfAccountsHierarchyIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'coa']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach ([
            'chart_of_accounts',
            'parish',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testSeederPopulatesCanonicalEpiscopalAccountsAsSystemScope(): void
    {
        $seeder = new ChartOfAccountsSeeder(config('Database'), $this->db);
        $seeder->run();

        $model = new ChartOfAccountModel($this->db);
        $accounts = $model->findAll();

        $this->assertNotEmpty($accounts);
        $this->assertGreaterThanOrEqual(70, count($accounts));

        // Verify key Episcopal accounts exist with system scope
        $pledgeAccount = $model->where('code', '4120')->first();
        $this->assertNotNull($pledgeAccount);
        $this->assertSame('system', $pledgeAccount['scope_type']);
        $this->assertSame(0, (int) $pledgeAccount['scope_id']);
        $this->assertSame('income', $pledgeAccount['account_type']);

        $rectorCompAccount = $model->where('code', '7110')->first();
        $this->assertNotNull($rectorCompAccount);
        $this->assertSame('system', $rectorCompAccount['scope_type']);
        $this->assertSame(0, (int) $rectorCompAccount['scope_id']);
        $this->assertSame('expense', $rectorCompAccount['account_type']);

        $discretionaryFund = $model->where('code', '6230')->first();
        $this->assertNotNull($discretionaryFund);
        $this->assertSame('system', $discretionaryFund['scope_type']);

        // Every seeded account must have scope_type = 'system' and scope_id = 0
        foreach ($accounts as $acc) {
            $this->assertSame('system', $acc['scope_type']);
            $this->assertSame(0, (int) $acc['scope_id']);
        }
    }

    public function testThreeTierInheritanceHierarchy(): void
    {
        $model = new ChartOfAccountModel($this->db);

        // 1. Seed base system account
        $model->insert([
            'code'         => '4010',
            'name'         => 'Pledge Offerings',
            'account_type' => 'income',
            'scope_type'   => 'system',
            'scope_id'     => 0,
            'is_active'    => 1,
            'sort_order'   => 4010,
        ]);

        // 2. Insert Diocesan account for Diocese 10 and Diocese 20
        $model->insert([
            'code'         => '4010-D10',
            'name'         => 'Diocese 10 Assessment Grant',
            'account_type' => 'income',
            'scope_type'   => 'diocese',
            'scope_id'     => 10,
            'is_active'    => 1,
            'sort_order'   => 4011,
        ]);

        $model->insert([
            'code'         => '4010-D20',
            'name'         => 'Diocese 20 Assessment Grant',
            'account_type' => 'income',
            'scope_type'   => 'diocese',
            'scope_id'     => 20,
            'is_active'    => 1,
            'sort_order'   => 4011,
        ]);

        // 3. Insert Parish accounts for Parish 101 (in Diocese 10) and Parish 201 (in Diocese 20)
        $model->insert([
            'code'         => '4010-P101',
            'name'         => 'Parish 101 Special Building Fund',
            'account_type' => 'income',
            'scope_type'   => 'parish',
            'scope_id'     => 101,
            'is_active'    => 1,
            'sort_order'   => 4012,
        ]);

        $model->insert([
            'code'         => '4010-P201',
            'name'         => 'Parish 201 Altar Guild Memorial',
            'account_type' => 'income',
            'scope_type'   => 'parish',
            'scope_id'     => 201,
            'is_active'    => 1,
            'sort_order'   => 4012,
        ]);

        // Verify Diocese 10 sees system + Diocese 10 accounts, but NOT Diocese 20 or any parish accounts
        $diocese10Accounts = $model->getActiveForDiocese(10);
        $d10Codes = array_column($diocese10Accounts, 'code');
        $this->assertContains('4010', $d10Codes);
        $this->assertContains('4010-D10', $d10Codes);
        $this->assertNotContains('4010-D20', $d10Codes);
        $this->assertNotContains('4010-P101', $d10Codes);
        $this->assertNotContains('4010-P201', $d10Codes);

        // Verify Diocese 20 sees system + Diocese 20 accounts
        $diocese20Accounts = $model->getActiveForDiocese(20);
        $d20Codes = array_column($diocese20Accounts, 'code');
        $this->assertContains('4010', $d20Codes);
        $this->assertContains('4010-D20', $d20Codes);
        $this->assertNotContains('4010-D10', $d20Codes);
        $this->assertNotContains('4010-P101', $d20Codes);
        $this->assertNotContains('4010-P201', $d20Codes);

        // Verify Parish 101 inherits system + Diocese 10 + Parish 101 accounts
        $parish101Accounts = $model->getActiveForParish(101, 10);
        $p101Codes = array_column($parish101Accounts, 'code');
        $this->assertContains('4010', $p101Codes);
        $this->assertContains('4010-D10', $p101Codes);
        $this->assertContains('4010-P101', $p101Codes);
        $this->assertNotContains('4010-D20', $p101Codes);
        $this->assertNotContains('4010-P201', $p101Codes);

        // Verify Parish 201 inherits system + Diocese 20 + Parish 201 accounts
        $parish201Accounts = $model->getActiveForParish(201, 20);
        $p201Codes = array_column($parish201Accounts, 'code');
        $this->assertContains('4010', $p201Codes);
        $this->assertContains('4010-D20', $p201Codes);
        $this->assertContains('4010-P201', $p201Codes);
        $this->assertNotContains('4010-D10', $p201Codes);
        $this->assertNotContains('4010-P101', $p201Codes);
    }

    public function testMutationGuardsProtectHigherTierAccounts(): void
    {
        $model = new ChartOfAccountModel($this->db);

        $systemAcc = [
            'id'         => 1,
            'code'       => '4010',
            'name'       => 'Pledge Offerings',
            'scope_type' => 'system',
            'scope_id'   => 0,
        ];

        $dioceseAcc = [
            'id'         => 2,
            'code'       => '4010-D1',
            'name'       => 'Diocese Fund',
            'scope_type' => 'diocese',
            'scope_id'   => 10,
        ];

        $parishAcc = [
            'id'         => 3,
            'code'       => '4010-P1',
            'name'       => 'Parish Sub-Account',
            'scope_type' => 'parish',
            'scope_id'   => 101,
        ];

        // System accounts can never be mutated by diocese or parish
        $this->assertTrue($model->isSystemAccount($systemAcc));
        $this->assertFalse($model->canMutateAccount($systemAcc, 'diocese', 10));
        $this->assertFalse($model->canMutateAccount($systemAcc, 'parish', 101));

        // Diocesan account can only be mutated by Diocese 10
        $this->assertFalse($model->isSystemAccount($dioceseAcc));
        $this->assertTrue($model->canMutateAccount($dioceseAcc, 'diocese', 10));
        $this->assertFalse($model->canMutateAccount($dioceseAcc, 'diocese', 99));
        $this->assertFalse($model->canMutateAccount($dioceseAcc, 'parish', 101));

        // Parish account can only be mutated by Parish 101
        $this->assertFalse($model->isSystemAccount($parishAcc));
        $this->assertTrue($model->canMutateAccount($parishAcc, 'parish', 101));
        $this->assertFalse($model->canMutateAccount($parishAcc, 'parish', 999));
        $this->assertFalse($model->canMutateAccount($parishAcc, 'diocese', 10));
    }
}
