<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishAffiliationForeignKey extends Migration
{
    public function up()
    {
        $this->db->query('
            ALTER TABLE `parish_affiliation`
            ADD CONSTRAINT `parish_affiliation_parish_id_foreign`
            FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ');
    }

    public function down()
    {
        $this->db->query('
            ALTER TABLE `parish_affiliation`
            DROP FOREIGN KEY `parish_affiliation_parish_id_foreign`
        ');
    }
}
