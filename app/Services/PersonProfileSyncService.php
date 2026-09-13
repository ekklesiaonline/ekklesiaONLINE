<?php

namespace App\Services;

use App\Models\PersonModel;
use App\Models\ProfileModel;

/**
 * Keep the canonical `person` row in sync with a parish `profile` row.
 *
 * The `person` table is the parish-agnostic pastoral identity (name, contact,
 * birthdate). Every time a profile is created or edited, this service ensures
 * the canonical record reflects the same identity data and that the profile is
 * linked to a person.
 */
class PersonProfileSyncService
{
    /**
     * Fields that can be copied from a profile row onto the canonical person row.
     *
     * Key = profile column, Value = person column.
     */
    private const FIELD_MAP = [
        'first_name' => 'first_name',
        'last_name'  => 'last_name',
        'phone'      => 'phone',
        'address'    => 'address',
        'birth_date' => 'birthdate',
    ];

    /**
     * Cached list of columns on the person table for the current connection.
     *
     * @var list<string>|null
     */
    private ?array $personFieldNames = null;

    /**
     * Sync the canonical person for the given profile_id.
     *
     * @return int|null The person_id, or null on failure.
     */
    public function syncFromProfile(int $profileId): ?int
    {
        $profile = model(ProfileModel::class)->find($profileId);

        if (! is_array($profile) || empty($profile['id'])) {
            return null;
        }

        return $this->syncFromProfileData($profile);
    }

    /**
     * Sync a person from an already-loaded profile array.
     *
     * @param array<string, mixed> $profile
     * @return int|null The person_id, or null on failure.
     */
    public function syncFromProfileData(array $profile): ?int
    {
        $profileId = (int) ($profile['id'] ?? 0);
        $userId    = (int) ($profile['user_id'] ?? 0);

        if ($profileId <= 0) {
            return null;
        }

        if (! db_connect()->tableExists('person')) {
            return null;
        }

        $personId = $this->resolvePersonId($profile, $userId);
        if ($personId === null) {
            return null;
        }

        $payload = $this->buildPersonPayload($profile, $userId);
        if ($payload !== []) {
            $personModel = model(PersonModel::class);
            $personModel->update($personId, $payload);
        }

        return $personId;
    }

    /**
     * Find or create a person_id for a profile and ensure the profile/user links
     * are set.
     *
     * @param array<string, mixed> $profile
     * @param int $userId
     * @return int|null
     */
    public function findOrCreatePersonForProfile(array $profile, int $userId): ?int
    {
        $profileId = (int) ($profile['id'] ?? 0);
        $existingPersonId = isset($profile['person_id']) ? (int) $profile['person_id'] : 0;

        if ($existingPersonId > 0) {
            return $existingPersonId;
        }

        $db = db_connect();

        // Defensive: a user_person row may already exist even if profile.person_id is null.
        // Skip this check for non-portal profiles (userId = 0) — they have no user to link.
        if ($userId > 0 && $db->tableExists('user_person')) {
            $existing = $db->table('user_person')
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            if (! empty($existing['person_id'])) {
                $personId = (int) $existing['person_id'];
                $linked   = $this->linkProfileToPerson($profileId, $personId, $userId);
                return $linked ? $personId : null;
            }
        }

        $personId = $this->createPersonFromProfile($profile, $userId);
        if ($personId === null) {
            return null;
        }

        $linked = $this->linkProfileToPerson($profileId, $personId, $userId);

        return $linked ? $personId : null;
    }

    /**
     * Build the update payload for the person row from the profile row.
     *
     * @param array<string, mixed> $profile
     * @param int $userId
     * @return array<string, mixed>
     */
    private function buildPersonPayload(array $profile, int $userId): array
    {
        $payload = [];

        foreach (self::FIELD_MAP as $profileField => $personField) {
            $value = $profile[$profileField] ?? null;
            if ($value !== null && $value !== '' && $value !== '0000-00-00') {
                $payload[$personField] = $value;
            }
        }

        // Email is not normally stored on profile; pull from the auth identity.
        $email = ! empty($profile['email']) ? trim((string) $profile['email']) : '';
        if ($email === '') {
            $email = $this->resolveEmailForUser($userId);
        }

        if ($email !== '') {
            $payload['email'] = $email;
        }

        if ($payload === []) {
            return [];
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->filterPersonPayload($payload);
    }

    /**
     * Restrict a payload to columns that actually exist on the person table.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPersonPayload(array $payload): array
    {
        $fieldNames = $this->getPersonFieldNames();
        if ($fieldNames === []) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($fieldNames));
    }

    /**
     * @return list<string>
     */
    private function getPersonFieldNames(): array
    {
        if ($this->personFieldNames === null) {
            try {
                $this->personFieldNames = db_connect()->getFieldNames('person');
            } catch (\Throwable $e) {
                $this->personFieldNames = [];
            }
        }

        return $this->personFieldNames;
    }

    /**
     * Resolve the person_id, creating a person row if necessary.
     *
     * @param array<string, mixed> $profile
     * @param int $userId
     * @return int|null
     */
    private function resolvePersonId(array $profile, int $userId): ?int
    {
        $existingPersonId = isset($profile['person_id']) ? (int) $profile['person_id'] : 0;

        if ($existingPersonId > 0) {
            return $existingPersonId;
        }

        return $this->findOrCreatePersonForProfile($profile, $userId);
    }

    /**
     * Create a new canonical person from the profile data.
     *
     * @param array<string, mixed> $profile
     * @param int $userId
     * @return int|null
     */
    private function createPersonFromProfile(array $profile, int $userId): ?int
    {
        $payload = $this->buildPersonPayload($profile, $userId);
        if ($payload === []) {
            // No identity data yet; create a minimal placeholder person.
            $payload = [
                'first_name' => ! empty($profile['first_name']) ? (string) $profile['first_name'] : 'Unknown',
                'last_name'  => ! empty($profile['last_name'])  ? (string) $profile['last_name']  : 'Unknown',
            ];
        }

        $payload['source']      = 'profile_sync';
        $payload['is_active']   = 1;
        $payload['created_at']  = date('Y-m-d H:i:s');
        $payload['updated_at']  = date('Y-m-d H:i:s');

        $personModel = model(PersonModel::class);
        $personId    = $personModel->insert($this->filterPersonPayload($payload), true);

        return is_int($personId) && $personId > 0 ? $personId : null;
    }

    /**
     * Ensure the profile and user_person rows point to the given person.
     *
     * @param int $profileId
     * @param int $personId
     * @param int $userId
     * @return bool True if profile.person_id was successfully written.
     */
    private function linkProfileToPerson(int $profileId, int $personId, int $userId): bool
    {
        $db = db_connect();

        // Update profile.person_id directly to bypass any model scoping.
        if (! $db->tableExists('profile')) {
            log_message('error', 'PersonProfileSyncService: profile table does not exist; cannot link profile #' . $profileId);
            return false;
        }

        if (! $db->fieldExists('person_id', 'profile')) {
            log_message('critical', 'PersonProfileSyncService: profile.person_id column does not exist. Run `php spark migrate` to add it. Profile #' . $profileId . ' could not be linked to person #' . $personId);
            return false;
        }

        $affected = $db->table('profile')
            ->where('id', $profileId)
            ->update([
                'person_id'  => $personId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected === false || $db->affectedRows() === 0) {
            // The update may have matched 0 rows (profile not found) or the
            // value was already set. Verify by re-reading.
            $check = $db->table('profile')
                ->select('person_id')
                ->where('id', $profileId)
                ->get()
                ->getRowArray();

            if ((int) ($check['person_id'] ?? 0) !== $personId) {
                log_message('warning', 'PersonProfileSyncService: profile.person_id update did not take effect for profile #' . $profileId . ' (expected person #' . $personId . ')');
                return false;
            }
        }

        // Non-portal profiles (userId = 0) have no user account to link.
        if ($userId <= 0 || ! $db->tableExists('user_person')) {
            return true;
        }

        $existing = $db->table('user_person')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults();

        if ($existing > 0) {
            return true;
        }

        $now = date('Y-m-d H:i:s');
        $db->table('user_person')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'is_primary' => 1,
            'linked_at'  => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    /**
     * Look up the email address for a user from their auth identity.
     *
     * @return string Plaintext email, or empty string if none.
     */
    private function resolveEmailForUser(int $userId): string
    {
        $db = db_connect();

        if (! $db->tableExists('auth_identities')) {
            return '';
        }

        $identity = $db->table('auth_identities')
            ->select('secret')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        return ! empty($identity['secret']) ? trim((string) $identity['secret']) : '';
    }
}
