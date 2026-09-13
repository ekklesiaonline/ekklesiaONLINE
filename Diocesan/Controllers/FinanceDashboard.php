<?php

namespace Diocesan\Controllers;

use App\Controllers\BaseController;

class FinanceDashboard extends BaseController
{
    public function index()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse(lang('App.diocesanAdminRequired'));
        }

        $parishModel = model('ParishModel');
        $donationModel = model('DonationModel');
        $expenseModel = model('ExpenseModel');

        $today = date('Y-m-d');
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd = date('Y-m-t');
        $thisYearStart = date('Y-01-01');
        $thisYearEnd = date('Y-12-31');

        $period = $this->request->getGet('period') ?? 'month';
        $customStart = $this->request->getGet('start_date');
        $customEnd = $this->request->getGet('end_date');

        switch ($period) {
            case 'today':
                $startDate = $today;
                $endDate = $today;
                break;
            case 'week':
                $startDate = $thisWeekStart;
                $endDate = $thisWeekEnd;
                break;
            case 'month':
                $startDate = $thisMonthStart;
                $endDate = $thisMonthEnd;
                break;
            case 'year':
                $startDate = $thisYearStart;
                $endDate = $thisYearEnd;
                break;
            case 'custom':
                $startDate = $customStart ?? $thisMonthStart;
                $endDate = $customEnd ?? $thisMonthEnd;
                break;
            default:
                $startDate = $thisMonthStart;
                $endDate = $thisMonthEnd;
        }

        // Scope parish list and all financial queries to this diocesan admin's own diocese.
        // Use centralized resolution with fallback to operational parish diocese.
        $scopeDioceseId = $this->resolveDiocesanScopeId();
        if ($scopeDioceseId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Diocesan.accountDiocesanScopeFailed'));
        }

        $allParishes = $parishModel->where('diocese_id', $scopeDioceseId)->orderBy('name', 'ASC')->findAll();

        // Build scoped parish ID list to pass to every financial query.
        $scopedParishIds = array_map(static fn($c) => (int) $c['id'], $allParishes);

        if (empty($scopedParishIds)) {
            $data = array_merge($this->getCommonViewData(lang('App.navFinanceDashboard')), [
                'all_parishes' => [],
                'total_income' => 0,
                'total_expenses' => 0,
                'net_amount' => 0,
                'income_by_parish' => [],
                'expenses_by_parish' => [],
                'finance_trend_labels' => $this->buildMonthLabels(12),
                'finance_income_trend' => array_fill(0, 12, 0),
                'finance_expense_trend' => array_fill(0, 12, 0),
                'finance_income_trend_by_parish' => [],
                'finance_expense_trend_by_parish' => [],
                'current_period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            return view('Diocesan\Views\Dashboard/finance', $data);
        }

        $totalIncome = $donationModel->getAllParishesTotalDonations($startDate, $endDate, $scopedParishIds);
        $totalExpenses = $expenseModel->getAllParishesTotalExpenses($startDate, $endDate, $scopedParishIds);
        $incomeByParish = $donationModel->getDonationsByParish($startDate, $endDate, $scopedParishIds);
        $expensesByParish = $expenseModel->getExpensesByParish($startDate, $endDate, $scopedParishIds);
        $incomeMonthlyTrend = $donationModel->getNetworkMonthlyTrend(12, $scopedParishIds);
        $expenseMonthlyTrend = $expenseModel->getNetworkMonthlyTrend(12, $scopedParishIds);
        $incomeMonthlyTrendByParish = $donationModel->getMonthlyTrendByParish(12, $scopedParishIds);
        $expenseMonthlyTrendByParish = $expenseModel->getMonthlyTrendByParish(12, $scopedParishIds);

        $monthLabels = $this->buildMonthLabels(12);
        $incomeTrendSeries = $this->buildTrendSeries($monthLabels, $incomeMonthlyTrend);
        $expenseTrendSeries = $this->buildTrendSeries($monthLabels, $expenseMonthlyTrend);
        $incomeTrendByParishSeries = $this->buildTrendSeriesByParish($monthLabels, $incomeMonthlyTrendByParish);
        $expenseTrendByParishSeries = $this->buildTrendSeriesByParish($monthLabels, $expenseMonthlyTrendByParish);

        $data = array_merge($this->getCommonViewData(lang('App.navFinanceDashboard')), [
            'all_parishes' => $allParishes,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_amount' => $totalIncome - $totalExpenses,
            'income_by_parish' => $incomeByParish,
            'expenses_by_parish' => $expensesByParish,
            'finance_trend_labels' => $monthLabels,
            'finance_income_trend' => $incomeTrendSeries,
            'finance_expense_trend' => $expenseTrendSeries,
            'finance_income_trend_by_parish' => $incomeTrendByParishSeries,
            'finance_expense_trend_by_parish' => $expenseTrendByParishSeries,
            'current_period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return view('Diocesan\Views\Dashboard/finance', $data);
    }

    protected function buildMonthLabels(int $months): array
    {
        $labels = [];
        for ($index = $months - 1; $index >= 0; $index--) {
            $labels[] = date('M Y', strtotime('-' . $index . ' months'));
        }
        return $labels;
    }

    protected function buildTrendSeries(array $labels, array $rows): array
    {
        $series = array_fill_keys($labels, 0.0);
        foreach ($rows as $row) {
            $label = (string) ($row['month_label'] ?? '');
            if (array_key_exists($label, $series)) {
                $series[$label] = (float) ($row['total_amount'] ?? 0);
            }
        }
        return array_values($series);
    }

    protected function buildTrendSeriesByParish(array $labels, array $rows): array
    {
        $series = [];
        foreach ($rows as $row) {
            $ParishId = (int) ($row['parish_id'] ?? 0);
            if ($ParishId <= 0) {
                continue;
            }
            if (!isset($series[$ParishId])) {
                $series[$ParishId] = array_fill_keys($labels, 0.0);
            }
            $label = (string) ($row['month_label'] ?? '');
            if (array_key_exists($label, $series[$ParishId])) {
                $series[$ParishId][$label] = (float) ($row['total_amount'] ?? 0);
            }
        }
        foreach ($series as $ParishId => $values) {
            $series[$ParishId] = array_values($values);
        }
        return $series;
    }
}
