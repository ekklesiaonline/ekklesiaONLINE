<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AttachPrimaryAdminsToHomeParish extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $affiliationModel = model('MemberParishAffiliationModel');

        $primaryTables = [
            'system_admin_primary'   => false, // resolve parish from profile below
            'province_admin_primary' => false,
            'diocese_admin_primary'  => false,
            'parish_admin_primary'   => true,
        ];

        foreach ($primaryTables as $table => $hasParishColumn) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $rows = $this->db->table($table)->get()->getResultArray();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $parishId = $hasParishColumn
                    ? (int) ($row['parish_id'] ?? 0)
                    : (int) ($this->db->table('profile')->select('parish_id')->where('user_id', $userId)->get()->getRowArray()['parish_id'] ?? 0);

                if ($parishId <= 0) {
                    continue;
                }

                $already = (int) $this->db->table('parish_affiliation')
                    ->where('linked_user_id', $userId)
                    ->where('parish_id', $parishId)
                    ->countAllResults() > 0;

                if ($already) {
                    continue;
                }

                try {
                    $affiliationModel->attachToParish($userId, $parishId, true, 'member');
                } catch (\Throwable $e) {
                    log_message('warning', 'AttachPrimaryAdminsToHomeParish: failed to attach user {user} to parish {parish}: {error}', [
                        'user'   => $userId,
                        'parish' => $parishId,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        // Intentionally no-op: parish affiliations created here may have been modified by users.
    }
}
