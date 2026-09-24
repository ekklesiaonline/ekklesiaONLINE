<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repair leadership_position.person_id.
 *
 * AlignLeadershipPositionToPersonId (2026-08-01) backfilled person_id with
 * profile.id instead of profile.person_id, and MemberPortal
 * LeadershipPositions::store never wrote the column at all. Both paths can
 * leave person_id NULL or pointing at a profile id rather than the
 * canonical person.id.
 *
 * This migration re-resolves every position's person_id from
 * user_person (primary link first), then profile.person_id as fallback,
 * and rewrites the column when the resolved value differs.
 */
class BackfillLeadershipPositionPersonIdMigration extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('leadership_position')
            || ! $this->db->fieldExists('person_id', 'leadership_position')) {
            return;
        }

        $positions = $this->db->table('leadership_position')
            ->select('id, user_id, person_id')
            ->get()
            ->getResultArray();

        if ($positions === []) {
            return;
        }

        // user_id => person_id, from user_person (primary link first).
        $personByUser = [];
        if ($this->db->tableExists('user_person')) {
            $rows = $this->db->table('user_person')
                ->select('user_id, person_id')
                ->orderBy('is_primary', 'DESC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                $pid = (int) ($row['person_id'] ?? 0);
                if ($uid > 0 && $pid > 0 && ! isset($personByUser[$uid])) {
                    $personByUser[$uid] = $pid;
                }
            }
        }

        // Fallback: profile.person_id for the user.
        if ($this->db->tableExists('profile') && $this->db->fieldExists('person_id', 'profile')) {
            $rows = $this->db->table('profile')
                ->select('user_id, person_id')
                ->where('person_id IS NOT NULL', null, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                $pid = (int) ($row['person_id'] ?? 0);
                if ($uid > 0 && $pid > 0 && ! isset($personByUser[$uid])) {
                    $personByUser[$uid] = $pid;
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($positions as $position) {
            $userId  = (int) ($position['user_id'] ?? 0);
            $correct = $personByUser[$userId] ?? null;
            if ($correct === null) {
                continue; // No authoritative person link — leave as-is.
            }

            if ((int) ($position['person_id'] ?? 0) !== $correct) {
                $this->db->table('leadership_position')
                    ->where('id', (int) $position['id'])
                    ->update(['person_id' => $correct, 'updated_at' => $now]);
            }
        }
    }

    public function down()
    {
        // Data repair only — nothing to roll back.
    }
}
