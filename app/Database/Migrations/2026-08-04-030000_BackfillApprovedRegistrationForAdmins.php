<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * System/province/diocese admins created via AdminCreationService (and any
 * created before that service started writing a user_registration_request
 * row) have no registration_request row at all. Most of the app already
 * treats "no row" as an implicit approved/staff state (RegistrationGateFilter,
 * Profile::resolveCurrentUserProfileState), but this backfill gives every
 * existing elevated admin an explicit 'approved' row for a consistent audit
 * trail, matching what AdminCreationService now does for newly created admins.
 *
 * Skipped for accounts where no province can be resolved (e.g. a system admin
 * with no province/diocese scope at all), because user_registration_requests.
 * province_id is NOT NULL.
 */
class BackfillApprovedRegistrationForAdmins extends Migration
{
    private const ADMIN_GROUPS = ['systemadmin', 'provinceadmin', 'dioceseadmin'];

    public function up()
    {
        $table = $this->resolveRegistrationRequestTable();
        if (
            $table === null
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('profile')
            || ! $this->db->fieldExists('membership_status', 'profile')
            || ! $this->db->tableExists('diocese')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $admins = $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, agu.group, p.province_id, p.diocese_id, p.membership_status')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->whereIn('agu.group', self::ADMIN_GROUPS)
            ->whereNotIn('agu.user_id', function ($builder) use ($table) {
                return $builder->select('user_id')->from($table);
            })
            ->get()
            ->getResultArray();

        $columns = array_map('strtolower', $this->db->getFieldNames($table));

        foreach ($admins as $admin) {
            $provinceId = (int) ($admin['province_id'] ?? 0);
            $dioceseId = (int) ($admin['diocese_id'] ?? 0);

            if ($provinceId <= 0 && $dioceseId > 0) {
                $diocese = $this->db->table('diocese')->select('province_id')->where('id', $dioceseId)->get()->getRowArray();
                $provinceId = (int) ($diocese['province_id'] ?? 0);
            }

            // Cannot satisfy the NOT NULL province_id FK (e.g. a system admin
            // with no diocese/province at all) — leave "no row" as-is.
            if ($provinceId <= 0) {
                continue;
            }

            $payload = array_intersect_key([
                'user_id'              => (int) $admin['user_id'],
                'province_id'          => $provinceId,
                'diocese_id'           => $dioceseId > 0 ? $dioceseId : null,
                'status'               => 'approved',
                'requested_role'       => $admin['group'],
                'profile_completed_at' => $now,
                'reviewed_at'          => $now,
                'created_at'           => $now,
                'updated_at'           => $now,
            ], array_flip($columns));

            $this->db->table($table)->insert($payload);
        }
    }

    public function down()
    {
        // No-op: do not remove backfilled audit-trail rows.
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
