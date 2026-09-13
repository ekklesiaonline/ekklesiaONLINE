<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLeaderFieldsToParishPeopleAndSyncAll extends Migration
{
    public function up()
    {
        $tables = ['parish_people', 'church_people'];

        foreach ($tables as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            $fields = [];
            if (!$this->db->fieldExists('is_leader', $table)) {
                $fields['is_leader'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_good_standing',
                ];
            }
            if (!$this->db->fieldExists('leader_titles', $table)) {
                $fields['leader_titles'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'is_leader',
                ];
            }

            if ($fields !== []) {
                $this->forge->addColumn($table, $fields);
            }
        }

        $service = new \App\Services\ParishPeopleRegistryService();
        $service->syncAll();
    }

    public function down()
    {
        $tables = ['parish_people', 'church_people'];

        foreach ($tables as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            if ($this->db->fieldExists('leader_titles', $table)) {
                $this->forge->dropColumn($table, 'leader_titles');
            }
            if ($this->db->fieldExists('is_leader', $table)) {
                $this->forge->dropColumn($table, 'is_leader');
            }
        }

        $service = new \App\Services\ParishPeopleRegistryService();
        $service->syncAll();
    }
}
