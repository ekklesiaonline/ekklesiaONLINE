<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enforces at most one roster row per (team_id, user_id) on team_member.
 *
 * Volunteers::addMember previously performed no membership check and the
 * schema keyed (team_id, user_id) as a plain index, so the same user could
 * be inserted repeatedly. The controller now rejects duplicates up front;
 * this unique key is the hard backstop.
 *
 * Pre-existing duplicates are reconciled first by keeping the oldest row.
 * The team_id foreign key is dropped and re-added because MySQL refuses to
 * drop the composite index while it backs the FK.
 */
class AddTeamMemberUniqueIndex extends Migration
{
    private const INDEX_NAME = 'team_id_user_id';
    private const FK_NAME    = 'team_members_team_id_foreign';

    public function up()
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('team_member')) {
            return;
        }

        $index = $this->db->getIndexData('team_member')[self::INDEX_NAME] ?? null;
        $isUnique = $index !== null && strtoupper((string) ($index->type ?? '')) === 'UNIQUE';
        $fkExists = isset($this->db->getForeignKeyData('team_member')[self::FK_NAME]);

        if ($isUnique && $fkExists) {
            return; // already migrated
        }

        $table = $this->db->protectIdentifiers('team_member', true);

        if (! $isUnique) {
            // Keep the earliest row when (team_id, user_id) duplicates exist.
            $this->db->query(
                'DELETE t1 FROM ' . $table . ' t1
                 INNER JOIN ' . $table . ' t2
                     ON t1.team_id = t2.team_id
                    AND t1.user_id = t2.user_id
                    AND t1.id > t2.id'
            );
        }

        if ($fkExists) {
            $this->forge->dropForeignKey('team_member', self::FK_NAME);
        }

        if (! $isUnique && $index !== null) {
            $this->forge->dropKey('team_member', self::INDEX_NAME);
            $this->forge->addKey(['team_id', 'user_id'], false, true, self::INDEX_NAME);
            $this->forge->processIndexes('team_member');
        } elseif ($index === null) {
            $this->forge->addKey(['team_id', 'user_id'], false, true, self::INDEX_NAME);
            $this->forge->processIndexes('team_member');
        }

        $this->forge->addForeignKey('team_id', 'team', 'id', 'CASCADE', 'CASCADE', self::FK_NAME);
        $this->forge->processIndexes('team_member');
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('team_member')) {
            return;
        }

        $index = $this->db->getIndexData('team_member')[self::INDEX_NAME] ?? null;
        if ($index === null || strtoupper((string) ($index->type ?? '')) !== 'UNIQUE') {
            return; // already non-unique or absent
        }

        if (isset($this->db->getForeignKeyData('team_member')[self::FK_NAME])) {
            $this->forge->dropForeignKey('team_member', self::FK_NAME);
        }

        $this->forge->dropKey('team_member', self::INDEX_NAME);
        $this->forge->addKey(['team_id', 'user_id'], false, false, self::INDEX_NAME);
        $this->forge->processIndexes('team_member');
        $this->forge->addForeignKey('team_id', 'team', 'id', 'CASCADE', 'CASCADE', self::FK_NAME);
        $this->forge->processIndexes('team_member');
    }
}
