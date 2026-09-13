<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$periodLabels = [
    'today' => lang('Dashboard.periodToday'),
    'week' => lang('Dashboard.periodWeek'),
    'month' => lang('Dashboard.periodMonth'),
    'year' => lang('Dashboard.periodYear'),
    'custom' => lang('Dashboard.periodCustom'),
];
$currentPeriodLabel = $periodLabels[$current_period] ?? $current_period;
?>

<style>
    .finance-chart-wrap {
        position: relative;
        height: 320px;
    }

    @media (max-width: 991.98px) {
        .finance-chart-wrap {
            height: 240px;
        }
    }
</style>

<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="churchFinanceScopeToggle" class="form-label mb-1"><?= lang('Dashboard.parishScopeLiveToggle') ?></label>
                <select id="churchFinanceScopeToggle" class="form-select">
                    <option value="all" selected><?= lang('App.scopeAllParishes') ?></option>
                    <?php foreach ($all_churches as $parish): ?>
                        <?php $churchUeid = trim((string) ($parish['parish_ueid'] ?? '')); ?>
                        <option value="<?= (int) $parish['id'] ?>\"><?= esc($parish['name'] . ($churchUeid !== '' ? ' (' . $churchUeid . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7">
                <small class="text-muted">
                    <?= lang('Dashboard.switchNetworkSingleParishHint') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#financeFilterPanel"
            aria-expanded="<?= $current_period === 'custom' ? 'true' : 'false' ?>"
            aria-controls="financeFilterPanel">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="financeFilterPanel" class="collapse <?= $current_period === 'custom' ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="GET" action="<?= base_url('diocesan/finance') ?>" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('Dashboard.timePeriod') ?></label>
                    <select name="period" class="form-select" id="periodSelect">
                        <option value="today" <?= $current_period === 'today' ? 'selected' : '' ?>><?= lang('Dashboard.periodToday') ?></option>
                        <option value="week" <?= $current_period === 'week' ? 'selected' : '' ?>><?= lang('Dashboard.periodWeek') ?></option>
                        <option value="month" <?= $current_period === 'month' ? 'selected' : '' ?>><?= lang('Dashboard.periodMonth') ?></option>
                        <option value="year" <?= $current_period === 'year' ? 'selected' : '' ?>><?= lang('Dashboard.periodYear') ?></option>
                        <option value="custom" <?= $current_period === 'custom' ? 'selected' : '' ?>><?= lang('Dashboard.periodCustom') ?></option>
                    </select>
                </div>
                <div class="col-md-3" id="customDatesDiv" style="display: <?= $current_period === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label"><?= lang('Dashboard.startDate') ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($start_date) ?>">
                </div>
                <div class="col-md-3" id="customDatesDiv2" style="display: <?= $current_period === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label"><?= lang('Dashboard.endDate') ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc($end_date) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> <?= lang('Dashboard.applyFilter') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.totalIncome') ?></h6>
                        <h3 class="mb-0 text-success" id="financeTileIncome">$<?= number_format($total_income, 2) ?></h3>
                    </div>
                    <div class="text-success fs-1">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.totalExpenses') ?></h6>
                        <h3 class="mb-0 text-danger" id="financeTileExpenses">$<?= number_format($total_expenses, 2) ?></h3>
                    </div>
                    <div class="text-danger fs-1">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card <?= $net_amount >= 0 ? 'primary' : 'warning' ?> h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.netPeriod') ?></h6>
                        <h3 class="mb-0 <?= $net_amount >= 0 ? 'text-primary' : 'text-warning' ?>" id="financeTileNet">$<?= number_format($net_amount, 2) ?></h3>
                        <small class="text-muted" id="financeTileNetStatus"><?= $net_amount >= 0 ? lang('Dashboard.surplusLabel') : lang('Dashboard.deficitLabel') ?></small>
                    </div>
                    <div class="<?= $net_amount >= 0 ? 'text-primary' : 'text-warning' ?> fs-1">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-stack"></i> <?= lang('Dashboard.financialSummaryPeriod', [$currentPeriodLabel]) ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h6 class="text-muted"><?= lang('Dashboard.totalIncome') ?></h6>
                        <h4 class="text-success" id="financialIncome">$<?= number_format($total_income, 2) ?></h4>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted"><?= lang('Dashboard.totalExpenses') ?></h6>
                        <h4 class="text-danger" id="financialExpenses">$<?= number_format($total_expenses, 2) ?></h4>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted"><?= lang('Dashboard.netLabel') ?></span>
                    <strong id="financialNet" class="<?= $net_amount >= 0 ? 'text-success' : 'text-danger' ?>">
                        $<?= number_format($net_amount, 2) ?>
                    </strong>
                </div>
                <small id="financialNetStatus" class="text-muted d-block mb-2"><?= $net_amount >= 0 ? lang('Dashboard.surplusLabel') : lang('Dashboard.deficitLabel') ?></small>
                <div class="progress" style="height: 30px;">
                    <div id="financeProgressIncome" class="progress-bar bg-success" role="progressbar"
                        style="width: <?= $total_income + $total_expenses > 0 ? ($total_income / ($total_income + $total_expenses) * 100) : 50 ?>%">
                        <?= lang('Dashboard.incomeLabel') ?>
                    </div>
                    <div id="financeProgressExpenses" class="progress-bar bg-danger" role="progressbar"
                        style="width: <?= $total_income + $total_expenses > 0 ? ($total_expenses / ($total_income + $total_expenses) * 100) : 50 ?>%">
                        <?= lang('Dashboard.expenseLabel') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bank"></i> <?= lang('Dashboard.periodSnapshot') ?></h5>
                <a href="<?= base_url('parish_list') ?>" class="btn btn-sm btn-outline-primary"><?= lang('Dashboard.manageParishes') ?></a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted"><?= lang('Dashboard.scopeLabel') ?></span>
                    <strong id="financeScopeLabel"><?= lang('App.scopeAllParishes') ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted"><?= lang('Dashboard.periodLabel') ?></span>
                    <strong><?= esc($currentPeriodLabel) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted"><?= lang('Dashboard.startDate') ?></span>
                    <strong><?= esc($start_date) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted"><?= lang('Dashboard.endDate') ?></span>
                    <strong><?= esc($end_date) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> <?= lang('Dashboard.incomeVsExpensesByParish') ?></h5>
            </div>
            <div class="card-body">
                <div class="finance-chart-wrap">
                    <canvas id="financeComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up-arrow"></i> <?= lang('Dashboard.monthlyTrendLast12Months') ?></h5>
            </div>
            <div class="card-body">
                <div class="finance-chart-wrap">
                    <canvas id="financeTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> <?= lang('Dashboard.parishesComparison') ?></h5>
                <span class="text-muted small"><?= lang('Dashboard.incomeExpensesNetByParish') ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= lang('Dashboard.parishLabel') ?></th>
                                <th class="text-end"><?= lang('Dashboard.incomeLabel') ?></th>
                                <th class="text-end"><?= lang('Dashboard.expenseLabel') ?></th>
                                <th class="text-end"><?= lang('Dashboard.netLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody id="financeComparisonBody">
                            <?php
                            $incomeByParishMap = [];
                            foreach ($income_by_parish as $row) {
                                $incomeByParishMap[$row['parish_id']] = $row;
                            }
                            $expensesByParishMap = [];
                            foreach ($expenses_by_parish as $row) {
                                $expensesByParishMap[$row['parish_id']] = $row;
                            }

                            foreach ($all_parishes as $parish):
                                $parishIncome = (float) ($incomeByParishMap[$parish['id']]['total_donations'] ?? 0);
                                $parishExpenses = (float) ($expensesByParishMap[$parish['id']]['total_expenses'] ?? 0);
                                $parishNet = $parishIncome - $parishExpenses;
                                $parishUeid = trim((string) ($parish['parish_ueid'] ?? ''));
                            ?>
                                <tr data-parish-id="<?= (int) $parish['id'] ?>">
                                    <td>
                                        <strong><?= esc($parish['name']) ?></strong>
                                        <?php if ($parishUeid !== ''): ?>
                                            <div class="small text-muted"><?= esc($parishUeid) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-success">$<?= number_format($parishIncome, 2) ?></td>
                                    <td class="text-end text-danger">$<?= number_format($parishExpenses, 2) ?></td>
                                    <td class="text-end <?= $parishNet >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($parishNet, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('periodSelect')?.addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        document.getElementById('customDatesDiv').style.display = isCustom ? 'block' : 'none';
        document.getElementById('customDatesDiv2').style.display = isCustom ? 'block' : 'none';
    });

    const allParishesRaw = <?= json_encode($all_parishes ?? []) ?>;
    const incomeByParishRaw = <?= json_encode($income_by_parish ?? []) ?>;
    const expensesByParishRaw = <?= json_encode($expenses_by_parish ?? []) ?>;
    const financeTrendLabels = <?= json_encode($finance_trend_labels ?? []) ?>;
    const financeIncomeTrend = <?= json_encode($finance_income_trend ?? []) ?>;
    const financeExpenseTrend = <?= json_encode($finance_expense_trend ?? []) ?>;
    const financeIncomeTrendByParish = <?= json_encode($finance_income_trend_by_parish ?? []) ?>;
    const financeExpenseTrendByParish = <?= json_encode($finance_expense_trend_by_parish ?? []) ?>;
    const financeAllParishesLabel = <?= json_encode(lang('App.scopeAllParishes')) ?>;
    const financeSelectedParishFallback = <?= json_encode(lang('Dashboard.selectedParishFallback')) ?>;
    const financeParishFallbackPrefix = <?= json_encode(lang('Dashboard.parishFallbackPrefix')) ?>;
    const financeIncomeLabel = <?= json_encode(lang('Dashboard.incomeLabel')) ?>;
    const financeExpenseLabel = <?= json_encode(lang('Dashboard.expenseLabel')) ?>;

    const moneyFmt = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    const financeScopeToggle = document.getElementById('parishFinanceScopeToggle');
    const financeComparisonBody = document.getElementById('financeComparisonBody');
    const financeScopeLabel = document.getElementById('financeScopeLabel');
    const financeTileIncome = document.getElementById('financeTileIncome');
    const financeTileExpenses = document.getElementById('financeTileExpenses');
    const financeTileNet = document.getElementById('financeTileNet');
    const financeTileNetStatus = document.getElementById('financeTileNetStatus');
    const financialIncome = document.getElementById('financialIncome');
    const financialExpenses = document.getElementById('financialExpenses');
    const financialNet = document.getElementById('financialNet');
    const financialNetStatus = document.getElementById('financialNetStatus');
    const financeProgressIncome = document.getElementById('financeProgressIncome');
    const financeProgressExpenses = document.getElementById('financeProgressExpenses');
    const financeComparisonChartEl = document.getElementById('financeComparisonChart');
    const financeTrendChartEl = document.getElementById('financeTrendChart');

    let financeComparisonChart = null;
    let financeTrendChart = null;

    const financeMetricsById = {};
    allParishesRaw.forEach((parish) => {
        const parishUeid = String(parish.parish_ueid || '').trim();
        const displayName = parish.name || `${financeParishFallbackPrefix} ${parish.id}`;
        financeMetricsById[Number(parish.id)] = {
            id: Number(parish.id),
            name: parishUeid ? `${displayName} (${parishUeid})` : displayName,
            income: 0,
            expenses: 0,
        };
    });

    incomeByParishRaw.forEach((row) => {
        const id = Number(row.parish_id);
        if (!financeMetricsById[id]) {
            financeMetricsById[id] = {
                id,
                name: row.parish_name || `${financeParishFallbackPrefix} ${id}`,
                income: 0,
                expenses: 0
            };
        }
        financeMetricsById[id].income = Number(row.total_donations || 0);
    });

    expensesByParishRaw.forEach((row) => {
        const id = Number(row.parish_id);
        if (!financeMetricsById[id]) {
            financeMetricsById[id] = {
                id,
                name: row.church_name || `${financeChurchFallbackPrefix} ${id}`,
                income: 0,
                expenses: 0
            };
        }
        financeMetricsById[id].expenses = Number(row.total_expenses || 0);
    });

    const financeNetworkBaseline = {
        income: <?= (float) ($total_income ?? 0) ?>,
        expenses: <?= (float) ($total_expenses ?? 0) ?>,
    };

    function buildComparisonChart(scopeChurchId = 'all') {
        if (!financeComparisonChartEl) {
            return;
        }

        if (financeComparisonChart) {
            financeComparisonChart.destroy();
        }

        let rows = Object.values(financeMetricsById)
            .sort((left, right) => (right.income + right.expenses) - (left.income + left.expenses));

        if (scopeChurchId !== 'all') {
            const selected = financeMetricsById[Number(scopeChurchId)];
            rows = selected ? [selected] : [];
        } else {
            rows = rows.slice(0, 10);
        }

        financeComparisonChart = new Chart(financeComparisonChartEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.name),
                datasets: [{
                        label: financeIncomeLabel,
                        data: rows.map((row) => row.income),
                        backgroundColor: 'rgba(25, 135, 84, 0.75)',
                        borderColor: '#198754',
                        borderWidth: 1,
                    },
                    {
                        label: financeExpenseLabel,
                        data: rows.map((row) => row.expenses),
                        backgroundColor: 'rgba(220, 53, 69, 0.75)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        position: 'top',
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback(value) {
                                return `$${moneyFmt.format(value)}`;
                            },
                        },
                    },
                },
            },
        });
    }

    function buildTrendChart(scopeChurchId = 'all') {
        if (!financeTrendChartEl) {
            return;
        }

        if (financeTrendChart) {
            financeTrendChart.destroy();
        }

        const incomeSeries = scopeChurchId === 'all' ?
            financeIncomeTrend :
            (financeIncomeTrendByParish[String(scopeChurchId)] || financeIncomeTrendByParish[Number(scopeChurchId)] || Array(financeTrendLabels.length).fill(0));
        const expenseSeries = scopeChurchId === 'all' ?
            financeExpenseTrend :
            (financeExpenseTrendByParish[String(scopeChurchId)] || financeExpenseTrendByParish[Number(scopeChurchId)] || Array(financeTrendLabels.length).fill(0));

        financeTrendChart = new Chart(financeTrendChartEl.getContext('2d'), {
            type: 'line',
            data: {
                labels: financeTrendLabels,
                datasets: [{
                        label: financeIncomeLabel,
                        data: incomeSeries,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.12)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: financeExpenseLabel,
                        data: expenseSeries,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.12)',
                        fill: true,
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback(value) {
                                return `$${moneyFmt.format(value)}`;
                            },
                        },
                    },
                },
            },
        });
    }

    function setFinancialSummary(income, expenses) {
        const net = income - expenses;
        const total = income + expenses;
        const incomePct = total > 0 ? (income / total) * 100 : 50;
        const expensesPct = total > 0 ? (expenses / total) * 100 : 50;

        financeTileIncome.textContent = `$${moneyFmt.format(income)}`;
        financeTileExpenses.textContent = `$${moneyFmt.format(expenses)}`;
        financeTileNet.textContent = `$${moneyFmt.format(net)}`;
        financeTileNet.className = `mb-0 ${net >= 0 ? 'text-primary' : 'text-warning'}`;
        financeTileNetStatus.textContent = net >= 0 ? <?= json_encode(lang('Dashboard.surplusLabel')) ?> : <?= json_encode(lang('Dashboard.deficitLabel')) ?>;

        financialIncome.textContent = `$${moneyFmt.format(income)}`;
        financialExpenses.textContent = `$${moneyFmt.format(expenses)}`;
        financialNet.textContent = `$${moneyFmt.format(net)}`;
        financialNet.className = net >= 0 ? 'text-success' : 'text-danger';
        financialNetStatus.textContent = net >= 0 ? <?= json_encode(lang('Dashboard.surplusLabel')) ?> : <?= json_encode(lang('Dashboard.deficitLabel')) ?>;
        financeProgressIncome.style.width = `${incomePct}%`;
        financeProgressExpenses.style.width = `${expensesPct}%`;
    }

    function applyFinanceScope(scopeChurchId = 'all') {
        const rows = financeComparisonBody ? financeComparisonBody.querySelectorAll('tr[data-church-id]') : [];

        if (scopeChurchId === 'all') {
            financeScopeLabel.textContent = financeAllChurchesLabel;
            setFinancialSummary(financeNetworkBaseline.income, financeNetworkBaseline.expenses);
            rows.forEach((row) => {
                row.style.display = '';
            });
            buildComparisonChart('all');
            buildTrendChart('all');
            return;
        }

        const selected = financeMetricsById[Number(scopeChurchId)] || {
            income: 0,
            expenses: 0,
            name: financeSelectedChurchFallback
        };
        financeScopeLabel.textContent = selected.name;
        setFinancialSummary(selected.income, selected.expenses);

        rows.forEach((row) => {
            row.style.display = Number(row.dataset.churchId) === Number(scopeChurchId) ? '' : 'none';
        });

        buildComparisonChart(scopeChurchId);
        buildTrendChart(scopeChurchId);
    }

    if (financeScopeToggle) {
        financeScopeToggle.addEventListener('change', function() {
            applyFinanceScope(this.value);
        });

        applyFinanceScope(financeScopeToggle.value || 'all');
    }
</script>

<?= $this->endSection() ?>