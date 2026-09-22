<?php

namespace App\Services;

use App\Models\ParishModel;
use CodeIgniter\Database\BaseConnection;

/**
 * ParishDeletionService
 *
 * Domain logic for parish deletion: transactional delete with optional
 * purge of nullable-reference rows, plus delete-impact counts for the
 * confirmation UI. Authorization and audit stay in the controller.
 */
class ParishDeletionService
{
    private BaseConnection $db;
    private ParishModel $parishes;

    public function __construct(?BaseConnection $db = null, ?ParishModel $parishes = null)
    {
        $this->db = $db ?? db_connect();
        $this->parishes = $parishes ?? model(ParishModel::class);
    }

    /**
     * Transactionally delete a parish, optionally purging rows that hold
     * only a nullable parish reference (role audit log, profile rows).
     */
    public function deleteParish(int $parishId, bool $purgeNullableRefs, ?string $profileTable): bool
    {
        $this->db->transBegin();

        try {
            if ($purgeNullableRefs) {
                $this->db->table('parish_role_audit_log')->where('parish_id', $parishId)->delete();
                if ($profileTable !== null) {
                    $this->db->table($profileTable)->where('parish_id', $parishId)->delete();
                }
            }

            if (! $this->parishes->delete($parishId) || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Parish delete failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Counts of rows that reference a parish, for the delete-impact UI.
     *
     * @return array<string, int>
     */
    public function impactCounts(int $parishId, ?string $profileTable): array
    {
        $profileCount = 0;
        if ($profileTable !== null) {
            $profileCount = (int) $this->db->table($profileTable)
                ->where('parish_id', $parishId)
                ->countAllResults();
        }

        return [
            'members' => (int) $this->db->table('parish_affiliation')
                ->where('parish_id', $parishId)
                ->where('is_member', 1)
                ->countAllResults(),
            'profile_unscoped' => $profileCount,
            'parish_role_audit_logs_unscoped' => (int) $this->db->table('parish_role_audit_log')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'donations' => (int) $this->db->table('donation')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'services' => (int) $this->db->table('service')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'attendance' => (int) $this->db->table('attendance a')
                ->join('service s', 's.id = a.service_id', 'inner')
                ->where('s.parish_id', $parishId)
                ->countAllResults(),
            'events' => (int) $this->db->table('event')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'event_registrations' => (int) $this->db->table('event_registration')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'visitors' => (int) $this->db->table('parish_affiliation')
                ->where('parish_id', $parishId)
                ->where('membership_status', 'visitor')
                ->countAllResults(),
            'expenses' => (int) $this->db->table('expense')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'families' => (int) $this->db->table('family')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'volunteer_teams' => (int) $this->db->table('team')
                ->where('parish_id', $parishId)
                ->countAllResults(),
        ];
    }
}
