<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlignFamilyMemberWithCanonical extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('family_member')) {
            return;
        }

        if (! $this->db->fieldExists('user_id', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` ADD COLUMN `user_id` INT UNSIGNED NOT NULL AFTER `family_id`');
        }

        if (! $this->db->fieldExists('relationship', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` ADD COLUMN `relationship` VARCHAR(100) NULL AFTER `user_id`');
        }

        if ($this->db->fieldExists('person_id', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` DROP FOREIGN KEY `fk_fm_person`');
            $this->db->query('ALTER TABLE `family_member` DROP COLUMN `person_id`');
        }

        if ($this->db->fieldExists('role', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` DROP COLUMN `role`');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('family_member')) {
            return;
        }

        if ($this->db->fieldExists('user_id', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` DROP COLUMN `user_id`');
        }

        if ($this->db->fieldExists('relationship', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` DROP COLUMN `relationship`');
        }

        if (! $this->db->fieldExists('person_id', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` ADD COLUMN `person_id` INT UNSIGNED NOT NULL AFTER `family_id`');
        }

        if (! $this->db->fieldExists('role', 'family_member')) {
            $this->db->query('ALTER TABLE `family_member` ADD COLUMN `role` VARCHAR(50) NULL AFTER `person_id`');
        }
    }
}
