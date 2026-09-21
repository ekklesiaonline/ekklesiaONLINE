<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates user_trusted_device: durable "remember this device" records.
 *
 * Trust is decoupled from the short-lived user_session rows. A successful
 * device verification issues a random selector+validator cookie; only the
 * SHA-256 hash of the validator is stored here. Records expire after
 * security.trustedDeviceTtlDays (default 30) and can be revoked by the
 * user or by security actions (password change, suspension, ban).
 */
class CreateUserTrustedDeviceTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'selector' => [
                'type' => 'VARCHAR',
                'constraint' => 24,
                'null' => false,
            ],
            'token_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => false,
            ],
            'display_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'last_ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'first_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('selector');
        $this->forge->addKey('user_id');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('user_trusted_device', true);
    }

    public function down()
    {
        $this->forge->dropTable('user_trusted_device', true);
    }
}
