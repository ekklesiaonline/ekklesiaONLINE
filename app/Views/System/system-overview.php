<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDateTime = static function (?string $value, string $locale): string {
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
        $formatter->setPattern('MMM d, y h:mm a');
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d H:i', $timestamp);
};
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1\"><?= lang('Dashboard.systemOverviewTotalParishes') ?></h6>
                        <h4 class="mb-0\"><?= number_format((int) ($total_parishes ?? 0)) ?></h4>
                    </div>
                    <div class="text-primary fs-1">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.systemOverviewNetworkActivity') ?></h6>
                        <h4 class="mb-0"><?= number_format((int) ($recent_activity_count_7d ?? 0)) ?></h4>
                        <small class="text-muted"><?= lang('Dashboard.systemOverviewLast7Days') ?></small>
                    </div>
                    <div class="text-success fs-1">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.systemOverviewAuditEvents') ?></h6>
                        <h4 class="mb-0"><?= number_format((int) ($recent_audit_count_7d ?? 0)) ?></h4>
                        <small class="text-muted"><?= lang('Dashboard.systemOverviewLast7Days') ?></small>
                    </div>
                    <div class="text-warning fs-1">
                        <i class="bi bi-journal-text"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.systemOverviewTotalRecords') ?></h6>
                        <h4 class="mb-0"><?= number_format((int) ($system_health['total_records'] ?? 0)) ?></h4>
                        <small class="text-muted"><?= lang('Dashboard.systemOverviewAllTables') ?></small>
                    </div>
                    <div class="text-danger fs-1">
                        <i class="bi bi-database"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cpu"></i> <?= lang('Dashboard.systemOverviewHealthTitle') ?></h5>
            </div>
            <div class="card-body">
                <div class="mb-3 d-flex justify-content-between">
                    <span class="text-muted"><?= lang('Dashboard.systemOverviewTotalRecords') ?></span>
                    <strong><?= number_format((int) ($system_health['total_records'] ?? 0)) ?></strong>
                </div>
                <div class="mb-3 d-flex justify-content-between">
                    <span class="text-muted"><?= lang('Dashboard.systemOverviewDatabaseSize') ?></span>
                    <strong><?= esc((string) ($system_health['database_size_human'] ?? lang('Dashboard.systemOverviewUnavailable'))) ?></strong>
                </div>
                <div class="mb-3 d-flex justify-content-between">
                    <span class="text-muted"><?= lang('Dashboard.systemOverviewLogsSize') ?></span>
                    <strong><?= esc((string) ($system_health['logs_size_human'] ?? lang('Dashboard.systemOverviewZeroBytes'))) ?></strong>
                </div>
                <div class="mb-3 d-flex justify-content-between">
                    <span class="text-muted"><?= lang('Dashboard.systemOverviewPhpVersion') ?></span>
                    <strong><?= esc((string) ($system_health['php_version'] ?? PHP_VERSION)) ?></strong>
                </div>
                <hr>
                <h6 class="text-muted mb-2"><?= lang('Dashboard.systemOverviewLast24Hours') ?></h6>
                <div class="small">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= lang('Dashboard.systemOverviewTotalParishes') ?></span>
                        <strong><?= number_format((int) ($system_health['recent_24h']['parishes'] ?? 0)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= lang('Dashboard.systemOverviewAuditEvents') ?></span>
                        <strong><?= number_format((int) ($system_health['recent_24h']['audit_logs'] ?? 0)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= lang('Dashboard.systemOverviewDonations') ?></span>
                        <strong><?= number_format((int) ($system_health['recent_24h']['donations'] ?? 0)) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><?= lang('Dashboard.systemOverviewExpenses') ?></span>
                        <strong><?= number_format((int) ($system_health['recent_24h']['expenses'] ?? 0)) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> <?= lang('Dashboard.systemOverviewRecentActivity') ?></h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                    <?php if (!empty($recent_activity)): ?>
                        <?php foreach ($recent_activity as $item): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold"><?= esc((string) ($item['title'] ?? lang('Dashboard.systemOverviewActivityFallback'))) ?></span>
                                    <small class="text-muted"><?= esc($formatLocalizedDateTime((string) ($item['happened_at'] ?? ''), $locale)) ?></small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted\"><?= esc((string) ($item['parish_name'] ?? lang('Dashboard.systemOverviewNetworkFallback'))) ?></small>
                                    <?php if (isset($item['amount']) && $item['amount'] !== null): ?>
                                        <small class="text-success fw-semibold">$<?= number_format((float) $item['amount'], 2) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-muted"><?= lang('Dashboard.systemOverviewNoRecentActivity') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>