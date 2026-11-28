<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reconciles schema and FK relationships on province, diocese, parish, and
 * parish_affiliation with the canonical migration schema.
 */
class AlignHierarchySchemaAndRelationships extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'MySQLi' && $this->db->DBDriver !== 'mysql') {
            return;
        }

        // Detect conflicting image/banner data and dangling FKs before executing any DDL.
        $this->assertNoConflictingParishImages();
        $this->assertNoDanglingHierarchyReferences();

        $this->alignProvinceTable();
        $this->alignDioceseTable();
        $this->alignParishTable();
        $this->deduplicateParishAffiliationForeignKeys();

        $this->db->resetDataCache();
    }

    public function down()
    {
        // Forward-only schema reconciliation: the target state (ON DELETE RESTRICT
        // ON UPDATE CASCADE on fk_diocese_province and parish_diocese_id_foreign,
        // plus canonical columns without legacy image/logo/province_id) matches
        // the fresh-install baseline in canonical_schema_tables.sql. Rolling
        // back must never weaken baseline FK delete rules or restore dropped
        // legacy columns.
    }

    private function alignProvinceTable(): void
    {
        if (! $this->db->tableExists('province')) {
            return;
        }

        if ($this->db->fieldExists('province_id', 'province')) {
            $this->db->query(
                "UPDATE `province` SET `province_number` = `province_id`
                 WHERE `province_number` IS NULL OR TRIM(`province_number`) = ''"
            );
        }

        if ($this->db->fieldExists('province_number', 'province')) {
            $this->db->query(
                'ALTER TABLE `province` MODIFY COLUMN `province_number` VARCHAR(10) NOT NULL'
            );

            if (! $this->indexExists('province', 'province_number')) {
                $this->db->query(
                    'ALTER TABLE `province` ADD UNIQUE KEY `province_number` (`province_number`)'
                );
            }
        }

        if ($this->db->fieldExists('province_id', 'province')) {
            $this->db->query('ALTER TABLE `province` DROP COLUMN `province_id`');
        }

        foreach (['logo', 'image'] as $column) {
            $this->dropColumnIfEmpty('province', $column);
        }
    }

    private function alignDioceseTable(): void
    {
        if (! $this->db->tableExists('diocese')) {
            return;
        }

        foreach (['logo', 'image'] as $column) {
            $this->dropColumnIfEmpty('diocese', $column);
        }

        if ($this->db->fieldExists('alias_norm', 'diocese')) {
            $rows = $this->db->table('diocese')
                ->select('id, name')
                ->groupStart()
                ->where('alias_norm IS NULL', null, false)
                ->orWhere("TRIM(alias_norm) = ''", null, false)
                ->groupEnd()
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $norm = $this->normalizeAlias((string) ($row['name'] ?? ''));
                if ($norm !== '') {
                    $this->db->table('diocese')
                        ->where('id', (int) $row['id'])
                        ->update(['alias_norm' => $norm]);
                }
            }
        }

        if ($this->db->fieldExists('province_id', 'diocese')) {
            $this->replaceForeignKey(
                'diocese',
                'province_id',
                'province',
                'fk_diocese_province',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    private function alignParishTable(): void
    {
        if (! $this->db->tableExists('parish')) {
            return;
        }

        $hasImage  = $this->db->fieldExists('image', 'parish');
        $hasBanner = $this->db->fieldExists('banner', 'parish');

        if ($hasImage && ! $hasBanner) {
            $this->db->query(
                'ALTER TABLE `parish` CHANGE COLUMN `image` `banner` VARCHAR(255) NULL DEFAULT NULL'
            );
        } elseif ($hasImage && $hasBanner) {
            $this->assertNoConflictingParishImages();

            $this->db->query(
                "UPDATE `parish` SET `banner` = `image`
                 WHERE (`banner` IS NULL OR TRIM(`banner`) = '')
                   AND `image` IS NOT NULL AND TRIM(`image`) <> ''"
            );

            $unmatched = $this->countUnmatchedParishImages();
            if ($unmatched > 0) {
                throw new \RuntimeException(sprintf(
                    'Cannot drop parish.image: %d parish row(s) still have uncopied or mismatched image values.',
                    $unmatched
                ));
            }

            $this->db->query('ALTER TABLE `parish` DROP COLUMN `image`');
        } elseif (! $hasBanner) {
            $this->db->query(
                'ALTER TABLE `parish` ADD COLUMN `banner` VARCHAR(255) NULL DEFAULT NULL AFTER `logo`'
            );
        }

        if ($this->db->fieldExists('public_token', 'parish')) {
            $this->db->query(
                'ALTER TABLE `parish` MODIFY COLUMN `public_token` VARCHAR(64) NULL DEFAULT NULL'
            );
            $this->db->query(
                "UPDATE `parish` SET `public_token` = NULL WHERE `public_token` = ''"
            );
        }

        if ($this->db->fieldExists('diocese_id', 'parish')) {
            $this->replaceForeignKey(
                'parish',
                'diocese_id',
                'diocese',
                'parish_diocese_id_foreign',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    private function deduplicateParishAffiliationForeignKeys(): void
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $rows = $this->db->query(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'parish_affiliation'
               AND COLUMN_NAME = 'parish_id'
               AND REFERENCED_TABLE_NAME = 'parish'"
        )->getResultArray();

        $names = array_column($rows, 'CONSTRAINT_NAME');
        if (
            in_array('fk_pa_parish', $names, true)
            && in_array('parish_affiliation_parish_id_foreign', $names, true)
        ) {
            $this->db->query('ALTER TABLE `parish_affiliation` DROP FOREIGN KEY `fk_pa_parish`');
        }
    }

    private function dropColumnIfEmpty(string $table, string $column): void
    {
        if (! $this->db->fieldExists($column, $table)) {
            return;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> ''"
        )->getRowArray();

        if ((int) ($row['c'] ?? 0) === 0) {
            $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$table, $indexName]
        )->getRowArray();

        return (int) ($row['c'] ?? 0) > 0;
    }

    protected function replaceForeignKey(
        string $table,
        string $column,
        string $refTable,
        string $expectedName,
        string $deleteRule,
        string $updateRule
    ): void {
        $existing = $this->db->query(
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

        if (
            count($existing) === 1
            && $existing[0]['CONSTRAINT_NAME'] === $expectedName
            && strtoupper((string) $existing[0]['DELETE_RULE']) === strtoupper($deleteRule)
            && strtoupper((string) $existing[0]['UPDATE_RULE']) === strtoupper($updateRule)
        ) {
            return;
        }

        $existingNames = array_map(
            static fn (array $fk): string => (string) $fk['CONSTRAINT_NAME'],
            $existing
        );

        // Add and validate the replacement constraint BEFORE dropping any old FK
        // so a validation failure never leaves the column unconstrained.
        if (! in_array($expectedName, $existingNames, true)) {
            $this->db->query(
                "ALTER TABLE `{$table}`
                 ADD CONSTRAINT `{$expectedName}`
                 FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`)
                 ON DELETE {$deleteRule} ON UPDATE {$updateRule}"
            );

            foreach ($existingNames as $fkName) {
                $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }

            return;
        }

        $tempName = $expectedName . '_next';
        if (! in_array($tempName, $existingNames, true)) {
            $this->db->query(
                "ALTER TABLE `{$table}`
                 ADD CONSTRAINT `{$tempName}`
                 FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`)
                 ON DELETE {$deleteRule} ON UPDATE {$updateRule}"
            );
        }

        foreach ($existingNames as $fkName) {
            if ($fkName !== $tempName) {
                $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        $this->db->query(
            "ALTER TABLE `{$table}`
             ADD CONSTRAINT `{$expectedName}`
             FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`)
             ON DELETE {$deleteRule} ON UPDATE {$updateRule}"
        );
        $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$tempName}`");
    }

    protected function assertNoDanglingHierarchyReferences(): void
    {
        if (
            $this->db->tableExists('diocese')
            && $this->db->tableExists('province')
            && $this->db->fieldExists('province_id', 'diocese')
        ) {
            $dioceseTable  = $this->db->prefixTable('diocese');
            $provinceTable = $this->db->prefixTable('province');

            $danglingDioceses = $this->db->query(
                "SELECT d.`id`
                 FROM `{$dioceseTable}` d
                 LEFT JOIN `{$provinceTable}` p ON p.`id` = d.`province_id`
                 WHERE d.`province_id` IS NOT NULL AND p.`id` IS NULL
                 ORDER BY d.`id` ASC"
            )->getResultArray();

            if ($danglingDioceses !== []) {
                $ids = array_map(static fn (array $row): string => (string) (int) $row['id'], $danglingDioceses);
                throw new \RuntimeException(sprintf(
                    'Cannot align diocese.province_id FK: dangling province_id reference on diocese ID(s): %s. Repair these rows before re-running the migration.',
                    implode(', ', $ids)
                ));
            }
        }

        if (
            $this->db->tableExists('parish')
            && $this->db->tableExists('diocese')
            && $this->db->fieldExists('diocese_id', 'parish')
        ) {
            $parishTable  = $this->db->prefixTable('parish');
            $dioceseTable = $this->db->prefixTable('diocese');

            // Include soft-deleted rows (deleted_at IS NOT NULL) because MySQL validates
            // all rows regardless of soft-delete status when adding a foreign key.
            $danglingParishes = $this->db->query(
                "SELECT pa.`id`
                 FROM `{$parishTable}` pa
                 LEFT JOIN `{$dioceseTable}` d ON d.`id` = pa.`diocese_id`
                 WHERE pa.`diocese_id` IS NOT NULL AND d.`id` IS NULL
                 ORDER BY pa.`id` ASC"
            )->getResultArray();

            if ($danglingParishes !== []) {
                $ids = array_map(static fn (array $row): string => (string) (int) $row['id'], $danglingParishes);
                throw new \RuntimeException(sprintf(
                    'Cannot align parish.diocese_id FK: dangling diocese_id reference (including soft-deleted rows, which MySQL validates) on parish ID(s): %s. Repair these rows before re-running the migration.',
                    implode(', ', $ids)
                ));
            }
        }
    }

    protected function assertNoConflictingParishImages(): void
    {
        if (
            ! $this->db->tableExists('parish')
            || ! $this->db->fieldExists('image', 'parish')
            || ! $this->db->fieldExists('banner', 'parish')
        ) {
            return;
        }

        $parishTable = $this->db->prefixTable('parish');
        $rows = $this->db->query(
            "SELECT `id` FROM `{$parishTable}`
             WHERE `image` IS NOT NULL AND TRIM(`image`) <> ''
               AND `banner` IS NOT NULL AND TRIM(`banner`) <> ''
               AND TRIM(`banner`) <> TRIM(`image`)
             ORDER BY `id` ASC"
        )->getResultArray();

        if ($rows !== []) {
            $ids = array_map(static fn (array $row): string => (string) (int) $row['id'], $rows);
            throw new \RuntimeException(sprintf(
                'Cannot drop parish.image: conflicting non-empty image and banner values on parish ID(s): %s. Reconcile these rows before re-running the migration.',
                implode(', ', $ids)
            ));
        }
    }

    protected function countUnmatchedParishImages(): int
    {
        if (
            ! $this->db->tableExists('parish')
            || ! $this->db->fieldExists('image', 'parish')
            || ! $this->db->fieldExists('banner', 'parish')
        ) {
            return 0;
        }

        $parishTable = $this->db->prefixTable('parish');
        $row = $this->db->query(
            "SELECT COUNT(*) AS c FROM `{$parishTable}`
             WHERE `image` IS NOT NULL AND TRIM(`image`) <> ''
               AND (`banner` IS NULL OR TRIM(`banner`) <> TRIM(`image`))"
        )->getRowArray();

        return (int) ($row['c'] ?? 0);
    }

    private function normalizeAlias(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (class_exists('Transliterator')) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($transliterator !== null) {
                $value = $transliterator->transliterate($value);
            }
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
