<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Ecclesial authority is never inferred from Shield group or numeric rank. */
final class ClergyAuthorizationService
{
    /** @var array<string, array{target: string, scopes: list<string>, offices: list<string>, order: string}> */
    private const POLICY = [
        'governance.report.finalize_clergy' => [
            'target' => 'parish', 'scopes' => ['parish'],
            'offices' => ['rector', 'priest_in_charge', 'interim_rector', 'vicar', 'cathedral_dean'],
            'order' => 'priest',
        ],
        'governance.diocese.approve' => [
            'target' => 'diocese', 'scopes' => ['diocese'], 'offices' => ['diocesan_bishop'], 'order' => 'bishop',
        ],
        'clergy.ordination.verify' => [
            'target' => 'diocese', 'scopes' => ['diocese'], 'offices' => ['diocesan_bishop'], 'order' => 'bishop',
        ],
        'clergy.appointment.manage' => [
            'target' => 'diocese', 'scopes' => ['diocese'], 'offices' => ['diocesan_bishop'], 'order' => 'bishop',
        ],
        'deanery.view' => [
            'target' => 'deanery', 'scopes' => ['deanery'],
            'offices' => ['regional_dean', 'deanery_dean'], 'order' => 'priest',
        ],
        'deanery.coordinate' => [
            'target' => 'deanery', 'scopes' => ['deanery'],
            'offices' => ['regional_dean', 'deanery_dean'], 'order' => 'priest',
        ],
        'pastoral.parish.view' => [
            'target' => 'parish', 'scopes' => ['parish', 'deanery'],
            'offices' => ['rector', 'priest_in_charge', 'interim_rector', 'vicar', 'cathedral_dean', 'regional_dean', 'deanery_dean'],
            'order' => 'priest',
        ],
        'pastoral.parish.manage' => [
            'target' => 'parish', 'scopes' => ['parish'],
            'offices' => ['rector', 'priest_in_charge', 'interim_rector', 'vicar', 'cathedral_dean'],
            'order' => 'priest',
        ],
    ];

    /** @var callable(int,string):bool|null */
    private $permissionCheck;
    private BaseConnection $db;
    private HierarchyService $hierarchy;

    public function __construct(?BaseConnection $db = null, ?callable $permissionCheck = null)
    {
        $this->db = $db ?? db_connect();
        $this->hierarchy = new HierarchyService($this->db);
        $this->permissionCheck = $permissionCheck;
    }

    public function allows(int $actorId, string $permission, string $targetType, int $targetId): bool
    {
        return $this->matchingGrant($actorId, $permission, $targetType, $targetId) !== null;
    }

    /** Return the effective office or delegation grant used for this decision. */
    public function matchingGrant(int $actorId, string $permission, string $targetType, int $targetId): ?array
    {
        $policy = self::POLICY[$permission] ?? null;
        if ($policy === null || $targetType !== $policy['target'] || $actorId <= 0 || $targetId <= 0
            || ! $this->db->tableExists('clergy_access_grant')
            || ! $this->coarsePermission($actorId, $permission)
            || ! $this->activeAccount($actorId)) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $grants = $this->db->table('clergy_access_grant')
            ->where('user_id', $actorId)->where('permission', $permission)
            ->where('starts_at <=', $now)
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >=', $now)->groupEnd()
            ->where('revoked_at', null)->get()->getResultArray();
        foreach ($grants as $grant) {
            $scopeType = (string) $grant['scope_type'];
            $scopeId = (int) $grant['scope_id'];
            if (! in_array($scopeType, $policy['scopes'], true)
                || ! $this->contains($scopeType, $scopeId, $targetType, $targetId)) {
                continue;
            }
            $dioceseId = $this->dioceseFor($scopeType, $scopeId);
            if ($dioceseId <= 0) {
                continue;
            }
            if ($grant['grant_type'] === 'office'
                && $this->verifiedOffice($actorId, (int) $grant['source_position_id'], $scopeType, $scopeId, $dioceseId, $policy, $permission)) {
                return $grant;
            }
            if ($grant['grant_type'] === 'delegation'
                && in_array($permission, ['governance.diocese.approve', 'clergy.ordination.verify', 'clergy.appointment.manage'], true)
                && $scopeType === 'diocese' && $scopeId === $targetId
                && (int) $grant['granted_by_user_id'] !== $actorId
                && $this->currentOrdinary((int) $grant['granted_by_user_id'], $dioceseId)) {
                return $grant;
            }
            if ($grant['grant_type'] === 'delegation' && $permission === 'governance.report.finalize_clergy'
                && $scopeType === 'parish' && $scopeId === $targetId
                && (int) $grant['granted_by_user_id'] !== $actorId
                && $this->currentOrdinary((int) $grant['granted_by_user_id'], $dioceseId)
                && $this->canServeAsPriest($actorId, $dioceseId)
                && $this->isVacantParish($scopeId)) {
                return $grant;
            }
        }
        return null;
    }

    /** Check an office without a Shield grant during supervised review/provisioning. */
    public function officeEligible(int $userId, int $positionId, string $permission): bool
    {
        $policy = self::POLICY[$permission] ?? null;
        if ($policy === null || $userId <= 0 || $positionId <= 0) {
            return false;
        }
        $position = $this->position($positionId);
        if ($position === null || (int) $position['user_id'] !== $userId) {
            return false;
        }
        $scopeType = (string) $position['scope_type'];
        $scopeId = (int) ($position[$scopeType . '_id'] ?? 0);
        return in_array($scopeType, $policy['scopes'], true)
            && $this->verifiedOffice($userId, $positionId, $scopeType, $scopeId,
                $this->dioceseFor($scopeType, $scopeId), $policy, $permission);
    }

    public function isCurrentOrdinary(int $userId, int $dioceseId): bool
    {
        return $this->currentOrdinary($userId, $dioceseId);
    }

    public function canServeAsPriest(int $userId, int $dioceseId): bool
    {
        $personId = $this->personForUser($userId);
        return $this->activeAccount($userId) && $personId > 0
            && $this->verifiedOrder($personId, 'priest') && $this->currentStanding($personId, $dioceseId);
    }

    public function hasCurrentStanding(int $personId, int $dioceseId): bool
    {
        return $personId > 0 && $dioceseId > 0 && $this->currentStanding($personId, $dioceseId);
    }

    public function isVacantParish(int $parishId): bool
    {
        if ($parishId <= 0) {
            return false;
        }
        $today = date('Y-m-d');
        return $this->db->table('leadership_position')->where('scope_type', 'parish')
            ->where('parish_id', $parishId)
            ->groupStart()
                ->whereIn('role_code', ['rector', 'priest_in_charge', 'interim_rector', 'vicar'])
                ->orGroupStart()->where('role_code', 'cathedral_dean')
                    ->where('pastoral_in_charge', 1)->groupEnd()
            ->groupEnd()
            ->where('active', 1)->where('approved_at IS NOT NULL', null, false)
            ->where('revoked_at', null)
            ->groupStart()->where('started_at', null)->orWhere('started_at <=', $today)->groupEnd()
            ->groupStart()->where('ended_at', null)->orWhere('ended_at >=', $today)->groupEnd()
            ->countAllResults() === 0;
    }

    private function verifiedOffice(int $userId, int $positionId, string $scopeType, int $scopeId,
        int $dioceseId, array $policy, string $permission): bool
    {
        if ($positionId <= 0 || $dioceseId <= 0 || ! $this->activeAccount($userId)) {
            return false;
        }
        $office = $this->position($positionId);
        if ($office === null || (int) $office['user_id'] !== $userId
            || (int) ($office['person_id'] ?? 0) !== $this->personForUser($userId)
            || (int) ($office['active'] ?? 0) !== 1
            || (string) ($office['scope_type'] ?? '') !== $scopeType
            || (int) ($office[$scopeType . '_id'] ?? 0) !== $scopeId
            || ! in_array((string) ($office['role_code'] ?? ''), $policy['offices'], true)
            || empty($office['approved_at']) || (int) ($office['approved_by_user_id'] ?? 0) <= 0
            || trim((string) ($office['approval_evidence_reference'] ?? '')) === ''
            || (int) $office['approved_by_user_id'] === $userId || $office['revoked_at'] !== null) {
            return false;
        }
        $today = date('Y-m-d');
        if (($office['started_at'] !== null && $office['started_at'] > $today)
            || ($office['ended_at'] !== null && $office['ended_at'] < $today)) {
            return false;
        }
        if ($office['role_code'] === 'cathedral_dean') {
            $parish = $this->db->table('parish')->select('congregation_type')->where('id', $scopeId)->get()->getRowArray();
            if (($parish['congregation_type'] ?? '') !== 'cathedral') {
                return false;
            }
            if (in_array($permission, ['governance.report.finalize_clergy', 'pastoral.parish.manage'], true)
                && (int) ($office['pastoral_in_charge'] ?? 0) !== 1) {
                return false;
            }
        }
        return $this->verifiedOrder((int) $office['person_id'], $policy['order'])
            && $this->currentStanding((int) $office['person_id'], $dioceseId);
    }

    private function currentOrdinary(int $userId, int $dioceseId): bool
    {
        if ($userId <= 0 || $dioceseId <= 0) {
            return false;
        }
        $positions = $this->db->table('leadership_position')
            ->select('id')->where('user_id', $userId)->where('scope_type', 'diocese')
            ->where('diocese_id', $dioceseId)->where('role_code', 'diocesan_bishop')->get()->getResultArray();
        foreach ($positions as $position) {
            if ($this->verifiedOffice($userId, (int) $position['id'], 'diocese', $dioceseId, $dioceseId,
                self::POLICY['governance.diocese.approve'], 'governance.diocese.approve')) {
                return true;
            }
        }
        return false;
    }

    private function verifiedOrder(int $personId, string $order): bool
    {
        return $personId > 0 && $this->db->table('clergy_ordination')
            ->where('person_id', $personId)->where('order_code', $order)
            ->where('verification_status', 'verified')->where('verified_at IS NOT NULL', null, false)
            ->countAllResults() > 0;
    }

    private function currentStanding(int $personId, int $dioceseId): bool
    {
        $today = date('Y-m-d');
        $rows = $this->db->table('clergy_canonical_status')
            ->select('status_code')->where('person_id', $personId)->where('diocese_id', $dioceseId)
            ->where('effective_from <=', $today)
            ->groupStart()->where('effective_to', null)->orWhere('effective_to >=', $today)->groupEnd()
            ->get()->getResultArray();
        $licensed = false;
        foreach ($rows as $row) {
            if (in_array($row['status_code'], ['suspended', 'inhibited', 'transferred', 'retired'], true)) {
                return false;
            }
            $licensed = $licensed || in_array($row['status_code'], ['resident', 'licensed'], true);
        }
        return $licensed;
    }

    private function position(int $id): ?array
    {
        if ($id <= 0 || ! $this->db->tableExists('leadership_position')) {
            return null;
        }
        return $this->db->table('leadership_position')->where('id', $id)->get()->getRowArray();
    }

    private function activeAccount(int $userId): bool
    {
        if ($userId <= 0 || $this->db->table('users')->where('id', $userId)->where('active', 1)->countAllResults() === 0) {
            return false;
        }
        if ($this->db->table('auth_identities')->where('user_id', $userId)
            ->where('type', 'email_password')->countAllResults() === 0) {
            return false;
        }
        $profile = $this->db->table('profile')->select('portal_access')
            ->where('user_id', $userId)->get()->getRowArray();
        return ($profile['portal_access'] ?? '') === 'enabled';
    }

    private function personForUser(int $userId): int
    {
        $rows = $this->db->table('user_person')->select('person_id, is_primary')
            ->where('user_id', $userId)->get()->getResultArray();
        $primary = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['is_primary'] === 1));
        if (count($primary) > 1 || (count($primary) === 0 && count($rows) > 1)) {
            return 0;
        }
        $personId = (int) ($primary[0]['person_id'] ?? $rows[0]['person_id'] ?? 0);
        if ($personId <= 0) {
            $profile = $this->db->table('profile')->select('person_id')->where('user_id', $userId)->get()->getRowArray();
            $personId = (int) ($profile['person_id'] ?? 0);
        } else {
            $profile = $this->db->table('profile')->select('person_id')->where('user_id', $userId)->get()->getRowArray();
            if ((int) ($profile['person_id'] ?? 0) > 0 && (int) $profile['person_id'] !== $personId) {
                return 0;
            }
        }
        return $personId;
    }

    private function contains(string $scopeType, int $scopeId, string $targetType, int $targetId): bool
    {
        if ($scopeId <= 0) {
            return false;
        }
        if ($scopeType === $targetType) {
            return $scopeId === $targetId;
        }
        return $scopeType === 'deanery' && $targetType === 'parish'
            && $this->hierarchy->parishInDeanery($targetId, $scopeId);
    }

    private function dioceseFor(string $scopeType, int $scopeId): int
    {
        if ($scopeId <= 0) {
            return 0;
        }
        if ($scopeType === 'diocese') {
            return $this->db->table('diocese')->where('id', $scopeId)->countAllResults() > 0 ? $scopeId : 0;
        }
        $table = ['parish' => 'parish', 'deanery' => 'deanery'][$scopeType] ?? null;
        if ($table === null) {
            return 0;
        }
        $row = $this->db->table($table)->select('diocese_id')->where('id', $scopeId)->get()->getRowArray();
        return (int) ($row['diocese_id'] ?? 0);
    }

    private function coarsePermission(int $userId, string $permission): bool
    {
        if ($this->permissionCheck !== null) {
            return (bool) ($this->permissionCheck)($userId, $permission);
        }
        $user = auth()->user();
        return $user !== null && (int) $user->id === $userId && $user->can($permission);
    }
}
