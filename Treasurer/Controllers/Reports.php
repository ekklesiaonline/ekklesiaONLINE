<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Models\DonationModel;

class Reports extends BaseController
{
    private const ADMIN_EXCLUDED_GROUPS = ['provinceadmin', 'diocesanadmin', 'dioceseadmin'];

    public function index()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;

        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.reportProvinceScopeFailed'));
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);

        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $summary = $this->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin
            ? $this->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate)
            : [];

        $data = array_merge($this->getCommonViewData(lang('App.navReports')), [
            'report_start_date' => $startDate,
            'report_end_date' => $endDate,
            'report_summary' => $summary,
            'report_diocese_breakdown' => $dioceseBreakdown,
            'report_is_provinceadmin' => $isProvinceAdmin,
        ]);

        return view('Treasurer\Views\Reports/index', $data);
    }

    public function export()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;

        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return redirect()->to('/reports')->with('error', lang('Treasurer.reportProvinceScopeFailed'));
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);

        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $summary = $this->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin ? $this->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate) : [];

        $rows = [];
        $rows[] = [lang('Treasurer.reportDateRange'), $startDate . ' ' . lang('Treasurer.reportTo') . ' ' . $endDate];
        $rows[] = [lang('Treasurer.reportTotalParishes'), (string) ($summary['parish_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalPeople'), (string) ($summary['people_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalVisitorsUnconverted'), (string) ($summary['visitor_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalMembers'), (string) ($summary['member_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportGoodStanding'), (string) ($summary['good_standing_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalServices'), (string) ($summary['service_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalAttendance'), (string) ($summary['attendance_count'] ?? 0)];
        $rows[] = [lang('Treasurer.reportTotalDonations'), (string) number_format((float) ($summary['donations_total'] ?? 0), 2, '.', '')];
        $rows[] = [lang('Treasurer.reportTotalExpenses'), (string) number_format((float) ($summary['expenses_total'] ?? 0), 2, '.', '')];
        $rows[] = [lang('Treasurer.reportNetBalance'), (string) number_format((float) ($summary['net_balance'] ?? 0), 2, '.', '')];

        if (!empty($dioceseBreakdown)) {
            $rows[] = [];
            $rows[] = [
                lang('Treasurer.reportDiocese'),
                lang('Treasurer.reportParishes'),
                lang('Treasurer.reportPeople'),
                lang('Treasurer.reportVisitors'),
                lang('Treasurer.reportMembers'),
                lang('Treasurer.reportGoodStanding'),
                lang('Treasurer.reportServices'),
                lang('Treasurer.reportAttendance'),
                lang('Treasurer.reportDonations'),
                lang('Treasurer.reportExpenses'),
                lang('Treasurer.reportNet'),
            ];
            foreach ($dioceseBreakdown as $item) {
                $rows[] = [
                    (string) ($item['diocese_name'] ?? ''),
                    (string) ($item['parish_count'] ?? 0),
                    (string) ($item['people_count'] ?? 0),
                    (string) ($item['visitor_count'] ?? 0),
                    (string) ($item['member_count'] ?? 0),
                    (string) ($item['good_standing_count'] ?? 0),
                    (string) ($item['service_count'] ?? 0),
                    (string) ($item['attendance_count'] ?? 0),
                    number_format((float) ($item['donations_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($item['expenses_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($item['net_balance'] ?? 0), 2, '.', ''),
                ];
            }
        }

        $filename = 'reports_' . $scope . '_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('report.export_csv', [
            'parish_id' => $isProvinceAdmin ? null : $selectedParishId,
            'entity_type' => 'finance_report',
            'meta' => [
                'scope' => $scope,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_province_admin' => $isProvinceAdmin,
                'breakdown_count' => count($dioceseBreakdown),
            ],
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;

        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return redirect()->to('/reports')->with('error', lang('Treasurer.reportProvinceScopeFailed'));
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);

        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $summary = $this->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin ? $this->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate) : [];

        $html = $this->buildPdfHtml($scope, $selectedParishId, $startDate, $endDate, $summary, $dioceseBreakdown);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('report.export_pdf', [
            'parish_id' => $isProvinceAdmin ? null : $selectedParishId,
            'entity_type' => 'finance_report',
            'meta' => [
                'scope' => $scope,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_province_admin' => $isProvinceAdmin,
                'breakdown_count' => count($dioceseBreakdown),
            ],
        ]);

        $filename = 'reports_' . $scope . '_' . date('Ymd_His') . '.pdf';
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    public function summaryApi()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorForbidden'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;
        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.reportProvinceScopeFailed'), 422, 'REPORT_PROVINCE_SCOPE_FAILED'), 422);
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);
        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 422, 'PARISH_SCOPE_REQUIRED'), 422);
        }

        $summary = $this->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'scope' => $scope,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'summary' => $summary,
            ],
        ]);
    }

    private function buildPdfHtml(string $scope, int $parishId, string $startDate, string $endDate, array $summary, array $breakdown): string
    {
        $scopeLabel = $scope === 'diocese' ? lang('Treasurer.scopeDiocese') : lang('Treasurer.scopeParish');
        $parishModel = model('ParishModel');
        $parishName = '';

        if ($scope === 'parish' && $parishId > 0) {
            $parish = $parishModel->find($parishId);
            $parishName = $parish['name'] ?? lang('Treasurer.unknownParish');
        }

        $tableRows = '';
        if ($scope === 'diocese' && !empty($breakdown)) {
            foreach ($breakdown as $row) {
                $tableRows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($row['diocese_name']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['parish_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['people_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['visitor_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['member_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) ($row['good_standing_count'] ?? 0)) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['service_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . number_format((int) $row['attendance_count']) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) $row['donations_total'], 2) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) $row['expenses_total'], 2) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: ' . (((float) $row['net_balance']) >= 0 ? '#0066cc' : '#cc0000') . '">$' . number_format((float) $row['net_balance'], 2) . '</td>
                </tr>';
            }
        }

        $tableHtml = '';
        if ($scope === 'diocese' && !empty($breakdown)) {
            $tableHtml = '<h3 style="margin-top: 30px; color: #333;">' . htmlspecialchars(lang('Treasurer.reportDioceseBreakdown')) . '</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportDiocese')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportParishes')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportPeople')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportVisitors')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportMembers')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportGoodStanding')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportServices')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportAttendance')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportDonations')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportExpenses')) . '</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportNet')) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $tableRows . '
                </tbody>
            </table>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.reportTitle')) . ' - ' . htmlspecialchars($scopeLabel) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                h1 { color: #2c3e50; border-bottom: 3px solid #0066cc; padding-bottom: 10px; }
                h2 { color: #34495e; margin-top: 20px; }
                h3 { color: #555; }
                .header { margin-bottom: 20px; }
                .kpi-section { margin: 20px 0; }
                .kpi-row { display: table; width: 100%; margin-bottom: 10px; }
                .kpi-item { display: table-cell; width: 48%; margin-right: 2%; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; }
                .kpi-label { font-size: 12px; color: #999; font-weight: bold; }
                .kpi-value { font-size: 24px; font-weight: bold; color: #0066cc; margin-top: 5px; }
                .financial { background-color: #f0f8ff; }
                .summary-info { font-size: 12px; color: #666; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>EkklesiaRD ' . htmlspecialchars(lang('Treasurer.reportTitle')) . ' - ' . htmlspecialchars($scopeLabel) . ' ' . htmlspecialchars(lang('Treasurer.reportLevel')) . '</h1>
                <div class="summary-info">
                    <strong>' . htmlspecialchars(lang('Treasurer.reportPeriod')) . ':</strong> ' . htmlspecialchars($startDate) . ' ' . htmlspecialchars(lang('Treasurer.reportTo')) . ' ' . htmlspecialchars($endDate) . '<br>
                    <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . date('F j, Y \a\t g:i A') . '<br>' .
            ($scope === 'parish' && $parishName ? '<strong>' . htmlspecialchars(lang('Treasurer.parishLabel')) . ':</strong> ' . htmlspecialchars($parishName) . '<br>' : '') .
            '</div>
            </div>

            <h2>' . htmlspecialchars(lang('Treasurer.reportSummaryMetrics')) . '</h2>
            <div class="kpi-section">
                <div class="kpi-row">
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportParishes')) . '</div>
                        <div class="kpi-value">' . (int) ($summary['parish_count'] ?? 0) . '</div>
                    </div>
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportPeople')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['people_count'] ?? 0)) . '</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportMembers')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['member_count'] ?? 0)) . '</div>
                    </div>
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportGoodStanding')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['good_standing_count'] ?? 0)) . '</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportVisitorsUnconverted')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['visitor_count'] ?? 0)) . '</div>
                    </div>
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportAttendance')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['attendance_count'] ?? 0)) . '</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-item">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportServices')) . '</div>
                        <div class="kpi-value">' . number_format((int) ($summary['service_count'] ?? 0)) . '</div>
                    </div>
                </div>
            </div>

            <h2>' . htmlspecialchars(lang('Treasurer.reportFinancialSummary')) . '</h2>
            <div class="kpi-section">
                <div class="kpi-row">
                    <div class="kpi-item financial">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportDonations')) . '</div>
                        <div class="kpi-value" style="color: #006600;">$' . number_format((float) ($summary['donations_total'] ?? 0), 2) . '</div>
                    </div>
                    <div class="kpi-item financial">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportExpenses')) . '</div>
                        <div class="kpi-value" style="color: #cc0000;">$' . number_format((float) ($summary['expenses_total'] ?? 0), 2) . '</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-item financial">
                        <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.reportNetBalance')) . '</div>
                        <div class="kpi-value" style="color: ' . (((float) ($summary['net_balance'] ?? 0)) >= 0 ? '#006600' : '#cc0000') . ';">$' . number_format((float) ($summary['net_balance'] ?? 0), 2) . '</div>
                    </div>
                </div>
            </div>

            ' . $tableHtml . '
        </body>
        </html>';

        return $html;
    }

    private function buildSummary(string $scope, int $parishId, string $startDate, string $endDate, int $provinceScopeId = 0): array
    {
        $db = db_connect();

        $parishIds = [];
        if ($scope === 'parish' && $parishId > 0) {
            $parishIds = [$parishId];
        } elseif ($scope === 'parish') {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        } else {
            $provinceMembership = $this->getProvinceMembershipSummary($provinceScopeId);

            if ($provinceMembership !== null) {
                return [
                    'parish_count' => (int) ($provinceMembership['parish_count'] ?? 0),
                    'people_count' => (int) ($provinceMembership['people_count'] ?? 0),
                    'visitor_count' => (int) ($provinceMembership['visitor_count'] ?? 0),
                    'member_count' => (int) ($provinceMembership['member_count'] ?? 0),
                    'good_standing_count' => (int) ($provinceMembership['good_standing_count'] ?? 0),
                    'service_count' => (int) ($provinceMembership['service_count'] ?? 0),
                    'attendance_count' => (int) ($provinceMembership['attendance_count'] ?? 0),
                    'donations_total' => (float) ($provinceMembership['donations_total'] ?? 0),
                    'expenses_total' => (float) ($provinceMembership['expenses_total'] ?? 0),
                    'net_balance' => (float) ($provinceMembership['net_balance'] ?? 0),
                ];
            }

            $parishQuery = $db->table('parish c')->select('c.id');
            if ($provinceScopeId > 0) {
                $parishQuery
                    ->join('diocese d', 'd.id = c.diocese_id', 'inner')
                    ->where('d.province_id', $provinceScopeId);
            }

            $parishIds = array_map(
                static fn($row) => (int) $row['id'],
                $parishQuery->get()->getResultArray()
            );
        }

        if (empty($parishIds)) {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        }

        $parishCount = count($parishIds);

        $peopleRow = $db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) as count', false)
            ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.is_member', 1)
            ->where('mca.deleted_at IS NULL', null, false)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $memberRow = $db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) as count', false)
            ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.membership_status', 'member')
            ->where('mca.deleted_at IS NULL', null, false)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $visitorRow = $db->table('parish_affiliation pa')
            ->select('COUNT(DISTINCT pa.person_id) AS visitor_count', false)
            ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
            ->whereIn('pa.parish_id', $parishIds)
            ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->where('pa.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        $goodStandingYear = (int) date('Y', strtotime($endDate));
        $goodStandingRow = $db->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) AS count', false)
            ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id', 'inner')
            ->join('users u', 'u.id = mss.user_id', 'inner')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.is_member', 1)
            ->where('mca.membership_status', 'member')
            ->where('mss.year', $goodStandingYear)
            ->where('mss.is_good_standing', 1)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $serviceCount = (int) $db->table('service')
            ->whereIn('parish_id', $parishIds)
            ->where('service_date >=', $startDate)
            ->where('service_date <=', $endDate)
            ->countAllResults();

        $attendanceCount = (int) $db->table('attendance a')
            ->join('service s', 's.id = a.service_id')
            ->whereIn('s.parish_id', $parishIds)
            ->where('a.attended', 1)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->countAllResults();

        $donationsTotal = model(DonationModel::class)->getAllParishesTotalDonations($startDate, $endDate, $parishIds, false);

        $expenseRow = $db->table('expense')
            ->select('COALESCE(SUM(amount), 0) AS total_amount')
            ->whereIn('parish_id', $parishIds)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getRowArray();

        $expensesTotal = (float) ($expenseRow['total_amount'] ?? 0);

        return [
            'parish_count' => $parishCount,
            'people_count' => (int) ($peopleRow['count'] ?? 0) + (int) ($visitorRow['visitor_count'] ?? 0),
            'visitor_count' => (int) ($visitorRow['visitor_count'] ?? 0),
            'member_count' => (int) ($memberRow['count'] ?? 0),
            'good_standing_count' => (int) ($goodStandingRow['count'] ?? 0),
            'service_count' => $serviceCount,
            'attendance_count' => $attendanceCount,
            'donations_total' => $donationsTotal,
            'expenses_total' => $expensesTotal,
            'net_balance' => $donationsTotal - $expensesTotal,
        ];
    }

    private function buildDioceseBreakdown(int $provinceId, string $startDate, string $endDate): array
    {
        $db = db_connect();
        $dioceses = $db->table('diocese')
            ->select('id, name')
            ->where('province_id', $provinceId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($dioceses)) {
            return [];
        }

        // OPTIMIZATION: Fetch all parishes in one query, then group by diocese (N+1 prevention)
        $dioceseIds = array_map(static fn(array $d) => (int) $d['id'], $dioceses);
        $allParishes = $db->table('parish')
            ->select('id, diocese_id')
            ->whereIn('diocese_id', $dioceseIds)
            ->get()
            ->getResultArray();

        // Group parishes by diocese in memory
        $parishesByDiocese = [];
        foreach ($allParishes as $parish) {
            $dioId = (int) $parish['diocese_id'];
            if (!isset($parishesByDiocese[$dioId])) {
                $parishesByDiocese[$dioId] = [];
            }
            $parishesByDiocese[$dioId][] = (int) $parish['id'];
        }

        $rows = [];
        foreach ($dioceses as $diocese) {
            $dioceseId = (int) $diocese['id'];
            $parishIds = $parishesByDiocese[$dioceseId] ?? [];

            if (empty($parishIds)) {
                $rows[] = [
                    'diocese_id' => $dioceseId,
                    'diocese_name' => (string) $diocese['name'],
                    'parish_count' => 0,
                    'people_count' => 0,
                    'visitor_count' => 0,
                    'member_count' => 0,
                    'good_standing_count' => 0,
                    'service_count' => 0,
                    'attendance_count' => 0,
                    'donations_total' => 0.0,
                    'expenses_total' => 0.0,
                    'net_balance' => 0.0,
                ];
                continue;
            }

            // Build summary for the whole diocese parish set.
            $peopleRow = $db->table('parish_affiliation mca')
                ->select('COUNT(DISTINCT mca.person_id) as count', false)
                ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
                ->join('users u', 'u.id = mca.linked_user_id', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.is_member', 1)
                ->where('mca.deleted_at IS NULL', null, false)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $memberRow = $db->table('parish_affiliation mca')
                ->select('COUNT(DISTINCT mca.person_id) as count', false)
                ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
                ->join('users u', 'u.id = mca.linked_user_id', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.membership_status', 'member')
                ->where('mca.deleted_at IS NULL', null, false)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $visitorRow = $db->table('parish_affiliation pa')
                ->select('COUNT(DISTINCT pa.person_id) AS visitor_count', false)
                ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
                ->whereIn('pa.parish_id', $parishIds)
                ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
                ->where('pa.converted_to_member_at IS NULL', null, false)
                ->where('pa.deleted_at IS NULL', null, false)
                ->get()
                ->getRowArray();

            $goodStandingYear = (int) date('Y', strtotime($endDate));
            $goodStandingRow = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) AS count', false)
                ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id', 'inner')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.is_member', 1)
                ->where('mca.membership_status', 'member')
                ->where('mss.year', $goodStandingYear)
                ->where('mss.is_good_standing', 1)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $serviceCount = (int) $db->table('service')
                ->whereIn('parish_id', $parishIds)
                ->where('service_date >=', $startDate)
                ->where('service_date <=', $endDate)
                ->countAllResults();

            $attendanceCount = (int) $db->table('attendance a')
                ->join('service s', 's.id = a.service_id')
                ->whereIn('s.parish_id', $parishIds)
                ->where('a.attended', 1)
                ->where('s.service_date >=', $startDate)
                ->where('s.service_date <=', $endDate)
                ->countAllResults();

            $donationsTotal = model(DonationModel::class)->getAllParishesTotalDonations($startDate, $endDate, $parishIds, false);

            $expenseRow = $db->table('expense')
                ->select('COALESCE(SUM(amount), 0) AS total_amount')
                ->whereIn('parish_id', $parishIds)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getRowArray();

            $expensesTotal = (float) ($expenseRow['total_amount'] ?? 0);

            $rows[] = [
                'diocese_id' => (int) $diocese['id'],
                'diocese_name' => (string) $diocese['name'],
                'parish_count' => count($parishIds),
                'people_count' => (int) ($peopleRow['count'] ?? 0) + (int) ($visitorRow['visitor_count'] ?? 0),
                'visitor_count' => (int) ($visitorRow['visitor_count'] ?? 0),
                'member_count' => (int) ($memberRow['count'] ?? 0),
                'good_standing_count' => (int) ($goodStandingRow['count'] ?? 0),
                'service_count' => $serviceCount,
                'attendance_count' => $attendanceCount,
                'donations_total' => $donationsTotal,
                'expenses_total' => $expensesTotal,
                'net_balance' => $donationsTotal - $expensesTotal,
            ];
        }

        return $rows;
    }

    private function getProvinceMembershipSummary(int $provinceId): ?array
    {
        if ($provinceId <= 0) {
            return null;
        }

        $breakdown = $this->buildDioceseBreakdown($provinceId, date('Y-01-01'), date('Y-m-d'));

        if ($breakdown === []) {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        }

        $summary = [
            'parish_count' => 0,
            'people_count' => 0,
            'visitor_count' => 0,
            'member_count' => 0,
            'good_standing_count' => 0,
            'service_count' => 0,
            'attendance_count' => 0,
            'donations_total' => 0.0,
            'expenses_total' => 0.0,
            'net_balance' => 0.0,
        ];

        foreach ($breakdown as $row) {
            $summary['parish_count'] += (int) ($row['parish_count'] ?? 0);
            $summary['people_count'] += (int) ($row['people_count'] ?? 0);
            $summary['visitor_count'] += (int) ($row['visitor_count'] ?? 0);
            $summary['member_count'] += (int) ($row['member_count'] ?? 0);
            $summary['good_standing_count'] += (int) ($row['good_standing_count'] ?? 0);
            $summary['service_count'] += (int) ($row['service_count'] ?? 0);
            $summary['attendance_count'] += (int) ($row['attendance_count'] ?? 0);
            $summary['donations_total'] += (float) ($row['donations_total'] ?? 0);
            $summary['expenses_total'] += (float) ($row['expenses_total'] ?? 0);
        }

        $summary['net_balance'] = $summary['donations_total'] - $summary['expenses_total'];

        return $summary;
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
