<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillPrimaryAdminGroupMemberships extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $map = [
            'system_admin_primary'  => 'systemadmin',
            'province_admin_primary' => 'provinceadmin',
            'diocese_admin_primary'  => 'dioceseadmin',
            'parish_admin_primary'   => 'parishadmin',
        ];

        foreach ($map as $table => $group) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $rows = $this->db->table($table)->select('user_id')->get()->getResultArray();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $exists = (int) $this->db->table('auth_groups_users')
                    ->where('user_id', $userId)
                    ->where('group', $group)
                    ->countAllResults() > 0;

                if (! $exists) {
                    $this->db->table('auth_groups_users')->insert([
                        'user_id'    => $userId,
                        'group'      => $group,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        // Intentionally no-op: removing group memberships could undo legitimate assignments.
    }
}
