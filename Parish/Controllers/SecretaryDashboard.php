<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish secretary dashboard — records, reports, and vestry minutes hub
 * for the secretary role.
 */
class SecretaryDashboard extends BaseController
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

        // Recent vestry minutes (if table exists)
        $recentMinutes = [];
        if ($db->tableExists('vestry_meeting')) {
            $recentMinutes = $db->table('vestry_meeting')
                ->select('id, meeting_date, meeting_type, minutes_text')
                ->where('parish_id', $parishId)
                ->where('deleted_at', null)
                ->where('minutes_text IS NOT NULL')
                ->orderBy('meeting_date', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray();
        }

        // Recent parish reports (if table exists)
        $recentReports = [];
        if ($db->tableExists('parish_report')) {
            $recentReports = $db->table('parish_report')
                ->where('parish_id', $parishId)
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray();
        }

        $canExport = auth()->user()->can('report.export');

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishSecretaryDashboard')), [
            'parish_id' => $parishId,
            'recent_minutes' => $recentMinutes,
            'recent_reports' => $recentReports,
            'can_export' => $canExport,
        ]);

        return view('Parish\Views\Secretary\index', $data);
    }
}
