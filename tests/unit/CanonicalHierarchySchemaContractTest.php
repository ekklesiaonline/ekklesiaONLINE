<?php

namespace Tests\Unit;

use App\Database\Migrations\AlignHierarchySchemaAndRelationships;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class CanonicalHierarchySchemaContractTest extends CIUnitTestCase
{
    public function testCanonicalSchemaDefinesAlignedHierarchyTablesAndForeignKeys(): void
    {
        $sqlPath = ROOTPATH . 'app/Database/canonical_schema_tables.sql';
        $this->assertFileExists($sqlPath);
        $sql = (string) file_get_contents($sqlPath);

        $provinceDdl = $this->extractCreateTable($sql, 'province');
        $this->assertStringContainsString('`province_number` varchar(10)', $provinceDdl);
        $this->assertMatchesRegularExpression('/`province_number`\s+varchar\(10\)[^,\n]*NOT NULL/', $provinceDdl);
        $this->assertStringContainsString('UNIQUE KEY `province_number` (`province_number`)', $provinceDdl);
        $this->assertStringNotContainsString('`province_id`', $provinceDdl);
        $this->assertStringNotContainsString('`logo`', $provinceDdl);
        $this->assertStringNotContainsString('`image`', $provinceDdl);

        $dioceseDdl = $this->extractCreateTable($sql, 'diocese');
        $this->assertMatchesRegularExpression('/`province_id`\s+int unsigned NOT NULL/', $dioceseDdl);
        $this->assertStringContainsString('`alias_norm`', $dioceseDdl);
        $this->assertStringContainsString(
            'FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
            $dioceseDdl
        );
        $this->assertStringNotContainsString('`logo`', $dioceseDdl);
        $this->assertStringNotContainsString('`image`', $dioceseDdl);

        $parishDdl = $this->extractCreateTable($sql, 'parish');
        $this->assertStringContainsString('`diocese_id`', $parishDdl);
        $this->assertStringContainsString('`parish_ueid`', $parishDdl);
        $this->assertStringContainsString('`banner`', $parishDdl);
        $this->assertStringContainsString('`public_token`', $parishDdl);
        $this->assertStringContainsString(
            'FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
            $parishDdl
        );
        $this->assertStringNotContainsString('`image`', $parishDdl);
    }

    public function testHierarchyTransferScriptEnforcesCompleteInsertAndSchemaCheck(): void
    {
        $scriptPath = ROOTPATH . 'build/parish_hierarchy_transfer.sh';
        $this->assertFileExists($scriptPath);
        $script = (string) file_get_contents($scriptPath);

        $this->assertStringContainsString('--complete-insert', $script);
        $this->assertStringContainsString('check_canonical_schema', $script);

        $sqlPath = ROOTPATH . 'app/Database/canonical_schema_tables.sql';
        $sql     = (string) file_get_contents($sqlPath);

        foreach (['province', 'diocese', 'parish'] as $table) {
            $ddl = $this->extractCreateTable($sql, $table);
            preg_match_all('/^\s+`([^`]+)`\s+/m', $ddl, $colMatches);
            $canonicalCols = $colMatches[1];
            if ($table === 'parish' && ! in_array('congregation_type', $canonicalCols, true)) {
                // Added by 2026-10-02-185843_CreateClergyDeaneryFoundation.php after baseline DDL.
                $canonicalCols[] = 'congregation_type';
            }
            sort($canonicalCols);

            $matched = preg_match(
                '/assert_exact_columns\s+"\$db"\s+"\$role"\s+' . preg_quote($table, '/') . '\s+\\\\\R\s+\'([^\']+)\'/',
                $script,
                $scriptMatches
            );
            $this->assertSame(1, $matched, "Missing assert_exact_columns definition for {$table} in parish_hierarchy_transfer.sh");

            $scriptCols = preg_split('/\s+/', trim($scriptMatches[1])) ?: [];
            sort($scriptCols);

            $this->assertSame(
                $canonicalCols,
                $scriptCols,
                "Transfer script column parity list for {$table} must match canonical schema exactly"
            );
        }
    }

    public function testParishImageConflictGuardDetectsDistinctPopulatedBannerAndImage(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-27-213833_AlignHierarchySchemaAndRelationships.php';

        $connection = Database::connect('tests', false);
        $forge      = Database::forge($connection);
        $parish     = $connection->prefixTable('parish');

        $connection->query("DROP TABLE IF EXISTS {$parish}");
        $connection->query("CREATE TABLE {$parish} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            image TEXT NULL,
            banner TEXT NULL
        )");

        $migration = new class ($forge) extends AlignHierarchySchemaAndRelationships {
            public function checkConflicts(): void
            {
                $this->assertNoConflictingParishImages();
            }

            public function countUnmatched(): int
            {
                return $this->countUnmatchedParishImages();
            }
        };

        try {
            // Matching non-empty values do not conflict and require no copy.
            $connection->table('parish')->insert([
                'id'     => 1,
                'name'   => 'Matched Parish',
                'image'  => 'same.jpg',
                'banner' => 'same.jpg',
            ]);
            $migration->checkConflicts();
            $this->assertSame(0, $migration->countUnmatched());

            // Empty banner with populated image does not conflict; unmatched drops to 0 after copy.
            $connection->table('parish')->insert([
                'id'     => 2,
                'name'   => 'Legacy Image Parish',
                'image'  => 'legacy.jpg',
                'banner' => '',
            ]);
            $migration->checkConflicts();
            $this->assertSame(1, $migration->countUnmatched());

            $connection->query(
                "UPDATE {$parish} SET `banner` = `image`
                 WHERE (`banner` IS NULL OR TRIM(`banner`) = '')
                   AND `image` IS NOT NULL AND TRIM(`image`) <> ''"
            );
            $this->assertSame(0, $migration->countUnmatched());

            // Distinct populated image and banner must abort with the affected parish ID.
            $connection->table('parish')->insert([
                'id'     => 42,
                'name'   => 'Conflicting Parish',
                'image'  => 'old-facade.jpg',
                'banner' => 'easter.jpg',
            ]);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('42');
            $migration->checkConflicts();
        } finally {
            $connection->query("DROP TABLE IF EXISTS {$parish}");
            $connection->close();
        }
    }

    public function testHierarchyAlignmentRollbackDoesNotWeakenForeignKeys(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-27-213833_AlignHierarchySchemaAndRelationships.php';

        $method = new \ReflectionMethod(AlignHierarchySchemaAndRelationships::class, 'down');
        $file   = $method->getFileName();
        $this->assertNotFalse($file);

        $lines = file($file);
        $this->assertIsArray($lines);
        $downSource = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringNotContainsString('replaceForeignKey', $downSource);
        $this->assertStringNotContainsString('SET NULL', $downSource);
        $this->assertStringNotContainsString('ON DELETE CASCADE', $downSource);
        $this->assertStringNotContainsString('dioceses_province_id_foreign', $downSource);
        $this->assertStringNotContainsString('ALTER TABLE', $downSource);

        $connection = Database::connect('tests', false);
        $forge      = Database::forge($connection);
        $migration  = new AlignHierarchySchemaAndRelationships($forge);
        $migration->down();
        $connection->close();
    }

    private function extractCreateTable(string $sql, string $table): string
    {
        $pattern = '/CREATE TABLE `' . preg_quote($table, '/') . '`.*?;\n/s';
        $matched = preg_match($pattern, $sql, $matches);
        $this->assertSame(1, $matched, "Missing CREATE TABLE `{$table}` in canonical_schema_tables.sql");

        return $matches[0];
    }
}
