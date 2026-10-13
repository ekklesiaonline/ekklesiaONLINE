<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('finance/chart-of-accounts') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('finance/chart-of-accounts/update/' . $account['id']) ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="code" class="form-label"><?= lang('ChartAccounts.labelCode') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" value="<?= esc(old('code', $account['code'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label"><?= lang('ChartAccounts.labelName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= esc(old('name', $account['name'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="account_type" class="form-label"><?= lang('ChartAccounts.labelAccountType') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="account_type" name="account_type" required>
                            <?php foreach ([
                                'income'    => lang('ChartAccounts.typeIncome'),
                                'expense'   => lang('ChartAccounts.typeExpense'),
                                'asset'     => lang('ChartAccounts.typeAsset'),
                                'liability' => lang('ChartAccounts.typeLiability'),
                                'equity'    => lang('ChartAccounts.typeEquity'),
                            ] as $val => $label): ?>
                                <option value="<?= esc($val) ?>" <?= old('account_type', $account['account_type']) === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="parent_account_id" class="form-label"><?= lang('ChartAccounts.labelParentAccount') ?></label>
                        <select class="form-select" id="parent_account_id" name="parent_account_id">
                            <option value=""><?= lang('ChartAccounts.selectParentAccount') ?></option>
                            <?php foreach (($parent_accounts ?? []) as $parent): ?>
                                <option value="<?= esc($parent['id']) ?>" data-account-type="<?= esc($parent['account_type']) ?>" data-code="<?= esc($parent['code']) ?>" <?= (string) old('parent_account_id', (string) ($account['parent_account_id'] ?? '')) === (string) $parent['id'] ? 'selected' : '' ?>>
                                    <?= esc($parent['code']) ?> - <?= esc($parent['name']) ?> (<?= esc(ucfirst($parent['scope_type'] ?? 'system')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="category" class="form-label"><?= lang('ChartAccounts.labelDefaultCategory') ?></label>
                        <input type="text" class="form-control" id="category" name="category" value="<?= esc(old('category', $account['category'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="sort_order" class="form-label"><?= lang('ChartAccounts.labelSortOrder') ?></label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= esc((string) old('sort_order', $account['sort_order'] ?? 0)) ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="allow_budget" name="allow_budget" <?= old('allow_budget', (string) ($account['allow_budget'] ?? 0)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allow_budget"><?= lang('ChartAccounts.labelAllowBudgeting') ?></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= old('is_active', (string) ($account['is_active'] ?? 0)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><?= lang('ChartAccounts.labelActive') ?></label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label"><?= lang('ChartAccounts.labelDescription') ?></label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= esc(old('description', $account['description'] ?? '')) ?></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= base_url('finance/chart-of-accounts') ?>" class="btn btn-outline-secondary"><?= lang('ChartAccounts.btn_cancel') ?></a>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_updateAccount') ?></button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const accountType = document.getElementById("account_type");
    const parentAccount = document.getElementById("parent_account_id");

    const placeholderText = "<?= esc(lang('ChartAccounts.selectParentAccount')) ?>";

    // Cache all parent account records from initial DOM options
    const allParentAccounts = Array.from(parentAccount.querySelectorAll("option"))
        .filter(function (opt) {
            return opt.value !== "";
        })
        .map(function (opt) {
            return {
                value: opt.value,
                text: opt.textContent.trim(),
                accountType: opt.dataset.accountType || "",
                code: opt.dataset.code || ""
            };
        });

    function filterParentAccounts(targetValue) {
        const selectedType = accountType.value;
        const desiredValue = targetValue !== undefined ? String(targetValue) : String(parentAccount.value || "");

        // Empty select entirely so non-matching options are not rendered as dimmed/disabled
        parentAccount.innerHTML = "";

        // Add default placeholder
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = placeholderText;
        parentAccount.appendChild(placeholder);

        if (!selectedType) {
            parentAccount.disabled = true;
            parentAccount.value = "";
            return;
        }

        // Only append options that match the selected account type
        const matchingAccounts = allParentAccounts.filter(function (acc) {
            return acc.accountType === selectedType;
        });

        matchingAccounts.forEach(function (acc) {
            const opt = document.createElement("option");
            opt.value = acc.value;
            opt.textContent = acc.text;
            opt.dataset.accountType = acc.accountType;
            opt.dataset.code = acc.code;
            parentAccount.appendChild(opt);
        });

        parentAccount.disabled = false;

        // Restore selected value if it exists in the matching list
        if (desiredValue && matchingAccounts.some(function (acc) { return String(acc.value) === desiredValue; })) {
            parentAccount.value = desiredValue;
        } else {
            parentAccount.value = "";
        }
    }

    accountType.addEventListener("change", function () {
        filterParentAccounts("");
    });

    const initialParentId = "<?= esc((string) old('parent_account_id', (string) ($account['parent_account_id'] ?? ''))) ?>";
    filterParentAccounts(initialParentId);
});
</script>
<?= $this->endSection() ?>
