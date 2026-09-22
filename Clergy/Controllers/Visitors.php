<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\VisitorDirectoryService;
use App\Services\VisitorFollowupService;
use App\Services\PersonConversionService;

class Visitors extends BaseController
{
    protected $personVisitModel;
    protected $followupLogModel;
    protected $followupSequenceModel;
    private VisitorFollowupService $followupQueries;
    private VisitorDirectoryService $visitorDirectory;

    public function __construct()
    {
        $this->visitorDirectory        = new VisitorDirectoryService();
        $this->personVisitModel      = model('PersonVisitModel');
        $this->followupLogModel      = model('FollowupLogModel');
        $this->followupSequenceModel = model('FollowupSequenceModel');
        $this->followupQueries       = new VisitorFollowupService();
    }

    /**
     * Display list of visitors
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('visitor.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $perPage  = 20;

        // Filters from query string
        $followedUp    = $this->request->getGet('followed_up');
        $search        = $this->request->getGet('search');
        $dateFrom      = $this->request->getGet('date_from');     // Y-m-d
        $dateTo        = $this->request->getGet('date_to');       // Y-m-d
        $followupStage = $this->request->getGet('followup_stage'); // pending|sent|failed|skipped|none
        $source        = $this->request->getGet('source');

        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = $this->visitorDirectory->paginateForParish((int) $parishId, [
            'followed_up'    => $followedUp,
            'search'         => $search,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'followup_stage' => $followupStage,
            'source'         => $source,
        ], $perPage, $page);

        $visitors = $result['rows'];

        // Per-visitor followup progress for 4-state badge (by person_id)
        $personIds = array_filter(array_column($visitors, 'person_id'));
        $progressMap = $this->followupQueries->progressMapForPersons($personIds);

        $data = [
            'visitors'        => $visitors,
            'pager'         => $result['pager'],
            'followed_up'   => $followedUp,
            'search'        => $search,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'followup_stage' => $followupStage,
            'source'        => $source,
            'progressMap'   => $progressMap,
        ];

        return view('Clergy\Views\Visitors/index', array_merge($this->getCommonViewData(lang('Visitors.pageTitle')), $data));
    }

    /**
     * Display single visitor
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('visitor.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        // Legacy ad-hoc tasks (retired — table dropped; pass empty array for view compatibility)
        $tasks = [];

        $personId = (int) ($visitor['person_id'] ?? 0);

        // Structured visit history
        $visits = $personId > 0
            ? $this->personVisitModel->where('person_id', $personId)->orderBy('visit_date', 'DESC')->findAll()
            : [];

        // Sequence-driven follow-up timeline
        $followupTimeline = $personId > 0
            ? $this->followupLogModel->getTimelineForPerson($personId)
            : [];

        // Next pending action (for the quick-trigger button)
        $nextPending = $personId > 0
            ? $this->followupQueries->nextPendingForPerson($personId)
            : null;

        // Sequences available to assign
        $sequences = $this->followupSequenceModel->getActiveForParish((int) $visitor['parish_id']);

        $data = [
            'visitor'            => $visitor,
            'tasks'            => $tasks,
            'visits'           => $visits,
            'followupTimeline' => $followupTimeline,
            'nextPending'      => $nextPending,
            'sequences'        => $sequences,
        ];

        return view('Clergy\Views\Visitors/view', array_merge($this->getCommonViewData(lang('Visitors.pageTitleView')), $data));
    }

    /**
     * Show create visitor form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Clergy\Views\Visitors/create', $this->getCommonViewData(lang('Visitors.pageTitleAdd')));
    }

    /**
     * Store new visitor
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'first_name'               => 'required|min_length[2]',
            'last_name'                => 'required|min_length[2]',
            'email'                    => 'permit_empty|valid_email',
            'phone'                    => 'permit_empty',
            'visit_date'               => 'required|valid_date',
            'address'                  => 'permit_empty|max_length[500]',
            'source'                   => 'permit_empty|max_length[100]',
            'preferred_contact_method' => 'permit_empty|in_list[email,text,call,mail]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $interestsRaw = $this->request->getPost('interests');
        $interests    = null;
        if ($interestsRaw !== null && $interestsRaw !== '') {
            $decoded   = json_decode($interestsRaw, true);
            $interests = json_last_error() === JSON_ERROR_NONE
                ? json_encode($decoded)
                : json_encode(array_filter(array_map('trim', explode(',', $interestsRaw))));
        }

        $data = [
            'parish_id'                => $this->getOperationalParishId(),
            'first_name'               => $this->request->getPost('first_name'),
            'last_name'                => $this->request->getPost('last_name'),
            'email'                    => $this->request->getPost('email'),
            'phone'                    => $this->request->getPost('phone'),
            'address'                  => $this->request->getPost('address'),
            'preferred_contact_method' => $this->request->getPost('preferred_contact_method') ?: null,
            'source'                   => $this->request->getPost('source'),
            'interests'                => $interests,
            'visit_date'               => $this->request->getPost('visit_date'),
            'service_attended'         => $this->request->getPost('service_attended'),
            'followed_up'              => 0,
            'notes'                    => $this->request->getPost('notes'),
        ];

        $parishId = (int) $this->getOperationalParishId();
        try {
            $intake   = $this->visitorDirectory->intake($data, $parishId);
            $personId = (int) $intake['person_id'];

            if (! $intake['is_new'] && $personId > 0) {
                // Dedup matched an existing person — apply the submitted edits.
                $this->visitorDirectory->update($personId, $parishId, $data);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Visitor intake failed: ' . $e->getMessage());
            $personId = 0;
        }

        if ($personId > 0) {
            $this->audit()->log('visitor.create', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'visitor',
                'entity_id' => $personId,
                'new_values' => [
                    'visit_date' => (string) ($data['visit_date'] ?? ''),
                    'service_attended' => (string) ($data['service_attended'] ?? ''),
                    'followed_up' => 0,
                ],
            ]);

            return redirect()->to('/visitors/view/' . $personId)->with('success', lang('Visitors.visitorCreateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Visitors.visitorCreateFailed'));
    }

    /**
     * Show edit visitor form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        return view('Clergy\Views\Visitors/edit', array_merge($this->getCommonViewData(lang('Visitors.pageTitleEdit')), ['visitor' => $visitor]));
    }

    /**
     * Update visitor
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        $rules = [
            'first_name'               => 'required|min_length[2]',
            'last_name'                => 'required|min_length[2]',
            'email'                    => 'permit_empty|valid_email',
            'phone'                    => 'permit_empty',
            'visit_date'               => 'required|valid_date',
            'address'                  => 'permit_empty|max_length[500]',
            'source'                   => 'permit_empty|max_length[100]',
            'preferred_contact_method' => 'permit_empty|in_list[email,text,call,mail]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $interestsRaw = $this->request->getPost('interests');
        $interests    = null;
        if ($interestsRaw !== null && $interestsRaw !== '') {
            $decoded   = json_decode($interestsRaw, true);
            $interests = json_last_error() === JSON_ERROR_NONE
                ? json_encode($decoded)
                : json_encode(array_filter(array_map('trim', explode(',', $interestsRaw))));
        }

        // Decode opted_out_channels from the hidden JSON field the view serialises.
        $optedOutRaw = $this->request->getPost('opted_out_channels');
        $optedOut    = null;
        if ($optedOutRaw !== null && $optedOutRaw !== '') {
            $decoded  = json_decode($optedOutRaw, true);
            $optedOut = is_array($decoded) ? json_encode(array_values($decoded)) : '[]';
        } else {
            $optedOut = '[]';
        }

        $timezone = $this->request->getPost('preferred_timezone') ?: null;

        $data = [
            'first_name'               => $this->request->getPost('first_name'),
            'last_name'                => $this->request->getPost('last_name'),
            'email'                    => $this->request->getPost('email'),
            'phone'                    => $this->request->getPost('phone'),
            'address'                  => $this->request->getPost('address'),
            'preferred_contact_method' => $this->request->getPost('preferred_contact_method') ?: null,
            'source'                   => $this->request->getPost('source'),
            'interests'                => $interests,
            'opted_out_channels'       => $optedOut,
            'preferred_timezone'       => $timezone,
            'visit_date'               => $this->request->getPost('visit_date'),
            'service_attended'         => $this->request->getPost('service_attended'),
            'followed_up'              => $this->request->getPost('followed_up') ? 1 : 0,
            'notes'                    => $this->request->getPost('notes'),
        ];

        if ($this->visitorDirectory->update((int) $id, (int) $this->getOperationalParishId(), $data)) {
            $this->audit()->log('visitor.update', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'visitor',
                'entity_id' => (int) $id,
                'old_values' => [
                    'visit_date' => (string) ($visitor['visit_date'] ?? ''),
                    'service_attended' => (string) ($visitor['service_attended'] ?? ''),
                    'followed_up' => (int) ($visitor['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($visitor['converted_to_member_at'] ?? ''),
                ],
                'new_values' => [
                    'visit_date' => (string) ($data['visit_date'] ?? ''),
                    'service_attended' => (string) ($data['service_attended'] ?? ''),
                    'followed_up' => (int) ($data['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($visitor['converted_to_member_at'] ?? ''),
                ],
            ]);

            return redirect()->to('/visitors/view/' . $id)->with('success', lang('Visitors.visitorUpdateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Visitors.visitorUpdateFailed'));
    }

    /**
     * Delete visitor
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('visitor.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')]);
        }

        // Require a reason (min 10 chars) — accept from POST body or JSON body.
        $deleteReason = trim((string) ($this->request->getPost('delete_reason') ?? ''));
        if ($deleteReason === '') {
            $body         = (array) ($this->request->getJSON(true) ?? []);
            $deleteReason = trim((string) ($body['delete_reason'] ?? ''));
        }

        if (strlen($deleteReason) < 10) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.deleteReasonRequired'),
            ]);
        }

        // Count pending followup log entries before the delete detaches them.
        $pendingSequences = $this->followupLogModel->countPendingForPerson((int) $id);

        if ($this->visitorDirectory->delete((int) $id, (int) $this->getOperationalParishId())) {
            $this->audit()->log('visitor.delete', [
                'parish_id'  => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'visitor',
                'entity_id'  => (int) $id,
                'old_values' => [
                    'visit_date'             => (string) ($visitor['visit_date'] ?? ''),
                    'service_attended'       => (string) ($visitor['service_attended'] ?? ''),
                    'followed_up'            => (int)    ($visitor['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($visitor['converted_to_member_at'] ?? ''),
                ],
                'meta' => [
                    'delete_reason'            => $deleteReason,
                    'pending_sequences_cancelled' => $pendingSequences,
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.visitorDeleteSuccess')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorDeleteFailed')]);
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

        if (! $this->visitorDirectory->affiliationExists((int) $log['person_id'], (int) $log['parish_id'])
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

        $notes = $this->request->getPost('notes');
        $ok    = $this->followupLogModel->markExecuted((int) $logId, [
            'triggered_by' => (int) auth()->id(),
            'triggered_at' => date('Y-m-d H:i:s'),
            'notes'        => $notes,
        ]);

        if ($ok) {
            $this->audit()->log('visitor.followup.trigger', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'followup_log',
                'entity_id'   => $logId,
                'new_values'  => ['status' => 'sent', 'triggered_by' => (int) auth()->id()],
                'meta'        => ['visitor_id' => (int) $log['person_id']],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Visitors.followupTriggered')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.followupTriggerFailed')], 500);
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

    /**
     * Convert visitor to member
     */
    public function convertToMember($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        $currentUserId = (int) (auth()->id());
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        // The visitor row id is the canonical person_id after the T4.5 migration.
        $personId = (int) $id;

        $converted = PersonConversionService::convertToMember(
            $personId,
            $parishId,
            null,
            null,
            null,
            $currentUserId
        );

        if (! $converted) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorConversionFailed'));
        }

        return redirect()->to('/people/view/' . $personId)
            ->with('success', lang('Visitors.visitorStatusUpdated'));
    }

    /**
     * Redirect to the member create form with the visitor's details pre-filled
     * (flashdata 'visitor_data' consumed by Members::create). The visitor_id is
     * carried through so store() can complete the conversion bookkeeping.
     */
    public function createAccountFromVisitor($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requirePermission('member.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitor = $this->visitorDirectory->findByPerson((int) $id, (int) $this->getOperationalParishId());

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (!empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)->with('error', lang('Visitors.visitorAlreadyConverted'));
        }

        session()->setFlashdata('visitor_data', [
            'visitor_id'   => (int) $visitor['id'],
            'role'       => 'member',
            'first_name' => (string) ($visitor['first_name'] ?? ''),
            'last_name'  => (string) ($visitor['last_name'] ?? ''),
            'email'      => (string) ($visitor['email'] ?? ''),
            'phone'      => (string) ($visitor['phone'] ?? ''),
            'address'    => (string) ($visitor['address'] ?? ''),
        ]);

        return redirect()->to('/members/create');
    }

    // ------------------------------------------------------------------
    // Follow-up Manual Timeline
    // ------------------------------------------------------------------

    /**
     * GET  visitors/followup-manual/:visitorId
     * Display the full follow-up timeline for a single visitor with inline
     * action controls (Mark Done, Reschedule, Skip, Print Label).
     */
    public function followupManual(int $visitorId)
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

        if (! $this->visitorDirectory->affiliationExists((int) $log['person_id'], (int) $log['parish_id'])
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

        if (! $this->visitorDirectory->affiliationExists((int) $log['person_id'], (int) $log['parish_id'])
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

        if (! $this->visitorDirectory->affiliationExists((int) $log['person_id'], (int) $log['parish_id'])
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

        if (! $this->visitorDirectory->affiliationExists((int) $log['person_id'], (int) $log['parish_id'])
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

    /**
     * GET  api/followup/pending-calls  (AJAX/JSON)
     *
     * Returns the count and a brief summary of pending call tasks for the
     * current parish.  Called by the navbar badge poller every 30 seconds.
     * Responds with JSON so it is safe for XHR / Fetch consumers.
     *
     * Requires: followup.view
     */

    // =========================================================================
    // Visitor Hub Dashboard
    // =========================================================================

    /**
     * Consolidated visitor hub: KPI tiles, quick-action cards, recent visitors.
     *
     * Requires: visitors.view
     */
    public function dashboard()
    {
        if ($redirect = $this->requirePermission('visitor.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();

        // KPI: total visitors (all time) vs converted
        $totalVisitors    = $this->visitorDirectory->countForParish($parishId, false);
        $convertedCount = $this->visitorDirectory->convertedCountForParish($parishId);

        $notFollowedUp = $this->followupQueries->pendingDistinctPersonsCount($parishId);

        $inProgressCount = $this->followupQueries->inProgressVisitorsCount($parishId);

        // KPI: pending call tasks
        $callTaskModel   = model('CallTaskModel');
        $pendingCalls    = $callTaskModel->countPending($parishId);

        // KPI: active sequences
        $activeSequences = $this->followupSequenceModel->getActiveForParish($parishId);
        $sequenceCount   = count($activeSequences);

        // KPI: template count
        $templateModel   = model('FollowupTemplateModel');
        $templates       = $templateModel->getAllForParish($parishId);
        $templateCount   = count($templates);

        // Recent visitors (last 10)
        $recentVisitors = $this->visitorDirectory->recentForParish($parishId, 10);

        // Pending call tasks preview (top 5)
        $pendingCallTasks = array_slice($callTaskModel->getPendingForParish($parishId), 0, 5);

        // Per-visitor progress map for recent visitors 4-state badge
        $recentPersonIds = array_filter(array_column($recentVisitors, 'person_id'));
        $recentProgressMap = $this->followupQueries->progressMapForPersons($recentPersonIds);

        $data = [
            'totalVisitors'       => $totalVisitors,
            'convertedCount'    => $convertedCount,
            'notFollowedUp'     => $notFollowedUp,
            'inProgressCount'   => $inProgressCount,
            'pendingCalls'      => $pendingCalls,
            'sequenceCount'     => $sequenceCount,
            'templateCount'     => $templateCount,
            'recentVisitors'      => $recentVisitors,
            'recentProgressMap' => $recentProgressMap,
            'pendingCallTasks'  => $pendingCallTasks,
        ];

        return view(
            'Clergy\Views\VisitorDashboard/index',
            array_merge($this->getCommonViewData(lang('Visitors.hubTitle')), $data)
        );
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
