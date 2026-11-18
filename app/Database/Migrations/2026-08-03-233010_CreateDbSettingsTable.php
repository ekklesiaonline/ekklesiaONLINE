<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDbSettingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('settings')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'class' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 63,
                'null'       => false,
                'default'    => 'string',
            ],
            'context' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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
        $this->forge->addKey(['class', 'key', 'context'], false, true);

        $this->forge->createTable('settings', true);
    }

    public function down()
    {
        // `settings` is created earlier by the vendor codeigniter4/settings
        // migrations on installs that run them; this migration only fills
        // the gap when it is missing. Dropping it here would break the
        // vendor migration's own rollback — leave its lifecycle to the
        // vendor migration.
    }
}
