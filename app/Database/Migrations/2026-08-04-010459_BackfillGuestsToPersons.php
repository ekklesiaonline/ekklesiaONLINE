<?php

namespace App\Database\Migrations;

use App\Services\GuestIntakeService;
use CodeIgniter\Database\Migration;

class BackfillGuestsToPersons extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('guest')) {
            return;
        }

        $guests = $this->db->table('guest')
            ->where('person_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($guests as $guest) {
            $parishId = (int) ($guest['parish_id'] ?? 0);
            if ($parishId <= 0) {
                continue;
            }

            try {
                $intake = GuestIntakeService::record($guest, $parishId);
                if ($intake['person_id'] > 0) {
                    $this->db->table('guest')
                        ->where('id', (int) $guest['id'])
                        ->update(['person_id' => $intake['person_id']]);
                }
            } catch (\Throwable $e) {
                log_message('warning', 'BackfillGuestsToPersons skipped guest_id=' . $guest['id'] . ': ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // Not reversible.
    }
}
