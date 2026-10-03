<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\VisitorDirectoryService;
use App\Services\VisitorFollowupService;

/**
 * Visitor follow-up endpoints: sequence status, timeline, log mutations,
 * and the pending-calls badge API. Extracted from Visitors (phase 2 split);
 * routes and behavior are unchanged.
 */
class VisitorFollowup extends BaseController
{
    protected $followupLogModel;
    protected $followupSequenceModel;
    private VisitorFollowupService $followupQueries;
    private VisitorDirectoryService $visitorDirectory;

    public function __construct()
    {
        $this->visitorDirectory        = new VisitorDirectoryService();
        $this->followupLogModel      = model('FollowupLogModel');
        $this->followupSequenceModel = model('FollowupSequenceModel');
        $this->followupQueries       = new VisitorFollowupService();
    }

    // -----------------------------------------------------------------------
    // AJAX endpoints
    // -----------------------------------------------------------------------

    /**
     * GET visitors/followup-status/:id
     * Returns the next pending sequence-driven action for a visitor,
     * plus a summary count by status.
     */
    public function getFollowupStatus(int $visitorId)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $visitorId, (int) $this->getOperationalParishId());
        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 404);
        }

        $personId = (int) ($visitor['person_id'] ?? 0);

        $next = $this->followupQueries->nextPendingForPerson($personId);
        $summary = $this->followupQueries->statusCountsForPerson($personId);

        return $this->jsonResponse([
            'success'     => true,
            'next_action' => $next ?: null,
            'summary'     => $summary,
        ]);
    }


    /**
     * POST visitors/followup-trigger/:logId
     * Manually marks a visitor_followup_log entry as executed (status → sent).
     */
    public function triggerFollowupAction(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = $this->followupQueries->logRow($logId);

        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupLogNotFound')], 404);
        }

        if (! $this->visitorDirectory->visitorAffiliationExists((int) $log['person_id'], (int) $log['parish_id'])
            || (int) $log['parish_id'] !== (int) $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $template = null;
        if (!empty($log['template_id'])) {
            $template = model('FollowupTemplateModel')->find((int) $log['template_id']);
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $log['person_id'], (int) $log['parish_id']);
        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 404);
        }

        $result = (new \App\Services\FollowupDispatchService())->dispatch($log, is_array($template) ? $template : null, $visitor);

        if (!$result['success'] && empty($result['metadata']['skipped'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupTriggerFailed'),
                'error'   => $result['error'] ?? '',
            ], 500);
        }

        $notes = $this->request->getPost('notes');

        if (!empty($result['metadata']['skipped'])) {
            $this->followupLogModel->markSkipped((int) $logId, array_merge(
                $result['metadata'],
                ['triggered_by' => (int) auth()->id(), 'notes' => $notes]
            ));
        } else {
            $this->followupLogModel->markExecuted((int) $logId, array_merge(
                $result['metadata'],
                ['triggered_by' => (int) auth()->id(), 'triggered_at' => date('Y-m-d H:i:s'), 'notes' => $notes]
            ));
        }

        $this->audit()->log('visitor.followup.trigger', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['status' => 'sent', 'triggered_by' => (int) auth()->id()],
            'meta'        => ['visitor_id' => (int) $log['person_id'], 'channel' => $log['channel'] ?? ''],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.followupTriggered')]);
    }


    /**
     * POST visitors/assign-sequence/:visitorId
     * Assigns a follow-up sequence to a visitor, bulk-creating all log rows.
     */
    public function assignSequence(int $visitorId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $visitorId, (int) $this->getOperationalParishId());
        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 404);
        }

        $sequenceId = (int) $this->request->getPost('sequence_id');
        if ($sequenceId < 1) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.sequenceRequired')], 422);
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $this->followupSequenceModel->find($sequenceId);

        if (!$sequence || (int) ($sequence['is_active'] ?? 0) !== 1) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.sequenceNotFound')], 404);
        }

        $scope = \App\Models\FollowupSequenceModel::scopeOf($sequence);

        if ($scope === 'parish') {
            if ((int) $sequence['parish_id'] !== $parishId) {
                return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.sequenceNotFound')], 404);
            }
        } else {
            // Upstream rows are clone templates, not operational
            // workflows: a global sequence or the parish's own diocesan
            // sequence must be adopted (cloned) into the parish first so
            // human-step assignees resolve to the primary admin and the
            // copy keeps lineage for propagation.
            $dioceseId = $this->followupSequenceModel->parishDioceseId($parishId);
            $inScope   = $scope === 'global'
                || ((int) ($sequence['diocese_id'] ?? 0) === $dioceseId && $dioceseId > 0);

            if (!$inScope) {
                return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.sequenceNotFound')], 404);
            }

            $cloneId = (new \App\Services\FollowupCloneService())->cloneSequenceIntoParish($sequenceId, $parishId);
            if (!$cloneId) {
                return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.seqCloneFailed')], 500);
            }

            $this->audit()->log('followup_sequence.cloned', [
                'parish_id'   => $parishId,
                'entity_type' => 'followup_sequence',
                'entity_id'   => $cloneId,
                'meta'        => ['source_sequence_id' => $sequenceId, 'via' => 'assign_sequence'],
            ]);

            $sequenceId = $cloneId;
        }

        $visitDate = $visitor['visit_date'] ?: date('Y-m-d');
        $count     = $this->followupLogModel->scheduleSequenceForPerson(
            (int) $visitorId,
            (int) $this->getOperationalParishId(),
            $sequenceId,
            $visitDate
        );

        if ($count > 0) {
            $this->audit()->log('visitor.sequence.assign', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'visitor',
                'entity_id'   => $visitorId,
                'new_values'  => ['sequence_id' => $sequenceId, 'actions_scheduled' => $count],
            ]);

            return $this->jsonResponse([
                'success' => true,
                'message' => lang('Visitors.sequenceAssigned'),
                'count'   => $count,
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.sequenceHasNoActions')], 422);
    }


    // ------------------------------------------------------------------
    // Follow-up Timeline
    // ------------------------------------------------------------------

    /**
     * GET  visitors/followup-timeline/:visitorId
     * Display the full follow-up timeline for a single visitor with inline
     * action controls (Mark Done, Reschedule, Skip, Print Label).
     */
    public function followupTimeline(int $visitorId)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $visitorId, (int) $this->getOperationalParishId());
        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        $personId = (int) ($visitor['person_id'] ?? 0);

        $timeline = $this->followupQueries->timelineForPerson($personId);

        $data = [
            'visitor'    => $visitor,
            'timeline' => $timeline,
        ];

        return view(
            'Clergy\Views\Visitors/followup_manual',
            array_merge($this->getCommonViewData(lang('Visitors.followupManualTitle')), $data)
        );
    }


    /**
     * POST  visitors/followup-log/mark-done/:logId  (AJAX)
     * Mark a pending followup log entry as done (status → sent).
     */
    public function logMarkDone(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = $this->followupQueries->logRow($logId);
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupLogNotFound')], 404);
        }

        if (! $this->visitorDirectory->visitorAffiliationExists((int) $log['person_id'], (int) $log['parish_id'])
            || (int) $log['parish_id'] !== (int) $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $notes         = trim((string) ($this->request->getPost('notes') ?? ''));
        $leftVoicemail = (bool) $this->request->getPost('left_voicemail');

        $meta = ['triggered_by' => (int) auth()->id(), 'manual' => true];
        if ($leftVoicemail) {
            $meta['left_voicemail'] = true;
        }

        $this->followupLogModel->update($logId, [
            'status'            => 'sent',
            'executed_at'       => date('Y-m-d H:i:s'),
            'notes'             => $notes !== '' ? $notes : null,
            'response_metadata' => json_encode($meta),
        ]);

        // Keep the human-task queue in sync: completes the pending task, or
        // records a completed one when the action was never dispatched.
        (new \App\Services\CallTaskSyncService())->resolveForLog($logId, 'completed', $notes !== '' ? $notes : null);

        $this->audit()->log('visitor.followup.manual_done', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['status' => 'sent'],
            'meta'        => ['visitor_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.followupMarkedDone')]);
    }


    /**
     * POST  visitors/followup-log/reschedule/:logId  (AJAX)
     * Update the scheduled_date of a pending log entry.
     */
    public function logReschedule(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = $this->followupQueries->logRow($logId);
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupLogNotFound')], 404);
        }

        if (! $this->visitorDirectory->visitorAffiliationExists((int) $log['person_id'], (int) $log['parish_id'])
            || (int) $log['parish_id'] !== (int) $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $newDate = trim((string) ($this->request->getPost('new_date') ?? ''));
        if ($newDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || strtotime($newDate) === false) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupInvalidDate')], 422);
        }

        $reason = trim((string) ($this->request->getPost('notes') ?? ''));

        $this->followupLogModel->update($logId, [
            'scheduled_date' => $newDate,
            'notes'          => $reason !== '' ? $reason : null,
        ]);

        // A queued task follows the log's new due date.
        (new \App\Services\CallTaskSyncService())->rescheduleForLog($logId, $newDate);

        $this->audit()->log('visitor.followup.rescheduled', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['scheduled_date' => $newDate],
            'meta'        => ['visitor_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse([
            'success'  => true,
            'message'  => lang('Visitors.followupRescheduled'),
            'new_date' => $newDate,
        ]);
    }


    /**
     * POST  visitors/followup-log/skip/:logId  (AJAX)
     * Mark a pending log entry as skipped.
     */
    public function logSkip(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = $this->followupQueries->logRow($logId);
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupLogNotFound')], 404);
        }

        if (! $this->visitorDirectory->visitorAffiliationExists((int) $log['person_id'], (int) $log['parish_id'])
            || (int) $log['parish_id'] !== (int) $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $notes = trim((string) ($this->request->getPost('notes') ?? ''));
        $this->followupLogModel->markSkipped($logId, $notes !== '' ? $notes : null);

        // Cancels the pending task, or records a cancelled one when the
        // action was never dispatched — skipped calls stay visible in the
        // queue's cancelled view.
        (new \App\Services\CallTaskSyncService())->resolveForLog($logId, 'cancelled', $notes !== '' ? $notes : null);

        $this->audit()->log('visitor.followup.skipped', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'meta'        => ['visitor_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.followupSkipped')]);
    }


    /**
     * POST  visitors/followup-log/mark-failed/:logId  (AJAX)
     * Manually mark a pending follow-up log entry as failed with a required reason.
     */
    public function logMarkFailed(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = $this->followupQueries->logRow($logId);
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupLogNotFound')], 404);
        }

        if (! $this->visitorDirectory->visitorAffiliationExists((int) $log['person_id'], (int) $log['parish_id'])
            || (int) $log['parish_id'] !== (int) $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $reason = trim((string) ($this->request->getPost('reason') ?? ''));
        if ($reason === '') {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.markFailedReasonRequired')], 422);
        }

        $meta = [
            'triggered_by' => (int) auth()->id(),
            'manual'       => true,
            'reason'       => $reason,
        ];

        $this->followupLogModel->update($logId, [
            'status'            => 'failed',
            'executed_at'       => date('Y-m-d H:i:s'),
            'notes'             => $reason,
            'response_metadata' => json_encode($meta),
        ]);

        $this->audit()->log('visitor.followup.manual_failed', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['status' => 'failed'],
            'meta'        => ['visitor_id' => (int) $log['person_id'], 'reason' => $reason],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.markFailedSuccess')]);
    }


    public function pendingCallsApi()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            // Route filter handles this first; this guard is defence-in-depth.
            // Discard the redirect and return a JSON 403 for the XHR caller.
            return $this->jsonResponse(['success' => false, 'message' => lang('App.errorForbidden')], 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse(['success' => true, 'count' => 0, 'items' => []]);
        }

        /** @var \App\Models\CallTaskModel $callTaskModel */
        $callTaskModel = model('CallTaskModel');
        $tasks = $callTaskModel->getPendingForParish($parishId);

        $items = array_map(static function (array $t): array {
            return [
                'id'             => (int) $t['id'],
                'visitor_id'       => (int) $t['person_id'],
                'visitor_name'     => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')),
                'scheduled_date' => $t['scheduled_date'] ?? '',
            ];
        }, $tasks);

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setJSON([
                'success' => true,
                'count'   => count($items),
                'items'   => $items,
            ]);
    }
}
