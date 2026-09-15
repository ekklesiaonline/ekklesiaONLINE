<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNationalProvinceScopeToEventCalendar extends Migration
{
    public function up()
    {
        $this->forge->addColumn('event_calendar', [
            'parish_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'diocese_id',
            ],
            'province_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'parish_id',
            ],
        ]);

        $this->db->query("ALTER TABLE `event_calendar` MODIFY `scope_level` ENUM('national','province','diocese','parish') NOT NULL DEFAULT 'parish'");

        $this->db->query("ALTER TABLE `event_calendar` ADD CONSTRAINT `fk_event_calendar_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->db->query("ALTER TABLE `event_calendar` ADD CONSTRAINT `fk_event_calendar_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");

        $this->db->query("ALTER TABLE `event_calendar` ADD KEY `idx_event_calendar_parish` (`parish_id`)");
        $this->db->query("ALTER TABLE `event_calendar` ADD KEY `idx_event_calendar_province` (`province_id`)");

        $this->db->table('event_calendar')->insert([
            'scope_level' => 'national',
            'parish_id'   => 1,
            'name'        => 'National Episcopal Calendar',
            'color_hex'   => '#0d6efd',
            'is_active'   => 1,
            'sort_order'  => 0,
        ]);

        $provinceIds = $this->db->table('province')->select('id')->get()->getResultArray();
        foreach ($provinceIds as $province) {
            $provinceRow = $this->db->table('province')->where('id', (int) $province['id'])->get()->getRowArray();
            if (! $provinceRow) {
                continue;
            }

            $this->db->table('event_calendar')->insert([
                'scope_level' => 'province',
                'province_id' => (int) $province['id'],
                'name'        => $provinceRow['name'] . ' Province Calendar',
                'color_hex'   => '#198754',
                'is_active'   => 1,
                'sort_order'  => 0,
            ]);
        }
    }

    public function down()
    {
        $this->db->query("ALTER TABLE `event_calendar` DROP FOREIGN KEY `fk_event_calendar_parish`");
        $this->db->query("ALTER TABLE `event_calendar` DROP FOREIGN KEY `fk_event_calendar_province`");
        $this->forge->dropColumn('event_calendar', ['province_id', 'parish_id']);
        $this->db->query("ALTER TABLE `event_calendar` MODIFY `scope_level` ENUM('parish','diocese') NOT NULL DEFAULT 'parish'");
    }
}
