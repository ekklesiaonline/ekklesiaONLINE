<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ServicesCreateEditControllerSmokeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);

        // Keep test reads/writes on the same runtime connection used by controller code.
        $this->db = db_connect();

        $this->ensureSettingsTable();
        $this->ensureCoreTables();
        $this->purgeServiceTables();

        $auth = new class(config('Auth')) extends Auth {
            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return false;
            }

            public function id(): ?int
            {
                return null;
            }

            public function user(): ?User
            {
                return null;
            }
        };
        Services::injectMock('auth', $auth);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testStoreThenUpdateThroughServicesControllerPath(): void
    {
        $parishId = $this->insertParish('Services Smoke Parish');

        $controller = new class($parishId) extends \Worship\Controllers\Services {
            private int $testParishId;

            public function __construct(int $parishId)
            {
                parent::__construct();
                $this->testParishId = $parishId;
                $this->serviceModel->skipValidation(true);
            }

            protected function requirePermission(string $permission)
            {
                return null;
            }

            protected function requireParish()
            {
                return null;
            }

            protected function validate($rules, array $messages = []): bool
            {
                return true;
            }

            protected function getOperationalParishId(): ?int
            {
                return $this->testParishId;
            }

            protected function audit(): \App\Services\AuditService
            {
                return service('audit');
            }
        };

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', [
            'date' => '2026-05-11',
            'service' => 'Sunday Worship',
            'liturgical_day' => 'Easter 5',
            'place' => 'Main Nave',
            'time' => '10:00',
            'preacher' => 'Rev. Smoke',
            'celebrant' => 'Rev. Smoke',
        ]);

        $controller->initController($request, service('response'), service('logger'));
        $storeResult = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $storeResult);

        $serviceRow = $this->db->table('service')
            ->where('parish_id', $parishId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertNotEmpty($serviceRow);
        $serviceId = (int) ($serviceRow['id'] ?? 0);
        $this->assertGreaterThan(0, $serviceId);

        $request->setMethod('post');
        $request->setGlobal('post', [
            'date' => '2026-05-11',
            'service' => 'Sunday Worship',
            'liturgical_day' => 'Easter 5',
            'place' => 'Main Nave',
            'time' => '10:30',
            'preacher' => 'Rev. Updated Smoke',
            'celebrant' => 'Rev. Updated Smoke',
        ]);

        $controller->initController($request, service('response'), service('logger'));
        $updateResult = $controller->update($serviceId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $updateResult);

        $updatedService = $this->db->table('service')
            ->where('id', $serviceId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();
        $this->assertNotEmpty($updatedService);
        $this->assertSame('10:30', (string) ($updatedService['service_time'] ?? ''));
        $this->assertSame('Rev. Updated Smoke', (string) ($updatedService['preacher'] ?? ''));
    }

    private function ensureSettingsTable(): void
    {
        if (!$this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->physicalTableName('settings') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureCoreTables(): void
    {
        if (!$this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->physicalTableName('parish') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
        $this->ensureUnprefixedShadowTable('parish', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        if (!$this->tableExists('service')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->physicalTableName('service') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NULL,
                service_date TEXT NULL,
                service_time TEXT NULL,
                service TEXT NULL,
                liturgical_day TEXT NULL,
                place TEXT NULL,
                preacher TEXT NULL,
                celebrant TEXT NULL,
                notes TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
        $this->ensureUnprefixedShadowTable('service', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NULL,
            service_date TEXT NULL,
            service_time TEXT NULL,
            service TEXT NULL,
            liturgical_day TEXT NULL,
            place TEXT NULL,
            preacher TEXT NULL,
            celebrant TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        // A previously-run test class may have created a narrower service table
        // in this shared in-memory database; guarantee the columns we exercise.
        $this->ensureColumnExists($this->physicalTableName('service'), 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists('service', 'parish_id', 'INTEGER NULL');
        foreach (['service_date', 'service_time', 'service', 'liturgical_day', 'place', 'preacher', 'celebrant', 'notes', 'created_at', 'updated_at'] as $column) {
            $this->ensureColumnExists($this->physicalTableName('service'), $column, 'TEXT NULL');
            $this->ensureColumnExists('service', $column, 'TEXT NULL');
        }

        if (!$this->tableExists('service_metric')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->physicalTableName('service_metric') . ' (
                service_id INTEGER PRIMARY KEY,
                format_of_service TEXT NULL,
                attendance_classification TEXT NULL,
                service_family TEXT NULL,
                service_schedule TEXT NULL,
                reported_present_count INTEGER NULL,
                reported_communion_count INTEGER NULL,
                online_live_views INTEGER NULL,
                online_recorded_views_7d INTEGER NULL,
                online_peak_viewers INTEGER NULL,
                languages_json TEXT NULL,
                place_labels_json TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
        $this->ensureUnprefixedShadowTable('service_metric', '(
            service_id INTEGER PRIMARY KEY,
            format_of_service TEXT NULL,
            attendance_classification TEXT NULL,
            service_family TEXT NULL,
            service_schedule TEXT NULL,
            reported_present_count INTEGER NULL,
            reported_communion_count INTEGER NULL,
            online_live_views INTEGER NULL,
            online_recorded_views_7d INTEGER NULL,
            online_peak_viewers INTEGER NULL,
            languages_json TEXT NULL,
            place_labels_json TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        if (!$this->tableExists('service_people')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->physicalTableName('service_people') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_id INTEGER NOT NULL,
                person_name TEXT NULL,
                role_name TEXT NULL,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
        $this->ensureUnprefixedShadowTable('service_people', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            person_name TEXT NULL,
            role_name TEXT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
    }

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();

        $prefix = property_exists($this->db, 'DBPrefix') ? (string) ($this->db->DBPrefix ?? '') : '';
        if ($prefix !== '') {
            try {
                $this->db->query(
                    'INSERT OR REPLACE INTO parish (id, name, created_at, updated_at) VALUES (?, ?, ?, ?)',
                    [$id, $name, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
                );
            } catch (\Throwable $e) {
                // Best-effort mirror for mixed table resolution in controller/model tests.
            }
        }

        return $id;
    }

    private function tableExists(string $table): bool
    {
        $physical = $this->physicalTableName($table);

        try {
            $this->db->query('SELECT 1 FROM ' . $physical . ' LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function physicalTableName(string $table): string
    {
        $prefix = property_exists($this->db, 'DBPrefix') ? (string) ($this->db->DBPrefix ?? '') : '';
        return $prefix . $table;
    }

    private function purgeServiceTables(): void
    {
        foreach (['service_people', 'service_metric', 'service', 'parish'] as $table) {
            foreach (array_unique([$this->physicalTableName($table), $table]) as $tableName) {
                try {
                    $this->db->query('DELETE FROM ' . $tableName);
                } catch (\Throwable $e) {
                    // Keep setup resilient in mixed schema test environments.
                }
            }
        }
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        try {
            $columns = $this->db->getFieldNames($table);
            if (in_array($column, $columns, true)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Best-effort for shared in-memory schema variations.
        }
    }

    private function ensureUnprefixedShadowTable(string $table, string $ddl): void
    {
        $prefix = property_exists($this->db, 'DBPrefix') ? (string) ($this->db->DBPrefix ?? '') : '';
        if ($prefix === '') {
            return;
        }

        try {
            $this->db->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return;
        } catch (\Throwable $e) {
            // Create unprefixed fallback for mixed resolution paths.
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' ' . $ddl);
    }
}
