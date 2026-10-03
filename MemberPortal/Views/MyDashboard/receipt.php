<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="container py-4">
    <div class="d-flex justify-content-start align-items-center gap-2 mb-3 no-print">
        <a href="<?= base_url('me') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('App.back') ?? 'Back') ?>
        </a>
        <button class="btn btn-outline-primary btn-sm ms-auto" onclick="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= esc(lang('Treasurer.printReceipt') ?? 'Print Receipt') ?>
        </button>
    </div>

    <div class="card shadow-sm" id="receipt-print-area">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h3 class="mb-1"><?= esc($parish['name'] ?? '') ?></h3>
                <?php if (!empty($parish['address'])): ?>
                    <p class="text-muted mb-0"><?= esc($parish['address']) ?></p>
                <?php endif; ?>
                <?php if (!empty($parish['city']) || !empty($parish['state']) || !empty($parish['zip'])): ?>
                    <p class="text-muted mb-0"><?= esc($parish['city'] ?? '') ?><?= !empty($parish['city']) && !empty($parish['state']) ? ', ' : '' ?><?= esc($parish['state'] ?? '') ?> <?= esc($parish['zip'] ?? '') ?></p>
                <?php endif; ?>
            </div>

            <hr>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <p class="mb-1"><strong><?= esc(lang('Finance.colReceiptNumber') ?? 'Receipt Number') ?>:</strong> <?= esc($receipt['receipt_number'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= esc(lang('Finance.colDate') ?? 'Date') ?>:</strong> <?= esc($donation['date'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= esc(lang('Finance.colDonor') ?? 'Donor') ?>:</strong> <?= esc($donor) ?></p>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <p class="mb-1"><strong><?= esc(lang('Treasurer.issuedAtLabel') ?? 'Issued At') ?>:</strong> <?= esc($receipt['issued_at'] ?? '') ?></p>
                    <p class="mb-1"><strong><?= esc(lang('Finance.colPaymentMethod') ?? 'Payment Method') ?>:</strong> <?= esc($donation['payment_method'] ?? '-') ?></p>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Finance.colCategory') ?? 'Category') ?></th>
                            <th scope="col"><?= esc(lang('Finance.colFund') ?? 'Fund') ?></th>
                            <th scope="col" class="text-end"><?= esc(lang('Finance.colAmount') ?? 'Amount') ?></th>
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
            </div>

            <?php if (!empty($donation['notes'])): ?>
                <p class="text-muted mt-3"><strong><?= esc(lang('Finance.colNotes') ?? 'Notes') ?>:</strong> <?= nl2br(esc($donation['notes'])) ?></p>
            <?php endif; ?>

            <div class="text-center mt-4 text-muted small">
                <p class="mb-0"><?= esc(lang('Treasurer.receiptFooter') ?? 'Thank you for your contribution.') ?></p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, .sidebar, .topbar, footer {
        display: none !important;
    }
    body {
        background: #fff !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?= $this->endSection() ?>
