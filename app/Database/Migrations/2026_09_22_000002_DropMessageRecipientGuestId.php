<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drop message_recipient.guest_id — always NULL since the guest→person
 * canonical migration; recipients are keyed by person_id.
 */
class DropMessageRecipientGuestId extends Migration
{
    public function up()
    {
        $db = db_connect();

        try {
            if ($db->fieldExists('guest_id', 'message_recipient')) {
                $this->forge->dropColumn('message_recipient', 'guest_id');
            }
        } catch (\Throwable $e) {
            // ignore if table/column missing
        }
    }

    public function down()
    {
        $db = db_connect();

        try {
            if (! $db->fieldExists('guest_id', 'message_recipient')) {
                $this->forge->addColumn('message_recipient', [
                    'guest_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
