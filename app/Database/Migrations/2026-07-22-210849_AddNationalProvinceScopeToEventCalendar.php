<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNationalProvinceScopeToEventCalendar extends Migration
{
    public function up()
    {
        $toAdd = [];
        if (! $this->db->fieldExists('parish_id', 'event_calendar')) {
            $toAdd['parish_id'] = [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'diocese_id',
            ];
        }
        if (! $this->db->fieldExists('province_id', 'event_calendar')) {
            $toAdd['province_id'] = [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'parish_id',
            ];
        }
        if ($toAdd !== []) {
            $this->forge->addColumn('event_calendar', $toAdd);
        }

        $this->db->query("ALTER TABLE `event_calendar` MODIFY `scope_level` ENUM('national','province','diocese','parish') NOT NULL DEFAULT 'parish'");

        $this->ensureForeignKey('event_calendar', 'parish_id', 'fk_event_calendar_parish', 'parish');
        $this->ensureForeignKey('event_calendar', 'province_id', 'fk_event_calendar_province', 'province');

        $this->ensureIndex('event_calendar', 'idx_event_calendar_parish', '(`parish_id`)');
        $this->ensureIndex('event_calendar', 'idx_event_calendar_province', '(`province_id`)');

        $hasNational = $this->db->table('event_calendar')->where('scope_level', 'national')->countAllResults() > 0;
        $parishOne   = $this->db->table('parish')->where('id', 1)->countAllResults() > 0;
        if (! $hasNational && $parishOne) {
            $this->db->table('event_calendar')->insert([
                'scope_level' => 'national',
                'parish_id'   => 1,
                'name'        => 'National Episcopal Calendar',
                'color_hex'   => '#0d6efd',
                'is_active'   => 1,
                'sort_order'  => 0,
            ]);
        }

        $provinceIds = $this->db->table('province')->select('id, name')->get()->getResultArray();
        foreach ($provinceIds as $provinceRow) {
            $exists = $this->db->table('event_calendar')
                ->where('scope_level', 'province')
                ->where('province_id', (int) $provinceRow['id'])
                ->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $this->db->table('event_calendar')->insert([
                'scope_level' => 'province',
                'province_id' => (int) $provinceRow['id'],
                'name'        => $provinceRow['name'] . ' Province Calendar',
                'color_hex'   => '#198754',
                'is_active'   => 1,
                'sort_order'  => 0,
            ]);
        }
    }

    private function ensureForeignKey(string $table, string $column, string $name, string $refTable): void
    {
        $exists = $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->db->database, $table, $column]
        )->getRow('c') > 0;

        if (! $exists) {
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
    }

    private function ensureIndex(string $table, string $name, string $columns): void
    {
        $exists = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name])->getNumRows() > 0;
        if (! $exists) {
            $this->db->query("ALTER TABLE `{$table}` ADD KEY `{$name}` {$columns}");
        }
    }

    public function down()
    {
        foreach (['fk_event_calendar_parish', 'fk_event_calendar_province'] as $fk) {
            $exists = $this->db->query(
                'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
                [$this->db->database, 'event_calendar', $fk, 'FOREIGN KEY']
            )->getRow('c') > 0;
            if ($exists) {
                $this->db->query("ALTER TABLE `event_calendar` DROP FOREIGN KEY `{$fk}`");
            }
        }

        $toDrop = [];
        foreach (['province_id', 'parish_id'] as $column) {
            if ($this->db->fieldExists($column, 'event_calendar')) {
                $toDrop[] = $column;
            }
        }
        if ($toDrop !== []) {
            $this->forge->dropColumn('event_calendar', $toDrop);
        }

        $this->db->query("ALTER TABLE `event_calendar` MODIFY `scope_level` ENUM('parish','diocese') NOT NULL DEFAULT 'parish'");
    }
}
