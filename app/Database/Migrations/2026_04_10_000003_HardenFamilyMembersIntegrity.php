<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenFamilyMembersIntegrity extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('family_members')) {
            return;
        }

        // Remove duplicate (family_id, user_id) rows while preserving the earliest row.
        $this->db->query(
            'DELETE fm1 FROM family_members fm1
             INNER JOIN family_members fm2
                 ON fm1.family_id = fm2.family_id
                AND fm1.user_id = fm2.user_id
                AND fm1.id > fm2.id'
        );

        $this->db->query('ALTER TABLE family_members ADD UNIQUE KEY uniq_family_members_family_user (family_id, user_id)');
        $this->db->query('ALTER TABLE family_members ADD INDEX idx_family_members_relationship (relationship)');
    }

    public function down()
    {
        if (!$this->db->tableExists('family_members')) {
            return;
        }

        $this->db->query('ALTER TABLE family_members DROP INDEX uniq_family_members_family_user');
        $this->db->query('ALTER TABLE family_members DROP INDEX idx_family_members_relationship');
    }
}
