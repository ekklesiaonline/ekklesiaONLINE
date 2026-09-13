<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataEncryption;

class Guests extends BaseController
{
    protected $guestModel;
    protected $personVisitModel;
    protected $followupLogModel;
    protected $followupSequenceModel;

    public function __construct()
    {
        $this->guestModel            = model('GuestModel');
        $this->personVisitModel      = model('PersonVisitModel');
        $this->followupLogModel      = model('FollowupLogModel');
        $this->followupSequenceModel = model('FollowupSequenceModel');
    }

    /**
     * Display list of guests
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('guest.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $churchId = $this->getOperationalParishId();
        $perPage  = 20;

        // Filters from query string
        $followedUp    = $this->request->getGet('followed_up');
        $search        = $this->request->getGet('search');
        $dateFrom      = $this->request->getGet('date_from');     // Y-m-d
        $dateTo        = $this->request->getGet('date_to');       // Y-m-d
        $followupStage = $this->request->getGet('followup_stage'); // pending|sent|failed|skipped|none
        $source        = $this->request->getGet('source');

        $builder = $this->guestModel->where('parish_id', $churchId);

        if ($followedUp !== null && $followedUp !== '') {
            $builder->where('followed_up', (int) $followedUp);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $builder->where('visit_date >=', $dateFrom);
        }
        if ($dateTo !== null && $dateTo !== '') {
            $builder->where('visit_date <=', $dateTo);
        }

        if ($source !== null && $source !== '') {
            $builder->where('source', $source);
        }

        // followup_stage: filter by presence / status of followup_log rows
        $validStages = ['pending', 'sent', 'failed', 'skipped'];
        if ($followupStage !== null && $followupStage !== '') {
            if ($followupStage === 'none') {
                $builder->where('person_id NOT IN (SELECT DISTINCT person_id FROM followup_log WHERE status IN ("sent","skipped"))', null, false);
            } elseif ($followupStage === 'inprogress') {
                $builder->where('person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status IN ("sent","skipped"))', null, false)
                    ->where('person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status = "pending")', null, false);
            } elseif (in_array($followupStage, $validStages, true)) {
                $builder->where(
                    "person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status = '{$followupStage}')",
                    null,
                    false
                );
            }
        }

        if ($search) {
            $emailHash = DataEncryption::emailIndex($search);
            $phoneHash = DataEncryption::phoneIndex($search);

            $builder->groupStart()
                ->like('first_name', $search)
                ->orLike('last_name', $search);

            if ($emailHash !== null) {
                $builder->orWhere('email_hash', $emailHash);
            }
            if ($phoneHash !== null) {
                $builder->orWhere('phone_hash', $phoneHash);
            }

            $builder->groupEnd();
        }

        $guests = $builder->orderBy('visit_date', 'DESC')->paginate($perPage);
        $guests = array_map(fn(array $row): array => $this->guestModel->decryptGuestRow($row), $guests);

        // Per-guest followup progress for 4-state badge (by person_id)
        $personIds = array_filter(array_column($guests, 'person_id'));
        $progressMap = [];
        if (!empty($personIds)) {
            $rows = db_connect()->table('followup_log')
                ->select('person_id, SUM(status IN ("sent","skipped")) AS done_count, SUM(status = "pending") AS pending_count')
                ->whereIn('person_id', $personIds)
                ->groupBy('person_id')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $progressMap[(int) $r['person_id']] = [
                    'done'    => (int) $r['done_count'],
                    'pending' => (int) $r['pending_count'],
                ];
            }
        }

        $data = [
            'guests'        => $guests,
            'pager'         => $this->guestModel->pager,
            'followed_up'   => $followedUp,
            'search'        => $search,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'followup_stage' => $followupStage,
            'source'        => $source,
            'progressMap'   => $progressMap,
        ];

        return view('Clergy\Views\Guests/index', array_merge($this->getCommonViewData(lang('Guests.pageTitle')), $data));
    }

    /**
     * Display single guest
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('guest.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($id);

        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestNotFound'));
        }

        // Legacy ad-hoc tasks (retired — table dropped; pass empty array for view compatibility)
        $tasks = [];

        $personId = (int) ($guest['person_id'] ?? 0);

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
            ? db_connect()->table('followup_log gfl')
                ->select('gfl.id, gfl.scheduled_date, gfl.status, fa.channel, fa.week_number, fa.template_id, ft.name AS template_name')
                ->join('followup_action fa',   'fa.id = gfl.action_id',  'left')
                ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
                ->where('gfl.person_id', $personId)
                ->where('gfl.status', 'pending')
                ->orderBy('gfl.scheduled_date', 'ASC')
                ->limit(1)
                ->get()
                ->getRowArray()
            : null;

        // Sequences available to assign
        $sequences = $this->followupSequenceModel->getActiveForParish((int) $guest['parish_id']);

        $data = [
            'guest'            => $guest,
            'tasks'            => $tasks,
            'visits'           => $visits,
            'followupTimeline' => $followupTimeline,
            'nextPending'      => $nextPending,
            'sequences'        => $sequences,
        ];

        return view('Clergy\Views\Guests/view', array_merge($this->getCommonViewData(lang('Guests.pageTitleView')), $data));
    }

    /**
     * Show create guest form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('guest.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Clergy\Views\Guests/create', $this->getCommonViewData(lang('Guests.pageTitleAdd')));
    }

    /**
     * Store new guest
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('guest.create')) {
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

        $guestId = $this->guestModel->insert($data);

        if ($guestId) {
            try {
                $intake = GuestIntakeService::record($data, (int) $this->getOperationalParishId());
                $this->guestModel->update($guestId, ['person_id' => $intake['person_id']]);
                $data['person_id'] = $intake['person_id'];
            } catch (\Throwable $e) {
                log_message('error', 'GuestIntakeService failed: ' . $e->getMessage());
            }

            // Create initial follow-up task
            $this->audit()->log('guest.create', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'guest',
                'entity_id' => (int) $guestId,
                'new_values' => [
                    'visit_date' => (string) ($data['visit_date'] ?? ''),
                    'service_attended' => (string) ($data['service_attended'] ?? ''),
                    'followed_up' => 0,
                ],
            ]);

            return redirect()->to('/guests/view/' . $guestId)->with('success', lang('Guests.guestCreateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Guests.guestCreateFailed'));
    }

    /**
     * Show edit guest form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($id);

        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestNotFound'));
        }

        return view('Clergy\Views\Guests/edit', array_merge($this->getCommonViewData(lang('Guests.pageTitleEdit')), ['guest' => $guest]));
    }

    /**
     * Update guest
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($id);

        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestNotFound'));
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

        if ($this->guestModel->update($id, $data)) {
            $this->audit()->log('guest.update', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'guest',
                'entity_id' => (int) $id,
                'old_values' => [
                    'visit_date' => (string) ($guest['visit_date'] ?? ''),
                    'service_attended' => (string) ($guest['service_attended'] ?? ''),
                    'followed_up' => (int) ($guest['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($guest['converted_to_member_at'] ?? ''),
                ],
                'new_values' => [
                    'visit_date' => (string) ($data['visit_date'] ?? ''),
                    'service_attended' => (string) ($data['service_attended'] ?? ''),
                    'followed_up' => (int) ($data['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($guest['converted_to_member_at'] ?? ''),
                ],
            ]);

            return redirect()->to('/guests/view/' . $id)->with('success', lang('Guests.guestUpdateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Guests.guestUpdateFailed'));
    }

    /**
     * Delete guest
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('guest.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($id);

        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')]);
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
                'message' => lang('Guests.deleteReasonRequired'),
            ]);
        }

        // Count pending followup log entries before the CASCADE delete wipes them.
        $pendingSequences = $this->followupLogModel->countPendingForGuest((int) $id);

        if ($this->guestModel->delete($id)) {
            $this->audit()->log('guest.delete', [
                'parish_id'  => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'guest',
                'entity_id'  => (int) $id,
                'old_values' => [
                    'visit_date'             => (string) ($guest['visit_date'] ?? ''),
                    'service_attended'       => (string) ($guest['service_attended'] ?? ''),
                    'followed_up'            => (int)    ($guest['followed_up'] ?? 0),
                    'converted_to_member_at' => (string) ($guest['converted_to_member_at'] ?? ''),
                ],
                'meta' => [
                    'delete_reason'            => $deleteReason,
                    'pending_sequences_cancelled' => $pendingSequences,
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Guests.guestDeleteSuccess')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestDeleteFailed')]);
    }

    // -----------------------------------------------------------------------
    // AJAX endpoints
    // -----------------------------------------------------------------------

    /**
     * GET guests/followup-status/:id
     * Returns the next pending sequence-driven action for a guest,
     * plus a summary count by status.
     */
    public function getFollowupStatus(int $guestId)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        $guest = $this->guestModel->find($guestId);
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 404);
        }

        $personId = (int) ($guest['person_id'] ?? 0);

        $next = db_connect()->table('followup_log gfl')
            ->select('gfl.id, gfl.scheduled_date, gfl.status, fa.channel, fa.week_number, fa.day_offset, ft.name AS template_name')
            ->join('followup_action fa',   'fa.id = gfl.action_id',  'left')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->where('gfl.person_id', $personId)
            ->where('gfl.status', 'pending')
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $counts = db_connect()->table('followup_log')
            ->select('status, COUNT(*) AS total')
            ->where('person_id', $personId)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $summary = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($counts as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $this->jsonResponse([
            'success'     => true,
            'next_action' => $next ?: null,
            'summary'     => $summary,
        ]);
    }

    /**
     * POST guests/followup-trigger/:logId
     * Manually marks a guest_followup_log entry as executed (status → sent).
     */
    public function triggerFollowupAction(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = db_connect()->table('followup_log')
            ->where('id', $logId)
            ->get()
            ->getRowArray();

        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupLogNotFound')], 404);
        }

        $guest = $this->guestModel->where('person_id', (int) $log['person_id'])->where('parish_id', (int) $log['parish_id'])->first();
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Guests.followupAlreadyProcessed'),
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
            $this->audit()->log('guest.followup.trigger', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'followup_log',
                'entity_id'   => $logId,
                'new_values'  => ['status' => 'sent', 'triggered_by' => (int) auth()->id()],
                'meta'        => ['guest_id' => (int) $log['person_id']],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Guests.followupTriggered')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupTriggerFailed')], 500);
    }

    /**
     * POST guests/assign-sequence/:guestId
     * Assigns a follow-up sequence to a guest, bulk-creating all log rows.
     */
    public function assignSequence(int $guestId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $guest = $this->guestModel->find($guestId);
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 404);
        }

        $sequenceId = (int) $this->request->getPost('sequence_id');
        if ($sequenceId < 1) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.sequenceRequired')], 422);
        }

        $visitDate = $guest['visit_date'] ?: date('Y-m-d');
        $count     = $this->followupLogModel->scheduleSequenceForGuest($guestId, $sequenceId, $visitDate);

        if ($count > 0) {
            $this->audit()->log('guest.sequence.assign', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'guest',
                'entity_id'   => $guestId,
                'new_values'  => ['sequence_id' => $sequenceId, 'actions_scheduled' => $count],
            ]);

            return $this->jsonResponse([
                'success' => true,
                'message' => lang('Guests.sequenceAssigned'),
                'count'   => $count,
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Guests.sequenceHasNoActions')], 422);
    }

    /**
     * Convert guest to member
     */
    public function convertToMember($id)
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($id);

        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestNotFound'));
        }

        $currentUserId = (int) (auth()->id());
        $churchId = (int) ($this->getOperationalParishId() ?? 0);

        $personId = (int) ($guest['person_id'] ?? 0);
        if ($personId <= 0) {
            try {
                $intake = GuestIntakeService::record($guest, $churchId);
                $personId = $intake['person_id'];
                $this->guestModel->update($id, ['person_id' => $personId]);
            } catch (\Throwable $e) {
                return redirect()->to('/guests')->with('error', lang('Guests.guestIntakeFailed'));
            }
        }

        $converted = PersonConversionService::convertToMember(
            $personId,
            $churchId,
            null,
            null,
            null,
            $currentUserId
        );

        if (! $converted) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestConversionFailed'));
        }

        return redirect()->to('/people/view/' . $personId)
            ->with('success', lang('Guests.guestStatusUpdated'));
    }

    // ------------------------------------------------------------------
    // Follow-up Manual Timeline
    // ------------------------------------------------------------------

    /**
     * GET  guests/followup-manual/:guestId
     * Display the full follow-up timeline for a single guest with inline
     * action controls (Mark Done, Reschedule, Skip, Print Label).
     */
    public function followupManual(int $guestId)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guest = $this->guestModel->find($guestId);
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/guests')->with('error', lang('Guests.guestNotFound'));
        }

        $personId = (int) ($guest['person_id'] ?? 0);

        $timeline = db_connect()->table('followup_log gfl')
            ->select('gfl.*, fa.channel, fa.week_number, fa.day_offset, fa.is_automated,
                      ft.name AS template_name, fs.name AS sequence_name')
            ->join('followup_action fa',   'fa.id = gfl.action_id',   'left')
            ->join('followup_template ft',  'ft.id = fa.template_id',  'left')
            ->join('followup_sequence fs',  'fs.id = fa.sequence_id',  'left')
            ->where('gfl.person_id', $personId)
            ->orderBy('gfl.scheduled_date', 'ASC')
            ->orderBy('gfl.id', 'ASC')
            ->get()
            ->getResultArray();

        // Merge orphaned call_tasks (log_id IS NULL means they pre-date the workflow
        // system and were never tied to a guest_followup_log entry).
        $orphanedCallTasks = db_connect()->table('call_task')
            ->where('person_id', $personId)
            ->where('log_id IS NULL', null, false)
            ->where('status !=', 'cancelled')
            ->orderBy('scheduled_date', 'ASC')
            ->get()
            ->getResultArray();

        if (!empty($orphanedCallTasks)) {
            $callTaskRows = array_map(static function (array $task): array {
                return [
                    'id'                => (int) $task['id'],
                    'guest_id'          => (int) $task['guest_id'],
                    'action_id'         => null,
                    'scheduled_date'    => $task['scheduled_date'] ?? null,
                    'status'            => $task['status'] === 'completed' ? 'sent' : $task['status'],
                    'executed_at'       => $task['completed_at'] ?? null,
                    'notes'             => $task['notes'] ?? null,
                    'response_metadata' => null,
                    'channel'           => 'call',
                    'week_number'       => null,
                    'day_offset'        => null,
                    'is_automated'      => 0,
                    'template_name'     => null,
                    'sequence_name'     => null,
                    '_source'           => 'call_task',
                ];
            }, $orphanedCallTasks);

            $timeline = array_merge($timeline, $callTaskRows);
            usort($timeline, static function (array $a, array $b): int {
                $cmp = strcmp($a['scheduled_date'] ?? '', $b['scheduled_date'] ?? '');
                return $cmp !== 0 ? $cmp : ((int) ($a['id'] ?? 0)) - ((int) ($b['id'] ?? 0));
            });
        }

        $data = [
            'guest'    => $guest,
            'timeline' => $timeline,
        ];

        return view(
            'Clergy\Views\Guests/followup_manual',
            array_merge($this->getCommonViewData(lang('Guests.followupManualTitle')), $data)
        );
    }

    /**
     * POST  guests/followup-log/mark-done/:logId  (AJAX)
     * Mark a pending followup log entry as done (status → sent).
     */
    public function logMarkDone(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = db_connect()->table('followup_log')->where('id', $logId)->get()->getRowArray();
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupLogNotFound')], 404);
        }

        $guest = $this->guestModel->where('person_id', (int) $log['person_id'])->where('parish_id', (int) $log['parish_id'])->first();
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Guests.followupAlreadyProcessed'),
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

        $this->audit()->log('guest.followup.manual_done', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['status' => 'sent'],
            'meta'        => ['guest_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Guests.followupMarkedDone')]);
    }

    /**
     * POST  guests/followup-log/reschedule/:logId  (AJAX)
     * Update the scheduled_date of a pending log entry.
     */
    public function logReschedule(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = db_connect()->table('followup_log')->where('id', $logId)->get()->getRowArray();
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupLogNotFound')], 404);
        }

        $guest = $this->guestModel->where('person_id', (int) $log['person_id'])->where('parish_id', (int) $log['parish_id'])->first();
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Guests.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $newDate = trim((string) ($this->request->getPost('new_date') ?? ''));
        if ($newDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || strtotime($newDate) === false) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupInvalidDate')], 422);
        }

        $reason = trim((string) ($this->request->getPost('notes') ?? ''));

        $this->followupLogModel->update($logId, [
            'scheduled_date' => $newDate,
            'notes'          => $reason !== '' ? $reason : null,
        ]);

        $this->audit()->log('guest.followup.rescheduled', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['scheduled_date' => $newDate],
            'meta'        => ['guest_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse([
            'success'  => true,
            'message'  => lang('Guests.followupRescheduled'),
            'new_date' => $newDate,
        ]);
    }

    /**
     * POST  guests/followup-log/skip/:logId  (AJAX)
     * Mark a pending log entry as skipped.
     */
    public function logSkip(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = db_connect()->table('followup_log')->where('id', $logId)->get()->getRowArray();
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupLogNotFound')], 404);
        }

        $guest = $this->guestModel->where('person_id', (int) $log['person_id'])->where('parish_id', (int) $log['parish_id'])->first();
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Guests.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $notes = trim((string) ($this->request->getPost('notes') ?? ''));
        $this->followupLogModel->markSkipped($logId, $notes !== '' ? $notes : null);

        $this->audit()->log('guest.followup.skipped', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'meta'        => ['guest_id' => (int) $log['person_id']],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Guests.followupSkipped')]);
    }

    /**
     * POST  guests/followup-log/mark-failed/:logId  (AJAX)
     * Manually mark a pending follow-up log entry as failed with a required reason.
     */
    public function logMarkFailed(int $logId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $log = db_connect()->table('followup_log')->where('id', $logId)->get()->getRowArray();
        if (!$log) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.followupLogNotFound')], 404);
        }

        $guest = $this->guestModel->where('person_id', (int) $log['person_id'])->where('parish_id', (int) $log['parish_id'])->first();
        if (!$guest || $guest['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.guestNotFound')], 403);
        }

        if ($log['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Guests.followupAlreadyProcessed'),
                'status'  => $log['status'],
            ], 422);
        }

        $reason = trim((string) ($this->request->getPost('reason') ?? ''));
        if ($reason === '') {
            return $this->jsonResponse(['success' => false, 'message' => lang('Guests.markFailedReasonRequired')], 422);
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

        $this->audit()->log('guest.followup.manual_failed', [
            'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'new_values'  => ['status' => 'failed'],
            'meta'        => ['guest_id' => (int) $log['person_id'], 'reason' => $reason],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Guests.markFailedSuccess')]);
    }

    /**
     * GET  api/followup/pending-calls  (AJAX/JSON)
     *
     * Returns the count and a brief summary of pending call tasks for the
     * current church.  Called by the navbar badge poller every 30 seconds.
     * Responds with JSON so it is safe for XHR / Fetch consumers.
     *
     * Requires: followup.view
     */

    // =========================================================================
    // Guest Hub Dashboard
    // =========================================================================

    /**
     * Consolidated guest hub: KPI tiles, quick-action cards, recent guests.
     *
     * Requires: guests.view
     */
    public function dashboard()
    {
        if ($redirect = $this->requirePermission('guest.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $churchId = (int) $this->getOperationalParishId();

        // KPI: total guests (all time) vs converted
        $totalGuests    = $this->guestModel->getParishGuestCount($churchId, false);
        $convertedCount = (int) $this->guestModel
            ->where('parish_id', $churchId)
            ->where('converted_to_member_at IS NOT NULL', null, false)
            ->countAllResults();

        $notFollowedUp = (int) (db_connect()
            ->table('followup_log gfl')
            ->select('COUNT(DISTINCT gfl.person_id) AS cnt')
            ->where('gfl.status', 'pending')
            ->where('gfl.parish_id', $churchId)
            ->get()->getRowArray()['cnt'] ?? 0);

        $inProgressCount = (int) db_connect()
            ->table('guest g')
            ->where('g.parish_id', $churchId)
            ->where('g.followed_up', 0)
            ->where('g.converted_to_member_at IS NULL', null, false)
            ->whereIn('g.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->whereIn('status', ['sent', 'skipped']);
            })
            ->whereIn('g.person_id', function ($subQ) {
                $subQ->select('person_id')->from('followup_log')
                    ->where('status', 'pending');
            })
            ->countAllResults();

        // KPI: pending call tasks
        $callTaskModel   = model('CallTaskModel');
        $pendingCalls    = $callTaskModel->countPending($churchId);

        // KPI: active sequences
        $activeSequences = $this->followupSequenceModel->getActiveForParish($churchId);
        $sequenceCount   = count($activeSequences);

        // KPI: template count
        $templateModel   = model('FollowupTemplateModel');
        $templates       = $templateModel->getAllForParish($churchId);
        $templateCount   = count($templates);

        // Recent guests (last 10)
        $recentGuests = $this->guestModel->getRecentGuests($churchId, 10);
        foreach ($recentGuests as &$row) {
            $row = $this->guestModel->decryptGuestRow($row);
        }
        unset($row);

        // Pending call tasks preview (top 5)
        $pendingCallTasks = array_slice($callTaskModel->getPendingForParish($churchId), 0, 5);

        // Per-guest progress map for recent guests 4-state badge
        $recentPersonIds = array_filter(array_column($recentGuests, 'person_id'));
        $recentProgressMap = [];
        if (!empty($recentPersonIds)) {
            $pRows = db_connect()->table('followup_log')
                ->select('person_id, SUM(status IN ("sent","skipped")) AS done_count, SUM(status = "pending") AS pending_count')
                ->whereIn('person_id', $recentPersonIds)
                ->groupBy('person_id')
                ->get()->getResultArray();
            foreach ($pRows as $r) {
                $recentProgressMap[(int) $r['person_id']] = [
                    'done'    => (int) $r['done_count'],
                    'pending' => (int) $r['pending_count'],
                ];
            }
        }

        $data = [
            'totalGuests'       => $totalGuests,
            'convertedCount'    => $convertedCount,
            'notFollowedUp'     => $notFollowedUp,
            'inProgressCount'   => $inProgressCount,
            'pendingCalls'      => $pendingCalls,
            'sequenceCount'     => $sequenceCount,
            'templateCount'     => $templateCount,
            'recentGuests'      => $recentGuests,
            'recentProgressMap' => $recentProgressMap,
            'pendingCallTasks'  => $pendingCallTasks,
        ];

        return view(
            'Clergy\Views\GuestDashboard/index',
            array_merge($this->getCommonViewData(lang('Guests.hubTitle')), $data)
        );
    }

    public function pendingCallsApi()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            // Route filter handles this first; this guard is defence-in-depth.
            // Discard the redirect and return a JSON 403 for the XHR caller.
            return $this->jsonResponse(['success' => false, 'message' => lang('App.errorForbidden')], 403);
        }

        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        if ($churchId <= 0) {
            return $this->jsonResponse(['success' => true, 'count' => 0, 'items' => []]);
        }

        /** @var \App\Models\CallTaskModel $callTaskModel */
        $callTaskModel = model('CallTaskModel');
        $tasks = $callTaskModel->getPendingForParish($churchId);

        $items = array_map(static function (array $t): array {
            return [
                'id'             => (int) $t['id'],
                'guest_id'       => (int) $t['guest_id'],
                'guest_name'     => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')),
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
