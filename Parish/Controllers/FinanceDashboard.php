<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish finance dashboard — scoped to the treasurer's own parish.
 *
 * Surfaces high-level financial KPIs, 12-month trends, recent activity,
 * stewardship pledge fulfillment, bank balances, and quick actions.
 */
class FinanceDashboard extends BaseController
{
    protected $helpers = ['auth', 'url', 'form', 'text', 'format'];

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
        $expenseModel  = model('ExpenseModel');

        $today = date('Y-m-d');
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd = date('Y-m-t');
        $thisYearStart = date('Y-01-01');
        $thisYearEnd = date('Y-12-31');

        $period = (string) ($this->request->getGet('period') ?? 'month');
        $customStart = (string) ($this->request->getGet('start_date') ?? '');
        $customEnd = (string) ($this->request->getGet('end_date') ?? '');

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
                $startDate = $customStart !== '' ? $customStart : $thisMonthStart;
                $endDate = $customEnd !== '' ? $customEnd : $thisMonthEnd;
                break;
            case 'month':
            default:
                $period = 'month';
                $startDate = $thisMonthStart;
                $endDate = $thisMonthEnd;
                break;
        }

        // Period totals: properly decrypted donations and expenses
        $donationSummary = $donationModel->getParishSummary($parishId, $startDate, $endDate);
        $totalIncome = (float) ($donationSummary['total'] ?? 0.0);
        $totalExpenses = (float) $expenseModel->getTotalExpenses($parishId, $startDate, $endDate);
        $netAmount = $totalIncome - $totalExpenses;

        // Recent activity
        $recentDonations = $donationModel->getParishDonations($parishId);
        if (count($recentDonations) > 10) {
            $recentDonations = array_slice($recentDonations, 0, 10);
        }

        $recentExpenses = $expenseModel->getParishExpenses($parishId);
        if (count($recentExpenses) > 10) {
            $recentExpenses = array_slice($recentExpenses, 0, 10);
        }

        // 12-Month Trend: single query for income and single query for expenses (optimized from 24 queries)
        $trendMonths = 12;
        $twelveMonthsAgo = date('Y-m-01', strtotime('-' . ($trendMonths - 1) . ' months'));

        $donationRows = $donationModel->db->table('donation')
            ->select('date, amount')
            ->where('parish_id', $parishId)
            ->where('date >=', $twelveMonthsAgo)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $incomeByMonth = [];
        foreach ($donationRows as $row) {
            $m = date('Y-m', strtotime((string) $row['date']));
            $incomeByMonth[$m] = ($incomeByMonth[$m] ?? 0.0) + $donationModel->normalizeAmount($row['amount'] ?? null);
        }

        $expenseRows = $expenseModel->db->table('expense')
            ->select('date, amount')
            ->where('parish_id', $parishId)
            ->where('date >=', $twelveMonthsAgo)
            ->get()
            ->getResultArray();

        $expenseByMonth = [];
        foreach ($expenseRows as $row) {
            $m = date('Y-m', strtotime((string) $row['date']));
            $expenseByMonth[$m] = ($expenseByMonth[$m] ?? 0.0) + (float) ($row['amount'] ?? 0);
        }

        $trendLabels = [];
        $incomeTrend = [];
        $expenseTrend = [];
        for ($i = $trendMonths - 1; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-{$i} months"));
            $trendLabels[] = date('M Y', strtotime("-{$i} months"));
            $incomeTrend[] = round($incomeByMonth[$m] ?? 0.0, 2);
            $expenseTrend[] = round($expenseByMonth[$m] ?? 0.0, 2);
        }

        // Stewardship & Annual Pledge Progress
        $currentYear = (int) date('Y');
        $pledgeSummary = [
            'year'            => $currentYear,
            'total_pledges'   => 0,
            'pledged_total'   => 0.0,
            'fulfilled_total' => 0.0,
            'fulfillment_pct' => 0.0,
            'remaining_total' => 0.0,
        ];
        if ($this->hasTable('annual_pledge')) {
            $rawPledge = model('AnnualPledgeModel')->getParishPledgeSummary($parishId, $currentYear);
            $pledgedTotal = (float) ($rawPledge['pledged_total'] ?? 0);
            $fulfilledTotal = (float) ($rawPledge['fulfilled_total'] ?? 0);
            $pct = $pledgedTotal > 0 ? round(($fulfilledTotal / $pledgedTotal) * 100, 1) : 0.0;
            $remaining = max(0.0, $pledgedTotal - $fulfilledTotal);

            $pledgeSummary = [
                'year'            => $currentYear,
                'total_pledges'   => (int) ($rawPledge['total_pledges'] ?? 0),
                'pledged_total'   => $pledgedTotal,
                'fulfilled_total' => $fulfilledTotal,
                'fulfillment_pct' => $pct,
                'remaining_total' => $remaining,
            ];
        }

        // Bank Accounts & Cash on Hand
        $bankAccounts = [];
        $totalCashBalance = 0.0;
        if ($this->hasTable('bank_account')) {
            $rawAccounts = model('BankAccountModel')->accountsWithBalances($parishId);
            foreach ($rawAccounts as $acc) {
                if ((int) ($acc['is_active'] ?? 1) === 1) {
                    $bal = (float) ($acc['opening_balance'] ?? 0) + (float) ($acc['txn_sum'] ?? 0);
                    $acc['current_balance'] = $bal;
                    $bankAccounts[] = $acc;
                    $totalCashBalance += $bal;
                }
            }
        }

        // Active Funds Summary
        $fundsSummary = [];
        $totalInFunds = 0.0;
        if ($this->hasTable('fund') && $this->hasTable('chart_of_accounts')) {
            $rawFunds = model('FundModel')->getParishFundsWithBalances($parishId);
            foreach ($rawFunds as $f) {
                if ((int) ($f['is_active'] ?? 1) === 1) {
                    $fundsSummary[] = $f;
                    $totalInFunds += (float) ($f['net_balance'] ?? 0);
                }
            }
        }

        // Permission flags for top action toolbar
        $user = auth()->user();
        $canRecordDonation = $user !== null && ($user->can('donation.create') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canRecordExpense  = $user !== null && ($user->can('expense.create') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canViewDonations  = $user !== null && ($user->can('donation.view') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canViewExpenses   = $user !== null && ($user->can('expense.view') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canManageFunds    = $user !== null && ($user->can('finance.view') || $user->can('finance.manage') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canManageBanking  = $user !== null && ($user->can('finance.view') || $user->can('finance.manage') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canViewLedger     = $user !== null && ($user->can('finance.view') || $user->can('finance.manage') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canManageBudgets  = $user !== null && ($user->can('budget.view') || $user->can('budget.manage') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canManagePledges  = $user !== null && ($user->can('finance.manage') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));
        $canViewReports    = $user !== null && ($user->can('report.view') || $user->inGroup('finance', 'parishadmin', 'systemadmin'));

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishFinanceDashboard')), [
            'parish_id'             => $parishId,
            'current_period'        => $period,
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'total_income'          => $totalIncome,
            'total_expenses'        => $totalExpenses,
            'net_amount'            => $netAmount,
            'recent_donations'      => $recentDonations,
            'recent_expenses'       => $recentExpenses,
            'finance_trend_labels'  => $trendLabels,
            'finance_income_trend'  => $incomeTrend,
            'finance_expense_trend' => $expenseTrend,
            'pledge_summary'        => $pledgeSummary,
            'bank_accounts'         => $bankAccounts,
            'total_cash_balance'    => $totalCashBalance,
            'funds_summary'         => $fundsSummary,
            'total_in_funds'        => $totalInFunds,
            'can_record_donation'   => $canRecordDonation,
            'can_record_expense'    => $canRecordExpense,
            'can_view_donations'    => $canViewDonations,
            'can_view_expenses'     => $canViewExpenses,
            'can_manage_funds'      => $canManageFunds,
            'can_manage_banking'    => $canManageBanking,
            'can_view_ledger'       => $canViewLedger,
            'can_manage_budgets'    => $canManageBudgets,
            'can_manage_pledges'    => $canManagePledges,
            'can_view_reports'      => $canViewReports,
        ]);

        return view('Parish\Views\Finance\index', $data);
    }

    private function hasTable(string $table): bool
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        return $db->tableExists($prefix . $table) || $db->tableExists($table);
    }
}
