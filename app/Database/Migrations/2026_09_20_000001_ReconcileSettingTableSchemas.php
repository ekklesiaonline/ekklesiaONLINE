<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reconcile parish/diocese/province/organisation_setting tables to the
 * live shape used by the application: key/value/is_encrypted with
 * unique index on the scope column + key.
 *
 * The canonical dumps had a divergent shape (setting_key/setting_value/
 * data_type/description) while the dev/production tables already used
 * key/value/is_encrypted. This migration is idempotent so it safely runs on
 * either shape.
 */
class ReconcileSettingTableSchemas extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $tables = [
        'diocese_setting'      => ['diocese_id'],
        'province_setting'     => ['province_id'],
        'organisation_setting' => [],
        'parish_setting'       => ['parish_id'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $scopeColumns) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            $fields = $this->db->getFieldNames($table);

            // Rename canonical columns to live column names.
            if (in_array('setting_key', $fields, true) && !in_array('key', $fields, true)) {
                $this->db->query("ALTER TABLE {$table} CHANGE COLUMN setting_key `key` VARCHAR(255) NOT NULL");
            }

            if (in_array('setting_value', $fields, true) && !in_array('value', $fields, true)) {
                $this->db->query("ALTER TABLE {$table} CHANGE COLUMN setting_value `value` LONGTEXT NULL");
            }

            // Drop canonical-only columns if present.
            foreach (['data_type', 'description'] as $dropColumn) {
                if (in_array($dropColumn, $fields, true)) {
                    $this->db->query("ALTER TABLE {$table} DROP COLUMN {$dropColumn}");
                }
            }

            // Add is_encrypted if missing.
            if (!in_array('is_encrypted', $fields, true)) {
                $this->db->query("ALTER TABLE {$table} ADD COLUMN is_encrypted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
            }

            // Refresh fields for validation and key rebuild.
            $fields = $this->db->getFieldNames($table);
            if (!in_array('key', $fields, true) || !in_array('value', $fields, true)) {
                throw new \RuntimeException("Table {$table} is missing required key/value columns after reconciliation.");
            }

            $this->rebuildUniqueKey($table, $scopeColumns);
        }
    }

    public function down(): void
    {
        // Intentionally no-op: the previous shape was inconsistent with the
        // application code and the data already in these tables, so rolling
        // back is not safe.
    }

    /**
     * Ensure the unique key scope column(s) + key exists. Preserve an
     * existing unique key with the correct columns to avoid breaking a
     * foreign-key index that MySQL uses on the scope column.
     *
     * @param string       $table
     * @param list<string> $scopeColumns
     */
    private function rebuildUniqueKey(string $table, array $scopeColumns): void
    {
        $keyColumns = array_merge($scopeColumns, ['key']);
        $keyName = 'uk_' . $table . '_' . implode('_', $keyColumns);

        $existingKeys = $this->db->query("SHOW INDEX FROM {$table}")->getResultArray();

        // Build map of unique index name -> ordered columns.
        $uniqueGroups = [];
        foreach ($existingKeys as $idx) {
            if ((int) $idx['Non_unique'] !== 0 || $idx['Key_name'] === 'PRIMARY') {
                continue;
            }
            $uniqueGroups[$idx['Key_name']][$idx['Seq_in_index']] = $idx['Column_name'];
        }

        $targetColumnList = array_values($keyColumns);
        foreach ($uniqueGroups as $existingName => $columnsBySeq) {
            ksort($columnsBySeq);
            if (array_values($columnsBySeq) === $targetColumnList) {
                // Correct unique key already exists; nothing to do.
                return;
            }
        }

        // Before dropping any unique key, add a plain index on the scope
        // column so a foreign key on that column still has an index to use.
        foreach ($scopeColumns as $scopeColumn) {
            $this->db->query("ALTER TABLE {$table} ADD INDEX idx_{$table}_{$scopeColumn} ({$scopeColumn})");
        }

        // Drop incorrect/stale unique keys.
        foreach ($uniqueGroups as $existingName => $columnsBySeq) {
            $this->db->query("ALTER TABLE {$table} DROP INDEX {$existingName}");
        }

        $this->db->query(
            'ALTER TABLE ' . $table . ' ADD UNIQUE KEY ' . $keyName
            . ' (' . implode(',', $keyColumns) . ')'
        );
    }
}
