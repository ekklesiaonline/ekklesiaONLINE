<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillGuestVisitsToPersonVisits extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('guest_visit') || ! $this->db->tableExists('person_visit')) {
            return;
        }

        $sql = "
            INSERT INTO {$this->db->getPrefix()}person_visit (
                person_id, parish_id, visit_date, visit_type, service_id,
                attended_event, gift_given, notes, created_at, updated_at
            )
            SELECT g.person_id, gv.parish_id, gv.visit_date, gv.visit_type, gv.service_id,
                   gv.attended_event, gv.gift_given, gv.notes, gv.created_at, gv.updated_at
            FROM {$this->db->getPrefix()}guest_visit gv
            JOIN {$this->db->getPrefix()}guest g ON g.id = gv.guest_id
            LEFT JOIN {$this->db->getPrefix()}person_visit pv
                ON pv.person_id = g.person_id
                AND pv.visit_date = gv.visit_date
                AND pv.parish_id = gv.parish_id
            WHERE g.person_id IS NOT NULL
              AND pv.id IS NULL
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        // Not reversible.
    }
}
