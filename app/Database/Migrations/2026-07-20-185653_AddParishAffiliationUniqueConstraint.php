<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishAffiliationUniqueConstraint extends Migration
{
    public function up()
    {
        $this->db->query('
            ALTER TABLE `parish_affiliation`
            ADD UNIQUE KEY `idx_parish_affiliation_user_parish` (`linked_user_id`, `parish_id`)
        ');
    }

    public function down()
    {
        $this->db->query('
            ALTER TABLE `parish_affiliation`
            DROP INDEX `idx_parish_affiliation_user_parish`
        ');
    }
}
