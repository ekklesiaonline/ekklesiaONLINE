<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * ROI – Second Visit (Followup Impact) Report
 *
 * Variables injected by Clergy\Controllers\Reports::roiSecondVisit():
 *   $report_start_date  string    Y-m-d
 *   $report_end_date    string    Y-m-d
 *   $with_followup      array     total_guests, converted, pct
 *   $without_followup   array     total_guests, converted, pct
 *   $week_breakdown     array[]   iso_week, week_start, new_guests, converted, received_followup
 *   $chart_labels       string    JSON ["Received Followup","No Followup"]
 *   $chart_total        string    JSON [int, int]
 *   $chart_converted    string    JSON [int, int]
 *   $chart_pcts         string    JSON [float, float]
 */
?>

<!-- Top action row -->
<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('guests') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Guests
    </a>
    <a href="<?= base_url('guests/reports/followup-completion?start_date=' . esc($report_start_date, 'url') . '&end_date=' . esc($report_end_date, 'url')) ?>"
        class="btn btn-outline-primary btn-sm">
        <i class="bi bi-check2-square"></i> Completion Report
    </a>
    <a href="<?= base_url('guests/reports/export-csv/roi-second-visit?start_date=' . esc($report_start_date, 'url') . '&end_date=' . esc($report_end_date, 'url')) ?>"
        class="btn btn-outline-success btn-sm">
        <i class="bi bi-download"></i> Export Summary CSV
    </a>
    <a href="<?= base_url('guests/reports/export-csv/guests-all?start_date=' . esc($report_start_date, 'url') . '&end_date=' . esc($report_end_date, 'url')) ?>"
        class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-file-earmark-spreadsheet"></i> Mail-merge CSV
    </a>
</div>

<!-- Date range filter -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#roiFilterPanel"
            aria-expanded="false">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="roiFilterPanel" class="collapse">
        <div class="card-body border-top">
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
                        <i class="bi bi-funnel"></i> <?= lang('Services.apply') ?>
                    </button>
                </div>
                <div class="col-auto ms-auto text-muted small align-self-end pb-1">
                    <i class="bi bi-calendar3"></i>
                    <?= esc($report_start_date) ?> – <?= esc($report_end_date) ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KPI tiles -->
<div class="row g-3 mb-4">

    <?php
    $wfTotal = (int) ($with_followup['total_guests'] ?? 0);
    $wfConv  = (int) ($with_followup['converted'] ?? 0);
    $wfPct   = (float) ($with_followup['pct'] ?? 0);

    $nfTotal = (int) ($without_followup['total_guests'] ?? 0);
    $nfConv  = (int) ($without_followup['converted'] ?? 0);
    $nfPct   = (float) ($without_followup['pct'] ?? 0);

    $totalGuests = $wfTotal + $nfTotal;
    $uplift      = round($wfPct - $nfPct, 1);
    $upliftClass = $uplift > 0 ? 'text-success' : ($uplift < 0 ? 'text-danger' : 'text-muted');
    $upliftIcon  = $uplift > 0 ? 'bi-arrow-up-circle-fill' : ($uplift < 0 ? 'bi-arrow-down-circle-fill' : 'bi-dash-circle');
    ?>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Guests</p>
                        <h3 class="mb-0"><?= $totalGuests ?></h3>
                    </div>
                    <i class="bi bi-people fs-1 text-secondary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">With Followup – Conversion</p>
                        <h3 class="mb-0 text-success"><?= $wfPct ?>%</h3>
                        <small class="text-muted"><?= $wfConv ?> of <?= $wfTotal ?></small>
                    </div>
                    <i class="bi bi-envelope-check fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">No Followup – Conversion</p>
                        <h3 class="mb-0 text-danger"><?= $nfPct ?>%</h3>
                        <small class="text-muted"><?= $nfConv ?> of <?= $nfTotal ?></small>
                    </div>
                    <i class="bi bi-envelope-slash fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Followup Uplift</p>
                        <h3 class="mb-0 <?= $upliftClass ?>">
                            <i class="bi <?= $upliftIcon ?>"></i>
                            <?= abs($uplift) ?>pp
                        </h3>
                        <small class="text-muted">percentage-point difference</small>
                    </div>
                    <i class="bi bi-graph-up-arrow fs-1 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Grouped bar chart -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-fill text-primary"></i>
        <span class="fw-semibold">Followup Impact – Guest Count vs Conversions</span>
        <small class="text-muted ms-auto">"Conversion" = joined as member within period</small>
    </div>
    <div class="card-body">
        <div style="height: 300px;">
            <canvas id="roiChart" aria-label="ROI followup impact chart" role="img"></canvas>
        </div>
    </div>
</div>

<!-- Cohort summary table -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-table text-secondary"></i>
        <span class="fw-semibold">Cohort Summary</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th scope="col">Cohort</th>
                    <th scope="col" class="text-end">Total Guests</th>
                    <th scope="col" class="text-end">Converted to Member</th>
                    <th scope="col" class="text-end">Conversion Rate</th>
                    <th scope="col" style="min-width:150px;">Rate Visual</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="badge border border-success text-success fw-normal me-1">✓</span>
                        Received Followup
                    </td>
                    <td class="text-end"><?= $wfTotal ?></td>
                    <td class="text-end"><?= $wfConv ?></td>
                    <td class="text-end fw-semibold"><?= $wfPct ?>%</td>
                    <td>
                        <div class="progress" style="height:10px;" title="<?= $wfPct ?>%">
                            <div class="progress-bar bg-success"
                                role="progressbar"
                                style="width:<?= $wfPct ?>%"
                                aria-valuenow="<?= $wfPct ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="badge border border-danger text-danger fw-normal me-1">✗</span>
                        No Followup
                    </td>
                    <td class="text-end"><?= $nfTotal ?></td>
                    <td class="text-end"><?= $nfConv ?></td>
                    <td class="text-end fw-semibold"><?= $nfPct ?>%</td>
                    <td>
                        <div class="progress" style="height:10px;" title="<?= $nfPct ?>%">
                            <div class="progress-bar bg-danger"
                                role="progressbar"
                                style="width:<?= $nfPct ?>%"
                                aria-valuenow="<?= $nfPct ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Weekly breakdown table (only if data exists) -->
<?php if (! empty($week_breakdown)): ?>
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-calendar-week text-secondary"></i>
            <span class="fw-semibold">Weekly Breakdown</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Week of</th>
                        <th scope="col" class="text-end">New Guests</th>
                        <th scope="col" class="text-end">Received Followup</th>
                        <th scope="col" class="text-end">Converted</th>
                        <th scope="col" class="text-end">Conversion %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($week_breakdown as $row):
                        $wkGuests  = (int) $row['new_guests'];
                        $wkConv    = (int) $row['converted'];
                        $wkFu      = (int) $row['received_followup'];
                        $wkPct     = $wkGuests > 0 ? round($wkConv / $wkGuests * 100, 1) : 0.0;
                    ?>
                        <tr>
                            <td><?= esc($row['week_start']) ?></td>
                            <td class="text-end"><?= $wkGuests ?></td>
                            <td class="text-end"><?= $wkFu ?></td>
                            <td class="text-end"><?= $wkConv ?></td>
                            <td class="text-end"><?= $wkPct ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
    (function() {
        'use strict';

        const labels = <?= $chart_labels ?>;
        const totals = <?= $chart_total ?>;
        const converted = <?= $chart_converted ?>;

        if (!labels.length || !window.Chart) {
            return;
        }

        new Chart(document.getElementById('roiChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                        label: 'Total Guests',
                        data: totals,
                        backgroundColor: ['rgba(13,110,253,0.65)', 'rgba(108,117,125,0.5)'],
                        borderColor: ['rgba(13,110,253,1)', 'rgba(108,117,125,1)'],
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Converted to Member',
                        data: converted,
                        backgroundColor: ['rgba(25,135,84,0.75)', 'rgba(220,53,69,0.6)'],
                        borderColor: ['rgba(25,135,84,1)', 'rgba(220,53,69,1)'],
                        borderWidth: 1,
                        borderRadius: 4,
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
                                const pcts = <?= $chart_pcts ?>;
                                return 'Conversion rate: ' + pcts[idx] + '%';
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Guests'
                        },
                    },
                },
            },
        });
    }());
</script>

<?= $this->endSection() ?>