<?php

use App\Models\ChartOfAccountModel;
use App\Models\DonationModel;
use App\Models\FundModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class DonationCreationChurchAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTestSchema();
    }

    public function testChurchAdminDonationCreationAcceptsChartDerivedCategory(): void
    {
        $churchId = $this->insertChurch('St. Michael Church');
        $userId = $this->insertChurchAdminWithProfile($churchId, 'church.admin');

        $chartAccountId = $this->insertIncomeChartAccount('4250', 'Special Offering Income', 'special_offering');
        $fundId = $this->insertFund($churchId, $chartAccountId, 'General Fund');

        $donationId = $this->attemptDonationCreationFlow(
            $churchId,
            [
                'fund_id' => $fundId,
                'user_id' => $userId,
                'date' => '2026-03-31',
                'amount' => '1250.00',
                'chart_account_id' => $chartAccountId,
                'payment_method' => 'cash',
                'notes' => 'Integration test donation',
            ]
        );

        $this->assertGreaterThan(0, $donationId);

        $storedDonation = $this->db->table('donation')->where('id', $donationId)->get()->getRowArray();
        $this->assertNotNull($storedDonation);
        $this->assertSame($churchId, (int) $storedDonation['parish_id']);
        $this->assertSame($fundId, (int) $storedDonation['fund_id']);
        $this->assertSame($chartAccountId, (int) $storedDonation['chart_account_id']);
        $this->assertSame('special_offering', $storedDonation['category']);
        $this->assertStringStartsWith('enc:v1:', (string) ($storedDonation['amount'] ?? ''));

        $decryptedDonation = model(DonationModel::class)->find($donationId);
        $this->assertIsArray($decryptedDonation);
        $this->assertSame(1250.0, (float) ($decryptedDonation['amount'] ?? 0));
    }

    public function testChurchAdminCannotCreateDonationWithFundFromAnotherChurch(): void
    {
        $churchId = $this->insertChurch('Holy Trinity Church');
        $otherChurchId = $this->insertChurch('St. Nicholas Church');
        $userId = $this->insertChurchAdminWithProfile($churchId, 'church.admin.blocked');

        $chartAccountId = $this->insertIncomeChartAccount('4260', 'Designated Income', 'donation');
        $foreignFundId = $this->insertFund($otherChurchId, $chartAccountId, 'Foreign Fund');

        $beforeCount = $this->db->table('donation')->countAllResults();

        $donationId = $this->attemptDonationCreationFlow(
            $churchId,
            [
                'fund_id' => $foreignFundId,
                'user_id' => $userId,
                'date' => '2026-03-31',
                'amount' => '75.00',
                'chart_account_id' => $chartAccountId,
                'payment_method' => 'cash',
                'notes' => 'Must be rejected due to church scope mismatch',
            ]
        );

        $afterCount = $this->db->table('donation')->countAllResults();

        $this->assertSame(0, $donationId);
        $this->assertSame($beforeCount, $afterCount);
    }

    /**
     * Mirrors the critical guard path in Treasurer\\Controllers\\Donations::store().
     */
    private function attemptDonationCreationFlow(int $churchId, array $payload): int
    {
        $chartAccount = model(ChartOfAccountModel::class)
            ->where('id', (int) ($payload['chart_account_id'] ?? 0))
            ->where('is_active', 1)
            ->where('account_type', 'income')
            ->first();

        if (!is_array($chartAccount)) {
            return 0;
        }

        $fund = model(FundModel::class)->find((int) ($payload['fund_id'] ?? 0));
        if (!is_array($fund) || (int) ($fund['parish_id'] ?? 0) !== $churchId) {
            return 0;
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1) {
            return 0;
        }

        $donationModel = model(DonationModel::class);

        return (int) $donationModel->insert([
            'parish_id' => $churchId,
            'fund_id' => (int) $payload['fund_id'],
            'user_id' => $payload['user_id'] ?? null,
            'date' => (string) $payload['date'],
            'amount' => (string) $payload['amount'],
            'category' => (string) ($chartAccount['category'] ?? 'other'),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => $payload['payment_method'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ], true);
    }

    private function insertChurch(string $name): int
    {
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();

        // Satisfies BaseTenantModel scoping in this test DB.
        session()->set('selected_parish_id', $id);

        return $id;
    }

    private function insertChurchAdminWithProfile(int $churchId, string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $this->db->insertID();

        $this->db->table('auth_groups_users')->insert([
            'group' => 'churchadmin',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $churchId,
            'first_name' => 'Church',
            'last_name' => 'Admin',
            'membership_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function insertIncomeChartAccount(string $code, string $name, string $category): int
    {
        $this->db->table('chart_of_accounts')->insert([
            'code' => $code,
            'name' => $name,
            'account_type' => 'income',
            'category' => $category,
            'description' => null,
            'allow_budget' => 1,
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFund(int $churchId, int $chartAccountId, string $name): int
    {
        $this->db->table('fund')->insert([
            'parish_id' => $churchId,
            'name' => $name,
            'description' => 'Unrestricted operating fund',
            'fund_type' => 'unrestricted',
            'restriction_purpose' => null,
            'chart_account_id' => $chartAccountId,
            'is_active' => 1,
            'is_locked' => 0,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function rebuildTestSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('donation', true);
        $forge->dropTable('fund', true);
        $forge->dropTable('chart_of_accounts', true);
        $forge->dropTable('profile', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'group' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INTEGER', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => false],
            // Matches production: profile.parish_id is nullable (elevated admins have
            // no single parish). A NOT NULL definition here previously leaked into
            // every other test file sharing this in-memory SQLite connection within
            // the same PHPUnit run, since this table is created with "IF NOT EXISTS"
            // and never dropped between test classes.
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'province_id' => ['type' => 'INTEGER', 'null' => true],
            'diocese_id' => ['type' => 'INTEGER', 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'account_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'allow_budget' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'is_active' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INTEGER', 'null' => false, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('chart_of_accounts', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'fund_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'restriction_purpose' => ['type' => 'TEXT', 'null' => true],
            'chart_account_id' => ['type' => 'INTEGER', 'null' => true],
            'is_active' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'is_locked' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'created_by' => ['type' => 'INTEGER', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('fund', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => false],
            'fund_id' => ['type' => 'INTEGER', 'null' => false],
            'user_id' => ['type' => 'INTEGER', 'null' => true],
            'amount' => ['type' => 'TEXT', 'null' => false],
            'date' => ['type' => 'DATE'],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'offering'],
            'chart_account_id' => ['type' => 'INTEGER', 'null' => true],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('donation', true);
    }
}
