<?php
$layout    = $layout ?? 'Clergy\\Views\\layouts/main';
$routeBase = $routeBase ?? 'system/followup-workflow';
$scopeMode = $scopeMode ?? 'system';
$backUrl   = $backUrl ?? 'system/dashboard';
?>
<?= $this->extend($layout) ?>

<?= $this->section('content') ?>

<?php
$translateSequenceName = static function (array $sequence): string {
    $name = (string) ($sequence['name'] ?? '');
    if ($name === '6-Week First-Time Visitor Journey') {
        return lang('Visitors.sequenceDefaultSixWeekName');
    }
    return $name;
};

$translateSequenceDescription = static function (array $sequence): string {
    $description = (string) ($sequence['description'] ?? '');
    if ($description === 'System default: a structured 6-week touchpoint cadence for first-time visitors, combining automated emails/texts with personal volunteer calls and a welcome gift.') {
        return lang('Visitors.sequenceDefaultSixWeekDescription');
    }
    return $description;
};

$isDioceseScope = $scopeMode === 'diocese';
$cloneBtnText     = lang('Visitors.' . ($isDioceseScope ? 'seqCloneDioceseBtn' : 'seqCloneBtn'));
$cloneConfirmText = lang('Visitors.' . ($isDioceseScope ? 'seqCloneDioceseConfirm' : 'seqCloneConfirm'));
$cloneSuccessText = lang('Visitors.' . ($isDioceseScope ? 'seqCloneDioceseSuccess' : 'seqCloneSuccess'));
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url($routeBase . '/sequences/create') ?>" class="btn btn-outline-primary">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.seqNewBtn') ?>
    </a>
    <a href="<?= base_url($routeBase . '/templates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-file-text" aria-hidden="true"></i> <?= lang('Visitors.seqMsgTemplatesBtn') ?>
    </a>
    <a href="<?= base_url($backUrl) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($sequences)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-diagram-3 fs-2 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Visitors.seqEmpty') ?>
                <a href="<?= base_url($routeBase . '/sequences/create') ?>"><?= lang('Visitors.seqCreateNow') ?></a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Visitors.seqColName') ?></th>
                            <th scope="col"><?= lang('Visitors.seqColDescription') ?></th>
                            <th scope="col" class="text-center"><?= lang('Visitors.seqColSteps') ?></th>
                            <th scope="col" class="text-center"><?= lang('Visitors.seqColStatus') ?></th>
                            <th scope="col"><?= lang('Visitors.seqColScope') ?></th>
                            <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sequences as $seq): ?>
                            <?php $isManaged = $seq['is_managed'] ?? true; ?>
                            <tr data-sequence-id="<?= (int) $seq['id'] ?>"<?= $isManaged ? '' : ' class="text-muted"' ?>>
                                <td>
                                    <?php if ($isManaged): ?>
                                        <a href="<?= base_url($routeBase . '/sequences/edit/' . $seq['id']) ?>" class="fw-semibold text-decoration-none">
                                            <?= esc($translateSequenceName($seq)) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="fw-semibold"><?= esc($translateSequenceName($seq)) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($seq['is_global_default'])): ?>
                                        <span class="badge border border-info text-info fw-normal ms-1"><?= lang('Visitors.seqGlobalDefaultBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= esc($translateSequenceDescription($seq) !== '' ? $translateSequenceDescription($seq) : '—') ?></td>
                                <td class="text-center">
                                    <span class="badge border border-secondary text-secondary fw-normal rounded-pill"><?= (int) $seq['action_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($seq['is_active']): ?>
                                        <span class="badge border border-success text-success fw-normal seq-status-badge"><?= lang('App.statusActive') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-secondary fw-normal seq-status-badge"><?= lang('App.statusInactive') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $scope = $seq['scope'] ?? 'global';
                                    $isScopedDefault = !empty($seq['is_global_default'])
                                        || !empty($seq['is_diocese_default']);
                                    if ($scope === 'diocese'): ?>
                                        <span class="badge border border-primary text-primary fw-normal"><?= esc($dioceseName ?? lang('Visitors.seqScopeDiocese')) ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-info text-info fw-normal"><?= lang('Visitors.seqScopeGlobal') ?></span>
                                    <?php endif; ?>
                                    <?php if ($isManaged && $isScopedDefault): ?>
                                        <span class="badge border border-info text-info fw-normal ms-1"><?= lang('Visitors.seqDefaultBadge') ?></span>
                                    <?php elseif ($isManaged && !empty($seq['is_active'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-set-global-default" data-id="<?= (int) $seq['id'] ?>">
                                            <?= lang('Visitors.seqSetDefaultTitle') ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!$isManaged && !empty($cloneUrl)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-clone-upstream"
                                            data-id="<?= (int) $seq['id'] ?>"
                                            data-name="<?= esc($translateSequenceName($seq), 'attr') ?>">
                                            <i class="bi bi-copy me-1" aria-hidden="true"></i><?= $cloneBtnText ?>
                                        </button>
                                    <?php elseif ($isManaged): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url($routeBase . '/sequences/edit/' . $seq['id']) ?>">
                                                    <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item btn-toggle-sequence"
                                                    type="button"
                                                    data-id="<?= (int) $seq['id'] ?>"
                                                    data-active="<?= (int) $seq['is_active'] ?>">
                                                    <i class="bi <?= $seq['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-2" aria-hidden="true"></i><?= $seq['is_active'] ? lang('Visitors.seqDeactivateTitle') : lang('Visitors.seqActivateTitle') ?>
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger btn-delete-sequence"
                                                    type="button"
                                                    data-id="<?= (int) $seq['id'] ?>"
                                                    data-name="<?= esc($seq['name'], 'attr') ?>">
                                                    <i class="bi bi-trash me-2" aria-hidden="true"></i><?= lang('App.delete') ?>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const csrfHeaders = () => {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            };
            if (csrfMeta) {
                headers[csrfHeader] = csrfMeta.content;
            }
            return headers;
        };
        const refreshCsrf = (r) => {
            const newToken = r.headers.get(csrfHeader);
            if (newToken && csrfMeta) {
                csrfMeta.content = newToken;
            }
        };

        document.querySelectorAll('.btn-toggle-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const active = parseInt(btn.dataset.active, 10);
                const label = active ? '<?= esc(lang('Visitors.seqDeactivateVerb'), 'js') ?>' : '<?= esc(lang('Visitors.seqActivateVerb'), 'js') ?>';
                showAppConfirm(
                    '<?= esc(lang('App.confirm'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqToggleConfirm'), 'js') ?>'.replace('{0}', label),
                    function() {
                        fetch('<?= base_url($routeBase . '/sequences/toggle/') ?>' + id, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: csrfHeaders(),
                        })
                            .then(r => { refreshCsrf(r); return r.json(); })
                            .then(data => {
                                if (data.success) { location.reload(); }
                                else { showAppToast(data.message ?? '<?= esc(lang('Visitors.seqToggleFailed'), 'js') ?>', 'danger'); }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });

        document.querySelectorAll('.btn-delete-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                showAppConfirm(
                    '<?= esc(lang('Visitors.seqDeleteTitle'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqDeleteConfirm'), 'js') ?>'.replace('{0}', '<strong>' + name + '</strong>'),
                    function() {
                        fetch('<?= base_url($routeBase . '/sequences/delete/') ?>' + id, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: csrfHeaders(),
                        })
                            .then(r => { refreshCsrf(r); return r.json(); })
                            .then(data => {
                                if (data.success) { showAppToast('<?= esc(lang('Visitors.seqDeleteSuccess'), 'js') ?>', 'primary'); setTimeout(() => location.reload(), 800); }
                                else { showAppToast(data.message ?? '<?= esc(lang('Visitors.seqDeleteFailed'), 'js') ?>', 'danger'); }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    },
                    '<?= esc(lang('Visitors.seqDeleteBtn'), 'js') ?>'
                );
            });
        });

        document.querySelectorAll('.btn-set-global-default').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                showAppConfirm(
                    '<?= esc(lang('Visitors.seqSetDefaultConfirmTitle'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqSetDefaultConfirmBody'), 'js') ?>',
                    function() {
                        fetch('<?= base_url($routeBase . '/sequences/set-default/') ?>' + id, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: csrfHeaders(),
                        })
                            .then(r => { refreshCsrf(r); return r.json(); })
                            .then(data => {
                                if (data.success) { showAppToast('<?= esc(lang('Visitors.seqSetDefaultSuccess'), 'js') ?>', 'primary'); setTimeout(() => location.reload(), 600); }
                                else { showAppToast(data.message ?? '<?= esc(lang('Visitors.seqSetDefaultFailed'), 'js') ?>', 'danger'); }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });

        document.querySelectorAll('.btn-clone-upstream').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                showAppConfirm(
                    '<?= esc(lang('Visitors.seqCloneTitle'), 'js') ?>',
                    '<?= esc($cloneConfirmText, 'js') ?>'.replace('{0}', '<strong>' + name + '</strong>'),
                    function() {
                        fetch('<?= esc($cloneUrl ? base_url($cloneUrl) : '', 'js') ?>' + id, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: csrfHeaders(),
                        })
                            .then(r => { refreshCsrf(r); return r.json(); })
                            .then(data => {
                                if (data.success) { showAppToast('<?= esc($cloneSuccessText, 'js') ?>', 'primary'); setTimeout(() => location.reload(), 600); }
                                else { showAppToast(data.message ?? '<?= esc(lang('Visitors.seqCloneFailed'), 'js') ?>', 'danger'); }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });
    });
</script>

<?= $this->endSection() ?>
