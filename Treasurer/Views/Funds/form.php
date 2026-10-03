<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables: $fund (array|null — null when creating), $typeChangeLocked (bool)
 */
$isEdit      = is_array($fund ?? null);
$action      = $isEdit ? base_url('funds/update/' . (int) $fund['id']) : base_url('funds/store');
$currentType = (string) old('fund_type', $fund['fund_type'] ?? 'unrestricted');
$typeOptions = [
    'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
    'restricted'   => lang('Treasurer.fundTypeRestricted'),
    'permanent'    => lang('Treasurer.fundTypePermanent'),
];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('funds') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label"><?= lang('Finance.fundName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= session('errors.name') ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= esc(old('name', $fund['name'] ?? '')) ?>" required minlength="3" maxlength="255">
                    <?php if (session('errors.name')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fund_type" class="form-label"><?= lang('Finance.fundType') ?> <span class="text-danger">*</span></label>
                    <select class="form-select<?= session('errors.fund_type') ? ' is-invalid' : '' ?>" id="fund_type" name="fund_type" required <?= !empty($typeChangeLocked) ? 'data-type-locked="1"' : '' ?>>
                        <?php foreach ($typeOptions as $value => $label): ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= $currentType === $value ? 'selected' : '' ?> <?= (!empty($typeChangeLocked) && $currentType !== $value) ? 'disabled' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($typeChangeLocked)): ?>
                        <?php /* Disabled <select> is not submitted, so keep the value in a hidden field. */ ?>
                        <input type="hidden" name="fund_type" value="<?= esc($currentType, 'attr') ?>" disabled data-type-hidden>
                        <div class="form-text"><?= lang('Finance.fundTypeChangeHelp') ?></div>
                    <?php endif; ?>
                    <?php if (session('errors.fund_type')): ?>
                        <div class="invalid-feedback d-block"><?= esc(session('errors.fund_type')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3" id="restrictionPurposeGroup">
                <label for="restriction_purpose" class="form-label"><?= lang('Finance.fundRestrictionPurpose') ?></label>
                <input type="text" class="form-control<?= session('errors.restriction_purpose') ? ' is-invalid' : '' ?>" id="restriction_purpose" name="restriction_purpose" value="<?= esc(old('restriction_purpose', $fund['restriction_purpose'] ?? '')) ?>" maxlength="255">
                <div class="form-text"><?= lang('Finance.fundPurposeHelp') ?></div>
                <?php if (session('errors.restriction_purpose')): ?>
                    <div class="invalid-feedback d-block"><?= esc(session('errors.restriction_purpose')) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="chart_account_id" class="form-label"><?= lang('Finance.fundChartAccount') ?></label>
                <select class="form-select<?= session('errors.chart_account_id') ? ' is-invalid' : '' ?>" id="chart_account_id" name="chart_account_id">
                    <option value=""><?= lang('Finance.fundChartAccountNone') ?></option>
                    <?php foreach (($chartAccounts ?? []) as $coa): ?>
                        <option value="<?= (int) $coa['id'] ?>" <?= (int) old('chart_account_id', $fund['chart_account_id'] ?? 0) === (int) $coa['id'] ? 'selected' : '' ?>>
                            <?= esc(($coa['code'] ?? '') . ' - ' . ($coa['name'] ?? '') . ' (' . ucfirst((string) ($coa['account_type'] ?? '')) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= lang('Finance.fundChartAccountHelp') ?></div>
                <?php if (session('errors.chart_account_id')): ?>
                    <div class="invalid-feedback d-block"><?= esc(session('errors.chart_account_id')) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label"><?= lang('Finance.fundDescription') ?></label>
                <textarea class="form-control<?= session('errors.description') ? ' is-invalid' : '' ?>" id="description" name="description" rows="3" maxlength="1000"><?= esc(old('description', $fund['description'] ?? '')) ?></textarea>
                <?php if (session('errors.description')): ?>
                    <div class="invalid-feedback"><?= esc(session('errors.description')) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-check-circle" aria-hidden="true"></i> <?= lang('App.save') ?>
            </button>
        </form>
    </div>
</div>

<script>
    (function () {
        var typeSelect = document.getElementById('fund_type');
        var group = document.getElementById('restrictionPurposeGroup');
        var purpose = document.getElementById('restriction_purpose');
        if (!typeSelect || !group || !purpose) {
            return;
        }

        // The disabled <select> is not submitted; enable the hidden twin instead.
        var hidden = document.querySelector('[data-type-hidden]');
        if (hidden && typeSelect.dataset.typeLocked === '1') {
            typeSelect.removeAttribute('name');
            hidden.disabled = false;
        }

        function sync() {
            var restricted = typeSelect.value !== 'unrestricted';
            group.classList.toggle('d-none', !restricted);
            purpose.required = restricted;
        }

        typeSelect.addEventListener('change', sync);
        sync();
    })();
</script>

<?= $this->endSection() ?>
