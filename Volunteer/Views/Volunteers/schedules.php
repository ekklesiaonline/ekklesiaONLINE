<?= $this->extend('Volunteer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$filterTeamId   = (int) ($filter_team_id ?? 0);
$filterStatus   = (string) ($filter_status ?? '');
$filterDateFrom = (string) ($filter_date_from ?? '');
$filterDateTo   = (string) ($filter_date_to ?? '');
$selectedPerPage = (int) ($per_page ?? 25);
$calView        = (string) ($cal_view ?? 'week');
$weekStart      = (string) ($week_start ?? date('Y-m-d'));
$weekEnd        = (string) ($week_end ?? date('Y-m-d'));
$prevWeekStart  = (string) ($prev_week_start ?? date('Y-m-d', strtotime($weekStart . ' -7 days')));
$nextWeekStart  = (string) ($next_week_start ?? date('Y-m-d', strtotime($weekStart . ' +7 days')));
$todayWeekStart = (string) ($today_week_start ?? date('Y-m-d'));
$monthStart     = (string) ($month_start ?? date('Y-m-01'));
$monthEnd       = (string) ($month_end ?? date('Y-m-t'));
$monthGridStart = (string) ($month_grid_start ?? $monthStart);
$monthGridEnd   = (string) ($month_grid_end ?? $monthEnd);
$prevMonthStart = (string) ($prev_month_start ?? date('Y-m-d', strtotime($monthStart . ' -1 month')));
$nextMonthStart = (string) ($next_month_start ?? date('Y-m-d', strtotime($monthStart . ' +1 month')));
$todayMonthStart = (string) ($today_month_start ?? date('Y-m-01'));
$calSchedules   = (array) ($cal_schedules ?? []);

$schedulesFiltersActive = $filterTeamId > 0
    || $filterStatus !== ''
    || $filterDateFrom !== ''
    || $filterDateTo !== '';

// Base params for all nav links (view + filters, no pagination)
$calBaseParams  = array_filter([
    'view'    => $calView,
    'team_id' => $filterTeamId > 0 ? $filterTeamId : null,
    'status'  => $filterStatus !== '' ? $filterStatus : null,
], fn($v) => $v !== null);

// Query params to preserve when using the shared calendar partial
$calendarFilterParams = array_filter([
    'team_id' => $filterTeamId > 0 ? $filterTeamId : null,
    'status'  => $filterStatus !== '' ? $filterStatus : null,
], fn($v) => $v !== null);

// Base params for list pagination links
$baseFilterParams = array_filter([
    'view'      => 'list',
    'team_id'   => $filterTeamId > 0 ? $filterTeamId : null,
    'status'    => $filterStatus !== '' ? $filterStatus : null,
    'date_from' => $filterDateFrom !== '' ? $filterDateFrom : null,
    'date_to'   => $filterDateTo !== '' ? $filterDateTo : null,
], fn($v) => $v !== null);
$baseFilterParams['per_page'] = $selectedPerPage;

// Build day buckets for the shared calendar partial
$dayBuckets = [];
if (in_array($calView, ['week', 'month'], true)) {
    $rangeStart = $calView === 'week' ? $weekStart : $monthGridStart;
    $rangeEnd   = $calView === 'week' ? $weekEnd : $monthGridEnd;
    $current    = new DateTimeImmutable($rangeStart);
    $end        = new DateTimeImmutable($rangeEnd);
    while ($current <= $end) {
        $date = $current->format('Y-m-d');
        $items = [];
        foreach ($calSchedules[$date] ?? [] as $entry) {
            $entryStatus = (string) ($entry['status'] ?? 'scheduled');
            $items[] = [
                'id' => (int) ($entry['id'] ?? 0),
                'title' => trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')),
                'status' => $entryStatus,
                'scope_level' => 'volunteer',
                'calendar_name' => (string) ($entry['team_name'] ?? ''),
                'calendar_color' => match ($entryStatus) {
                    'confirmed' => '#198754',
                    'cancelled' => '#dc3545',
                    default => '#6c757d',
                },
                'view_url' => base_url('volunteers/schedules/edit/' . (int) ($entry['id'] ?? 0)),
                'time_label' => (string) ($entry['service_time_slot'] ?? ''),
            ];
        }
        $dayBuckets[] = ['date' => $date, 'items' => $items];
        $current = $current->modify('+1 day');
    }
}
?>

<?php /* ── Filter panel ─────────────────────────────────────────────────── */ ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#schedulesFilterPanel"
            aria-expanded="<?= $schedulesFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="schedulesFilterPanel" class="collapse <?= $schedulesFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('volunteers/schedules') ?>" class="row g-2 align-items-end">
                <input type="hidden" name="view" value="<?= esc($calView) ?>">
                <?php if ($calView === 'week'): ?>
                    <input type="hidden" name="week_start" value="<?= esc($weekStart) ?>">
                <?php elseif ($calView === 'month'): ?>
                    <input type="hidden" name="month_start" value="<?= esc($monthStart) ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label" for="filterTeam"><?= lang('Volunteer.team') ?></label>
                    <select id="filterTeam" name="team_id" class="form-select form-select-sm">
                        <option value=""><?= lang('Volunteer.filterAllTeams') ?></option>
                        <?php foreach ($filter_teams as $team): ?>
                            <option value="<?= (int) $team['id'] ?>" <?= $filterTeamId === (int) $team['id'] ? 'selected' : '' ?>>
                                <?= esc($team['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterStatus"><?= lang('Volunteer.status') ?></label>
                    <select id="filterStatus" name="status" class="form-select form-select-sm">
                        <option value=""><?= lang('Volunteer.filterAllStatuses') ?></option>
                        <option value="scheduled" <?= $filterStatus === 'scheduled' ? 'selected' : '' ?>><?= lang('Volunteer.statusScheduled') ?></option>
                        <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>><?= lang('Volunteer.statusConfirmed') ?></option>
                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>><?= lang('Volunteer.statusCancelled') ?></option>
                    </select>
                </div>
                <?php if ($calView === 'list'): ?>
                    <div class="col-md-2">
                        <label class="form-label" for="filterDateFrom"><?= lang('Volunteer.filterDateFrom') ?></label>
                        <input type="date" id="filterDateFrom" name="date_from" class="form-control form-control-sm" value="<?= esc($filterDateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterDateTo"><?= lang('Volunteer.filterDateTo') ?></label>
                        <input type="date" id="filterDateTo" name="date_to" class="form-control form-control-sm" value="<?= esc($filterDateTo) ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label" for="schedulesPerPage"><?= lang('App.perPage') ?></label>
                        <select id="schedulesPerPage" name="per_page" class="form-select form-select-sm">
                            <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                    <?php else: ?>
                        <div class="col-md-7 d-flex gap-2">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary flex-fill">
                            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                        </button>
                        <a href="<?= base_url('volunteers/schedules?view=' . esc($calView)
                                        . ($calView === 'week' ? '&week_start=' . esc($weekStart) : '')
                                        . ($calView === 'month' ? '&month_start=' . esc($monthStart) : '')) ?>"
                            class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                        </div>
            </form>
        </div>
    </div>
</div>

<?php if (in_array($calView, ['week', 'month'], true)): ?>

    <?= view('partials/events_calendar', [
        'calendar_mode' => $calView,
        'anchor_date' => $calView === 'week' ? $weekStart : $monthStart,
        'period_start' => $calView === 'week' ? $weekStart : $monthStart,
        'period_end' => $calView === 'week' ? $weekEnd : $monthEnd,
        'day_buckets' => $dayBuckets,
        'calendar_base_url' => 'volunteers/schedules',
        'calendar_base_query' => $calendarFilterParams,
        'list_url' => 'volunteers/schedules?' . http_build_query($baseFilterParams),
        'create_url' => 'volunteers/schedules/create',
        'create_button_label' => lang('Volunteer.addSchedule'),
        'can_create_event' => auth()->user()->can('volunteer.schedule'),
        'can_manage_calendars' => false,
        'show_agenda' => false,
        'weekday_header_order' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    ]) ?>

<?php else: /* list view */ ?>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($schedules)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Volunteer.date') ?></th>
                                <th scope="col"><?= lang('Volunteer.team') ?></th>
                                <th scope="col"><?= lang('Volunteer.volunteer') ?></th>
                                <th scope="col"><?= lang('Volunteer.timeSlot') ?></th>
                                <th scope="col"><?= lang('Volunteer.status') ?></th>
                                <th scope="col"><?= lang('App.colActions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <?php
                                $rowStatus   = (string) ($schedule['status'] ?? 'scheduled');
                                $statusColor = $rowStatus === 'confirmed' ? 'success' : ($rowStatus === 'cancelled' ? 'danger' : 'secondary');
                                ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($schedule['service_date'])) ?></td>
                                    <td><?= esc($schedule['team_name'] ?? '-') ?></td>
                                    <td><?= esc(trim(($schedule['first_name'] ?? '') . ' ' . ($schedule['last_name'] ?? ''))) ?></td>
                                    <td><?= esc($schedule['service_time_slot'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang('Volunteer.status' . ucfirst($rowStatus)) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($rowStatus === 'cancelled'): ?>
                                            <a href="<?= base_url('volunteers/schedules/edit/' . (int) $schedule['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= lang('App.edit') ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="<?= esc(lang('App.colActions')) ?>">
                                                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <a class="dropdown-item" href="<?= base_url('volunteers/schedules/edit/' . (int) $schedule['id']) ?>">
                                                            <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                        </a>
                                                    </li>
                                                    <?php if ($rowStatus === 'scheduled'): ?>
                                                        <li>
                                                            <form action="<?= base_url('volunteers/schedules/confirm/' . (int) $schedule['id']) ?>" method="post"
                                                                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                                data-confirm-body="<?= esc(lang('Volunteer.confirmScheduleConfirm'), 'attr') ?>">
                                                                <?= csrf_field() ?>
                                                                <button type="submit" class="dropdown-item text-success">
                                                                    <i class="bi bi-check-circle me-2" aria-hidden="true"></i><?= lang('Volunteer.confirmAction') ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form action="<?= base_url('volunteers/schedules/cancel/' . (int) $schedule['id']) ?>" method="post"
                                                            data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                            data-confirm-body="<?= esc(lang('Volunteer.cancelScheduleConfirm'), 'attr') ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-x-circle me-2" aria-hidden="true"></i><?= lang('App.cancel') ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0"><?= lang('Volunteer.noSchedulesFound') ?></p>
            <?php endif; ?>

            <?php
            $currentPage = max(1, (int) ($current_page ?? 1));
            $totalPages  = max(1, (int) ($total_pages ?? 1));
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="<?= esc(lang('App.pagination')) ?>">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $prevDisabled = $currentPage <= 1;
                        $nextDisabled = $currentPage >= $totalPages;
                        $startPage    = max(1, $currentPage - 2);
                        $endPage      = min($totalPages, $currentPage + 2);
                        ?>
                        <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $prevDisabled ? '#' : base_url('volunteers/schedules?' . http_build_query(array_merge($baseFilterParams, ['page' => $currentPage - 1]))) ?>">&laquo;</a>
                        </li>
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('volunteers/schedules?' . http_build_query(array_merge($baseFilterParams, ['page' => $p]))) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $nextDisabled ? '#' : base_url('volunteers/schedules?' . http_build_query(array_merge($baseFilterParams, ['page' => $currentPage + 1]))) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

<?php endif; /* end cal_view */ ?>

<?= $this->endSection() ?>
