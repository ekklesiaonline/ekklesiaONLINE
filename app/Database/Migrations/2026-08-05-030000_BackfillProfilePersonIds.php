<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillProfilePersonIds extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('person_id', 'profile')) {
            return;
        }

        // Backfill from the canonical user_person mapping first.
        if ($this->db->tableExists('user_person')) {
            $this->db->query(<<<'SQL'
                UPDATE profile
                SET person_id = (
                    SELECT person_id
                    FROM user_person
                    WHERE user_person.user_id = profile.user_id
                      AND user_person.person_id IS NOT NULL
                    LIMIT 1
                )
                WHERE person_id IS NULL
                SQL
            );
        }

        // Fall back to parish_affiliation for any remaining profiles.
        if ($this->db->tableExists('parish_affiliation')) {
            $this->db->query(<<<'SQL'
                UPDATE profile
                SET person_id = (
                    SELECT person_id
                    FROM parish_affiliation
                    WHERE parish_affiliation.linked_user_id = profile.user_id
                      AND parish_affiliation.person_id IS NOT NULL
                    LIMIT 1
                )
                WHERE person_id IS NULL
                SQL
            );
        }
    }

    public function down()
    {
        // No-op: this migration only fills missing data.
    }
}
