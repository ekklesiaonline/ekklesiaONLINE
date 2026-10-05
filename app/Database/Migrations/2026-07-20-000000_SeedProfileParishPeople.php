<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedProfileParishPeople extends Migration
{
    public function up()
    {
        $db = db_connect();
        $profileTable = $db->DBPrefix . 'profile';
        $parishTable  = $db->DBPrefix . 'parish_people';

        if (! $db->tableExists($parishTable)) {
            return; // legacy table retired; nothing to seed on fresh installs
        }

        $sql = "
            INSERT INTO {$parishTable}
                (parish_id, user_id, profile_id, first_name, last_name, membership_status, is_member, source, synced_at, created_at, updated_at)
            SELECT
                p.parish_id,
                p.user_id,
                p.id,
                p.first_name,
                p.last_name,
                p.membership_status,
                CASE WHEN p.membership_status = 'active' THEN 1 ELSE 0 END,
                'profile_migration',
                NOW(),
                NOW(),
                NOW()
            FROM {$profileTable} p
            WHERE p.parish_id IS NOT NULL AND p.parish_id > 0
            ON DUPLICATE KEY UPDATE
                membership_status = VALUES(membership_status),
                is_member = VALUES(is_member),
                synced_at = NOW(),
                updated_at = NOW()
        ";

        $db->query($sql);
    }

    public function down()
    {
        $db = db_connect();
        $parishTable = $db->DBPrefix . 'parish_people';

        $db->query("DELETE FROM {$parishTable} WHERE source = 'profile_migration'");
    }
}
