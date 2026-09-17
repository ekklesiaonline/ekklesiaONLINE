<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') { return '-'; }
    $timestamp = strtotime($value);
    if ($timestamp === false) { return (string) $value; }
    return date('M d, Y', $timestamp);
};
?>

<!-- KPI cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="<?= base_url('followup?status=pending') ?>" class="text-decoration-none d-block h-100">
            <div class="card stat-card warning h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Pending Follow-ups</h6>
                    <h3 class="mb-0"><?= number_format((int) ($pending_followups ?? 0)) ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Call Tasks Today</h6>
                <h3 class="mb-0"><?= number_format((int) ($call_tasks_today ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="<?= base_url('guests') ?>" class="text-decoration-none d-block h-100">
            <div class="card stat-card info h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Recent Guests</h6>
                    <h3 class="mb-0"><?= count($recent_guests ?? []) ?></h3>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Recent services -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= lang('Dashboard.recentServices') ?></h5>
                <a href="<?= base_url('services') ?>" class="btn btn-sm btn-outline-primary"><?= lang('App.viewAll') ?></a>
            </div>
            <div class="card-body" style="min-height: 350px;">
                <?php if (!empty($recent_services)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th scope="col"><?= lang('App.colDate') ?></th>
                                    <th scope="col"><?= lang('Dashboard.colService') ?></th>
                                    <th scope="col" class="text-end"><?= lang('Dashboard.colAttendComm') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_services as $service): ?>
                                    <tr>
                                        <td><?= esc($formatLocalizedDate($service['service_date'] ?? '')) ?></td>
                                        <td><?= esc($service['service'] ?? '-') ?></td>
                                        <td class="text-end"><?= (int) ($service['total_attended'] ?? 0) ?> / <?= (int) ($service['total_communicants'] ?? 0) ?></td>
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

    <!-- Sunday attendance chart -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> <?= lang('Dashboard.parishSundayAttendanceTitle') ?></h5>
            </div>
            <div class="card-body" style="min-height: 350px;">
                <?php if (empty($sunday_attendance_has_data)): ?>
                    <div class="alert alert-light border small"><?= lang('Dashboard.diocesanSundayAttendanceNoDataForPeriod') ?></div>
                <?php endif; ?>
                <div style="height: 280px;">
                    <canvas id="worshipAttendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const worshipLabels = <?= json_encode($sunday_attendance_labels ?? []) ?>;
    const worshipAsa = <?= json_encode($sunday_attendance_averages ?? []) ?>;
    const worshipAsc = <?= json_encode($communion_attendance_averages ?? []) ?>;
    const worshipCtx = document.getElementById('worshipAttendanceChart');
    if (worshipCtx && worshipLabels.length > 0) {
        new Chart(worshipCtx, {
            type: 'line',
            data: {
                labels: worshipLabels,
                datasets: [
                    { label: 'Present', data: worshipAsa, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.25, pointRadius: 3 },
                    { label: 'Communion', data: worshipAsc, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.25, pointRadius: 3 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }
</script>
<?= $this->endSection() ?>
