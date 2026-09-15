<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<style>
.audit-action-badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.audit-action-icon {
    font-size: 0.875rem;
}

.audit-row:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.audit-security-event {
    background-color: rgba(255, 193, 7, 0.1);
}

.audit-security-event:hover {
    background-color: rgba(255, 193, 7, 0.2);
}

.diff-table td {
    vertical-align: top;
}

.diff-value-added {
    color: var(--bs-success-text-emphasis);
    font-weight: 500;
}

.diff-value-removed {
    color: var(--bs-danger-text-emphasis);
    font-weight: 500;
}

.diff-value-changed {
    color: var(--bs-warning-text-emphasis);
    font-weight: 500;
}
</style>

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

$stringifyValue = static function ($value): string {
    if ($value === null) {
        return '-';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '-' : $json;
};
?>

<?php
$exportFilterParams = array_filter([
    'parish_id' => ((int) ($filter_parish_id ?? 0)) > 0 ? (int) ($filter_parish_id ?? 0) : null,
    'action' => (($filter_action ?? null) !== null && trim((string) $filter_action) !== '') ? (string) $filter_action : null,
    'entity_type' => (($filter_entity_type ?? null) !== null && trim((string) $filter_entity_type) !== '') ? (string) $filter_entity_type : null,
    'actor_name' => (($filter_actor_name ?? null) !== null && trim((string) $filter_actor_name) !== '') ? (string) $filter_actor_name : null,
    'quick_filter' => (($filter_quick_filter ?? null) !== null && trim((string) $filter_quick_filter) !== '') ? (string) $filter_quick_filter : null,
    'start_date' => (($filter_start_date ?? null) !== null && trim((string) $filter_start_date) !== '') ? (string) $filter_start_date : null,
    'end_date' => (($filter_end_date ?? null) !== null && trim((string) $filter_end_date) !== '') ? (string) $filter_end_date : null,
], static fn($v) => $v !== null && $v !== '');
?>

<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="dropdown">
        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> <?= lang('App.auditExport') ?>
        </button>
        <ul class="dropdown-menu">
            <li>
                <a class="dropdown-item" href="<?= base_url('admin/audit-logs/export-csv' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>">
                    <i class="bi bi-filetype-csv me-2"></i> <?= lang('App.auditExportCsv') ?>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="<?= base_url('admin/audit-logs/export-json' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>">
                    <i class="bi bi-filetype-json me-2"></i> <?= lang('App.auditExportJson') ?>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="<?= base_url('admin/audit-logs/export-pdf' . (!empty($exportFilterParams) ? '?' . http_build_query($exportFilterParams) : '')) ?>">
                    <i class="bi bi-filetype-pdf me-2"></i> <?= lang('App.auditExportPdf') ?>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Statistics Dashboard -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted"><?= lang('App.auditStatsTodayEvents') ?></h6>
                <div class="card-title h2 mb-0" id="statsTodayEvents">-</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted"><?= lang('App.auditStatsSecurityEvents') ?></h6>
                <div class="card-title h2 mb-0 text-warning" id="statsSecurityEvents">-</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted"><?= lang('App.auditStatsMostActiveUsers') ?></h6>
                <ul class="list-unstyled mb-0" id="statsActiveUsers" data-no-data="<?= lang('App.auditNoData') ?>">
                    <li class="text-muted"><?= lang('App.auditLoading') ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted"><?= lang('App.auditStatsTopOperations') ?></h6>
                <ul class="list-unstyled mb-0" id="statsTopOperations" data-no-data="<?= lang('App.auditNoData') ?>">
                    <li class="text-muted"><?= lang('App.auditLoading') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <strong><?= lang('App.auditActivityChart') ?></strong>
                    <span id="liveIndicator" class="badge bg-success ms-2" style="display: none;">
                        <i class="bi bi-circle-fill" style="font-size: 8px;"></i> <?= lang('App.auditLive') ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button id="toggleLiveBtn" class="btn btn-sm btn-outline-secondary" data-enable-live="<?= lang('App.auditEnableLive') ?>" data-disable-live="<?= lang('App.auditDisableLive') ?>">
                        <i class="bi bi-play-circle"></i> <?= lang('App.auditEnableLive') ?>
                    </button>
                    <select id="timeRangeSelector" class="form-select form-select-sm" style="width: auto;">
                        <option value="24h"><?= lang('App.auditLast24Hours') ?></option>
                        <option value="7d"><?= lang('App.auditLast7Days') ?></option>
                        <option value="30d"><?= lang('App.auditLast30Days') ?></option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <canvas id="activityChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($audit_table_missing)): ?>
    <div class="alert alert-warning" role="alert">
        <?= lang('App.auditLogsTableMissingHelp') ?>
    </div>
<?php endif; ?>

<?php
$auditFiltersActive = ((int) ($filter_parish_id ?? 0) > 0)
    || trim((string) ($filter_action ?? '')) !== ''
    || trim((string) ($filter_entity_type ?? '')) !== ''
    || trim((string) ($filter_actor_name ?? '')) !== ''
    || trim((string) ($filter_quick_filter ?? '')) !== ''
    || trim((string) ($filter_start_date ?? '')) !== ''
    || trim((string) ($filter_end_date ?? '')) !== '';
?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#auditLogFilterPanel"
            aria-expanded="<?= $auditFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="auditLogFilterPanel" class="collapse <?= $auditFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('admin/audit-logs') ?>" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('App.navParishes') ?></label>
                    <select name="parish_id" class="form-select form-select-sm">
                        <option value="0"><?= lang('App.all') ?></option>
                        <?php foreach (($audit_parishes ?? []) as $parish): ?>
                            <option value="<?= (int) ($parish['id'] ?? 0) ?>" <?= ((int) ($filter_parish_id ?? 0) === (int) ($parish['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= esc((string) $parish['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('App.colActions') ?></label>
                    <select name="action" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?></option>
                        <?php foreach (($audit_actions ?? []) as $action): ?>
                            <option value="<?= esc($action) ?>" <?= ((string) ($filter_action ?? '') === (string) $action) ? 'selected' : '' ?>>
                                <?= esc($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('App.auditFilterEntityType') ?></label>
                    <select name="entity_type" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?></option>
                        <?php foreach (($audit_entity_types ?? []) as $entityType): ?>
                            <option value="<?= esc($entityType) ?>" <?= ((string) ($filter_entity_type ?? '') === (string) $entityType) ? 'selected' : '' ?>>
                                <?= esc($entityType) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('App.auditFilterActorName') ?></label>
                    <input type="text" name="actor_name" class="form-control form-control-sm" value="<?= esc((string) ($filter_actor_name ?? '')) ?>" placeholder="<?= lang('App.auditFilterActorName') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('App.filter') ?></label>
                    <select name="quick_filter" class="form-select form-select-sm">
                        <option value=""><?= lang('App.auditFilterAll') ?></option>
                        <option value="system_ops" <?= ((string) ($filter_quick_filter ?? '') === 'system_ops') ? 'selected' : '' ?>><?= lang('App.auditQuickFilterSystemOps') ?></option>
                        <option value="security_events" <?= ((string) ($filter_quick_filter ?? '') === 'security_events') ? 'selected' : '' ?>><?= lang('App.auditQuickFilterSecurityEvents') ?></option>
                        <option value="data_changes" <?= ((string) ($filter_quick_filter ?? '') === 'data_changes') ? 'selected' : '' ?>><?= lang('App.auditQuickFilterDataChanges') ?></option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1"><?= lang('Treasurer.fromLabel') ?></label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= esc((string) ($filter_start_date ?? '')) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1"><?= lang('Treasurer.toLabel') ?></label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= esc((string) ($filter_end_date ?? '')) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-search"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('admin/audit-logs') ?>" class="btn btn-sm btn-outline-secondary flex-fill">
                        <?= lang('App.clear') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong><?= lang('App.navAuditLogs') ?></strong>
    </div>
    <div class="card-body p-0">
        <?php if (empty($audit_rows)): ?>
            <div class="p-3 text-muted"><?= lang('App.noRecords') ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('App.colDate') ?></th>
                            <th><?= lang('App.navParishes') ?></th>
                            <th><?= lang('App.colName') ?></th>
                            <th><?= lang('App.colActions') ?></th>
                            <th><?= lang('App.auditReason') ?></th>
                            <th><?= lang('App.auditEntity') ?></th>
                            <th><?= lang('App.auditIp') ?></th>
                            <th><?= lang('App.auditChanges') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_rows as $row): ?>
                            <tr class="<?= ($row['is_security_event'] ?? false) ? 'table-warning' : '' ?>" data-audit-id="<?= (int) ($row['id'] ?? 0) ?>">
                                <td><?= esc($formatLocalizedDateTime((string) ($row['created_at'] ?? ''), $locale)) ?></td>
                                <td><?= esc((string) ($row['parish_name'] ?? '-')) ?></td>
                                <td>
                                    <?php $actorName = trim((string) (($row['actor_first_name'] ?? '') . ' ' . ($row['actor_last_name'] ?? ''))); ?>
                                    <?= esc($actorName !== '' ? $actorName : lang('App.auditUserFallback', [(int) ($row['user_id'] ?? 0)])) ?>
                                </td>
                                <td>
                                    <?php $actionFormatted = $row['action_formatted'] ?? []; ?>
                                    <?php if (!empty($actionFormatted)): ?>
                                        <span class="badge bg-<?= esc($actionFormatted['badge_class'] ?? 'secondary') ?> me-1">
                                            <?= esc($actionFormatted['scope'] ?? '') ?>
                                        </span>
                                        <i class="bi bi-<?= esc($actionFormatted['icon'] ?? 'activity') ?> me-1"></i>
                                        <?= esc($actionFormatted['entity'] ?? '') ?> <?= esc($actionFormatted['action'] ?? '') ?>
                                    <?php else: ?>
                                        <code><?= esc((string) ($row['action'] ?? '')) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $reasonText = trim((string) ($row['reason_text'] ?? '')); ?>
                                    <?php if ($reasonText === ''): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <?= esc($reasonText) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= esc((string) ($row['entity_type'] ?? '-')) ?>
                                    <?php if (!empty($row['entity_id'])): ?>
                                        #<?= (int) $row['entity_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($row['ip_address'] ?? '-')) ?></td>
                                <td>
                                    <?php
                                    $oldValues = $row['old_values_decoded'] ?? null;
                                    $newValues = $row['new_values_decoded'] ?? null;
                                    ?>
                                    <?php if ($oldValues === null && $newValues === null): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary audit-diff-btn"
                                            data-old="<?= esc(json_encode($oldValues), 'attr') ?>"
                                            data-new="<?= esc(json_encode($newValues), 'attr') ?>">
                                            <i class="bi bi-eye"></i> <?= lang('App.auditViewDiff') ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($pager)): ?>
    <div class="mt-3"><?= $pager->links() ?></div>
<?php endif; ?>

<?= $this->include('MemberPortal\Views\Admin/audit_diff_modal') ?>

<?= $this->endSection() ?>