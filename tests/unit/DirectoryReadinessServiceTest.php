<?php

namespace Tests\Unit;

use App\Commands\VerifyDirectoryReadiness;
use App\Database\Seeds\ProvincesAndDiocesesSeeder;
use App\Services\DirectoryReadinessService;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;

final class DirectoryReadinessServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = db_connect('tests');
        $parish = $this->connection->prefixTable('parish');
        $diocese = $this->connection->prefixTable('diocese');
        $province = $this->connection->prefixTable('province');

        $this->connection->query("DROP TABLE IF EXISTS {$parish}");
        $this->connection->query("DROP TABLE IF EXISTS {$diocese}");
        $this->connection->query("DROP TABLE IF EXISTS {$province}");

        $this->connection->query("CREATE TABLE {$province} (id INTEGER PRIMARY KEY AUTOINCREMENT, province_number TEXT NOT NULL, name TEXT NOT NULL)");
        $this->connection->query("CREATE TABLE {$diocese} (id INTEGER PRIMARY KEY AUTOINCREMENT, province_id INTEGER NOT NULL, name TEXT NOT NULL, alias_norm TEXT NULL)");
        $this->connection->query("CREATE TABLE {$parish} (id INTEGER PRIMARY KEY AUTOINCREMENT, diocese_id INTEGER NULL, name TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, deleted_at TEXT NULL)");
    }

    protected function tearDown(): void
    {
        $parish = $this->connection->prefixTable('parish');
        $diocese = $this->connection->prefixTable('diocese');
        $province = $this->connection->prefixTable('province');

        $this->connection->query("DROP TABLE IF EXISTS {$parish}");
        $this->connection->query("DROP TABLE IF EXISTS {$diocese}");
        $this->connection->query("DROP TABLE IF EXISTS {$province}");

        parent::tearDown();
    }

    public function testFailsWhenHierarchyTablesAreEmpty(): void
    {
        $result = (new DirectoryReadinessService())->evaluate($this->connection);

        $this->assertFalse($result['ready']);
        $this->assertCount(3, $result['blocking_issues']);
        $this->assertSame(0, $result['counts']['provinces']);
        $this->assertSame(0, $result['counts']['linked_dioceses']);
        $this->assertSame(0, $result['counts']['linked_active_parishes']);
    }

    public function testFailsWhenProvincesAndDiocesesExistWithoutActiveParishes(): void
    {
        $this->connection->table('province')->insert(['province_number' => '1', 'name' => 'Province I']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert(['province_id' => $provinceId, 'name' => 'Diocese of Massachusetts']);
        $dioceseId = (int) $this->connection->insertID();

        // Inactive and soft-deleted parishes must not satisfy readiness.
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Inactive Parish',
            'is_active' => 0,
            'deleted_at' => null,
        ]);
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Deleted Parish',
            'is_active' => 1,
            'deleted_at' => '2026-01-01 00:00:00',
        ]);

        $result = (new DirectoryReadinessService())->evaluate($this->connection);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['counts']['provinces']);
        $this->assertSame(1, $result['counts']['linked_dioceses']);
        $this->assertSame(1, $result['counts']['parishes']);
        $this->assertSame(0, $result['counts']['active_parishes']);
        $this->assertSame(0, $result['counts']['linked_active_parishes']);
    }

    public function testFailsWhenActiveParishHasNoValidParentDiocese(): void
    {
        $this->connection->table('province')->insert(['province_number' => '1', 'name' => 'Province I']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert(['province_id' => $provinceId, 'name' => 'Diocese of Massachusetts']);
        $this->connection->table('parish')->insert([
            'diocese_id' => null,
            'name' => 'Orphan Parish',
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $result = (new DirectoryReadinessService())->evaluate($this->connection);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['counts']['active_parishes']);
        $this->assertSame(0, $result['counts']['linked_active_parishes']);
        $this->assertSame(1, $result['counts']['unlinked_active_parishes']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function testPassesWhenProvinceDioceseAndActiveParishChainExists(): void
    {
        $this->connection->table('province')->insert(['province_number' => '9', 'name' => 'Province IX']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert(['province_id' => $provinceId, 'name' => 'Diocese of the Dominican Republic']);
        $dioceseId = (int) $this->connection->insertID();
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Iglesia Episcopal La Transfiguracion',
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $service = new DirectoryReadinessService();
        $result = $service->evaluate($this->connection);

        $this->assertTrue($result['ready']);
        $this->assertTrue($service->isReady($this->connection));
        $this->assertSame([], $result['blocking_issues']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(1, $result['counts']['provinces']);
        $this->assertSame(1, $result['counts']['linked_dioceses']);
        $this->assertSame(1, $result['counts']['linked_active_parishes']);
    }

    public function testProvincesAndDiocesesSeederDoesNotRecreateSampleDiocesesWhenRealDiocesesExist(): void
    {
        $seeder = new ProvincesAndDiocesesSeeder(new Database(), $this->connection);
        $seeder->run();

        // Fresh empty database gets the 2 placeholder dioceses.
        $this->assertSame(2, $this->connection->table('diocese')->countAllResults());

        // Replace placeholder dioceses with a real diocese, then re-run seeder (as on routine deploys).
        $firstProvince = $this->connection->table('province')->orderBy('id', 'ASC')->get()->getFirstRow('array');
        $this->assertIsArray($firstProvince);
        $this->connection->table('diocese')->truncate();
        $this->connection->table('diocese')->insert([
            'province_id' => $firstProvince['id'],
            'name' => 'Diocese of the Dominican Republic',
            'alias_norm' => 'diocese of the dominican republic',
        ]);

        $seeder->run();

        $names = array_column(
            $this->connection->table('diocese')->select('name')->get()->getResultArray(),
            'name'
        );
        $this->assertSame(['Diocese of the Dominican Republic'], $names);
    }

    public function testProvincesAndDiocesesSeederDoesNotInjectExtraProvincesWhenProvincesExist(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->connection->table('province')->insert([
                'province_number' => (string) $i,
                'name' => 'Province ' . $i,
            ]);
        }
        $firstProvince = $this->connection->table('province')->orderBy('id', 'ASC')->get()->getFirstRow('array');
        $this->assertIsArray($firstProvince);
        $this->connection->table('diocese')->insert([
            'province_id' => $firstProvince['id'],
            'name' => 'Diocese of the Dominican Republic',
            'alias_norm' => 'diocese of the dominican republic',
        ]);

        $seeder = new ProvincesAndDiocesesSeeder(new Database(), $this->connection);
        $seeder->run();

        $this->assertSame(9, $this->connection->table('province')->countAllResults());
        $this->assertSame(0, $this->connection->table('province')->where('province_number', '10')->countAllResults());
    }

    public function testVerifyCommandReturnsExitErrorWhenHierarchyIsEmpty(): void
    {
        $command = $this->makeVerifyCommand();

        ob_start();
        $exitCode = $command->run([]);
        ob_end_clean();

        $this->assertSame(EXIT_ERROR, $exitCode);
    }

    public function testVerifyCommandExitCodesWithWarningsInDefaultAndStrictModes(): void
    {
        $this->connection->table('province')->insert(['province_number' => '9', 'name' => 'Province IX']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => 'Diocese of the Dominican Republic',
            'alias_norm' => 'diocese of the dominican republic',
        ]);
        $dioceseId = (int) $this->connection->insertID();
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Iglesia Episcopal La Transfiguracion',
            'is_active' => 1,
            'deleted_at' => null,
        ]);
        // Second active parish without a diocese produces a warning while keeping the chain valid.
        $this->connection->table('parish')->insert([
            'diocese_id' => null,
            'name' => 'Unlinked Active Parish',
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $command = $this->makeVerifyCommand();

        ob_start();
        $defaultExitCode = $command->run([]);
        ob_end_clean();
        $this->assertSame(EXIT_SUCCESS, $defaultExitCode);

        ob_start();
        $strictExitCode = $command->run(['strict' => null]);
        ob_end_clean();
        $this->assertSame(EXIT_ERROR, $strictExitCode);

        // Removing the unlinked parish makes --strict pass with EXIT_SUCCESS.
        $this->connection->table('parish')->where('diocese_id', null)->delete();

        ob_start();
        $cleanStrictExitCode = $command->run(['strict' => null]);
        ob_end_clean();
        $this->assertSame(EXIT_SUCCESS, $cleanStrictExitCode);
    }

    public function testWarnsOnPlaceholderDiocesesWhileRemainingReady(): void
    {
        $this->connection->table('province')->insert(['province_number' => '9', 'name' => 'Province IX']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => 'Diocese of the Dominican Republic',
            'alias_norm' => 'diocese of the dominican republic',
        ]);
        $dioceseId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => 'Sample Diocese One',
            'alias_norm' => 'sample diocese one',
        ]);
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Iglesia Episcopal La Transfiguracion',
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $result = (new DirectoryReadinessService())->evaluate($this->connection);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['counts']['placeholder_dioceses']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Sample Diocese One/Two', $result['warnings'][0]);
    }

    public function testWarnsOnInactiveOrphanParishesImportedWithoutForeignKeyChecks(): void
    {
        $this->connection->table('province')->insert(['province_number' => '9', 'name' => 'Province IX']);
        $provinceId = (int) $this->connection->insertID();
        $this->connection->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => 'Diocese of the Dominican Republic',
            'alias_norm' => 'diocese of the dominican republic',
        ]);
        $dioceseId = (int) $this->connection->insertID();
        $this->connection->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Iglesia Episcopal La Transfiguracion',
            'is_active' => 1,
            'deleted_at' => null,
        ]);
        // Inactive parish whose diocese reference is broken — the corruption a
        // FOREIGN_KEY_CHECKS=0 hierarchy import can leave behind.
        $this->connection->table('parish')->insert([
            'diocese_id' => 99999,
            'name' => 'Inactive Orphan Parish',
            'is_active' => 0,
            'deleted_at' => null,
        ]);

        $result = (new DirectoryReadinessService())->evaluate($this->connection);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['counts']['unlinked_parishes']);
        $this->assertSame(0, $result['counts']['unlinked_active_parishes']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('non-deleted parish', $result['warnings'][0]);
    }

    private function makeVerifyCommand(): VerifyDirectoryReadiness
    {
        $connection = $this->connection;

        return new class (Services::logger(), new Commands(), $connection) extends VerifyDirectoryReadiness {
            public function __construct($logger, $commands, private readonly BaseConnection $testConnection)
            {
                parent::__construct($logger, $commands);
            }

            protected function evaluateReadiness(): array
            {
                return (new DirectoryReadinessService())->evaluate($this->testConnection);
            }
        };
    }
}
