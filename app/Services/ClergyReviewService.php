<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClergyCanonicalStatusModel;
use App\Models\ClergyOrdinationModel;
use CodeIgniter\Database\BaseConnection;

/** Review is restricted to the ordinary or an explicitly delegated reviewer. */
final class ClergyReviewService
{
    private BaseConnection $db;
    private ClergyAuthorizationService $authorization;
    private LeadershipPositionService $positions;

    private const OFFICE_PERMISSIONS = [
        'diocesan_bishop' => [
            'governance.diocese.approve', 'clergy.appointment.manage', 'clergy.ordination.verify',
        ],
        'rector' => ['pastoral.parish.view', 'pastoral.parish.manage', 'governance.report.finalize_clergy'],
        'priest_in_charge' => ['pastoral.parish.view', 'pastoral.parish.manage', 'governance.report.finalize_clergy'],
        'interim_rector' => ['pastoral.parish.view', 'pastoral.parish.manage', 'governance.report.finalize_clergy'],
        'vicar' => ['pastoral.parish.view', 'pastoral.parish.manage', 'governance.report.finalize_clergy'],
        'cathedral_dean' => ['pastoral.parish.view', 'pastoral.parish.manage', 'governance.report.finalize_clergy'],
        'regional_dean' => ['deanery.view', 'deanery.coordinate', 'pastoral.parish.view'],
        'deanery_dean' => ['deanery.view', 'deanery.coordinate', 'pastoral.parish.view'],
    ];

    public function __construct(?BaseConnection $db = null, ?ClergyAuthorizationService $authorization = null)
    {
        $this->db = $db ?? db_connect();
        $this->authorization = $authorization ?? new ClergyAuthorizationService($this->db);
        $this->positions = new LeadershipPositionService();
    }

    public function verifyOrdination(int $personId, int $dioceseId, string $order, ?string $track,
        ?string $ordainedOn, string $evidenceReference, int $reviewerId): ?int
    {
        if (! in_array($order, ['deacon', 'priest', 'bishop'], true)
            || ($track !== null && ! in_array($track, ['vocational', 'transitional'], true))
            || ($track !== null && $order !== 'deacon')
            || ! $this->canReview($reviewerId, $personId, $dioceseId, 'clergy.ordination.verify')
            || trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || $this->db->table('person')->where('id', $personId)->countAllResults() === 0
            || $this->db->table('clergy_ordination')->where('person_id', $personId)
                ->where('order_code', $order)->where('verification_status', 'verified')->countAllResults() > 0) {
            return null;
        }
        $model = model(ClergyOrdinationModel::class);
        $id = $model->insert([
            'person_id' => $personId, 'order_code' => $order, 'deacon_track' => $track,
            'ordained_on' => $ordainedOn, 'evidence_reference' => trim($evidenceReference),
            'verification_status' => 'verified', 'verified_by_user_id' => $reviewerId,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
        return $id !== false ? (int) $id : null;
    }

    public function recordStanding(int $personId, int $dioceseId, string $status, string $from,
        ?string $to, string $evidenceReference, int $reviewerId): ?int
    {
        if (! in_array($status, ['resident', 'licensed', 'transferred', 'retired', 'suspended', 'inhibited'], true)
            || ! $this->canReview($reviewerId, $personId, $dioceseId, 'clergy.ordination.verify')
            || $this->db->table('person')->where('id', $personId)->countAllResults() === 0
            || trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || ($to !== null && $to < $from)) {
            return null;
        }
        $id = model(ClergyCanonicalStatusModel::class)->insert([
            'person_id' => $personId, 'diocese_id' => $dioceseId, 'status_code' => $status,
            'effective_from' => $from, 'effective_to' => $to,
            'evidence_reference' => trim($evidenceReference), 'verified_by_user_id' => $reviewerId,
        ]);
        return $id !== false ? (int) $id : null;
    }

    /** Reviewing a migrated candidate records evidence only, never a grant. */
    public function reviewCandidate(int $candidateId, int $dioceseId, string $decision,
        ?string $confirmedOrder, ?string $reasonCode, int $reviewerId): bool
    {
        $candidate = $this->db->table('clergy_transition_candidate')
            ->where('id', $candidateId)->where('review_status', 'pending')->get()->getRowArray();
        if ($candidate === null || ! in_array($decision, ['approved', 'rejected'], true)
            || (int) ($candidate['person_id'] ?? 0) <= 0 || ! $this->candidateInDiocese($candidate, $dioceseId)
            || ! $this->canReview($reviewerId, (int) $candidate['person_id'],
                $dioceseId, 'clergy.ordination.verify')) {
            return false;
        }
        if ($decision === 'approved') {
            $personId = (int) $candidate['person_id'];
            if (! in_array($confirmedOrder, ['deacon', 'priest', 'bishop'], true)
                || ($candidate['proposed_order'] !== null && $candidate['proposed_order'] !== $confirmedOrder)
                || $this->positions->resolvePersonIdForUser((int) $candidate['user_id']) !== $personId
                || $this->db->table('clergy_ordination')->where('person_id', $personId)
                    ->where('order_code', $confirmedOrder)->where('verification_status', 'verified')
                    ->where('verified_at IS NOT NULL', null, false)->countAllResults() === 0
                || ! $this->authorization->hasCurrentStanding($personId, $dioceseId)) {
                return false;
            }
        } elseif (! in_array($reasonCode, ['wrong_person', 'invalid_order', 'not_in_diocese', 'duplicate'], true)) {
            return false;
        }
        return $this->db->table('clergy_transition_candidate')->where('id', $candidateId)
            ->where('review_status', 'pending')->update([
                'review_status' => $decision, 'review_reason_code' => $decision === 'rejected' ? $reasonCode : null,
                'reviewed_by_user_id' => $reviewerId, 'reviewed_at' => date('Y-m-d H:i:s'),
            ]) && $this->db->affectedRows() === 1;
    }

    private function candidateInDiocese(array $candidate, int $dioceseId): bool
    {
        $scope = (string) ($candidate['scope_type'] ?? '');
        $id = (int) ($candidate['scope_id'] ?? 0);
        if ($scope === 'diocese') {
            return $id > 0 && $id === $dioceseId;
        }
        if ($scope === 'parish') {
            return $id > 0 && $this->dioceseFor('parish', $id) === $dioceseId;
        }
        if ($scope !== '' || $dioceseId <= 0) {
            return false; // No province-wide inference from a legacy position.
        }
        $profile = $this->db->table('profile')->select('diocese_id, parish_id')
            ->where('user_id', $candidate['user_id'])->get()->getRowArray();
        $declaredDiocese = (int) ($profile['diocese_id'] ?? 0);
        $parishDiocese = $this->dioceseFor('parish', (int) ($profile['parish_id'] ?? 0));
        if ($declaredDiocese > 0 && $parishDiocese > 0 && $declaredDiocese !== $parishDiocese) {
            return false;
        }
        return $declaredDiocese === $dioceseId || $parishDiocese === $dioceseId;
    }

    /** Approved office provisions only role-policy capabilities, never an admin group. */
    public function approveOffice(int $positionId, int $reviewerId, string $evidenceReference): bool
    {
        $position = $this->positions->find($positionId);
        if (trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || $position === null || (int) ($position['person_id'] ?? 0) <= 0
            || (int) ($position['active'] ?? 0) !== 1
            || ! empty($position['approved_at'])
            || ! isset(self::OFFICE_PERMISSIONS[(string) ($position['role_code'] ?? '')])
            || ! $this->positions->validPositionPayload($position)
            || ! $this->positions->hasPortalAccess((int) $position['user_id'])) {
            return false;
        }
        $scope = (string) $position['scope_type'];
        $scopeId = (int) ($position[$scope . '_id'] ?? 0);
        $dioceseId = $this->dioceseFor($scope, $scopeId);
        if ($scopeId <= 0 || ! $this->canReview($reviewerId, (int) $position['person_id'],
            $dioceseId, 'clergy.appointment.manage')) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if (! empty($position['ended_at']) && $position['ended_at'] < date('Y-m-d')) {
            return false;
        }
        $this->db->transBegin();
        try {
            if ($this->db->DBDriver === 'MySQLi') {
                $this->db->query('SELECT id FROM ' . $this->db->protectIdentifiers($scope)
                    . ' WHERE id = ? FOR UPDATE', [$scopeId]);
            }
            if ($this->hasConflictingIncumbent($position)) {
                $this->db->transRollback();
                return false;
            }
            $this->db->table('leadership_position')->where('id', $positionId)->update([
                'approved_by_user_id' => $reviewerId, 'approved_at' => $now,
                'approval_evidence_reference' => trim($evidenceReference),
                'revoked_at' => null, 'updated_at' => $now,
            ]);
            $start = max($now, ! empty($position['started_at']) ? $position['started_at'] . ' 00:00:00' : $now);
            $end = ! empty($position['ended_at']) ? $position['ended_at'] . ' 23:59:59' : null;
            foreach (self::OFFICE_PERMISSIONS[(string) $position['role_code']] as $permission) {
                $this->db->table('clergy_access_grant')->insert([
                    'user_id' => (int) $position['user_id'], 'permission' => $permission,
                    'scope_type' => $scope, 'scope_id' => $scopeId,
                    'source_position_id' => $positionId, 'grant_type' => 'office',
                    'granted_by_user_id' => $reviewerId,
                    'starts_at' => $start, 'ends_at' => $end, 'created_at' => $now,
                ]);
                $this->provisionShieldPermission((int) $position['user_id'], $permission, $positionId, $now);
            }
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return false;
        }
    }

    public function revokeOffice(int $positionId, int $reviewerId): bool
    {
        $position = $this->positions->find($positionId);
        if ($position === null || (int) ($position['person_id'] ?? 0) <= 0) {
            return false;
        }
        $scope = (string) $position['scope_type'];
        if (! $this->canReview($reviewerId, (int) $position['person_id'],
            $this->dioceseFor($scope, (int) ($position[$scope . '_id'] ?? 0)), 'clergy.appointment.manage')) {
            return false;
        }
        if (! $this->positions->delete($positionId)) {
            return false;
        }
        $this->removeManagedPermissions($positionId);
        return true;
    }

    /** A lay signer requires a distinct approver and a documented local appointment. */
    public function approveLayOffice(int $positionId, int $reviewerId, string $evidenceReference,
        int $authorizedParishId, bool $bishopDesignatedMission = false): bool
    {
        $position = $this->positions->find($positionId);
        if (trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || $position === null || (int) ($position['person_id'] ?? 0) <= 0
            || (int) $position['user_id'] === $reviewerId || ! empty($position['approved_at'])
            || (int) $position['active'] !== 1 || $position['scope_type'] !== 'parish'
            || (int) $position['parish_id'] !== $authorizedParishId
            || $position['role_category'] !== 'lay_staff'
            || ! $this->positions->validPositionPayload($position)) {
            return false;
        }
        $code = (string) $position['role_code'];
        $mission = $this->db->table('parish')->select('congregation_type, diocese_id')
            ->where('id', $authorizedParishId)->get()->getRowArray();
        $isMission = ($mission['congregation_type'] ?? '') === 'mission';
        if (($bishopDesignatedMission !== $isMission)
            || ! in_array($code, $isMission
                ? ['mission_governance_signer', 'mission_approver']
                : ['vestry_secretary', 'vestry_clerk', 'warden'], true)) {
            return false;
        }
        if ($bishopDesignatedMission) {
            if (! $this->authorization->allows($reviewerId, 'clergy.appointment.manage',
                'diocese', (int) $mission['diocese_id'])) {
                return false;
            }
        } elseif ((int) auth()->id() !== $reviewerId
            || auth()->user()?->can('governance.vestry.manage') !== true) {
            return false;
        }
        $eligibility = $this->positions->eligibilityFor((int) $position['user_id'],
            (int) $position['person_id'], $authorizedParishId, $position);
        if (! $eligibility['eligible']) {
            return false;
        }
        return $this->db->table('leadership_position')->where('id', $positionId)
            ->where('approved_at', null)->update([
                'approved_by_user_id' => $reviewerId,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_evidence_reference' => trim($evidenceReference),
                'revoked_at' => null,
            ]);
    }

    /** Delegation is explicit, diocesan, documentary, non-self, and expires. */
    public function issueDelegation(int $ordinaryId, int $recipientId, int $dioceseId,
        string $permission, string $endsOn, string $evidenceReference): ?int
    {
        if (! in_array($permission, [
            'governance.diocese.approve', 'clergy.ordination.verify', 'clergy.appointment.manage',
        ], true) || $ordinaryId === $recipientId || $recipientId <= 0 || $dioceseId <= 0
            || trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || ! $this->validDate($endsOn) || $endsOn < date('Y-m-d')
            || ! $this->authorization->isCurrentOrdinary($ordinaryId, $dioceseId)
            || ! $this->authorization->allows($ordinaryId, 'governance.diocese.approve', 'diocese', $dioceseId)
            || ! $this->positions->hasPortalAccess($recipientId)) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $ok = $this->db->table('clergy_access_grant')->insert([
                'user_id' => $recipientId, 'permission' => $permission,
                'scope_type' => 'diocese', 'scope_id' => $dioceseId,
                'source_position_id' => null, 'grant_type' => 'delegation',
                'granted_by_user_id' => $ordinaryId, 'evidence_reference' => trim($evidenceReference),
                'starts_at' => $now, 'ends_at' => $endsOn . ' 23:59:59', 'created_at' => $now,
            ]);
            $grantId = (int) $this->db->insertID();
            if (! $ok || $grantId <= 0) {
                $this->db->transRollback();
                return null;
            }
            $this->provisionShieldPermission($recipientId, $permission, $grantId, $now, 'delegation');
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return null;
            }
            $this->db->transCommit();
            return $grantId;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return null;
        }
    }

    public function issueParishVacancyDelegation(int $ordinaryId, int $recipientId, int $parishId,
        string $endsOn, string $evidenceReference): ?int
    {
        $dioceseId = $this->dioceseFor('parish', $parishId);
        if ($ordinaryId === $recipientId || $recipientId <= 0 || $dioceseId <= 0
            || trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || ! $this->validDate($endsOn) || $endsOn < date('Y-m-d')
            || ! $this->authorization->isCurrentOrdinary($ordinaryId, $dioceseId)
            || ! $this->authorization->allows($ordinaryId, 'governance.diocese.approve', 'diocese', $dioceseId)
            || ! $this->authorization->canServeAsPriest($recipientId, $dioceseId)
            || ! $this->authorization->isVacantParish($parishId)) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('clergy_access_grant')->insert([
                'user_id' => $recipientId, 'permission' => 'governance.report.finalize_clergy',
                'scope_type' => 'parish', 'scope_id' => $parishId,
                'source_position_id' => null, 'grant_type' => 'delegation',
                'granted_by_user_id' => $ordinaryId, 'evidence_reference' => trim($evidenceReference),
                'starts_at' => $now, 'ends_at' => $endsOn . ' 23:59:59', 'created_at' => $now,
            ]);
            $id = (int) $this->db->insertID();
            if ($id <= 0 || $this->db->transStatus() === false) {
                $this->db->transRollback();
                return null;
            }
            $this->provisionShieldPermission($recipientId, 'governance.report.finalize_clergy',
                $id, $now, 'delegation');
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return null;
            }
            $this->db->transCommit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return null;
        }
    }

    public function setReportSignaturePolicy(int $ordinaryId, int $dioceseId,
        bool $enabled, string $evidenceReference): bool
    {
        if ($dioceseId <= 0 || trim($evidenceReference) === '' || strlen($evidenceReference) > 255
            || ! $this->authorization->isCurrentOrdinary($ordinaryId, $dioceseId)
            || ! $this->authorization->allows($ordinaryId, 'governance.diocese.approve', 'diocese', $dioceseId)) {
            return false;
        }
        $this->db->transBegin();
        try {
            $now = date('Y-m-d H:i:s');
            foreach ([
                'governance.report.signatures_enabled' => $enabled ? '1' : '0',
                'governance.report.signatures_policy_reference' => trim($evidenceReference),
            ] as $key => $value) {
                $existing = $this->db->table('diocese_setting')->select('id')
                    ->where('diocese_id', $dioceseId)->where('key', $key)->get()->getRowArray();
                if ($existing === null) {
                    $this->db->table('diocese_setting')->insert([
                        'diocese_id' => $dioceseId, 'key' => $key, 'value' => $value,
                        'is_encrypted' => 0, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                } else {
                    $this->db->table('diocese_setting')->where('id', $existing['id'])
                        ->update(['value' => $value, 'updated_at' => $now]);
                }
            }
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return false;
        }
    }

    public function revokeDelegation(int $grantId, int $ordinaryId): bool
    {
        $grant = $this->db->table('clergy_access_grant')->where('id', $grantId)
            ->where('grant_type', 'delegation')->get()->getRowArray();
        $dioceseId = $grant !== null ? $this->dioceseFor((string) $grant['scope_type'], (int) $grant['scope_id']) : 0;
        if ($grant === null || ! in_array($grant['scope_type'], ['diocese', 'parish'], true)
            || ! $this->authorization->isCurrentOrdinary($ordinaryId, $dioceseId)) {
            return false;
        }
        $this->db->table('clergy_access_grant')->where('id', $grantId)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
        $this->removeManagedPermissionSource('delegation', $grantId);
        return true;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function hasConflictingIncumbent(array $candidate): bool
    {
        $role = (string) $candidate['role_code'];
        if ($role === 'diocesan_bishop') {
            $codes = ['diocesan_bishop'];
        } elseif (in_array($role, ['rector', 'priest_in_charge', 'interim_rector', 'vicar', 'cathedral_dean'], true)) {
            $codes = ['rector', 'priest_in_charge', 'interim_rector', 'vicar', 'cathedral_dean'];
        } else {
            return false; // Multiple concurrent regional/associate clergy are allowed.
        }
        $scope = (string) $candidate['scope_type'];
        $scopeId = (int) ($candidate[$scope . '_id'] ?? 0);
        $rows = $this->db->table('leadership_position')
            ->select('id, started_at, ended_at')->where('scope_type', $scope)
            ->where($scope . '_id', $scopeId)->whereIn('role_code', $codes)
            ->where('active', 1)->where('approved_at IS NOT NULL', null, false)
            ->where('revoked_at', null)->where('id !=', (int) $candidate['id'])
            ->get()->getResultArray();
        $start = $candidate['started_at'] ?? null;
        $end = $candidate['ended_at'] ?? null;
        foreach ($rows as $row) {
            if (($end === null || $row['started_at'] === null || $row['started_at'] <= $end)
                && ($row['ended_at'] === null || $start === null || $start <= $row['ended_at'])) {
                return true;
            }
        }
        return false;
    }

    private function canReview(int $reviewerId, int $personId, int $dioceseId, string $permission): bool
    {
        if ($reviewerId <= 0 || $personId <= 0 || $dioceseId <= 0) {
            return false;
        }
        $linked = $this->db->table('user_person')->where('user_id', $reviewerId)
            ->where('person_id', $personId)->countAllResults() > 0;
        if ($linked || $this->db->table('profile')->where('user_id', $reviewerId)
            ->where('person_id', $personId)->countAllResults() > 0) {
            return false;
        }
        return $this->authorization->allows($reviewerId, $permission, 'diocese', $dioceseId);
    }

    private function dioceseFor(string $scope, int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        if ($scope === 'diocese') {
            return $this->db->table('diocese')->where('id', $id)->countAllResults() > 0 ? $id : 0;
        }
        if (! in_array($scope, ['parish', 'deanery'], true)) {
            return 0;
        }
        $row = $this->db->table($scope)->select('diocese_id')->where('id', $id)->get()->getRowArray();
        return (int) ($row['diocese_id'] ?? 0);
    }

    private function provisionShieldPermission(int $userId, string $permission, int $sourceId, string $now,
        string $sourceType = 'office'): void
    {
        $existing = $this->db->table('auth_permissions_users')->select('id')
            ->where('user_id', $userId)->where('permission', $permission)->get()->getRowArray();
        if ($existing === null) {
            $this->db->table('auth_permissions_users')->insert([
                'user_id' => $userId, 'permission' => $permission, 'created_at' => $now,
            ]);
            $permissionId = (int) $this->db->insertID();
        } else {
            $permissionId = (int) $existing['id'];
        }
        // Do not claim a pre-existing direct grant without managed provenance.
        $managed = $this->db->table('permission_role_sync_managed')
            ->where('user_id', $userId)->where('permission', $permission)
            ->where('permission_user_id', $permissionId)->countAllResults() > 0;
        if ($existing === null || $managed) {
            $this->db->table('permission_role_sync_managed')->insert([
                'user_id' => $userId, 'permission' => $permission,
                'permission_user_id' => $permissionId,
                'source_type' => $sourceType, 'source_key' => (string) $sourceId,
                'created_at' => $now,
            ]);
        }
    }

    private function removeManagedPermissions(int $positionId): void
    {
        $this->removeManagedPermissionSource('office', $positionId);
    }

    private function removeManagedPermissionSource(string $sourceType, int $sourceId): void
    {
        $sources = $this->db->table('permission_role_sync_managed')
            ->where('source_type', $sourceType)->where('source_key', (string) $sourceId)->get()->getResultArray();
        foreach ($sources as $source) {
            $this->db->table('permission_role_sync_managed')->where('id', $source['id'])->delete();
            if ($this->db->table('permission_role_sync_managed')
                ->where('permission_user_id', $source['permission_user_id'])->countAllResults() === 0) {
                $this->db->table('auth_permissions_users')
                    ->where('id', $source['permission_user_id'])
                    ->where('user_id', $source['user_id'])
                    ->where('permission', $source['permission'])->delete();
            }
        }
    }
}
