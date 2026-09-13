<?php

use App\Models\DonationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class DonationAggregationEncryptedTotalsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEncryptionKeys();
        $this->rebuildTestSchema();
    }

    public function testEncryptedDonationAmountsAggregateCorrectlyByCategoryFundChurchAndMonth(): void
    {
        $churchA = $this->insertChurch('Church A');
        $churchB = $this->insertChurch('Church B');

        $fundA1 = $this->insertFund($churchA, 'General Fund');
        $fundA2 = $this->insertFund($churchA, 'Missions Fund');
        $fundB1 = $this->insertFund($churchB, 'Building Fund');

        $model = model(DonationModel::class);

        $nowMonthDate = date('Y-m-15');
        $prevMonthDate = date('Y-m-15', strtotime('-1 month'));

        $entries = [
            ['parish_id' => $churchA, 'fund_id' => $fundA1, 'amount' => '100.00', 'date' => $nowMonthDate, 'category' => 'offering'],
            ['parish_id' => $churchA, 'fund_id' => $fundA1, 'amount' => '50.50', 'date' => $nowMonthDate, 'category' => 'tithe'],
            ['parish_id' => $churchA, 'fund_id' => $fundA2, 'amount' => '25.25', 'date' => $prevMonthDate, 'category' => 'offering'],
            ['parish_id' => $churchB, 'fund_id' => $fundB1, 'amount' => '75.00', 'date' => $nowMonthDate, 'category' => 'offering'],
        ];

        foreach ($entries as $entry) {
            $id = (int) $model->insert([
                'parish_id' => $entry['parish_id'],
                'fund_id' => $entry['fund_id'],
                'user_id' => null,
                'amount' => $entry['amount'],
                'date' => $entry['date'],
                'category' => $entry['category'],
                'chart_account_id' => null,
                'payment_method' => 'cash',
                'notes' => 'aggregation regression',
            ], true);

            $this->assertGreaterThan(0, $id);
        }

        $rawRows = $this->db->table('donation')->select('amount')->get()->getResultArray();
        $this->assertCount(4, $rawRows);
        foreach ($rawRows as $row) {
            $this->assertStringStartsWith('enc:v1:', (string) ($row['amount'] ?? ''));
        }

        $start = date('Y-m-01', strtotime('-2 months'));
        $end = date('Y-m-t');

        $byCategory = $model->getDonationsByCategory($churchA, $start, $end);
        $categoryTotals = [];
        foreach ($byCategory as $row) {
            $categoryTotals[(string) $row['category']] = (float) ($row['total'] ?? 0);
        }
        $this->assertEqualsWithDelta(125.25, (float) ($categoryTotals['offering'] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(50.50, (float) ($categoryTotals['tithe'] ?? 0.0), 0.0001);

        $byFund = $model->getDonationsByFund($churchA, $start, $end);
        $fundTotals = [];
        foreach ($byFund as $row) {
            $fundTotals[(int) $row['fund_id']] = (float) ($row['total'] ?? 0);
        }
        $this->assertEqualsWithDelta(150.50, (float) ($fundTotals[$fundA1] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(25.25, (float) ($fundTotals[$fundA2] ?? 0.0), 0.0001);

        $byChurch = $model->getDonationsByParish($start, $end, [$churchA, $churchB], false);
        $churchTotals = [];
        foreach ($byChurch as $row) {
            $churchTotals[(int) $row['parish_id']] = (float) ($row['total_donations'] ?? 0);
        }
        $this->assertEqualsWithDelta(175.75, (float) ($churchTotals[$churchA] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(75.00, (float) ($churchTotals[$churchB] ?? 0.0), 0.0001);

        $networkMonthly = $model->getNetworkMonthlyTrend(12, [$churchA, $churchB], false);
        $monthlyTotals = [];
        foreach ($networkMonthly as $row) {
            $monthlyTotals[(string) $row['month_key']] = (float) ($row['total_amount'] ?? 0);
        }
        $thisMonthKey = date('Y-m', strtotime($nowMonthDate));
        $prevMonthKey = date('Y-m', strtotime($prevMonthDate));
        $this->assertEqualsWithDelta(225.50, (float) ($monthlyTotals[$thisMonthKey] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(25.25, (float) ($monthlyTotals[$prevMonthKey] ?? 0.0), 0.0001);
    }

    public function testMonthlyTrendByChurchAggregatesEncryptedAmountsPerChurchAndMonth(): void
    {
        $model = model(DonationModel::class);
        $fixture = $this->seedAggregationFixture($model);

        $monthlyByChurch = $model->getMonthlyTrendByParish(12, [$fixture['churchA'], $fixture['churchB']], false);

        $indexed = [];
        foreach ($monthlyByChurch as $row) {
            $key = (int) ($row['parish_id'] ?? 0) . '|' . (string) ($row['month_key'] ?? '');
            $indexed[$key] = (float) ($row['total_amount'] ?? 0.0);
        }

        $this->assertEqualsWithDelta(150.50, (float) ($indexed[$fixture['churchA'] . '|' . $fixture['thisMonthKey']] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(25.25, (float) ($indexed[$fixture['churchA'] . '|' . $fixture['prevMonthKey']] ?? 0.0), 0.0001);
        $this->assertEqualsWithDelta(75.00, (float) ($indexed[$fixture['churchB'] . '|' . $fixture['thisMonthKey']] ?? 0.0), 0.0001);
    }

    private function configureEncryptionKeys(): void
    {
        $key = 'hex2bin:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $blind = 'test-blind-index-key-for-integration-tests-123';

        putenv('encryption.key=' . $key);
        putenv('encryption.blindIndexKey=' . $blind);
        $_ENV['encryption.key'] = $key;
        $_ENV['encryption.blindIndexKey'] = $blind;
        $_SERVER['encryption.key'] = $key;
        $_SERVER['encryption.blindIndexKey'] = $blind;

        Services::reset(true);
    }

    private function rebuildTestSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('donation', true);
        $forge->dropTable('fund', true);
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
            'parish_id' => ['type' => 'INTEGER', 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'fund_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'unrestricted'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('fund', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => false],
            'fund_id' => ['type' => 'INTEGER', 'null' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => true],
            'amount' => ['type' => 'TEXT', 'null' => false],
            'date' => ['type' => 'DATE', 'null' => false],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'offering'],
            'chart_account_id' => ['type' => 'INTEGER', 'null' => true],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('donation', true);
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

    private function insertFund(int $churchId, string $name): int
    {
        $this->db->table('fund')->insert([
            'parish_id' => $churchId,
            'name' => $name,
            'fund_type' => 'unrestricted',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array{churchA:int,churchB:int,thisMonthKey:string,prevMonthKey:string}
     */
    private function seedAggregationFixture(DonationModel $model): array
    {
        $churchA = $this->insertChurch('Church A');
        $churchB = $this->insertChurch('Church B');

        $fundA1 = $this->insertFund($churchA, 'General Fund');
        $fundA2 = $this->insertFund($churchA, 'Missions Fund');
        $fundB1 = $this->insertFund($churchB, 'Building Fund');

        $nowMonthDate = date('Y-m-15');
        $prevMonthDate = date('Y-m-15', strtotime('-1 month'));

        $entries = [
            ['parish_id' => $churchA, 'fund_id' => $fundA1, 'amount' => '100.00', 'date' => $nowMonthDate, 'category' => 'offering'],
            ['parish_id' => $churchA, 'fund_id' => $fundA1, 'amount' => '50.50', 'date' => $nowMonthDate, 'category' => 'tithe'],
            ['parish_id' => $churchA, 'fund_id' => $fundA2, 'amount' => '25.25', 'date' => $prevMonthDate, 'category' => 'offering'],
            ['parish_id' => $churchB, 'fund_id' => $fundB1, 'amount' => '75.00', 'date' => $nowMonthDate, 'category' => 'offering'],
        ];

        foreach ($entries as $entry) {
            $id = (int) $model->insert([
                'parish_id' => $entry['parish_id'],
                'fund_id' => $entry['fund_id'],
                'user_id' => null,
                'amount' => $entry['amount'],
                'date' => $entry['date'],
                'category' => $entry['category'],
                'chart_account_id' => null,
                'payment_method' => 'cash',
                'notes' => 'aggregation regression',
            ], true);

            $this->assertGreaterThan(0, $id);
        }

        return [
            'churchA' => $churchA,
            'churchB' => $churchB,
            'thisMonthKey' => date('Y-m', strtotime($nowMonthDate)),
            'prevMonthKey' => date('Y-m', strtotime($prevMonthDate)),
        ];
    }
}
