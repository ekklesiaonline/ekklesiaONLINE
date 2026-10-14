<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('system/chart-of-accounts') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_back') ?></a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('system/chart-of-accounts/store') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="account_type" class="form-label"><?= lang('ChartAccounts.labelAccountType') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="account_type" name="account_type" required>
                            <option value=""><?= lang('ChartAccounts.selectAccountType') ?></option>
                            <option value="income"><?= lang('ChartAccounts.typeIncome') ?></option>
                            <option value="expense"><?= lang('ChartAccounts.typeExpense') ?></option>
                            <option value="asset"><?= lang('ChartAccounts.typeAsset') ?></option>
                            <option value="liability"><?= lang('ChartAccounts.typeLiability') ?></option>
                            <option value="equity"><?= lang('ChartAccounts.typeEquity') ?></option>
                        </select>
                        <div class="form-text"><?= lang('ChartAccounts.helpAccountType') ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="parent_account_id" class="form-label"><?= lang('ChartAccounts.labelParentAccount') ?></label>
                        <select class="form-select" id="parent_account_id" name="parent_account_id">
                            <option value=""><?= lang('ChartAccounts.selectParentAccount') ?></option>
                            <?php foreach (($parent_accounts ?? []) as $parent): ?>
                                <option value="<?= esc($parent['id']) ?>" data-account-type="<?= esc($parent['account_type']) ?>" data-code="<?= esc($parent['code']) ?>" data-category-id="<?= esc((string) ($parent['category_id'] ?? '')) ?>" data-category-path="<?= esc($parent['category_path'] ?? '') ?>" <?= (string) old('parent_account_id') === (string) $parent['id'] ? 'selected' : '' ?>>
                                    <?= esc($parent['code']) ?> - <?= esc($parent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= lang('ChartAccounts.helpParentAccount') ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label"><?= lang('ChartAccounts.labelCode') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" value="<?= old('code') ?>" required>
                        <div class="form-text"><?= lang('ChartAccounts.helpCode') ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label"><?= lang('ChartAccounts.labelName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" required>
                        <div class="form-text"><?= lang('ChartAccounts.helpName') ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <?= view('partials/_coa_category_hierarchy', ['account_categories' => $account_categories ?? [], 'selected_category_id' => '']) ?>
                    <div class="mb-3">
                        <label for="sort_order" class="form-label"><?= lang('ChartAccounts.labelSortOrder') ?></label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= old('sort_order', 0) ?>">
                        <div class="form-text"><?= lang('ChartAccounts.helpSortOrder') ?></div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="allow_budget" name="allow_budget" <?= old('allow_budget', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allow_budget"><?= lang('ChartAccounts.labelAllowBudgeting') ?></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= old('is_active', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><?= lang('ChartAccounts.labelActive') ?></label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label"><?= lang('ChartAccounts.labelDescription') ?></label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= old('description') ?></textarea>
                <div class="form-text"><?= lang('ChartAccounts.helpDescription') ?></div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= base_url('system/chart-of-accounts') ?>" class="btn btn-outline-secondary"><?= lang('ChartAccounts.btn_cancel') ?></a>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_createAccount') ?></button>
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
    const code = document.getElementById("code");

    const placeholderText = "<?= esc(lang('ChartAccounts.selectParentAccount')) ?>";

    const allParentAccounts = Array.from(parentAccount.querySelectorAll("option"))
        .filter(function (opt) {
            return opt.value !== "";
        })
        .map(function (opt) {
            return {
                value: opt.value,
                text: opt.textContent.trim(),
                accountType: opt.dataset.accountType || "",
                code: opt.dataset.code || "",
                categoryId: opt.dataset.categoryId || "",
                categoryPath: opt.dataset.categoryPath || ""
            };
        });

    let manualCodeValue = "";

    function filterParentAccounts(targetValue) {
        const selectedType = accountType.value;
        const desiredValue = targetValue !== undefined ? String(targetValue) : String(parentAccount.value || "");

        parentAccount.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = placeholderText;
        parentAccount.appendChild(placeholder);

        if (!selectedType) {
            parentAccount.disabled = true;
            parentAccount.value = "";
            return;
        }

        const matchingAccounts = allParentAccounts.filter(function (acc) {
            return acc.accountType === selectedType;
        });

        matchingAccounts.forEach(function (acc) {
            const opt = document.createElement("option");
            opt.value = acc.value;
            opt.textContent = acc.text;
            opt.dataset.accountType = acc.accountType;
            opt.dataset.code = acc.code;
            opt.dataset.categoryId = acc.categoryId;
            opt.dataset.categoryPath = acc.categoryPath;
            parentAccount.appendChild(opt);
        });

        parentAccount.disabled = false;

        if (desiredValue && matchingAccounts.some(function (acc) { return String(acc.value) === desiredValue; })) {
            parentAccount.value = desiredValue;
        } else {
            parentAccount.value = "";
        }
    }

    function updateCodePrefix() {
        if (!code) {
            return;
        }
        const selected = parentAccount.options[parentAccount.selectedIndex];
        const parentCode = selected && selected.value !== "" ? (selected.dataset.code || "") : "";
        const raw = code.value;

        if (parentCode === "") {
            return;
        }

        if (raw === "" || raw === manualCodeValue) {
            code.value = parentCode + "-";
        } else if (!raw.startsWith(parentCode + "-")) {
            code.value = parentCode + "-" + raw;
        }

        manualCodeValue = code.value;
    }

    accountType.addEventListener("change", function () {
        filterParentAccounts("");
        updateCodePrefix();
    });

    parentAccount.addEventListener("change", function () {
        updateCodePrefix();
    });

    code?.addEventListener("input", function () {
        manualCodeValue = code.value;
    });

    const initialParentId = "<?= esc((string) old('parent_account_id', '')) ?>";
    filterParentAccounts(initialParentId);
    updateCodePrefix();
});
</script>
<?= $this->endSection() ?>
