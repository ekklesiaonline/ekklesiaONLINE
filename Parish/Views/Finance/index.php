<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;

$fundTypeColors = [
    'restricted'   => 'warning',
    'permanent'    => 'info',
    'unrestricted' => 'success',
];
?>

<!-- Top Action Toolbar -->
<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <?php if (!empty($can_record_donation)): ?>
        <a href="<?= base_url('donations/create') ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= esc(lang('Finance.recordDonation')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_record_expense)): ?>
        <a href="<?= base_url('expenses/create') ?>" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-dash-circle" aria-hidden="true"></i> <?= esc(lang('Finance.recordExpense')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_view_donations)): ?>
        <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cash-stack" aria-hidden="true"></i> <?= esc(lang('Finance.donationsTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_view_expenses)): ?>
        <a href="<?= base_url('expenses') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i> <?= esc(lang('Finance.expensesTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_manage_funds)): ?>
        <a href="<?= base_url('funds') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-piggy-bank" aria-hidden="true"></i> <?= esc(lang('Finance.fundsTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_manage_banking)): ?>
        <a href="<?= base_url('banking') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bank" aria-hidden="true"></i> <?= esc(lang('Finance.bankAccountsTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_view_ledger)): ?>
        <a href="<?= base_url('journal-entries') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text" aria-hidden="true"></i> <?= esc(lang('Finance.journalEntriesTitle')) ?>
        </a>
        <a href="<?= base_url('accounting-periods') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar3" aria-hidden="true"></i> <?= esc(lang('Finance.accountingPeriodsTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_manage_budgets)): ?>
        <a href="<?= base_url('budgets') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calculator" aria-hidden="true"></i> <?= esc(lang('Finance.budgetsTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_manage_pledges)): ?>
        <a href="<?= base_url('pledges') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-award" aria-hidden="true"></i> <?= esc(lang('Finance.pledgesTitle')) ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($can_view_reports)): ?>
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> <?= esc(lang('Finance.reportsTitle')) ?>
        </a>
    <?php endif; ?>
</div>

<!-- Period filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= base_url('parish/finance') ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1"><?= esc(lang('Dashboard.timePeriod')) ?></label>
                <select name="period" class="form-select form-select-sm">
                    <option value="today" <?= ($current_period ?? '') === 'today' ? 'selected' : '' ?>><?= esc(lang('Dashboard.periodToday')) ?></option>
                    <option value="week" <?= ($current_period ?? '') === 'week' ? 'selected' : '' ?>><?= esc(lang('Dashboard.periodWeek')) ?></option>
                    <option value="month" <?= ($current_period ?? '') === 'month' ? 'selected' : '' ?>><?= esc(lang('Dashboard.periodMonth')) ?></option>
                    <option value="year" <?= ($current_period ?? '') === 'year' ? 'selected' : '' ?>><?= esc(lang('Dashboard.periodYear')) ?></option>
                    <option value="custom" <?= ($current_period ?? '') === 'custom' ? 'selected' : '' ?>><?= esc(lang('Dashboard.periodCustom')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1"><?= esc(lang('Dashboard.startDate')) ?></label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= esc($start_date ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1"><?= esc(lang('Dashboard.endDate')) ?></label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= esc($end_date ?? '') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100" aria-label="<?= esc(lang('App.filter')) ?>">
                    <i class="bi bi-funnel" aria-hidden="true"></i> <?= esc(lang('App.filter')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card success h-100 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small mb-1"><?= esc(lang('Dashboard.totalDonations')) ?></h6>
                <h3 class="mb-0 text-success">$<?= esc(format_currency($total_income ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card danger h-100 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small mb-1"><?= esc(lang('Dashboard.totalExpenses')) ?></h6>
                <h3 class="mb-0 text-danger">$<?= esc(format_currency($total_expenses ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card primary h-100 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small mb-1"><?= esc(lang('Dashboard.netLabel')) ?></h6>
                <h3 class="mb-0 <?= ($net_amount ?? 0) >= 0 ? 'text-primary' : 'text-danger' ?>">
                    $<?= esc(format_currency($net_amount ?? 0)) ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card info h-100 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small mb-1"><?= esc(lang('Finance.totalCashBalance')) ?></h6>
                <h3 class="mb-0 text-info">$<?= esc(format_currency($total_cash_balance ?? 0)) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Stewardship Pledges & Bank Accounts Row -->
<div class="row g-4 mb-4">
    <!-- Stewardship & Annual Pledges -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-award me-1" aria-hidden="true"></i>
                    <?= esc(lang('Finance.pledgeProgressTitle')) ?> (<?= esc($pledge_summary['year'] ?? date('Y')) ?>)
                </h6>
                <?php if (!empty($can_manage_pledges)): ?>
                    <a href="<?= base_url('pledges') ?>" class="btn btn-outline-primary btn-sm">
                        <?= esc(lang('Finance.managePledges')) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <small class="text-muted d-block"><?= esc(lang('Finance.pledgedTotal')) ?></small>
                        <span class="fw-semibold text-primary">$<?= esc(format_currency($pledge_summary['pledged_total'] ?? 0)) ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block"><?= esc(lang('Finance.fulfilledTotal')) ?></small>
                        <span class="fw-semibold text-success">$<?= esc(format_currency($pledge_summary['fulfilled_total'] ?? 0)) ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block"><?= esc(lang('Finance.remainingTotal')) ?></small>
                        <span class="fw-semibold text-secondary">$<?= esc(format_currency($pledge_summary['remaining_total'] ?? 0)) ?></span>
                    </div>
                </div>

                <div class="progress mb-2" style="height: 18px;" role="progressbar" aria-valuenow="<?= min(100, max(0, (float) ($pledge_summary['fulfillment_pct'] ?? 0))) ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= min(100, max(0, (float) ($pledge_summary['fulfillment_pct'] ?? 0))) ?>%;">
                        <?= esc(number_format((float) ($pledge_summary['fulfillment_pct'] ?? 0), 1)) ?>%
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="badge border border-primary text-primary fw-normal">
                        <i class="bi bi-people me-1" aria-hidden="true"></i>
                        <?= esc(lang('Finance.pledgeCount', [$pledge_summary['total_pledges'] ?? 0])) ?>
                    </span>
                    <small class="text-muted">
                        <?= esc(number_format((float) ($pledge_summary['fulfillment_pct'] ?? 0), 1)) ?>% <?= esc(lang('Finance.fulfilledTotal')) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Accounts & Cash Balance -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-bank me-1" aria-hidden="true"></i>
                    <?= esc(lang('Finance.bankBalancesTitle')) ?>
                </h6>
                <?php if (!empty($can_manage_banking)): ?>
                    <a href="<?= base_url('banking') ?>" class="btn btn-outline-primary btn-sm">
                        <?= esc(lang('Finance.manageBanking')) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($bank_accounts)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('App.colName')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.bankInstitution') ?? 'Institution') ?></th>
                                    <th scope="col" class="text-end"><?= esc(lang('Finance.colAmount')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bank_accounts as $account): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?= esc($account['name'] ?? '-') ?></span>
                                            <?php if (!empty($account['masked_account_number'])): ?>
                                                <small class="text-muted d-block"><?= esc($account['masked_account_number']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= esc($account['institution_name'] ?? '-') ?></td>
                                        <td class="text-end fw-semibold">
                                            $<?= esc(format_currency($account['current_balance'] ?? 0)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= esc(lang('Finance.noActiveBankAccounts')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 12-Month Trend Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-graph-up me-1" aria-hidden="true"></i> <?= esc(lang('Dashboard.trend12Months')) ?></h6>
    </div>
    <div class="card-body" style="min-height: 320px;">
        <canvas id="financeTrendChart"></canvas>
    </div>
</div>

<!-- Recent Donations & Expenses -->
<div class="row g-4 mb-4">
    <!-- Recent Donations -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-cash-stack me-1" aria-hidden="true"></i> <?= esc(lang('Dashboard.totalDonations')) ?></h6>
                <?php if (!empty($can_view_donations)): ?>
                    <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary btn-sm">
                        <?= esc(lang('Finance.viewAllDonations')) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_donations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Dashboard.colDate')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colDonor')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colFund')) ?></th>
                                    <th scope="col" class="text-end"><?= esc(lang('Dashboard.colAmount')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_donations as $donation): ?>
                                    <?php
                                    $donor = trim((string) (($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? '')));
                                    if ($donor === '') {
                                        $donor = lang('Finance.anonymous');
                                    }
                                    ?>
                                    <tr>
                                        <td><?= esc($donation['date'] ?? '-') ?></td>
                                        <td><?= esc($donor) ?></td>
                                        <td><?= esc($donation['fund_name'] ?? ($donation['category'] ?? '-')) ?></td>
                                        <td class="text-end fw-semibold text-success">
                                            $<?= esc(format_currency($donation['amount'] ?? 0)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= esc(lang('Dashboard.noDonationsInPeriod')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Expenses -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-receipt-cutoff me-1" aria-hidden="true"></i> <?= esc(lang('Dashboard.totalExpenses')) ?></h6>
                <?php if (!empty($can_view_expenses)): ?>
                    <a href="<?= base_url('expenses') ?>" class="btn btn-outline-secondary btn-sm">
                        <?= esc(lang('Finance.viewAllExpenses')) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_expenses)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Dashboard.colDate')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colDescription')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colFund')) ?></th>
                                    <th scope="col" class="text-end"><?= esc(lang('Dashboard.colAmount')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_expenses as $expense): ?>
                                    <tr>
                                        <td><?= esc($expense['date'] ?? '-') ?></td>
                                        <td><?= esc($expense['description'] ?? ($expense['category'] ?? '-')) ?></td>
                                        <td><?= esc($expense['fund_name'] ?? '-') ?></td>
                                        <td class="text-end fw-semibold text-danger">
                                            $<?= esc(format_currency($expense['amount'] ?? 0)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= esc(lang('Dashboard.noExpenses')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Active Funds Summary -->
<?php if (!empty($funds_summary)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-piggy-bank me-1" aria-hidden="true"></i>
                <?= esc(lang('Finance.activeFundsTitle')) ?>
            </h6>
            <?php if (!empty($can_manage_funds)): ?>
                <a href="<?= base_url('funds') ?>" class="btn btn-outline-secondary btn-sm">
                    <?= esc(lang('Finance.viewAllFunds')) ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Finance.fundName')) ?></th>
                            <th scope="col"><?= esc(lang('Finance.fundType')) ?></th>
                            <th scope="col" class="text-end"><?= esc(lang('Finance.colAmount')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funds_summary as $fund): ?>
                            <?php
                            $fType = (string) ($fund['fund_type'] ?? 'unrestricted');
                            $color = $fundTypeColors[$fType] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($fund['name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge border border-<?= esc($color) ?> text-<?= esc($color) ?> fw-normal">
                                        <?= esc(ucfirst($fType)) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">
                                    $<?= esc(format_currency($fund['net_balance'] ?? 0)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const trendLabels = <?= json_encode($finance_trend_labels ?? []) ?>;
    const incomeTrend = <?= json_encode($finance_income_trend ?? []) ?>;
    const expenseTrend = <?= json_encode($finance_expense_trend ?? []) ?>;
    const trendCtx = document.getElementById('financeTrendChart');
    if (trendCtx && trendLabels.length > 0) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    { label: '<?= esc(lang('Dashboard.chartLabelIncome')) ?>', data: incomeTrend, backgroundColor: 'rgba(25, 135, 84, 0.7)' },
                    { label: '<?= esc(lang('Dashboard.chartLabelExpenses')) ?>', data: expenseTrend, backgroundColor: 'rgba(220, 53, 69, 0.7)' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + Number(value).toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + Number(context.raw).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                }
            },
        });
    }
</script>
<?= $this->endSection() ?>
