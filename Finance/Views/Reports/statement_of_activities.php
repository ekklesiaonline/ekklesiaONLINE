<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $statement: array containing:
 *   - start_date, end_date, parish_id
 *   - unrestricted_revenues, total_unrestricted_revenues
 *   - restricted_revenues, total_restricted_revenues, total_revenues
 *   - outreach_expenses, total_outreach_expenses
 *   - ministry_expenses, total_ministry_expenses
 *   - operations_expenses, total_operations_expenses, total_expenses
 *   - net_change
 * - $startDate: string (YYYY-MM-DD)
 * - $endDate: string (YYYY-MM-DD)
 */
$netChange = (float) $statement['net_change'];
$isSurplus = $netChange >= 0;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToReports') ?>
        </a>
        <a href="<?= base_url('reports/trial-balance') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-scale" aria-hidden="true"></i> <?= lang('Finance.trialBalanceTitle') ?>
        </a>
        <a href="<?= base_url('reports/balance-sheet') ?>" class="btn btn-outline-primary">
            <i class="bi bi-wallet2" aria-hidden="true"></i> <?= lang('Finance.balanceSheetTitle') ?>
        </a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('App.print') ?>
        </button>
        <a href="<?= base_url('reports/statement-of-activities/export-csv?' . http_build_query(['start_date' => $startDate, 'end_date' => $endDate])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
        </a>
        <a href="<?= base_url('reports/statement-of-activities/export-pdf?' . http_build_query(['start_date' => $startDate, 'end_date' => $endDate])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.generatePdf') ?>
        </a>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <form method="get" action="<?= base_url('reports/statement-of-activities') ?>" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="startDate" class="col-form-label col-form-label-sm fw-semibold"><?= lang('Finance.fromLabel') ?>:</label>
            </div>
            <div class="col-auto">
                <input type="date" id="startDate" name="start_date" class="form-control form-control-sm" value="<?= esc($startDate) ?>">
            </div>
            <div class="col-auto">
                <label for="endDate" class="col-form-label col-form-label-sm fw-semibold"><?= lang('Finance.toLabel') ?>:</label>
            </div>
            <div class="col-auto">
                <input type="date" id="endDate" name="end_date" class="form-control form-control-sm" value="<?= esc($endDate) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                </button>
            </div>
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= base_url('reports/statement-of-activities?' . http_build_query(['start_date' => date('Y-01-01'), 'end_date' => date('Y-m-d')])) ?>" class="btn btn-outline-secondary"><?= lang('Finance.presetYtd') ?></a>
                    <a href="<?= base_url('reports/statement-of-activities?' . http_build_query(['start_date' => date('Y-01-01', strtotime('-1 year')), 'end_date' => date('Y-12-31', strtotime('-1 year'))])) ?>" class="btn btn-outline-secondary"><?= lang('Finance.presetPriorYear') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-4 border-success h-100">
            <div class="card-body py-2">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.totalRevenuesHeading') ?></span>
                <h4 class="mb-0 mt-1 text-success fw-bold">$<?= number_format((float) $statement['total_revenues'], 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-4 border-danger h-100">
            <div class="card-body py-2">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.totalExpensesHeading') ?></span>
                <h4 class="mb-0 mt-1 text-danger fw-bold">$<?= number_format((float) $statement['total_expenses'], 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-4 <?= $isSurplus ? 'border-primary' : 'border-warning' ?> h-100">
            <div class="card-body py-2">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.changeInNetAssetsHeading') ?></span>
                <h4 class="mb-0 mt-1 <?= $isSurplus ? 'text-primary' : 'text-warning' ?> fw-bold">
                    <?= $isSurplus ? '+' : '' ?>$<?= number_format($netChange, 2) ?>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Statement Table -->
<div class="card shadow-sm mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3" style="width: 20%;"><?= lang('Finance.colAccountCode') ?></th>
                        <th scope="col" style="width: 55%;"><?= lang('Finance.colAccountName') ?></th>
                        <th scope="col" class="text-end pe-3" style="width: 25%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- REVENUES -->
                    <tr class="table-group-divider bg-body-tertiary">
                        <td colspan="3" class="fs-6 fw-bold py-2 ps-3 text-success">
                            <i class="bi bi-arrow-down-circle me-1" aria-hidden="true"></i> <?= lang('Finance.revenuesHeading') ?>
                        </td>
                    </tr>
                    <tr class="bg-body-secondary">
                        <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.unrestrictedRevenuesHeading') ?></td>
                    </tr>
                    <?php if (empty($statement['unrestricted_revenues'])): ?>
                        <tr><td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($statement['unrestricted_revenues'] as $acc): ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-success text-success fw-normal"><?= esc($acc['code']) ?></span></td>
                                <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-semibold border-top">
                        <td colspan="2" class="ps-3"><?= lang('Finance.totalUnrestrictedRevenues') ?></td>
                        <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $statement['total_unrestricted_revenues'], 2) ?></td>
                    </tr>

                    <tr class="bg-body-secondary">
                        <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.restrictedRevenuesHeading') ?></td>
                    </tr>
                    <?php if (empty($statement['restricted_revenues'])): ?>
                        <tr><td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($statement['restricted_revenues'] as $acc): ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-success text-success fw-normal"><?= esc($acc['code']) ?></span></td>
                                <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-semibold border-top">
                        <td colspan="2" class="ps-3"><?= lang('Finance.totalRestrictedRevenues') ?></td>
                        <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $statement['total_restricted_revenues'], 2) ?></td>
                    </tr>
                    <tr class="fw-bold border-top border-success">
                        <td colspan="2" class="ps-3 py-2 fs-6 text-success"><?= lang('Finance.totalRevenuesHeading') ?></td>
                        <td class="text-end pe-3 py-2 fs-6 text-success" style="font-family: monospace;">$<?= number_format((float) $statement['total_revenues'], 2) ?></td>
                    </tr>

                    <!-- EXPENSES -->
                    <tr class="table-group-divider bg-body-tertiary">
                        <td colspan="3" class="fs-6 fw-bold py-2 ps-3 text-danger">
                            <i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i> <?= lang('Finance.expensesHeading') ?>
                        </td>
                    </tr>
                    <tr class="bg-body-secondary">
                        <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.outreachExpensesHeading') ?></td>
                    </tr>
                    <?php if (empty($statement['outreach_expenses'])): ?>
                        <tr><td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($statement['outreach_expenses'] as $acc): ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-danger text-danger fw-normal"><?= esc($acc['code']) ?></span></td>
                                <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-semibold border-top">
                        <td colspan="2" class="ps-3"><?= lang('Finance.totalOutreachExpenses') ?></td>
                        <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $statement['total_outreach_expenses'], 2) ?></td>
                    </tr>

                    <tr class="bg-body-secondary">
                        <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.ministryExpensesHeading') ?></td>
                    </tr>
                    <?php if (empty($statement['ministry_expenses'])): ?>
                        <tr><td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($statement['ministry_expenses'] as $acc): ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-danger text-danger fw-normal"><?= esc($acc['code']) ?></span></td>
                                <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-semibold border-top">
                        <td colspan="2" class="ps-3"><?= lang('Finance.totalMinistryExpenses') ?></td>
                        <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $statement['total_ministry_expenses'], 2) ?></td>
                    </tr>

                    <tr class="bg-body-secondary">
                        <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.operationsExpensesHeading') ?></td>
                    </tr>
                    <?php if (empty($statement['operations_expenses'])): ?>
                        <tr><td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($statement['operations_expenses'] as $acc): ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-danger text-danger fw-normal"><?= esc($acc['code']) ?></span></td>
                                <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-semibold border-top">
                        <td colspan="2" class="ps-3"><?= lang('Finance.totalOperationsExpenses') ?></td>
                        <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $statement['total_operations_expenses'], 2) ?></td>
                    </tr>
                    <tr class="fw-bold border-top border-danger">
                        <td colspan="2" class="ps-3 py-2 fs-6 text-danger"><?= lang('Finance.totalExpensesHeading') ?></td>
                        <td class="text-end pe-3 py-2 fs-6 text-danger" style="font-family: monospace;">$<?= number_format((float) $statement['total_expenses'], 2) ?></td>
                    </tr>
                </tbody>

                <!-- NET CHANGE FOOTER -->
                <tfoot>
                    <tr class="border-top border-3 border-dark fw-bold">
                        <td colspan="2" class="ps-3 py-3 fs-5"><?= lang('Finance.changeInNetAssetsHeading') ?></td>
                        <td class="text-end pe-3 py-3 fs-5 <?= $isSurplus ? 'text-success' : 'text-danger' ?>" style="font-family: monospace;">
                            <?= $isSurplus ? '+' : '' ?>$<?= number_format($netChange, 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
