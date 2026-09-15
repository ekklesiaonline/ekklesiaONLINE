<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillEventOrganizerColumns extends Migration
{
    public function up()
    {
        $this->db->query(<<<'SQL'
            UPDATE `event`
            SET
                organizer_type       = scope_level,
                organizer_parish_id    = NULL,
                organizer_province_id  = CASE WHEN scope_level = 'province' THEN scope_province_id ELSE NULL END,
                organizer_diocese_id   = CASE WHEN scope_level = 'diocese'  THEN scope_diocese_id  ELSE NULL END,
                organizer_parish_id    = CASE WHEN scope_level = 'parish'   THEN parish_id          ELSE NULL END,
                host_parish_id         = parish_id
            WHERE scope_level IN ('parish','diocese','province')
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<'SQL'
            UPDATE `event`
            SET
                organizer_type       = 'parish',
                organizer_parish_id  = NULL,
                organizer_province_id = NULL,
                organizer_diocese_id  = NULL,
                organizer_parish_id   = NULL,
                host_parish_id        = NULL
        SQL);
    }
}
