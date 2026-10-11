<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $entry: array with journal entry metadata, total_debit, total_credit, and lines
 * - $canManage: bool
 */
$sourceColorMap = [
    'donation' => 'success',
    'expense'  => 'danger',
    'bank'     => 'info',
    'manual'   => 'secondary',
];
$statusColorMap = [
    'posted' => 'success',
    'draft'  => 'warning',
    'void'   => 'danger',
];
$source = (string) ($entry['source_type'] ?? 'manual');
$sColor = $sourceColorMap[$source] ?? 'secondary';
$status = (string) ($entry['status'] ?? 'draft');
$stColor = $statusColorMap[$status] ?? 'secondary';
$isBalanced = abs(((float) ($entry['total_debit'] ?? 0.0)) - ((float) ($entry['total_credit'] ?? 0.0))) <= 0.005;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('journal-entries') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToJournalEntries') ?>
        </a>
    </div>
    <div class="d-flex gap-2">
        <span class="badge border border-<?= $sColor ?> text-<?= $sColor ?> fw-normal px-3 py-2 fs-6">
            <?= lang('Finance.colSource') ?>: <?= esc(ucfirst($source)) ?>
        </span>
        <span class="badge border border-<?= $stColor ?> text-<?= $stColor ?> fw-normal px-3 py-2 fs-6">
            <?= lang('Finance.colStatus') ?>: <?= esc(ucfirst($status)) ?>
        </span>
    </div>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-transparent py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 text-primary">
                    <i class="bi bi-journal-text me-2"></i><?= esc($entry['entry_number']) ?>
                </h4>
                <div class="text-muted small mt-1">
                    <?= lang('Finance.colEntryDate') ?>: <strong class="text-body"><?= esc($entry['entry_date']) ?></strong>
                    <?php if (!empty($entry['posted_at'])): ?>
                        &bull; <?= lang('Finance.entryPostedAt') ?>: <?= esc($entry['posted_at']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if ($isBalanced): ?>
                    <span class="badge border border-success text-success fw-normal px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i><?= lang('Finance.trialBalanceBalanced') ?>
                    </span>
                <?php else: ?>
                    <span class="badge border border-danger text-danger fw-normal px-3 py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i><?= lang('Finance.trialBalanceUnbalanced') ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($entry['memo'])): ?>
            <div class="mb-3">
                <span class="text-muted small d-block"><?= lang('Finance.colMemo') ?>:</span>
                <p class="mb-0 fs-6"><?= esc($entry['memo']) ?></p>
            </div>
        <?php endif; ?>

        <div class="table-responsive border rounded mt-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 5%;">#</th>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colAccountCode') ?></th>
                        <th scope="col" style="width: 35%;"><?= lang('Finance.colAccountName') ?></th>
                        <th scope="col" style="width: 15%;"><?= lang('Finance.colAccountType') ?></th>
                        <th scope="col" class="text-end" style="width: 15%;"><?= lang('Finance.colDebit') ?></th>
                        <th scope="col" class="text-end" style="width: 15%;"><?= lang('Finance.colCredit') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entry['lines'])): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No line items recorded.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entry['lines'] as $idx => $line): ?>
                            <tr>
                                <td class="text-muted small"><?= $idx + 1 ?></td>
                                <td class="font-monospace fw-semibold"><?= esc($line['account_code'] ?? '—') ?></td>
                                <td>
                                    <?= esc($line['account_name'] ?? '—') ?>
                                    <?php if (!empty($line['memo'])): ?>
                                        <div class="text-muted small"><?= esc($line['memo']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge border border-secondary text-secondary fw-normal">
                                        <?= esc(ucfirst((string) ($line['account_type'] ?? '—'))) ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= ((float) ($line['debit_amount'] ?? 0)) > 0 ? '$' . number_format((float) $line['debit_amount'], 2) : '—' ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= ((float) ($line['credit_amount'] ?? 0)) > 0 ? '$' . number_format((float) $line['credit_amount'], 2) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold border-top bg-body-tertiary">
                        <td colspan="4" class="py-3"><?= lang('Finance.reportTotal') ?></td>
                        <td class="text-end py-3 font-monospace text-primary fs-6">
                            $<?= number_format((float) ($entry['total_debit'] ?? 0), 2) ?>
                        </td>
                        <td class="text-end py-3 font-monospace text-primary fs-6">
                            $<?= number_format((float) ($entry['total_credit'] ?? 0), 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
