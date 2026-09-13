<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfilePersonId extends Migration
{
    public function up()
    {
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
            $this->forge->addKey('person_id');
            $this->forge->processIndexes('profile');
        }

        if ($this->db->tableExists('parish_affiliation')) {
            $this->db->query(<<<'SQL'
                UPDATE profile
                SET person_id = (
                    SELECT person_id
                    FROM parish_affiliation
                    WHERE linked_user_id = profile.user_id
                      AND person_id IS NOT NULL
                    LIMIT 1
                )
                WHERE person_id IS NULL
                SQL
            );
        }

        if ($this->db->tableExists('user_person')) {
            $this->db->query(<<<'SQL'
                UPDATE profile
                SET person_id = (
                    SELECT person_id
                    FROM user_person
                    WHERE user_id = profile.user_id
                      AND person_id IS NOT NULL
                    LIMIT 1
                )
                WHERE person_id IS NULL
                SQL
            );
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('person_id', 'profile')) {
            $this->forge->dropColumn('profile', 'person_id');
        }
    }
}
