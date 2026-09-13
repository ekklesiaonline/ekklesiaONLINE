<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureProfilePersonIdColumn extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('profile')) {
            return;
        }

        // Add the person_id column if it doesn't exist.
        if (! $this->db->fieldExists('person_id', 'profile')) {
            $this->forge->addColumn('profile', [
                'person_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'user_id',
                ],
            ]);

            try {
                $this->forge->addKey('person_id');
                $this->forge->processIndexes('profile');
            } catch (\Throwable $e) {
                // Index creation is best-effort; the column is what matters.
            }
        }

        // Backfill from user_person first.
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
        // No-op: removing the column would break the canonical person system.
    }
}
