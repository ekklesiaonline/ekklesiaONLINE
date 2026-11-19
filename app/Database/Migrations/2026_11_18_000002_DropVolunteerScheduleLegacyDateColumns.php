<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drops the legacy `date`/`time_slot` columns on volunteer_schedule.
 *
 * The canonical schema kept `date DATE NOT NULL` (no default) alongside the
 * `service_date`/`service_time_slot` columns the model actually writes.
 * Under strict SQL mode every insert that only sets service_* fails with
 * "Field 'date' doesn't have a default value"; on relaxed schemas `date`
 * silently drifts from `service_date`.
 *
 * Rows that only carry the legacy values are backfilled into service_*
 * before the columns are dropped.
 */
class DropVolunteerScheduleLegacyDateColumns extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('volunteer_schedule')) {
            return;
        }

        $fields = $this->db->getFieldNames('volunteer_schedule');
        $table  = $this->db->protectIdentifiers('volunteer_schedule', true);

        if (in_array('date', $fields, true) && in_array('service_date', $fields, true)) {
            $this->db->query(
                'UPDATE ' . $table . '
                 SET service_date = `date`
                 WHERE service_date IS NULL AND `date` IS NOT NULL'
            );
        }

        if (in_array('time_slot', $fields, true) && in_array('service_time_slot', $fields, true)) {
            $this->db->query(
                'UPDATE ' . $table . "
                 SET service_time_slot = time_slot
                 WHERE (service_time_slot IS NULL OR service_time_slot = '')
                   AND time_slot IS NOT NULL"
            );
        }

        $drop = array_values(array_intersect(['date', 'time_slot'], $this->db->getFieldNames('volunteer_schedule')));
        if ($drop !== []) {
            $this->forge->dropColumn('volunteer_schedule', $drop);
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('volunteer_schedule')) {
            return;
        }

        $fields = $this->db->getFieldNames('volunteer_schedule');
        $add    = [];

        if (! in_array('date', $fields, true)) {
            $add['date'] = ['type' => 'DATE', 'null' => true];
        }
        if (! in_array('time_slot', $fields, true)) {
            $add['time_slot'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        }

        if ($add !== []) {
            $this->forge->addColumn('volunteer_schedule', $add);
        }
    }
}
