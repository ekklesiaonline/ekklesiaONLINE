<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isOpen = ($recon['status'] ?? '') === 'open';
$reconColor = $isOpen ? 'info' : 'success';
$difference = (float) ($totals['difference'] ?? 0);
$diffColor = abs($difference) < 0.005 ? 'success' : 'danger';
$txnStatusColors = [
    'unmatched'  => 'secondary',
    'cleared'    => 'info',
    'reconciled' => 'success',
];
$txnStatusLabels = [
    'unmatched'  => 'Finance.txnStatusUnmatched',
    'cleared'    => 'Finance.txnStatusCleared',
    'reconciled' => 'Finance.txnStatusReconciled',
];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('banking/' . (int) $recon['bank_account_id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.reconBackToAccount') ?>
    </a>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.bankAccountName') ?></h6>
                <h5 class="mb-0"><?= esc($account['name'] ?? '') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.reconStatementBalance') ?></h6>
                <h5 class="mb-0"><?= number_format((float) $recon['statement_balance'], 2) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.reconClearedTotal') ?></h6>
                <h5 class="mb-0"><?= number_format((float) ($totals['cleared_total'] ?? 0), 2) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.reconDifference') ?></h6>
                <h5 class="mb-0"><span class="badge border border-<?= $diffColor ?> text-<?= $diffColor ?> fw-normal"><?= number_format($difference, 2) ?></span></h5>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <?= lang('Finance.reconStatementDate') ?>: <?= esc($recon['statement_date']) ?>
            <span class="badge border border-<?= $reconColor ?> text-<?= $reconColor ?> fw-normal ms-2"><?= lang($isOpen ? 'Finance.reconStatusOpen' : 'Finance.reconStatusClosed') ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <p class="text-muted mb-0"><?= lang('Finance.bankNoTransactions') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <?php if ($isOpen && !empty($canManage)): ?>
                                    <th scope="col"><?= lang('Finance.reconCleared') ?></th>
                                <?php endif; ?>
                                <th scope="col"><?= lang('Finance.colDate') ?></th>
                                <th scope="col" class="text-end"><?= lang('Finance.colAmount') ?></th>
                                <th scope="col"><?= lang('Finance.bankTxnDescription') ?></th>
                                <th scope="col"><?= lang('Finance.bankTxnReference') ?></th>
                                <th scope="col"><?= lang('Finance.colJournalEntry') ?></th>
                                <th scope="col"><?= lang('App.colStatus') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <?php
                                $txnStatus = (string) ($txn['status'] ?? 'unmatched');
                                $txnColor = $txnStatusColors[$txnStatus] ?? 'secondary';
                                $txnLabelKey = $txnStatusLabels[$txnStatus] ?? 'Finance.txnStatusUnmatched';
                                ?>
                                <tr>
                                    <?php if ($isOpen && !empty($canManage)): ?>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="cleared[]" value="<?= (int) $txn['id'] ?>" id="cleared_<?= (int) $txn['id'] ?>" form="reconItemsForm" <?= (int) ($txn['is_cleared'] ?? 0) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label visually-hidden" for="cleared_<?= (int) $txn['id'] ?>"><?= lang('Finance.reconCleared') ?></label>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= esc($txn['transaction_date']) ?></td>
                                    <td class="text-end"><?= number_format((float) $txn['amount'], 2) ?></td>
                                    <td><?= esc($txn['description'] ?? '—') ?></td>
                                    <td><?= esc($txn['reference'] ?? '—') ?></td>
                                    <td>
                                        <?php if (! empty($txn['journal_entry_number'])): ?>
                                            <span class="badge border border-secondary text-secondary fw-normal">
                                                <i class="bi bi-journal-text me-1" aria-hidden="true"></i><?= esc($txn['journal_entry_number']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge border border-<?= $txnColor ?> text-<?= $txnColor ?> fw-normal"><?= lang($txnLabelKey) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($isOpen && !empty($canManage)): ?>
                    <div class="d-flex gap-2 mt-3">
                        <form method="post" action="<?= base_url('banking/reconciliations/' . (int) $recon['id'] . '/items') ?>" id="reconItemsForm">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-check2-square" aria-hidden="true"></i> <?= lang('Finance.reconSave') ?>
                            </button>
                        </form>
                        <form method="post" action="<?= base_url('banking/reconciliations/' . (int) $recon['id'] . '/close') ?>" onsubmit="return confirm('<?= esc(lang('Finance.reconCloseConfirm'), 'js') ?>');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-success" <?= abs($difference) >= 0.005 ? 'disabled' : '' ?>>
                                <i class="bi bi-lock" aria-hidden="true"></i> <?= lang('Finance.reconClose') ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
