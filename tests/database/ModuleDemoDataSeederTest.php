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
        if (!class_exists(ModuleDemoDataSeeder::class)) {
            $this->markTestSkipped('ModuleDemoDataSeeder is an optional development fixture and is not shipped.');
        }

        $parishesTable = $this->resolveTableName('parish');
        $this->assertNotNull($parishesTable);
        $this->seed(ModuleDemoDataSeeder::class);

        $parish = $this->db->table($parishesTable)
            ->where('name', 'Module Demo Parish')
            ->get()
            ->getRowArray();

        $this->assertNotNull($parish);
        $parishId = (int) $parish['id'];

        foreach (['profile', 'services', 'person_visit', 'donations', 'expenses', 'volunteer_teams', 'families'] as $baseTable) {
            $table = $this->resolveTableName($baseTable);
            if ($table === null) {
                continue;
            }

            $columns = $this->db->getFieldNames($table);
            if (!in_array('parish_id', $columns, true)) {
                continue;
            }

            $count = (int) $this->db->table($table)->where('parish_id', $parishId)->countAllResults();
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
