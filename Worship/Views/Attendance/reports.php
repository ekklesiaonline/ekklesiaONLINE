<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .attendance-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .attendance-kpi-tile {
        border: 1px solid var(--app-border);
        border-radius: 0.75rem;
        background: var(--app-surface);
        padding: 1rem;
        min-height: 120px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .attendance-kpi-value {
        font-size: 1.9rem;
        line-height: 1;
        font-weight: 700;
        margin: 0.5rem 0;
    }

    .attendance-kpi-label {
        font-size: 0.9rem;
        color: var(--bs-secondary-color);
        margin: 0;
    }

    .attendance-kpi-accent {
        width: 44px;
        height: 4px;
        border-radius: 999px;
        background: #0d6efd;
    }

    .attendance-kpi-tile.special .attendance-kpi-accent {
        background: #dc3545;
    }

    .attendance-kpi-tile.awa .attendance-kpi-accent {
        background: #198754;
    }

    .attendance-kpi-tile.asa .attendance-kpi-accent {
        background: #6f42c1;
    }

    .attendance-kpi-tile.comm .attendance-kpi-accent {
        background: #fd7e14;
    }

    .attendance-kpi-tile.snapshot .attendance-kpi-accent {
        background: #0dcaf0;
    }

    .attendance-snapshot-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .attendance-snapshot-item {
        border: 1px solid var(--app-border);
        border-radius: 0.5rem;
        padding: 0.5rem 0.625rem;
        background: var(--app-surface-muted);
    }

    .attendance-snapshot-item .value {
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.1;
        margin: 0;
    }

    .attendance-snapshot-item .label {
        font-size: 0.75rem;
        color: var(--bs-secondary-color);
        margin: 0.125rem 0 0;
    }

    .attendance-kpi-tile.full {
        grid-column: 1 / -1;
    }

    @media (max-width: 767px) {
        .attendance-kpi-grid {
            grid-template-columns: 1fr;
        }

        .attendance-kpi-tile.full {
            grid-column: auto;
        }

        .attendance-snapshot-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#attendanceFilterPanel"
            aria-expanded="<?= !empty($has_custom_filter) ? 'true' : 'false' ?>"
            aria-controls="attendanceFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Attendance.filter') ?>
        </button>
    </div>
    <div id="attendanceFilterPanel" class="collapse <?= !empty($has_custom_filter) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('attendance/reports') ?>">
                <div class="row">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label"><?= lang('Attendance.startDate') ?></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label"><?= lang('Attendance.endDate') ?></label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search" aria-hidden="true"></i> <?= lang('Attendance.apply') ?>
                        </button>
                        <a href="<?= base_url('attendance/reports') ?>" class="btn btn-light border"><?= lang('App.reset') ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><?= lang('Attendance.reportsThisYear') ?></h5>
                <span class="text-muted small"><?= esc(liturgy_format_localized((string) $end_date, 'y', 'Y')) ?></span>
            </div>
            <div class="card-body">
                <div class="attendance-kpi-grid">
                    <div class="attendance-kpi-tile">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (int) ($attendance_kpis['total_in_person_regular'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.totalInPersonRegular') ?></p>
                    </div>

                    <div class="attendance-kpi-tile special">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (int) ($attendance_kpis['total_in_person_special'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.totalInPersonSpecial') ?></p>
                    </div>

                    <div class="attendance-kpi-tile awa">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (float) ($attendance_kpis['average_weekday_attendance'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.avgWeekdayAttendance') ?></p>
                    </div>

                    <div class="attendance-kpi-tile asa">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (float) ($attendance_kpis['average_sunday_attendance'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.avgSundayAttendance') ?></p>
                    </div>

                    <div class="attendance-kpi-tile comm full">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (int) ($attendance_kpis['total_communicants'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.totalCommunicants') ?></p>
                    </div>

                    <div class="attendance-kpi-tile snapshot full">
                        <div class="attendance-kpi-accent"></div>
                        <h3 class="attendance-kpi-value"><?= (int) ($attendance_kpis['snapshot_services_logged'] ?? 0) ?></h3>
                        <p class="attendance-kpi-label"><?= lang('Attendance.snapshotTitle') ?></p>
                        <div class="attendance-snapshot-grid">
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (int) ($attendance_kpis['snapshot_regular_services'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotRegular') ?></p>
                            </div>
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (int) ($attendance_kpis['snapshot_special_services'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotSpecial') ?></p>
                            </div>
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (int) ($attendance_kpis['snapshot_in_person_services'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotInPerson') ?></p>
                            </div>
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (int) ($attendance_kpis['snapshot_sunday_services'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotSunday') ?></p>
                            </div>
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (int) ($attendance_kpis['snapshot_weekday_services'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotWeekday') ?></p>
                            </div>
                            <div class="attendance-snapshot-item">
                                <p class="value"><?= (float) ($attendance_kpis['snapshot_avg_per_service'] ?? 0) ?></p>
                                <p class="label"><?= lang('Attendance.snapshotAvgPerService') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>