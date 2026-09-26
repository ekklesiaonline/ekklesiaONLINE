<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <?php if (auth()->user()->can('visitor.edit')): ?>
        <a href="<?= base_url('followup-workflow/sequences/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.seqNewBtn') ?>
        </a>
    <?php endif; ?>
    <a href="<?= base_url('followup-workflow/templates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-file-text" aria-hidden="true"></i> <?= lang('Visitors.seqMsgTemplatesBtn') ?>
    </a>
    <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
</div>

<?php
$showGlobal  = $showGlobal ?? false;
$showDiocese = $showDiocese ?? false;
$hasDiocese  = $hasDiocese ?? true;
$upstreamFiltersActive = $showGlobal || $showDiocese;
$activeCount = ($showGlobal ? 1 : 0) + ($showDiocese ? 1 : 0);
?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#upstreamFilterPanel"
            aria-expanded="<?= $upstreamFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
            <?php if ($upstreamFiltersActive): ?>
                <span class="badge border border-primary text-primary fw-normal ms-1"><?= (int) $activeCount ?></span>
            <?php endif; ?>
        </button>
    </div>
    <div id="upstreamFilterPanel" class="collapse <?= $upstreamFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('followup-workflow/sequences') ?>" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label mb-1"><?= lang('Visitors.seqFilterUpstreamLabel') ?></label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filter_upstream_global" name="upstream_global" value="1"
                            <?= $showGlobal ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="filter_upstream_global"><?= lang('Visitors.seqFilterShowGlobal') ?></label>
                    </div>
                    <?php if ($hasDiocese): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="filter_upstream_diocese" name="upstream_diocese" value="1"
                                <?= $showDiocese ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="filter_upstream_diocese">
                                <?= lang('Visitors.seqFilterShowDiocese') ?><?= !empty($scopeNames['diocese']) ? ' (' . esc($scopeNames['diocese']) . ')' : '' ?>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="form-text"><?= lang('Visitors.seqFilterUpstreamHint') ?></div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$translateSequenceName = static function (array $sequence): string {
    $name = (string) ($sequence['name'] ?? '');

    if (
        ($sequence['parish_id'] ?? null) === null
        && $name === '6-Week First-Time Visitor Journey'
    ) {
        return lang('Visitors.sequenceDefaultSixWeekName');
    }

    return $name;
};

$translateSequenceDescription = static function (array $sequence): string {
    $description = (string) ($sequence['description'] ?? '');

    if (
        ($sequence['parish_id'] ?? null) === null
        && $description === 'System default: a structured 6-week touchpoint cadence for first-time visitors, combining automated emails/texts with personal volunteer calls and a welcome gift.'
    ) {
        return lang('Visitors.sequenceDefaultSixWeekDescription');
    }

    return $description;
};
?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($sequences)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-diagram-3 fs-2 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Visitors.seqEmpty') ?>
                <?php if (auth()->user()->can('visitor.edit')): ?>
                    <a href="<?= base_url('followup-workflow/sequences/create') ?>"><?= lang('Visitors.seqCreateNow') ?></a>
                <?php endif; ?>
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
                            <?php if (auth()->user()->can('visitor.edit')): ?>
                                <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sequences as $seq): ?>
                            <?php
                            $isUpstream  = ($seq['parish_id'] === null);
                            $seqScope    = $seq['scope'] ?? 'parish';
                            $displayName = $translateSequenceName($seq);
                            $displayDescription = $translateSequenceDescription($seq);
                            ?>
                            <tr data-sequence-id="<?= (int) $seq['id'] ?>">
                                <td>
                                    <?php if (!$isUpstream && auth()->user()->can('visitor.edit')): ?>
                                        <a href="<?= base_url('followup-workflow/sequences/edit/' . $seq['id']) ?>"
                                            class="fw-semibold text-decoration-none">
                                            <?= esc($displayName) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="fw-semibold"><?= esc($displayName) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$isUpstream && !empty($seq['is_default'])): ?>
                                        <span class="badge border border-primary text-primary fw-normal ms-1"><?= lang('Visitors.seqDefaultBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!$isUpstream && !empty($seq['is_customized'])): ?>
                                        <span class="badge border border-warning text-warning fw-normal ms-1" title="<?= esc(lang('Visitors.seqCustomizedHint'), 'attr') ?>"><?= lang('Visitors.seqCustomizedBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if ($isUpstream && !empty($seq['is_global_default'])): ?>
                                        <span class="badge border border-info text-info fw-normal ms-1"><?= lang('Visitors.seqGlobalDefaultBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= esc($displayDescription !== '' ? $displayDescription : '—') ?></td>
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
                                    <?php if ($seqScope === 'diocese'): ?>
                                        <span class="badge border border-primary text-primary fw-normal"><?= esc($scopeNames['diocese'] ?? '') ?: lang('Visitors.seqScopeDiocese') ?></span>
                                    <?php elseif ($isUpstream): ?>
                                        <span class="badge border border-info text-info fw-normal"><?= lang('Visitors.seqScopeGlobal') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-body fw-normal"><?= lang('Visitors.seqScopeParish') ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if (auth()->user()->can('visitor.edit')): ?>
                                    <td class="text-end">
                                        <?php if ($isUpstream && !empty($seq['is_active'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-clone-sequence"
                                                data-id="<?= (int) $seq['id'] ?>"
                                                data-name="<?= esc($displayName, 'attr') ?>"
                                                title="<?= esc(lang('Visitors.seqCloneHint'), 'attr') ?>">
                                                <i class="bi bi-copy" aria-hidden="true"></i> <?= lang('Visitors.seqCloneBtn') ?>
                                            </button>
                                        <?php elseif (!$isUpstream): ?>
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
                                                        <a class="dropdown-item" href="<?= base_url('followup-workflow/sequences/edit/' . $seq['id']) ?>">
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
                                                    <?php if (!empty($seq['is_active']) && empty($seq['is_default'])): ?>
                                                        <li>
                                                            <button class="dropdown-item btn-set-default-sequence"
                                                                type="button"
                                                                data-id="<?= (int) $seq['id'] ?>">
                                                                <i class="bi bi-check-circle me-2" aria-hidden="true"></i><?= lang('Visitors.seqSetDefaultTitle') ?>
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
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
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
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

        // ── Toggle active ──────────────────────────────────────────────────
        document.querySelectorAll('.btn-toggle-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const active = parseInt(btn.dataset.active, 10);
                const label = active ? '<?= esc(lang('Visitors.seqDeactivateVerb'), 'js') ?>' : '<?= esc(lang('Visitors.seqActivateVerb'), 'js') ?>';

                showAppConfirm(
                    '<?= esc(lang('App.confirm'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqToggleConfirm'), 'js') ?>'.replace('{0}', label),
                    function() {
                        fetch('<?= base_url('followup-workflow/sequences/toggle/') ?>' + id, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: csrfHeaders(),
                            })
                            .then(r => {
                                refreshCsrf(r);
                                return r.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                } else {
                                    showAppToast(data.message ?? '<?= esc(lang('Visitors.seqToggleFailed'), 'js') ?>', 'danger');
                                }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });

        // ── Delete ─────────────────────────────────────────────────────────
        document.querySelectorAll('.btn-delete-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const name = btn.dataset.name;

                showAppConfirm(
                    '<?= esc(lang('Visitors.seqDeleteTitle'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqDeleteConfirm'), 'js') ?>'.replace('{0}', '<strong>' + name + '</strong>'),
                    function() {
                        fetch('<?= base_url('followup-workflow/sequences/delete/') ?>' + id, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: csrfHeaders(),
                            })
                            .then(r => {
                                refreshCsrf(r);
                                return r.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    showAppToast('<?= esc(lang('Visitors.seqDeleteSuccess'), 'js') ?>', 'primary');
                                    setTimeout(() => location.reload(), 800);
                                } else {
                                    showAppToast(data.message ?? '<?= esc(lang('Visitors.seqDeleteFailed'), 'js') ?>', 'danger');
                                }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    },
                    '<?= esc(lang('Visitors.seqDeleteBtn'), 'js') ?>'
                );
            });
        });

        // ── Clone upstream sequence into this parish ────────────────────────
        document.querySelectorAll('.btn-clone-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                const name = btn.dataset.name;

                showAppConfirm(
                    '<?= esc(lang('Visitors.seqCloneTitle'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqCloneConfirm'), 'js') ?>'.replace('{0}', '<strong>' + name + '</strong>'),
                    function() {
                        fetch('<?= base_url('followup-workflow/sequences/clone/') ?>' + id, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: csrfHeaders(),
                            })
                            .then(r => {
                                refreshCsrf(r);
                                return r.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    showAppToast('<?= esc(lang('Visitors.seqCloneSuccess'), 'js') ?>', 'primary');
                                    setTimeout(() => location.href = '<?= base_url('followup-workflow/sequences/edit/') ?>' + data.sequence_id, 600);
                                } else {
                                    showAppToast(data.message ?? '<?= esc(lang('Visitors.seqCloneFailed'), 'js') ?>', 'danger');
                                }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });

        // ── Set as default ──────────────────────────────────────────────────
        document.querySelectorAll('.btn-set-default-sequence').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;

                showAppConfirm(
                    '<?= esc(lang('Visitors.seqSetDefaultConfirmTitle'), 'js') ?>',
                    '<?= esc(lang('Visitors.seqSetDefaultConfirmBody'), 'js') ?>',
                    function() {
                        fetch('<?= base_url('followup-workflow/sequences/set-default/') ?>' + id, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: csrfHeaders(),
                            })
                            .then(r => {
                                refreshCsrf(r);
                                return r.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    showAppToast('<?= esc(lang('Visitors.seqSetDefaultSuccess'), 'js') ?>', 'primary');
                                    setTimeout(() => location.reload(), 600);
                                } else {
                                    showAppToast(data.message ?? '<?= esc(lang('Visitors.seqSetDefaultFailed'), 'js') ?>', 'danger');
                                }
                            })
                            .catch(() => showAppToast('<?= esc(lang('App.networkError'), 'js') ?>', 'danger'));
                    }
                );
            });
        });
    });
</script>

<?= $this->endSection() ?>