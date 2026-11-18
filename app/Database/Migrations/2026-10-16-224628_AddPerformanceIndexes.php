<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPerformanceIndexes extends Migration
{
    private const INDEXES = [
        'parish_affiliation' => [
            'idx_pa_linked_primary_deleted' => ['linked_user_id', 'is_primary', 'deleted_at'],
        ],
        'service' => [
            'idx_service_parish_date' => ['parish_id', 'service_date'],
        ],
        'attendance' => [
            'idx_attendance_service_attended' => ['service_id', 'attended'],
        ],
        'donation' => [
            'idx_donation_parish_date_deleted' => ['parish_id', 'date', 'deleted_at'],
        ],
        'expense' => [
            'idx_expense_parish_date' => ['parish_id', 'date'],
        ],
        'event' => [
            'idx_event_parish_scope_start' => ['organizer_parish_id', 'organizer_type', 'deleted_at', 'start_at'],
            'idx_event_diocese_scope_start' => ['organizer_diocese_id', 'organizer_type', 'deleted_at', 'start_at'],
            'idx_event_recurrence_range' => ['recurrence_pattern', 'recurrence_until', 'start_at'],
        ],
        'event_registration' => [
            'idx_event_registration_event' => ['event_id'],
        ],
        'member_standing_summary' => [
            'idx_standing_year_status_user' => ['year', 'is_good_standing', 'user_id'],
        ],
        'audit_log' => [
            'idx_audit_created' => ['created_at'],
            'idx_audit_entity_created' => ['entity_type', 'created_at'],
            'idx_audit_user_created' => ['user_id', 'created_at'],
        ],
    ];

    public function up()
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            $existing = $this->db->getIndexData($table);
            foreach ($indexes as $name => $columns) {
                if (isset($existing[$name])) {
                    continue;
                }
                $this->forge->addKey($columns, false, false, $name);
                $this->forge->processIndexes($table);
            }
        }

        if ($this->supportsFunctionalIndexes() && $this->db->tableExists('profile') && ! isset($this->db->getIndexData('profile')['idx_profile_birth_month_day'])) {
            $table = $this->db->escapeIdentifiers($this->db->prefixTable('profile'));
            $expression = $this->db->DBDriver === 'SQLite3'
                ? "(CAST(strftime('%m', birth_date) AS INTEGER) * 100 + CAST(strftime('%d', birth_date) AS INTEGER))"
                : '((MONTH(birth_date) * 100 + DAYOFMONTH(birth_date)))';
            $this->db->query("CREATE INDEX idx_profile_birth_month_day ON {$table} {$expression}");
        }
    }

    /**
     * MariaDB has no functional key parts; skip the optional birthday index there.
     */
    private function supportsFunctionalIndexes(): bool
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return true;
        }

        return stripos($this->db->getVersion(), 'mariadb') === false;
    }

    public function down()
    {
        if ($this->db->tableExists('profile') && isset($this->db->getIndexData('profile')['idx_profile_birth_month_day'])) {
            $this->forge->dropKey('profile', 'idx_profile_birth_month_day');
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            $existing = $this->db->getIndexData($table);
            $fkColumnSets = array_map(
                static fn ($fk) => array_map('strtolower', (array) ($fk->column_name ?? [])),
                $this->db->getForeignKeyData($table)
            );
            foreach ($indexes as $name => $columns) {
                if (! isset($existing[$name]) || $this->isSoleIndexCoveringForeignKey($existing, $fkColumnSets, $name, $columns)) {
                    continue;
                }
                $this->forge->dropKey($table, $name);
                unset($existing[$name]);
            }
        }
    }

    /**
     * An index whose leftmost columns match a foreign key backs that
     * constraint; dropping it fails when no sibling index also covers it.
     */
    private function isSoleIndexCoveringForeignKey(array $existing, array $fkColumnSets, string $indexName, array $columns): bool
    {
        $indexColumns = array_map('strtolower', $columns);

        foreach ($fkColumnSets as $fkColumns) {
            if ($fkColumns === [] || array_slice($indexColumns, 0, count($fkColumns)) !== $fkColumns) {
                continue;
            }

            foreach ($existing as $otherName => $other) {
                if ($otherName === $indexName) {
                    continue;
                }
                $otherColumns = array_map('strtolower', (array) ($other->fields ?? []));
                if (array_slice($otherColumns, 0, count($fkColumns)) === $fkColumns) {
                    continue 2; // another index backs this FK
                }
            }

            return true;
        }

        return false;
    }
}
