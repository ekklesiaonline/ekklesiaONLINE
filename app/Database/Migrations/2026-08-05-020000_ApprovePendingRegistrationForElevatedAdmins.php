<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repairs registration_request rows left stuck at 'pending_profile' or
 * 'pending_review' for users who already hold a top-tier admin group
 * (systemadmin/provinceadmin/dioceseadmin).
 *
 * This is the exact scenario reported in production: a diocese admin's own
 * parish-join request was escalated (RegistrationApprovalService::escalate())
 * instead of auto-approved, leaving their profile.membership_status as
 * 'visitor' and /profile showing "Pending" + no parish affiliation even
 * though they were already a fully-functioning diocese admin. escalate()
 * now auto-approves top-tier admins going forward (see
 * RegistrationApprovalService::escalate()); this migration fixes any
 * accounts already stuck in that state.
 */
class ApprovePendingRegistrationForElevatedAdmins extends Migration
{
    private const ADMIN_GROUPS = ['systemadmin', 'provinceadmin', 'dioceseadmin'];

    public function up()
    {
        $requestTable = $this->resolveRegistrationRequestTable();
        if (
            $requestTable === null
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('profile')
            || ! $this->db->fieldExists('membership_status', 'profile')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $stuckRequests = $this->db->table($requestTable . ' urr')
            ->select('urr.id, urr.user_id, urr.province_id, urr.diocese_id')
            ->join('auth_groups_users agu', 'agu.user_id = urr.user_id', 'inner')
            ->whereIn('urr.status', ['pending_profile', 'pending_review'])
            ->whereIn('agu.group', self::ADMIN_GROUPS)
            ->get()
            ->getResultArray();

        foreach ($stuckRequests as $row) {
            $this->db->table($requestTable)->where('id', (int) $row['id'])->update([
                'status'      => 'approved',
                'reviewed_at' => $now,
                'updated_at'  => $now,
            ]);

            // 'member' (not 'active') is the valid profile.membership_status
            // ENUM value; MembershipStatusService normalizes/displays it as "Active".
            $profileUpdate = ['membership_status' => 'member'];

            $profile = $this->db->table('profile')->where('user_id', (int) $row['user_id'])->get()->getRowArray();
            if ($profile !== null) {
                if (empty($profile['province_id']) && ! empty($row['province_id'])) {
                    $profileUpdate['province_id'] = (int) $row['province_id'];
                }
                if (empty($profile['diocese_id']) && ! empty($row['diocese_id'])) {
                    $profileUpdate['diocese_id'] = (int) $row['diocese_id'];
                }

                $this->db->table('profile')->where('user_id', (int) $row['user_id'])->update($profileUpdate);
            }
        }
    }

    public function down()
    {
        // No-op: do not revert an admin's account back to a stuck pending state.
    }

    private function resolveRegistrationRequestTable(): ?string
    {
        $candidates = array_values(array_unique([
            $this->db->getPrefix() . 'user_registration_request',
            'user_registration_request',
            $this->db->getPrefix() . 'user_registration_requests',
            'user_registration_requests',
        ]));

        foreach ($candidates as $candidate) {
            if (! $this->db->tableExists($candidate)) {
                continue;
            }

            try {
                if ($this->db->getFieldNames($candidate) !== []) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }
}
