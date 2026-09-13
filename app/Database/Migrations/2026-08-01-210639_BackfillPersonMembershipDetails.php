<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillPersonMembershipDetails extends Migration
{
    public function up()
    {
        $this->backfillPersonMembershipDetails();
    }

    public function down()
    {
        // No-op: backfilled data should be removed manually if needed.
    }

    private function backfillPersonMembershipDetails(): void
    {
        if (!$this->db->tableExists('person_membership_details')) {
            return;
        }

        $profileTable = $this->resolveExistingTable(['profile']);
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);
        $standingTable = $this->resolveExistingTable(['member_standing_summary']);
        $pledgeTable = $this->resolveExistingTable(['annual_pledge']);

        if ($profileTable === null || $affiliationTable === null) {
            return;
        }

        $year = (int) date('Y');

        // Determine the affiliation user column.
        $affiliationColumns = $this->db->getFieldNames($affiliationTable);
        $userColumn = in_array('linked_user_id', $affiliationColumns, true) ? 'linked_user_id' : 'user_id';
        $hasPersonId = in_array('person_id', $affiliationColumns, true);

        $personIdExpr = $hasPersonId
            ? 'mca.person_id'
            : 'p.id';

        $standingJoin = $standingTable !== null
            ? 'LEFT JOIN ' . $standingTable . ' mss ON mss.user_id = mca.' . $userColumn . ' AND mss.year = ' . $year
            : '';

        $pledgeJoin = $pledgeTable !== null
            ? 'LEFT JOIN ' . $pledgeTable . ' ap ON ap.user_id = mca.' . $userColumn . ' AND ap.pledge_year = ' . $year . ' AND (ap.fulfillment_status != "cancelled" OR ap.fulfillment_status IS NULL)'
            : '';

        $sql = 'INSERT INTO person_membership_details (';
        $sql .= 'person_id, parish_id, is_baptized, is_confirmed, is_communicant, is_dependent, has_pledge, pledge_status, good_standing_override, created_at, updated_at';
        $sql .= ') SELECT DISTINCT';
        $sql .= ' p.id,';
        $sql .= ' mca.parish_id,';
        $sql .= ' CASE WHEN p.baptism_date IS NOT NULL THEN 1 ELSE 0 END,';
        $sql .= ' CASE WHEN p.confirmation_date IS NOT NULL OR p.is_confirmed = 1 THEN 1 ELSE 0 END,';
        $sql .= ' CASE WHEN mss.communion_count >= 3 THEN 1 ELSE 0 END,';
        $sql .= ' COALESCE(p.is_dependent, 0),';
        $sql .= ' CASE WHEN ap.id IS NOT NULL THEN 1 ELSE 0 END,';
        $sql .= ' NULL,';
        $sql .= ' 0,';
        $sql .= ' NOW(),';
        $sql .= ' NOW()';
        $sql .= ' FROM ' . $affiliationTable . ' mca';
        $sql .= ' INNER JOIN ' . $profileTable . ' p ON p.user_id = mca.' . $userColumn;
        $sql .= ' ' . $standingJoin;
        $sql .= ' ' . $pledgeJoin;
        $sql .= ' WHERE mca.deleted_at IS NULL';
        $sql .= ' AND p.deleted_at IS NULL';
        $sql .= ' AND NOT EXISTS (';
        $sql .= '   SELECT 1 FROM person_membership_details pmd';
        $sql .= '   WHERE pmd.person_id = p.id AND pmd.parish_id = mca.parish_id';
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
