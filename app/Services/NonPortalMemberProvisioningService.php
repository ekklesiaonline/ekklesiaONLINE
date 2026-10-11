<?php

namespace App\Services;

use App\Libraries\SchemaResolver;
use CodeIgniter\Database\BaseConnection;

class NonPortalMemberProvisioningService
{
    private BaseConnection $db;
    private SchemaResolver $schemaResolver;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->schemaResolver = new SchemaResolver($this->db);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function create(array $payload, int $parishId): array
    {
        $familyId = (int) ($payload['family_id'] ?? 0);
        if ($familyId > 0) {
            $family = $this->db->table('family')->where('id', $familyId)->get()->getRowArray();
            if ($family === null) {
                return $this->result(false, 404, 'FAMILY_NOT_FOUND', 'Family not found.');
            }

            if ((int) ($family['parish_id'] ?? 0) !== $parishId) {
                return $this->result(false, 403, 'OUT_OF_SCOPE', 'The requested family is out of scope.');
            }
        }

        $username = trim((string) ($payload['username'] ?? ''));

        $now = date('Y-m-d H:i:s');
        $birthDate = isset($payload['birth_date']) && trim((string) $payload['birth_date']) !== ''
            ? (string) $payload['birth_date']
            : null;
        $relationship = trim((string) ($payload['relationship'] ?? ''));
        $relationship = $relationship === '' ? null : $relationship;

        $isChild = filter_var($payload['is_child'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isMember = filter_var($payload['is_member'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $defaultStatus = $isChild
            ? 'visitor'
            : ($isMember ? 'member' : 'visitor');
        $paStatus = \App\Services\MembershipStatusService::normalizeAffiliationStatus($defaultStatus);

        $useTransaction = strtolower((string) $this->db->DBDriver) !== 'sqlite3';
        if ($useTransaction) {
            $this->db->transBegin();
        }

        try {
            $provisioned = \App\Services\PersonProvisioningService::provision([
                'parish_id'              => $parishId,
                'first_name'             => (string) ($payload['first_name'] ?? ''),
                'last_name'              => (string) ($payload['last_name'] ?? ''),
                'email'                  => (string) ($payload['email'] ?? '') ?: null,
                'phone'                  => (string) ($payload['phone'] ?? '') ?: null,
                'address'                => (string) ($payload['address'] ?? '') ?: null,
                'nickname'               => (string) ($payload['nickname'] ?? '') ?: null,
                'birth_date'             => $birthDate,
                'birthdate'              => $birthDate,
                'is_child'               => $isChild,
                'membership_status'      => $paStatus,
                'source'                 => 'system',
                'preferred_contact_method' => 'email',
                'create_user'              => true,
                'username'                 => $username !== '' ? $username : null,
            ]);

            $userId = (int) $provisioned['user_id'];
            $personId = (int) $provisioned['person_id'];

            $profileId = $this->resolveProfileIdForUser($userId);
            if ($profileId !== null) {
                (new PersonProfileSyncService())->syncFromProfile($profileId);
            }

            $familyMemberId = null;
            if ($familyId > 0) {
                $familyMemberColumns = array_map('strtolower', $this->getColumnsForTable('family_member'));
                $familyMemberData = [
                    'family_id' => $familyId,
                    'user_id' => $userId,
                ];

                if (in_array('relationship', $familyMemberColumns, true)) {
                    $familyMemberData['relationship'] = $relationship !== null ? $relationship : 'Member';
                }
                if (in_array('created_at', $familyMemberColumns, true)) {
                    $familyMemberData['created_at'] = $now;
                }

                $this->db->table('family_member')->insert($familyMemberData);
                $familyMemberId = (int) $this->db->insertID();
            }

            if ($useTransaction) {
                $this->db->transCommit();
            }

            $linkedToFamily = $familyId > 0;
            return $this->result(
                true,
                201,
                $linkedToFamily ? 'NON_PORTAL_MEMBER_CREATED' : 'NON_PORTAL_MEMBER_CREATED_STANDALONE',
                $linkedToFamily ? 'Non-portal member created and linked to family.' : 'Standalone non-portal member created.',
                [],
                [
                    'user_id' => $userId,
                    'person_id' => $personId,
                    'profile_id' => $this->resolveProfileIdForUser($userId),
                    'family_id' => $linkedToFamily ? $familyId : null,
                    'parish_id' => $parishId,
                    'portal_access' => 'none',
                    'affiliation_status' => 'active',
                    'family_member_id' => $familyMemberId,
                    'family_linked' => $linkedToFamily,
                ]
            );
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $this->db->transRollback();
            }

            $error = strtolower($e->getMessage());
            if (str_contains($error, 'duplicate') || str_contains($error, 'unique')) {
                return $this->result(false, 409, 'TRANSACTION_FAILED', 'Duplicate value prevented creating non-portal member.', [
                    [
                        'field' => 'username',
                        'code' => 'ALREADY_EXISTS',
                        'message' => 'Username already exists.',
                    ],
                ]);
            }

            return $this->result(false, 500, 'TRANSACTION_FAILED', 'Failed to create non-portal member.', [
                [
                    'field' => 'system',
                    'code' => 'TRANSACTION_FAILED',
                    'message' => 'The create operation could not be completed. ' . $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Resolve the profile_id for a freshly created user.
     *
     * @return int|null
     */
    private function resolveProfileIdForUser(int $userId): ?int
    {
        if (! $this->db->tableExists('profile')) {
            return null;
        }

        $profile = $this->db->table('profile')
            ->select('id')
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return $profile ? (int) $profile['id'] : null;
    }

    /**
     * Revoke portal access for an existing user so they can no longer log in.
     *
     * Steps (atomic transaction):
     *  1. Delete auth_identities row(s) of type email_password — removes login capability.
     *     NOTE: users.active is intentionally left at 1. Setting it to 0 could exclude
     *     the person from parish directory listings that filter on active portal users.
     *     The auth_identities deletion alone prevents login; Shield requires that row
     *     to authenticate.
     *  2. Set profile.portal_access = 'none' — keeps the column consistent with the
     *     auth_identities state so the non-portal badge logic evaluates correctly.
     *
     * The caller is responsible for writing the audit log.
     *
     * @return array<string,mixed>
     */
    public function revokePortalAccess(int $userId, int $parishId): array
    {
        // Verify user belongs to this parish.
        $profileBuilder = $this->db->table('profile')
            ->where('user_id', $userId);
        $profileColumns = array_map('strtolower', $this->getColumnsForTable('profile'));
        if (in_array('parish_id', $profileColumns, true)) {
            $profileBuilder->where('parish_id', $parishId);
        } elseif (in_array('parish_id', $profileColumns, true)) {
            $profileBuilder->where('parish_id', $parishId);
        } else {
            return $this->result(false, 404, 'MEMBER_NOT_FOUND', 'Member not found in this parish.');
        }
        $profile = $profileBuilder->get()->getRowArray();

        if ($profile === null) {
            return $this->result(false, 404, 'MEMBER_NOT_FOUND', 'Member not found in this parish.');
        }

        // Check that a real portal identity exists — nothing to do if already non-portal.
        $identityCount = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->countAllResults();

        if ($identityCount === 0) {
            return $this->result(false, 409, 'ALREADY_NON_PORTAL', 'This member has no active portal identity.');
        }

        // Leadership eligibility requires portal access — revoking it while
        // the user holds active positions would silently recreate the
        // "leader with no login" inconsistency.
        if ($this->db->tableExists('leadership_position')) {
            $activePositions = $this->db->table('leadership_position')
                ->where('user_id', $userId)
                ->where('active', 1)
                ->countAllResults();
            if ($activePositions > 0) {
                return $this->result(false, 409, 'ACTIVE_LEADERSHIP_POSITIONS', 'Portal access cannot be revoked while the user holds active leadership positions. End or deactivate the positions first.', [
                    [
                        'field' => 'userId',
                        'code' => 'ACTIVE_LEADERSHIP_POSITIONS',
                        'message' => 'User holds ' . $activePositions . ' active leadership position(s).',
                    ],
                ]);
            }
        }

        $this->db->transBegin();

        try {
            $this->db->table('auth_identities')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->delete();

            // Do NOT set users.active = 0 — the auth_identities deletion alone
            // prevents login; the user record stays active so they remain a
            // visible member on /people.

            $profileUpdate = ['portal_access' => 'none', 'updated_at' => date('Y-m-d H:i:s')];
            $profileColumns = array_map('strtolower', $this->getColumnsForTable('profile'));
            $profileTable2 = $this->db->getPrefix() !== '' ? $this->db->getPrefix() . 'profile' : 'profile';
            if (in_array('portal_enabled_at', $profileColumns, true)) {
                $profileUpdate['portal_enabled_at'] = null;
            }
            $this->db->table($profileTable2)
                ->where('user_id', $userId)
                ->update($profileUpdate);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction status is false after portal revoke flow.');
            }

            $this->db->transCommit();

            return $this->result(true, 200, 'PORTAL_ACCESS_REVOKED', 'Portal access revoked successfully.', [], [
                'user_id' => $userId,
                'parish_id' => $parishId,
                'portal_access' => 'none',
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            return $this->result(false, 500, 'TRANSACTION_FAILED', 'Failed to revoke portal access.', [
                ['field' => 'system', 'code' => 'TRANSACTION_FAILED', 'message' => 'The revoke operation could not be completed.'],
            ]);
        }
    }

    /**
     * @return array<int,string>
     */
    private function getColumnsForTable(string $baseTable): array
    {
        return $this->schemaResolver->getColumnsForTable($baseTable);
    }

    /**
     * @param array<int,array<string,string>> $errors
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>
     */
    private function result(bool $ok, int $status, string $code, string $message, array $errors = [], ?array $data = null): array
    {
        return [
            'ok' => $ok,
            'http_status' => $status,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
