<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFulfillmentStatusToAnnualPledge extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('annual_pledge')) {
            return;
        }

        if ($this->db->fieldExists('fulfillment_status', 'annual_pledge')) {
            return;
        }

        $this->forge->addColumn('annual_pledge', [
            'fulfillment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pledged', 'partial', 'fulfilled', 'cancelled'],
                'default'    => 'pledged',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('annual_pledge')) {
            return;
        }

        if (! $this->db->fieldExists('fulfillment_status', 'annual_pledge')) {
            return;
        }

        $this->forge->dropColumn('annual_pledge', 'fulfillment_status');
    }
}
