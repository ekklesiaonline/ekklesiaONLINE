<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillProfileProvinceIdFromProvinceAdminPrimary extends Migration
{
    public function up()
    {
        $this->backfillProfileProvinceId();
    }

    public function down()
    {
        // No-op: do not remove backfilled province assignments.
    }

    private function backfillProfileProvinceId(): void
    {
        $profileTable = $this->resolveExistingTable(['profile']);
        if ($profileTable === null) {
            return;
        }

        $papTable = $this->resolveExistingTable(['province_admin_primary']);
        if ($papTable === null) {
            return;
        }

        $sql = 'UPDATE ' . $profileTable . ' p';
        $sql .= ' INNER JOIN ' . $papTable . ' pap ON pap.user_id = p.user_id';
        $sql .= ' SET p.province_id = pap.province_id';
        $sql .= ' WHERE p.province_id IS NULL OR p.province_id = 0';

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
