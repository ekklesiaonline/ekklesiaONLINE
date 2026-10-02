<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Each signature binds the frozen canonical report, not the financial-summary row. */
final class ParishReportApprovalService
{
    private BaseConnection $db;
    private ClergyAuthorizationService $clergy;
    private string $lastSignError = 'SIGNATURE_CONFLICT';
    /** @var callable(int,string):bool|null */
    private $permissionCheck;

    public function __construct(?BaseConnection $db = null, ?ClergyAuthorizationService $clergy = null,
        ?callable $permissionCheck = null)
    {
        $this->db = $db ?? db_connect();
        $this->clergy = $clergy ?? new ClergyAuthorizationService($this->db);
        $this->permissionCheck = $permissionCheck;
    }

    public function submit(int $parishId, int $year, int $actorId): ?int
    {
        $dioceseId = $this->diocese($parishId);
        if ($year < 1900 || $year > 2200 || ! $this->policyEnabled($dioceseId)
            || ! $this->canPrepare($actorId, $parishId, $dioceseId)) {
            return null;
        }
        $storage = $this->storage($parishId, $year);
        if ($storage === null) {
            return null;
        }
        $json = json_encode($storage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->transBegin();
        try {
            $report = $this->reportForUpdate($parishId, $year);
            if ($report === null) {
                $this->db->table('parish_report')->insert([
                    'parish_id' => $parishId, 'report_year' => $year,
                    'report_status' => 'draft', 'report_revision' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $report = $this->reportForUpdate($parishId, $year);
            }
            if ($report === null || $report['report_status'] !== 'draft') {
                $this->db->transRollback();
                return null;
            }
            $id = (int) $report['id'];
            $this->db->table('parish_report')->where('id', $id)->update([
                'report_status' => 'submitted', 'submitted_by' => $actorId,
                'submitted_at' => date('Y-m-d H:i:s'),
                'frozen_snapshot_json' => $json,
                'snapshot_sha256' => hash('sha256', $json),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
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

    public function lastSignError(): string
    {
        return $this->lastSignError;
    }

    /** Steps: certify_clerk → approve_warden → finalize_clergy → approve_diocese. */
    public function sign(int $reportId, int $actorId, string $step): bool
    {
        $this->lastSignError = 'SIGNATURE_CONFLICT';
        $steps = [
            'certify_clerk' => ['submitted', 'clerk_certified', 'clerk_certified_by', 'clerk_certified_at', 'governance.report.certify_clerk'],
            'approve_warden' => ['clerk_certified', 'warden_approved', 'warden_approved_by', 'warden_approved_at', 'governance.report.approve_warden'],
            'finalize_clergy' => ['warden_approved', 'clergy_finalized', 'finalized_by', 'finalized_at', 'governance.report.finalize_clergy'],
            'approve_diocese' => ['clergy_finalized', 'diocesan_approved', 'diocesan_approved_by', 'diocesan_approved_at', 'governance.diocese.approve'],
        ];
        if (! isset($steps[$step]) || $reportId <= 0 || $actorId <= 0) {
            return false;
        }
        [$from, $to, $signer, $timestamp, $permission] = $steps[$step];
        $this->db->transBegin();
        try {
            $report = $this->db->table('parish_report')->where('id', $reportId)->get()->getRowArray();
            if ($report === null) {
                $this->lastSignError = 'REPORT_NOT_FOUND';
                $this->db->transRollback();
                return false;
            }
            $report = $this->reportForUpdate((int) $report['parish_id'], (int) $report['report_year']);
            if ($report === null) {
                $this->db->transRollback();
                return false;
            }
            $parishId = (int) ($report['parish_id'] ?? 0);
            $dioceseId = $this->diocese($parishId);
            if (! $this->activeAccount($actorId) || ! $this->coarse($actorId, $permission)) {
                $this->lastSignError = 'SCOPE_FORBIDDEN';
                $this->db->transRollback();
                return false;
            }
            $source = match ($step) {
                'certify_clerk' => $this->layOffice($actorId, $parishId,
                    $this->congregationType($parishId) === 'mission' ? ['mission_governance_signer'] : ['vestry_secretary', 'vestry_clerk']),
                'approve_warden' => $this->layOffice($actorId, $parishId,
                    $this->congregationType($parishId) === 'mission' ? ['mission_approver'] : ['warden']),
                'finalize_clergy' => $this->clergy->matchingGrant($actorId, $permission, 'parish', $parishId),
                'approve_diocese' => $this->clergy->matchingGrant($actorId, $permission, 'diocese', $dioceseId),
            };
            if ($source === null) {
                $this->lastSignError = 'REPORT_NOT_FOUND';
                $this->db->transRollback();
                return false;
            }
            if (in_array($actorId, array_map('intval', [
                $report['clerk_certified_by'] ?? 0, $report['warden_approved_by'] ?? 0,
                $report['finalized_by'] ?? 0, $report['diocesan_approved_by'] ?? 0,
            ]), true)) {
                $this->lastSignError = 'SELF_SIGNATURE_FORBIDDEN';
                $this->db->transRollback();
                return false;
            }
            if ($report['report_status'] !== $from || ! $this->policyEnabled($dioceseId)
                || ! $this->snapshotMatchesStorage($report)) {
                $this->db->transRollback();
                return false;
            }
            $this->db->table('parish_report')->where('id', $reportId)
                ->where('report_status', $from)->update([
                    'report_status' => $to, $signer => $actorId, $timestamp => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($this->db->transStatus() === false || $this->db->affectedRows() !== 1) {
                $this->db->transRollback();
                return false;
            }
            $clergyStage = in_array($step, ['finalize_clergy', 'approve_diocese'], true);
            $this->db->table('parish_report_signature')->insert([
                'report_id' => $reportId,
                'revision_number' => (int) $report['report_revision'],
                'stage_code' => $step,
                'actor_user_id' => $actorId,
                'source_position_id' => $clergyStage ? $source['source_position_id'] : $source['id'],
                'source_grant_id' => $clergyStage ? $source['id'] : null,
                'diocese_id' => $dioceseId,
                'was_delegated' => (int) ($clergyStage && $source['grant_type'] === 'delegation'),
                'snapshot_sha256' => $report['snapshot_sha256'],
                'signed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->lastSignError = 'REPORT_SIGNING_FAILED';
            $this->db->transRollback();
            return false;
        }
    }

    /** Never overwrite a signed report: archive evidence before opening the next revision. */
    public function startRevision(int $reportId, int $actorId): bool
    {
        $this->db->transBegin();
        try {
            $row = $this->db->table('parish_report')->where('id', $reportId)->get()->getRowArray();
            if ($row === null) {
                $this->db->transRollback();
                return false;
            }
            $row = $this->reportForUpdate((int) $row['parish_id'], (int) $row['report_year']);
            $parishId = (int) ($row['parish_id'] ?? 0);
            if ($row === null || $row['report_status'] === 'draft'
                || ! $this->policyEnabled($this->diocese($parishId))
                || ! $this->canPrepare($actorId, $parishId, $this->diocese($parishId))
                || ! $this->validSnapshot($row)) {
                $this->db->transRollback();
                return false;
            }
            $this->db->table('parish_report_revision')->insert([
                'report_id' => $reportId, 'revision_number' => (int) $row['report_revision'],
                'report_status' => $row['report_status'], 'snapshot_json' => $row['frozen_snapshot_json'],
                'snapshot_sha256' => $row['snapshot_sha256'], 'submitted_by' => $row['submitted_by'],
                'clerk_certified_by' => $row['clerk_certified_by'],
                'warden_approved_by' => $row['warden_approved_by'],
                'finalized_by' => $row['finalized_by'],
                'diocesan_approved_by' => $row['diocesan_approved_by'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('parish_report')->where('id', $reportId)->update([
                'report_status' => 'draft', 'report_revision' => (int) $row['report_revision'] + 1,
                'frozen_snapshot_json' => null, 'snapshot_sha256' => null,
                'submitted_by' => null, 'submitted_at' => null,
                'clerk_certified_by' => null, 'clerk_certified_at' => null,
                'warden_approved_by' => null, 'warden_approved_at' => null,
                'finalized_by' => null, 'finalized_at' => null,
                'diocesan_approved_by' => null, 'diocesan_approved_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
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

    private function reportForUpdate(int $parishId, int $year): ?array
    {
        if ($this->db->DBDriver === 'MySQLi') {
            return $this->db->query('SELECT * FROM ' . $this->db->protectIdentifiers('parish_report')
                . ' WHERE parish_id = ? AND report_year = ? FOR UPDATE', [$parishId, $year])->getRowArray();
        }
        return $this->db->table('parish_report')->where('parish_id', $parishId)
            ->where('report_year', $year)->get()->getRowArray();
    }

    private function validSnapshot(array $row): bool
    {
        $json = (string) ($row['frozen_snapshot_json'] ?? '');
        return $json !== '' && hash_equals((string) ($row['snapshot_sha256'] ?? ''), hash('sha256', $json));
    }

    private function snapshotMatchesStorage(array $row): bool
    {
        if (! $this->validSnapshot($row)) {
            return false;
        }
        $storage = $this->storage((int) $row['parish_id'], (int) $row['report_year']);
        return $storage !== null && hash_equals((string) $row['snapshot_sha256'],
            hash('sha256', json_encode($storage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
    }

    private function storage(int $parishId, int $year): ?array
    {
        return $this->db->table('parish_report_storage')->where('parish_id', $parishId)
            ->where('report_year', $year)->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function policyEnabled(int $dioceseId): bool
    {
        return $dioceseId > 0 && $this->db->tableExists('diocese_setting')
            && $this->db->table('diocese_setting')->where('diocese_id', $dioceseId)
                ->where('key', 'governance.report.signatures_enabled')->where('value', '1')->countAllResults() > 0;
    }

    private function diocese(int $parishId): int
    {
        $row = $this->db->table('parish')->select('diocese_id')->where('id', $parishId)->get()->getRowArray();
        return (int) ($row['diocese_id'] ?? 0);
    }

    private function congregationType(int $parishId): string
    {
        $row = $this->db->table('parish')->select('congregation_type')->where('id', $parishId)->get()->getRowArray();
        return (string) ($row['congregation_type'] ?? 'parish');
    }

    private function canPrepare(int $actorId, int $parishId, int $dioceseId): bool
    {
        if (! $this->activeAccount($actorId) || ! $this->coarse($actorId, 'report.parochial')) {
            return false;
        }
        return $this->db->table('profile')->where('user_id', $actorId)
            ->where('parish_id', $parishId)->countAllResults() > 0
            || ($dioceseId > 0 && $this->db->table('profile')->where('user_id', $actorId)
                ->where('diocese_id', $dioceseId)->countAllResults() > 0
                && $this->coarse($actorId, 'diocese.admin'));
    }

    private function activeAccount(int $actorId): bool
    {
        return $actorId > 0 && $this->db->table('users')->where('id', $actorId)
            ->where('active', 1)->countAllResults() > 0
            && $this->db->table('auth_identities')->where('user_id', $actorId)
                ->where('type', 'email_password')->countAllResults() > 0
            && $this->db->table('profile')->where('user_id', $actorId)
                ->where('portal_access', 'enabled')->countAllResults() > 0;
    }

    private function layOffice(int $actorId, int $parishId, array $codes): ?array
    {
        $links = $this->db->table('user_person')->select('person_id, is_primary')
            ->where('user_id', $actorId)->get()->getResultArray();
        $primary = array_values(array_filter($links,
            static fn (array $link): bool => (int) $link['is_primary'] === 1));
        if (count($primary) > 1 || (count($primary) === 0 && count($links) !== 1)) {
            return null;
        }
        $personId = (int) ($primary[0]['person_id'] ?? $links[0]['person_id'] ?? 0);
        $profile = $this->db->table('profile')->select('person_id')
            ->where('user_id', $actorId)->get()->getRowArray();
        if ($personId <= 0 || ((int) ($profile['person_id'] ?? 0) > 0
            && (int) $profile['person_id'] !== $personId)) {
            return null;
        }
        return $this->db->table('leadership_position lp')->select('lp.id')
            ->where('lp.person_id', $personId)
            ->where('lp.user_id', $actorId)->where('lp.scope_type', 'parish')->where('lp.parish_id', $parishId)
            ->whereIn('lp.role_code', $codes)->where('lp.role_category', 'lay_staff')->where('lp.active', 1)
            ->where('lp.approved_at IS NOT NULL', null, false)->where('lp.revoked_at', null)
            ->where('lp.approval_evidence_reference IS NOT NULL', null, false)
            ->where('lp.approval_evidence_reference !=', '')
            ->where('lp.approved_by_user_id !=', $actorId)
            ->groupStart()->where('lp.started_at', null)->orWhere('lp.started_at <=', date('Y-m-d'))->groupEnd()
            ->groupStart()->where('lp.ended_at', null)->orWhere('lp.ended_at >=', date('Y-m-d'))->groupEnd()
            ->get()->getRowArray();
    }

    private function coarse(int $actorId, string $permission): bool
    {
        if ($this->permissionCheck !== null) {
            return ($this->permissionCheck)($actorId, $permission);
        }
        return (int) auth()->id() === $actorId && auth()->user()?->can($permission) === true;
    }
}
