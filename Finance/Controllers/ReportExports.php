<?php

namespace Finance\Controllers;

/**
 * ReportExports
 *
 * CSV/PDF export endpoints and PDF HTML rendering for treasurer reports.
 * Extracted from Reports (treasurer second pass); extends it to share the
 * reports data service and the index/summary endpoints' scope handling.
 */
class ReportExports extends Reports
{

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
        } elseif (!$isProvinceAdmin) {
            $fundBreakdown = $this->reportData->buildParishFundBreakdown($selectedParishId, $startDate, $endDate);
            $accountBreakdown = $this->reportData->buildParishAccountCategoryBreakdown($selectedParishId, $startDate, $endDate);

            if (!empty($fundBreakdown['funds'])) {
                $rows[] = [];
                $rows[] = [lang('Treasurer.reportFundBreakdown')];
                $rows[] = [
                    lang('Treasurer.reportFund'),
                    lang('Treasurer.reportFundType'),
                    lang('Treasurer.reportDonations'),
                    lang('Treasurer.reportExpenses'),
                    lang('Treasurer.reportNetBalance'),
                ];
                foreach ($fundBreakdown['funds'] as $fund) {
                    $rows[] = [
                        (string) $fund['fund_name'],
                        (string) $fund['fund_type'],
                        number_format((float) $fund['donations_total'], 2, '.', ''),
                        number_format((float) $fund['expenses_total'], 2, '.', ''),
                        number_format((float) $fund['net_balance'], 2, '.', ''),
                    ];
                }
                $rows[] = [
                    lang('Treasurer.reportTotal'),
                    '',
                    number_format((float) $fundBreakdown['totals']['donations_total'], 2, '.', ''),
                    number_format((float) $fundBreakdown['totals']['expenses_total'], 2, '.', ''),
                    number_format((float) $fundBreakdown['totals']['net_balance'], 2, '.', ''),
                ];
            }

            if (!empty($accountBreakdown['income'])) {
                $rows[] = [];
                $rows[] = [lang('Treasurer.reportIncomeBreakdown')];
                $rows[] = [
                    lang('Treasurer.reportAccountCode'),
                    lang('Treasurer.reportAccount'),
                    lang('Treasurer.reportCategory'),
                    lang('Treasurer.reportTransactions'),
                    lang('Treasurer.reportAmount'),
                    lang('Treasurer.reportPercentOfTotal'),
                ];
                foreach ($accountBreakdown['income'] as $item) {
                    $rows[] = [
                        (string) $item['account_code'],
                        (string) $item['account_name'],
                        (string) $item['category_label'],
                        (string) $item['transaction_count'],
                        number_format((float) $item['total_amount'], 2, '.', ''),
                        number_format((float) $item['percentage'], 1, '.', '') . '%',
                    ];
                }
                $rows[] = [
                    lang('Treasurer.reportTotal'),
                    '',
                    '',
                    (string) $accountBreakdown['totals']['income_count'],
                    number_format((float) $accountBreakdown['totals']['income_total'], 2, '.', ''),
                    '100.0%',
                ];
            }

            if (!empty($accountBreakdown['expenses'])) {
                $rows[] = [];
                $rows[] = [lang('Treasurer.reportExpenseBreakdown')];
                $rows[] = [
                    lang('Treasurer.reportAccountCode'),
                    lang('Treasurer.reportAccount'),
                    lang('Treasurer.reportCategory'),
                    lang('Treasurer.reportTransactions'),
                    lang('Treasurer.reportAmount'),
                    lang('Treasurer.reportPercentOfTotal'),
                ];
                foreach ($accountBreakdown['expenses'] as $item) {
                    $rows[] = [
                        (string) $item['account_code'],
                        (string) $item['account_name'],
                        (string) $item['category_label'],
                        (string) $item['transaction_count'],
                        number_format((float) $item['total_amount'], 2, '.', ''),
                        number_format((float) $item['percentage'], 1, '.', '') . '%',
                    ];
                }
                $rows[] = [
                    lang('Treasurer.reportTotal'),
                    '',
                    '',
                    (string) $accountBreakdown['totals']['expenses_count'],
                    number_format((float) $accountBreakdown['totals']['expenses_total'], 2, '.', ''),
                    '100.0%',
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
        $fundBreakdown = [];
        $accountBreakdown = [];
        if (!$isProvinceAdmin) {
            $fundBreakdown = $this->reportData->buildParishFundBreakdown($selectedParishId, $startDate, $endDate);
            $accountBreakdown = $this->reportData->buildParishAccountCategoryBreakdown($selectedParishId, $startDate, $endDate);
        }

        $html = $this->buildPdfHtml(
            $scope,
            $selectedParishId,
            $startDate,
            $endDate,
            $summary,
            $dioceseBreakdown,
            $fundBreakdown,
            $accountBreakdown
        );

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


    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $breakdown
     * @param array<string, mixed> $fundBreakdown
     * @param array<string, mixed> $accountBreakdown
     */
    private function buildPdfHtml(
        string $scope,
        int $parishId,
        string $startDate,
        string $endDate,
        array $summary,
        array $breakdown,
        array $fundBreakdown = [],
        array $accountBreakdown = []
    ): string
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
        } elseif ($scope === 'parish') {
            $fundRows = $fundBreakdown['funds'] ?? [];
            $fundTotals = $fundBreakdown['totals'] ?? [];
            if (!empty($fundRows)) {
                $fundTableRows = '';
                foreach ($fundRows as $row) {
                    $net = (float) ($row['net_balance'] ?? 0.0);
                    $fundTableRows .= '<tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($row['fund_name']) . (!empty($row['is_active']) ? '' : ' (' . htmlspecialchars(lang('App.inactive')) . ')') . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(ucfirst($row['fund_type'])) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #006600;">$' . number_format((float) ($row['donations_total'] ?? 0), 2) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #cc0000;">$' . number_format((float) ($row['expenses_total'] ?? 0), 2) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: ' . ($net >= 0 ? '#0066cc' : '#cc0000') . ';">$' . number_format($net, 2) . '</td>
                    </tr>';
                }

                $tableHtml .= '<h3 style="margin-top: 25px; color: #333;">' . htmlspecialchars(lang('Treasurer.reportFundBreakdown')) . '</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportFund')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportFundType')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportDonations')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportExpenses')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportNetBalance')) . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $fundTableRows . '
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold; background-color: #fafafa;">
                            <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars(lang('Treasurer.reportTotal')) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd;"></td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #006600;">$' . number_format((float) ($fundTotals['donations_total'] ?? 0), 2) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #cc0000;">$' . number_format((float) ($fundTotals['expenses_total'] ?? 0), 2) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: ' . (((float) ($fundTotals['net_balance'] ?? 0)) >= 0 ? '#0066cc' : '#cc0000') . ';">$' . number_format((float) ($fundTotals['net_balance'] ?? 0), 2) . '</td>
                        </tr>
                    </tfoot>
                </table>';
            }

            $incomeList = $accountBreakdown['income'] ?? [];
            $incomeTotals = $accountBreakdown['totals'] ?? [];
            if (!empty($incomeList)) {
                $incomeTableRows = '';
                foreach ($incomeList as $item) {
                    $incomeTableRows .= '<tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;">' . htmlspecialchars($item['account_code']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['account_name']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['category_label']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . (int) ($item['transaction_count'] ?? 0) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #006600;">$' . number_format((float) ($item['total_amount'] ?? 0), 2) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . number_format((float) ($item['percentage'] ?? 0), 1) . '%</td>
                    </tr>';
                }

                $tableHtml .= '<h3 style="margin-top: 25px; color: #333;">' . htmlspecialchars(lang('Treasurer.reportIncomeBreakdown')) . '</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportAccountCode')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportAccount')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportCategory')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportTransactions')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportAmount')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportPercentOfTotal')) . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $incomeTableRows . '
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold; background-color: #fafafa;">
                            <td colspan="3" style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars(lang('Treasurer.reportTotal')) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . (int) ($incomeTotals['income_count'] ?? 0) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #006600;">$' . number_format((float) ($incomeTotals['income_total'] ?? 0), 2) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">100.0%</td>
                        </tr>
                    </tfoot>
                </table>';
            }

            $expenseList = $accountBreakdown['expenses'] ?? [];
            $expenseTotals = $accountBreakdown['totals'] ?? [];
            if (!empty($expenseList)) {
                $expenseTableRows = '';
                foreach ($expenseList as $item) {
                    $expenseTableRows .= '<tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;">' . htmlspecialchars($item['account_code']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['account_name']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['category_label']) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . (int) ($item['transaction_count'] ?? 0) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #cc0000;">$' . number_format((float) ($item['total_amount'] ?? 0), 2) . '</td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . number_format((float) ($item['percentage'] ?? 0), 1) . '%</td>
                    </tr>';
                }

                $tableHtml .= '<h3 style="margin-top: 25px; color: #333;">' . htmlspecialchars(lang('Treasurer.reportExpenseBreakdown')) . '</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportAccountCode')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportAccount')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . htmlspecialchars(lang('Treasurer.reportCategory')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars(lang('Treasurer.reportTransactions')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportAmount')) . '</th>
                            <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . htmlspecialchars(lang('Treasurer.reportPercentOfTotal')) . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $expenseTableRows . '
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold; background-color: #fafafa;">
                            <td colspan="3" style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars(lang('Treasurer.reportTotal')) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . (int) ($expenseTotals['expenses_count'] ?? 0) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #cc0000;">$' . number_format((float) ($expenseTotals['expenses_total'] ?? 0), 2) . '</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">100.0%</td>
                        </tr>
                    </tfoot>
                </table>';
            }
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

    /**
     * Export Trial Balance as CSV.
     */
    public function trialBalanceCsv()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $asOfDate = (string) ($this->request->getGet('as_of_date') ?? date('Y-m-d'));
        if (!$this->reportData->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $includeZero = (string) ($this->request->getGet('include_zero') ?? '') === '1';
        $tb = $this->reportData->buildTrialBalance($parishId, $asOfDate, $includeZero);

        $filename = 'trial_balance_' . $parishId . '_' . date('Ymd', strtotime($asOfDate)) . '.csv';

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }

        fputcsv($output, [
            lang('Finance.colAccountCode'),
            lang('Finance.colAccountName'),
            lang('Finance.colAccountType'),
            lang('Finance.colDebitBalance'),
            lang('Finance.colCreditBalance'),
        ]);

        foreach ($tb['accounts'] as $acc) {
            fputcsv($output, [
                $acc['code'],
                $acc['name'],
                ucfirst((string) $acc['account_type']),
                $acc['debit_balance'] > 0 ? number_format((float) $acc['debit_balance'], 2, '.', '') : '0.00',
                $acc['credit_balance'] > 0 ? number_format((float) $acc['credit_balance'], 2, '.', '') : '0.00',
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, [
            lang('Finance.reportTotal'),
            '',
            '',
            number_format((float) $tb['total_debits'], 2, '.', ''),
            number_format((float) $tb['total_credits'], 2, '.', ''),
        ]);
        fputcsv($output, [
            lang('Finance.trialBalanceStatus'),
            $tb['is_balanced'] ? lang('Finance.trialBalanceBalanced') : lang('Finance.trialBalanceUnbalanced'),
            '',
            '',
            '',
        ]);

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent ?: '');
    }

    /**
     * Export Trial Balance as PDF.
     */
    public function trialBalancePdf()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $asOfDate = (string) ($this->request->getGet('as_of_date') ?? date('Y-m-d'));
        if (!$this->reportData->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $includeZero = (string) ($this->request->getGet('include_zero') ?? '') === '1';
        $tb = $this->reportData->buildTrialBalance($parishId, $asOfDate, $includeZero);

        $parish = model('ParishModel')->find($parishId);
        $parishName = $parish['name'] ?? 'Parish';

        $rowsHtml = '';
        foreach ($tb['accounts'] as $acc) {
            $debitStr = $acc['debit_balance'] > 0 ? '$' . number_format((float) $acc['debit_balance'], 2) : '—';
            $creditStr = $acc['credit_balance'] > 0 ? '$' . number_format((float) $acc['credit_balance'], 2) : '—';

            $rowsHtml .= '<tr>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;">' . htmlspecialchars((string) $acc['code']) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars((string) $acc['name']) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-transform: capitalize;">' . htmlspecialchars((string) $acc['account_type']) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: right;">' . $debitStr . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: right;">' . $creditStr . '</td>'
                . '</tr>';
        }

        $statusColor = $tb['is_balanced'] ? '#15803d' : '#b91c1c';
        $statusBg = $tb['is_balanced'] ? '#f0fdf4' : '#fef2f2';
        $statusText = $tb['is_balanced'] ? lang('Finance.trialBalanceBalanced') : lang('Finance.trialBalanceUnbalanced');

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; line-height: 1.4; }
                h1 { font-size: 18px; margin: 0 0 4px 0; color: #0f172a; }
                .subtitle { color: #64748b; font-size: 12px; margin-bottom: 16px; }
                .status-box { padding: 8px 12px; margin-bottom: 16px; border: 1px solid ' . $statusColor . '; background: ' . $statusBg . '; color: ' . $statusColor . '; border-radius: 4px; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th { text-align: left; padding: 8px; border-bottom: 2px solid #cbd5e1; font-weight: bold; }
                th.num { text-align: right; }
                tfoot tr td { padding: 10px 8px; border-top: 2px solid #0f172a; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($parishName) . '</h1>
            <div class="subtitle">' . htmlspecialchars(lang('Finance.trialBalanceTitle')) . ' — ' . htmlspecialchars(lang('Finance.asOfDate')) . ': ' . htmlspecialchars($asOfDate) . '</div>
            <div class="status-box">' . htmlspecialchars($statusText) . '</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">' . htmlspecialchars(lang('Finance.colAccountCode')) . '</th>
                        <th style="width: 45%;">' . htmlspecialchars(lang('Finance.colAccountName')) . '</th>
                        <th style="width: 16%;">' . htmlspecialchars(lang('Finance.colAccountType')) . '</th>
                        <th class="num" style="width: 12%;">' . htmlspecialchars(lang('Finance.colDebitBalance')) . '</th>
                        <th class="num" style="width: 12%;">' . htmlspecialchars(lang('Finance.colCreditBalance')) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $rowsHtml . '
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">' . htmlspecialchars(lang('Finance.reportTotal')) . '</td>
                        <td style="text-align: right;">$' . number_format((float) $tb['total_debits'], 2) . '</td>
                        <td style="text-align: right;">$' . number_format((float) $tb['total_credits'], 2) . '</td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'trial_balance_' . $parishId . '_' . date('Ymd', strtotime($asOfDate)) . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    /**
     * Export Statement of Financial Position (Balance Sheet) as CSV.
     */
    public function balanceSheetCsv()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $asOfDate = (string) ($this->request->getGet('as_of_date') ?? date('Y-m-d'));
        if (!$this->reportData->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $bs = $this->reportData->buildStatementOfFinancialPosition($parishId, $asOfDate);
        $filename = 'balance_sheet_' . $parishId . '_' . date('Ymd', strtotime($asOfDate)) . '.csv';

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }

        fputcsv($output, [lang('Finance.colCategory'), lang('Finance.colAccountCode'), lang('Finance.colAccountName'), lang('Finance.colBalance')]);

        // Current Assets
        fputcsv($output, [lang('Finance.currentAssetsHeading'), '', '', '']);
        foreach ($bs['current_assets'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['balance'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalCurrentAssets'), number_format((float) $bs['total_current_assets'], 2, '.', '')]);

        // Fixed Assets
        fputcsv($output, [lang('Finance.fixedAssetsHeading'), '', '', '']);
        foreach ($bs['fixed_assets'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['balance'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalFixedAssets'), number_format((float) $bs['total_fixed_assets'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.totalAssetsHeading'), '', '', number_format((float) $bs['total_assets'], 2, '.', '')]);
        fputcsv($output, []);

        // Current Liabilities
        fputcsv($output, [lang('Finance.currentLiabilitiesHeading'), '', '', '']);
        foreach ($bs['current_liabilities'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['balance'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalCurrentLiabilities'), number_format((float) $bs['total_current_liabilities'], 2, '.', '')]);

        // Long-term Liabilities
        fputcsv($output, [lang('Finance.longTermLiabilitiesHeading'), '', '', '']);
        foreach ($bs['long_term_liabilities'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['balance'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalLongTermLiabilities'), number_format((float) $bs['total_long_term_liabilities'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.totalLiabilitiesHeading'), '', '', number_format((float) $bs['total_liabilities'], 2, '.', '')]);
        fputcsv($output, []);

        // Net Assets / Equity
        fputcsv($output, [lang('Finance.netAssetsHeading'), '', '', '']);
        foreach ($bs['equity_accounts'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['balance'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.currentPeriodNetChange'), number_format((float) $bs['current_period_net_change'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.totalNetAssetsHeading'), '', '', number_format((float) $bs['total_net_assets'], 2, '.', '')]);
        fputcsv($output, []);

        // Grand Total
        fputcsv($output, [lang('Finance.totalLiabilitiesAndNetAssetsHeading'), '', '', number_format((float) $bs['total_liabilities_and_net_assets'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.trialBalanceStatus'), $bs['is_balanced'] ? lang('Finance.trialBalanceBalanced') : lang('Finance.trialBalanceUnbalanced'), '', '']);

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent ?: '');
    }

    /**
     * Export Statement of Financial Position (Balance Sheet) as PDF.
     */
    public function balanceSheetPdf()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $asOfDate = (string) ($this->request->getGet('as_of_date') ?? date('Y-m-d'));
        if (!$this->reportData->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $bs = $this->reportData->buildStatementOfFinancialPosition($parishId, $asOfDate);
        $parish = db_connect()->table('parish')->select('name')->where('id', $parishId)->get()->getRowArray();
        $parishName = $parish['name'] ?? 'Parish';

        $renderRows = static function (array $accounts): string {
            if (empty($accounts)) {
                return '<tr><td colspan="3" style="font-style: italic; color: #64748b;">' . htmlspecialchars(lang('Finance.noneLabel')) . '</td></tr>';
            }
            $html = '';
            foreach ($accounts as $acc) {
                $html .= '<tr>'
                    . '<td style="width: 20%;">' . htmlspecialchars((string) $acc['code']) . '</td>'
                    . '<td style="width: 55%;">' . htmlspecialchars((string) $acc['name']) . '</td>'
                    . '<td style="width: 25%; text-align: right;">$' . number_format((float) $acc['balance'], 2) . '</td>'
                    . '</tr>';
            }
            return $html;
        };

        $statusColor = $bs['is_balanced'] ? '#15803d' : '#b91c1c';
        $statusBg = $bs['is_balanced'] ? '#f0fdf4' : '#fef2f2';
        $statusText = $bs['is_balanced'] ? lang('Finance.balanceSheetBalanced') : lang('Finance.balanceSheetUnbalanced');

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Finance.balanceSheetTitle')) . '</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #0f172a; margin: 20px; line-height: 1.4; }
                h1 { font-size: 18px; margin: 0 0 4px; color: #0f172a; }
                .subtitle { font-size: 12px; color: #475569; margin-bottom: 12px; }
                .status-box { padding: 6px 12px; background: ' . $statusBg . '; color: ' . $statusColor . '; font-weight: bold; border-radius: 4px; display: inline-block; margin-bottom: 15px; border: 1px solid ' . $statusColor . '; }
                .section-header { font-size: 13px; font-weight: bold; background: #f1f5f9; padding: 6px 8px; margin: 14px 0 6px; border-left: 3px solid #0284c7; }
                .subsection-header { font-size: 11px; font-weight: bold; color: #475569; padding: 4px 8px; margin-top: 6px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
                th, td { padding: 5px 8px; text-align: left; }
                tr.subtotal td { border-top: 1px solid #cbd5e1; font-weight: bold; }
                tr.total td { border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; font-weight: bold; font-size: 12px; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($parishName) . '</h1>
            <div class="subtitle">' . htmlspecialchars(lang('Finance.balanceSheetTitle')) . ' — ' . htmlspecialchars(lang('Finance.asOfDate')) . ': ' . htmlspecialchars($asOfDate) . '</div>
            <div class="status-box">' . htmlspecialchars($statusText) . '</div>

            <div class="section-header">' . htmlspecialchars(lang('Finance.assetsHeading')) . '</div>
            <div class="subsection-header">' . htmlspecialchars(lang('Finance.currentAssetsHeading')) . '</div>
            <table>
                ' . $renderRows($bs['current_assets']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalCurrentAssets')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_current_assets'], 2) . '</td></tr>
            </table>

            <div class="subsection-header">' . htmlspecialchars(lang('Finance.fixedAssetsHeading')) . '</div>
            <table>
                ' . $renderRows($bs['fixed_assets']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalFixedAssets')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_fixed_assets'], 2) . '</td></tr>
                <tr class="total"><td colspan="2">' . htmlspecialchars(lang('Finance.totalAssetsHeading')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_assets'], 2) . '</td></tr>
            </table>

            <div class="section-header">' . htmlspecialchars(lang('Finance.liabilitiesHeading')) . '</div>
            <div class="subsection-header">' . htmlspecialchars(lang('Finance.currentLiabilitiesHeading')) . '</div>
            <table>
                ' . $renderRows($bs['current_liabilities']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalCurrentLiabilities')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_current_liabilities'], 2) . '</td></tr>
            </table>

            <div class="subsection-header">' . htmlspecialchars(lang('Finance.longTermLiabilitiesHeading')) . '</div>
            <table>
                ' . $renderRows($bs['long_term_liabilities']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalLongTermLiabilities')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_long_term_liabilities'], 2) . '</td></tr>
                <tr class="total"><td colspan="2">' . htmlspecialchars(lang('Finance.totalLiabilitiesHeading')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_liabilities'], 2) . '</td></tr>
            </table>

            <div class="section-header">' . htmlspecialchars(lang('Finance.netAssetsHeading')) . '</div>
            <table>
                ' . $renderRows($bs['equity_accounts']) . '
                <tr><td colspan="2">' . htmlspecialchars(lang('Finance.currentPeriodNetChange')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['current_period_net_change'], 2) . '</td></tr>
                <tr class="total"><td colspan="2">' . htmlspecialchars(lang('Finance.totalNetAssetsHeading')) . '</td><td style="text-align: right;">$' . number_format((float) $bs['total_net_assets'], 2) . '</td></tr>
            </table>

            <div style="margin-top: 15px;">
                <table>
                    <tr class="total" style="background: #f8fafc;">
                        <td colspan="2" style="font-size: 13px;">' . htmlspecialchars(lang('Finance.totalLiabilitiesAndNetAssetsHeading')) . '</td>
                        <td style="text-align: right; font-size: 13px;">$' . number_format((float) $bs['total_liabilities_and_net_assets'], 2) . '</td>
                    </tr>
                </table>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'balance_sheet_' . $parishId . '_' . date('Ymd', strtotime($asOfDate)) . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    /**
     * Export Statement of Activities (Income Statement) as CSV.
     */
    public function statementOfActivitiesCsv()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate   = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        $soa = $this->reportData->buildStatementOfActivities($parishId, $startDate, $endDate);
        $filename = 'statement_of_activities_' . $parishId . '_' . date('Ymd', strtotime($startDate)) . '_' . date('Ymd', strtotime($endDate)) . '.csv';

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }

        fputcsv($output, [lang('Finance.colCategory'), lang('Finance.colAccountCode'), lang('Finance.colAccountName'), lang('Finance.colAmount')]);

        // Revenues
        fputcsv($output, [lang('Finance.revenuesHeading'), '', '', '']);
        fputcsv($output, [lang('Finance.unrestrictedRevenuesHeading'), '', '', '']);
        foreach ($soa['unrestricted_revenues'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['amount'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalUnrestrictedRevenues'), number_format((float) $soa['total_unrestricted_revenues'], 2, '.', '')]);

        fputcsv($output, [lang('Finance.restrictedRevenuesHeading'), '', '', '']);
        foreach ($soa['restricted_revenues'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['amount'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalRestrictedRevenues'), number_format((float) $soa['total_restricted_revenues'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.totalRevenuesHeading'), '', '', number_format((float) $soa['total_revenues'], 2, '.', '')]);
        fputcsv($output, []);

        // Expenses
        fputcsv($output, [lang('Finance.expensesHeading'), '', '', '']);
        fputcsv($output, [lang('Finance.outreachExpensesHeading'), '', '', '']);
        foreach ($soa['outreach_expenses'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['amount'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalOutreachExpenses'), number_format((float) $soa['total_outreach_expenses'], 2, '.', '')]);

        fputcsv($output, [lang('Finance.ministryExpensesHeading'), '', '', '']);
        foreach ($soa['ministry_expenses'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['amount'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalMinistryExpenses'), number_format((float) $soa['total_ministry_expenses'], 2, '.', '')]);

        fputcsv($output, [lang('Finance.operationsExpensesHeading'), '', '', '']);
        foreach ($soa['operations_expenses'] as $acc) {
            fputcsv($output, ['', $acc['code'], $acc['name'], number_format((float) $acc['amount'], 2, '.', '')]);
        }
        fputcsv($output, ['', '', lang('Finance.totalOperationsExpenses'), number_format((float) $soa['total_operations_expenses'], 2, '.', '')]);
        fputcsv($output, [lang('Finance.totalExpensesHeading'), '', '', number_format((float) $soa['total_expenses'], 2, '.', '')]);
        fputcsv($output, []);

        // Net Change
        fputcsv($output, [lang('Finance.changeInNetAssetsHeading'), '', '', number_format((float) $soa['net_change'], 2, '.', '')]);

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent ?: '');
    }

    /**
     * Export Statement of Activities (Income Statement) as PDF.
     */
    public function statementOfActivitiesPdf()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate   = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        $soa = $this->reportData->buildStatementOfActivities($parishId, $startDate, $endDate);
        $parish = db_connect()->table('parish')->select('name')->where('id', $parishId)->get()->getRowArray();
        $parishName = $parish['name'] ?? 'Parish';

        $renderRows = static function (array $accounts): string {
            if (empty($accounts)) {
                return '<tr><td colspan="3" style="font-style: italic; color: #64748b;">' . htmlspecialchars(lang('Finance.noneLabel')) . '</td></tr>';
            }
            $html = '';
            foreach ($accounts as $acc) {
                $html .= '<tr>'
                    . '<td style="width: 20%;">' . htmlspecialchars((string) $acc['code']) . '</td>'
                    . '<td style="width: 55%;">' . htmlspecialchars((string) $acc['name']) . '</td>'
                    . '<td style="width: 25%; text-align: right;">$' . number_format((float) $acc['amount'], 2) . '</td>'
                    . '</tr>';
            }
            return $html;
        };

        $netColor = $soa['net_change'] >= 0 ? '#15803d' : '#b91c1c';
        $netBg = $soa['net_change'] >= 0 ? '#f0fdf4' : '#fef2f2';

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Finance.statementOfActivitiesTitle')) . '</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #0f172a; margin: 20px; line-height: 1.4; }
                h1 { font-size: 18px; margin: 0 0 4px; color: #0f172a; }
                .subtitle { font-size: 12px; color: #475569; margin-bottom: 12px; }
                .summary-card { padding: 10px 14px; background: ' . $netBg . '; color: ' . $netColor . '; font-weight: bold; border-radius: 4px; display: inline-block; margin-bottom: 15px; border: 1px solid ' . $netColor . '; font-size: 13px; }
                .section-header { font-size: 13px; font-weight: bold; background: #f1f5f9; padding: 6px 8px; margin: 14px 0 6px; border-left: 3px solid #0284c7; }
                .subsection-header { font-size: 11px; font-weight: bold; color: #475569; padding: 4px 8px; margin-top: 6px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
                th, td { padding: 5px 8px; text-align: left; }
                tr.subtotal td { border-top: 1px solid #cbd5e1; font-weight: bold; }
                tr.total td { border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; font-weight: bold; font-size: 12px; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($parishName) . '</h1>
            <div class="subtitle">' . htmlspecialchars(lang('Finance.statementOfActivitiesTitle')) . ' — ' . htmlspecialchars($startDate) . ' ' . htmlspecialchars(lang('Treasurer.reportTo')) . ' ' . htmlspecialchars($endDate) . '</div>
            <div class="summary-card">' . htmlspecialchars(lang('Finance.changeInNetAssetsHeading')) . ': $' . number_format((float) $soa['net_change'], 2) . '</div>

            <div class="section-header">' . htmlspecialchars(lang('Finance.revenuesHeading')) . '</div>
            <div class="subsection-header">' . htmlspecialchars(lang('Finance.unrestrictedRevenuesHeading')) . '</div>
            <table>
                ' . $renderRows($soa['unrestricted_revenues']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalUnrestrictedRevenues')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_unrestricted_revenues'], 2) . '</td></tr>
            </table>

            <div class="subsection-header">' . htmlspecialchars(lang('Finance.restrictedRevenuesHeading')) . '</div>
            <table>
                ' . $renderRows($soa['restricted_revenues']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalRestrictedRevenues')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_restricted_revenues'], 2) . '</td></tr>
                <tr class="total"><td colspan="2">' . htmlspecialchars(lang('Finance.totalRevenuesHeading')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_revenues'], 2) . '</td></tr>
            </table>

            <div class="section-header">' . htmlspecialchars(lang('Finance.expensesHeading')) . '</div>
            <div class="subsection-header">' . htmlspecialchars(lang('Finance.outreachExpensesHeading')) . '</div>
            <table>
                ' . $renderRows($soa['outreach_expenses']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalOutreachExpenses')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_outreach_expenses'], 2) . '</td></tr>
            </table>

            <div class="subsection-header">' . htmlspecialchars(lang('Finance.ministryExpensesHeading')) . '</div>
            <table>
                ' . $renderRows($soa['ministry_expenses']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalMinistryExpenses')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_ministry_expenses'], 2) . '</td></tr>
            </table>

            <div class="subsection-header">' . htmlspecialchars(lang('Finance.operationsExpensesHeading')) . '</div>
            <table>
                ' . $renderRows($soa['operations_expenses']) . '
                <tr class="subtotal"><td colspan="2">' . htmlspecialchars(lang('Finance.totalOperationsExpenses')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_operations_expenses'], 2) . '</td></tr>
                <tr class="total"><td colspan="2">' . htmlspecialchars(lang('Finance.totalExpensesHeading')) . '</td><td style="text-align: right;">$' . number_format((float) $soa['total_expenses'], 2) . '</td></tr>
            </table>

            <div style="margin-top: 15px;">
                <table>
                    <tr class="total" style="background: ' . $netBg . '; color: ' . $netColor . ';">
                        <td colspan="2" style="font-size: 13px;">' . htmlspecialchars(lang('Finance.changeInNetAssetsHeading')) . '</td>
                        <td style="text-align: right; font-size: 13px;">$' . number_format((float) $soa['net_change'], 2) . '</td>
                    </tr>
                </table>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'statement_of_activities_' . $parishId . '_' . date('Ymd', strtotime($startDate)) . '_' . date('Ymd', strtotime($endDate)) . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }
}

