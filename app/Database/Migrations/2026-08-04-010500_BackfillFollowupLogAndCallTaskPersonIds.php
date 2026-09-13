<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillFollowupLogAndCallTaskPersonIds extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('followup_log') && $this->db->fieldExists('person_id', 'followup_log')) {
            $sql = "
                UPDATE {$this->db->getPrefix()}followup_log fl
                JOIN {$this->db->getPrefix()}guest g ON g.id = fl.guest_id
                SET fl.person_id = g.person_id
                WHERE g.person_id IS NOT NULL
                  AND fl.person_id IS NULL
            ";
            $this->db->query($sql);
        }

        if ($this->db->tableExists('call_task') && $this->db->fieldExists('person_id', 'call_task')) {
            $sql = "
                UPDATE {$this->db->getPrefix()}call_task ct
                JOIN {$this->db->getPrefix()}guest g ON g.id = ct.guest_id
                SET ct.person_id = g.person_id
                WHERE g.person_id IS NOT NULL
                  AND ct.person_id IS NULL
            ";
            $this->db->query($sql);
        }
    }

    public function down()
    {
        // Not reversible.
    }
}
