<?php

namespace App\Services;

use App\Models\MemberParishAffiliationModel;

/**
 * Centralized membership-pathway transitions for parish_affiliation.
 *
 * The affiliation's membership_status is the canonical parish-relationship
 * state. Every change goes through transition() so that:
 *
 *  - the status vocabulary stays canonical (visitor / regular_attender /
 *    member / inactive / transferred / deceased),
 *  - is_member, affiliation_type and end_date stay consistent with the status,
 *  - an immutable person_status_history row is written under the canonical
 *    person_id (never the profile id).
 *
 * All canonical transitions are legal — pastoral reality includes corrections
 * and non-linear movement — but entering or leaving a terminal status
 * (transferred, deceased) requires a recorded reason.
 */
class MembershipPathwayService
{
    public const MOVE_FORWARD      = 'forward';
    public const MOVE_BACKWARD     = 'backward';
    public const MOVE_REACTIVATION = 'reactivation';
    public const MOVE_DEACTIVATION = 'deactivation';
    public const MOVE_TERMINAL     = 'terminal';
    public const MOVE_CORRECTION   = 'correction';
    public const MOVE_NONE         = 'none';

    private const TERMINAL_STATUSES = ['transferred', 'deceased'];

    private MemberParishAffiliationModel $affiliations;
    private PersonStatusHistoryService $history;

    public function __construct(
        ?MemberParishAffiliationModel $affiliations = null,
        ?PersonStatusHistoryService $history = null
    ) {
        $this->affiliations = $affiliations ?? new MemberParishAffiliationModel();
        $this->history      = $history ?? new PersonStatusHistoryService();
    }

    /**
     * Canonical statuses a relationship may move to from the given status.
     * Same-status is excluded (no-op); everything else is permitted.
     *
     * @return array<int, string>
     */
    public function allowedTransitions(string $fromStatus): array
    {
        $from = MembershipStatusService::normalizeAffiliationStatus($fromStatus);

        return array_values(array_filter(
            MembershipStatusService::affiliationStatuses(),
            static fn (string $status): bool => $status !== $from
        ));
    }

    /**
     * Transitions into or out of a terminal status must carry a reason so the
     * history record explains the pastoral decision or the data correction.
     */
    public function requiresReason(string $fromStatus, string $toStatus): bool
    {
        $from = MembershipStatusService::normalizeAffiliationStatus($fromStatus);
        $to   = MembershipStatusService::normalizeAffiliationStatus($toStatus);

        if ($from === $to) {
            return false;
        }

        return in_array($to, self::TERMINAL_STATUSES, true)
            || in_array($from, self::TERMINAL_STATUSES, true);
    }

    /**
     * Classify a transition for audit/display purposes.
     */
    public function classify(string $fromStatus, string $toStatus): string
    {
        $from = MembershipStatusService::normalizeAffiliationStatus($fromStatus);
        $to   = MembershipStatusService::normalizeAffiliationStatus($toStatus);

        if ($from === $to) {
            return self::MOVE_NONE;
        }

        if (in_array($from, self::TERMINAL_STATUSES, true)) {
            return self::MOVE_CORRECTION;
        }

        if (in_array($to, self::TERMINAL_STATUSES, true)) {
            return self::MOVE_TERMINAL;
        }

        if ($to === 'inactive') {
            return self::MOVE_DEACTIVATION;
        }

        if ($from === 'inactive') {
            return self::MOVE_REACTIVATION;
        }

        $pathway = MembershipStatusService::orderedPathway();
        $fromPos = array_search($from, $pathway, true);
        $toPos   = array_search($to, $pathway, true);

        if ($fromPos === false || $toPos === false) {
            return self::MOVE_FORWARD;
        }

        return $toPos > $fromPos ? self::MOVE_FORWARD : self::MOVE_BACKWARD;
    }

    /**
     * Transition the affiliation identified by its primary key.
     *
     * @return array{ok: bool, code: ?string, classification: string, from_status: string, to_status: string, affiliation_id: int}
     */
    public function transition(int $affiliationId, string $toStatus, ?int $changedBy = null, ?string $reason = null): array
    {
        $affiliation = $this->affiliations->find($affiliationId);

        if (! is_array($affiliation)) {
            return $this->result(false, 'affiliation_not_found', self::MOVE_NONE, '', $toStatus, $affiliationId);
        }

        return $this->applyTransition($affiliation, $toStatus, $changedBy, $reason);
    }

    /**
     * Transition the current affiliation of a portal user within a parish.
     * Unlike getActiveAffiliationForParish() this does not require
     * is_member = 1, so inactive members can be reactivated.
     *
     * @return array{ok: bool, code: ?string, classification: string, from_status: string, to_status: string, affiliation_id: int}
     */
    public function transitionForUser(int $userId, int $parishId, string $toStatus, ?int $changedBy = null, ?string $reason = null): array
    {
        $affiliation = $this->affiliations
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->orderBy('id', 'DESC')
            ->first();

        if (! is_array($affiliation)) {
            return $this->result(false, 'affiliation_not_found', self::MOVE_NONE, '', $toStatus, 0);
        }

        return $this->applyTransition($affiliation, $toStatus, $changedBy, $reason);
    }

    /**
     * Transition the current affiliation of a canonical person within a parish.
     *
     * @return array{ok: bool, code: ?string, classification: string, from_status: string, to_status: string, affiliation_id: int}
     */
    public function transitionForPerson(int $personId, int $parishId, string $toStatus, ?int $changedBy = null, ?string $reason = null): array
    {
        $affiliation = $this->affiliations
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->orderBy('id', 'DESC')
            ->first();

        if (! is_array($affiliation)) {
            return $this->result(false, 'affiliation_not_found', self::MOVE_NONE, '', $toStatus, 0);
        }

        return $this->applyTransition($affiliation, $toStatus, $changedBy, $reason);
    }

    /**
     * @param array<string, mixed> $affiliation
     * @return array{ok: bool, code: ?string, classification: string, from_status: string, to_status: string, affiliation_id: int}
     */
    private function applyTransition(array $affiliation, string $toStatus, ?int $changedBy, ?string $reason): array
    {
        $affiliationId = (int) ($affiliation['id'] ?? 0);
        $parishId      = (int) ($affiliation['parish_id'] ?? 0);

        $to = MembershipStatusService::normalizeAffiliationStatus($toStatus);
        if (! in_array($to, MembershipStatusService::affiliationStatuses(), true)) {
            return $this->result(false, 'invalid_status', self::MOVE_NONE, '', $toStatus, $affiliationId);
        }

        $from = MembershipStatusService::normalizeAffiliationStatus((string) ($affiliation['membership_status'] ?? 'visitor'));

        $classification = $this->classify($from, $to);
        if ($classification === self::MOVE_NONE) {
            return $this->result(true, null, self::MOVE_NONE, $from, $to, $affiliationId);
        }

        $reason = trim((string) $reason);
        if ($this->requiresReason($from, $to) && $reason === '') {
            return $this->result(false, 'reason_required', $classification, $from, $to, $affiliationId);
        }

        $personId = (int) ($affiliation['person_id'] ?? 0);
        if ($personId <= 0) {
            $linkedUserId = (int) ($affiliation['linked_user_id'] ?? 0);
            if ($linkedUserId > 0) {
                $personId = (int) ($this->affiliations->getOrCreatePersonIdForUser($linkedUserId) ?? 0);
            }
        }

        $db = $this->affiliations->db;
        // transStatus is a connection-level flag that is NOT reset by
        // transBegin(): a failed query or rollback earlier in the request
        // (e.g. a best-effort snapshot sync) would otherwise poison this
        // transaction's transComplete() check.
        $db->resetTransStatus();
        $db->transStart();

        $this->affiliations->update($affiliationId, [
            'membership_status' => $to,
            'affiliation_type'  => MembershipStatusService::affiliationTypeForStatus($to),
            'is_member'         => MembershipStatusService::isMember($to) ? 1 : 0,
            'end_date'          => in_array($to, self::TERMINAL_STATUSES, true) ? date('Y-m-d') : null,
        ]);

        if ($personId > 0) {
            $this->history->record(
                $personId,
                'affiliation',
                $from,
                $to,
                $parishId > 0 ? $parishId : null,
                $changedBy,
                $reason !== '' ? $reason : 'Status transition: ' . $from . ' -> ' . $to
            );
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', 'MembershipPathwayService: transition failed: ' . json_encode($db->error()));
            return $this->result(false, 'update_failed', $classification, $from, $to, $affiliationId);
        }

        if ($parishId > 0) {
            if ($personId > 0) {
                // Keep the denormalized pathway stage in step with the new
                // status (member gains stage 1; terminal statuses clear it).
                try {
                    (new PathwayStageService($db))->recompute($personId, $parishId);
                } catch (\Throwable $e) {
                    log_message('error', 'MembershipPathwayService: pathway stage recompute failed for person ' . $personId . ': ' . $e->getMessage());
                }
            }
        }

        return $this->result(true, null, $classification, $from, $to, $affiliationId);
    }

    /**
     * @return array{ok: bool, code: ?string, classification: string, from_status: string, to_status: string, affiliation_id: int}
     */
    private function result(bool $ok, ?string $code, string $classification, string $from, string $to, int $affiliationId): array
    {
        return [
            'ok'              => $ok,
            'code'            => $code,
            'classification'  => $classification,
            'from_status'     => $from,
            'to_status'       => $to,
            'affiliation_id'  => $affiliationId,
        ];
    }
}
