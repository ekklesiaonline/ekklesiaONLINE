<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('banking') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= base_url('banking/store') ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label"><?= lang('Finance.bankAccountName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= session('errors.name') ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= esc(old('name', '')) ?>" required maxlength="150">
                    <?php if (session('errors.name')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="institution_name" class="form-label"><?= lang('Finance.bankInstitution') ?></label>
                    <input type="text" class="form-control" id="institution_name" name="institution_name" value="<?= esc(old('institution_name', '')) ?>" maxlength="150">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="masked_account_number" class="form-label"><?= lang('Finance.bankAccountNumber') ?></label>
                    <input type="text" class="form-control" id="masked_account_number" name="masked_account_number" value="<?= esc(old('masked_account_number', '')) ?>" maxlength="34" placeholder="****1234">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="routing_last4" class="form-label"><?= lang('Finance.bankRoutingLast4') ?></label>
                    <input type="text" class="form-control<?= session('errors.routing_last4') ? ' is-invalid' : '' ?>" id="routing_last4" name="routing_last4" value="<?= esc(old('routing_last4', '')) ?>" maxlength="4" inputmode="numeric">
                    <?php if (session('errors.routing_last4')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.routing_last4')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="currency_code" class="form-label"><?= lang('Finance.bankCurrency') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= session('errors.currency_code') ? ' is-invalid' : '' ?>" id="currency_code" name="currency_code" value="<?= esc(old('currency_code', 'USD')) ?>" required maxlength="3">
                    <?php if (session('errors.currency_code')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.currency_code')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="opening_balance" class="form-label"><?= lang('Finance.bankOpeningBalance') ?></label>
                    <input type="number" step="0.01" class="form-control<?= session('errors.opening_balance') ? ' is-invalid' : '' ?>" id="opening_balance" name="opening_balance" value="<?= esc(old('opening_balance', '0.00')) ?>">
                    <?php if (session('errors.opening_balance')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.opening_balance')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="opened_on" class="form-label"><?= lang('Finance.bankOpenedOn') ?></label>
                    <input type="date" class="form-control<?= session('errors.opened_on') ? ' is-invalid' : '' ?>" id="opened_on" name="opened_on" value="<?= esc(old('opened_on', '')) ?>">
                    <?php if (session('errors.opened_on')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.opened_on')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-check-circle" aria-hidden="true"></i> <?= lang('App.save') ?>
            </button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
