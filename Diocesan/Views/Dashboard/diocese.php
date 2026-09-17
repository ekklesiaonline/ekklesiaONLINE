<?= $this->extend('Diocesan\Views\layouts/main') ?>

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
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get());
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
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, date_default_timezone_get());
        $formatter->setPattern('MMM d, y h:mm a');
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }
    return date('Y-m-d H:i', $timestamp);
};
?>

<!-- Filter panel -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm filter-toggle-btn" type="button"
                data-bs-toggle="collapse" data-bs-target="#dioceseDashboardFilterPanel"
                aria-expanded="<?= $current_period === 'custom' ? 'true' : 'false' ?>"
                aria-controls="dioceseDashboardFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="dioceseDashboardFilterPanel" class="collapse <?= $current_period === 'custom' ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="GET" action="<?= base_url('diocese/dashboard') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label d-flex align-items-center gap-2">
                        <span><?= lang('Dashboard.parishScope') ?></span>
                        <?php if (!empty($is_all_parishes)): ?>
                            <span class="badge border border-primary text-primary fw-normal"><?= lang('Dashboard.allParishesActive') ?></span>
                        <?php endif; ?>
                    </label>
                    <select name="parish_id" class="form-select">
                        <option value="all" <?= $selected_parish_id === null ? 'selected' : '' ?>><?= lang('Dashboard.allParishes') ?></option>
                        <?php foreach ($parishes as $parish): ?>
                            <option value="<?= (int) $parish['id'] ?>" <?= $selected_parish_id === (int) $parish['id'] ? 'selected' : '' ?>>
                                <?= esc($parish['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('Dashboard.timePeriod') ?></label>
                    <select name="period" class="form-select" id="diocesePeriodSelect">
                        <option value="today" <?= $current_period === 'today' ? 'selected' : '' ?>><?= lang('Dashboard.periodToday') ?></option>
                        <option value="week"  <?= $current_period === 'week'  ? 'selected' : '' ?>><?= lang('Dashboard.periodWeek') ?></option>
                        <option value="month" <?= $current_period === 'month' ? 'selected' : '' ?>><?= lang('Dashboard.periodMonth') ?></option>
                        <option value="year"  <?= ($current_period === 'year' || empty($current_period)) ? 'selected' : '' ?>><?= lang('Dashboard.periodYear') ?></option>
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
                    <button type="submit" class="btn btn-primary w-100" aria-label="<?= esc(lang('App.filter')) ?>"><i class="bi bi-funnel" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KPI cards (selected parish scope) -->
<?php if (!empty($show_parish_scope_dashboard)): ?>
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
                    <small class="text-muted"><?= number_format((int) ($scope_good_standing_members ?? 0)) ?> <?= lang('Dashboard.goodStandingLabel') ?></small>
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
    <!-- Diocese totals (all parishes) -->
    <div class="row mb-4">
        <div class="col-12 mb-2">
            <h6 class="text-muted mb-0"><?= lang('Dashboard.dioceseTotalsAllParishes') ?></h6>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalParishes') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($diocese_totals['total_parishes'] ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.totalPeople') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($total_people ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($total_guests ?? 0)) ?> <?= lang('Dashboard.guestsLabel') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.colMembers') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($diocese_totals['total_members'] ?? 0)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card primary h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><?= lang('Dashboard.colAttendance') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($diocese_totals['total_attendance'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= number_format((int) ($diocese_totals['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?> | <?= lang('Dashboard.avgLabel') ?> <?= number_format((float) ($diocese_totals['average_per_service'] ?? 0), 1) ?></small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ((int) ($pending_admin_reviews_count ?? 0) > 0): ?>
<!-- Pending registration approvals (diocese oversight only — parish admins moderate) -->
<div class="card border-warning mb-4">
    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#diocesePendingRegistrations" aria-expanded="true" aria-controls="diocesePendingRegistrations" role="button">
        <h6 class="mb-0"><i class="bi bi-person-exclamation" aria-hidden="true"></i> <?= lang('Dashboard.diocesePendingRegistrationsTitle') ?></h6>
        <span class="badge border border-warning text-warning fw-normal"><?= (int) $pending_admin_reviews_count ?></span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2"><?= lang('Dashboard.diocesePendingRegistrationsDesc') ?></p>
        <div class="collapse show" id="diocesePendingRegistrations">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('App.colName') ?></th>
                            <th scope="col"><?= lang('App.colEmail') ?></th>
                            <th scope="col"><?= lang('Dashboard.parishLabel') ?></th>
                            <th scope="col"><?= lang('Dashboard.submittedOn') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($pending_admin_reviews ?? []) as $req): ?>
                            <tr>
                                <td><?= esc(trim((string) ($req['first_name'] ?? '') . ' ' . (string) ($req['last_name'] ?? ''))) ?></td>
                                <td><?= esc((string) ($req['email'] ?? '-')) ?></td>
                                <td><?= esc((string) ($req['requested_parish_name'] ?? '-')) ?></td>
                                <td><?= esc($formatLocalizedDate((string) ($req['profile_completed_at'] ?? ''), 'MMM d, y', $locale)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Attendance averages by period -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= lang('Dashboard.attendanceAveragesByPeriod') ?></h6>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodToday') ?></h6>
                <h4><?= number_format((float) ($attendance_today['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_today['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodWeek') ?></h6>
                <h4><?= number_format((float) ($attendance_week['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_week['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodMonth') ?></h6>
                <h4><?= number_format((float) ($attendance_month['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_month['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.periodYear') ?></h6>
                <h4><?= number_format((float) ($attendance_year['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_year['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.sundaysYtd') ?></h6>
                <h4><?= number_format((float) ($attendance_sundays['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_sundays['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Dashboard.weekdaysYtd') ?></h6>
                <h4><?= number_format((float) ($attendance_weekdays['average_per_service'] ?? 0), 1) ?></h4>
                <small class="text-muted"><?= number_format((int) ($attendance_weekdays['total_services'] ?? 0)) ?> <?= lang('Dashboard.servicesLabel') ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Per-parish summary table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table" aria-hidden="true"></i> <?= lang('Dashboard.parishSummaryTable') ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Dashboard.parishLabel') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.colMembers') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.activeLabel') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.colAttendance') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.servicesLogged') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.asaLabel') ?></th>
                        <th scope="col" class="text-end"><?= lang('Dashboard.awaLabel') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($parish_summary_rows)): ?>
                        <?php foreach ($parish_summary_rows as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['parish_name'] ?? '')) ?></td>
                                <td class="text-end"><?= number_format((int) ($row['total_members'] ?? 0)) ?></td>
                                <td class="text-end"><?= number_format((int) ($row['active_members'] ?? 0)) ?></td>
                                <td class="text-end"><?= number_format((int) ($row['total_attendance'] ?? 0)) ?></td>
                                <td class="text-end"><?= number_format((int) ($row['total_services'] ?? 0)) ?></td>
                                <td class="text-end"><?= number_format((float) ($row['asa'] ?? 0), 1) ?></td>
                                <td class="text-end"><?= number_format((float) ($row['awa'] ?? 0), 1) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Totals row -->
                        <tr class="table-active fw-semibold">
                            <td><?= lang('Dashboard.totalsRow') ?></td>
                            <td class="text-end"><?= number_format((int) ($diocese_totals['total_members'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((int) ($diocese_totals['active_members'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((int) ($diocese_totals['total_attendance'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((int) ($diocese_totals['total_services'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((float) ($diocese_totals['asa'] ?? 0), 1) ?></td>
                            <td class="text-end"><?= number_format((float) ($diocese_totals['awa'] ?? 0), 1) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4"><?= lang('Dashboard.noParishData') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sunday attendance trend chart -->
<?php if (!empty($sunday_attendance_has_data)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> <?= lang('Dashboard.sundayAttendanceTrend') ?></h6>
        </div>
        <div class="card-body">
            <canvas id="dioceseSundayChart" style="max-height: 320px;"></canvas>
        </div>
    </div>
<?php endif; ?>

<!-- Recent activity + audit log -->
<div class="row mt-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-activity" aria-hidden="true"></i> <?= lang('Dashboard.systemOverviewNetworkActivity') ?></h5>
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('diocese/dashboard/recent-activity') ?>"><?= lang('App.viewAll') ?></a>
            </div>
            <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                <?php if (!empty($recent_activity)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activity as $activity): ?>
                            <?php $liturgicalText = (string) ($activity['display_liturgical'] ?? '-'); ?>
                            <div class="list-group-item">
                                <div class="small text-muted">
                                    <?= esc($formatLocalizedDate((string) ($activity['happened_at'] ?? ''), 'MMM d, y', $locale)) ?>
                                    – <?= esc((string) ($activity['parish_name'] ?? lang('Dashboard.systemOverviewUnknownParish'))) ?>
                                </div>
                                <div class="small mt-1">
                                    <span><?= esc((string) ($activity['display_subject'] ?? lang('Dashboard.systemOverviewActivityFallback'))) ?></span>
                                    – <span class="<?= esc((string) ($activity['display_flow_class'] ?? 'text-secondary')) ?>"><?= esc((string) ($activity['display_flow'] ?? '')) ?></span>
                                    – <span class="<?= esc((string) ($activity['display_amount_class'] ?? 'text-secondary')) ?>"><?= esc((string) ($activity['display_amount'] ?? '-')) ?></span>
                                    <?php if ($liturgicalText !== '' && $liturgicalText !== '-'): ?>
                                        / <span class="badge fw-normal <?= esc((string) ($activity['display_liturgical_class'] ?? 'bg-light text-secondary border')) ?>"><?= esc($liturgicalText) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center text-muted" style="min-height: 420px;">
                        <?= lang('Dashboard.systemOverviewNoRecentActivity') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-0"><i class="bi bi-journal-text" aria-hidden="true"></i> <?= lang('Dashboard.systemOverviewAuditLog') ?></h5>
                    <small class="text-muted"><?= lang('Dashboard.systemOverviewModerationEventsLast7Days', [number_format((int) ($recent_audit_count_7d ?? 0))]) ?></small>
                </div>
                <div class="btn-group btn-group-sm audit-filter-controls" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-audit-filter="all"><?= lang('Dashboard.systemOverviewFilterAll') ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-audit-filter="moderation"><?= lang('Dashboard.systemOverviewFilterModeration') ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-audit-filter="other"><?= lang('Dashboard.systemOverviewFilterOther') ?></button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush audit-log-list" style="max-height: 420px; overflow-y: auto;">
                    <?php if (!empty($recent_audit_logs)): ?>
                        <?php foreach ($recent_audit_logs as $log): ?>
                            <?php $isModerationLog = str_starts_with((string) ($log['action'] ?? ''), 'registration_'); ?>
                            <div class="list-group-item audit-log-item" data-audit-type="<?= $isModerationLog ? 'moderation' : 'other' ?>">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold"><?= esc(ucfirst(str_replace('_', ' ', $log['action'] ?? lang('Dashboard.systemOverviewActionFallback')))) ?></span>
                                    <small class="text-muted"><?= esc($formatLocalizedDateTime((string) ($log['created_at'] ?? ''), $locale)) ?></small>
                                </div>
                                <div class="small text-muted">
                                    <?= esc((string) ($log['parish_name'] ?? $log['parish_name'] ?? lang('Dashboard.systemOverviewUnknownParish'))) ?>
                                    | <?= esc(trim((string) ($log['actor_name'] ?? '')) ?: lang('App.auditUserFallback', [(string) ($log['actor_user_id'] ?? lang('App.notApplicable'))])) ?>
                                    <?= esc(lang('Dashboard.systemOverviewActorTargetSeparator')) ?>
                                    <?= esc(trim((string) ($log['target_name'] ?? '')) ?: lang('App.auditUserFallback', [(string) ($log['target_user_id'] ?? lang('App.notApplicable'))])) ?>
                                    <?php if (!empty($log['role'])): ?>
                                        | <?= lang('Dashboard.systemOverviewRolePrefix') ?>: <span class="fw-semibold"><?= esc((string) $log['role']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($log['details'])): ?>
                                    <div class="small"><?= esc((string) $log['details']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-muted audit-log-empty"><?= lang('Dashboard.systemOverviewNoAuditEntries') ?></div>
                    <?php endif; ?>
                    <div class="list-group-item text-muted audit-log-empty d-none"><?= lang('Dashboard.systemOverviewNoAuditEntriesForFilter') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($sunday_attendance_has_data)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('dioceseSundayChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($sunday_attendance_labels ?? []) ?>,
            datasets: [
                {
                    label: '<?= lang('Dashboard.avgSundayAttendanceLabel') ?>',
                    data: <?= json_encode($sunday_attendance_averages ?? []) ?>,
                    borderColor: 'rgba(13, 110, 253, 0.8)',
                    backgroundColor: 'rgba(13, 110, 253, 0.08)',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: '<?= lang('Dashboard.avgCommunionLabel') ?>',
                    data: <?= json_encode($communion_attendance_averages ?? []) ?>,
                    borderColor: 'rgba(25, 135, 84, 0.8)',
                    backgroundColor: 'rgba(25, 135, 84, 0.08)',
                    tension: 0.3,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } },
        },
    });
});
</script>
<?php endif; ?>

<script>
// Audit log client-side filter
document.querySelectorAll('[data-audit-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-audit-filter]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var filter = btn.getAttribute('data-audit-filter');
        document.querySelectorAll('.audit-log-item').forEach(function (item) {
            item.style.display = (filter === 'all' || item.getAttribute('data-audit-type') === filter) ? '' : 'none';
        });
        var emptyMsgs = document.querySelectorAll('.audit-log-empty');
        var visibleCount = document.querySelectorAll('.audit-log-item:not([style*="none"])').length;
        emptyMsgs.forEach(function (msg) { msg.classList.toggle('d-none', visibleCount > 0); });
    });
});

// Custom date range show/hide
var periodSelect = document.getElementById('diocesePeriodSelect');
if (periodSelect) {
    periodSelect.addEventListener('change', function () {
        var show = this.value === 'custom';
        ['customDatesDiv', 'customDatesDiv2'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.style.display = show ? 'block' : 'none';
        });
    });
}
</script>

<?= $this->endSection() ?>
