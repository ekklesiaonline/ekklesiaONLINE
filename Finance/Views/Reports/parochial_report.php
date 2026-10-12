<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $reportLines: array containing Parochial Report canonical finance lines (1-12, A, B)
 * - $year: int (selected reporting year)
 */
$netOperating = (float) ($reportLines['net_operating'] ?? 0.0);
$isSurplus = $netOperating >= 0;
$badgeColor = $isSurplus ? 'success' : 'danger';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToReports') ?>
        </a>
        <a href="<?= base_url('reports/trial-balance') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-scale" aria-hidden="true"></i> <?= lang('Finance.trialBalanceTitle') ?>
        </a>
        <a href="<?= base_url('reports/balance-sheet') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-wallet2" aria-hidden="true"></i> <?= lang('Finance.balanceSheetTitle') ?>
        </a>
        <a href="<?= base_url('reports/statement-of-activities') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-graph-up" aria-hidden="true"></i> <?= lang('Finance.statementOfActivitiesTitle') ?>
        </a>
        <a href="<?= base_url('reports/parochial-report') ?>" class="btn btn-outline-primary active">
            <i class="bi bi-file-earmark-ruled" aria-hidden="true"></i> <?= lang('Finance.parochialReportTitle') ?>
        </a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('App.print') ?>
        </button>
        <a href="<?= base_url('reports/parochial-report/export-csv?' . http_build_query(['year' => $year])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
        </a>
        <a href="<?= base_url('reports/parochial-report/export-pdf?' . http_build_query(['year' => $year])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.generatePdf') ?>
        </a>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <form method="get" action="<?= base_url('reports/parochial-report') ?>" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="reportYear" class="col-form-label col-form-label-sm fw-semibold"><?= lang('App.year') ?>:</label>
            </div>
            <div class="col-auto">
                <select id="reportYear" name="year" class="form-select form-select-sm">
                    <?php
                    $currentY = (int) date('Y');
                    for ($y = $currentY; $y >= $currentY - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                </button>
            </div>
            <div class="col-auto ms-auto">
                <span class="badge border border-primary text-primary fw-normal py-2 px-3">
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> <?= lang('App.year') ?> <?= esc($year) ?>
                </span>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.totalOperatingRevenueA') ?></span>
                <div class="fs-4 fw-bold text-success mt-1">$<?= number_format((float) ($reportLines['total_revenue'] ?? 0.0), 2) ?></div>
                <div class="small text-muted mt-1"><?= lang('Finance.line3PlatePledge') ?> + Lines 4, 5</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.totalOperatingExpensesB') ?></span>
                <div class="fs-4 fw-bold text-danger mt-1">$<?= number_format((float) ($reportLines['total_expenses'] ?? 0.0), 2) ?></div>
                <div class="small text-muted mt-1">Lines 6 &amp; 7 Total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.netOperatingTotal') ?></span>
                <div class="fs-4 fw-bold text-<?= esc($badgeColor) ?> mt-1">
                    $<?= number_format($netOperating, 2) ?>
                </div>
                <div class="mt-1">
                    <span class="badge border border-<?= esc($badgeColor) ?> text-<?= esc($badgeColor) ?> fw-normal">
                        <?= $isSurplus ? lang('Finance.surplusBadge') : lang('Finance.deficitBadge') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <span class="text-muted small text-uppercase fw-semibold"><?= lang('Finance.line8Cash') ?></span>
                <div class="fs-4 fw-bold text-primary mt-1">$<?= number_format((float) ($reportLines['cash_balance'] ?? 0.0), 2) ?></div>
                <div class="small text-muted mt-1"><?= lang('Finance.asOfDate') ?> Dec 31, <?= esc($year) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Section: Stewardship & Pledging -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-transparent py-2">
        <h6 class="card-title mb-0 fw-semibold">
            <i class="bi bi-heart me-1 text-danger" aria-hidden="true"></i> <?= lang('Finance.stewardshipSectionHeading') ?>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colParochialLine') ?></th>
                        <th scope="col" style="width: 65%;"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Line 1</td>
                        <td><?= lang('Finance.line1PledgesCount') ?></td>
                        <td class="text-end fw-bold"><?= number_format((int) ($reportLines['confirmed_pledges_count'] ?? 0)) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 2</td>
                        <td><?= lang('Finance.line2PledgesAmount') ?></td>
                        <td class="text-end fw-bold text-success">$<?= number_format((float) ($reportLines['confirmed_pledges_amount'] ?? 0.0), 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section: Operating Revenue -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-semibold">
            <i class="bi bi-box-arrow-in-down me-1 text-success" aria-hidden="true"></i> <?= lang('Finance.operatingRevenueHeading') ?>
        </h6>
        <span class="badge border border-success text-success fw-normal">
            Line A: $<?= number_format((float) ($reportLines['total_revenue'] ?? 0.0), 2) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colParochialLine') ?></th>
                        <th scope="col" style="width: 65%;"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Line 3 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 3</td>
                        <td class="fw-semibold"><?= lang('Finance.line3PlatePledge') ?></td>
                        <td class="text-end fw-bold text-success">$<?= number_format((float) ($reportLines['line_3_plate_pledge'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['revenue_line3_accounts'])): ?>
                        <?php foreach ($reportLines['revenue_line3_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line 4 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 4</td>
                        <td class="fw-semibold"><?= lang('Finance.line4Restricted') ?></td>
                        <td class="text-end fw-bold text-success">$<?= number_format((float) ($reportLines['line_4_restricted'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['revenue_line4_accounts'])): ?>
                        <?php foreach ($reportLines['revenue_line4_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line 5 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 5</td>
                        <td class="fw-semibold"><?= lang('Finance.line5OtherRevenue') ?></td>
                        <td class="text-end fw-bold text-success">$<?= number_format((float) ($reportLines['line_5_other_revenue'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['revenue_line5_accounts'])): ?>
                        <?php foreach ($reportLines['revenue_line5_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line A -->
                    <tr class="border-top border-2 border-dark">
                        <td class="fw-bold">Line A</td>
                        <td class="fw-bold"><?= lang('Finance.totalOperatingRevenueA') ?></td>
                        <td class="text-end fw-bold fs-6 text-success">$<?= number_format((float) ($reportLines['total_revenue'] ?? 0.0), 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section: Operating Expenses -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-semibold">
            <i class="bi bi-box-arrow-up me-1 text-danger" aria-hidden="true"></i> <?= lang('Finance.operatingExpensesHeading') ?>
        </h6>
        <span class="badge border border-danger text-danger fw-normal">
            Line B: $<?= number_format((float) ($reportLines['total_expenses'] ?? 0.0), 2) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colParochialLine') ?></th>
                        <th scope="col" style="width: 65%;"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Line 6 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 6</td>
                        <td class="fw-semibold"><?= lang('Finance.line6Noncapital') ?></td>
                        <td class="text-end fw-bold text-danger">$<?= number_format((float) ($reportLines['line_6_noncapital'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['expense_line6_accounts'])): ?>
                        <?php foreach ($reportLines['expense_line6_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line 7 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 7</td>
                        <td class="fw-semibold"><?= lang('Finance.line7Capital') ?></td>
                        <td class="text-end fw-bold text-danger">$<?= number_format((float) ($reportLines['line_7_capital'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['expense_line7_accounts'])): ?>
                        <?php foreach ($reportLines['expense_line7_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) $acc['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line B -->
                    <tr class="border-top border-2 border-dark">
                        <td class="fw-bold">Line B</td>
                        <td class="fw-bold"><?= lang('Finance.totalOperatingExpensesB') ?></td>
                        <td class="text-end fw-bold fs-6 text-danger">$<?= number_format((float) ($reportLines['total_expenses'] ?? 0.0), 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section: Cash, Endowments & Investments -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-transparent py-2">
        <h6 class="card-title mb-0 fw-semibold">
            <i class="bi bi-bank me-1 text-primary" aria-hidden="true"></i> <?= lang('Finance.cashAndInvestmentsHeading') ?>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colParochialLine') ?></th>
                        <th scope="col" style="width: 65%;"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Line 8 -->
                    <tr class="table-light">
                        <td class="fw-semibold">Line 8</td>
                        <td class="fw-semibold"><?= lang('Finance.line8Cash') ?></td>
                        <td class="text-end fw-bold text-primary">$<?= number_format((float) ($reportLines['cash_balance'] ?? 0.0), 2) ?></td>
                    </tr>
                    <?php if (!empty($reportLines['cash_line8_accounts'])): ?>
                        <?php foreach ($reportLines['cash_line8_accounts'] as $acc): ?>
                            <tr>
                                <td></td>
                                <td class="ps-4 text-muted small">
                                    <code><?= esc($acc['code']) ?></code> — <?= esc($acc['name']) ?>
                                </td>
                                <td class="text-end text-muted small">$<?= number_format((float) ($acc['balance'] ?? 0.0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Line 9a-d -->
                    <tr>
                        <td class="fw-semibold">Line 9a</td>
                        <td><?= lang('Finance.line9aRestrictedEndowment') ?></td>
                        <td class="text-end">$<?= number_format((float) ($reportLines['endowment_restricted'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 9b</td>
                        <td><?= lang('Finance.line9bUnrestrictedEndowment') ?></td>
                        <td class="text-end">$<?= number_format((float) ($reportLines['endowment_unrestricted'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr class="table-light">
                        <td></td>
                        <td class="fw-semibold ps-3"><?= lang('Finance.totalEndowmentsInvestments') ?> (Endowments)</td>
                        <td class="text-end fw-semibold">$<?= number_format((float) ($reportLines['total_endowment'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 9c</td>
                        <td><?= lang('Finance.line9cRestrictedInvestments') ?></td>
                        <td class="text-end">$<?= number_format((float) ($reportLines['investment_restricted'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 9d</td>
                        <td><?= lang('Finance.line9dUnrestrictedInvestments') ?></td>
                        <td class="text-end">$<?= number_format((float) ($reportLines['investment_unrestricted'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr class="table-light border-top">
                        <td class="fw-bold">Line 9</td>
                        <td class="fw-bold"><?= lang('Finance.totalEndowmentsInvestments') ?></td>
                        <td class="text-end fw-bold text-primary">$<?= number_format((float) ($reportLines['total_investments'] ?? 0.0), 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section: Debt & Property -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-transparent py-2">
        <h6 class="card-title mb-0 fw-semibold">
            <i class="bi bi-building me-1 text-secondary" aria-hidden="true"></i> <?= lang('Finance.debtAndPropertyHeading') ?>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colParochialLine') ?></th>
                        <th scope="col" style="width: 65%;"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Line 10</td>
                        <td>
                            <?= lang('Finance.line10Debt') ?>
                            <?php if (!empty($reportLines['debt_description'])): ?>
                                <span class="text-muted small ms-2">(<?= esc($reportLines['debt_description']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold text-dark">$<?= number_format((float) ($reportLines['debt_amount'] ?? 0.0), 2) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 11</td>
                        <td><?= lang('Finance.line11Property') ?></td>
                        <td class="text-end">
                            <?php if (!empty($reportLines['owns_property'])): ?>
                                <span class="badge border border-success text-success fw-normal"><?= lang('App.yes') ?></span>
                            <?php else: ?>
                                <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Line 12</td>
                        <td><?= lang('Finance.line12Rental') ?></td>
                        <td class="text-end">
                            <?php if (!empty($reportLines['rents_to_outside'])): ?>
                                <span class="badge border border-primary text-primary fw-normal"><?= lang('App.yes') ?></span>
                            <?php else: ?>
                                <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($reportLines['rents_to_outside'])): ?>
                        <tr>
                            <td></td>
                            <td class="ps-4 text-muted small"><?= lang('Finance.rentalIncomeLabel') ?></td>
                            <td class="text-end text-muted small">$<?= number_format((float) ($reportLines['rental_income'] ?? 0.0), 2) ?></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="ps-4 text-muted small"><?= lang('Finance.rentalExpenseLabel') ?></td>
                            <td class="text-end text-muted small">$<?= number_format((float) ($reportLines['rental_expense'] ?? 0.0), 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-light border shadow-sm mb-4">
    <div class="d-flex align-items-start">
        <i class="bi bi-info-circle text-primary fs-5 me-2 mt-0" aria-hidden="true"></i>
        <div class="small text-muted">
            <?= lang('Finance.canonicalParochialNotice') ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
