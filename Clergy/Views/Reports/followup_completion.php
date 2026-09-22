<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Followup Completion Report
 *
 * Variables injected by Clergy\Controllers\Reports::followupCompletion():
 *   $report_start_date  string    Y-m-d
 *   $report_end_date    string    Y-m-d
 *   $rows               array[]   week_number, total_actions, completed_actions
 *   $chart_labels       string    JSON  ["Week 1", "Week 2", …]
 *   $chart_completed    string    JSON  [int, …]
 *   $chart_missed       string    JSON  [int, …]
 *   $chart_pcts         string    JSON  [float, …]
 */
?>

<!-- Top action row -->
<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('visitors') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.pageTitle') ?>
    </a>
    <a href="<?= base_url('visitors/reports/roi-second-visit?start_date=' . esc($report_start_date, 'url') . '&end_date=' . esc($report_end_date, 'url')) ?>"
        class="btn btn-outline-primary btn-sm">
        <i class="bi bi-bar-chart-line" aria-hidden="true"></i> <?= lang('Visitors.rptRoiReport') ?>
    </a>
    <a href="<?= base_url('visitors/reports/export-csv/followup-completion?start_date=' . esc($report_start_date, 'url') . '&end_date=' . esc($report_end_date, 'url')) ?>"
        class="btn btn-outline-success btn-sm ms-auto">
        <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Visitors.rptExportCsv') ?>
    </a>
</div>

<!-- Date range filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1 small fw-semibold" for="start_date"><?= lang('Treasurer.fromLabel') ?></label>
                <input type="date" id="start_date" name="start_date"
                    class="form-control form-control-sm"
                    value="<?= esc($report_start_date) ?>"
                    max="<?= esc($report_end_date) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small fw-semibold" for="end_date"><?= lang('Treasurer.toLabel') ?></label>
                <input type="date" id="end_date" name="end_date"
                    class="form-control form-control-sm"
                    value="<?= esc($report_end_date) ?>"
                    max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                </button>
            </div>
            <div class="col-auto ms-auto text-muted small align-self-end pb-1">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <?= esc($report_start_date) ?> – <?= esc($report_end_date) ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
        <?= lang('Visitors.rptNoData') ?>
    </div>
<?php else: ?>

    <!-- Bar chart -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-bar-chart-fill text-primary" aria-hidden="true"></i>
            <span class="fw-semibold"><?= lang('Visitors.rptCompletionChartTitle') ?></span>
            <small class="text-muted ms-auto"><?= lang('Visitors.rptCompletionChartNote') ?></small>
        </div>
        <div class="card-body">
            <div style="height: 340px;">
                <canvas id="completionChart" aria-label="<?= esc(lang('Visitors.rptCompletionChartAria')) ?>" role="img"></canvas>
            </div>
        </div>
    </div>

    <!-- Detail table -->
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-table text-secondary" aria-hidden="true"></i>
            <span class="fw-semibold"><?= lang('Visitors.rptWeeklyDetail') ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Visitors.rptColSequenceWeek') ?></th>
                        <th scope="col" class="text-end"><?= lang('Visitors.rptColScheduled') ?></th>
                        <th scope="col" class="text-end"><?= lang('Visitors.rptColCompleted') ?></th>
                        <th scope="col" class="text-end"><?= lang('Visitors.rptColMissed') ?></th>
                        <th scope="col" class="text-end"><?= lang('Visitors.rptColCompletionPct') ?></th>
                        <th scope="col" style="min-width:140px;"><?= lang('Visitors.rptColProgress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        $total     = (int) $row['total_actions'];
                        $done      = (int) $row['completed_actions'];
                        $missed    = $total - $done;
                        $pct       = $total > 0 ? round($done / $total * 100, 1) : 0.0;
                        $barClass  = $pct >= 75 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger');
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= lang('Visitors.rptWeek') ?> <?= (int) $row['week_number'] ?></td>
                            <td class="text-end"><?= $total ?></td>
                            <td class="text-end text-success"><?= $done ?></td>
                            <td class="text-end text-danger"><?= $missed ?></td>
                            <td class="text-end fw-semibold"><?= $pct ?>%</td>
                            <td>
                                <div class="progress" style="height:10px;" title="<?= $pct ?>%">
                                    <div class="progress-bar <?= $barClass ?>"
                                        role="progressbar"
                                        style="width:<?= $pct ?>%"
                                        aria-valuenow="<?= $pct ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-semibold">
                    <tr>
                        <?php
                        $sumTotal = array_sum(array_column($rows, 'total_actions'));
                        $sumDone  = array_sum(array_column($rows, 'completed_actions'));
                        $sumPct   = $sumTotal > 0 ? round($sumDone / $sumTotal * 100, 1) : 0.0;
                        ?>
                        <td><?= lang('Visitors.rptOverall') ?></td>
                        <td class="text-end"><?= $sumTotal ?></td>
                        <td class="text-end text-success"><?= $sumDone ?></td>
                        <td class="text-end text-danger"><?= $sumTotal - $sumDone ?></td>
                        <td class="text-end"><?= $sumPct ?>%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php endif; ?>

<script>
    (function() {
        'use strict';

        const labels = <?= $chart_labels ?>;
        const completed = <?= $chart_completed ?>;
        const missed = <?= $chart_missed ?>;

        if (!labels.length || !window.Chart) {
            return;
        }

        new Chart(document.getElementById('completionChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                        label: '<?= esc(lang('Visitors.rptCompletedSent'), 'js') ?>',
                        data: completed,
                        backgroundColor: 'rgba(25,135,84,0.75)',
                        borderColor: 'rgba(25,135,84,1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        stack: 'actions',
                    },
                    {
                        label: '<?= esc(lang('Visitors.rptNotCompleted'), 'js') ?>',
                        data: missed,
                        backgroundColor: 'rgba(220,53,69,0.55)',
                        borderColor: 'rgba(220,53,69,0.8)',
                        borderWidth: 1,
                        borderRadius: 4,
                        stack: 'actions',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            footer(items) {
                                const idx = items[0].dataIndex;
                                const total = completed[idx] + missed[idx];
                                const pct = total > 0 ? Math.round(completed[idx] / total * 1000) / 10 : 0;
                                return '<?= esc(lang('Visitors.rptCompletionLabel'), 'js') ?>: ' + pct + '%';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: '<?= esc(lang('Visitors.rptColActions'), 'js') ?>'
                        },
                    },
                },
            },
        });
    }());
</script>

<?= $this->endSection() ?>