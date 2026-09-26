<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drop the unused `is_automated` flag from `followup_action`.
 *
 * Channel drives dispatch behavior now: `call`/`manual` materialize human
 * tasks; `email`/`text`/`mail`/`gift` dispatch automatically via the cron.
 * The flag was never consulted by the scheduler or dispatcher, so keeping it
 * only confused the UI contract.
 */
class DropIsAutomatedFromFollowupActionMigration extends Migration
{
    public function up()
    {
        $db = db_connect();
        if (! $db->fieldExists('is_automated', 'followup_action')) {
            return;
        }

        $this->forge->dropColumn('followup_action', 'is_automated');
    }

    public function down()
    {
        $db = db_connect();
        if ($db->fieldExists('is_automated', 'followup_action')) {
            return;
        }

        $this->forge->addColumn('followup_action', [
            'is_automated' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'after'      => 'template_id',
            ],
        ]);
    }
}
