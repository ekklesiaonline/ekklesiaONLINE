<?= $this->extend('Diocesan\Views\layouts/main') ?>

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
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get());
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }
    return date('Y-m-d H:i', $timestamp);
};

$pagination = $pagination ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1];
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 25);
$total = (int) ($pagination['total'] ?? 0);
$lastPage = (int) ($pagination['last_page'] ?? 1);

$filters = $filters ?? ['activity_type' => 'all', 'parish_id' => 0, 'date_from' => '', 'date_to' => ''];
$parishOptions = $parish_options ?? [];
$activityTypeOptions = $activity_type_options ?? ['all' => lang('Dashboard.recentActivityTypeAll')];

$buildPageUrl = static function (int $targetPage, int $targetPerPage, array $filters): string {
    $query = [
        'page'          => $targetPage,
        'per_page'      => $targetPerPage,
        'activity_type' => (string) ($filters['activity_type'] ?? 'all'),
        'parish_id'     => (int) ($filters['parish_id'] ?? 0),
        'date_from'     => (string) ($filters['date_from'] ?? ''),
        'date_to'       => (string) ($filters['date_to'] ?? ''),
    ];
    return base_url('diocese/dashboard/recent-activity?' . http_build_query($query));
};

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = min($total, $page * $perPage);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-activity"></i> <?= lang('Dashboard.systemOverviewRecentActivity') ?></h4>
        <div class="text-muted small"><?= lang('Dashboard.dioceseWideScope') ?> · <?= number_format($from) ?>–<?= number_format($to) ?> / <?= number_format($total) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('diocese/dashboard') ?>"><?= lang('App.navDashboard') ?></a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('diocese/dashboard/recent-activity') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="1">

            <div class="col-md-3">
                <label class="form-label mb-1"><?= lang('Dashboard.recentActivityFilterTypeLabel') ?></label>
                <select name="activity_type" class="form-select form-select-sm">
                    <?php foreach ($activityTypeOptions as $value => $label): ?>
                        <option value="<?= esc((string) $value) ?>" <?= ((string) ($filters['activity_type'] ?? 'all') === (string) $value) ? 'selected' : '' ?>>
                            <?= esc((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1"><?= lang('Dashboard.recentActivityFilterParishLabel') ?></label>
                <select name="parish_id" class="form-select form-select-sm">
                    <option value="0" <?= ((int) ($filters['parish_id'] ?? 0) === 0) ? 'selected' : '' ?>><?= lang('Dashboard.recentActivityFilterAllParishes') ?></option>
                    <?php foreach ($parishOptions as $parishOption): ?>
                        <option value="<?= (int) ($parishOption['id'] ?? 0) ?>" <?= ((int) ($filters['parish_id'] ?? 0) === (int) ($parishOption['id'] ?? 0)) ? 'selected' : '' ?>>
                            <?= esc((string) $parishOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label mb-1"><?= lang('Dashboard.recentActivityFilterFromLabel') ?></label>
                <input type="date" name="date_from" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>" class="form-control form-control-sm">
            </div>

            <div class="col-md-2">
                <label class="form-label mb-1"><?= lang('Dashboard.recentActivityFilterToLabel') ?></label>
                <input type="date" name="date_to" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>" class="form-control form-control-sm">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><?= lang('Dashboard.recentActivityFilterApply') ?></button>
                <a href="<?= base_url('diocese/dashboard/recent-activity') ?>" class="btn btn-outline-secondary btn-sm w-100"><?= lang('Dashboard.recentActivityFilterReset') ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!empty($recent_activity)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recent_activity as $activity): ?>
                    <?php $liturgicalText = (string) ($activity['display_liturgical'] ?? '-'); ?>
                    <div class="list-group-item">
                        <div class="small text-muted">
                            <?= esc($formatLocalizedDate((string) ($activity['happened_at'] ?? ''), 'MMM d, y h:mm a', $locale)) ?>
                            – <?= esc((string) ($activity['parish_name'] ?? $activity['parish_name'] ?? lang('Dashboard.systemOverviewUnknownParish'))) ?>
                        </div>
                        <div class="small mt-1">
                            <span><?= esc((string) ($activity['display_subject'] ?? lang('Dashboard.systemOverviewActivityFallback'))) ?></span>
                            – <span class="<?= esc((string) ($activity['display_flow_class'] ?? 'text-secondary')) ?>"><?= esc((string) ($activity['display_flow'] ?? lang('Dashboard.systemOverviewFlowActivity'))) ?></span>
                            – <span class="<?= esc((string) ($activity['display_amount_class'] ?? 'text-secondary')) ?>"><?= esc((string) ($activity['display_amount'] ?? '-')) ?></span>
                            <?php if ($liturgicalText !== '' && $liturgicalText !== '-'): ?>
                                / <span class="badge fw-normal <?= esc((string) ($activity['display_liturgical_class'] ?? 'bg-light text-secondary border')) ?>"><?= esc($liturgicalText) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1"><?= esc((string) ($activity['title'] ?? lang('Dashboard.systemOverviewActivityFallback'))) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted"><?= lang('Dashboard.systemOverviewNoRecentActivity') ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <div class="btn-group btn-group-sm" role="group" aria-label="<?= esc(lang('Dashboard.recentActivityPerPageAria')) ?>">
        <?php foreach ([10, 25, 50, 100] as $size): ?>
            <a href="<?= $buildPageUrl(1, $size, $filters) ?>" class="btn <?= $perPage === $size ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $size ?></a>
        <?php endforeach; ?>
    </div>

    <div class="btn-group btn-group-sm" role="group" aria-label="<?= esc(lang('Dashboard.recentActivityPaginationAria')) ?>">
        <a href="<?= $buildPageUrl(max(1, $page - 1), $perPage, $filters) ?>" class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>">&laquo; <?= lang('Dashboard.recentActivityPrev') ?></a>
        <span class="btn btn-light disabled"><?= lang('Dashboard.recentActivityPageLabel') ?> <?= number_format($page) ?> / <?= number_format($lastPage) ?></span>
        <a href="<?= $buildPageUrl(min($lastPage, $page + 1), $perPage, $filters) ?>" class="btn btn-outline-secondary <?= $page >= $lastPage ? 'disabled' : '' ?>"><?= lang('Dashboard.recentActivityNext') ?> &raquo;</a>
    </div>
</div>

<?= $this->endSection() ?>
