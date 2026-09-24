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
        $lifecycle     = $this->request->getGet('lifecycle'); // ''|visitor|regular_attender|converted|all
        $convertedWindow = $this->request->getGet('converted_window'); // ''|30|90|365 (days)

        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = $this->visitorDirectory->paginateForParish((int) $parishId, [
            'followed_up'    => $followedUp,
            'search'         => $search,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'followup_stage' => $followupStage,
            'source'         => $source,
            'lifecycle'      => $lifecycle,
            'converted_window' => $convertedWindow,
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
            'lifecycle'     => $lifecycle,
            'converted_window' => $convertedWindow,
            'progressMap'   => $progressMap,
            'attenderThreshold' => $this->visitorDirectory->attenderSuggestionThreshold((int) $parishId),
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

        // Aggregated notes feed (staff notes + visit + follow-up outcomes)
        $notesFeed = $personId > 0
            ? (new \App\Services\PersonNoteService())->feedForPerson($personId, (int) $visitor['parish_id'])
            : [];

        // Linkable in-parish users for the promote-to-member modal
        // (link_user mode picker). Any linked user in scope qualifies — the
        // typical target is a portal user still at visitor status, which the
        // member-only getParishMembers() list would exclude.
        $linkableMembers = auth()->user()->can('visitor.edit')
            ? db_connect()->table('parish_affiliation pa')
                ->select('pa.linked_user_id AS user_id, u.username, ai.secret AS email, p.first_name, p.last_name')
                ->join('users u', 'u.id = pa.linked_user_id')
                ->join('profile p', 'p.user_id = u.id AND p.deleted_at IS NULL', 'left')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->where('pa.parish_id', (int) $visitor['parish_id'])
                ->where('pa.deleted_at', null)
                ->where('pa.linked_user_id IS NOT NULL', null, false)
                ->groupBy(['pa.linked_user_id', 'u.username', 'ai.secret', 'p.first_name', 'p.last_name'])
                ->orderBy('p.first_name', 'ASC')
                ->orderBy('p.last_name', 'ASC')
                ->get()
                ->getResultArray()
            : [];

        $data = [
            'visitor'            => $visitor,
            'tasks'            => $tasks,
            'visits'           => $visits,
            'followupTimeline' => $followupTimeline,
            'nextPending'      => $nextPending,
            'sequences'        => $sequences,
            'notesFeed'        => $notesFeed,
            'linkableMembers'  => $linkableMembers,
            'attenderThreshold' => $this->visitorDirectory->attenderSuggestionThreshold((int) $visitor['parish_id']),
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

        // Register members and already-converted rows are managed from the
        // People directory — the visitor delete must refuse them.
        if (!empty($visitor['converted_to_member_at'])
            || in_array((string) ($visitor['membership_status'] ?? ''), ['member', 'inactive'], true)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Visitors.deleteGuardConverted'),
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
     * POST visitors/promote/:personId
     *
     * The single canonical visitor→member promotion endpoint. Three modes:
     *   mark_member     — person only, no portal account.
     *   link_user       — link an existing in-parish user account.
     *   create_account  — create a new portal account (requires member.create).
     *
     * All modes run through PersonConversionService::convertToMember, which
     * transitions the affiliation via MembershipPathwayService (history row
     * written), links/creates the user, marks conversion markers, and skips
     * pending follow-ups.
     */
    public function promote($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (!empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorAlreadyConverted'));
        }

        $rules = [
            'mode' => 'required|in_list[mark_member,link_user,create_account]',
        ];

        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'link_user') {
            $rules['link_user_id'] = 'required|is_natural_no_zero';
        } elseif ($mode === 'create_account') {
            $rules['account_email']    = 'required|valid_email';
            $rules['account_password'] = 'required|min_length[8]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);
        $actor    = (int) auth()->id();

        if ($mode === 'link_user') {
            $linkUserId = (int) $this->request->getPost('link_user_id');

            // The linked account must already belong to this parish.
            $inScope = db_connect()->table('parish_affiliation')
                ->where('linked_user_id', $linkUserId)
                ->where('parish_id', $parishId)
                ->where('deleted_at IS NULL', null, false)
                ->countAllResults() > 0;

            if (! $inScope) {
                return redirect()->back()->with('error', lang('Visitors.promoteLinkOutOfScope'));
            }

            $converted = PersonConversionService::convertToMember(
                $personId, $parishId, $linkUserId, null, null, $actor
            );
        } elseif ($mode === 'create_account') {
            if ($redirect = $this->requirePermission('member.create')) {
                return $redirect;
            }

            $converted = PersonConversionService::convertToMember(
                $personId,
                $parishId,
                null,
                (string) $this->request->getPost('account_email'),
                (string) $this->request->getPost('account_password'),
                $actor
            );
        } else {
            $converted = PersonConversionService::convertToMember(
                $personId, $parishId, null, null, null, $actor
            );
        }

        if (! $converted) {
            return redirect()->back()->with('error', lang('Visitors.visitorConversionFailed'));
        }

        $this->audit()->log('visitor.promote_member', [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'new_values'  => ['membership_status' => 'member'],
            'meta'        => ['mode' => $mode],
        ]);

        // People::view() keys on profile.id, not person.id — resolve the
        // profile so the post-conversion landing is the canonical person page.
        // Visitors promoted without a portal user may have no profile yet;
        // fall back to the (now-converted) visitor detail page in that case.
        $profileId = (int) (db_connect()->table('profile')
            ->where('person_id', $personId)
            ->where('deleted_at IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray()['id'] ?? 0);

        $target = $profileId > 0 ? '/people/view/' . $profileId : '/visitors/view/' . $personId;

        return redirect()->to($target)
            ->with('success', lang('Visitors.promoteMemberSuccess'));
    }

    /**
     * POST visitors/revert-conversion/:personId
     * Undo a visitor→member conversion: status back to visitor, markers cleared.
     */
    public function revertConversion($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorNotConverted'));
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);

        $this->visitorDirectory->revertConversion($personId, $parishId, (int) auth()->id());

        $this->audit()->log('visitor.revert_conversion', [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'old_values'  => ['membership_status' => 'member'],
            'new_values'  => ['membership_status' => 'visitor'],
        ]);

        return redirect()->to('/visitors/view/' . $personId)
            ->with('success', lang('Visitors.conversionRevertSuccess'));
    }

    /**
     * POST visitors/promote-attender/:personId
     * Promote a visitor to regular_attender through the canonical pathway.
     */
    public function promoteAttender($id)
    {
        return $this->attenderTransition($id, 'regular_attender');
    }

    /**
     * POST visitors/demote/:personId
     * Move a regular_attender back to visitor through the canonical pathway.
     */
    public function demoteToVisitor($id)
    {
        return $this->attenderTransition($id, 'visitor');
    }

    /**
     * Shared visitor ⇄ regular_attender transition handler. Only those two
     * targets are reachable here — member promotion is Phase-3 territory.
     */
    private function attenderTransition($id, string $toStatus)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (!empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorAlreadyConverted'));
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);
        $from     = (string) ($visitor['membership_status'] ?? 'visitor');

        if ($from === $toStatus) {
            return redirect()->back()
                ->with('error', lang('Visitors.transitionNoop'));
        }

        $reason = $toStatus === 'regular_attender'
            ? 'Promoted to regular attender'
            : 'Moved back to visitor';

        $result = (new \App\Services\MembershipPathwayService())->transitionForPerson(
            $personId,
            $parishId,
            $toStatus,
            (int) auth()->id(),
            $reason
        );

        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->with('error', lang('Visitors.transitionFailed'));
        }

        $this->audit()->log('visitor.' . ($toStatus === 'regular_attender' ? 'promote_attender' : 'demote'), [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'old_values'  => ['membership_status' => $from],
            'new_values'  => ['membership_status' => $toStatus],
        ]);

        $message = $toStatus === 'regular_attender'
            ? lang('Visitors.promoteAttenderSuccess')
            : lang('Visitors.demoteVisitorSuccess');

        return redirect()->back()->with('success', $message);
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
     * POST  visitors/notes/add/:personId
     * Append a staff note to the visitor's notes feed.
     */
    public function addNote(int $personId)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        if (! $this->visitorDirectory->visitorAffiliationExists($personId, $parishId)) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (! $this->validate(['note' => 'required|max_length[2000]'])) {
            return redirect()->to('visitors/view/' . $personId)
                ->with('error', lang('Visitors.noteInvalid'));
        }

        $noteId = (new \App\Services\PersonNoteService())->addNote(
            $personId,
            $parishId,
            (int) auth()->id(),
            trim((string) $this->request->getPost('note'))
        );

        if ($noteId === false) {
            return redirect()->to('visitors/view/' . $personId)
                ->with('error', lang('Visitors.noteSaveFailed'));
        }

        // Note body is freeform text — never written to the audit trail.
        $this->audit()->log('visitor.note_added', [
            'parish_id'   => $parishId,
            'entity_type' => 'person_note',
            'entity_id'   => $noteId,
            'meta'        => ['visitor_id' => $personId],
        ]);

        return redirect()->to('visitors/view/' . $personId)
            ->with('success', lang('Visitors.noteAdded'));
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

        // KPI: total visitors (all time) vs converted (rolling window,
        // configurable system-wide via visitor.converted_window_days)
        $convertedWindowDays = max(1, (int) service('systemSettings')->get('visitor.converted_window_days', 30));
        $totalVisitors    = $this->visitorDirectory->countForParish($parishId, false);
        $convertedCount = $this->visitorDirectory->convertedCountForParish($parishId, $convertedWindowDays);

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
            'convertedWindowDays' => $convertedWindowDays,
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
