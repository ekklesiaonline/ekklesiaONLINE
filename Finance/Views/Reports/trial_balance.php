<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $trialBalance: array with as_of_date, accounts, total_debits, total_credits, variance, is_balanced, include_zero
 * - $asOfDate: string (YYYY-MM-DD)
 * - $includeZero: bool
 */
$typeBadgeMap = [
    'asset'     => 'primary',
    'liability' => 'warning',
    'equity'    => 'info',
    'income'    => 'success',
    'expense'   => 'danger',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToReports') ?>
        </a>
        <a href="<?= base_url('reports/balance-sheet') ?>" class="btn btn-outline-primary">
            <i class="bi bi-wallet2" aria-hidden="true"></i> <?= lang('Finance.balanceSheetTitle') ?>
        </a>
        <a href="<?= base_url('reports/statement-of-activities') ?>" class="btn btn-outline-success">
            <i class="bi bi-graph-up" aria-hidden="true"></i> <?= lang('Finance.statementOfActivitiesTitle') ?>
        </a>
        <a href="<?= base_url('journal-entries') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text" aria-hidden="true"></i> <?= lang('Finance.journalEntriesTitle') ?>
        </a>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('App.print') ?>
        </button>
        <a href="<?= base_url('reports/trial-balance/export-csv?' . http_build_query(['as_of_date' => $asOfDate, 'include_zero' => $includeZero ? '1' : '0'])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
        </a>
        <a href="<?= base_url('reports/trial-balance/export-pdf?' . http_build_query(['as_of_date' => $asOfDate, 'include_zero' => $includeZero ? '1' : '0'])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.generatePdf') ?>
        </a>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-body py-2">
        <form method="get" action="<?= base_url('reports/trial-balance') ?>" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="asOfDate" class="col-form-label col-form-label-sm fw-semibold"><?= lang('Finance.asOfDate') ?>:</label>
            </div>
            <div class="col-auto">
                <input type="date" id="asOfDate" name="as_of_date" class="form-control form-control-sm" value="<?= esc($asOfDate) ?>">
            </div>
            <div class="col-auto">
                <div class="form-check form-check-inline pt-1">
                    <input class="form-check-input" type="checkbox" id="includeZero" name="include_zero" value="1" <?= $includeZero ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="includeZero"><?= lang('Finance.includeZeroBalances') ?></label>
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($trialBalance['is_balanced']): ?>
    <div class="alert alert-success border border-success bg-transparent text-success d-flex align-items-center mb-3 py-2" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>
            <strong><?= lang('Finance.trialBalanceBalanced') ?></strong> — 
            <?= lang('Finance.trialBalanceBalancedMsg', [number_format((float) $trialBalance['total_debits'], 2)]) ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-danger border border-danger bg-transparent text-danger d-flex align-items-center mb-3 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong><?= lang('Finance.trialBalanceUnbalanced') ?></strong> — 
            <?= lang('Finance.trialBalanceUnbalancedMsg', [number_format((float) $trialBalance['total_debits'], 2), number_format((float) $trialBalance['total_credits'], 2), number_format((float) $trialBalance['variance'], 2)]) ?>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colAccountCode') ?></th>
                        <th scope="col" style="width: 45%;"><?= lang('Finance.colAccountName') ?></th>
                        <th scope="col" style="width: 16%;"><?= lang('Finance.colAccountType') ?></th>
                        <th scope="col" class="text-end" style="width: 12%;"><?= lang('Finance.colDebitBalance') ?></th>
                        <th scope="col" class="text-end" style="width: 12%;"><?= lang('Finance.colCreditBalance') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trialBalance['accounts'])): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                <?= lang('Finance.noTrialBalanceData') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trialBalance['accounts'] as $acc): ?>
                            <?php
                            $type = strtolower((string) ($acc['account_type'] ?? ''));
                            $color = $typeBadgeMap[$type] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="font-monospace fw-semibold"><?= esc($acc['code']) ?></td>
                                <td><?= esc($acc['name']) ?></td>
                                <td>
                                    <span class="badge border border-<?= $color ?> text-<?= $color ?> fw-normal">
                                        <?= esc(ucfirst($type)) ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= ((float) ($acc['debit_balance'] ?? 0)) > 0 ? '$' . number_format((float) $acc['debit_balance'], 2) : '—' ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= ((float) ($acc['credit_balance'] ?? 0)) > 0 ? '$' . number_format((float) $acc['credit_balance'], 2) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold border-top bg-body-tertiary">
                        <td colspan="3" class="py-3"><?= lang('Finance.reportTotal') ?></td>
                        <td class="text-end py-3 font-monospace text-primary fs-6">
                            $<?= number_format((float) ($trialBalance['total_debits'] ?? 0), 2) ?>
                        </td>
                        <td class="text-end py-3 font-monospace text-primary fs-6">
                            $<?= number_format((float) ($trialBalance['total_credits'] ?? 0), 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
