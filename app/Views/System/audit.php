<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$page = $page ?? 1;
$perPage = $perPage ?? 25;
$totalRows = $totalRows ?? 0;
$totalPages = $totalPages ?? 1;
$hasAuditTable = $hasAuditTable ?? false;

$currentQuery = array_filter([
    'action' => $filters['action'] ?? '',
    'user_id' => $filters['user_id'] ?? '',
    'entity_type' => $filters['entity_type'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'search' => $filters['search'] ?? '',
    'per_page' => $perPage !== 25 ? $perPage : null,
]);

$pageLink = static function (int $p) use ($currentQuery): string {
    $query = array_merge($currentQuery, ['page' => $p]);
    return base_url('system/audit') . '?' . http_build_query($query);
};

$formatJson = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '<em class="text-muted">-</em>';
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $lines = [];
        foreach ($decoded as $k => $v) {
            $lines[] = esc((string) $k) . ': ' . (is_array($v) ? json_encode($v) : esc((string) $v));
        }
        return implode('<br>', $lines);
    }
    return esc($value);
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= lang('System.sectionAuditLog') ?></h5>
    <a href="<?= base_url('system/dashboard') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= lang('App.back') ?>
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong><?= lang('App.search') ?></strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= base_url('system/audit') ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('System.auditFilterAction') ?></label>
                    <select name="action" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?></option>
                        <option value="system." <?= ($filters['action'] ?? '') === 'system.' ? 'selected' : '' ?>>system.*</option>
                        <option value="system.admin" <?= ($filters['action'] ?? '') === 'system.admin' ? 'selected' : '' ?>>system.admin.*</option>
                        <option value="system.province" <?= ($filters['action'] ?? '') === 'system.province' ? 'selected' : '' ?>>system.province.*</option>
                        <option value="system.diocese" <?= ($filters['action'] ?? '') === 'system.diocese' ? 'selected' : '' ?>>system.diocese.*</option>
                        <option value="system.primary_transfer" <?= ($filters['action'] ?? '') === 'system.primary_transfer' ? 'selected' : '' ?>>system.primary_transfer.*</option>
                        <option value="auth." <?= ($filters['action'] ?? '') === 'auth.' ? 'selected' : '' ?>>auth.*</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('System.auditFilterUser') ?> ID</label>
                    <input type="number" name="user_id" class="form-control form-control-sm" value="<?= esc((string) ($filters['user_id'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('System.auditFilterEntityType') ?></label>
                    <input type="text" name="entity_type" class="form-control form-control-sm" value="<?= esc((string) ($filters['entity_type'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('System.auditFilterDateFrom') ?></label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc((string) ($filters['date_from'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('System.auditFilterDateTo') ?></label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc((string) ($filters['date_to'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('System.auditFilterSearch') ?></label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.itemsPerPage') ?></label>
                    <select name="per_page" class="form-select form-select-sm">
                        <?php foreach ([10, 25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i><?= lang('App.search') ?>
                    </button>
                    <a href="<?= base_url('system/audit') ?>" class="btn btn-outline-secondary btn-sm ms-2">
                        <?= lang('App.reset') ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$hasAuditTable): ?>
    <div class="alert alert-warning"><?= lang('System.auditNoEventsFound') ?></div>
<?php elseif (empty($rows)): ?>
    <div class="alert alert-info"><?= lang('System.auditNoEventsFound') ?></div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('App.colTimestamp') ?></th>
                            <th><?= lang('System.auditFilterAction') ?></th>
                            <th><?= lang('System.auditFilterEntityType') ?></th>
                            <th>ID</th>
                            <th><?= lang('System.auditFilterUser') ?></th>
                            <th>IP</th>
                            <th><?= lang('App.oldValues') ?></th>
                            <th><?= lang('App.newValues') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= esc((string) ($row['created_at'] ?? '')) ?></td>
                                <td><code><?= esc((string) ($row['action'] ?? '')) ?></code></td>
                                <td><?= esc((string) ($row['entity_type'] ?? '')) ?></td>
                                <td><?= (int) ($row['entity_id'] ?? 0) ?></td>
                                <td><?= $row['user_id'] !== null ? (int) $row['user_id'] : '-' ?></td>
                                <td><?= esc((string) ($row['ip_address'] ?? '')) ?></td>
                                <td class="small"><?= $formatJson($row['old_values'] ?? null) ?></td>
                                <td class="small"><?= $formatJson($row['new_values'] ?? null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted"><?= lang('App.pageShowing', [(($page - 1) * $perPage) + 1, min($totalRows, $page * $perPage), $totalRows]) ?></small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pageLink($page - 1) ?>"><?= lang('App.previous') ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pageLink($page + 1) ?>"><?= lang('App.next') ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
