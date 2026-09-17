<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2">
        <?php if (auth()->user()->can('expense.create')): ?>
            <a href="<?= base_url('expenses/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Finance.recordExpense') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php $request = service('request'); ?>

<?php
$locale = $request->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d H:i', $timestamp);
};
?>

<?php if (isset($summary)): ?>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card bg-danger text-white text-center">
                <div class="card-body">
                    <h6><?= lang('Finance.totalExpenses') ?></h6>
                    <h2 class="mb-0">$<?= number_format($summary['total'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#expensesFilterPanel"
            aria-expanded="<?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'true' : 'false' ?>"
            aria-controls="expensesFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="expensesFilterPanel" class="collapse <?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('expenses') ?>">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select class="form-select" name="fund_id">
                            <option value=""><?= lang('Finance.allFunds') ?></option>
                            <?php foreach (($funds ?? []) as $fund): ?>
                                <option value="<?= (int) $fund['id'] ?>" <?= (int) ($fund_id ?? 0) === (int) $fund['id'] ? 'selected' : '' ?>>
                                    <?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category">
                            <option value=""><?= lang('Finance.allCategories') ?></option>
                            <option value="utilities" <?= ($category ?? '') == 'utilities' ? 'selected' : '' ?>><?= lang('Finance.catUtilities') ?></option>
                            <option value="maintenance" <?= ($category ?? '') == 'maintenance' ? 'selected' : '' ?>><?= lang('Finance.catMaintenance') ?></option>
                            <option value="salaries" <?= ($category ?? '') == 'salaries' ? 'selected' : '' ?>><?= lang('Finance.catSalaries') ?></option>
                            <option value="ministry" <?= ($category ?? '') == 'ministry' ? 'selected' : '' ?>><?= lang('Finance.catMinistry') ?></option>
                            <option value="supplies" <?= ($category ?? '') == 'supplies' ? 'selected' : '' ?>><?= lang('Finance.catSupplies') ?></option>
                            <option value="other" <?= ($category ?? '') == 'other' ? 'selected' : '' ?>><?= lang('Finance.categoryOther') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="start_date" value="<?= esc($start_date ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="end_date" value="<?= esc($end_date ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter" aria-hidden="true"></i> <?= lang('App.filter') ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php
        $activeFilters = [];
        $selectedFundId = (int) ($fund_id ?? 0);
        $selectedCategory = (string) ($category ?? '');
        $selectedStart = (string) ($start_date ?? '');
        $selectedEnd = (string) ($end_date ?? '');

        $fundNameById = [];
        foreach (($funds ?? []) as $fund) {
            $fundNameById[(int) $fund['id']] = fund_name_label((string) ($fund['name'] ?? ''));
        }

        if ($selectedFundId > 0) {
            $activeFilters['fund_id'] = [
                'label' => lang('Treasurer.fundLabel') . ': ' . ($fundNameById[$selectedFundId] ?? ('#' . $selectedFundId)),
            ];
        }

        if ($selectedCategory !== '') {
            $categoryLabelMap = [
                'utilities' => lang('Finance.catUtilities'),
                'maintenance' => lang('Finance.catMaintenance'),
                'salaries' => lang('Finance.catSalaries'),
                'ministry' => lang('Finance.catMinistry'),
                'supplies' => lang('Finance.catSupplies'),
                'other' => lang('Finance.categoryOther'),
            ];
            $activeFilters['category'] = [
                'label' => lang('Finance.category') . ': ' . ($categoryLabelMap[$selectedCategory] ?? ucfirst($selectedCategory)),
            ];
        }

        if ($selectedStart !== '') {
            $activeFilters['start_date'] = [
                'label' => lang('Treasurer.fromLabel') . ': ' . $formatLocalizedDate($selectedStart, 'MMM d, y', $locale),
            ];
        }

        if ($selectedEnd !== '') {
            $activeFilters['end_date'] = [
                'label' => lang('Treasurer.toLabel') . ': ' . $formatLocalizedDate($selectedEnd, 'MMM d, y', $locale),
            ];
        }

        $baseFilterParams = [
            'fund_id' => $selectedFundId > 0 ? $selectedFundId : null,
            'category' => $selectedCategory !== '' ? $selectedCategory : null,
            'start_date' => $selectedStart !== '' ? $selectedStart : null,
            'end_date' => $selectedEnd !== '' ? $selectedEnd : null,
        ];
        $exportFilterParams = array_filter($baseFilterParams, static fn($v) => $v !== null && $v !== '');
        ?>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('expenses/export' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
                </a>
                <a href="<?= base_url('expenses/export-pdf' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.exportPdf') ?>
                </a>
            </div>
            <span class="text-muted small"><?= lang('Treasurer.exportsRespectFilters') ?></span>
        </div>

        <?php if (!empty($activeFilters)): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="text-muted small"><?= lang('Treasurer.activeFilters') ?></span>
                <?php foreach ($activeFilters as $filterKey => $filterMeta): ?>
                    <?php
                    $chipParams = $baseFilterParams;
                    $chipParams[$filterKey] = null;
                    $chipParams = array_filter($chipParams, static fn($v) => $v !== null && $v !== '');
                    ?>
                    <a href="<?= base_url('expenses' . (!empty($chipParams) ? '?' . http_build_query($chipParams) : '')) ?>"
                        class="badge rounded-pill text-bg-light border text-decoration-none">
                        <?= esc($filterMeta['label']) ?>
                        <span class="ms-1">x</span>
                    </a>
                <?php endforeach; ?>
                <a href="<?= base_url('expenses') ?>" class="btn btn-sm btn-outline-secondary"><?= lang('Finance.clearAll') ?></a>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Finance.colDate') ?></th>
                        <th scope="col"><?= lang('Finance.colDescription') ?></th>
                        <th scope="col"><?= lang('Finance.colFund') ?></th>
                        <th scope="col"><?= lang('Finance.colFundType') ?></th>
                        <th scope="col"><?= lang('Finance.colCategory') ?></th>
                        <th scope="col"><?= lang('Finance.colNotes') ?></th>
                        <th scope="col"><?= lang('Finance.colAmount') ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?= esc($formatLocalizedDate((string) ($expense['date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td><strong><?= esc(expense_description_label((string) ($expense['description'] ?? ''))) ?></strong></td>
                                <td><?= esc(fund_name_label((string) ($expense['fund_name'] ?? lang('Finance.unassigned')))) ?></td>
                                <td>
                                    <?php $fundType = (string) ($expense['fund_type'] ?? 'unrestricted'); ?>
                                    <?php $fundTypeLabelMap = ['restricted' => lang('Treasurer.fundTypeRestricted'), 'permanent' => lang('Treasurer.fundTypePermanent'), 'unrestricted' => lang('Treasurer.fundTypeUnrestricted')]; ?>
                                    <?php $fundBadgeColor = $fundType === 'restricted' ? 'warning' : ($fundType === 'permanent' ? 'secondary' : 'success'); ?>
                                    <span class="badge border border-<?= $fundBadgeColor ?> text-<?= $fundBadgeColor ?> fw-normal">
                                        <?= esc($fundTypeLabelMap[$fundType] ?? ucfirst($fundType)) ?>
                                    </span>
                                </td>
                                <?php $rowCategory = (string) ($expense['category'] ?? 'other'); ?>
                                <?php $rowCategoryLabelMap = ['utilities' => lang('Finance.catUtilities'), 'maintenance' => lang('Finance.catMaintenance'), 'salaries' => lang('Finance.catSalaries'), 'ministry' => lang('Finance.catMinistry'), 'supplies' => lang('Finance.catSupplies'), 'other' => lang('Finance.categoryOther')]; ?>
                                <?php $fallbackCategoryLabel = $rowCategoryLabelMap[$rowCategory] ?? ucfirst($rowCategory); ?>
                                <td><span class="badge border border-danger text-danger fw-normal"><?= esc(coa_category_label($rowCategory, $fallbackCategoryLabel)) ?></span></td>
                                <?php $rowNotes = (string) ($expense['notes'] ?? ''); ?>
                                <td><?= esc($rowNotes !== '' ? expense_notes_label($rowNotes) : '-') ?></td>
                                <td><strong>$<?= number_format($expense['amount'], 2) ?></strong></td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <?php if (auth()->user()->can('expense.edit')): ?>
                                            <a href="<?= base_url('expenses/edit/' . $expense['id']) ?>" class="btn btn-sm btn-warning" aria-label="<?= esc(lang('App.edit')) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (auth()->user()->can('expense.delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteExpense(<?= $expense['id'] ?>)" aria-label="<?= esc(lang('App.delete')) ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4"><?= lang('Treasurer.noExpensesFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($pager)): ?>
            <div class="mt-3"><?= $pager->links() ?></div>
        <?php endif; ?>
    </div>
</div>

<script>
    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    function deleteExpense(id) {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            '<?= esc(lang('Treasurer.expenseDeleteConfirm'), 'js') ?>',
            function() {
                fetch('<?= base_url('expenses/delete/') ?>' + id, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            showAppToast(contractErrorMessage(data, '<?= esc(lang('Treasurer.expenseDeleteFailed'), 'js') ?>'), 'danger');
                        }
                    })
                    .catch(() => {
                        showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                    });
            }
        );
    }
</script>

<?= $this->endSection() ?>