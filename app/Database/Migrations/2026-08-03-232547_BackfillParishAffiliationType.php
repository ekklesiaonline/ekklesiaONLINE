<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillParishAffiliationType extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        if (! $this->db->fieldExists('affiliation_type', 'parish_affiliation')) {
            return;
        }

        if (! $this->db->fieldExists('membership_status', 'parish_affiliation')) {
            return;
        }

        $this->db->query('
            UPDATE `parish_affiliation`
            SET `affiliation_type` = CASE
                WHEN `membership_status` = "visitor"          THEN "guest"
                WHEN `membership_status` = "regular_attender" THEN "attendee"
                WHEN `membership_status` = "clergy"           THEN "clergy"
                WHEN `membership_status` = "staff"            THEN "staff"
                ELSE "member"
            END
            WHERE `affiliation_type` != CASE
                WHEN `membership_status` = "visitor"          THEN "guest"
                WHEN `membership_status` = "regular_attender" THEN "attendee"
                WHEN `membership_status` = "clergy"           THEN "clergy"
                WHEN `membership_status` = "staff"            THEN "staff"
                ELSE "member"
            END
        ');
    }

    public function down()
    {
        // Backfill is not reversible because the original values are not stored.
    }
}
