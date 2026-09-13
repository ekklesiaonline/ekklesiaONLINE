<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One-time cleanup: remove canonical person rows and user/person links that no
 * longer have any references.
 *
 * This migration is destructive by design because these rows are true orphans.
 * A database backup should be taken before running it in production.
 */
class CleanupOrphanedPersonRows extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('person')) {
            return;
        }

        if ($db->tableExists('user_person')) {
            $db->table('user_person')
                ->where('user_id NOT IN (SELECT id FROM ' . $db->prefixTable('users') . ')', null, false)
                ->orWhere('person_id NOT IN (SELECT id FROM ' . $db->prefixTable('person') . ')', null, false)
                ->delete();
        }

        $personTable      = $db->prefixTable('person');
        $userPersonTable  = $db->prefixTable('user_person');
        $profileTable     = $db->prefixTable('profile');
        $affiliationTable = $db->prefixTable('parish_affiliation');

        $leadershipClause = '';
        if ($db->tableExists('leadership_position') && $db->fieldExists('person_id', 'leadership_position')) {
            $leadershipTable = $db->prefixTable('leadership_position');
            $leadershipClause = "AND NOT EXISTS (SELECT 1 FROM {$leadershipTable} lp WHERE lp.person_id = p.id)";
        }

        $sql = <<<SQL
            SELECT p.id
            FROM {$personTable} p
            WHERE NOT EXISTS (SELECT 1 FROM {$userPersonTable} up WHERE up.person_id = p.id)
              AND NOT EXISTS (SELECT 1 FROM {$profileTable} pr WHERE pr.person_id = p.id AND pr.deleted_at IS NULL)
              AND NOT EXISTS (SELECT 1 FROM {$affiliationTable} pa WHERE pa.person_id = p.id AND pa.deleted_at IS NULL)
              {$leadershipClause}
        SQL;

        $orphanRows = $db->query($sql)->getResultArray();
        $orphanPersonIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $orphanRows
        ), static fn(int $id): bool => $id > 0));

        if ($orphanPersonIds !== []) {
            $childTables = [
                'person_visit',
                'person_membership_details',
                'person_onboarding',
                'person_status_history',
            ];

            foreach ($childTables as $table) {
                if (! $db->tableExists($table)) {
                    continue;
                }
                $db->table($table)
                    ->whereIn('person_id', $orphanPersonIds)
                    ->delete();
            }

            $db->table('person')
                ->whereIn('id', $orphanPersonIds)
                ->delete();
        }

        log_message(
            'info',
            'CleanupOrphanedPersonRows: removed {0} orphaned person rows.',
            [count($orphanPersonIds)]
        );
    }

    public function down()
    {
        // No-op: this migration only removes invalid orphan data.
    }
}
