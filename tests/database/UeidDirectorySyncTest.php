<?php

use App\Commands\SyncUeidDirectory;
use App\Services\DirectoryReadinessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class UeidDirectorySyncTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private string $tempCsvPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = \Config\Database::connect('tests', false);
        $parish           = $this->connection->prefixTable('parish');
        $diocese          = $this->connection->prefixTable('diocese');
        $province         = $this->connection->prefixTable('province');

        $this->connection->query("DROP TABLE IF EXISTS {$parish}");
        $this->connection->query("DROP TABLE IF EXISTS {$diocese}");
        $this->connection->query("DROP TABLE IF EXISTS {$province}");

        $this->connection->query("CREATE TABLE {$province} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_number TEXT NULL,
            province_id TEXT NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");

        $this->connection->query("CREATE TABLE {$diocese} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            alias_norm TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");

        // Intentionally do NOT create `diocese_alias` to mirror fresh Path A/B destinations.
        $this->connection->query("CREATE TABLE {$parish} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            parish_ueid TEXT NULL UNIQUE,
            is_active INTEGER NOT NULL DEFAULT 1,
            diocese_id INTEGER NULL,
            city TEXT NULL,
            state TEXT NULL,
            county TEXT NULL,
            public_token TEXT NOT NULL UNIQUE,
            congregation_type TEXT NOT NULL DEFAULT 'parish',
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )");
    }

    protected function tearDown(): void
    {
        if ($this->tempCsvPath !== '' && is_file($this->tempCsvPath)) {
            @unlink($this->tempCsvPath);
        }

        $parish   = $this->connection->prefixTable('parish');
        $diocese  = $this->connection->prefixTable('diocese');
        $province = $this->connection->prefixTable('province');

        $this->connection->query("DROP TABLE IF EXISTS {$parish}");
        $this->connection->query("DROP TABLE IF EXISTS {$diocese}");
        $this->connection->query("DROP TABLE IF EXISTS {$province}");
        $this->connection->close();

        Services::reset(true);
        parent::tearDown();
    }

    private function seedCanonicalParentDioceses(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->connection->table('province')->insertBatch([
            ['province_number' => 'II', 'province_id' => 'II', 'name' => 'New York & New Jersey', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['province_number' => 'IV', 'province_id' => 'IV', 'name' => 'Sewanee (Southeast)', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $provII = (int) $this->connection->table('province')->where('province_number', 'II')->get()->getRowArray()['id'];
        $provIV = (int) $this->connection->table('province')->where('province_number', 'IV')->get()->getRowArray()['id'];

        $this->connection->table('diocese')->insertBatch([
            ['province_id' => $provII, 'name' => 'Convocation of Episcopal Churches in Europe', 'alias_norm' => 'convocation of episcopal churches in europe', 'created_at' => $now, 'updated_at' => $now],
            ['province_id' => $provIV, 'name' => 'Alabama', 'alias_norm' => 'alabama', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function runSyncCommand(array $params): int
    {
        $testConnection = $this->connection;
        $cmd            = new class($testConnection, Services::logger(), Services::commands()) extends SyncUeidDirectory {
            public function __construct(
                private readonly BaseConnection $testDb,
                \Psr\Log\LoggerInterface $logger,
                \CodeIgniter\CLI\Commands $commands,
            ) {
                parent::__construct($logger, $commands);
            }

            protected function getConnection(): BaseConnection
            {
                return $this->testDb;
            }
        };

        return (int) $cmd->run($params);
    }

    public function testSyncGeneratesUniqueTokensResolvesEuropeAliasAndIsIdempotent(): void
    {
        $this->seedCanonicalParentDioceses();

        $readiness = new DirectoryReadinessService();
        $this->assertFalse($readiness->isReady($this->connection));

        $this->tempCsvPath = WRITEPATH . 'ueid_test_' . uniqid('', true) . '.csv';
        file_put_contents(
            $this->tempCsvPath,
            implode("\n", [
                'ParishUEID,Diocese,Name,City,State,County',
                '1111-2222,Alabama,"All Saints Episcopal Church",Montgomery,Alabama,Montgomery',
                '1285-3651,"Churches in Europe","American Cathedral of the Holy Trinity",Paris,France,',
            ]) . "\n"
        );

        $exitCode = $this->runSyncCommand([
            'source' => $this->tempCsvPath,
            'apply'  => null,
        ]);
        $this->assertSame(0, $exitCode);

        $rows = $this->connection->table('parish')->orderBy('id', 'ASC')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertNotSame('', $rows[0]['public_token']);
        $this->assertNotSame('', $rows[1]['public_token']);
        $this->assertNotSame($rows[0]['public_token'], $rows[1]['public_token']);
        $this->assertSame(64, strlen($rows[0]['public_token']));
        $this->assertSame('parish', $rows[0]['congregation_type']);
        $this->assertNotNull($rows[0]['created_at']);
        $this->assertNotNull($rows[0]['updated_at']);

        // Re-running must be idempotent
        $repeatExit = $this->runSyncCommand([
            'source' => $this->tempCsvPath,
            'apply'  => null,
        ]);
        $this->assertSame(0, $repeatExit);
        $this->assertSame(2, $this->connection->table('parish')->countAllResults());

        $this->assertTrue($readiness->isReady($this->connection));
    }

    public function testPreserveInactiveFlagPreventsReactivationWhileAllowingMetadataUpdates(): void
    {
        $this->seedCanonicalParentDioceses();
        $alabamaId = (int) $this->connection->table('diocese')->where('name', 'Alabama')->get()->getRowArray()['id'];

        $this->connection->table('parish')->insert([
            'name'              => 'Closed Mission',
            'parish_ueid'       => '9999-0001',
            'is_active'         => 0,
            'diocese_id'        => $alabamaId,
            'city'              => 'Old City',
            'public_token'      => bin2hex(random_bytes(32)),
            'congregation_type' => 'parish',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->tempCsvPath = WRITEPATH . 'ueid_preserve_' . uniqid('', true) . '.csv';
        file_put_contents(
            $this->tempCsvPath,
            implode("\n", [
                'ParishUEID,Diocese,Name,City,State,County',
                '9999-0001,Alabama,"Closed Mission",New City,Alabama,Montgomery',
            ]) . "\n"
        );

        // With --preserve-inactive, city updates while is_active remains 0
        $exitPreserve = $this->runSyncCommand([
            'source'            => $this->tempCsvPath,
            'apply'             => null,
            'preserve-inactive' => null,
        ]);
        $this->assertSame(0, $exitPreserve);

        $preservedRow = $this->connection->table('parish')->where('parish_ueid', '9999-0001')->get()->getRowArray();
        $this->assertSame(0, (int) $preservedRow['is_active']);
        $this->assertSame('New City', $preservedRow['city']);

        // Without --preserve-inactive, the parish is reactivated (is_active = 1)
        $exitReactivate = $this->runSyncCommand([
            'source' => $this->tempCsvPath,
            'apply'  => null,
        ]);
        $this->assertSame(0, $exitReactivate);

        $reactivatedRow = $this->connection->table('parish')->where('parish_ueid', '9999-0001')->get()->getRowArray();
        $this->assertSame(1, (int) $reactivatedRow['is_active']);
    }

    public function testUnresolvedDioceseReturnsNonZeroExitCode(): void
    {
        $this->seedCanonicalParentDioceses();

        $this->tempCsvPath = WRITEPATH . 'ueid_error_' . uniqid('', true) . '.csv';
        file_put_contents(
            $this->tempCsvPath,
            implode("\n", [
                'ParishUEID,Diocese,Name,City,State,County',
                '2603-1393,"Nonexistent Diocese","Broken Parish",Nowhere,TX,',
            ]) . "\n"
        );

        $exitCode = $this->runSyncCommand([
            'source' => $this->tempCsvPath,
        ]);
        $this->assertSame(1, $exitCode);
    }

    public function testShippedUeidJsonParsesAndMatchesCsv(): void
    {
        $jsonPath = ROOTPATH . 'docs/UEID.json';
        $csvPath  = ROOTPATH . 'docs/UEID.csv';

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($csvPath);
        $this->assertGreaterThan(0, (int) filesize($jsonPath));
        $this->assertGreaterThan(0, (int) filesize($csvPath));

        $decoded = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $fields   = ['ParishUEID', 'Diocese', 'Name', 'City', 'State', 'County'];
        $jsonRows = [];
        foreach ($decoded as $item) {
            $this->assertIsArray($item);
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = trim((string) ($item[$field] ?? ''));
            }
            $this->assertNotSame('2603-1393', $row['ParishUEID']);
            $jsonRows[] = $row;
        }

        $fh = fopen($csvPath, 'rb');
        $this->assertNotFalse($fh);

        $header = fgetcsv($fh, 0, ',', '"', '');
        $this->assertIsArray($header);
        $indexes = array_flip(array_map(static fn ($col): string => trim((string) $col), $header));
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $indexes);
        }

        $csvRows = [];
        while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = trim((string) ($line[$indexes[$field]] ?? ''));
            }
            $this->assertNotSame('2603-1393', $row['ParishUEID']);
            $csvRows[] = $row;
        }
        fclose($fh);

        $this->assertGreaterThan(0, count($jsonRows));
        $this->assertSame(count($csvRows), count($jsonRows));
        $this->assertSame($csvRows, $jsonRows);
    }
}
