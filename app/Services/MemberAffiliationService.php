<?php

namespace App\Services;

use App\Models\MemberParishAffiliationModel;
use App\Models\ParishModel;
use App\Models\ProfileModel;
use CodeIgniter\Database\BaseConnection;

/**
 * MemberAffiliationService
 *
 * Owns parish_affiliation write paths for member parish attachment:
 * attach/detach/home-parish transactions (each spanning the affiliation
 * table plus the legacy profile mirror), affiliation deactivation on
 * delete, and canonical person cleanup via PersonCleanupService.
 *
 * Transaction methods return null on success or a stage code on failure:
 * 'attach' | 'detach' | 'home' | 'sync' | 'transaction'.
 */
class MemberAffiliationService
{
    private BaseConnection $db;
    private MemberParishAffiliationModel $affiliations;
    private ProfileModel $profiles;
    private ParishModel $parishes;

    public function __construct(
        ?BaseConnection $db = null,
        ?MemberParishAffiliationModel $affiliations = null,
        ?ProfileModel $profiles = null,
        ?ParishModel $parishes = null
    ) {
        $this->db = $db ?? db_connect();
        $this->affiliations = $affiliations ?? model(MemberParishAffiliationModel::class);
        $this->profiles = $profiles ?? model(ProfileModel::class);
        $this->parishes = $parishes ?? model(ParishModel::class);
    }

    /**
     * Deactivate all active parish_affiliation rows for a user.
     *
     * @return list<int> Parish IDs that were deactivated (for re-sync).
     */
    public function deactivateActiveAffiliationsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->table('parish_affiliation')
            ->select('parish_id AS parish_id')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->get()
            ->getResultArray();

        $parishIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $rows
        )));
        $parishIds = array_values(array_filter($parishIds, static fn(int $id): bool => $id > 0));

        if ($parishIds === []) {
            return [];
        }

        // Off the register: same state setMemberTag() produces when the
        // member tag is revoked — keeps status/flag consistent instead of
        // leaving membership_status='member' on a non-member row.
        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->update(MembershipStatusService::fieldsForStatus('visitor') + [
                'end_date'   => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $parishIds;
    }

    /**
     * Deactivate canonical person records for a user when no active
     * profiles remain linked to them.
     */
    public function deactivatePersonsForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $service = new PersonCleanupService($this->db);
        $personIds = [];

        if ($this->db->tableExists('user_person')) {
            $rows = $this->db->table('user_person')
                ->distinct()
                ->select('person_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $id = (int) ($row['person_id'] ?? 0);
                if ($id > 0) {
                    $personIds[$id] = true;
                }
            }
        }

        if ($this->db->tableExists('profile')) {
            $rows = $this->db->table('profile')
                ->distinct()
                ->select('person_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $id = (int) ($row['person_id'] ?? 0);
                if ($id > 0) {
                    $personIds[$id] = true;
                }
            }
        }

        foreach (array_keys($personIds) as $personId) {
            $service->deactivateIfNoActiveProfiles($personId);
        }
    }

    /**
     * Attach a user to a parish (optionally as home parish) and mirror
     * the result onto the legacy profile row. Runs in a transaction.
     */
    public function attachToParish(
        int $userId,
        int $parishId,
        string $status,
        bool $isHome,
        int $profileId,
        ?int $fallbackProvinceId = null,
        ?int $fallbackDioceseId = null
    ): ?string {
        $this->db->transStart();

        if (! $this->affiliations->attachToParish($userId, $parishId, false, $status)) {
            $this->db->transRollback();
            return 'attach';
        }

        if ($isHome && ! $this->affiliations->setHomeParish($userId, $parishId)) {
            $this->db->transRollback();
            return 'home';
        }

        if (! $this->syncProfileParishMirror($profileId, $userId, $fallbackProvinceId, $fallbackDioceseId)) {
            $this->db->transRollback();
            return 'sync';
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            return 'transaction';
        }

        return null;
    }

    /**
     * Detach a user from a parish and re-mirror the profile. Runs in a
     * transaction.
     */
    public function detachFromParish(
        int $userId,
        int $parishId,
        int $profileId,
        ?int $fallbackProvinceId = null,
        ?int $fallbackDioceseId = null
    ): ?string {
        $this->db->transStart();

        if (! $this->affiliations->detachFromParish($userId, $parishId)) {
            $this->db->transRollback();
            return 'detach';
        }

        if (! $this->syncProfileParishMirror($profileId, $userId, $fallbackProvinceId, $fallbackDioceseId)) {
            $this->db->transRollback();
            return 'sync';
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            return 'transaction';
        }

        return null;
    }

    /**
     * Set the user's home parish and re-mirror the profile. Runs in a
     * transaction.
     */
    public function setHomeParish(
        int $userId,
        int $parishId,
        int $profileId,
        ?int $fallbackProvinceId = null,
        ?int $fallbackDioceseId = null
    ): ?string {
        $this->db->transStart();

        if (! $this->affiliations->setHomeParish($userId, $parishId)) {
            $this->db->transRollback();
            return 'home';
        }

        if (! $this->syncProfileParishMirror($profileId, $userId, $fallbackProvinceId, $fallbackDioceseId)) {
            $this->db->transRollback();
            return 'sync';
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            return 'transaction';
        }

        return null;
    }

    /**
     * Canonical replacement for ParishPeopleModel::replaceForUser —
     * reconcile a user's non-primary affiliations against a posted list.
     * Secondary rows not in the desired set are soft-deleted; each desired
     * parish is upserted via attachToParish (status + history handled there).
     *
     * @param array<int, array{parish_id: int, membership_status?: string}> $affiliations
     */
    public function replaceSecondaryAffiliationsForUser(int $userId, int $primaryParishId, array $affiliations, ?int $personId = null): void
    {
        $desiredParishIds = [$primaryParishId];
        $desired = [];
        foreach ($affiliations as $affiliation) {
            $parishId = (int) ($affiliation['parish_id'] ?? 0);
            if ($parishId <= 0 || $parishId === $primaryParishId) {
                continue;
            }
            $desiredParishIds[] = $parishId;
            $desired[$parishId] = (string) ($affiliation['membership_status'] ?? '');
        }

        // Soft-delete non-primary affiliations that are no longer desired.
        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_primary', 0)
            ->whereNotIn('parish_id', $desiredParishIds)
            ->where('deleted_at', null)
            ->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        foreach ($desired as $parishId => $rawStatus) {
            $status = MembershipStatusService::normalizeAffiliationStatus($rawStatus);
            if (! in_array($status, MembershipStatusService::affiliationStatuses(), true)) {
                $status = MembershipStatusService::profileToAffiliation($rawStatus);
            }
            if (! in_array($status, MembershipStatusService::affiliationStatuses(), true)) {
                $status = 'visitor';
            }

            $this->affiliations->attachToParish(
                $userId,
                $parishId,
                false,
                $status,
                $personId
            );
        }
    }

    /**
     * Canonical replacement for ParishPeopleModel::syncPrimaryParish —
     * ensure the user's primary parish affiliation exists. The affiliation
     * row (not the profile) owns membership_status, so an existing primary
     * affiliation is left untouched.
     */
    public function ensurePrimaryAffiliationForUser(int $userId, int $primaryParishId, ?int $personId = null): void
    {
        if ($userId <= 0 || $primaryParishId <= 0) {
            return;
        }

        $existing = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $primaryParishId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($existing) {
            if ((int) ($existing['is_primary'] ?? 0) !== 1) {
                $this->db->table('parish_affiliation')
                    ->where('linked_user_id', $userId)
                    ->where('is_primary', 1)
                    ->update(['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

                $this->db->table('parish_affiliation')
                    ->where('id', (int) $existing['id'])
                    ->update(['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            }

            return;
        }

        $this->affiliations->attachToParish($userId, $primaryParishId, true, 'regular_attender', $personId);

        // setHomeParish() requires is_member=1, so a freshly inserted
        // regular_attender row would never become primary. Mark it here.
        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_primary', 1)
            ->update(['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $primaryParishId)
            ->where('deleted_at', null)
            ->update(['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Mirror the user's primary active affiliation onto the legacy
     * profile row (parish_id / diocese_id / province_id).
     */
    public function syncProfileParishMirror(int $profileId, int $userId, ?int $fallbackProvinceId = null, ?int $fallbackDioceseId = null): bool
    {
        $affiliation = $this->db->table('parish_affiliation')
            ->select('parish_id')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->getFirstRow('array');

        $parishId = isset($affiliation['parish_id']) ? (int) $affiliation['parish_id'] : null;
        $dioceseId = isset($affiliation['diocese_id']) ? (int) $affiliation['diocese_id'] : null;
        $provinceId = $fallbackProvinceId;

        if ($parishId) {
            $parish = $this->parishes->findWithHierarchy($parishId);
            if ($parish) {
                $provinceId = isset($parish['province_id']) ? (int) $parish['province_id'] : $provinceId;
                $dioceseId = isset($parish['diocese_id']) ? (int) $parish['diocese_id'] : $dioceseId;
            }
        }

        if ($dioceseId === null) {
            $dioceseId = $fallbackDioceseId;
        }

        return $this->profiles->update($profileId, [
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'province_id' => $provinceId,
        ]);
    }

    /**
     * Parishes in a diocese the user is not yet affiliated with.
     *
     * @param list<int> $excludeParishIds
     * @return list<array<string, mixed>>
     */
    public function attachableParishes(int $dioceseId, array $excludeParishIds): array
    {
        $builder = $this->db->table('parish')
            ->select('id, name')
            ->where('diocese_id', $dioceseId)
            ->orderBy('name', 'ASC');

        if ($excludeParishIds !== []) {
            $builder->whereNotIn('id', $excludeParishIds);
        }

        return $builder->get()->getResultArray();
    }
}
