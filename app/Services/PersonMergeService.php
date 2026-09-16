<?php

namespace App\Services;

use App\Models\PersonModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Merge two canonical person records into one.
 *
 * The source person is removed and all references to it are reassigned to the
 * target person. The service refuses to merge when there are ambiguous
 * conflicts (e.g., both persons have a profile/affiliation for the same parish
 * or both are linked to the same user) so an admin can resolve those first.
 */
class PersonMergeService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Find candidate duplicate person pairs within a parish.
     *
     * @return list<array{source: array, target: array, reason: string}>
     */
    public function findCandidates(int $parishId): array
    {
        if (! $this->db->tableExists('person') || ! $this->db->tableExists('parish_affiliation')) {
            return [];
        }

        $personTable = $this->db->prefixTable('person');
        $affiliationTable = $this->db->prefixTable('parish_affiliation');
        $rows = $this->db->query(<<<SQL
            SELECT p1.id AS p1_id,
                   p1.first_name AS p1_first_name,
                   p1.last_name AS p1_last_name,
                   p1.email_hash AS p1_email_hash,
                   p1.phone_hash AS p1_phone_hash,
                   p2.id AS p2_id,
                   p2.first_name AS p2_first_name,
                   p2.last_name AS p2_last_name,
                   p2.email_hash AS p2_email_hash,
                   p2.phone_hash AS p2_phone_hash
            FROM {$personTable} p1
            INNER JOIN {$affiliationTable} pa1
                ON pa1.person_id = p1.id AND pa1.parish_id = ?
            INNER JOIN {$personTable} p2
                ON p2.id <> p1.id
            INNER JOIN {$affiliationTable} pa2
                ON pa2.person_id = p2.id AND pa2.parish_id = ?
            WHERE p1.is_active = 1
              AND p2.is_active = 1
              AND (
                (p1.first_name <> '' AND p2.first_name <> ''
                 AND p1.last_name <> '' AND p2.last_name <> ''
                 AND p1.first_name = p2.first_name
                 AND p1.last_name = p2.last_name)
                OR (p1.email_hash IS NOT NULL AND p2.email_hash IS NOT NULL
                    AND p1.email_hash = p2.email_hash)
                OR (p1.phone_hash IS NOT NULL AND p2.phone_hash IS NOT NULL
                    AND p1.phone_hash = p2.phone_hash)
            )
            ORDER BY p1.id, p2.id
        SQL, [$parishId, $parishId])->getResultArray();

        $seen = [];
        $candidates = [];
        foreach ($rows as $row) {
            $idA = (int) $row['p1_id'];
            $idB = (int) $row['p2_id'];
            $key = implode(':', [min($idA, $idB), max($idA, $idB)]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $reasons = [];
            if ($row['p1_first_name'] === $row['p2_first_name']
                && $row['p1_last_name'] === $row['p2_last_name']) {
                $reasons[] = 'same name';
            }
            if ($row['p1_email_hash'] !== null
                && $row['p1_email_hash'] === $row['p2_email_hash']) {
                $reasons[] = 'same email';
            }
            if ($row['p1_phone_hash'] !== null
                && $row['p1_phone_hash'] === $row['p2_phone_hash']) {
                $reasons[] = 'same phone';
            }

            $candidates[] = [
                'source' => [
                    'id' => $idA,
                    'first_name' => $row['p1_first_name'],
                    'last_name' => $row['p1_last_name'],
                ],
                'target' => [
                    'id' => $idB,
                    'first_name' => $row['p2_first_name'],
                    'last_name' => $row['p2_last_name'],
                ],
                'reason' => implode(', ', $reasons),
            ];
        }

        return $candidates;
    }

    /**
     * Build a preview payload for a proposed merge.
     *
     * @return array{ok: bool, source?: array, target?: array, conflicts?: list<string>, message?: string}
     */
    public function getPreview(int $sourcePersonId, int $targetPersonId, int $parishId): array
    {
        if ($sourcePersonId === $targetPersonId) {
            return $this->result(false, 'Cannot merge a person into itself.');
        }

        $source = $this->loadPerson($sourcePersonId);
        $target = $this->loadPerson($targetPersonId);

        if ($source === null || $target === null) {
            return $this->result(false, 'One or both person records were not found.');
        }

        $conflicts = $this->detectConflicts($sourcePersonId, $targetPersonId, $parishId);

        return [
            'ok'        => $conflicts === [],
            'source'    => $source,
            'target'    => $target,
            'conflicts' => $conflicts,
            'message'   => $conflicts === []
                ? 'Merge can proceed.'
                : 'Resolve the conflicts before merging.',
        ];
    }

    /**
     * Execute the merge.
     *
     * @param array<string,mixed> $overrides Values to apply to the target person row.
     * @return array{ok: bool, message: string}
     */
    public function merge(
        int $sourcePersonId,
        int $targetPersonId,
        int $parishId,
        int $actorUserId,
        array $overrides = [],
        bool $syncSnapshot = true
    ): array {
        if ($sourcePersonId === $targetPersonId) {
            return $this->result(false, 'Cannot merge a person into itself.');
        }

        $source = $this->loadPerson($sourcePersonId);
        $target = $this->loadPerson($targetPersonId);
        if ($source === null || $target === null) {
            return $this->result(false, 'One or both person records were not found.');
        }

        $conflicts = $this->detectConflicts($sourcePersonId, $targetPersonId, $parishId);
        if ($conflicts !== []) {
            return $this->result(false, 'Merge blocked by conflicts: ' . implode('; ', $conflicts));
        }

        $this->db->transException(true);
        $this->db->transStart();
        // Clear any stale failure flag left by earlier queries during long test runs.
        $this->db->resetTransStatus();

        try {
            $this->reassignPersonReferences($sourcePersonId, $targetPersonId, $parishId);
            $this->applyTargetOverrides($targetPersonId, $overrides);

            // Use a direct builder delete to guarantee a hard delete even if the
            // model is configured for soft deletes. If the row cannot be removed
            // (for example, because of a foreign-key reference we did not reassign),
            // fall back to deactivating it so the duplicate is no longer active.
            $this->db->table('person')->delete(['id' => $sourcePersonId]);

            $stillThere = $this->db->table('person')->where('id', $sourcePersonId)->countAllResults();
            if ($stillThere > 0 && $this->db->fieldExists('is_active', 'person')) {
                $this->db->table('person')->where('id', $sourcePersonId)->update(['is_active' => 0]);
            }

            // Defensive: clean up any remaining child rows tied to the removed person.
            $cleanup = new PersonCleanupService($this->db);
            $cleanup->deleteIfOrphaned($sourcePersonId);

            if (! $this->db->transComplete()) {
                throw new \RuntimeException('Transaction failed to complete during person merge.');
            }

            if ($syncSnapshot) {
                (new ParishPeopleRegistryService())->syncParish($parishId);
            }

            $this->logAudit($sourcePersonId, $targetPersonId, $parishId, $actorUserId);

            return $this->result(true, 'Person records merged successfully.');
        } catch (\Throwable $e) {
            $lastQuery = (string) $this->db->getLastQuery();
            $this->db->transRollback();
            log_message('error', 'PersonMergeService::merge failed: ' . $e->getMessage() . ' | last query: ' . $lastQuery);
            return $this->result(false, 'Merge failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadPerson(int $personId): ?array
    {
        return model(PersonModel::class)->find($personId) ?: null;
    }

    /**
     * @return list<string>
     */
    private function detectConflicts(int $sourcePersonId, int $targetPersonId, int $parishId): array
    {
        $conflicts = [];

        // Same user linked to both persons.
        if ($this->db->tableExists('user_person')
            && $this->db->fieldExists('person_id', 'user_person')
            && $this->db->fieldExists('user_id', 'user_person')) {
            $userPersonTable = $this->db->prefixTable('user_person');
            $commonUsers = $this->db->query(
                "SELECT up1.user_id FROM {$userPersonTable} up1 INNER JOIN {$userPersonTable} up2 ON up2.user_id = up1.user_id WHERE up1.person_id = ? AND up2.person_id = ?",
                [$sourcePersonId, $targetPersonId]
            )->getResultArray();
            foreach ($commonUsers as $row) {
                $conflicts[] = 'Both persons are linked to user #' . $row['user_id'];
            }
        }

        // Both persons have a profile in the same parish.
        if ($this->db->tableExists('profile')
            && $this->db->fieldExists('person_id', 'profile')
            && $this->db->fieldExists('user_id', 'profile')
            && $this->db->fieldExists('parish_id', 'profile')) {
            $profileTable = $this->db->prefixTable('profile');
            $commonProfiles = $this->db->query(
                "SELECT pr1.parish_id FROM {$profileTable} pr1 INNER JOIN {$profileTable} pr2 ON pr2.user_id = pr1.user_id AND pr2.parish_id = pr1.parish_id WHERE pr1.person_id = ? AND pr2.person_id = ?",
                [$sourcePersonId, $targetPersonId]
            )->getResultArray();
            foreach ($commonProfiles as $row) {
                $conflicts[] = 'Both persons have a profile in parish #' . $row['parish_id'];
            }
        }

        // Child records keyed by (person_id, parish_id) would duplicate after merge.
        $childTables = [
            'person_membership_details' => 'parish_id',
            'person_onboarding' => 'parish_id',
        ];
        foreach ($childTables as $table => $parishColumn) {
            if (! $this->db->tableExists($table)
                || ! $this->db->fieldExists('person_id', $table)
                || ! $this->db->fieldExists($parishColumn, $table)) {
                continue;
            }
            $tableName = $this->db->prefixTable($table);
            $common = $this->db->query(
                "SELECT c1.{$parishColumn} AS parish_id FROM {$tableName} c1 INNER JOIN {$tableName} c2 ON c2.{$parishColumn} = c1.{$parishColumn} WHERE c1.person_id = ? AND c2.person_id = ?",
                [$sourcePersonId, $targetPersonId]
            )->getResultArray();
            foreach ($common as $row) {
                $conflicts[] = "Both persons have a record in {$table} for parish #" . $row['parish_id'];
            }
        }

        return $conflicts;
    }

    private function reassignPersonReferences(int $sourcePersonId, int $targetPersonId, int $parishId): void
    {
        $tables = [
            ['profile', 'person_id'],
            ['parish_affiliation', 'person_id'],
            ['user_person', 'person_id'],
            ['leadership_position', 'person_id'],
            ['person_visit', 'person_id'],
            ['person_membership_details', 'person_id'],
            ['person_onboarding', 'person_id'],
            ['person_status_history', 'person_id'],
            ['guest', 'person_id'],
            ['parish_people', 'person_id'],
        ];

        foreach ($tables as [$table, $column]) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists($column, $table)) {
                continue;
            }

            // user_person has a unique key on (user_id, person_id) in many
            // installations; avoid duplicate-key errors by deleting rows that
            // would collide.
            if ($table === 'user_person') {
                $userPersonTable = $this->db->prefixTable('user_person');
                $duplicates = $this->db->query(
                    "SELECT up1.user_id FROM {$userPersonTable} up1 INNER JOIN {$userPersonTable} up2 ON up2.user_id = up1.user_id AND up2.person_id = ? WHERE up1.person_id = ?",
                    [$targetPersonId, $sourcePersonId]
                )->getResultArray();

                foreach ($duplicates as $row) {
                    $deleted = $this->db->table('user_person')
                        ->where('user_id', (int) $row['user_id'])
                        ->where('person_id', $sourcePersonId)
                        ->delete();
                    if ($deleted === false) {
                        throw new \RuntimeException("Failed to delete colliding user_person row for user {$row['user_id']} during merge.");
                    }
                }
            }

            $updated = $this->db->table($table)
                ->where($column, $sourcePersonId)
                ->update([$column => $targetPersonId]);
            if ($updated === false) {
                throw new \RuntimeException("Failed to reassign {$table}.{$column} from person {$sourcePersonId} to {$targetPersonId}.");
            }
        }
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function applyTargetOverrides(int $targetPersonId, array $overrides): void
    {
        $allowed = ['first_name', 'last_name', 'email', 'phone', 'address', 'birthdate', 'gender'];
        $payload = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $overrides)) {
                $value = $overrides[$field];
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        if ($payload === []) {
            return;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $ok = model(PersonModel::class)->update($targetPersonId, $payload);
        if ($ok === false) {
            throw new \RuntimeException('Failed to apply target person overrides during merge.');
        }
    }

    private function logAudit(int $sourcePersonId, int $targetPersonId, int $parishId, int $actorUserId): void
    {
        $audit = service('audit');
        if (! is_object($audit) || ! method_exists($audit, 'log')) {
            return;
        }

        $audit->log('person.merge', [
            'parish_id' => $parishId,
            'entity_type' => 'person',
            'entity_id' => $targetPersonId,
            'old_values' => ['source_person_id' => $sourcePersonId],
            'new_values' => ['target_person_id' => $targetPersonId],
            'meta' => [
                'actor_user_id' => $actorUserId,
            ],
        ]);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function result(bool $ok, string $message): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
        ];
    }
}
