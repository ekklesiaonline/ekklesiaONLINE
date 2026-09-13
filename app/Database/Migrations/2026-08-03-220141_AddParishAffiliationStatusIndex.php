<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishAffiliationStatusIndex extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $this->dropIndexIfExists('idx_parish_affiliation_status');

        $this->db->query('
            ALTER TABLE `parish_affiliation`
            ADD KEY `idx_parish_affiliation_status` (`parish_id`, `is_member`, `membership_status`)
        ');
    }

    public function down()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $this->dropIndexIfExists('idx_parish_affiliation_status');
    }

    private function dropIndexIfExists(string $indexName): void
    {
        try {
            $this->db->query('ALTER TABLE `parish_affiliation` DROP INDEX `' . $indexName . '`');
        } catch (\Throwable $e) {
            // Index may not exist; ignore.
        }
    }
}
