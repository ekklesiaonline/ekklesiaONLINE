<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvitationMetadataToAuthIdentities extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('invited_at', 'auth_identities')) {
            $this->forge->addColumn('auth_identities', [
                'invited_at' => ['type' => 'DATETIME', 'null' => true],
                'invitation_sent_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }

        if ($this->db->tableExists('system_settings') && $this->db->table('system_settings')->where('setting_key', 'province_dashboard.reporting_overdue_days')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('system_settings')->insert([
                'setting_key' => 'province_dashboard.reporting_overdue_days',
                'setting_value' => '30',
                'data_type' => 'integer',
                'category' => 'province_dashboard',
                'description' => 'Days without a service before a diocese is marked reporting overdue.',
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('invited_at', 'auth_identities')) {
            $this->forge->dropColumn('auth_identities', ['invited_at', 'invitation_sent_at']);
        }
    }
}
