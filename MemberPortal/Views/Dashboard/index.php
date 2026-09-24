<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};

$formatLocalizedDateTime = static function (?string $value, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
            date_default_timezone_get()
        );
        $formatter->setPattern('MMM d, y h:mm a');
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d H:i', $timestamp);
};
?>

<style>
    .dashboard-kpi-link {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        border-radius: 0.5rem;
    }

    .dashboard-kpi-link .card {
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }

    .dashboard-kpi-link .card .card-body {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .dashboard-kpi-link .card h6 {
        margin-bottom: 0.15rem !important;
    }

    .dashboard-kpi-link .card h3 {
        line-height: 1.1;
    }

    .dashboard-kpi-link:hover {
        transform: translateY(-2px);
    }

    .dashboard-kpi-link:hover .card {
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.12);
    }

    .dashboard-kpi-link:active {
        transform: translateY(0);
    }

    .dashboard-kpi-link:focus-visible {
        outline: 2px solid rgba(var(--bs-primary-rgb), 0.4);
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {

        .dashboard-kpi-link,
        .dashboard-kpi-link .card {
            transition: none;
        }
    }
</style>

<?php if (!empty($can_moderate_registrations) && (int) ($pending_registrations_count ?? 0) > 0): ?>
<div class="row g-4 mb-2">
    <div class="col-12">
        <div class="alert alert-warning d-flex align-items-center justify-content-between shadow-sm mb-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5" aria-hidden="true"></i>
                <div>
                    <strong><?= lang('Members.pendingRegistrationsTitle') ?></strong>
                    <span class="ms-1">(<?= (int) $pending_registrations_count ?>)</span>
                </div>
            </div>
            <a href="<?= base_url('people#pendingRegistrationsCard') ?>" class="btn btn-sm btn-warning">
                <i class="bi bi-person-check me-1" aria-hidden="true"></i> <?= lang('Members.pendingRegistrationsTitle') ?>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Top KPI cards -->
    <div class="col-md-3">
        <a href="<?= base_url('people') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.totalPeople') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($total_people ?? 0)) ?></h3>
                            <small class="text-muted">&nbsp;</small>
                        </div>
                        <div class="text-primary fs-1">
                            <i class="bi bi-people" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('members') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.membersLabel') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($total_members ?? 0)) ?></h3>
                            <small class="text-muted"><?= lang('Dashboard.inLeadership', [number_format((int) ($leadership_members ?? 0))]) ?></small>
                        </div>
                        <div class="text-info fs-1">
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('standing') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.goodStanding') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($good_standing_members ?? 0)) ?></h3>
                            <small class="text-muted">&nbsp;</small>
                        </div>
                        <div class="text-success fs-1">
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('families') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.totalFamilies') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($total_families ?? 0)) ?></h3>
                            <small class="text-muted">&nbsp;</small>
                        </div>
                        <div class="text-warning fs-1">
                            <i class="bi bi-house-heart" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('people') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.birthdaysThisWeek') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($birthdays_this_week ?? 0)) ?></h3>
                        </div>
                        <div class="text-primary fs-1">
                            <i class="bi bi-gift" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('visitors?followup_stage=pending') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.pendingVisitorFollowUps') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($pending_visitor_followups ?? 0)) ?></h3>
                        </div>
                        <div class="text-warning fs-1">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('followup-workflow/call-tasks?status=completed') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.doneFollowUpsThisMonthAdmins') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($done_followups_this_month_admins ?? 0)) ?></h3>
                        </div>
                        <div class="text-success fs-1">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="<?= base_url('events') ?>" class="dashboard-kpi-link text-decoration-none d-block h-100">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1"><?= lang('Dashboard.eventsThisMonth') ?></h6>
                            <h3 class="mb-0"><?= number_format((int) ($events_this_month ?? 0)) ?></h3>
                        </div>
                        <div class="text-info fs-1">
                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        <?= lang('Dashboard.recentServices') ?>
                    </h5>
                    <?php if (auth()->user()->can('service.view')): ?>
                        <a href="<?= base_url('services') ?>" class="btn btn-sm btn-outline-primary"><?= lang('App.viewAll') ?></a>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="min-height: 420px;">
                    <?php if (!empty($recent_services)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= lang('App.colDate') ?></th>
                                        <th scope="col"><?= lang('Dashboard.colService') ?></th>
                                        <th scope="col"><?= lang('Dashboard.colLiturgicalDay') ?></th>
                                        <th scope="col" class="text-end"><?= lang('Dashboard.colAttendComm') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_services as $service): ?>
                                        <tr>
                                            <td><?= esc($formatLocalizedDate((string) ($service['service_date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                            <td>
                                                <?php
                                                $rawServiceValue = $service['service'] ?? '';
                                                $services = json_decode((string) $rawServiceValue, true);

                                                if (!is_array($services)) {
                                                    $rawServiceValue = trim((string) $rawServiceValue);
                                                    $services = $rawServiceValue !== '' ? [$rawServiceValue] : [];
                                                }

                                                if (!empty($services)):
                                                    $localizedServices = array_map(static fn($item): string => service_type_label((string) $item), $services);
                                                    echo esc(implode(', ', $localizedServices));
                                                else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? '')) ?: '-') ?></td>
                                            <td class="text-end"><?= (int) ($service['total_attended'] ?? 0) ?> - <?= (int) ($service['total_communicants'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3"><?= lang('Dashboard.noRecentServices') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h5 class="mb-0"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> <?= lang('Dashboard.parishSundayAttendanceTitle') ?></h5>
                </div>
                <div class="card-body" style="min-height: 420px;">
                    <?php if (empty($sunday_attendance_has_data)): ?>
                        <div class="alert alert-light border small mb-3 py-2 px-3">
                            <?= lang('Dashboard.diocesanSundayAttendanceNoDataForPeriod') ?>
                        </div>
                    <?php endif; ?>
                    <div style="height: 260px; max-height: 260px;">
                        <canvas id="parishAttendanceChart"></canvas>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0 pb-3">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <button
                            type="button"
                            id="parishChartTypeToggle"
                            class="btn btn-sm btn-outline-secondary"
                            data-current-type="line"
                            aria-label="<?= esc(lang('Dashboard.chartType')) ?>">
                            <?= lang('Dashboard.chartTypeBar') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= view('Clergy\Views\Dashboard\_followup_stats') ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const parishLabels = <?= json_encode($sunday_attendance_labels ?? []) ?>;
    const parishAsaValues = <?= json_encode($sunday_attendance_averages ?? []) ?>;
    const parishAscValues = <?= json_encode($communion_attendance_averages ?? []) ?>;
    const parishCtx = document.getElementById('parishAttendanceChart');
    const parishChartTypeToggle = document.getElementById('parishChartTypeToggle');
    const parishPresentLegendLabel = <?= json_encode(lang('Dashboard.parishPresentCountLegend')) ?>;
    const parishCommunionLegendLabel = <?= json_encode(lang('Dashboard.parishCommunionCountLegend')) ?>;
    const chartTypeLineLabel = <?= json_encode(lang('Dashboard.chartTypeLine')) ?>;
    const chartTypeBarLabel = <?= json_encode(lang('Dashboard.chartTypeBar')) ?>;
    let parishChart = null;

    function syncParishChartTypeToggleLabel(currentType) {
        if (!parishChartTypeToggle) {
            return;
        }

        const nextType = currentType === 'bar' ? 'line' : 'bar';
        parishChartTypeToggle.setAttribute('data-current-type', currentType);
        parishChartTypeToggle.textContent = nextType === 'bar' ? chartTypeBarLabel : chartTypeLineLabel;
    }

    function applyParishChartStyle(chartType) {
        if (!parishChart || !parishChart.data || !Array.isArray(parishChart.data.datasets)) {
            return;
        }

        const isBar = chartType === 'bar';

        parishChart.data.datasets.forEach((dataset, index) => {
            const colorBorder = index === 0 ? '#0d6efd' : '#198754';
            const lineFill = index === 0 ? 'rgba(13, 110, 253, 0.10)' : 'rgba(25, 135, 84, 0.10)';
            const barFill = index === 0 ? 'rgba(13, 110, 253, 0.70)' : 'rgba(25, 135, 84, 0.70)';

            dataset.borderColor = colorBorder;
            dataset.backgroundColor = isBar ? barFill : lineFill;
            dataset.borderWidth = isBar ? 1 : 2;
            dataset.fill = !isBar;
            dataset.tension = isBar ? 0 : 0.25;
            dataset.pointRadius = isBar ? 0 : 3;
        });

        if (!parishChart.options.scales) {
            parishChart.options.scales = {};
        }
        if (!parishChart.options.scales.x) {
            parishChart.options.scales.x = {};
        }
        parishChart.options.scales.x.offset = isBar;
    }

    if (parishCtx) {
        parishChart = new Chart(parishCtx, {
            type: 'line',
            data: {
                labels: parishLabels,
                datasets: [{
                        label: parishPresentLegendLabel,
                        data: parishAsaValues,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.10)',
                        fill: false,
                        tension: 0.25,
                        pointRadius: 3,
                    },
                    {
                        label: parishCommunionLegendLabel,
                        data: parishAscValues,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.10)',
                        fill: false,
                        tension: 0.25,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                    },
                },
                plugins: {
                    legend: {
                        display: true,
                    },
                },
            },
        });

        applyParishChartStyle('line');
        parishChart.update();
        syncParishChartTypeToggleLabel('line');
    }

    if (parishChartTypeToggle) {
        parishChartTypeToggle.addEventListener('click', () => {
            if (!parishChart) {
                return;
            }

            const currentType = parishChartTypeToggle.getAttribute('data-current-type') || 'line';
            const nextType = currentType === 'bar' ? 'line' : 'bar';

            parishChart.config.type = nextType;
            applyParishChartStyle(nextType);
            parishChart.update();
            syncParishChartTypeToggleLabel(nextType);
        });
    }
</script>
<?= $this->endSection() ?>
