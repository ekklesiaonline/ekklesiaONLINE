<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drops event_registration.guest_id + its FK to the retired `guest` table (T4.5).
 *
 * `fk_er_guest` referenced guest(id); dropping `guest` left a dangling
 * constraint. Live code never writes guest_id — walk-in registrations use the
 * free-text guest_name/guest_email/guest_phone columns and registered members
 * use person_id (fk_er_person). See docs/LEGACY_PATH_RETIREMENT.md.
 */
class DropEventRegistrationGuestFk extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('event_registration')) {
            return;
        }

        $constraints = array_column(
            $this->db->query(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_registration'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
            )->getResultArray(),
            'CONSTRAINT_NAME'
        );
        if (in_array('fk_er_guest', $constraints, true)) {
            $this->forge->dropForeignKey('event_registration', 'fk_er_guest');
        }

        $indexes = array_column(
            $this->db->query("SHOW INDEX FROM event_registration")->getResultArray(),
            'Key_name'
        );
        if (in_array('uk_er_event_guest', $indexes, true)) {
            $this->db->query('ALTER TABLE event_registration DROP INDEX uk_er_event_guest');
        }

        if ($this->db->fieldExists('guest_id', 'event_registration')) {
            $this->forge->dropColumn('event_registration', 'guest_id');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('event_registration') || $this->db->fieldExists('guest_id', 'event_registration')) {
            return;
        }

        $this->forge->addColumn('event_registration', [
            'guest_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'person_id',
            ],
        ]);
        // The guest table no longer exists, so the FK cannot be restored.
    }
}
