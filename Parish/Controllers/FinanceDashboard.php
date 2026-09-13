<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish finance dashboard — scoped to the treasurer's own parish.
 *
 * Replaces the diocese-scoped Treasurer\Controllers\Dashboard.
 */
class FinanceDashboard extends BaseController
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
            case 'year':
                $startDate = $thisYearStart;
                $endDate = $thisYearEnd;
                break;
            case 'custom':
                $startDate = $customStart ?? $thisMonthStart;
                $endDate = $customEnd ?? $thisMonthEnd;
                break;
            case 'month':
            default:
                $startDate = $thisMonthStart;
                $endDate = $thisMonthEnd;
        }

        // Scope all financial queries to this single parish.
        $totalIncome = (float) $donationModel->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->selectSum('amount')
            ->first()['amount'] ?? 0;

        $totalExpenses = (float) $expenseModel->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->selectSum('amount')
            ->first()['amount'] ?? 0;

        $recentDonations = $donationModel->where('parish_id', $parishId)
            ->orderBy('date', 'DESC')
            ->limit(10)
            ->findAll();

        $recentExpenses = $expenseModel->where('parish_id', $parishId)
            ->orderBy('date', 'DESC')
            ->limit(10)
            ->findAll();

        // 12-month trend
        $trendLabels = [];
        $incomeTrend = [];
        $expenseTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = date('Y-m', strtotime("-{$i} months"));
            $trendLabels[] = date('M Y', strtotime("-{$i} months"));
            $incomeTrend[] = (float) $donationModel->where('parish_id', $parishId)
                ->where('date >=', $monthDate . '-01')
                ->where('date <=', date('Y-m-t', strtotime($monthDate . '-01')))
                ->selectSum('amount')
                ->first()['amount'] ?? 0;
            $expenseTrend[] = (float) $expenseModel->where('parish_id', $parishId)
                ->where('date >=', $monthDate . '-01')
                ->where('date <=', date('Y-m-t', strtotime($monthDate . '-01')))
                ->selectSum('amount')
                ->first()['amount'] ?? 0;
        }

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishFinanceDashboard')), [
            'parish_id' => $parishId,
            'current_period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_amount' => $totalIncome - $totalExpenses,
            'recent_donations' => $recentDonations,
            'recent_expenses' => $recentExpenses,
            'finance_trend_labels' => $trendLabels,
            'finance_income_trend' => $incomeTrend,
            'finance_expense_trend' => $expenseTrend,
        ]);

        return view('Parish\Views\Finance\index', $data);
    }
}
