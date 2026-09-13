<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ServiceAnalyticsCompatibilityIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->ensureSettingsTable();
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

    public function testServiceCreateAndEditPersistsMetricsEndToEnd(): void
    {
        $this->ensureCoreTablesAndColumns();

        $parishId = $this->insertParish('Analytics E2E Parish');

        $serviceModel = model('ServiceModel');
        $metricsModel = model('ServiceMetricsModel');
        $peopleModel = model('ServicePeopleModel');

        $serviceId = (int) $serviceModel->insert([
            'parish_id' => $parishId,
            'service_date' => '2026-05-11',
            'service_time' => '10:00',
            'service' => json_encode(['Sunday Worship']),
            'liturgical_day' => 'Easter 5',
            'place' => 'Main Nave',
            'preacher' => 'Rev. Test',
            'celebrant' => 'Rev. Test',
        ]);

        $this->assertGreaterThan(0, $serviceId);

        $metricsModel->insert([
            'service_id' => $serviceId,
            'format_of_service' => 'Hybrid',
            'attendance_classification' => 'Regular',
            'service_family' => 'holy_communion',
            'service_schedule' => 'sunday',
            'reported_present_count' => 120,
            'reported_communion_count' => 90,
            'online_live_views' => 85,
            'online_recorded_views_7d' => 140,
            'online_peak_viewers' => 80,
            'languages_json' => json_encode(['en', 'es']),
        ]);

        $peopleModel->insert([
            'service_id' => $serviceId,
            'person_name' => 'Alice Reader',
            'role_name' => 'Reader',
            'sort_order' => 0,
        ]);
        $peopleModel->insert([
            'service_id' => $serviceId,
            'person_name' => 'Bob Intercessor',
            'role_name' => 'Intercessor',
            'sort_order' => 1,
        ]);

        $serviceTable = $this->resolveTableName('service');
        $metricTable = $this->resolveTableName('service_metric');
        $peopleTable = $this->resolveTableName('service_people');

        $serviceRow = $this->db->table($serviceTable)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotEmpty($serviceRow);
        $this->assertSame($serviceId, (int) ($serviceRow['id'] ?? 0));

        $metricRow = $this->db->table($metricTable)
            ->where('service_id', $serviceId)
            ->get()
            ->getRowArray();

        $this->assertNotEmpty($metricRow);
        $this->assertSame(120, (int) ($metricRow['reported_present_count'] ?? 0));
        $this->assertSame(90, (int) ($metricRow['reported_communion_count'] ?? 0));

        $peopleRows = $this->db->table($peopleTable)
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $peopleRows);
        $this->assertSame('Alice Reader', (string) ($peopleRows[0]['person_name'] ?? ''));

        $serviceModel->update($serviceId, [
            'service_time' => '10:30',
            'preacher' => 'Rev. Updated',
            'celebrant' => 'Rev. Updated',
        ]);

        $metricsModel->update($serviceId, [
            'reported_present_count' => 130,
            'reported_communion_count' => 100,
            'online_live_views' => 95,
            'online_recorded_views_7d' => 170,
            'online_peak_viewers' => 90,
            'languages_json' => json_encode(['en', 'es', 'fr']),
        ]);

        $peopleModel->where('service_id', $serviceId)->delete();
        $peopleModel->insert([
            'service_id' => $serviceId,
            'person_name' => 'Carol Deacon',
            'role_name' => 'Deacon',
            'sort_order' => 0,
        ]);

        $updatedMetricRow = $this->db->table($metricTable)
            ->where('service_id', $serviceId)
            ->get()
            ->getRowArray();

        $this->assertSame(130, (int) ($updatedMetricRow['reported_present_count'] ?? 0));
        $this->assertSame(100, (int) ($updatedMetricRow['reported_communion_count'] ?? 0));

        $updatedPeopleRows = $this->db->table($peopleTable)
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $updatedPeopleRows);
        $this->assertSame('Carol Deacon', (string) ($updatedPeopleRows[0]['person_name'] ?? ''));
    }

    private function ensureCoreTablesAndColumns(): void
    {
        $this->ensureParishTable();
        $this->ensureServiceTable();
        $this->ensureServiceMetricTable();
        $this->ensureServicePeopleTable();

        $this->assertTrue($this->tableExists('service'), 'Expected canonical table "service" to exist.');
        $this->assertTrue($this->tableExists('service_metric'), 'Expected canonical table "service_metric" to exist.');
        $this->assertTrue($this->tableExists('service_people'), 'Expected table "service_people" to exist.');

        $serviceTable = $this->resolveTableName('service');
        $metricTable = $this->resolveTableName('service_metric');
        $peopleTable = $this->resolveTableName('service_people');

        $this->ensureColumnExists($serviceTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($serviceTable, 'service_date', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'service_time', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'service', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'liturgical_day', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'place', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'preacher', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'celebrant', 'TEXT NULL');
        $this->ensureColumnExists($serviceTable, 'notes', 'TEXT NULL');

        $this->ensureColumnExists($metricTable, 'service_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($metricTable, 'reported_present_count', 'INTEGER NULL');
        $this->ensureColumnExists($metricTable, 'reported_communion_count', 'INTEGER NULL');
        $this->ensureColumnExists($metricTable, 'online_live_views', 'INTEGER NULL');
        $this->ensureColumnExists($metricTable, 'online_recorded_views_7d', 'INTEGER NULL');
        $this->ensureColumnExists($metricTable, 'online_peak_viewers', 'INTEGER NULL');
        $this->ensureColumnExists($metricTable, 'languages_json', 'TEXT NULL');

        $this->ensureColumnExists($peopleTable, 'service_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($peopleTable, 'person_name', 'TEXT NULL');
        $this->ensureColumnExists($peopleTable, 'role_name', 'TEXT NULL');
        $this->ensureColumnExists($peopleTable, 'sort_order', 'INTEGER NULL');
    }

    private function ensureParishTable(): void
    {
        $table = $this->physicalTableName('parish');
        if (!$this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($table, 'name', 'TEXT NULL');
    }

    private function ensureSettingsTable(): void
    {
        $table = $this->physicalTableName('settings');
        if (!$this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

        $this->ensureColumnExists($table, 'class', 'TEXT NULL');
        $this->ensureColumnExists($table, 'key', 'TEXT NULL');
        $this->ensureColumnExists($table, 'value', 'TEXT NULL');
        $this->ensureColumnExists($table, 'type', 'TEXT NULL');
        $this->ensureColumnExists($table, 'context', 'TEXT NULL');
    }

    private function ensureServiceTable(): void
    {
        $table = $this->physicalTableName('service');
        $this->db->query('DROP TABLE IF EXISTS ' . $table);
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

    private function ensureServiceMetricTable(): void
    {
        $table = $this->physicalTableName('service_metric');
        $this->db->query('DROP TABLE IF EXISTS ' . $table);
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

    private function ensureServicePeopleTable(): void
    {
        $table = $this->physicalTableName('service_people');
        $this->db->query('DROP TABLE IF EXISTS ' . $table);
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            person_name TEXT NULL,
            role_name TEXT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
    }

    private function purgeServiceTables(): void
    {
        foreach (['service_people', 'service_metric', 'service', 'parish'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            try {
                $this->db->query('DELETE FROM ' . $this->physicalTableName($table));
            } catch (\Throwable $e) {
                // Keep setup resilient in mixed schema test environments.
            }
        }
    }

    private function insertParish(string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function tableExists(string $base): bool
    {
        $prefixed = $this->resolveTableName($base);
        $prefix = property_exists($this->db, 'DBPrefix') ? (string) ($this->db->DBPrefix ?? '') : '';
        $candidates = array_values(array_unique([$prefixed, $base, $prefix . $base]));

        foreach ($candidates as $table) {
            if ($table === '') {
                continue;
            }

            try {
                $this->db->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
                return true;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return false;
    }

    private function resolveTableName(string $base): string
    {
        return $base;
    }

    private function physicalTableName(string $base): string
    {
        $prefix = property_exists($this->db, 'DBPrefix') ? (string) ($this->db->DBPrefix ?? '') : '';
        return $prefix . $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $hasColumn = false;

        try {
            $this->db->resetDataCache();
            $columns = array_map('strtolower', $this->db->getFieldNames($table) ?: []);
            $hasColumn = in_array(strtolower($column), $columns, true);
        } catch (\Throwable $e) {
            // Fall through and attempt ALTER defensively.
        }

        if ($hasColumn) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            $this->db->resetDataCache();
        } catch (\Throwable $e) {
            // Ignore if current DB driver cannot alter this table in test mode.
        }
    }
}
