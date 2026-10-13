<?php
$selectedCategoryId = (string) old('category_id', (string) ($selected_category_id ?? ''));
?>
<div class="mb-3">
    <label for="category_id" class="form-label"><?= lang('ChartAccounts.labelDefaultCategory') ?> <span class="text-danger">*</span></label>
    <select class="form-select" id="category_id" name="category_id" required disabled>
        <option value=""><?= lang('ChartAccounts.selectCategory') ?></option>
        <?php foreach (($account_categories ?? []) as $category): ?>
            <?php if (!empty($category['is_selectable'])): ?>
                <option value="<?= (int) $category['id'] ?>" data-account-type="<?= esc($category['account_type']) ?>" data-path="<?= esc($category['path']) ?>" data-key="<?= esc($category['category_key']) ?>" <?= $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?>>
                    <?= esc($category['path']) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <div class="form-text"><?= lang('ChartAccounts.helpCategorySuggestion') ?></div>
</div>
<div class="mb-3">
    <label for="parent-category-path" class="form-label"><?= lang('ChartAccounts.labelParentCategory') ?></label>
    <input type="text" class="form-control" id="parent-category-path" value="" readonly>
    <div class="form-text"><?= lang('ChartAccounts.helpParentCategory') ?></div>
</div>
<details class="border rounded p-3 mb-3">
    <summary class="fw-semibold"><?= lang('ChartAccounts.categoryHierarchyReference') ?></summary>
    <div class="mt-3 small" id="category-hierarchy-reference">
        <?php foreach (($account_categories ?? []) as $category): ?>
            <?php $depth = substr_count((string) $category['category_key'], '.'); ?>
            <div class="py-1" data-account-type="<?= esc($category['account_type']) ?>" data-category-reference="<?= (int) $category['id'] ?>" style="padding-left: <?= $depth * 1.25 ?>rem">
                <?= esc($category['label']) ?><?= empty($category['is_selectable']) ? ' — ' . esc(lang('ChartAccounts.categoryGroup')) : '' ?>
            </div>
        <?php endforeach; ?>
    </div>
</details>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const accountType = document.getElementById("account_type");
    const parentAccount = document.getElementById("parent_account_id");
    const category = document.getElementById("category_id");
    const parentPath = document.getElementById("parent-category-path");
    const code = document.getElementById("code");
    const initialCategoryId = "<?= esc($selectedCategoryId) ?>";
    const categoryOptions = Array.from(category.options).filter(function (option) { return option.value !== ""; }).map(function (option) {
        return { value: option.value, text: option.textContent.trim(), accountType: option.dataset.accountType, path: option.dataset.path, key: option.dataset.key };
    });

    function renderCategories(suggestedId) {
        const type = accountType.value;
        const current = category.value || initialCategoryId;
        category.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "<?= esc(lang('ChartAccounts.selectCategory')) ?>";
        category.appendChild(placeholder);
        categoryOptions.filter(function (item) { return item.accountType === type; }).forEach(function (item) {
            const option = document.createElement("option");
            option.value = item.value;
            option.textContent = item.text;
            option.dataset.path = item.path;
            option.dataset.key = item.key;
            category.appendChild(option);
        });
        category.disabled = !type;
        const preferred = suggestedId || current;
        if (preferred && Array.from(category.options).some(function (option) { return option.value === String(preferred); })) {
            category.value = String(preferred);
        }
        document.querySelectorAll("[data-category-reference]").forEach(function (row) {
            row.classList.toggle("d-none", row.dataset.accountType !== type);
            row.classList.toggle("fw-bold", row.dataset.categoryReference === category.value);
        });
    }

    function suggestedKeyFromCode() {
        const number = parseInt((code?.value || "").replace(/\D.*$/, ""), 10) || 0;
        if (accountType.value === "asset") {
            if ([1230, 1525, 1535, 1545].includes(number)) return "asset.contra";
            if (number === 1550) return "asset.fixed.construction";
            if (number >= 1540) return "asset.fixed.equipment";
            if (number >= 1500) return "asset.fixed.land_buildings";
            if (number >= 1400) return "asset.investments";
            if (number >= 1300) return "asset.current.prepaid";
            return number >= 1200 ? "asset.current.receivables" : "asset.current.cash";
        }
        if (accountType.value === "liability") return number >= 2400 ? "liability.long_term.debt" : (number >= 2300 ? "liability.current.deferred" : (number >= 2200 ? "liability.current.payroll" : "liability.current.payables"));
        if (accountType.value === "equity") return number >= 3400 ? "equity.with_restrictions.perpetual" : (number >= 3300 ? "equity.with_restrictions.purpose_time" : (number >= 3200 ? "equity.without_restrictions.board_designated" : (number === 3120 ? "equity.without_restrictions.plant" : "equity.without_restrictions.undesignated")));
        if (accountType.value === "income") {
            if (number >= 5600) return "income.with_restrictions.release";
            if ([5510, 5520].includes(number)) return "income.with_restrictions.investment_gain";
            if (number >= 5500) return "income.with_restrictions.grants";
            if (number === 5410) return "income.with_restrictions.endowment";
            if (number >= 5400) return "income.with_restrictions.transfer";
            if (number >= 5300) return "income.with_restrictions.grants";
            if (number >= 5100) return number === 5110 ? "income.with_restrictions.capital" : "income.with_restrictions.designated";
            if (number >= 4600) return "income.without_restrictions.other";
            if (number >= 4500) return "income.without_restrictions.grants";
            if (number >= 4400) return "income.without_restrictions.investment";
            if (number >= 4300) return "income.without_restrictions.fundraising";
            return number >= 4200 ? "income.without_restrictions.rental" : "income.without_restrictions.contributions";
        }
        if (accountType.value === "expense") {
            if (number >= 8700) return "expense.capital.depreciation";
            if (number === 8630) return "expense.capital.debt_principal";
            if (number >= 8600) return "expense.capital.expenditures";
            if (number >= 8500) return "expense.management.professional";
            if (number >= 8400) return "expense.management.administration";
            if (number >= 8300) return "expense.management.insurance";
            if (number >= 8200) return "expense.management.facilities";
            if (number >= 8100) return "expense.management.utilities";
            if (number >= 7600) return "expense.management.governance";
            if (number >= 7500) return "expense.management.payroll";
            if (number >= 7400) return "expense.program.formation";
            if (number >= 7300) return "expense.program.worship";
            if (number >= 7200) return "expense.management.lay_personnel";
            if (number >= 7100) return "expense.program.clergy";
            if (number === 6230) return "expense.program.benevolence";
            return number >= 6200 ? "expense.program.outreach" : "expense.program.diocesan";
        }
        return "";
    }

    function suggestFromParent() {
        const selected = parentAccount.options[parentAccount.selectedIndex];
        parentPath.value = selected && selected.dataset.categoryPath ? selected.dataset.categoryPath : "<?= esc(lang('ChartAccounts.noParentCategory')) ?>";
        let suggestedId = selected && selected.dataset.categoryId ? selected.dataset.categoryId : "";
        if (!suggestedId) {
            const suggestion = categoryOptions.find(function (item) { return item.key === suggestedKeyFromCode(); });
            suggestedId = suggestion ? suggestion.value : "";
        }
        renderCategories(suggestedId);
    }

    accountType.addEventListener("change", function () { renderCategories(""); });
    parentAccount.addEventListener("change", suggestFromParent);
    code?.addEventListener("blur", suggestFromParent);
    category.addEventListener("change", function () { renderCategories(category.value); });
    setTimeout(suggestFromParent, 0);
});
</script>
