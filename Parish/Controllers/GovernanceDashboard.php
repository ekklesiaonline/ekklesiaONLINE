<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish governance dashboard — vestry meetings, motions, and report
 * certification status for vestry members, wardens, clerks, and secretaries.
 */
class GovernanceDashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/profile')->with('error', lang('App.errorNotFound'));
        }

        $parishId = (int) $parishId;
        $db = db_connect();

        // Recent vestry meetings (if the table exists)
        $recentMeetings = [];
        $openMotions = [];
        if ($db->tableExists('vestry_meeting')) {
            $recentMeetings = $db->table('vestry_meeting')
                ->where('parish_id', $parishId)
                ->where('deleted_at', null)
                ->orderBy('meeting_date', 'DESC')
                ->limit(10)
                ->get()
                ->getResultArray();

            if ($db->tableExists('vestry_motion')) {
                $openMotions = $db->table('vestry_motion vm')
                    ->select('vm.*, vm2.meeting_date')
                    ->join('vestry_meeting vm2', 'vm2.id = vm.meeting_id', 'inner')
                    ->where('vm2.parish_id', $parishId)
                    ->where('vm.status', 'open')
                    ->where('vm.deleted_at', null)
                    ->orderBy('vm.created_at', 'DESC')
                    ->limit(10)
                    ->get()
                    ->getResultArray();
            }
        }

        $canManageMeetings = auth()->user()->can('governance.vestry.manage');
        $canVote = auth()->user()->can('vestry.motion.vote');

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishGovernanceDashboard')), [
            'parish_id' => $parishId,
            'recent_meetings' => $recentMeetings,
            'open_motions' => $openMotions,
            'can_manage_meetings' => $canManageMeetings,
            'can_vote' => $canVote,
            'has_vestry_tables' => $db->tableExists('vestry_meeting'),
        ]);

        return view('Parish\Views\Governance\index', $data);
    }
}
