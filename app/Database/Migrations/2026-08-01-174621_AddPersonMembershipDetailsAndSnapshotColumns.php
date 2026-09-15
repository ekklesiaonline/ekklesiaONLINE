<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonMembershipDetailsAndSnapshotColumns extends Migration
{
    public function up()
    {
        $this->createPersonMembershipDetailsTable();
        $this->addSnapshotColumns();
        $this->backfillPersonMembershipDetails();

        $service = new \App\Services\ParishPeopleRegistryService();
        $service->syncAll();
    }

    public function down()
    {
        $this->dropSnapshotColumns();
        $this->dropPersonMembershipDetailsTable();

        $service = new \App\Services\ParishPeopleRegistryService();
        $service->syncAll();
    }

    private function createPersonMembershipDetailsTable(): void
    {
        if ($this->db->tableExists('person_membership_details')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'person_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'parish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'is_baptized' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_confirmed' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_communicant' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_dependent' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'has_pledge' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'pledge_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'good_standing_override' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->addKey('parish_id');
        $this->forge->createTable('person_membership_details');
    }

    private function dropPersonMembershipDetailsTable(): void
    {
        if ($this->db->tableExists('person_membership_details')) {
            $this->forge->dropTable('person_membership_details');
        }
    }

    private function addSnapshotColumns(): void
    {
        $columns = [
            'is_baptized' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_confirmed' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_communicant' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_dependent' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'is_under_16' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'has_pledge' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
        ];

        foreach (['parish_people', 'parish_people'] as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $definition) {
                if (!$this->db->fieldExists($column, $table)) {
                    $this->forge->addColumn($table, [$column => $definition]);
                }
            }
        }
    }

    private function dropSnapshotColumns(): void
    {
        $columns = ['has_pledge', 'is_under_16', 'is_dependent', 'is_communicant', 'is_confirmed', 'is_baptized'];

        foreach (['parish_people', 'parish_people'] as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if ($this->db->fieldExists($column, $table)) {
                    $this->forge->dropColumn($table, $column);
                }
            }
        }
    }

    private function backfillPersonMembershipDetails(): void
    {
        if (!$this->db->tableExists('person_membership_details')) {
            return;
        }

        $this->db->query('TRUNCATE TABLE person_membership_details');

        $profileTable = $this->resolveExistingTable(['profile']);
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);
        $hasMss = $this->db->tableExists('member_standing_summary');
        $hasPledge = $this->db->tableExists('annual_pledge');
        $year = (int) date('Y');

        if ($profileTable === null || $affiliationTable === null) {
            return;
        }

        $sql = 'INSERT INTO person_membership_details (person_id, parish_id, is_baptized, is_confirmed, is_communicant, is_dependent, has_pledge, pledge_status, good_standing_override, created_at, updated_at)';
        $sql .= ' SELECT p.id, mca.parish_id,';
        $sql .= ' CASE WHEN p.baptism_date IS NOT NULL THEN 1 ELSE 0 END,';
        $sql .= ' CASE WHEN p.confirmation_date IS NOT NULL OR p.is_confirmed = 1 THEN 1 ELSE 0 END,';
        $sql .= ' COALESCE(mss.communion_count >= 3, 0),';
        $sql .= ' COALESCE(p.is_dependent, 0),';

        if ($hasPledge) {
            $sql .= ' CASE WHEN ap.id IS NOT NULL THEN 1 ELSE 0 END,';
            $sql .= ' ap.fulfillment_status,';
        } else {
            $sql .= ' 0,';
            $sql .= ' NULL,';
        }

        $sql .= ' 0,';
        $sql .= ' NOW(), NOW()';
        $sql .= ' FROM ' . $affiliationTable . ' mca';
        $sql .= ' INNER JOIN ' . $profileTable . ' p ON p.user_id = mca.linked_user_id';
        $sql .= ' INNER JOIN users u ON u.id = mca.linked_user_id';

        if ($hasMss) {
            $sql .= ' LEFT JOIN member_standing_summary mss ON mss.user_id = mca.linked_user_id AND mss.year = ' . $year;
        }

        if ($hasPledge) {
            $sql .= ' LEFT JOIN annual_pledge ap ON ap.user_id = mca.linked_user_id AND ap.pledge_year = ' . $year . ' AND (ap.fulfillment_status != "cancelled" OR ap.fulfillment_status IS NULL)';
        }

        $sql .= ' WHERE mca.deleted_at IS NULL AND u.active = 1';
        $sql .= ' AND mca.id = (';
        $sql .= '   SELECT sub_mca.id FROM ' . $affiliationTable . ' sub_mca';
        $sql .= '   WHERE sub_mca.parish_id = mca.parish_id AND sub_mca.linked_user_id = mca.linked_user_id';
        $sql .= '   ORDER BY COALESCE(sub_mca.is_primary, 0) DESC, sub_mca.id DESC';
        $sql .= '   LIMIT 1';
        $sql .= ' )';

        $this->db->query($sql);
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
