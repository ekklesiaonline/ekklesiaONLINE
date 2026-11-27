<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

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
    }

    private function extractCreateTable(string $sql, string $table): string
    {
        $pattern = '/CREATE TABLE `' . preg_quote($table, '/') . '`.*?;\n/s';
        $matched = preg_match($pattern, $sql, $matches);
        $this->assertSame(1, $matched, "Missing CREATE TABLE `{$table}` in canonical_schema_tables.sql");

        return $matches[0];
    }
}
