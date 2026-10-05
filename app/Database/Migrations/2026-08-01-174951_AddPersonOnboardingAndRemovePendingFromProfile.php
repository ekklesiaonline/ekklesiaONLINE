<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonOnboardingAndRemovePendingFromProfile extends Migration
{
    public function up()
    {
        $this->createPersonOnboardingTable();
        if ($this->db->fieldExists('membership_status', 'profile')) {
            $this->backfillOnboardingFromProfile();
            $this->removePendingFromProfile();
        }
    }

    public function down()
    {
        $this->restorePendingToProfile();
        $this->dropPersonOnboardingTable();
    }

    private function createPersonOnboardingTable(): void
    {
        if ($this->db->tableExists('person_onboarding')) {
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
            'onboarding_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
                'default'    => 'pending',
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->createTable('person_onboarding');
    }

    private function dropPersonOnboardingTable(): void
    {
        if ($this->db->tableExists('person_onboarding')) {
            $this->forge->dropTable('person_onboarding');
        }
    }

    private function backfillOnboardingFromProfile(): void
    {
        if (!$this->db->tableExists('person_onboarding')) {
            return;
        }

        $profileTable = $this->resolveExistingTable(['profile']);
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);

        if ($profileTable === null) {
            return;
        }

        $sql = 'INSERT INTO person_onboarding (person_id, parish_id, onboarding_status, started_at, created_at, updated_at)';
        $sql .= ' SELECT p.id,';
        $sql .= ' COALESCE(pa.parish_id, p.parish_id) AS parish_id,';
        $sql .= ' "pending",';
        $sql .= ' p.created_at,';
        $sql .= ' NOW(),';
        $sql .= ' NOW()';
        $sql .= ' FROM ' . $profileTable . ' p';

        if ($affiliationTable !== null) {
            $sql .= ' LEFT JOIN ' . $affiliationTable . ' pa ON pa.linked_user_id = p.user_id AND COALESCE(pa.is_primary, 0) = 1';
        }

        $sql .= ' WHERE p.membership_status = "pending"';

        $this->db->query($sql);
    }

    private function removePendingFromProfile(): void
    {
        $profileTable = $this->resolveExistingTable(['profile']);
        if ($profileTable === null) {
            return;
        }

        // Move pending profiles to a valid affiliation status before narrowing the enum.
        $this->db->query('UPDATE ' . $profileTable . ' SET membership_status = "visitor" WHERE membership_status = "pending"');

        $this->db->query('ALTER TABLE ' . $profileTable . ' MODIFY COLUMN membership_status ENUM("visitor","regular_attender","member","non_active","inactive","transferred","deceased") NOT NULL DEFAULT "visitor"');
    }

    private function restorePendingToProfile(): void
    {
        $profileTable = $this->resolveExistingTable(['profile']);
        if ($profileTable === null) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $profileTable . ' MODIFY COLUMN membership_status ENUM("pending","visitor","regular_attender","member","non_active","inactive","transferred","deceased") NOT NULL DEFAULT "visitor"');
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
