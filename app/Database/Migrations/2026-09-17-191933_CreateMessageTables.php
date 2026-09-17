<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the bulk-messaging tables.
 *
 * message_template  — parish-scoped reusable message bodies ({token}
 *                     substitution at send time).
 * message_campaign  — one bulk send: channel, audience descriptor, status,
 *                     per-campaign tallies.
 * message_recipient — per-person delivery record; SMS rows link back to
 *                     sms_queue via queue_id.
 */
class CreateMessageTables extends Migration
{
    public function up()
    {
        // message_template
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => false,
            ],
            'channel' => [
                'type' => 'ENUM',
                'constraint' => ['email', 'sms', 'notification', 'any'],
                'default' => 'any',
                'null' => false,
            ],
            'subject' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'body' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
            ],
            'created_by' => [
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parish_id');
        $this->forge->createTable('message_template', true);

        // message_campaign
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'channel' => [
                'type' => 'ENUM',
                'constraint' => ['email', 'sms', 'notification'],
                'null' => false,
            ],
            'subject' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'body' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'audience' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON descriptor: statuses, group_ids, team_ids, include_guests',
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['draft', 'sending', 'sent', 'cancelled'],
                'default' => 'draft',
                'null' => false,
            ],
            'total_recipients' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
                'null' => false,
            ],
            'sent_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
                'null' => false,
            ],
            'skipped_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
                'null' => false,
            ],
            'failed_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
                'null' => false,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('parish_id');
        $this->forge->addKey('status');
        $this->forge->createTable('message_campaign', true);

        // message_recipient
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'guest_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 160,
                'null' => true,
            ],
            'first_name' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'last_name' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'address' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Delivery address used (email or phone), like sms_queue.to_number',
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'queued', 'sent', 'skipped', 'failed'],
                'default' => 'pending',
                'null' => false,
            ],
            'skip_reason' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'queue_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('campaign_id');
        $this->forge->addKey('person_id');
        $this->forge->addKey('status');
        $this->forge->createTable('message_recipient', true);
    }

    public function down()
    {
        $this->forge->dropTable('message_recipient', true);
        $this->forge->dropTable('message_campaign', true);
        $this->forge->dropTable('message_template', true);
    }
}
