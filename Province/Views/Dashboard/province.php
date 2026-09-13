<?= $this->extend('Province\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#provinceDashboardFilterPanel"
            aria-expanded="<?= $current_period === 'custom' ? 'true' : 'false' ?>"
            aria-controls="provinceDashboardFilterPanel">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="provinceDashboardFilterPanel" class="collapse <?= $current_period === 'custom' ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="GET" action="<?= base_url('province/dashboard') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label d-flex align-items-center gap-2">
                        <span><?= lang('Dashboard.dioceseScope') ?></span>
                        <?php if (!empty($is_all_dioceses)): ?>
                            <span class="badge border border-primary text-primary fw-normal"><?= lang('Dashboard.allDiocesesActive') ?></span>
                        <?php endif; ?>
                    </label>
                    <select name="diocese_id" id="dioceseScopeSelect" class="form-select">
                        <option value="all" <?= $selected_diocese_id === null ? 'selected' : '' ?>><?= lang('Dashboard.allDioceses') ?></option>
                        <?php foreach ($dioceses as $diocese): ?>
                            <option value="<?= (int) $diocese['id'] ?>" <?= $selected_diocese_id === (int) $diocese['id'] ? 'selected' : '' ?>>
                                <?= esc($diocese['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('Dashboard.timePeriod') ?></label>
                    <select name="period" class="form-select" id="periodSelect">
                        <option value="today" <?= $current_period === 'today' ? 'selected' : '' ?>><?= lang('Dashboard.periodToday') ?></option>
                        <option value="week" <?= $current_period === 'week' ? 'selected' : '' ?>><?= lang('Dashboard.periodWeek') ?></option>
                        <option value="month" <?= $current_period === 'month' ? 'selected' : '' ?>><?= lang('Dashboard.periodMonth') ?></option>
                        <option value="year" <?= ($current_period === 'year' || empty($current_period)) ? 'selected' : '' ?>><?= lang('Dashboard.periodYear') ?></option>
                        <option value="custom" <?= $current_period === 'custom' ? 'selected' : '' ?>><?= lang('Dashboard.periodCustom') ?></option>
                    </select>
                </div>
                <div class="col-md-2" id="customDatesDiv" style="display: <?= $current_period === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label"><?= lang('Dashboard.startDate') ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($start_date) ?>">
                </div>
                <div class="col-md-2" id="customDatesDiv2" style="display: <?= $current_period === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label"><?= lang('Dashboard.endDate') ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc($end_date) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $attendanceDelta = (int) ($attendance_stats['total_attendance'] ?? 0) - (int) ($previous_attendance_stats['total_attendance'] ?? 0); ?>
<div class="alert alert-light border d-flex justify-content-between align-items-center mb-4" role="status">
    <span><?= lang('Dashboard.attendanceComparison', [esc((string) ($previous_start_date ?? '')), esc((string) ($previous_end_date ?? ''))]) ?></span>
    <strong class="<?= $attendanceDelta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $attendanceDelta >= 0 ? '+' : '' ?><?= number_format($attendanceDelta) ?></strong>
</div>

<?php if (!empty($show_diocese_scope_dashboard)): ?>
    <div class="row mb-4 g-3">
        <div class="col-md-6 col-xl">
            <div class="card stat-card success h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalPeople') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($scope_total_people ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($scope_total_guests ?? 0)) ?> <?= lang('Dashboard.guestsLabel') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card stat-card primary h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.colAttendance') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($scope_total_attendance ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.servicesLogged') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($scope_total_services ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.colMembers') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($scope_total_members ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($scope_good_standing_members ?? 0)) ?> <?= lang('Dashboard.goodStandingLabel') ?> | <?= number_format((int) ($scope_leadership_members ?? 0)) ?> <?= lang('Dashboard.leadershipLabel') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card stat-card danger h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.asaAwaLabel') ?></h6>
                    <h3 class="mb-0"><?= number_format((float) ($scope_asa ?? 0), 1) ?> / <?= number_format((float) ($scope_awa ?? 0), 1) ?></h3>
                    <small class="text-muted"><?= lang('Dashboard.avgSundayWeekdayAttendance') ?></small>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row mb-4">
        <div class="col-12 mb-2">
            <h6 class="text-muted mb-0"><?= lang('Dashboard.provinceTotalsAllDioceses') ?></h6>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalDioceses') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($province_totals['total_dioceses'] ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalChurches') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($province_totals['total_churches'] ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalPeople') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($province_totals['total_people'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($province_totals['total_guests'] ?? 0)) ?> <?= lang('Dashboard.guestsLabel') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card primary h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.colAttendance') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($province_totals['total_attendance'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($province_totals['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?> | <?= lang('Dashboard.avgLabel') ?> <?= number_format((float) ($province_totals['average_per_service'] ?? 0), 1) ?></small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-clock-history"></i> <?= lang('Dashboard.attendanceAveragesByPeriod') ?></h6>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodToday') ?> <span id="periodCardTodaySpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardTodayAvg"><?= number_format((float) ($attendance_today['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardTodayServices"><?= number_format((int) ($attendance_today['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodWeek') ?> <span id="periodCardWeekSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardWeekAvg"><?= number_format((float) ($attendance_week['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardWeekServices"><?= number_format((int) ($attendance_week['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodMonth') ?> <span id="periodCardMonthSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardMonthAvg"><?= number_format((float) ($attendance_month['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardMonthServices"><?= number_format((int) ($attendance_month['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodYear') ?> <span id="periodCardYearSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardYearAvg"><?= number_format((float) ($attendance_year['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardYearServices"><?= number_format((int) ($attendance_year['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.sundaysYtd') ?> <span id="periodCardSundaysSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardSundaysAvg"><?= number_format((float) ($attendance_sundays['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardSundaysServices"><?= number_format((int) ($attendance_sundays['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.weekdaysYtd') ?> <span id="periodCardWeekdaysSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span></h6>
                <h4 id="periodCardWeekdaysAvg"><?= number_format((float) ($attendance_weekdays['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted" id="periodCardWeekdaysServices"><?= number_format((int) ($attendance_weekdays['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($show_diocese_scope_dashboard)): ?>
    <div class="row mb-4 g-3">
        <div class="col-lg-12">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-event"></i> <?= lang('Dashboard.servicesLogged') ?></h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th><?= lang('App.colDate') ?></th>
                                    <th><?= lang('Dashboard.parishLabel') ?></th>
                                    <th><?= lang('Dashboard.serviceLabel') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_services)): ?>
                                    <?php foreach ($recent_services as $service): ?>
                                        <tr>
                                            <td><?= esc((string) ($service['service_date'] ?? '')) ?></td>
                                            <td><?= esc((string) ($service['church_name'] ?? '')) ?></td>
                                            <td>
                                                <?php
                                                $serviceLabel = service_type_label((string) ($service['service_type'] ?? ''));
                                                ?>
                                                <?= esc($serviceLabel !== '' ? $serviceLabel : lang('Dashboard.serviceLabel')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3"><?= lang('Dashboard.noServicesForDioceseScope') ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php $healthByDiocese = array_column($diocese_health_rows ?? [], null, 'diocese_id'); ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h6 class="mb-0"><i class="bi bi-list-columns"></i> <?= lang('Dashboard.perDioceseSummaryAll') ?></h6><small class="text-muted"><?= lang('Dashboard.provinceReportDefinition') ?></small></div>
            <?php if (auth()->user()->can('report.export')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('province/report-export?' . http_build_query(['diocese_id' => $selected_diocese_id ?? 'all', 'period' => $current_period, 'start_date' => $start_date, 'end_date' => $end_date])) ?>">
                    <i class="bi bi-download"></i> <?= lang('Dashboard.exportCsv') ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('Dashboard.colDiocese') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colChurches') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colPeople') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colGuests') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colMembers') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colActive') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colAttendance') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colServices') ?></th>
                            <th class="text-end"><?= lang('Dashboard.colAvgPerService') ?></th>
                            <th><?= lang('Dashboard.adminCoverage') ?></th>
                            <th><?= lang('Dashboard.lastServiceDate') ?></th>
                            <th><?= lang('Dashboard.healthStatus') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($diocese_summary_rows)): ?>
                            <?php foreach ($diocese_summary_rows as $row): ?>
                                <?php $health = $healthByDiocese[(int) $row['diocese_id']] ?? []; ?>
                                <tr>
                                    <td><?= esc($row['diocese_name']) ?></td>
                                    <td class="text-end"><?= number_format((int) $row['churches_count']) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['total_people'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['guest_count'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int) $row['total_members']) ?></td>
                                    <td class="text-end"><?= number_format((int) $row['active_members']) ?></td>
                                    <td class="text-end"><?= number_format((int) $row['total_attendance']) ?></td>
                                    <td class="text-end"><?= number_format((int) $row['total_services']) ?></td>
                                    <td class="text-end"><?= number_format((float) $row['average_per_service'], 1) ?></td>
                                    <td><?= !empty($health['has_active_admin']) ? '<span class="badge text-bg-success">' . esc(lang('Dashboard.adminCovered')) . '</span>' : '<span class="badge text-bg-danger">' . esc(lang('Dashboard.adminMissing')) . '</span>' ?></td>
                                    <td><?= esc((string) ($health['last_service_date'] ?? lang('Dashboard.noServiceReported'))) ?></td>
                                    <td><span class="badge text-bg-<?= ($health['health_status'] ?? 'attention') === 'healthy' ? 'success' : (($health['health_status'] ?? '') === 'overdue' ? 'warning' : 'danger') ?>"><?= esc(lang('Dashboard.health_' . ($health['health_status'] ?? 'attention'))) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4"><?= lang('Dashboard.noDiocesanSummaryData') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    const periodSelect = document.getElementById('periodSelect');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const customDiv = document.getElementById('customDatesDiv');
            const customDiv2 = document.getElementById('customDatesDiv2');
            const isCustom = this.value === 'custom';
            customDiv.style.display = isCustom ? 'block' : 'none';
            customDiv2.style.display = isCustom ? 'block' : 'none';
        });
    }

    const attendancePeriodBreakdownUrl = '<?= base_url('province/attendance-period-breakdown') ?>';
    const dioceseScopeSelect = document.getElementById('dioceseScopeSelect');

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    const periodCards = {
        today: {
            avg: document.getElementById('periodCardTodayAvg'),
            services: document.getElementById('periodCardTodayServices'),
            spinner: document.getElementById('periodCardTodaySpinner')
        },
        week: {
            avg: document.getElementById('periodCardWeekAvg'),
            services: document.getElementById('periodCardWeekServices'),
            spinner: document.getElementById('periodCardWeekSpinner')
        },
        month: {
            avg: document.getElementById('periodCardMonthAvg'),
            services: document.getElementById('periodCardMonthServices'),
            spinner: document.getElementById('periodCardMonthSpinner')
        },
        year: {
            avg: document.getElementById('periodCardYearAvg'),
            services: document.getElementById('periodCardYearServices'),
            spinner: document.getElementById('periodCardYearSpinner')
        },
        sundays: {
            avg: document.getElementById('periodCardSundaysAvg'),
            services: document.getElementById('periodCardSundaysServices'),
            spinner: document.getElementById('periodCardSundaysSpinner')
        },
        weekdays: {
            avg: document.getElementById('periodCardWeekdaysAvg'),
            services: document.getElementById('periodCardWeekdaysServices'),
            spinner: document.getElementById('periodCardWeekdaysSpinner')
        }
    };

    function setPeriodCardsLoading(isLoading) {
        Object.values(periodCards).forEach((card) => {
            if (!card || !card.spinner) {
                return;
            }
            if (isLoading) {
                card.spinner.classList.remove('d-none');
            } else {
                card.spinner.classList.add('d-none');
            }
        });
    }

    function setPeriodCard(periodKey, payload) {
        const card = periodCards[periodKey];
        if (!card || !payload) {
            return;
        }

        const avg = Number(payload.average_per_service || 0);
        const services = Number(payload.total_services || 0);

        card.avg.textContent = avg.toFixed(1);
        card.services.textContent = `${services.toLocaleString()} services`;
    }

    async function refreshAttendancePeriodCards(dioceseIdValue) {
        setPeriodCardsLoading(true);

        try {
            const params = new URLSearchParams({
                diocese_id: dioceseIdValue || 'all'
            });
            const response = await fetch(`${attendancePeriodBreakdownUrl}?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (!data.success || !data.periods) {
                return;
            }

            setPeriodCard('today', data.periods.today);
            setPeriodCard('week', data.periods.week);
            setPeriodCard('month', data.periods.month);
            setPeriodCard('year', data.periods.year);
            setPeriodCard('sundays', data.periods.sundays);
            setPeriodCard('weekdays', data.periods.weekdays);
        } catch (error) {
            console.error('Failed to refresh period cards', error);
        } finally {
            setPeriodCardsLoading(false);
        }
    }

    if (dioceseScopeSelect) {
        refreshAttendancePeriodCards(dioceseScopeSelect.value || 'all');
    }
</script>

<?= $this->endSection() ?>