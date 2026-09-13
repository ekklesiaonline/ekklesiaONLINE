<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlignLeadershipPositionToPersonId extends Migration
{
    public function up()
    {
        $this->addPersonIdToLeadershipPosition();
        $this->backfillLeadershipPositionPersonId();
        $this->rebuildSnapshot();
    }

    public function down()
    {
        $this->dropPersonIdFromLeadershipPosition();
    }

    private function addPersonIdToLeadershipPosition(): void
    {
        if ($this->db->fieldExists('person_id', 'leadership_position')) {
            return;
        }

        $this->forge->addColumn('leadership_position', [
            'person_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'user_id',
            ],
        ]);

        $this->db->query('ALTER TABLE leadership_position ADD INDEX person_id (person_id)');
    }

    private function dropPersonIdFromLeadershipPosition(): void
    {
        if ($this->db->fieldExists('person_id', 'leadership_position')) {
            $this->forge->dropColumn('leadership_position', 'person_id');
        }
    }

    private function backfillLeadershipPositionPersonId(): void
    {
        if (!$this->db->fieldExists('person_id', 'leadership_position')) {
            return;
        }

        $profileTable = $this->resolveExistingTable(['profile']);
        if ($profileTable === null) {
            return;
        }

        $sql = 'UPDATE leadership_position lp';
        $sql .= ' INNER JOIN ' . $profileTable . ' p ON p.user_id = lp.user_id';
        $sql .= ' SET lp.person_id = p.id';
        $sql .= ' WHERE lp.person_id IS NULL';

        $this->db->query($sql);
    }

    private function rebuildSnapshot(): void
    {
        if (!$this->db->tableExists('parish_people') && !$this->db->tableExists('church_people')) {
            return;
        }

        $service = new \App\Services\ParishPeopleRegistryService();
        $service->syncAll();
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
