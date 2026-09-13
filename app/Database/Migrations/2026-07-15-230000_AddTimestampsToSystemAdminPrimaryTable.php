<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTimestampsToSystemAdminPrimaryTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('system_admin_primary')) {
            return;
        }

        $fields = $this->db->getFieldNames('system_admin_primary');

        $columns = [];
        if (!in_array('created_at', $fields, true)) {
            $columns['created_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }
        if (!in_array('updated_at', $fields, true)) {
            $columns['updated_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('system_admin_primary', $columns);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('system_admin_primary')) {
            return;
        }

        $fields = $this->db->getFieldNames('system_admin_primary');

        if (in_array('created_at', $fields, true)) {
            $this->forge->dropColumn('system_admin_primary', 'created_at');
        }
        if (in_array('updated_at', $fields, true)) {
            $this->forge->dropColumn('system_admin_primary', 'updated_at');
        }
    }
}
