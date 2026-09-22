<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Schema hygiene guard (docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md —
 * Phase 5).
 *
 * Migrations are the only schema truth. The single *.sql file permitted
 * under app/Database/ is canonical_schema_tables.sql, which is not a
 * snapshot — it is the input the CreateCanonicalSchema baseline migration
 * executes on fresh installs. Any other committed SQL dump would drift
 * from the migration chain and mislead fresh environments.
 *
 * @internal
 */
final class SchemaHygieneTest extends CIUnitTestCase
{
    /**
     * The only SQL file allowed under app/Database/ — the baseline
     * migration's schema source.
     */
    private const ALLOWED_SQL_FILES = [
        'canonical_schema_tables.sql',
    ];

    public function testNoCommittedSqlDumpsUnderAppDatabase(): void
    {
        $dir = APPPATH . 'Database';
        $this->assertDirectoryExists($dir);

        $stray = [];
        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            $name = basename($file);
            if (! in_array($name, self::ALLOWED_SQL_FILES, true)) {
                $stray[] = $name;
            }
        }

        $this->assertSame(
            [],
            $stray,
            'Committed SQL dumps drift from migration truth — only '
            . implode(', ', self::ALLOWED_SQL_FILES)
            . ' (the baseline migration input) may live under app/Database/.'
        );
    }

    public function testBaselineMigrationReadsOnlyTheCanonicalTablesFile(): void
    {
        $migration = APPPATH . 'Database/Migrations/2026_05_12_000001_CreateCanonicalSchema.php';
        $this->assertFileExists($migration);

        $source = (string) file_get_contents($migration);

        $this->assertStringContainsString(
            'canonical_schema_tables.sql',
            $source,
            'CreateCanonicalSchema must keep reading canonical_schema_tables.sql.'
        );
        foreach (['canonical_schema.sql', 'canonical_schema_tables_ordered.sql', 'localhost.sql'] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $source,
                "CreateCanonicalSchema must not reference the retired {$retired} dump."
            );
        }
    }
}
