<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveLegacyMembershipStatusValues extends Migration
{
    public function up()
    {
        $this->sanitizeProfileStatusValues();
        $this->sanitizeAffiliationStatusValues();
        $this->rebuildSnapshot();
    }

    public function down()
    {
        // No-op: the legacy values are not restored.
    }

    private function sanitizeProfileStatusValues(): void
    {
        $profileTable = $this->resolveExistingTable(['profile']);
        if ($profileTable === null) {
            return;
        }

        $sql = 'UPDATE ' . $profileTable . ' SET membership_status = "member" WHERE membership_status = "active"';
        $this->db->query($sql);

        $sql = 'UPDATE ' . $profileTable . ' SET membership_status = "visitor" WHERE membership_status = "pending"';
        $this->db->query($sql);
    }

    private function sanitizeAffiliationStatusValues(): void
    {
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);
        if ($affiliationTable === null) {
            return;
        }

        $sql = 'UPDATE ' . $affiliationTable . ' SET membership_status = "member" WHERE membership_status = "active"';
        $this->db->query($sql);

        $sql = 'UPDATE ' . $affiliationTable . ' SET membership_status = "visitor" WHERE membership_status = "pending"';
        $this->db->query($sql);
    }

    private function rebuildSnapshot(): void
    {
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
