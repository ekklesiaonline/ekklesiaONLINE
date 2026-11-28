<?php

use App\Database\Migrations\AlignHierarchySchemaAndRelationships;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Rehearses AlignHierarchySchemaAndRelationships against a real MySQL server:
 * legacy drifted schema upgrade, idempotency, ON DELETE RESTRICT enforcement,
 * non-destructive rollback, and pre-DDL abort on conflicting parish images.
 *
 * @internal
 */
final class HierarchyAlignmentMySqlUpgradeTest extends CIUnitTestCase
{
    private ?BaseConnection $mysqlConnection = null;

    protected function setUp(): void
    {
        parent::setUp();
        require_once APPPATH . 'Database/Migrations/2026-11-27-213833_AlignHierarchySchemaAndRelationships.php';
    }

    protected function tearDown(): void
    {
        if ($this->mysqlConnection !== null) {
            $this->dropTestTables($this->mysqlConnection);
            $this->mysqlConnection->close();
            $this->mysqlConnection = null;
        }

        parent::tearDown();
    }

    public function testLegacyDriftedHierarchyUpgradesEnforcesRestrictDeletesAndPreservesFksOnRollback(): void
    {
        $db = $this->getIsolatedMySqlConnection();
        $this->dropTestTables($db);

        $db->query(
            'CREATE TABLE `province` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `province_number` varchar(10) DEFAULT NULL,
                `province_id` varchar(10) NOT NULL,
                `name` varchar(150) NOT NULL,
                `logo` varchar(255) DEFAULT NULL,
                `image` varchar(255) DEFAULT NULL,
                `sort_order` tinyint unsigned DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `province_id` (`province_id`),
                KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $db->query(
            'CREATE TABLE `diocese` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `province_id` int unsigned NOT NULL,
                `name` varchar(255) NOT NULL,
                `alias_norm` varchar(255) DEFAULT NULL,
                `logo` varchar(255) DEFAULT NULL,
                `image` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `province_id_name` (`province_id`,`name`),
                KEY `province_id` (`province_id`),
                KEY `idx_dioceses_province_id` (`province_id`),
                CONSTRAINT `dioceses_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $db->query(
            "CREATE TABLE `parish` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `parish_ueid` varchar(32) DEFAULT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT '1',
                `logo` varchar(255) DEFAULT NULL,
                `image` varchar(255) DEFAULT NULL,
                `diocese_id` int unsigned DEFAULT NULL,
                `public_token` varchar(64) NOT NULL DEFAULT '',
                `congregation_type` varchar(16) NOT NULL DEFAULT 'parish',
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                `deleted_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_parish_ueid` (`parish_ueid`),
                KEY `idx_parish_diocese_id` (`diocese_id`),
                CONSTRAINT `parish_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $db->query(
            'CREATE TABLE `parish_affiliation` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `parish_id` int unsigned NOT NULL,
                `person_id` int unsigned NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pa_parish` (`parish_id`),
                CONSTRAINT `fk_pa_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `parish_affiliation_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $db->query(
            "INSERT INTO `province` (`id`, `province_number`, `province_id`, `name`, `sort_order`)
             VALUES (1, NULL, 'IX', 'Latin America & Caribbean', 9)"
        );
        $db->query(
            "INSERT INTO `diocese` (`id`, `province_id`, `name`, `alias_norm`)
             VALUES (10, 1, 'Micronesia', NULL)"
        );
        $db->query(
            "INSERT INTO `parish` (`id`, `name`, `diocese_id`, `image`, `public_token`)
             VALUES (100, 'Legacy Parish', 10, 'legacy-banner.jpg', '')"
        );
        $db->query(
            'INSERT INTO `parish_affiliation` (`id`, `parish_id`, `person_id`) VALUES (1, 100, 1)'
        );

        $migration = new AlignHierarchySchemaAndRelationships(Database::forge($db));
        $migration->up();

        // Province aligned to 6 canonical columns + unique NOT NULL province_number.
        $this->assertSame(
            ['id', 'province_number', 'name', 'sort_order', 'created_at', 'updated_at'],
            $this->columnNames($db, 'province')
        );
        $provinceNumberSpec = $this->columnSpec($db, 'province', 'province_number');
        $this->assertSame('NO', $provinceNumberSpec['IS_NULLABLE']);
        $provinceUnique = $db->query(
            "SHOW INDEX FROM `province` WHERE Key_name = 'province_number' AND Non_unique = 0"
        )->getResultArray();
        $this->assertCount(1, $provinceUnique);
        $provinceRow = $db->table('province')->where('id', 1)->get()->getRowArray();
        $this->assertSame('IX', $provinceRow['province_number']);

        // Diocese aligned + alias_norm backfilled + RESTRICT/CASCADE FK.
        $this->assertSame(
            ['id', 'province_id', 'name', 'alias_norm', 'created_at', 'updated_at'],
            $this->columnNames($db, 'diocese')
        );
        $dioceseRow = $db->table('diocese')->where('id', 10)->get()->getRowArray();
        $this->assertSame('micronesia', $dioceseRow['alias_norm']);
        $this->assertSingleFk($db, 'diocese', 'province_id', 'province', 'fk_diocese_province', 'RESTRICT', 'CASCADE');

        // Parish image -> banner, nullable public_token, RESTRICT/CASCADE FK.
        $parishCols = $this->columnNames($db, 'parish');
        $this->assertContains('banner', $parishCols);
        $this->assertNotContains('image', $parishCols);
        $parishRow = $db->table('parish')->where('id', 100)->get()->getRowArray();
        $this->assertSame('legacy-banner.jpg', $parishRow['banner']);
        $this->assertNull($parishRow['public_token']);
        $publicTokenSpec = $this->columnSpec($db, 'parish', 'public_token');
        $this->assertSame('YES', $publicTokenSpec['IS_NULLABLE']);
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'RESTRICT', 'CASCADE');

        // Duplicate parish_affiliation FK cleaned up.
        $this->assertSingleFk(
            $db,
            'parish_affiliation',
            'parish_id',
            'parish',
            'parish_affiliation_parish_id_foreign',
            'CASCADE',
            'CASCADE'
        );

        // Re-running up() must remain idempotent.
        $migration->up();
        $this->assertSingleFk($db, 'diocese', 'province_id', 'province', 'fk_diocese_province', 'RESTRICT', 'CASCADE');
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'RESTRICT', 'CASCADE');

        // Deletion rehearsal: MySQL must block parent deletes when children exist.
        $this->assertDeleteBlocked($db, 'DELETE FROM `province` WHERE `id` = 1');
        $this->assertNotNull($db->table('diocese')->where('id', 10)->get()->getRowArray());

        $this->assertDeleteBlocked($db, 'DELETE FROM `diocese` WHERE `id` = 10');
        $parishAfter = $db->table('parish')->where('id', 100)->get()->getRowArray();
        $this->assertSame(10, (int) $parishAfter['diocese_id']);

        // Rollback rehearsal: down() must not weaken RESTRICT/CASCADE FKs.
        $migration->down();
        $this->assertSingleFk($db, 'diocese', 'province_id', 'province', 'fk_diocese_province', 'RESTRICT', 'CASCADE');
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'RESTRICT', 'CASCADE');
        $this->assertDeleteBlocked($db, 'DELETE FROM `province` WHERE `id` = 1');
        $this->assertDeleteBlocked($db, 'DELETE FROM `diocese` WHERE `id` = 10');
    }

    public function testDanglingSoftDeletedReferencesAndConflictingImagesAbortWithoutLosingFksOnMySql(): void
    {
        $db = $this->getIsolatedMySqlConnection();
        $this->dropTestTables($db);

        $createDriftedTables = static function (BaseConnection $db): void {
            $db->query(
                'CREATE TABLE `province` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `province_number` varchar(10) DEFAULT NULL,
                    `province_id` varchar(10) NOT NULL,
                    `name` varchar(150) NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
            );

            $db->query(
                'CREATE TABLE `diocese` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `province_id` int unsigned NOT NULL,
                    `name` varchar(255) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `province_id` (`province_id`),
                    CONSTRAINT `dioceses_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
            );

            $db->query(
                'CREATE TABLE `parish` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `diocese_id` int unsigned DEFAULT NULL,
                    `image` varchar(255) DEFAULT NULL,
                    `banner` varchar(255) DEFAULT NULL,
                    `deleted_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_parish_diocese_id` (`diocese_id`),
                    CONSTRAINT `parish_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
            );
        };

        // 1) Conflicting image/banner aborts before any DDL.
        $createDriftedTables($db);
        $db->query("INSERT INTO `province` (`id`, `province_number`, `province_id`, `name`) VALUES (1, NULL, 'IX', 'Province IX')");
        $db->query("INSERT INTO `diocese` (`id`, `province_id`, `name`) VALUES (10, 1, 'Dominican Republic')");
        $db->query(
            "INSERT INTO `parish` (`id`, `name`, `diocese_id`, `image`, `banner`)
             VALUES (42, 'Conflicting Parish', 10, 'old-facade.jpg', 'easter.jpg')"
        );

        $migration = new AlignHierarchySchemaAndRelationships(Database::forge($db));

        $thrown = null;
        try {
            $migration->up();
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertStringContainsString('42', $thrown->getMessage());
        $this->assertContains('province_id', $this->columnNames($db, 'province'));
        $this->assertSingleFk($db, 'diocese', 'province_id', 'province', 'dioceses_province_id_foreign', 'CASCADE', 'CASCADE');
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'SET NULL', 'CASCADE');
        $row = $db->table('parish')->where('id', 42)->get()->getRowArray();
        $this->assertSame('old-facade.jpg', $row['image']);
        $this->assertSame('easter.jpg', $row['banner']);

        // 2) Imported soft-deleted parish with dangling diocese_id (simulated mysqldump
        //    with FOREIGN_KEY_CHECKS=0) aborts preflight before any DDL.
        $this->dropTestTables($db);
        $createDriftedTables($db);
        $db->query("INSERT INTO `province` (`id`, `province_number`, `province_id`, `name`) VALUES (1, NULL, 'IX', 'Province IX')");
        $db->query("INSERT INTO `diocese` (`id`, `province_id`, `name`) VALUES (10, 1, 'Dominican Republic')");
        $db->query("INSERT INTO `parish` (`id`, `name`, `diocese_id`, `deleted_at`) VALUES (100, 'Active Valid Parish', 10, NULL)");
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $db->query(
                "INSERT INTO `parish` (`id`, `name`, `diocese_id`, `deleted_at`)
                 VALUES (101, 'Soft Deleted Dangling Parish', 99, '2026-01-01 00:00:00')"
            );
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        $danglingThrown = null;
        try {
            $migration->up();
        } catch (\RuntimeException $e) {
            $danglingThrown = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $danglingThrown);
        $this->assertStringContainsString('101', $danglingThrown->getMessage());
        $this->assertContains('province_id', $this->columnNames($db, 'province'));
        $this->assertSingleFk($db, 'diocese', 'province_id', 'province', 'dioceses_province_id_foreign', 'CASCADE', 'CASCADE');
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'SET NULL', 'CASCADE');

        // 3) Failure-path rehearsal of replaceForeignKey() itself: even when MySQL rejects
        //    the replacement constraint because of row 101, the original FK is preserved.
        $exposedMigration = new class (Database::forge($db)) extends AlignHierarchySchemaAndRelationships {
            public function exposeReplaceForeignKey(
                string $table,
                string $column,
                string $refTable,
                string $expectedName,
                string $deleteRule,
                string $updateRule
            ): void {
                $this->replaceForeignKey($table, $column, $refTable, $expectedName, $deleteRule, $updateRule);
            }
        };

        $fkFailure = null;
        try {
            $exposedMigration->exposeReplaceForeignKey(
                'parish',
                'diocese_id',
                'diocese',
                'parish_diocese_id_foreign',
                'RESTRICT',
                'CASCADE'
            );
        } catch (\Throwable $e) {
            $fkFailure = $e;
        }

        $this->assertInstanceOf(\Throwable::class, $fkFailure);
        $this->assertSingleFk($db, 'parish', 'diocese_id', 'diocese', 'parish_diocese_id_foreign', 'SET NULL', 'CASCADE');
    }

    private function getIsolatedMySqlConnection(): BaseConnection
    {
        $db = Database::connect('tests', false);
        if ($db->DBDriver !== 'MySQLi') {
            $db->close();
            $this->markTestSkipped('Hierarchy alignment MySQL upgrade test requires MySQLi fixture');
        }

        $dbName = (string) $db->getDatabase();
        $this->assertStringContainsString(
            'upgrade_test',
            $dbName,
            'Refusing to run destructive upgrade test outside an isolated *upgrade_test* database.'
        );

        $this->mysqlConnection = $db;

        return $db;
    }

    private function dropTestTables(BaseConnection $db): void
    {
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['parish_affiliation', 'parish', 'diocese', 'province'] as $table) {
                $db->query("DROP TABLE IF EXISTS `{$table}`");
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * @return list<string>
     */
    private function columnNames(BaseConnection $db, string $table): array
    {
        $rows = $db->query(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION ASC',
            [$table]
        )->getResultArray();

        return array_map(static fn (array $r): string => (string) $r['COLUMN_NAME'], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function columnSpec(BaseConnection $db, string $table, string $column): array
    {
        $row = $db->query(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        )->getRowArray();

        $this->assertIsArray($row, "Missing column {$table}.{$column}");

        return $row;
    }

    private function assertSingleFk(
        BaseConnection $db,
        string $table,
        string $column,
        string $refTable,
        string $expectedName,
        string $expectedDeleteRule,
        string $expectedUpdateRule
    ): void {
        $rows = $db->query(
            'SELECT kcu.CONSTRAINT_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
             FROM information_schema.KEY_COLUMN_USAGE kcu
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
               AND rc.TABLE_NAME = kcu.TABLE_NAME
             WHERE kcu.TABLE_SCHEMA = DATABASE()
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME = ?',
            [$table, $column, $refTable]
        )->getResultArray();

        $this->assertCount(1, $rows, "Expected exactly 1 FK on {$table}.{$column} -> {$refTable}");
        $this->assertSame($expectedName, $rows[0]['CONSTRAINT_NAME']);
        $this->assertSame($expectedDeleteRule, strtoupper((string) $rows[0]['DELETE_RULE']));
        $this->assertSame($expectedUpdateRule, strtoupper((string) $rows[0]['UPDATE_RULE']));
    }

    private function assertDeleteBlocked(BaseConnection $db, string $sql): void
    {
        $blocked = false;
        try {
            $db->query($sql);
        } catch (\Throwable) {
            $blocked = true;
        }

        $this->assertTrue($blocked, "Expected FK RESTRICT to block: {$sql}");
    }
}
