<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $balanceSheet: array containing:
 *   - as_of_date, parish_id
 *   - current_assets, total_current_assets
 *   - fixed_assets, total_fixed_assets, total_assets
 *   - current_liabilities, total_current_liabilities
 *   - long_term_liabilities, total_long_term_liabilities, total_liabilities
 *   - equity_accounts, total_stated_equity, current_period_net_change, total_net_assets
 *   - total_liabilities_and_net_assets, variance, is_balanced, funds_summary
 * - $asOfDate: string (YYYY-MM-DD)
 */
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToReports') ?>
        </a>
        <a href="<?= base_url('reports/trial-balance') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-scale" aria-hidden="true"></i> <?= lang('Finance.trialBalanceTitle') ?>
        </a>
        <a href="<?= base_url('reports/statement-of-activities') ?>" class="btn btn-outline-primary">
            <i class="bi bi-graph-up" aria-hidden="true"></i> <?= lang('Finance.statementOfActivitiesTitle') ?>
        </a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('App.print') ?>
        </button>
        <a href="<?= base_url('reports/balance-sheet/export-csv?' . http_build_query(['as_of_date' => $asOfDate])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
        </a>
        <a href="<?= base_url('reports/balance-sheet/export-pdf?' . http_build_query(['as_of_date' => $asOfDate])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.generatePdf') ?>
        </a>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <form method="get" action="<?= base_url('reports/balance-sheet') ?>" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="asOfDate" class="col-form-label col-form-label-sm fw-semibold"><?= lang('Finance.asOfDate') ?>:</label>
            </div>
            <div class="col-auto">
                <input type="date" id="asOfDate" name="as_of_date" class="form-control form-control-sm" value="<?= esc($asOfDate) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($balanceSheet['is_balanced']): ?>
    <div class="alert alert-success border border-success bg-transparent text-success d-flex align-items-center mb-3 py-2" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>
            <strong><?= lang('Finance.balanceSheetBalanced') ?></strong> — 
            <?= lang('Finance.balanceSheetBalancedMsg', [number_format((float) $balanceSheet['total_assets'], 2)]) ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-danger border border-danger bg-transparent text-danger d-flex align-items-center mb-3 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong><?= lang('Finance.balanceSheetUnbalanced') ?></strong> — 
            <?= lang('Finance.balanceSheetUnbalancedMsg', [number_format((float) $balanceSheet['total_assets'], 2), number_format((float) $balanceSheet['total_liabilities_and_net_assets'], 2), number_format((float) $balanceSheet['variance'], 2)]) ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- Assets Column -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent py-2 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold text-primary">
                    <i class="bi bi-wallet2 me-1" aria-hidden="true"></i> <?= lang('Finance.assetsHeading') ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3" style="width: 20%;"><?= lang('Finance.colAccountCode') ?></th>
                                <th scope="col" style="width: 55%;"><?= lang('Finance.colAccountName') ?></th>
                                <th scope="col" class="text-end pe-3" style="width: 25%;"><?= lang('Finance.balanceLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-group-divider bg-body-tertiary">
                                <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.currentAssetsHeading') ?></td>
                            </tr>
                            <?php if (empty($balanceSheet['current_assets'])): ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($balanceSheet['current_assets'] as $acc): ?>
                                    <tr>
                                        <td class="ps-3" style="width: 20%;"><span class="badge border border-primary text-primary fw-normal"><?= esc($acc['code']) ?></span></td>
                                        <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                        <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="fw-bold border-top">
                                <td colspan="2" class="ps-3"><?= lang('Finance.totalCurrentAssets') ?></td>
                                <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_current_assets'], 2) ?></td>
                            </tr>
                            <tr class="table-group-divider bg-body-tertiary">
                                <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.fixedAssetsHeading') ?></td>
                            </tr>
                            <?php if (empty($balanceSheet['fixed_assets'])): ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($balanceSheet['fixed_assets'] as $acc): ?>
                                    <tr>
                                        <td class="ps-3" style="width: 20%;"><span class="badge border border-primary text-primary fw-normal"><?= esc($acc['code']) ?></span></td>
                                        <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                        <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="fw-bold border-top">
                                <td colspan="2" class="ps-3"><?= lang('Finance.totalFixedAssets') ?></td>
                                <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_fixed_assets'], 2) ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-top border-2 border-primary fw-bold">
                                <td colspan="2" class="ps-3 py-2 fs-6"><?= lang('Finance.totalAssetsHeading') ?></td>
                                <td class="text-end pe-3 py-2 fs-6" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_assets'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Liabilities & Net Assets Column -->
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent py-2 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold text-danger">
                    <i class="bi bi-credit-card me-1" aria-hidden="true"></i> <?= lang('Finance.liabilitiesHeading') ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3" style="width: 20%;"><?= lang('Finance.colAccountCode') ?></th>
                                <th scope="col" style="width: 55%;"><?= lang('Finance.colAccountName') ?></th>
                                <th scope="col" class="text-end pe-3" style="width: 25%;"><?= lang('Finance.balanceLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-group-divider bg-body-tertiary">
                                <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.currentLiabilitiesHeading') ?></td>
                            </tr>
                            <?php if (empty($balanceSheet['current_liabilities'])): ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($balanceSheet['current_liabilities'] as $acc): ?>
                                    <tr>
                                        <td class="ps-3" style="width: 20%;"><span class="badge border border-warning text-warning fw-normal"><?= esc($acc['code']) ?></span></td>
                                        <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                        <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="fw-bold border-top">
                                <td colspan="2" class="ps-3"><?= lang('Finance.totalCurrentLiabilities') ?></td>
                                <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_current_liabilities'], 2) ?></td>
                            </tr>
                            <tr class="table-group-divider bg-body-tertiary">
                                <td colspan="3" class="fw-semibold text-secondary py-1 ps-3"><?= lang('Finance.longTermLiabilitiesHeading') ?></td>
                            </tr>
                            <?php if (empty($balanceSheet['long_term_liabilities'])): ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2 fst-italic"><?= lang('Finance.noneLabel') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($balanceSheet['long_term_liabilities'] as $acc): ?>
                                    <tr>
                                        <td class="ps-3" style="width: 20%;"><span class="badge border border-warning text-warning fw-normal"><?= esc($acc['code']) ?></span></td>
                                        <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                        <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="fw-bold border-top">
                                <td colspan="2" class="ps-3"><?= lang('Finance.totalLongTermLiabilities') ?></td>
                                <td class="text-end pe-3" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_long_term_liabilities'], 2) ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-top border-2 border-danger fw-bold">
                                <td colspan="2" class="ps-3 py-2"><?= lang('Finance.totalLiabilitiesHeading') ?></td>
                                <td class="text-end pe-3 py-2" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_liabilities'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Net Assets (Equity) Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-transparent py-2 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold text-success">
                    <i class="bi bi-pie-chart me-1" aria-hidden="true"></i> <?= lang('Finance.netAssetsHeading') ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3" style="width: 20%;"><?= lang('Finance.colAccountCode') ?></th>
                                <th scope="col" style="width: 55%;"><?= lang('Finance.colAccountName') ?></th>
                                <th scope="col" class="text-end pe-3" style="width: 25%;"><?= lang('Finance.balanceLabel') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balanceSheet['equity_accounts'] as $acc): ?>
                                <tr>
                                    <td class="ps-3" style="width: 20%;"><span class="badge border border-info text-info fw-normal"><?= esc($acc['code']) ?></span></td>
                                    <td style="width: 55%;"><?= esc($acc['name']) ?></td>
                                    <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $acc['balance'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td class="ps-3" style="width: 20%;"><span class="badge border border-secondary text-secondary fw-normal">YTD</span></td>
                                <td style="width: 55%;"><?= lang('Finance.currentPeriodNetChange') ?></td>
                                <td class="text-end pe-3" style="width: 25%; font-family: monospace;">$<?= number_format((float) $balanceSheet['current_period_net_change'], 2) ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-top border-2 border-success fw-bold">
                                <td colspan="2" class="ps-3 py-2"><?= lang('Finance.totalNetAssetsHeading') ?></td>
                                <td class="text-end pe-3 py-2" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_net_assets'], 2) ?></td>
                            </tr>
                            <tr class="border-top border-2 border-dark fw-bold">
                                <td colspan="2" class="ps-3 py-2 fs-6"><?= lang('Finance.totalLiabilitiesAndNetAssetsHeading') ?></td>
                                <td class="text-end pe-3 py-2 fs-6" style="font-family: monospace;">$<?= number_format((float) $balanceSheet['total_liabilities_and_net_assets'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
