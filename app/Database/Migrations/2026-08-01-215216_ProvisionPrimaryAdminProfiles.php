<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ProvisionPrimaryAdminProfiles extends Migration
{
    public function up()
    {
        $this->provisionPrimaryAdminProfiles();
    }

    public function down()
    {
        // No-op: do not remove auto-provisioned admin profiles.
    }

    private function provisionPrimaryAdminProfiles(): void
    {
        if (!$this->db->tableExists('profile') || !$this->db->tableExists('users')) {
            return;
        }

        $this->provisionSystemAdmins();
        $this->provisionProvinceAdmins();
        $this->provisionDioceseAdmins();
        $this->provisionParishAdmins();
    }

    private function provisionSystemAdmins(): void
    {
        if (!$this->db->tableExists('system_admin_primary')) {
            return;
        }

        $sql = 'INSERT INTO ' . $this->db->getPrefix() . 'profile '
            . '(user_id, first_name, last_name, created_at, updated_at) '
            . 'SELECT u.id, u.username, "Admin", NOW(), NOW() '
            . 'FROM ' . $this->db->getPrefix() . 'users u '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'system_admin_primary sap ON sap.user_id = u.id '
            . 'LEFT JOIN ' . $this->db->getPrefix() . 'profile p ON p.user_id = u.id '
            . 'WHERE p.id IS NULL';

        $this->db->query($sql);
    }

    private function provisionProvinceAdmins(): void
    {
        if (!$this->db->tableExists('province_admin_primary')) {
            return;
        }

        $sql = 'INSERT INTO ' . $this->db->getPrefix() . 'profile '
            . '(user_id, province_id, first_name, last_name, created_at, updated_at) '
            . 'SELECT u.id, pap.province_id, u.username, "Admin", NOW(), NOW() '
            . 'FROM ' . $this->db->getPrefix() . 'users u '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'province_admin_primary pap ON pap.user_id = u.id '
            . 'LEFT JOIN ' . $this->db->getPrefix() . 'profile p ON p.user_id = u.id '
            . 'WHERE p.id IS NULL';

        $this->db->query($sql);
    }

    private function provisionDioceseAdmins(): void
    {
        if (!$this->db->tableExists('diocese_admin_primary') || !$this->db->tableExists('diocese')) {
            return;
        }

        $sql = 'INSERT INTO ' . $this->db->getPrefix() . 'profile '
            . '(user_id, province_id, diocese_id, first_name, last_name, created_at, updated_at) '
            . 'SELECT u.id, d.province_id, dap.diocese_id, u.username, "Admin", NOW(), NOW() '
            . 'FROM ' . $this->db->getPrefix() . 'users u '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'diocese_admin_primary dap ON dap.user_id = u.id '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'diocese d ON d.id = dap.diocese_id '
            . 'LEFT JOIN ' . $this->db->getPrefix() . 'profile p ON p.user_id = u.id '
            . 'WHERE p.id IS NULL';

        $this->db->query($sql);
    }

    private function provisionParishAdmins(): void
    {
        if (!$this->db->tableExists('parish_admin_primary') || !$this->db->tableExists('parish') || !$this->db->tableExists('diocese')) {
            return;
        }

        $sql = 'INSERT INTO ' . $this->db->getPrefix() . 'profile '
            . '(user_id, province_id, diocese_id, parish_id, first_name, last_name, created_at, updated_at) '
            . 'SELECT u.id, d.province_id, p.diocese_id, pap.parish_id, u.username, "Admin", NOW(), NOW() '
            . 'FROM ' . $this->db->getPrefix() . 'users u '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'parish_admin_primary pap ON pap.user_id = u.id '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'parish p ON p.id = pap.parish_id '
            . 'INNER JOIN ' . $this->db->getPrefix() . 'diocese d ON d.id = p.diocese_id '
            . 'LEFT JOIN ' . $this->db->getPrefix() . 'profile p2 ON p2.user_id = u.id '
            . 'WHERE p2.id IS NULL';

        $this->db->query($sql);
    }
}
