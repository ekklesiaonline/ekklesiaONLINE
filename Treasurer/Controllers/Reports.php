<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Services\TreasurerReportsDataService;

class Reports extends BaseController
{
    private const ADMIN_EXCLUDED_GROUPS = ['provinceadmin', 'diocesanadmin', 'dioceseadmin'];

    private TreasurerReportsDataService $reportData;

    public function __construct()
    {
        $this->reportData = new TreasurerReportsDataService();
    }

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

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
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

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin
            ? $this->reportData->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate)
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

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
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

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin ? $this->reportData->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate) : [];

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

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
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

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin ? $this->reportData->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate) : [];

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

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
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

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);

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
}
