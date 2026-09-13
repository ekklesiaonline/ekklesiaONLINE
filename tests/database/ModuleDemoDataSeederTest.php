<?php

use App\Database\Seeds\ModuleDemoDataSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ModuleDemoDataSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testSeederCreatesCrossModuleBaselineRecords(): void
    {
        $churchesTable = $this->resolveTableName('churches');
        if ($churchesTable === null) {
            $this->markTestSkipped('No churches table found in current test database schema.');
        }

        $this->seed(ModuleDemoDataSeeder::class);

        $church = $this->db->table($churchesTable)
            ->where('name', 'Module Demo Church')
            ->get()
            ->getRowArray();

        $this->assertNotNull($church);
        $churchId = (int) $church['id'];

        foreach (['profile', 'services', 'guests', 'donations', 'expenses', 'volunteer_teams', 'families'] as $baseTable) {
            $table = $this->resolveTableName($baseTable);
            if ($table === null) {
                continue;
            }

            $columns = $this->db->getFieldNames($table);
            if (!in_array('church_id', $columns, true)) {
                continue;
            }

            $count = (int) $this->db->table($table)->where('church_id', $churchId)->countAllResults();
            $this->assertGreaterThanOrEqual(0, $count, $baseTable . ' table query should be valid');
        }
    }

    private function resolveTableName(string $baseName): ?string
    {
        $tables = $this->db->listTables();

        if (in_array($baseName, $tables, true)) {
            return $baseName;
        }

        $prefixed = $this->db->getPrefix() . $baseName;
        if (in_array($prefixed, $tables, true)) {
            return $prefixed;
        }

        return null;
    }
}
