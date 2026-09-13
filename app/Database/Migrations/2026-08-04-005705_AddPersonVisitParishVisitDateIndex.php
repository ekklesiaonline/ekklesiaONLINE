<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonVisitParishVisitDateIndex extends Migration
{
    public function up()
    {
        $this->forge->addKey(['parish_id', 'visit_date'], false, false, 'idx_person_visit_parish_visit_date', 'person_visit');
    }

    public function down()
    {
        $this->forge->dropKey('person_visit', 'idx_person_visit_parish_visit_date');
    }
}
