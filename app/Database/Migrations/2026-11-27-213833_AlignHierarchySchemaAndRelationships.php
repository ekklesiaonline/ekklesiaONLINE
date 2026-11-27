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

        $this->alignProvinceTable();
        $this->alignDioceseTable();
        $this->alignParishTable();
        $this->deduplicateParishAffiliationForeignKeys();

        $this->db->resetDataCache();
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'MySQLi' && $this->db->DBDriver !== 'mysql') {
            return;
        }

        if ($this->db->tableExists('parish') && $this->db->fieldExists('diocese_id', 'parish')) {
            $this->replaceForeignKey(
                'parish',
                'diocese_id',
                'diocese',
                'parish_diocese_id_foreign',
                'SET NULL',
                'CASCADE'
            );
        }

        if ($this->db->tableExists('diocese') && $this->db->fieldExists('province_id', 'diocese')) {
            $this->replaceForeignKey(
                'diocese',
                'province_id',
                'province',
                'dioceses_province_id_foreign',
                'CASCADE',
                'CASCADE'
            );
        }

        $this->db->resetDataCache();
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
            $this->db->query(
                "UPDATE `parish` SET `banner` = `image`
                 WHERE (`banner` IS NULL OR `banner` = '')
                   AND `image` IS NOT NULL AND `image` <> ''"
            );
            $remaining = $this->db->query(
                "SELECT COUNT(*) AS c FROM `parish`
                 WHERE `image` IS NOT NULL AND `image` <> ''
                   AND (`banner` IS NULL OR `banner` = '')"
            )->getRowArray();
            if ((int) ($remaining['c'] ?? 0) === 0) {
                $this->db->query('ALTER TABLE `parish` DROP COLUMN `image`');
            }
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

    private function replaceForeignKey(
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

        foreach ($existing as $fk) {
            $fkName = (string) $fk['CONSTRAINT_NAME'];
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }

        $this->db->query(
            "ALTER TABLE `{$table}`
             ADD CONSTRAINT `{$expectedName}`
             FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`)
             ON DELETE {$deleteRule} ON UPDATE {$updateRule}"
        );
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
