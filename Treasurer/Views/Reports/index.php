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
<?php else: ?>
    <!-- Fund Breakdown Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-wallet2 me-2" aria-hidden="true"></i><?= lang('Treasurer.reportFundBreakdown') ?>
            </h5>
            <?php if (!empty($report_fund_breakdown['funds'])): ?>
                <span class="badge border border-primary text-primary fw-normal">
                    <?= count($report_fund_breakdown['funds']) ?> <?= count($report_fund_breakdown['funds']) === 1 ? lang('Treasurer.reportFund') : (lang('Treasurer.reportFunds') ?? lang('Treasurer.reportFund')) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($report_fund_breakdown['funds'])): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                    <?= lang('Treasurer.reportNoFundData') ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Treasurer.reportFund') ?></th>
                                <th scope="col" class="text-center"><?= lang('Treasurer.reportFundType') ?></th>
                                <th scope="col" class="text-end"><?= lang('Treasurer.reportDonations') ?></th>
                                <th scope="col" class="text-end"><?= lang('Treasurer.reportExpenses') ?></th>
                                <th scope="col" class="text-end"><?= lang('Treasurer.reportNetBalance') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_fund_breakdown['funds'] as $fund): ?>
                                <?php
                                $typeColor = ($fund['fund_type'] ?? '') === 'restricted' ? 'warning' : 'info';
                                $fundNet = (float) ($fund['net_balance'] ?? 0.0);
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium"><?= esc($fund['fund_name']) ?></span>
                                        <?php if (empty($fund['is_active'])): ?>
                                            <span class="badge border border-secondary text-secondary fw-normal ms-1"><?= esc(lang('App.inactive')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge border border-<?= $typeColor ?> text-<?= $typeColor ?> fw-normal">
                                            <?= esc(ucfirst((string) ($fund['fund_type'] ?? 'unrestricted'))) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success fw-medium">$<?= number_format((float) ($fund['donations_total'] ?? 0), 2) ?></span>
                                        <div class="small text-muted"><?= number_format((int) ($fund['donation_count'] ?? 0)) ?> <?= strtolower(lang('Treasurer.reportTransactions')) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-danger fw-medium">$<?= number_format((float) ($fund['expenses_total'] ?? 0), 2) ?></span>
                                        <div class="small text-muted"><?= number_format((int) ($fund['expense_count'] ?? 0)) ?> <?= strtolower(lang('Treasurer.reportTransactions')) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?= $fundNet >= 0 ? 'text-primary' : 'text-danger' ?> fw-semibold">
                                            $<?= number_format($fundNet, 2) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold border-top">
                                <td><?= lang('Treasurer.reportTotal') ?></td>
                                <td></td>
                                <td class="text-end text-success">
                                    $<?= number_format((float) ($report_fund_breakdown['totals']['donations_total'] ?? 0), 2) ?>
                                </td>
                                <td class="text-end text-danger">
                                    $<?= number_format((float) ($report_fund_breakdown['totals']['expenses_total'] ?? 0), 2) ?>
                                </td>
                                <td class="text-end <?= ((float) ($report_fund_breakdown['totals']['net_balance'] ?? 0)) >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    $<?= number_format((float) ($report_fund_breakdown['totals']['net_balance'] ?? 0), 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Account & Category Breakdown Section -->
    <div class="card mb-4">
        <div class="card-header border-bottom-0 pb-0">
            <h5 class="mb-3">
                <i class="bi bi-diagram-3 me-2" aria-hidden="true"></i><?= lang('Treasurer.reportAccountCategoryBreakdown') ?>
            </h5>
            <ul class="nav nav-tabs card-header-tabs" id="accountBreakdownTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="income-tab" data-bs-toggle="tab" data-bs-target="#income-panel" type="button" role="tab" aria-controls="income-panel" aria-selected="true">
                        <i class="bi bi-arrow-down-left text-success me-1" aria-hidden="true"></i><?= lang('Treasurer.reportIncomeAccounts') ?>
                        <span class="badge border border-success text-success fw-normal ms-1"><?= count($report_account_breakdown['income'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense-panel" type="button" role="tab" aria-controls="expense-panel" aria-selected="false">
                        <i class="bi bi-arrow-up-right text-danger me-1" aria-hidden="true"></i><?= lang('Treasurer.reportExpenseAccounts') ?>
                        <span class="badge border border-danger text-danger fw-normal ms-1"><?= count($report_account_breakdown['expenses'] ?? []) ?></span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="accountBreakdownTabContent">
                <!-- Income Tab Pane -->
                <div class="tab-pane fade show active" id="income-panel" role="tabpanel" aria-labelledby="income-tab">
                    <?php if (empty($report_account_breakdown['income'])): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                            <?= lang('Treasurer.reportNoAccountData') ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= lang('Treasurer.reportAccountCode') ?></th>
                                        <th scope="col"><?= lang('Treasurer.reportAccount') ?></th>
                                        <th scope="col"><?= lang('Treasurer.reportCategory') ?></th>
                                        <th scope="col" class="text-center"><?= lang('Treasurer.reportTransactions') ?></th>
                                        <th scope="col" class="text-end"><?= lang('Treasurer.reportAmount') ?></th>
                                        <th scope="col" class="text-end" style="width: 140px;"><?= lang('Treasurer.reportPercentOfTotal') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_account_breakdown['income'] as $item): ?>
                                        <tr>
                                            <td>
                                                <code class="text-dark fw-bold"><?= esc($item['account_code']) ?></code>
                                            </td>
                                            <td class="fw-medium"><?= esc($item['account_name']) ?></td>
                                            <td>
                                                <span class="badge border border-secondary text-secondary fw-normal">
                                                    <?= esc($item['category_label']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= number_format((int) ($item['transaction_count'] ?? 0)) ?></td>
                                            <td class="text-end text-success fw-medium">
                                                $<?= number_format((float) ($item['total_amount'] ?? 0), 2) ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <span><?= number_format((float) ($item['percentage'] ?? 0), 1) ?>%</span>
                                                    <div class="progress flex-grow-1" style="height: 5px; max-width: 50px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, (float) ($item['percentage'] ?? 0)) ?>%" aria-valuenow="<?= (float) ($item['percentage'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold border-top">
                                        <td colspan="3"><?= lang('Treasurer.reportTotal') ?></td>
                                        <td class="text-center"><?= number_format((int) ($report_account_breakdown['totals']['income_count'] ?? 0)) ?></td>
                                        <td class="text-end text-success">
                                            $<?= number_format((float) ($report_account_breakdown['totals']['income_total'] ?? 0), 2) ?>
                                        </td>
                                        <td class="text-end">100.0%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Expense Tab Pane -->
                <div class="tab-pane fade" id="expense-panel" role="tabpanel" aria-labelledby="expense-tab">
                    <?php if (empty($report_account_breakdown['expenses'])): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                            <?= lang('Treasurer.reportNoAccountData') ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= lang('Treasurer.reportAccountCode') ?></th>
                                        <th scope="col"><?= lang('Treasurer.reportAccount') ?></th>
                                        <th scope="col"><?= lang('Treasurer.reportCategory') ?></th>
                                        <th scope="col" class="text-center"><?= lang('Treasurer.reportTransactions') ?></th>
                                        <th scope="col" class="text-end"><?= lang('Treasurer.reportAmount') ?></th>
                                        <th scope="col" class="text-end" style="width: 140px;"><?= lang('Treasurer.reportPercentOfTotal') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_account_breakdown['expenses'] as $item): ?>
                                        <tr>
                                            <td>
                                                <code class="text-dark fw-bold"><?= esc($item['account_code']) ?></code>
                                            </td>
                                            <td class="fw-medium"><?= esc($item['account_name']) ?></td>
                                            <td>
                                                <span class="badge border border-secondary text-secondary fw-normal">
                                                    <?= esc($item['category_label']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= number_format((int) ($item['transaction_count'] ?? 0)) ?></td>
                                            <td class="text-end text-danger fw-medium">
                                                $<?= number_format((float) ($item['total_amount'] ?? 0), 2) ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <span><?= number_format((float) ($item['percentage'] ?? 0), 1) ?>%</span>
                                                    <div class="progress flex-grow-1" style="height: 5px; max-width: 50px;">
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= min(100, (float) ($item['percentage'] ?? 0)) ?>%" aria-valuenow="<?= (float) ($item['percentage'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold border-top">
                                        <td colspan="3"><?= lang('Treasurer.reportTotal') ?></td>
                                        <td class="text-center"><?= number_format((int) ($report_account_breakdown['totals']['expenses_count'] ?? 0)) ?></td>
                                        <td class="text-end text-danger">
                                            $<?= number_format((float) ($report_account_breakdown['totals']['expenses_total'] ?? 0), 2) ?>
                                        </td>
                                        <td class="text-end">100.0%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
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