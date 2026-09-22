<?php

namespace App\Services;

use App\Models\PersonModel;
use App\Models\ProfileModel;
use App\Models\MemberParishAffiliationModel;

class PersonConversionService
{
    /**
     * Convert a visitor person to a member.
     *
     * @param int      $personId         Person to convert
     * @param int      $parishId         Parish scope
     * @param int|null $userId           Existing user to link (optional)
     * @param string|null $email         Email for new portal account (optional)
     * @param string|null $password      Password for new portal account (optional)
     * @param int|null $convertedByUserId Actor who performed the conversion (optional)
     * @return bool
     */
    public static function convertToMember(
        int $personId,
        int $parishId,
        ?int $userId = null,
        ?string $email = null,
        ?string $password = null,
        ?int $convertedByUserId = null
    ): bool {
        $db = db_connect();

        $db->transBegin();

        try {
            $person = model(PersonModel::class)->find($personId);
            if (! is_array($person)) {
                throw new \RuntimeException('Person not found.');
            }

            $affiliation = $db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('parish_id', $parishId)
                ->get()
                ->getRowArray();

            if (! is_array($affiliation)) {
                throw new \RuntimeException('Parish affiliation not found.');
            }

            $affiliationId = (int) $affiliation['id'];

            $now = date('Y-m-d H:i:s');

            if ($userId !== null && $userId > 0) {
                self::linkToUser($userId, $personId, $parishId, $person, $now);
            } elseif ($email !== null && $email !== '' && $password !== null && $password !== '') {
                $userId = self::createPortalUser($email, $password, $person, $personId, $parishId, $now);
            }

            $db->table('parish_affiliation')
                ->where('id', (int) $affiliation['id'])
                ->update(MembershipStatusService::fieldsForStatus('member') + [
                    'linked_user_id'    => $userId ?? $affiliation['linked_user_id'] ?? null,
                    'updated_at'        => $now,
                ]);

            (new PersonStatusHistoryService())->record(
                $personId,
                'affiliation',
                $affiliation['membership_status'] ?? 'visitor',
                'member',
                $parishId,
                $convertedByUserId,
                'Converted to member',
            );

            self::markPendingFollowupsSkipped($personId, $now);
            self::markGuestConverted($affiliationId, $userId, $convertedByUserId, $now);

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PersonConversionService::convertToMember failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function linkToUser(int $userId, int $personId, int $parishId, array $person, string $now): void
    {
        $db = db_connect();

        if ($db->tableExists('user_person')) {
            $existing = $db->table('user_person')
                ->where('user_id', $userId)
                ->where('person_id', $personId)
                ->countAllResults();
            if ($existing === 0) {
                $db->table('user_person')->insert([
                    'user_id'    => $userId,
                    'person_id'  => $personId,
                    'is_primary' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $existingProfile = model(ProfileModel::class)
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->first();

        if (! is_array($existingProfile)) {
            $profileId = model(ProfileModel::class)->insert([
                'user_id'           => $userId,
                'person_id'         => $personId,
                'parish_id'         => $parishId,
                'first_name'        => (string) ($person['first_name'] ?? ''),
                'last_name'         => (string) ($person['last_name'] ?? ''),
                'email'             => (string) ($person['email'] ?? '') ?: null,
                'phone'             => (string) ($person['phone'] ?? '') ?: null,
                'membership_status' => 'member',
                'portal_access'     => 'enabled',
                'is_confirmed'      => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ], true);

            if (is_int($profileId) && $profileId > 0) {
                (new PersonProfileSyncService())->syncFromProfile($profileId);
            }
        }
    }

    private static function createPortalUser(string $email, string $password, array $person, int $personId, int $parishId, string $now): int
    {
        $db = db_connect();

        $username = self::generateUsername($email, $person);
        $db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $db->insertID();
        if ($userId <= 0) {
            throw new \RuntimeException('Failed to create user.');
        }

        $db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => 'email_password',
            'secret'     => $email,
            'secret2'    => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => 'member',
            'created_at' => $now,
        ]);

        self::linkToUser($userId, $personId, $parishId, $person, $now);

        return $userId;
    }

    private static function generateUsername(string $email, array $person): string
    {
        $base = trim((string) ($person['first_name'] ?? '') . ($person['last_name'] ?? ''));
        if ($base === '') {
            $base = explode('@', $email)[0] ?? 'user';
        }
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $base = strtolower(substr($base, 0, 20)) ?: 'user';
        return $base . '_' . bin2hex(random_bytes(3));
    }

    /**
     * Mark a person's guest affiliation as converted when the member
     * account was created through a different path (e.g. the pre-filled
     * member create form). Skips pending follow-ups for the person.
     */
    public static function recordManualConversion(int $personId, int $parishId, ?int $userId, ?int $convertedByUserId = null): void
    {
        $now = date('Y-m-d H:i:s');

        $update = [
            'followed_up'            => 1,
            'converted_to_member_at' => $now,
            'converted_by_user_id'   => $convertedByUserId,
            'updated_at'             => $now,
        ];
        if ($userId !== null && $userId > 0) {
            $update['linked_user_id'] = $userId;
        }

        $db = db_connect();
        $db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->update($update);

        self::markPendingFollowupsSkipped($personId, $now);
    }

    private static function markPendingFollowupsSkipped(int $personId, string $now): void
    {
        $db = db_connect();
        $db->table('followup_log')
            ->where('person_id', $personId)
            ->where('status', 'pending')
            ->update([
                'status'     => 'skipped',
                'notes'      => 'Converted to member',
                'updated_at' => $now,
            ]);
    }

    private static function markGuestConverted(int $affiliationId, ?int $userId, ?int $convertedByUserId, string $now): void
    {
        db_connect()->table('parish_affiliation')
            ->where('id', $affiliationId)
            ->update([
                'followed_up'            => 1,
                'converted_to_member_at' => $now,
                'converted_by_user_id'   => $convertedByUserId,
            ]);
    }
}
