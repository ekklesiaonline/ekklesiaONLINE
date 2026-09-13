<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ProvisionProfilesForGroupAdmins extends Migration
{
    public function up()
    {
        $this->provisionProfilesForGroupAdmins();
    }

    public function down()
    {
        // No-op: do not remove auto-provisioned admin profiles.
    }

    private function provisionProfilesForGroupAdmins(): void
    {
        if (!$this->db->tableExists('profile') || !$this->db->tableExists('users') || !$this->db->tableExists('auth_groups_users')) {
            return;
        }

        $requestTable = $this->db->tableExists('user_registration_request') ? $this->db->getPrefix() . 'user_registration_request' : null;

        $sql = 'INSERT INTO ' . $this->db->getPrefix() . 'profile '
            . '(user_id, province_id, diocese_id, parish_id, first_name, last_name, membership_status, created_at, updated_at) '
            . 'SELECT '
            . '  u.id, '
            . '  urr.province_id, '
            . '  urr.diocese_id, '
            . '  urr.parish_id, '
            . '  MAX(u.username), '
            . '  "Admin", '
            . '  "visitor", '
            . '  NOW(), '
            . '  NOW() '
            . 'FROM ' . $this->db->getPrefix() . 'users u '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'auth_groups_users agu ON agu.user_id = u.id '
            . '  AND agu.group IN ("systemadmin", "provinceadmin", "dioceseadmin", "parishadmin") '
            . 'LEFT JOIN ' . $this->db->getPrefix() . 'profile p ON p.user_id = u.id ';

        if ($requestTable !== null) {
            $sql .= 'LEFT JOIN ( '
                . 'SELECT r1.user_id, r1.province_id, r1.diocese_id, r1.parish_id '
                . 'FROM ' . $requestTable . ' r1 '
                . 'WHERE r1.id = ( '
                . '  SELECT r2.id FROM ' . $requestTable . ' r2 '
                . '  WHERE r2.user_id = r1.user_id '
                . '  ORDER BY r2.id DESC LIMIT 1 '
                . ') '
                . ') urr ON urr.user_id = u.id ';
        } else {
            $sql .= 'LEFT JOIN (SELECT NULL AS user_id, NULL AS province_id, NULL AS diocese_id, NULL AS parish_id) urr ON 1=0 ';
        }

        $sql .= 'WHERE p.id IS NULL '
            . 'GROUP BY u.id, urr.province_id, urr.diocese_id, urr.parish_id';

        $this->db->query($sql);
    }
}
