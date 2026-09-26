<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixFollowupActionChannelEnum extends Migration
{
    public function up()
    {
        $db = $this->db;

        // MySQL-specific: widen the enum to include the manual human-task channel.
        if ($db->DBDriver === 'MySQLi') {
            $db->query("ALTER TABLE `followup_action` MODIFY COLUMN `channel` ENUM('text','email','call','mail','gift','manual') COLLATE utf8mb4_general_ci NOT NULL");
        }
    }

    public function down()
    {
        $db = $this->db;

        if ($db->DBDriver === 'MySQLi') {
            // SQLite has no enum enforcement, so this is MySQL-only.
            $db->query("ALTER TABLE `followup_action` MODIFY COLUMN `channel` ENUM('text','email','call','mail','gift') COLLATE utf8mb4_general_ci NOT NULL");
        }
    }
}
