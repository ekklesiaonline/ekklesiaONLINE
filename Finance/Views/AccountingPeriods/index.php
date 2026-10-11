<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $periods: array of accounting periods for the selected year
 * - $fiscalYears: list of available fiscal years
 * - $selectedYear: int
 * - $summary: array with total, open, closed
 * - $canManage: bool
 */
$statusBadgeMap = [
    'open'        => 'success',
    'closed'      => 'secondary',
    'soft_closed' => 'warning',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('parish/finance') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToFinanceDashboard') ?>
        </a>
        <a href="<?= base_url('journal-entries') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text" aria-hidden="true"></i> <?= lang('Finance.journalEntriesTitle') ?>
        </a>
    </div>
    <?php if (!empty($canManage)): ?>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generatePeriodsModal">
                <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i> <?= lang('Finance.generatePeriods') ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> <?= lang('Finance.addPeriod') ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colTotalPeriods') ?></div>
                <div class="h4 mb-0"><?= (int) ($summary['total'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colOpenPeriods') ?></div>
                <div class="h4 mb-0 text-success"><?= (int) ($summary['open'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colClosedPeriods') ?></div>
                <div class="h4 mb-0 text-secondary"><?= (int) ($summary['closed'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <form method="get" action="<?= base_url('accounting-periods') ?>" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="fiscalYearSelect" class="col-form-label col-form-label-sm fw-semibold">
                    <i class="bi bi-calendar3 me-1"></i><?= lang('Finance.fiscalYear') ?>:
                </label>
            </div>
            <div class="col-auto">
                <select id="fiscalYearSelect" name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($fiscalYears as $yr): ?>
                        <option value="<?= (int) $yr ?>" <?= (int) $yr === (int) $selectedYear ? 'selected' : '' ?>>
                            <?= (int) $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 8%;"><?= lang('Finance.periodNumber') ?></th>
                        <th scope="col" style="width: 25%;"><?= lang('Finance.periodName') ?></th>
                        <th scope="col" style="width: 12%;"><?= lang('Finance.fiscalYear') ?></th>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.periodStartDate') ?></th>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.periodEndDate') ?></th>
                        <th scope="col" style="width: 10%;"><?= lang('Finance.periodStatus') ?></th>
                        <th scope="col" class="text-end" style="width: 15%;"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($periods)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2 text-secondary"></i>
                                <?= lang('Finance.noAccountingPeriodsFound') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($periods as $p): ?>
                            <?php
                            $st = (string) ($p['status'] ?? 'open');
                            $color = $statusBadgeMap[$st] ?? 'secondary';
                            $isOpen = $st === 'open';
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int) ($p['period_number'] ?? 0) ?></td>
                                <td><?= esc($p['period_name']) ?></td>
                                <td><?= (int) ($p['fiscal_year'] ?? 0) ?></td>
                                <td><?= esc($p['start_date']) ?></td>
                                <td><?= esc($p['end_date']) ?></td>
                                <td>
                                    <span class="badge border border-<?= $color ?> text-<?= $color ?> fw-normal">
                                        <?= esc(ucfirst(str_replace('_', ' ', $st))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if (!empty($canManage)): ?>
                                        <?php if ($isOpen): ?>
                                            <form method="post" action="<?= base_url('accounting-periods/close/' . (int) $p['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Finance.confirmClosePeriod')) ?>');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="<?= esc(lang('Finance.closePeriod')) ?>">
                                                    <i class="bi bi-lock me-1"></i><?= lang('Finance.closePeriod') ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="<?= base_url('accounting-periods/reopen/' . (int) $p['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Finance.confirmReopenPeriod')) ?>');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="<?= esc(lang('Finance.reopenPeriod')) ?>">
                                                    <i class="bi bi-unlock me-1"></i><?= lang('Finance.reopenPeriod') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($canManage)): ?>
<!-- Modal: Generate 12 Standard Monthly Periods -->
<div class="modal fade" id="generatePeriodsModal" tabindex="-1" aria-labelledby="generatePeriodsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="post" action="<?= base_url('accounting-periods/generate') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="generatePeriodsModalLabel">
                        <i class="bi bi-calendar-plus me-2 text-primary"></i><?= lang('Finance.generatePeriodsTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        <?= lang('Finance.generatePeriodsConfirm') ?>
                    </p>
                    <div class="mb-3">
                        <label for="generateYearInput" class="form-label"><?= lang('Finance.fiscalYear') ?> <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="generateYearInput" name="fiscal_year" value="<?= (int) $selectedYear ?>" min="2000" max="2100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check-circle me-1"></i><?= lang('Finance.generatePeriods') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Single Period -->
<div class="modal fade" id="addPeriodModal" tabindex="-1" aria-labelledby="addPeriodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="post" action="<?= base_url('accounting-periods/store') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addPeriodModalLabel">
                        <i class="bi bi-plus-circle me-2 text-primary"></i><?= lang('Finance.addPeriod') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="newPeriodYear" class="form-label small"><?= lang('Finance.fiscalYear') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="newPeriodYear" name="fiscal_year" value="<?= (int) $selectedYear ?>" required min="2000" max="2100">
                        </div>
                        <div class="col-6">
                            <label for="newPeriodNumber" class="form-label small"><?= lang('Finance.periodNumber') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="newPeriodNumber" name="period_number" min="1" max="24" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="newPeriodName" class="form-label small"><?= lang('Finance.periodName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="newPeriodName" name="period_name" required maxlength="50" placeholder="e.g. Q1 Adjustment 2026">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="newPeriodStart" class="form-label small"><?= lang('Finance.periodStartDate') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="newPeriodStart" name="start_date" required>
                        </div>
                        <div class="col-6">
                            <label for="newPeriodEnd" class="form-label small"><?= lang('Finance.periodEndDate') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="newPeriodEnd" name="end_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-save me-1"></i><?= lang('App.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
