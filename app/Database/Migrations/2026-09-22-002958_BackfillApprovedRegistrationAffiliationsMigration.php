<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BackfillApprovedRegistrationAffiliationsMigration
 *
 * RegistrationApprovalService::performApprove() used to mark the request
 * approved and set profile.parish_id, but never created the canonical
 * parish_affiliation row. The people directory is affiliation-driven, so
 * those approved members stayed invisible to parish people lists.
 *
 * This backfill inserts a primary 'regular_attender' affiliation for every
 * approved request that has a parish and no live affiliation for that
 * user+parish. Approval affiliates the person but does not confer
 * canonical membership (is_member=0); a parish admin promotes to 'member'
 * once sacramental/standing requirements are verified.
 */
class BackfillApprovedRegistrationAffiliationsMigration extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation') || ! $this->db->tableExists('user_registration_requests')) {
            return;
        }

        $requests = $this->db->table('user_registration_requests urr')
            ->select('urr.user_id, urr.parish_id, p.person_id')
            ->join('profile p', 'p.user_id = urr.user_id', 'left')
            ->where('urr.status', 'approved')
            ->where('urr.parish_id IS NOT NULL', null, false)
            ->where('urr.parish_id >', 0)
            ->get()
            ->getResultArray();

        $now = date('Y-m-d H:i:s');

        foreach ($requests as $request) {
            $userId   = (int) $request['user_id'];
            $parishId = (int) $request['parish_id'];
            $personId = (int) ($request['person_id'] ?? 0);

            if ($userId <= 0 || $parishId <= 0) {
                continue;
            }

            $existing = $this->db->table('parish_affiliation')
                ->where('linked_user_id', $userId)
                ->where('parish_id', $parishId)
                ->where('deleted_at IS NULL', null, false)
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                // Affiliation exists: make it primary if it isn't, but never
                // rewrite its membership_status — that is a parish decision.
                if ((int) ($existing['is_primary'] ?? 0) !== 1) {
                    $this->db->table('parish_affiliation')
                        ->where('linked_user_id', $userId)
                        ->where('is_primary', 1)
                        ->update(['is_primary' => 0, 'updated_at' => $now]);

                    $this->db->table('parish_affiliation')
                        ->where('id', (int) $existing['id'])
                        ->update(['is_primary' => 1, 'updated_at' => $now]);
                }
                continue;
            }

            // Clear any stale primary flag before marking this one primary.
            $this->db->table('parish_affiliation')
                ->where('linked_user_id', $userId)
                ->where('is_primary', 1)
                ->update(['is_primary' => 0, 'updated_at' => $now]);

            $this->db->table('parish_affiliation')->insert([
                'parish_id'         => $parishId,
                'person_id'         => $personId,
                'linked_user_id'    => $userId,
                'affiliation_type'  => 'attendee',
                'membership_status' => 'regular_attender',
                'is_member'         => 0,
                'is_primary'        => 1,
                'source'            => 'system',
                'start_date'        => date('Y-m-d'),
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }

    public function down()
    {
        // Data backfill: not reversible without destroying legitimate rows.
    }
}
