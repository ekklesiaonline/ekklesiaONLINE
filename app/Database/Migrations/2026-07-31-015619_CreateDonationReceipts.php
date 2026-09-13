<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDonationReceipts extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('donation_receipts')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'donation_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'receipt_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'issued_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'issued_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
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
        $this->forge->addKey('donation_id');
        $this->forge->addKey(['parish_id', 'receipt_number'], false, true, 'uq_donation_receipts_parish_number');
        $this->forge->createTable('donation_receipts');
    }

    public function down()
    {
        if ($this->db->tableExists('donation_receipts')) {
            $this->forge->dropTable('donation_receipts');
        }
    }
}
