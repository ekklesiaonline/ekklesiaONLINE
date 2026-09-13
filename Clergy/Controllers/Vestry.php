<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * Vestry governance controller — CRUD for meetings, motions, and votes.
 *
 * Access is gated by governance.vestry.view (read) and
 * governance.vestry.manage (write). Voting requires vestry.motion.vote.
 */
class Vestry extends BaseController
{
    /**
     * List vestry meetings for the current parish.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('governance.vestry.view')) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/parish/governance')->with('error', lang('App.errorNotFound'));
        }

        $meetings = model('VestryMeetingModel')->getParishMeetings((int) $parishId, 20);

        $data = array_merge($this->getCommonViewData(lang('Dashboard.vestryMeetings')), [
            'meetings' => $meetings,
            'can_manage' => auth()->user()->can('governance.vestry.manage'),
        ]);

        return view('Clergy\Views\Vestry\index', $data);
    }

    /**
     * View a single meeting with its motions and votes.
     */
    public function view(int $meetingId)
    {
        if ($redirect = $this->requirePermission('governance.vestry.view')) {
            return $redirect;
        }

        $meeting = model('VestryMeetingModel')->getMeetingWithMotions($meetingId);

        if (!$meeting) {
            return redirect()->to('/parish/governance')->with('error', lang('App.errorNotFound'));
        }

        $data = array_merge($this->getCommonViewData(lang('Dashboard.vestryMeetings')), [
            'meeting' => $meeting,
            'can_manage' => auth()->user()->can('governance.vestry.manage'),
            'can_vote' => auth()->user()->can('vestry.motion.vote'),
        ]);

        return view('Clergy\Views\Vestry\view', $data);
    }

    /**
     * Show the create meeting form.
     */
    public function createMeeting()
    {
        if ($redirect = $this->requirePermission('governance.vestry.manage')) {
            return $redirect;
        }

        $data = array_merge($this->getCommonViewData(lang('Dashboard.vestryMeetings')), [
            'meeting' => [],
        ]);

        return view('Clergy\Views\Vestry\_meeting_form', $data);
    }

    /**
     * Store a new meeting.
     */
    public function storeMeeting()
    {
        if ($redirect = $this->requirePermission('governance.vestry.manage')) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/parish/governance')->with('error', lang('App.errorNotFound'));
        }

        $rules = [
            'meeting_date' => 'required|valid_date',
            'meeting_type' => 'required|in_list[regular,special,annual]',
            'minutes_text' => 'permit_empty|string',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $meetingId = model('VestryMeetingModel')->insert([
            'parish_id' => (int) $parishId,
            'meeting_date' => $this->request->getPost('meeting_date'),
            'meeting_type' => $this->request->getPost('meeting_type'),
            'minutes_text' => $this->request->getPost('minutes_text'),
            'created_by' => (int) auth()->id(),
        ]);

        if ($meetingId) {
            try {
                $this->audit()->log('vestry.meeting.created', [
                    'entity_type' => 'vestry_meeting',
                    'entity_id' => (int) $meetingId,
                    'new_values' => [
                        'parish_id' => (int) $parishId,
                        'meeting_date' => $this->request->getPost('meeting_date'),
                        'meeting_type' => $this->request->getPost('meeting_type'),
                    ],
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to log vestry meeting creation: {message}', ['message' => $e->getMessage()]);
            }

            return redirect()->to('/vestry/view/' . (int) $meetingId)->with('success', 'Meeting created.');
        }

        return redirect()->back()->withInput()->with('error', 'Failed to create meeting.');
    }

    /**
     * Store a new motion on a meeting.
     */
    public function storeMotion(int $meetingId)
    {
        if ($redirect = $this->requirePermission('governance.vestry.manage')) {
            return $redirect;
        }

        $meeting = model('VestryMeetingModel')->find($meetingId);

        if (!$meeting) {
            return redirect()->to('/parish/governance')->with('error', lang('App.errorNotFound'));
        }

        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'body_text' => 'permit_empty|string',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $motionId = model('VestryMotionModel')->insert([
            'meeting_id' => $meetingId,
            'title' => $this->request->getPost('title'),
            'body_text' => $this->request->getPost('body_text'),
            'status' => 'open',
        ]);

        if ($motionId) {
            try {
                $this->audit()->log('vestry.motion.created', [
                    'entity_type' => 'vestry_motion',
                    'entity_id' => (int) $motionId,
                    'new_values' => [
                        'meeting_id' => $meetingId,
                        'title' => $this->request->getPost('title'),
                    ],
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to log vestry motion creation: {message}', ['message' => $e->getMessage()]);
            }

            return redirect()->to('/vestry/view/' . $meetingId)->with('success', 'Motion created.');
        }

        return redirect()->back()->withInput()->with('error', 'Failed to create motion.');
    }

    /**
     * Cast a vote on a motion (AJAX JSON).
     */
    public function castVote(int $motionId)
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authenticated'])->setStatusCode(401);
        }

        if (!auth()->user()->can('vestry.motion.vote')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authorized'])->setStatusCode(403);
        }

        $motion = model('VestryMotionModel')->find($motionId);

        if (!$motion) {
            return $this->response->setJSON(['success' => false, 'message' => 'Motion not found'])->setStatusCode(404);
        }

        if (($motion['status'] ?? 'open') !== 'open') {
            return $this->response->setJSON(['success' => false, 'message' => 'Motion is not open for voting'])->setStatusCode(400);
        }

        $vote = (string) $this->request->getPost('vote');

        if (!in_array($vote, ['yea', 'nay', 'abstain'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid vote choice'])->setStatusCode(400);
        }

        $userId = (int) auth()->id();
        $result = model('VestryVoteModel')->recordVote($motionId, $userId, $vote);

        if ($result) {
            try {
                $this->audit()->log('vestry.vote.cast', [
                    'entity_type' => 'vestry_motion',
                    'entity_id' => $motionId,
                    'meta' => ['user_id' => $userId, 'vote' => $vote],
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to log vestry vote: {message}', ['message' => $e->getMessage()]);
            }

            $tally = model('VestryVoteModel')->tallyVotes($motionId);

            return $this->response->setJSON(['success' => true, 'tally' => $tally]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to record vote'])->setStatusCode(500);
    }

    /**
     * Close a motion by tallying votes and setting the final status.
     */
    public function closeMotion(int $motionId)
    {
        if ($redirect = $this->requirePermission('governance.vestry.manage')) {
            return $redirect;
        }

        $motion = model('VestryMotionModel')->find($motionId);

        if (!$motion) {
            return redirect()->to('/parish/governance')->with('error', lang('App.errorNotFound'));
        }

        $result = model('VestryMotionModel')->closeMotion($motionId);

        try {
            $this->audit()->log('vestry.motion.closed', [
                'entity_type' => 'vestry_motion',
                'entity_id' => $motionId,
                'new_values' => $result,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log vestry motion close: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/vestry/view/' . (int) ($motion['meeting_id'] ?? 0))->with('success', 'Motion closed: ' . $result['status']);
    }
}
