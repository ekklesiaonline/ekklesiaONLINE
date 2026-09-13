<?php

namespace App\Services;

use App\Models\PersonModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Safely clean up canonical person rows and user/person links when a user or
 * profile is removed.
 *
 * The service never deletes a person that is still referenced by another user,
 * profile, parish affiliation, or leadership position.
 */
class PersonCleanupService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Clean up user_person links for a deleted user and remove any persons that
     * become orphaned as a result.
     *
     * This should be called inside the same transaction as the user/profile delete.
     *
     * @param int $userId The user_id that is being removed.
     */
    public function cleanupForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (! $this->db->tableExists('user_person')) {
            return;
        }

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

        if ($this->db->tableExists('user_person')) {
            $this->db->table('user_person')
                ->where('user_id', $userId)
                ->delete();
        }

        foreach (array_keys($personIds) as $personId) {
            $this->deleteIfOrphaned($personId);
        }
    }

    /**
     * Mark a person inactive if there are no active profiles linked to it.
     *
     * Use this for soft deletes where the canonical row should remain but be
     * flagged as inactive.
     *
     * @param int $personId
     */
    public function deactivateIfNoActiveProfiles(int $personId): void
    {
        if ($personId <= 0 || ! $this->db->tableExists('person')) {
            return;
        }

        $hasActiveProfile = $this->db->tableExists('profile')
            && $this->db->table('profile')
                ->where('person_id', $personId)
                ->where('deleted_at', null)
                ->countAllResults() > 0;

        if ($hasActiveProfile) {
            return;
        }

        if (! $this->db->fieldExists('is_active', 'person')) {
            return;
        }

        $this->db->table('person')
            ->where('id', $personId)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Hard-delete a person row only when no meaningful references remain.
     *
     * @param int $personId
     * @return bool True if the person was deleted, false if it still has references.
     */
    public function deleteIfOrphaned(int $personId): bool
    {
        if ($personId <= 0 || ! $this->db->tableExists('person')) {
            return false;
        }

        if ($this->hasActiveReferences($personId)) {
            return false;
        }

        $this->deleteChildRows($personId);

        $personModel = model(PersonModel::class);
        $personModel->delete($personId, true);

        return true;
    }

    /**
     * Determine whether the person is still referenced by other records.
     *
     * @param int $personId
     */
    private function hasActiveReferences(int $personId): bool
    {
        if ($this->db->tableExists('user_person')) {
            $count = $this->db->table('user_person')
                ->where('person_id', $personId)
                ->countAllResults();
            if ($count > 0) {
                return true;
            }
        }

        if ($this->db->tableExists('profile')) {
            $count = $this->db->table('profile')
                ->where('person_id', $personId)
                ->where('deleted_at', null)
                ->countAllResults();
            if ($count > 0) {
                return true;
            }
        }

        if ($this->db->tableExists('parish_affiliation')) {
            $count = $this->db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('deleted_at', null)
                ->countAllResults();
            if ($count > 0) {
                return true;
            }
        }

        if ($this->db->tableExists('leadership_position')) {
            $count = $this->db->table('leadership_position')
                ->where('person_id', $personId)
                ->countAllResults();
            if ($count > 0) {
                return true;
            }
        }

        if ($this->db->tableExists('parish_people')) {
            $count = $this->db->table('parish_people')
                ->where('person_id', $personId)
                ->where('deleted_at', null)
                ->countAllResults();
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete child rows that are keyed strictly by person_id.
     *
     * @param int $personId
     */
    private function deleteChildRows(int $personId): void
    {
        $childTables = [
            'person_visit',
            'person_membership_details',
            'person_onboarding',
            'person_status_history',
        ];

        foreach ($childTables as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $this->db->table($table)
                ->where('person_id', $personId)
                ->delete();
        }
    }
}
