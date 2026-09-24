<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="mb-4 d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="#" onclick="window.print(); return false;">
        <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('Treasurer.reportPrint') ?>
    </a>
    <a
        class="btn btn-outline-primary"
        href="<?= base_url('reports/export?start_date=' . urlencode((string) ($report_start_date ?? date('Y-01-01'))) . '&end_date=' . urlencode((string) ($report_end_date ?? date('Y-m-d')))) ?>">
        <i class="bi bi-file-csv" aria-hidden="true"></i> CSV
    </a>
    <a
        class="btn btn-primary"
        href="<?= base_url('reports/export-pdf?start_date=' . urlencode((string) ($report_start_date ?? date('Y-01-01'))) . '&end_date=' . urlencode((string) ($report_end_date ?? date('Y-m-d')))) ?>">
        <i class="bi bi-file-pdf" aria-hidden="true"></i> PDF
    </a>
</div>

<?php $request = service('request'); ?>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#treasurerReportsFilterPanel"
            aria-expanded="<?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'true' : 'false' ?>"
            aria-controls="treasurerReportsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="treasurerReportsFilterPanel" class="collapse <?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('reports') ?>" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Treasurer.reportStartDate') ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc((string) ($report_start_date ?? '')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Treasurer.reportEndDate') ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc((string) ($report_end_date ?? '')) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Treasurer.reportApply') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportParishes') ?></div>
                <div class="h3 mb-0"><?= (int) ($report_summary['parish_count'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportPeople') ?></div>
                <div class="h3 mb-0"><?= number_format((int) ($report_summary['people_count'] ?? 0)) ?></div>
                <small class="text-muted"><?= number_format((int) ($report_summary['visitor_count'] ?? 0)) ?> <?= lang('Treasurer.reportVisitors') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportMembers') ?></div>
                <div class="h3 mb-0"><?= number_format((int) ($report_summary['member_count'] ?? 0)) ?></div>
                <small class="text-muted"><?= number_format((int) ($report_summary['good_standing_count'] ?? 0)) ?> <?= lang('Treasurer.reportGoodStanding') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportAttendance') ?></div>
                <div class="h3 mb-0"><?= number_format((int) ($report_summary['attendance_count'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportDonations') ?></div>
                <div class="h4 mb-0 text-success">$<?= number_format((float) ($report_summary['donations_total'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportExpenses') ?></div>
                <div class="h4 mb-0 text-danger">$<?= number_format((float) ($report_summary['expenses_total'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.reportNetBalance') ?></div>
                <div class="h4 mb-0 <?= ((float) ($report_summary['net_balance'] ?? 0)) >= 0 ? 'text-primary' : 'text-danger' ?>">
                    $<?= number_format((float) ($report_summary['net_balance'] ?? 0), 2) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($report_is_provinceadmin)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?= lang('Treasurer.reportPerDioceseSummary') ?></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Treasurer.reportDiocese') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportParishes') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportPeople') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportVisitors') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportMembers') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportGoodStanding') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportServices') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportAttendance') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportDonations') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportExpenses') ?></th>
                            <th scope="col"><?= lang('Treasurer.reportNet') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($report_diocese_breakdown ?? []) as $row): ?>
                            <tr>
                                <td><?= esc($row['diocese_name']) ?></td>
                                <td><?= number_format((int) $row['parish_count']) ?></td>
                                <td><?= number_format((int) ($row['people_count'] ?? 0)) ?></td>
                                <td><?= number_format((int) ($row['visitor_count'] ?? 0)) ?></td>
                                <td><?= number_format((int) $row['member_count']) ?></td>
                                <td><?= number_format((int) ($row['good_standing_count'] ?? 0)) ?></td>
                                <td><?= number_format((int) $row['service_count']) ?></td>
                                <td><?= number_format((int) $row['attendance_count']) ?></td>
                                <td>$<?= number_format((float) $row['donations_total'], 2) ?></td>
                                <td>$<?= number_format((float) $row['expenses_total'], 2) ?></td>
                                <td class="<?= ((float) $row['net_balance']) >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    $<?= number_format((float) $row['net_balance'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    @media print {

        .sidebar,
        .btn,
        form,
        .top-header-meta {
            display: none !important;
        }

        .main-content {
            width: 100% !important;
        }
    }
</style>

<?= $this->endSection() ?>