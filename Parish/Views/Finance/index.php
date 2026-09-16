<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
?>

<!-- Period filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= base_url('parish/finance') ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?= lang('Dashboard.timePeriod') ?></label>
                <select name="period" class="form-select">
                    <option value="today" <?= ($current_period ?? '') === 'today' ? 'selected' : '' ?>><?= lang('Dashboard.periodToday') ?></option>
                    <option value="week" <?= ($current_period ?? '') === 'week' ? 'selected' : '' ?>><?= lang('Dashboard.periodWeek') ?></option>
                    <option value="month" <?= ($current_period ?? '') === 'month' ? 'selected' : '' ?>><?= lang('Dashboard.periodMonth') ?></option>
                    <option value="year" <?= ($current_period ?? '') === 'year' ? 'selected' : '' ?>><?= lang('Dashboard.periodYear') ?></option>
                    <option value="custom" <?= ($current_period ?? '') === 'custom' ? 'selected' : '' ?>><?= lang('Dashboard.periodCustom') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Dashboard.startDate') ?></label>
                <input type="date" name="start_date" class="form-control" value="<?= esc($start_date ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Dashboard.endDate') ?></label>
                <input type="date" name="end_date" class="form-control" value="<?= esc($end_date ?? '') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100" aria-label="<?= esc(lang('App.filter')) ?>"><i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- KPI cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1"><?= lang('Dashboard.totalDonations') ?></h6>
                <h3 class="mb-0"><?= esc(format_currency($total_income ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1"><?= lang('Dashboard.totalExpenses') ?></h6>
                <h3 class="mb-0"><?= esc(format_currency($total_expenses ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1"><?= lang('Dashboard.netLabel') ?></h6>
                <h3 class="mb-0 <?= ($net_amount ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= esc(format_currency($net_amount ?? 0)) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- 12-month trend chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-graph-up" aria-hidden="true"></i> <?= lang('Dashboard.trend12Months') ?></h5>
    </div>
    <div class="card-body" style="min-height: 300px;">
        <canvas id="financeTrendChart"></canvas>
    </div>
</div>

<!-- Recent donations + expenses -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Dashboard.totalDonations') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_donations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th scope="col"><?= lang('Dashboard.colDate') ?></th><th scope="col" class="text-end"><?= lang('Dashboard.colAmount') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($recent_donations as $donation): ?>
                                    <tr>
                                        <td><?= esc($donation['date'] ?? '-') ?></td>
                                        <td class="text-end"><?= esc(format_currency($donation['amount'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noDonationsInPeriod') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Dashboard.totalExpenses') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_expenses)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th scope="col"><?= lang('Dashboard.colDate') ?></th><th scope="col" class="text-end"><?= lang('Dashboard.colAmount') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($recent_expenses as $expense): ?>
                                    <tr>
                                        <td><?= esc($expense['date'] ?? '-') ?></td>
                                        <td class="text-end"><?= esc(format_currency($expense['amount'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noExpenses') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }
</script>
<?= $this->endSection() ?>
