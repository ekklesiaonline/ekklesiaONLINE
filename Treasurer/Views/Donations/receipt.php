<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= esc($title ?? 'Donation Receipt') ?></h5>
            <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary btn-sm">
                <?= lang('App.back') ?? 'Back' ?>
            </a>
        </div>
        <div class="card-body" id="receipt-print-area">
            <div class="text-center mb-4">
                <h3 class="mb-1"><?= esc($parish['name'] ?? '') ?></h3>
                <p class="text-muted mb-0"><?= esc($parish['address'] ?? '') ?></p>
                <p class="text-muted mb-0"><?= esc($parish['city'] ?? '') ?>, <?= esc($parish['state'] ?? '') ?> <?= esc($parish['zip'] ?? '') ?></p>
            </div>

            <hr>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <p class="mb-1"><strong><?= lang('Finance.colReceiptNumber') ?? 'Receipt Number' ?>:</strong> <?= esc($receipt['receipt_number'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= lang('Finance.colDate') ?? 'Date' ?>:</strong> <?= esc($donation['date'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= lang('Finance.colDonor') ?? 'Donor' ?>:</strong> <?= esc($donor) ?></p>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <p class="mb-1"><strong><?= lang('Treasurer.issuedAtLabel') ?? 'Issued At' ?>:</strong> <?= esc($receipt['issued_at'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= lang('Finance.colPaymentMethod') ?? 'Payment Method' ?>:</strong> <?= esc($donation['payment_method'] ?? '-') ?></p>
                </div>
            </div>

            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th><?= lang('Finance.colCategory') ?? 'Category' ?></th>
                        <th><?= lang('Finance.colFund') ?? 'Fund' ?></th>
                        <th class="text-end"><?= lang('Finance.colAmount') ?? 'Amount' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= esc($donation['category'] ?? '-') ?></td>
                        <td><?= esc($donation['fund_name'] ?? '-') ?></td>
                        <td class="text-end">$<?= number_format((float) ($donation['amount'] ?? 0), 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($donation['notes'])): ?>
                <p class="text-muted mt-3"><strong><?= lang('Finance.colNotes') ?? 'Notes' ?>:</strong> <?= nl2br(esc($donation['notes'])) ?></p>
            <?php endif; ?>

            <div class="text-center mt-4 text-muted small">
                <p class="mb-0"><?= lang('Treasurer.receiptFooter') ?? 'Thank you for your contribution.' ?></p>
            </div>
        </div>
        <div class="card-footer text-end no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <?= lang('Treasurer.printReceipt') ?? 'Print Receipt' ?>
            </button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
