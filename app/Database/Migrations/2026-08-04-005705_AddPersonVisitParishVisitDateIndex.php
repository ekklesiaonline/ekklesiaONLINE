<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonVisitParishVisitDateIndex extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('person_visit')) {
            return;
        }

        $existing = $this->db->getIndexData('person_visit');
        if (isset($existing['idx_person_visit_parish_visit_date'])) {
            return;
        }

        $this->forge->addKey(['parish_id', 'visit_date'], false, false, 'idx_person_visit_parish_visit_date', 'person_visit');
    }

    public function down()
    {
        if (! $this->db->tableExists('person_visit')) {
            return;
        }

        if (isset($this->db->getIndexData('person_visit')['idx_person_visit_parish_visit_date'])) {
            $this->forge->dropKey('person_visit', 'idx_person_visit_parish_visit_date');
        }
    }
}
