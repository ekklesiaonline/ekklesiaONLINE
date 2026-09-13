<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
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
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};
?>

<?php
$pledgeFiltersActive = trim((string) ($search ?? '')) !== ''
    || (int) ($per_page ?? 25) !== 25;
?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#pledgeFilterPanel"
            aria-expanded="<?= $pledgeFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="pledgeFilterPanel" class="collapse <?= $pledgeFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('pledges') ?>" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="year" class="form-label mb-1"><?= lang('Pledges.year') ?></label>
                    <input type="number" min="2000" class="form-control form-control-sm" id="year" name="year" value="<?= (int) ($selected_year ?? date('Y')) ?>">
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label mb-1"><?= lang('Pledges.searchMember') ?></label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?= esc($search ?? '') ?>" placeholder="<?= lang('Pledges.nameOrEmail') ?>">
                </div>
                <div class="col-md-2">
                    <label for="per_page" class="form-label mb-1"><?= lang('Pledges.perPage') ?></label>
                    <select class="form-select form-select-sm" id="per_page" name="per_page">
                        <?php foreach ([10, 25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= (int) ($per_page ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('pledges') ?>?year=<?= (int) ($selected_year ?? date('Y')) ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> <?= lang('Pledges.addAnnualPledge') ?></h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('pledges/store') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label"><?= lang('Pledges.member') ?></label>
                <select name="user_id" class="form-select" required>
                    <option value=""><?= lang('Pledges.selectMember') ?></option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= (int) $member['user_id'] ?>">
                            <?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                            <?php if (!empty($member['email'])): ?>
                                - <?= esc($member['email']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Pledges.year') ?></label>
                <input type="number" min="2000" name="pledge_year" class="form-control" value="<?= (int) ($selected_year ?? date('Y')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Pledges.pledged') ?></label>
                <input type="number" step="0.01" min="0" name="pledged_amount" class="form-control" placeholder="<?= lang('Pledges.zeroAmount') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Pledges.fulfilled') ?></label>
                <input type="number" step="0.01" min="0" name="fulfilled_amount" class="form-control" value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Pledges.status') ?></label>
                <select name="fulfillment_status" class="form-select" required>
                    <option value="pledged"><?= lang('Pledges.statusPledged') ?></option>
                    <option value="partial"><?= lang('Pledges.statusPartial') ?></option>
                    <option value="fulfilled"><?= lang('Pledges.statusFulfilled') ?></option>
                    <option value="cancelled"><?= lang('Pledges.statusCancelled') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Pledges.pledgedAt') ?></label>
                <input type="date" name="pledged_at" class="form-control">
            </div>
            <div class="col-md-7">
                <label class="form-label"><?= lang('Pledges.notes') ?></label>
                <input type="text" name="notes" class="form-control" placeholder="<?= lang('Pledges.optionalNote') ?>">
            </div>
            <div class="col-md-2 d-grid">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary"><?= lang('Pledges.savePledge') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> <?= lang('Pledges.pledgesForYear', [(int) ($selected_year ?? date('Y'))]) ?></h5>
        <span class="badge border border-secondary text-secondary fw-normal"><?= lang('Pledges.recordsCount', [(int) ($total_rows ?? count($pledges ?? []))]) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pledges)): ?>
            <p class="text-muted text-center py-4 mb-0"><?= lang('Pledges.noPledgesFound') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?= lang('Pledges.member') ?></th>
                            <th><?= lang('Pledges.year') ?></th>
                            <th class="text-end"><?= lang('Pledges.pledged') ?></th>
                            <th class="text-end"><?= lang('Pledges.fulfilled') ?></th>
                            <th><?= lang('Pledges.status') ?></th>
                            <th><?= lang('Pledges.pledgedAt') ?></th>
                            <th><?= lang('Pledges.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pledges as $pledge): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc(trim(($pledge['first_name'] ?? '') . ' ' . ($pledge['last_name'] ?? ''))) ?></div>
                                    <small class="text-muted"><?= esc($pledge['email'] ?? '-') ?></small>
                                </td>
                                <td><?= (int) ($pledge['pledge_year'] ?? 0) ?></td>
                                <td class="text-end">$<?= number_format((float) ($pledge['pledged_amount'] ?? 0), 2) ?></td>
                                <td class="text-end">$<?= number_format((float) ($pledge['fulfilled_amount'] ?? 0), 2) ?></td>
                                <td>
                                    <?php $pledgeStatusColor = ($pledge['fulfillment_status'] ?? '') === 'fulfilled' ? 'success' : (($pledge['fulfillment_status'] ?? '') === 'partial' ? 'warning' : (($pledge['fulfillment_status'] ?? '') === 'cancelled' ? 'danger' : 'secondary')); ?>
                                    <span class="badge border border-<?= $pledgeStatusColor ?> text-<?= $pledgeStatusColor ?> fw-normal">
                                        <?php $fulfillmentStatus = (string) ($pledge['fulfillment_status'] ?? 'pledged'); ?>
                                        <?= esc(lang('Pledges.status' . ucfirst($fulfillmentStatus))) ?>
                                    </span>
                                </td>
                                <td><?= esc($formatLocalizedDate((string) ($pledge['pledged_at'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td>
                                    <details>
                                        <summary class="btn btn-sm btn-outline-primary"><?= lang('Pledges.edit') ?></summary>
                                        <div class="mt-2">
                                            <form method="post" action="<?= base_url('pledges/update/' . (int) $pledge['id']) ?>" class="row g-2">
                                                <?= csrf_field() ?>
                                                <div class="col-6">
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="pledged_amount" value="<?= esc((string) ($pledge['pledged_amount'] ?? '')) ?>" placeholder="<?= lang('Pledges.pledged') ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="fulfilled_amount" value="<?= esc((string) ($pledge['fulfilled_amount'] ?? '0')) ?>" placeholder="<?= lang('Pledges.fulfilled') ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="date" class="form-control form-control-sm" name="pledged_at" value="<?= esc((string) ($pledge['pledged_at'] ?? '')) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <select name="fulfillment_status" class="form-select form-select-sm" required>
                                                        <?php foreach (['pledged', 'partial', 'fulfilled', 'cancelled'] as $status): ?>
                                                            <option value="<?= $status ?>" <?= ($pledge['fulfillment_status'] ?? '') === $status ? 'selected' : '' ?>><?= lang('Pledges.status' . ucfirst($status)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" class="form-control form-control-sm" name="notes" value="<?= esc((string) ($pledge['notes'] ?? '')) ?>" placeholder="<?= lang('Pledges.notes') ?>">
                                                </div>
                                                <div class="col-12 d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm btn-primary"><?= lang('Pledges.save') ?></button>
                                            </form>
                                            <form method="post" action="<?= base_url('pledges/delete/' . (int) $pledge['id']) ?>"
                                                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                data-confirm-body="<?= esc(lang('Pledges.deleteConfirm'), 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('Pledges.delete') ?></button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (($total_pages ?? 1) > 1): ?>
        <?php
        $currentPage = (int) ($page ?? 1);
        $query = [
            'year' => (int) ($selected_year ?? date('Y')),
            'search' => (string) ($search ?? ''),
            'per_page' => (int) ($per_page ?? 25),
        ];
        ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= lang('Pledges.pageOf', [$currentPage, (int) ($total_pages ?? 1)]) ?></small>
            <div class="btn-group">
                <?php if ($currentPage > 1): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('pledges') . '?' . http_build_query(array_merge($query, ['page' => $currentPage - 1])) ?>"><?= lang('Pledges.previous') ?></a>
                <?php endif; ?>
                <?php if ($currentPage < (int) ($total_pages ?? 1)): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('pledges') . '?' . http_build_query(array_merge($query, ['page' => $currentPage + 1])) ?>"><?= lang('Pledges.next') ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>