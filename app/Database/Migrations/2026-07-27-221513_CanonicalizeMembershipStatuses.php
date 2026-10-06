<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CanonicalizeMembershipStatuses extends Migration
{
    public function up()
    {
        // Profile: add temporary superset, backfill, then canonical enum
        $this->db->query("ALTER TABLE `profile` MODIFY COLUMN `membership_status` ENUM('active','inactive','visitor','pending','regular_attender','member','non_active') NOT NULL DEFAULT 'regular_attender'");
        $this->db->query("UPDATE `profile` SET `membership_status` = 'member' WHERE `membership_status` = 'active'");
        $this->db->query("UPDATE `profile` SET `membership_status` = 'non_active' WHERE `membership_status` = 'inactive'");
        $this->db->query("ALTER TABLE `profile` MODIFY COLUMN `membership_status` ENUM('pending','visitor','regular_attender','member','non_active') NOT NULL DEFAULT 'pending'");

        // Parish affiliation: add temporary superset, backfill, then canonical enum
        $this->db->query("ALTER TABLE `parish_affiliation` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased','visitor','regular_attender','baptized_member','communicant','leader') NOT NULL DEFAULT 'guest'");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'visitor' WHERE `membership_status` = 'guest'");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'regular_attender' WHERE `membership_status` = 'attendee'");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'communicant' WHERE `membership_status` = 'member'");
        $this->db->query("ALTER TABLE `parish_affiliation` MODIFY COLUMN `membership_status` ENUM('visitor','regular_attender','baptized_member','communicant','leader','inactive','transferred','deceased') NOT NULL DEFAULT 'visitor'");

        // Rebuild parish_people snapshot so it reflects the new canonical values
        $service = new \App\Services\ParishPeopleRegistryService();
        foreach ($this->db->table('parish')->select('id')->get()->getResultArray() as $row) {
            $service->syncParish((int) ($row['id'] ?? 0));
        }
    }

    public function down()
    {
        // Parish affiliation: restore previous enum
        $this->db->query("ALTER TABLE `parish_affiliation` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased','visitor','regular_attender','baptized_member','communicant','leader') NOT NULL DEFAULT 'guest'");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'member' WHERE `membership_status` IN ('baptized_member','communicant','leader')");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'guest' WHERE `membership_status` = 'visitor'");
        $this->db->query("UPDATE `parish_affiliation` SET `membership_status` = 'attendee' WHERE `membership_status` = 'regular_attender'");
        $this->db->query("ALTER TABLE `parish_affiliation` MODIFY COLUMN `membership_status` ENUM('guest','attendee','member','inactive','transferred','deceased') NOT NULL DEFAULT 'guest'");

        // Profile: restore previous enum
        $this->db->query("ALTER TABLE `profile` MODIFY COLUMN `membership_status` ENUM('active','inactive','visitor','pending','regular_attender','member','non_active') NOT NULL DEFAULT 'regular_attender'");
        $this->db->query("UPDATE `profile` SET `membership_status` = 'active' WHERE `membership_status` = 'member'");
        $this->db->query("UPDATE `profile` SET `membership_status` = 'inactive' WHERE `membership_status` = 'non_active'");
        $this->db->query("ALTER TABLE `profile` MODIFY COLUMN `membership_status` ENUM('active','inactive','visitor','pending','regular_attender') NOT NULL DEFAULT 'regular_attender'");

        // Rebuild snapshot after rollback
        $service = new \App\Services\ParishPeopleRegistryService();
        foreach ($this->db->table('parish')->select('id')->get()->getResultArray() as $row) {
            $service->syncParish((int) ($row['id'] ?? 0));
        }
    }
}
