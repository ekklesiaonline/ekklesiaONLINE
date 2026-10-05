<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Parish "hire" requests: a parish proposes a clergy office for a person in
 * its own diocese; the diocesan review queue approves or rejects it.
 * A row only ever records workflow state — approval is what activates a
 * leadership_position via the diocesan office-approval path.
 */
class CreateClergyAppointmentRequest extends Migration
{
    public function up()
    {
        foreach (['person', 'users', 'diocese', 'parish', 'leadership_position'] as $required) {
            if (! $this->db->tableExists($required)) {
                throw new RuntimeException("Missing canonical table: {$required}");
            }
        }
        if ($this->db->tableExists('clergy_appointment_request')) {
            throw new RuntimeException('clergy_appointment_request already exists; review for a partial install.');
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'person_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'parish_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'diocese_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'proposed_office' => ['type' => 'VARCHAR', 'constraint' => 100],
            'review_status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'review_reason_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'requested_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'reviewed_by_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'position_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['diocese_id', 'review_status']);
        $this->forge->addKey(['parish_id', 'review_status']);
        $this->forge->addForeignKey('person_id', 'person', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('parish_id', 'parish', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('diocese_id', 'diocese', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('requested_by_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('clergy_appointment_request', false,
            $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : []);

        // Clergy are looked up by their diocese membership: index the profile
        // diocese column used by the diocesan clergy directory queries.
        if ($this->db->DBDriver === 'MySQLi' && $this->db->tableExists('profile')
            && $this->db->fieldExists('diocese_id', 'profile')) {
            $this->db->query('CREATE INDEX idx_profile_diocese ON '
                . $this->db->protectIdentifiers('profile') . ' (diocese_id)');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('clergy_appointment_request')
            && $this->db->table('clergy_appointment_request')->countAllResults() > 0) {
            throw new RuntimeException('Cannot discard reviewed appointment requests; restore from backup or forward-fix.');
        }
        $this->forge->dropTable('clergy_appointment_request', true);
    }
}
