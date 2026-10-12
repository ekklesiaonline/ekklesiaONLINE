<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('chart-accounts') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_back') ?></a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('chart-accounts/store') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3"><label for="code" class="form-label"><?= lang('ChartAccounts.labelCode') ?> <span class="text-danger">*</span></label><input type="text" class="form-control" id="code" name="code" value="<?= old('code') ?>" required></div>
                    <div class="mb-3"><label for="name" class="form-label"><?= lang('ChartAccounts.labelName') ?> <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" required></div>
                    <div class="mb-3"><label for="account_type" class="form-label"><?= lang('ChartAccounts.labelAccountType') ?> <span class="text-danger">*</span></label><select class="form-select" id="account_type" name="account_type" required>
                            <option value="income"><?= lang('ChartAccounts.typeIncome') ?></option>
                            <option value="expense"><?= lang('ChartAccounts.typeExpense') ?></option>
                            <option value="asset"><?= lang('ChartAccounts.typeAsset') ?></option>
                            <option value="liability"><?= lang('ChartAccounts.typeLiability') ?></option>
                            <option value="equity"><?= lang('ChartAccounts.typeEquity') ?></option>
                        </select></div>
                    <div class="mb-3">
                        <label for="parent_account_id" class="form-label"><?= lang('ChartAccounts.labelParentAccount') ?></label>
                        <select class="form-select" id="parent_account_id" name="parent_account_id">
                            <option value="">-- None (Standalone) --</option>
                            <?php foreach (($parent_accounts ?? []) as $parent): ?>
                                <option value="<?= esc($parent['id']) ?>" <?= (string) old('parent_account_id') === (string) $parent['id'] ? 'selected' : '' ?>>
                                    <?= esc($parent['code']) ?> - <?= esc($parent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3"><label for="category" class="form-label"><?= lang('ChartAccounts.labelDefaultCategory') ?></label><input type="text" class="form-control" id="category" name="category" value="<?= old('category') ?>"></div>
                    <div class="mb-3"><label for="sort_order" class="form-label"><?= lang('ChartAccounts.labelSortOrder') ?></label><input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= old('sort_order', 0) ?>"></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" value="1" id="allow_budget" name="allow_budget" <?= old('allow_budget', '1') ? 'checked' : '' ?>><label class="form-check-label" for="allow_budget"><?= lang('ChartAccounts.labelAllowBudgeting') ?></label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= old('is_active', '1') ? 'checked' : '' ?>><label class="form-check-label" for="is_active"><?= lang('ChartAccounts.labelActive') ?></label></div>
                </div>
            </div>
            <div class="mb-3"><label for="description" class="form-label"><?= lang('ChartAccounts.labelDescription') ?></label><textarea class="form-control" id="description" name="description" rows="3"><?= old('description') ?></textarea></div>
            <div class="d-flex justify-content-end gap-2"><a href="<?= base_url('chart-accounts') ?>" class="btn btn-outline-secondary"><?= lang('ChartAccounts.btn_cancel') ?></a><button type="submit" class="btn btn-outline-primary"><i class="bi bi-save" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_createAccount') ?></button></div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>