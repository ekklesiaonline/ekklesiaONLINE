<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\VisitorDirectoryService;
use App\Services\VisitorFollowupService;

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

            $session = session();
            $session->setFlashdata('success', lang('Visitors.visitorCreateSuccess'));

            if (!(new \App\Services\FollowupSequenceService())->hasDefaultForParish($parishId)) {
                $session->setFlashdata('warning', lang('Visitors.noDefaultFollowupSequence'));
            }

            return redirect()->to('/visitors/view/' . $personId);
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
}
