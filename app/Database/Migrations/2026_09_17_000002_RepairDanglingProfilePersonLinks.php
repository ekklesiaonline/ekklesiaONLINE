<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repairs dangling profile.person_id links (T4).
 *
 * A small number of profiles can point at person rows that no longer exist
 * (e.g. hard-deleted by person merges before profile.person_id was included
 * in reassignments). Soft-deleted profiles are unlinked outright — the link
 * target is gone and the profile itself is deleted. Active profiles are left
 * for `php spark person:link-profiles`, which creates a fresh canonical
 * person via PersonProfileSyncService (encryption-aware).
 */
class RepairDanglingProfilePersonLinks extends Migration
{
    public function up()
    {
        $db = $this->db ?? db_connect();

        if (! $db->tableExists('profile') || ! $db->tableExists('person')) {
            return;
        }
        if (! $db->fieldExists('person_id', 'profile')) {
            return;
        }

        $profileTable = $db->prefixTable('profile');
        $personTable  = $db->prefixTable('person');

        // Null dangling links on soft-deleted profiles only. Active dangling
        // profiles are repaired by person:link-profiles so the new person row
        // inherits properly encrypted contact fields.
        $db->query(
            "UPDATE {$profileTable} p
             SET p.person_id = NULL
             WHERE p.person_id IS NOT NULL
               AND p.deleted_at IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM {$personTable} pe WHERE pe.id = p.person_id)"
        );
    }

    public function down()
    {
        // Irreversible: the dropped links pointed at person rows that no
        // longer exist, so there is nothing to restore.
    }
}
