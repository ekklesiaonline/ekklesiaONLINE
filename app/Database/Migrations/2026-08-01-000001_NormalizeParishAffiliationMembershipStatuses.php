<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeParishAffiliationMembershipStatuses extends Migration
{
    public function up(): void
    {
        $db = db_connect();
        $table = $db->getPrefix() !== '' ? $db->getPrefix() . 'parish_affiliation' : 'parish_affiliation';

        try {
            $columns = array_map('strtolower', $db->getFieldNames($table));
        } catch (\Throwable $e) {
            return;
        }

        if (!in_array('membership_status', $columns, true)) {
            return;
        }

        $db->query("ALTER TABLE `{$table}` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased','visitor','regular_attender','baptized_member','communicant','leader') NOT NULL DEFAULT 'visitor'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'visitor' WHERE `membership_status` = 'guest'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'regular_attender' WHERE `membership_status` = 'attendee'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'member' WHERE `membership_status` IN ('baptized_member', 'communicant', 'leader', 'member')");
        $db->query("ALTER TABLE `{$table}` MODIFY COLUMN `membership_status` ENUM('visitor','regular_attender','member','inactive','transferred','deceased') NOT NULL DEFAULT 'visitor'");
    }

    public function down(): void
    {
        $db = db_connect();
        $table = $db->getPrefix() !== '' ? $db->getPrefix() . 'parish_affiliation' : 'parish_affiliation';

        try {
            $columns = array_map('strtolower', $db->getFieldNames($table));
        } catch (\Throwable $e) {
            return;
        }

        if (!in_array('membership_status', $columns, true)) {
            return;
        }

        $db->query("ALTER TABLE `{$table}` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased','visitor','regular_attender','baptized_member','communicant','leader') NOT NULL DEFAULT 'guest'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'guest' WHERE `membership_status` = 'visitor'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'attendee' WHERE `membership_status` = 'regular_attender'");
        $db->query("UPDATE `{$table}` SET `membership_status` = 'member' WHERE `membership_status` IN ('baptized_member', 'communicant', 'leader', 'member')");
        $db->query("ALTER TABLE `{$table}` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased') NOT NULL DEFAULT 'guest'");
    }
}
