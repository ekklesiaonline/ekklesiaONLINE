<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillFamilyHeadsIntoFamilyMembers extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('family_members') || !$this->db->tableExists('families')) {
            return;
        }

        $this->db->query(
            "INSERT INTO family_members (family_id, user_id, relationship, created_at)
             SELECT f.id, f.head_of_household_id, 'Head', NOW()
             FROM families f
             LEFT JOIN family_members fm
               ON fm.family_id = f.id
              AND fm.user_id = f.head_of_household_id
             WHERE f.head_of_household_id IS NOT NULL
               AND fm.id IS NULL"
        );
    }

    public function down()
    {
        if (!$this->db->tableExists('family_members') || !$this->db->tableExists('families')) {
            return;
        }

        $this->db->query(
            "DELETE fm FROM family_members fm
             INNER JOIN families f ON f.id = fm.family_id
             WHERE fm.user_id = f.head_of_household_id
               AND fm.relationship = 'Head'"
        );
    }
}
