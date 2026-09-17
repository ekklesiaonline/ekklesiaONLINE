<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2">
        <?php if (auth()->user()->can('donation.create')): ?>
            <a href="<?= base_url('donations/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Finance.recordDonation') ?>
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

$donationCategoryLabelMap = [
    'tithe' => lang('Finance.categoryTithe'),
    'offering' => lang('Finance.categoryOffering'),
    'building fund' => lang('Finance.categoryBuilding'),
    'mission' => lang('Finance.categoryMission'),
    'other' => lang('Finance.categoryOther'),
];

$donationMethodLabelMap = [
    'cash'          => lang('Finance.methodCash'),
    'check'         => lang('Finance.methodCheck'),
    'credit_card'   => lang('Finance.methodCreditCard'),
    'bank_transfer' => lang('Finance.methodBankTransfer'),
    'online'        => lang('Finance.methodOnline'),
];
?>

<!-- Summary Cards -->
<?php if (isset($summary)): ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted"><?= lang('Finance.donationsTitle') ?></h6>
                    <h3 class="mb-0">$<?= number_format($summary['total'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted"><?= lang('Finance.categoryTithe') ?></h6>
                    <h4 class="mb-0">$<?= number_format($summary['tithe'] ?? 0, 2) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted"><?= lang('Finance.categoryOffering') ?></h6>
                    <h4 class="mb-0">$<?= number_format($summary['offering'] ?? 0, 2) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted"><?= lang('Finance.categoryOther') ?></h6>
                    <h4 class="mb-0">$<?= number_format($summary['other'] ?? 0, 2) ?></h4>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#donationsFilterPanel"
            aria-expanded="<?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'true' : 'false' ?>"
            aria-controls="donationsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="donationsFilterPanel" class="collapse <?= ($request->getGet('start_date') !== null || $request->getGet('end_date') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('donations') ?>">
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
                            <option value="tithe" <?= ($category ?? '') == 'tithe' ? 'selected' : '' ?>><?= lang('Finance.categoryTithe') ?></option>
                            <option value="offering" <?= ($category ?? '') == 'offering' ? 'selected' : '' ?>><?= lang('Finance.categoryOffering') ?></option>
                            <option value="building fund" <?= ($category ?? '') == 'building fund' ? 'selected' : '' ?>><?= lang('Finance.categoryBuilding') ?></option>
                            <option value="mission" <?= ($category ?? '') == 'mission' ? 'selected' : '' ?>><?= lang('Finance.categoryMission') ?></option>
                            <option value="other" <?= ($category ?? '') == 'other' ? 'selected' : '' ?>><?= lang('Finance.categoryOther') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="start_date" value="<?= esc($start_date ?? '') ?>" placeholder="<?= lang('Finance.startDate') ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="end_date" value="<?= esc($end_date ?? '') ?>" placeholder="<?= lang('Finance.endDate') ?>">
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

<!-- Donations Table -->
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
            $activeFilters['category'] = [
                'label' => lang('Treasurer.categoryLabel') . ': ' . ($donationCategoryLabelMap[$selectedCategory] ?? ucfirst($selectedCategory)),
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
                <a href="<?= base_url('donations/export' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
                </a>
                <a href="<?= base_url('donations/export-pdf' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= lang('Finance.exportPdf') ?>
                </a>
            </div>
            <span class="text-muted small"><?= lang('Treasurer.donationExportsRespectFilters') ?></span>
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
                    <a href="<?= base_url('donations' . (!empty($chipParams) ? '?' . http_build_query($chipParams) : '')) ?>"
                        class="badge rounded-pill text-bg-light border text-decoration-none">
                        <?= esc($filterMeta['label']) ?>
                        <span class="ms-1">x</span>
                    </a>
                <?php endforeach; ?>
                <a href="<?= base_url('donations') ?>" class="btn btn-sm btn-outline-secondary"><?= lang('Finance.clearAll') ?></a>
            </div>
        <?php endif; ?>

        <div class="card border mb-3">
            <div class="card-header bg-light">
                <strong><?= lang('Finance.contributionStatement') ?></strong>
            </div>
            <div class="card-body">
                <form method="get" action="<?= base_url('donations/statement') ?>" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="statement_member_id" class="form-label"><?= lang('Finance.memberLabel') ?></label>
                        <select name="statement_member_id" id="statement_member_id" class="form-select" required>
                            <option value=""><?= lang('Finance.selectMember') ?></option>
                            <?php foreach (($members ?? []) as $member): ?>
                                <?php $memberName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')); ?>
                                <option value="<?= (int) $member['user_id'] ?>" <?= (int) ($statement_member_id ?? 0) === (int) $member['user_id'] ? 'selected' : '' ?>>
                                    <?= esc($memberName !== '' ? $memberName : (lang('Finance.memberPrefix') . $member['user_id'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="statement_start_date" class="form-label"><?= lang('Finance.startDate') ?></label>
                        <input type="date" name="start_date" id="statement_start_date" class="form-control" value="<?= esc($start_date ?: date('Y-01-01')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="statement_end_date" class="form-label"><?= lang('Finance.endDate') ?></label>
                        <input type="date" name="end_date" id="statement_end_date" class="form-control" value="<?= esc($end_date ?: date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-receipt" aria-hidden="true"></i> <?= lang('Finance.generatePdf') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('App.colDate') ?></th>
                        <th scope="col"><?= lang('Finance.colDonor') ?></th>
                        <th scope="col"><?= lang('Finance.colFund') ?></th>
                        <th scope="col"><?= lang('Finance.colFundType') ?></th>
                        <th scope="col"><?= lang('Finance.colCategory') ?></th>
                        <th scope="col"><?= lang('Finance.colAmount') ?></th>
                        <th scope="col"><?= lang('Finance.colPaymentMethod') ?></th>
                        <th scope="col"><?= lang('Finance.colReceiptNumber') ?? 'Receipt' ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($donations)): ?>
                        <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><?= esc($formatLocalizedDate((string) ($donation['date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td><?= !empty($donation['user_id']) ? lang('Finance.memberPrefix') . esc((string) $donation['user_id']) : '<em>' . lang('Finance.anonymous') . '</em>' ?></td>
                                <td><?= esc(fund_name_label((string) ($donation['fund_name'] ?? lang('Finance.unassigned')))) ?></td>
                                <td>
                                    <?php $fundType = (string) ($donation['fund_type'] ?? 'unrestricted'); ?>
                                    <?php $fundTypeLabelMap = ['restricted' => lang('Treasurer.fundTypeRestricted'), 'permanent' => lang('Treasurer.fundTypePermanent'), 'unrestricted' => lang('Treasurer.fundTypeUnrestricted')]; ?>
                                    <?php $fundBadgeColor = $fundType === 'restricted' ? 'warning' : ($fundType === 'permanent' ? 'secondary' : 'success'); ?>
                                    <span class="badge border border-<?= $fundBadgeColor ?> text-<?= $fundBadgeColor ?> fw-normal">
                                        <?= esc($fundTypeLabelMap[$fundType] ?? ucfirst($fundType)) ?>
                                    </span>
                                </td>
                                <?php $rowCategory = (string) ($donation['category'] ?? 'other'); ?>
                                <?php $fallbackCategoryLabel = $donationCategoryLabelMap[$rowCategory] ?? ucfirst(str_replace('_', ' ', $rowCategory)); ?>
                                <td><span class="badge border border-primary text-primary fw-normal"><?= esc(coa_category_label($rowCategory, $fallbackCategoryLabel)) ?></span></td>
                                <td><strong>$<?= number_format($donation['amount'], 2) ?></strong></td>
                                <?php $rowMethod = (string) ($donation['payment_method'] ?? ''); ?>
                                <td><?= esc($rowMethod !== '' ? ($donationMethodLabelMap[$rowMethod] ?? $rowMethod) : '-') ?></td>
                                <td>
                                    <?php if (!empty($donation['receipt_number'])): ?>
                                        <a href="<?= base_url('donations/receipt/' . (int) $donation['id']) ?>" class="badge border border-success text-success text-decoration-none">
                                            <?= esc($donation['receipt_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <?php if (auth()->user()->can('donation.delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteDonation(<?= $donation['id'] ?>)" aria-label="<?= esc(lang('App.delete')) ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4"><?= lang('Finance.notFound') ?></td>
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
    function deleteDonation(id) {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            '<?= esc(lang('Finance.deleteConfirm'), 'js') ?>',
            function() {
                fetch('<?= base_url('donations/delete/') ?>' + id, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        location.reload();
                    });
            }
        );
    }
</script>

<?= $this->endSection() ?>