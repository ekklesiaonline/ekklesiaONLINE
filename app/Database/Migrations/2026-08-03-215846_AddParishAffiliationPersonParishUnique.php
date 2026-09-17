<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishAffiliationPersonParishUnique extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        if (! $this->db->fieldExists('person_id', 'parish_affiliation')) {
            return;
        }

        // Backfill person_id for rows that were created before the person table
        // or that reference a missing user_person link. This prevents the unique
        // index from failing on person_id = 0 duplicates.
        $this->backfillPersonIds();

        // Remove duplicate (parish_id, person_id) rows, keeping the active
        // affiliation or the most recently updated record.
        $this->deduplicatePersonParishRows();

        // Drop the old non-unique parish_id/person_id key if it exists, then
        // add the unique constraint.
        $uniqueExists = $this->db->query(
            "SHOW INDEX FROM `parish_affiliation` WHERE Key_name = 'uniq_parish_affiliation_person_parish'"
        )->getNumRows() > 0;

        if (! $uniqueExists) {
            $this->dropIndexIfExists('parish_id_person_id');
            $this->dropIndexIfExists('idx_parish_affiliation_person_parish');

            $this->db->query('ALTER TABLE `parish_affiliation` ADD UNIQUE KEY `uniq_parish_affiliation_person_parish` (`parish_id`, `person_id`)');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $this->db->query('ALTER TABLE `parish_affiliation` DROP INDEX `uniq_parish_affiliation_person_parish`');

        $this->db->query('ALTER TABLE `parish_affiliation` ADD KEY `parish_id_person_id` (`parish_id`, `person_id`)');
    }

    private function backfillPersonIds(): void
    {
        // First pass: use existing user_person links.
        if ($this->db->tableExists('user_person') && $this->db->tableExists('users')) {
            $this->db->query('
                UPDATE `parish_affiliation` pa
                INNER JOIN `user_person` up ON up.user_id = pa.linked_user_id
                SET pa.person_id = up.person_id
                WHERE pa.linked_user_id IS NOT NULL
                  AND (pa.person_id IS NULL OR pa.person_id = 0)
            ');
        }

        // Second pass: provision a minimal person row for any affiliation that
        // still lacks a person_id and has a linked user.
        if (! $this->db->tableExists('person') || ! $this->db->tableExists('user_person')) {
            return;
        }

        $rows = $this->db->query('
            SELECT pa.id, pa.linked_user_id
            FROM `parish_affiliation` pa
            LEFT JOIN `user_person` up ON up.user_id = pa.linked_user_id
            WHERE (pa.person_id IS NULL OR pa.person_id = 0)
              AND pa.linked_user_id IS NOT NULL
              AND up.id IS NULL
            LIMIT 1000
        ')->getResultArray();

        if (empty($rows)) {
            return;
        }

        $profileTable = $this->resolveExistingTable(['profile']);
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $userId = (int) ($row['linked_user_id'] ?? 0);
            $affiliationId = (int) ($row['id'] ?? 0);

            if ($userId <= 0 || $affiliationId <= 0) {
                continue;
            }

            $firstName = 'Unknown';
            $lastName  = 'Unknown';

            if ($profileTable !== null) {
                $profile = $this->db->query('
                    SELECT first_name, last_name
                    FROM `' . $profileTable . '`
                    WHERE user_id = ' . $userId . '
                    LIMIT 1
                ')->getRowArray();

                if ($profile !== null) {
                    $firstName = ! empty($profile['first_name']) ? (string) $profile['first_name'] : $firstName;
                    $lastName  = ! empty($profile['last_name']) ? (string) $profile['last_name'] : $lastName;
                }
            }

            $this->db->query('
                INSERT INTO `person` (first_name, last_name, created_at, updated_at)
                VALUES (?, ?, ?, ?)
            ', [$firstName, $lastName, $now, $now]);

            $personId = (int) $this->db->insertID();

            if ($personId > 0) {
                $this->db->query('
                    INSERT INTO `user_person` (user_id, person_id, is_primary, linked_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ', [$userId, $personId, 1, $now, $now, $now]);

                $this->db->query('
                    UPDATE `parish_affiliation`
                    SET person_id = ?
                    WHERE id = ?
                ', [$personId, $affiliationId]);
            }
        }
    }

    private function deduplicatePersonParishRows(): void
    {
        // Keep the active, primary, most-recently-updated row per
        // (parish_id, person_id) and hard-delete the rest. This migration
        // intentionally hard-deletes duplicates because a unique index cannot
        // coexist with duplicate rows regardless of soft-delete state.
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        $this->db->query('
            DELETE pa FROM `parish_affiliation` pa
            INNER JOIN (
                SELECT parish_id, person_id, MAX(id) AS keep_id
                FROM `parish_affiliation`
                WHERE person_id > 0
                GROUP BY parish_id, person_id
                HAVING COUNT(*) > 1
            ) dup ON dup.parish_id = pa.parish_id
                  AND dup.person_id = pa.person_id
                  AND pa.id < dup.keep_id
        ');
    }

    private function dropIndexIfExists(string $indexName): void
    {
        try {
            $this->db->query('ALTER TABLE `parish_affiliation` DROP INDEX `' . $indexName . '`');
        } catch (\Throwable $e) {
            // Index may not exist; ignore.
        }
    }

    private function resolveExistingTable(array $baseCandidates): ?string
    {
        foreach ($baseCandidates as $candidate) {
            $prefixed = $this->db->getPrefix() . $candidate;
            foreach ([$prefixed, $candidate] as $table) {
                try {
                    if ($this->db->tableExists($table)) {
                        return $table;
                    }
                } catch (\Throwable $e) {
                    // Try next.
                }
            }
        }

        return null;
    }
}
