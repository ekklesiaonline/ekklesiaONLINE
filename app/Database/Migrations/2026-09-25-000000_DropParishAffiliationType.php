<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * DropParishAffiliationType — removes parish_affiliation.affiliation_type.
 *
 * The column was a redundant bucket (guest/attendee/member) derived from
 * membership_status. "Guest" and "visitor" were two names for the same
 * state — the lifecycle is now just
 * visitor -> regular_attender -> member (plus inactive/transferred/
 * deceased exits). Every reader that filtered affiliation_type='guest'
 * now reads membership_status='visitor' directly, so the column carries
 * no information of its own.
 *
 * down() restores the enum and repopulates it from membership_status.
 */
class DropParishAffiliationType extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')
            || ! $this->db->fieldExists('affiliation_type', 'parish_affiliation')) {
            return;
        }

        $this->forge->dropColumn('parish_affiliation', 'affiliation_type');
    }

    public function down()
    {
        if (! $this->db->tableExists('parish_affiliation')
            || $this->db->fieldExists('affiliation_type', 'parish_affiliation')) {
            return;
        }

        $this->forge->addColumn('parish_affiliation', [
            'affiliation_type' => [
                'type'       => 'ENUM',
                'constraint' => ['guest', 'attendee', 'member'],
                'default'    => 'guest',
                'null'       => false,
                'after'      => 'person_id',
            ],
        ]);

        $this->db->query(
            "UPDATE parish_affiliation SET affiliation_type = CASE membership_status
                WHEN 'visitor'          THEN 'guest'
                WHEN 'regular_attender' THEN 'attendee'
                ELSE 'member'
             END"
        );
    }
}
