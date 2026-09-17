<?= $this->extend('layouts/main') ?>

<?php
$year = (int) ($year ?? (int) date('Y'));
$defaultYear = (int) ($default_year ?? (int) date('Y'));
$compareTo = isset($compare_to) ? (int) $compare_to : 0;
$availableYears = $available_years ?? [];
$attendanceOverview = $attendance_overview ?? ['total_attendance' => 0, 'average_per_service' => 0];
$attendanceCompareOverview = $attendance_compare_overview ?? null;
$affiliationOverview = $affiliation_overview ?? ['total_affiliations' => 0];
$affiliationCompareOverview = $affiliation_compare_overview ?? null;
$activeMembersOverview = $active_members_overview ?? ['active_members' => 0];
$activeMembersCompareOverview = $active_members_compare_overview ?? null;
$attendanceMonthly = $attendance_monthly ?? array_fill(0, 12, 0);
$attendanceMonthlyCompare = $attendance_monthly_compare ?? array_fill(0, 12, 0);
$affiliationMonthly = $affiliation_monthly ?? array_fill(0, 12, 0);
$affiliationMonthlyCompare = $affiliation_monthly_compare ?? array_fill(0, 12, 0);
$provinceAttendanceComparison = $province_attendance_comparison ?? [];
$provinceActiveMembersComparison = $province_active_members_comparison ?? [];
$filtersActive = $year !== $defaultYear || $compareTo > 0;

$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$attendanceDelta = $compareTo > 0
    ? (int) ($attendanceOverview['total_attendance'] ?? 0) - (int) ($attendanceCompareOverview['total_attendance'] ?? 0)
    : null;
$averageDelta = $compareTo > 0
    ? (float) ($attendanceOverview['average_per_service'] ?? 0) - (float) ($attendanceCompareOverview['average_per_service'] ?? 0)
    : null;
$affiliationDelta = $compareTo > 0
    ? (int) ($affiliationOverview['total_affiliations'] ?? 0) - (int) ($affiliationCompareOverview['total_affiliations'] ?? 0)
    : null;
$activeMembersDelta = $compareTo > 0
    ? (int) ($activeMembersOverview['active_members'] ?? 0) - (int) ($activeMembersCompareOverview['active_members'] ?? 0)
    : null;

$renderDelta = static function ($delta, bool $float = false): string {
    if ($delta === null) {
        return '';
    }

    $prefix = $delta > 0 ? '+' : '';
    return $prefix . ($float ? number_format((float) $delta, 2) : number_format((int) $delta));
};
?>

<?= $this->section('content') ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <small class="text-secondary opacity-75 me-2"><?= lang('System.noComparisonSelected') ?></small>
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#systemMissionOutcomeFilterPanel"
            aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="systemMissionOutcomeFilterPanel" class="collapse <?= $filtersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('system/mission-outcomes') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm"><?= lang('System.filterYear') ?></label>
                    <select name="year" class="form-select form-select-sm">
                        <?php foreach ($availableYears as $availableYear): ?>
                            <option value="<?= (int) $availableYear ?>" <?= (int) $availableYear === $year ? 'selected' : '' ?>><?= esc((string) $availableYear) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm"><?= lang('System.compareToYear') ?></label>
                    <select name="compare_to" class="form-select form-select-sm">
                        <option value=""><?= lang('System.compareToNone') ?></option>
                        <?php foreach ($availableYears as $availableYear): ?>
                            <?php if ((int) $availableYear === $year) {
                                continue;
                            } ?>
                            <option value="<?= (int) $availableYear ?>" <?= (int) $availableYear === $compareTo ? 'selected' : '' ?>><?= esc((string) $availableYear) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('system/mission-outcomes') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.attendanceTotalYear') ?></small>
                <h4 class="mb-1"><?= number_format((int) ($attendanceOverview['total_attendance'] ?? 0)) ?></h4>
                <?php if ($compareTo > 0): ?>
                    <small class="text-muted"><?= lang('System.compareToYear') ?> <?= esc((string) $compareTo) ?>: <?= $renderDelta($attendanceDelta) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card info h-100">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.avgAttendanceYear') ?></small>
                <h4 class="mb-1"><?= number_format((float) ($attendanceOverview['average_per_service'] ?? 0), 2) ?></h4>
                <?php if ($compareTo > 0): ?>
                    <small class="text-muted"><?= lang('System.compareToYear') ?> <?= esc((string) $compareTo) ?>: <?= $renderDelta($averageDelta, true) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.newAffiliationsYear') ?></small>
                <h4 class="mb-1"><?= number_format((int) ($affiliationOverview['total_affiliations'] ?? 0)) ?></h4>
                <?php if ($compareTo > 0): ?>
                    <small class="text-muted"><?= lang('System.compareToYear') ?> <?= esc((string) $compareTo) ?>: <?= $renderDelta($affiliationDelta) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.activeMembersYearEnd') ?></small>
                <h4 class="mb-1"><?= number_format((int) ($activeMembersOverview['active_members'] ?? 0)) ?></h4>
                <?php if ($compareTo > 0): ?>
                    <small class="text-muted"><?= lang('System.compareToYear') ?> <?= esc((string) $compareTo) ?>: <?= $renderDelta($activeMembersDelta) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.networkAttendanceTrend') ?></strong></div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="networkAttendanceTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.provinceAttendanceComparison') ?></strong></div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="provinceAttendanceComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.affiliationTrend') ?></strong></div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="affiliationTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.provinceActiveMembersComparison') ?></strong></div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="provinceActiveMembersComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.provinceAttendanceComparison') ?></strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('System.provinceName') ?></th>
                                <th scope="col"><?= lang('System.currentValueLabel') ?></th>
                                <th scope="col"><?= lang('System.comparisonValueLabel') ?></th>
                                <th scope="col"><?= lang('System.deltaLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($provinceAttendanceComparison as $row): ?>
                                <tr>
                                    <td><?= esc((string) (($row['province_number'] ?? '') . ' - ' . ($row['province_name'] ?? ''))) ?></td>
                                    <td><?= number_format((int) ($row['current_value'] ?? 0)) ?></td>
                                    <td><?= number_format((int) ($row['compare_value'] ?? 0)) ?></td>
                                    <td><?= $renderDelta((int) ($row['delta_value'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><strong><?= lang('System.provinceActiveMembersComparison') ?></strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('System.provinceName') ?></th>
                                <th scope="col"><?= lang('System.currentValueLabel') ?></th>
                                <th scope="col"><?= lang('System.comparisonValueLabel') ?></th>
                                <th scope="col"><?= lang('System.deltaLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($provinceActiveMembersComparison as $row): ?>
                                <tr>
                                    <td><?= esc((string) (($row['province_number'] ?? '') . ' - ' . ($row['province_name'] ?? ''))) ?></td>
                                    <td><?= number_format((int) ($row['current_value'] ?? 0)) ?></td>
                                    <td><?= number_format((int) ($row['compare_value'] ?? 0)) ?></td>
                                    <td><?= $renderDelta((int) ($row['delta_value'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function() {
        const monthLabels = <?= json_encode($monthLabels) ?>;
        const yearLabel = <?= json_encode((string) $year) ?>;
        const compareLabel = <?= json_encode($compareTo > 0 ? (string) $compareTo : '') ?>;
        const attendanceMonthly = <?= json_encode(array_map('intval', $attendanceMonthly)) ?>;
        const attendanceMonthlyCompare = <?= json_encode(array_map('intval', $attendanceMonthlyCompare)) ?>;
        const affiliationMonthly = <?= json_encode(array_map('intval', $affiliationMonthly)) ?>;
        const affiliationMonthlyCompare = <?= json_encode(array_map('intval', $affiliationMonthlyCompare)) ?>;
        const provinceAttendanceRows = <?= json_encode($provinceAttendanceComparison) ?>;
        const provinceActiveRows = <?= json_encode($provinceActiveMembersComparison) ?>;

        function buildLineChart(canvasId, currentSeries, compareSeries, currentLabel, optionalCompareLabel, currentColor, compareColor) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const datasets = [{
                label: currentLabel,
                data: currentSeries,
                borderColor: currentColor,
                backgroundColor: currentColor.replace('1)', '0.15)'),
                fill: true,
                tension: 0.3,
            }];

            if (optionalCompareLabel) {
                datasets.push({
                    label: optionalCompareLabel,
                    data: compareSeries,
                    borderColor: compareColor,
                    backgroundColor: compareColor.replace('1)', '0.08)'),
                    fill: true,
                    tension: 0.3,
                });
            }

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        },
                    },
                },
            });
        }

        function buildBarChart(canvasId, rows, currentLabel, optionalCompareLabel, currentColor, compareColor) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const datasets = [{
                label: currentLabel,
                data: rows.map((row) => Number(row.current_value || 0)),
                backgroundColor: currentColor,
                borderColor: currentColor,
                borderWidth: 1,
            }];

            if (optionalCompareLabel) {
                datasets.push({
                    label: optionalCompareLabel,
                    data: rows.map((row) => Number(row.compare_value || 0)),
                    backgroundColor: compareColor,
                    borderColor: compareColor,
                    borderWidth: 1,
                });
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rows.map((row) => `${row.province_number} - ${row.province_name}`),
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        },
                    },
                },
            });
        }

        buildLineChart(
            'networkAttendanceTrendChart',
            attendanceMonthly,
            attendanceMonthlyCompare,
            yearLabel,
            compareLabel,
            'rgba(13, 110, 253, 1)',
            'rgba(108, 117, 125, 1)'
        );
        buildLineChart(
            'affiliationTrendChart',
            affiliationMonthly,
            affiliationMonthlyCompare,
            yearLabel,
            compareLabel,
            'rgba(25, 135, 84, 1)',
            'rgba(255, 193, 7, 1)'
        );
        buildBarChart(
            'provinceAttendanceComparisonChart',
            provinceAttendanceRows,
            yearLabel,
            compareLabel,
            'rgba(13, 110, 253, 0.75)',
            'rgba(108, 117, 125, 0.65)'
        );
        buildBarChart(
            'provinceActiveMembersComparisonChart',
            provinceActiveRows,
            yearLabel,
            compareLabel,
            'rgba(25, 135, 84, 0.75)',
            'rgba(255, 193, 7, 0.65)'
        );
    })();
</script>
<?= $this->endSection() ?>