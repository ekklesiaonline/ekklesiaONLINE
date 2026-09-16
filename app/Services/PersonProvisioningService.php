<?php

namespace App\Services;

use App\Models\PersonModel;
use App\Models\ProfileModel;
use DateTime;

class PersonProvisioningService
{
    /**
     * Provision a person and all related identity links.
     *
     * If a `user_id` is supplied, the person is linked to that user and a profile
     * is created. If no `user_id` is supplied but `create_user` is truthy (or a
     * `username` is provided), a placeholder `users` row is created automatically.
     *
     * @return array{user_id: ?int, person_id: int, profile_id: ?int}
     */
    public static function provision(array $data): array
    {
        $parishId = (int) ($data['parish_id'] ?? 0);
        if ($parishId <= 0 || ! self::isActiveParish($parishId)) {
            throw new \RuntimeException('A valid, active parish_id is required.');
        }

        $now = date('Y-m-d H:i:s');
        $db  = db_connect();

        $db->transBegin();

        try {
            $userId = self::resolveUserId($data, $now);

            $personData = [
                'first_name'    => (string) ($data['first_name'] ?? ''),
                'last_name'     => (string) ($data['last_name'] ?? ''),
                'email'         => (string) ($data['email'] ?? '') ?: null,
                'phone'         => (string) ($data['phone'] ?? '') ?: null,
                'address'       => (string) ($data['address'] ?? '') ?: null,
                'preferred_contact_method' => (string) ($data['preferred_contact_method'] ?? 'email') ?: 'email',
                'preferred_timezone'       => (string) ($data['preferred_timezone'] ?? '') ?: null,
                'opted_out_channels'       => (string) ($data['opted_out_channels'] ?? '') ?: '',
                'birthdate'     => (string) ($data['birthdate'] ?? $data['birth_date'] ?? '') ?: null,
                'gender'        => (string) ($data['gender'] ?? '') ?: null,
                'source'        => (string) ($data['source'] ?? 'system') ?: 'system',
                'is_active'     => (int) ($data['is_active'] ?? 1),
            ];

            $personModel = model(PersonModel::class);
            $personId    = $personModel->insert($personData, true);

            if (! is_int($personId) || $personId <= 0) {
                throw new \RuntimeException('Failed to create person.');
            }

            $membershipStatus  = (string) ($data['membership_status'] ?? 'visitor');
            $isMember          = (int) ($data['is_member'] ?? (int) MembershipStatusService::isMember($membershipStatus));
            $affiliationType   = (string) ($data['affiliation_type'] ?? MembershipStatusService::affiliationTypeForStatus($membershipStatus));
            $canonicalStatus   = (string) ($data['canonical_status'] ?? $membershipStatus);

            $db->table('parish_affiliation')->insert([
                'parish_id'        => $parishId,
                'person_id'        => $personId,
                'linked_user_id'   => $userId,
                'affiliation_type' => $affiliationType,
                'membership_status' => $membershipStatus,
                'canonical_status'  => $canonicalStatus,
                'is_member'        => $isMember,
                'source'           => (string) ($data['source'] ?? 'system'),
                'start_date'       => (string) ($data['start_date'] ?? date('Y-m-d')),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $profileId = null;
            if ($userId !== null && $userId > 0) {
                self::linkToUser($userId, $personId, $now);
                $profileId = self::createProfile($userId, $personId, $parishId, $data, $now);
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Sync the canonical person record outside the transaction to avoid
        // nested transaction conflicts in PersonProfileSyncService.
        if (isset($profileId) && $profileId !== null && $profileId > 0) {
            (new PersonProfileSyncService())->syncFromProfile($profileId);
        }

        return [
            'user_id'    => $userId ?? null,
            'person_id'  => $personId,
            'profile_id' => $profileId ?? null,
        ];
    }

    private static function resolveUserId(array $data, string $now): ?int
    {
        if (isset($data['user_id']) && (int) $data['user_id'] > 0) {
            return (int) $data['user_id'];
        }

        if (empty($data['create_user']) && empty($data['username'])) {
            return null;
        }

        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            $username = self::generateUsername();
        }

        $db = db_connect();
        $db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $db->insertID();
        if ($userId <= 0) {
            throw new \RuntimeException('Failed to create placeholder user row.');
        }

        return $userId;
    }

    private static function createProfile(int $userId, int $personId, int $parishId, array $data, string $now): ?int
    {
        $isChild = filter_var($data['is_child'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $profileData = [
            'user_id'          => $userId,
            'person_id'        => $personId,
            'parish_id'        => $parishId,
            'first_name'       => (string) ($data['first_name'] ?? ''),
            'last_name'        => (string) ($data['last_name'] ?? ''),
            'phone'            => (string) ($data['phone'] ?? '') ?: null,
            'address'          => (string) ($data['address'] ?? '') ?: null,
            'email'            => (string) ($data['email'] ?? '') ?: null,
            'membership_status' => (string) ($data['profile_membership_status'] ?? $data['membership_status'] ?? 'regular_attender'),
            'portal_access'    => (string) ($data['portal_access'] ?? 'none'),
            'is_confirmed'     => 0,
            'birth_date'       => (string) ($data['birth_date'] ?? $data['birthdate'] ?? '') ?: null,
            'is_dependent'     => $isChild ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $nickname = trim((string) ($data['nickname'] ?? ''));
        if ($nickname !== '') {
            $profileData['nickname'] = $nickname;
        }

        $profileModel = model(ProfileModel::class);
        $profileId    = $profileModel->insert($profileData, true);

        return is_int($profileId) && $profileId > 0 ? $profileId : null;
    }

    private static function isActiveParish(int $parishId): bool
    {
        $db = db_connect();

        if (! $db->tableExists('parish')) {
            return false;
        }

        $query = $db->table('parish')->where('id', $parishId);

        if ($db->fieldExists('deleted_at', 'parish')) {
            $query->where('deleted_at', null);
        }

        return $query->countAllResults() > 0;
    }

    private static function linkToUser(int $userId, int $personId, string $now): void
    {
        $db = db_connect();

        if (! $db->tableExists('user_person')) {
            return;
        }

        $existing = $db->table('user_person')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults();

        if ($existing > 0) {
            return;
        }

        $db->table('user_person')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'is_primary' => 1,
            'linked_at'  => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function generateUsername(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'person_' . time() . '_' . random_int(1000, 999999);
            $exists = db_connect()->table('users')->where('username', $candidate)->countAllResults();
            if ($exists === 0) {
                return $candidate;
            }
        }

        return 'person_' . time() . '_' . bin2hex(random_bytes(4));
    }
}
