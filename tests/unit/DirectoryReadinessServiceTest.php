<?php

namespace Tests\Unit;

use App\Services\DirectoryReadinessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

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
        $this->connection->query("CREATE TABLE {$diocese} (id INTEGER PRIMARY KEY AUTOINCREMENT, province_id INTEGER NOT NULL, name TEXT NOT NULL)");
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
}
