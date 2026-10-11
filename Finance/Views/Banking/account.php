<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$bookBalance = (float) ($account['opening_balance'] ?? 0);
foreach (($transactions ?? []) as $txn) {
    $bookBalance += (float) ($txn['amount'] ?? 0);
}
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
    <a href="<?= base_url('banking') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.bankingTitle') ?>
    </a>
    <?php if (!empty($canManage)): ?>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importStatementModal">
            <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> <?= lang('Finance.bankImportStatement') ?>
        </button>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.bankOpeningBalance') ?></h6>
                <h4 class="mb-0"><?= esc($account['currency_code']) ?> <?= number_format((float) $account['opening_balance'], 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.bankBookBalance') ?></h6>
                <h4 class="mb-0"><?= esc($account['currency_code']) ?> <?= number_format($bookBalance, 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Finance.bankTransactions') ?></h6>
                <h4 class="mb-0"><?= count($transactions ?? []) ?></h4>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($canManage)): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><?= lang('Finance.bankAddTransaction') ?></h6>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importStatementModal">
                <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i> <?= lang('Finance.bankImportStatement') ?>
            </button>
        </div>
        <div class="card-body">
            <form method="post" action="<?= base_url('banking/' . (int) $account['id'] . '/transactions') ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-2">
                    <label for="transaction_date" class="form-label"><?= lang('Finance.colDate') ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?= esc(old('transaction_date', date('Y-m-d'))) ?>" required>
                </div>
                <div class="col-md-2">
                    <label for="amount" class="form-label"><?= lang('Finance.colAmount') ?> <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= esc(old('amount', '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="description" class="form-label"><?= lang('Finance.bankTxnDescription') ?></label>
                    <input type="text" class="form-control" id="description" name="description" value="<?= esc(old('description', '')) ?>" maxlength="255" placeholder="<?= esc(lang('Finance.colNotes'), 'attr') ?>">
                </div>
                <div class="col-md-2">
                    <label for="reference" class="form-label"><?= lang('Finance.bankTxnReference') ?></label>
                    <input type="text" class="form-control" id="reference" name="reference" value="<?= esc(old('reference', '')) ?>" maxlength="100">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('App.add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><?= lang('Finance.bankTransactions') ?></h6></div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <p class="text-muted mb-0"><?= lang('Finance.bankNoTransactions') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Finance.colDate') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.colAmount') ?></th>
                            <th scope="col"><?= lang('Finance.colNotes') ?></th>
                            <th scope="col"><?= lang('Finance.colReceiptNumber') ?></th>
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
                                <td><?= esc($txn['transaction_date']) ?></td>
                                <td class="text-end"><?= number_format((float) $txn['amount'], 2) ?></td>
                                <td><?= esc($txn['description'] ?? '—') ?></td>
                                <td><?= esc($txn['reference'] ?? '—') ?></td>
                                <td><span class="badge border border-<?= $txnColor ?> text-<?= $txnColor ?> fw-normal"><?= lang($txnLabelKey) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0"><?= lang('Finance.reconList') ?></h6></div>
    <div class="card-body">
        <?php if (!empty($canManage) && empty($openRecon)): ?>
            <form method="post" action="<?= base_url('banking/' . (int) $account['id'] . '/reconciliations') ?>" class="row g-2 align-items-end mb-3">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label for="statement_date" class="form-label"><?= lang('Finance.reconStatementDate') ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control<?= session('errors.statement_date') ? ' is-invalid' : '' ?>" id="statement_date" name="statement_date" value="<?= esc(old('statement_date', date('Y-m-d'))) ?>" required>
                    <?php if (session('errors.statement_date')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.statement_date')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="statement_balance" class="form-label"><?= lang('Finance.reconStatementBalance') ?> <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control<?= session('errors.statement_balance') ? ' is-invalid' : '' ?>" id="statement_balance" name="statement_balance" value="<?= esc(old('statement_balance', '')) ?>" required>
                    <?php if (session('errors.statement_balance')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.statement_balance')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-play-circle" aria-hidden="true"></i> <?= lang('Finance.reconStart') ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (empty($reconciliations)): ?>
            <p class="text-muted mb-0"><?= lang('Finance.bankNoReconciliations') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Finance.reconStatementDate') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.reconStatementBalance') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"><?= lang('Finance.reconClosedAt') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reconciliations as $recon): ?>
                            <?php
                            $reconOpen = ($recon['status'] ?? '') === 'open';
                            $reconColor = $reconOpen ? 'info' : 'success';
                            ?>
                            <tr>
                                <td><?= esc($recon['statement_date']) ?></td>
                                <td class="text-end"><?= number_format((float) $recon['statement_balance'], 2) ?></td>
                                <td><span class="badge border border-<?= $reconColor ?> text-<?= $reconColor ?> fw-normal"><?= lang($reconOpen ? 'Finance.reconStatusOpen' : 'Finance.reconStatusClosed') ?></span></td>
                                <td><?= esc($recon['closed_at'] ?? '—') ?></td>
                                <td class="text-end">
                                    <a href="<?= base_url('banking/reconciliations/' . (int) $recon['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                        <?= lang($reconOpen && !empty($canManage) ? 'Finance.reconContinue' : 'App.view') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= view('Finance\Views\Banking/_import_modal', ['accounts' => [$account], 'preselectedAccountId' => (int) $account['id']]) ?>

<?= $this->endSection() ?>
