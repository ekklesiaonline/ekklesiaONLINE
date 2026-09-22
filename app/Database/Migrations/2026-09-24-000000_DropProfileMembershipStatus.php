<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * DropProfileMembershipStatus — Phase 3 of the membership status
 * simplification (docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md).
 *
 * profile.membership_status was a stale mirror of
 * parish_affiliation.membership_status that had also absorbed onboarding
 * states ('pending', 'active') that no longer fit its enum — writes of
 * 'pending' were silently coerced to ''. Registration/onboarding state
 * lives on user_registration_requests.status; parish lifecycle lives on
 * parish_affiliation.membership_status. All readers were repointed to the
 * affiliation row (or decorated profile arrays via
 * MemberParishAffiliationModel::statusForUser()).
 */
class DropProfileMembershipStatus extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('profile')) {
            return;
        }

        if (! $this->db->fieldExists('membership_status', 'profile')) {
            return;
        }

        // Indexes that reference the column must go first. Older dumps
        // name the composite idx_church_status; newer ones idx_parish_status.
        foreach (['idx_parish_status', 'idx_church_status', 'idx_membership_status'] as $index) {
            try {
                $this->forge->dropKey('profile', $index);
            } catch (\Throwable $e) {
                // Index absent on this install — fine.
            }
        }

        $this->forge->dropColumn('profile', 'membership_status');
    }

    public function down()
    {
        if (! $this->db->tableExists('profile')) {
            return;
        }

        if ($this->db->fieldExists('membership_status', 'profile')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE `profile`
             ADD COLUMN `membership_status`
             ENUM('visitor','regular_attender','member','non_active','inactive','transferred','deceased')
             NOT NULL DEFAULT 'visitor'"
        );

        // Restore the historical mirror from the primary affiliation.
        $this->db->query(
            "UPDATE `profile` p
             LEFT JOIN `parish_affiliation` pa
               ON pa.linked_user_id = p.user_id
              AND pa.is_primary = 1
              AND pa.deleted_at IS NULL
             SET p.membership_status = COALESCE(pa.membership_status, 'visitor')"
        );
    }
}
