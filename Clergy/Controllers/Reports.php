<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * Clergy\Controllers\Reports
 *
 * Followup-focused reporting for parish admins.
 *
 * Routes (all require permission:report.view):
 *   GET  guests/reports/followup-completion         → followupCompletion()
 *   GET  guests/reports/roi-second-visit            → roiSecondVisit()
 *   GET  guests/reports/export-csv/(:segment)       → exportCsv($reportType)
 */
class Reports extends BaseController
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // 1. Followup completion rate  (bar chart)
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * GET  guests/reports/followup-completion
     *
     * Shows, for each followup-sequence week number (1–N), what percentage of
     * scheduled actions were completed (status = 'sent') within the selected date
     * window.  Default window: last 3 months.
     */
    public function followupCompletion()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }
        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId  = (int) ($this->getOperationalParishId() ?? 0);
        $startDate = $this->sanitizeDate(
            $this->request->getGet('start_date'),
            date('Y-m-d', strtotime('-3 months'))
        );
        $endDate   = $this->sanitizeDate(
            $this->request->getGet('end_date'),
            date('Y-m-d')
        );

        $rows = $this->queryCompletionByWeek($parishId, $startDate, $endDate);

        // ---- Chart.js dataset ----
        $labels    = [];
        $completed = [];
        $missed    = [];
        $pctsRaw   = [];

        foreach ($rows as $row) {
            $week      = (int) $row['week_number'];
            $total     = (int) $row['total_actions'];
            $done      = (int) $row['completed_actions'];
            $pct       = $total > 0 ? round($done / $total * 100, 1) : 0.0;

            $labels[]    = lang('Guests.rptWeek') . ' ' . $week;
            $completed[] = $done;
            $missed[]    = $total - $done;
            $pctsRaw[]   = $pct;
        }

        $data = array_merge($this->getCommonViewData('Followup Completion'), [
            'report_start_date' => $startDate,
            'report_end_date'   => $endDate,
            'rows'              => $rows,
            'chart_labels'      => json_encode($labels),
            'chart_completed'   => json_encode($completed),
            'chart_missed'      => json_encode($missed),
            'chart_pcts'        => json_encode($pctsRaw),
        ]);

        return view('Clergy\Views\Reports/followup_completion', $data);
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // 2. ROI – did followup lead to a second visit?
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * GET  guests/reports/roi-second-visit
     *
     * Compares guests who received at least one sent followup action versus those who
     * did not.  "Return rate" is measured as conversion to membership
     * (converted_to_member_at IS NOT NULL), which is the only durable second-visit
     * signal stored in the schema.
     */
    public function roiSecondVisit()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }
        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId  = (int) ($this->getOperationalParishId() ?? 0);
        $startDate = $this->sanitizeDate(
            $this->request->getGet('start_date'),
            date('Y-m-d', strtotime('-3 months'))
        );
        $endDate   = $this->sanitizeDate(
            $this->request->getGet('end_date'),
            date('Y-m-d')
        );

        [$withFollowup, $withoutFollowup] = $this->queryRoiCohorts($parishId, $startDate, $endDate);

        // ---- Chart.js dataset (grouped bar: total vs converted per cohort) ----
        $wfTotal     = (int) ($withFollowup['total_guests'] ?? 0);
        $wfConverted = (int) ($withFollowup['converted'] ?? 0);
        $wfPct       = $wfTotal > 0 ? round($wfConverted / $wfTotal * 100, 1) : 0.0;

        $nfTotal     = (int) ($withoutFollowup['total_guests'] ?? 0);
        $nfConverted = (int) ($withoutFollowup['converted'] ?? 0);
        $nfPct       = $nfTotal > 0 ? round($nfConverted / $nfTotal * 100, 1) : 0.0;

        // Also retrieve per-week breakdown for the detailed table
        $weekBreakdown = $this->queryWeeklyGuestVolume($parishId, $startDate, $endDate);

        $data = array_merge($this->getCommonViewData('ROI – Second Visit'), [
            'report_start_date'  => $startDate,
            'report_end_date'    => $endDate,
            'with_followup'      => array_merge($withFollowup, ['pct' => $wfPct]),
            'without_followup'   => array_merge($withoutFollowup, ['pct' => $nfPct]),
            'week_breakdown'     => $weekBreakdown,
            // Chart data
            'chart_labels'       => json_encode([lang('Guests.rptReceivedFollowup'), lang('Guests.rptNoFollowup')]),
            'chart_total'        => json_encode([$wfTotal, $nfTotal]),
            'chart_converted'    => json_encode([$wfConverted, $nfConverted]),
            'chart_pcts'         => json_encode([$wfPct, $nfPct]),
        ]);

        return view('Clergy\Views\Reports/roi_second_visit', $data);
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // 3. CSV export
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * GET  guests/reports/export-csv/{reportType}
     *
     * Supported reportType values:
     *   followup-completion  – week-level completion summary
     *   roi-second-visit     – cohort comparison summary
     *   guests-all           – full guest list suitable for mail-merge
     */
    public function exportCsv(string $reportType = 'guests-all')
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }
        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId  = (int) ($this->getOperationalParishId() ?? 0);
        $startDate = $this->sanitizeDate(
            $this->request->getGet('start_date'),
            date('Y-m-d', strtotime('-3 months'))
        );
        $endDate   = $this->sanitizeDate(
            $this->request->getGet('end_date'),
            date('Y-m-d')
        );

        $allowed = ['followup-completion', 'roi-second-visit', 'guests-all'];
        if (! in_array($reportType, $allowed, true)) {
            $reportType = 'guests-all';
        }

        [$headers, $rows] = match ($reportType) {
            'followup-completion' => $this->csvFollowupCompletion($parishId, $startDate, $endDate),
            'roi-second-visit'    => $this->csvRoiSecondVisit($parishId, $startDate, $endDate),
            default               => $this->csvGuestsAll($parishId, $startDate, $endDate),
        };

        $this->audit()->log('report.export_csv', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_report',
            'meta'        => [
                'report_type' => $reportType,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'row_count'   => count($rows),
            ],
        ]);

        $filename = 'report_' . $reportType . '_' . date('Ymd') . '.csv';
        $handle   = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv); // UTF-8 BOM for Excel compatibility
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Private – data queries
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * Per-week completion stats.
     *
     * Returns rows: week_number, total_actions, completed_actions
     */
    private function queryCompletionByWeek(int $parishId, string $startDate, string $endDate): array
    {
        return $this->db
            ->table('followup_log gfl')
            ->select([
                'fa.week_number',
                'COUNT(gfl.id) AS total_actions',
                'SUM(CASE WHEN gfl.status = \'sent\' THEN 1 ELSE 0 END) AS completed_actions',
            ])
            ->join('followup_action fa', 'fa.id = gfl.action_id')
            ->where('gfl.parish_id', $parishId)
            ->where('gfl.scheduled_date >=', $startDate)
            ->where('gfl.scheduled_date <=', $endDate)
            ->where('fa.week_number IS NOT NULL', null, false)
            ->groupBy('fa.week_number')
            ->orderBy('fa.week_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return two cohort rows: [withFollowup, withoutFollowup]
     * Each row has keys: total_guests, converted
     */
    private function queryRoiCohorts(int $parishId, string $startDate, string $endDate): array
    {
        $db = $this->db;
        $directory = new \App\Services\GuestDirectoryService();
        $visitDate = $directory->firstVisitDateSql();

        $selects = [
            'COUNT(DISTINCT pa.person_id) AS total_guests',
            'SUM(CASE WHEN pa.converted_to_member_at IS NOT NULL THEN 1 ELSE 0 END) AS converted',
        ];

        $withFollowup = $directory->scopeBuilder($parishId)
            ->select($selects)
            ->where($visitDate . ' >= ' . $db->escape($startDate), null, false)
            ->where($visitDate . ' <= ' . $db->escape($endDate), null, false)
            ->where("EXISTS (SELECT 1 FROM followup_log gfl WHERE gfl.person_id = pa.person_id AND gfl.status = 'sent')", null, false)
            ->get()
            ->getRowArray() ?? ['total_guests' => 0, 'converted' => 0];

        $withoutFollowup = $directory->scopeBuilder($parishId)
            ->select($selects)
            ->where($visitDate . ' >= ' . $db->escape($startDate), null, false)
            ->where($visitDate . ' <= ' . $db->escape($endDate), null, false)
            ->where("NOT EXISTS (SELECT 1 FROM followup_log gfl WHERE gfl.person_id = pa.person_id AND gfl.status = 'sent')", null, false)
            ->get()
            ->getRowArray() ?? ['total_guests' => 0, 'converted' => 0];

        return [$withFollowup, $withoutFollowup];
    }

    /**
     * Per-ISO-week new guest volume (for the ROI detail table).
     */
    private function queryWeeklyGuestVolume(int $parishId, string $startDate, string $endDate): array
    {
        $directory = new \App\Services\GuestDirectoryService();
        $visitDate = $directory->firstVisitDateSql();

        return $directory->scopeBuilder($parishId)
            ->select([
                "YEARWEEK({$visitDate}, 1) AS iso_week",
                "MIN({$visitDate}) AS week_start",
                'COUNT(pa.person_id) AS new_guests',
                'SUM(CASE WHEN pa.converted_to_member_at IS NOT NULL THEN 1 ELSE 0 END) AS converted',
                "SUM(CASE WHEN EXISTS (SELECT 1 FROM followup_log gfl WHERE gfl.person_id = pa.person_id AND gfl.status = 'sent') THEN 1 ELSE 0 END) AS received_followup",
            ])
            ->where($visitDate . ' >= ' . $this->db->escape($startDate), null, false)
            ->where($visitDate . ' <= ' . $this->db->escape($endDate), null, false)
            ->groupBy("YEARWEEK({$visitDate}, 1)", false)
            ->orderBy('iso_week', 'ASC')
            ->get()
            ->getResultArray();
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Private – CSV builders
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /** @return array{0: string[], 1: array[]} */
    private function csvFollowupCompletion(int $parishId, string $startDate, string $endDate): array
    {
        $headers = [lang('Guests.rptColSequenceWeek'), lang('Guests.rptColTotalScheduled'), lang('Guests.rptCompletedSent'), lang('Guests.rptColCompletionPct')];
        $source  = $this->queryCompletionByWeek($parishId, $startDate, $endDate);
        $rows    = [];
        foreach ($source as $r) {
            $total = (int) $r['total_actions'];
            $done  = (int) $r['completed_actions'];
            $pct   = $total > 0 ? round($done / $total * 100, 1) : 0.0;
            $rows[] = [$r['week_number'], $total, $done, $pct];
        }
        return [$headers, $rows];
    }

    /** @return array{0: string[], 1: array[]} */
    private function csvRoiSecondVisit(int $parishId, string $startDate, string $endDate): array
    {
        $headers = [lang('Guests.rptColCohort'), lang('Guests.rptTotalGuests'), lang('Guests.rptColConverted'), lang('Guests.rptColConversionPct')];
        [$with, $without] = $this->queryRoiCohorts($parishId, $startDate, $endDate);

        $wfTotal = (int) ($with['total_guests'] ?? 0);
        $wfConv  = (int) ($with['converted'] ?? 0);
        $nfTotal = (int) ($without['total_guests'] ?? 0);
        $nfConv  = (int) ($without['converted'] ?? 0);

        return [$headers, [
            [lang('Guests.rptReceivedFollowup'), $wfTotal, $wfConv, $wfTotal > 0 ? round($wfConv / $wfTotal * 100, 1) : 0.0],
            [lang('Guests.rptNoFollowup'),       $nfTotal, $nfConv, $nfTotal > 0 ? round($nfConv / $nfTotal * 100, 1) : 0.0],
        ]];
    }

    /** @return array{0: string[], 1: array[]} */
    private function csvGuestsAll(int $parishId, string $startDate, string $endDate): array
    {
        $headers = [
            lang('Guests.firstName'),
            lang('Guests.lastName'),
            lang('Guests.colEmail'),
            lang('Guests.colPhone'),
            lang('Guests.visitDate'),
            lang('Guests.source'),
            lang('Guests.interestsMinistries'),
            lang('Guests.followedUpLabel'),
            lang('Guests.rptLogTotal'),
            lang('Guests.rptLogSent'),
            lang('Guests.rptColConverted'),
            lang('Guests.rptConvertedDate'),
        ];

        $directory = new \App\Services\GuestDirectoryService();
        $visitDate = $directory->firstVisitDateSql();

        $guestRows = $directory->scopeBuilder($parishId)
            ->select('pe.id AS id, pe.first_name, pe.last_name, pe.email, pe.phone, pe.source, pe.interests,'
                . ' pa.followed_up, pa.converted_to_member_at,'
                . ' COUNT(gfl.id) AS log_total,'
                . " SUM(CASE WHEN gfl.status = 'sent' THEN 1 ELSE 0 END) AS log_sent")
            ->select($visitDate . ' AS visit_date', false)
            ->join('followup_log gfl', 'gfl.person_id = pa.person_id', 'left')
            ->where($visitDate . ' >= ' . $this->db->escape($startDate), null, false)
            ->where($visitDate . ' <= ' . $this->db->escape($endDate), null, false)
            ->groupBy('pe.id', false)
            ->orderBy($visitDate, 'DESC', false)
            ->get()
            ->getResultArray();

        $enc  = new \App\Libraries\DataEncryption();
        $rows = [];
        foreach ($guestRows as $g) {
            $rows[] = [
                $g['first_name'],
                $g['last_name'],
                $enc->decrypt((string) ($g['email'] ?? '')),
                $enc->decrypt((string) ($g['phone'] ?? '')),
                $g['visit_date'],
                $g['source'] ?? '',
                $g['interests'] ? implode(', ', (array) json_decode((string) $g['interests'], true)) : '',
                $g['followed_up'] ? lang('App.yes') : lang('App.no'),
                $g['log_total'],
                $g['log_sent'],
                $g['converted_to_member_at'] ? lang('App.yes') : lang('App.no'),
                $g['converted_to_member_at'] ?? '',
            ];
        }
        return [$headers, $rows];
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Private – helpers
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    private function sanitizeDate(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
    }
}
