<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-diagram-3" aria-hidden="true"></i> <?= lang('Visitors.callTasksSequencesBtn') ?>
    </a>
    <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-tabs mb-3">
    <?php
    $tabMap = [
        'pending'   => lang('App.statusPending'),
        'completed' => lang('App.statusCompleted'),
        'cancelled' => lang('App.statusCancelled'),
        'all'       => lang('App.all'),
    ];
    foreach ($tabMap as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeStatus === $key ? 'active' : '' ?>"
                href="<?= base_url('followup-workflow/call-tasks?status=' . $key) ?>">
                <?= esc($label) ?>
                <?php if ($key === 'pending' && $pendingCount > 0): ?>
                    <span class="badge rounded-pill border border-warning text-warning fw-normal ms-1"><?= (int) $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-telephone-x fs-2 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Visitors.callTasksEmpty') ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="callTasksTable">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Visitors.callTasksColVisitor') ?></th>
                            <th scope="col"><?= lang('Visitors.colPhone') ?></th>
                            <th scope="col"><?= lang('Visitors.scheduledLabel') ?></th>
                            <th scope="col"><?= lang('App.colNotes') ?></th>
                            <th scope="col" class="text-center"><?= lang('Visitors.colStatus') ?></th>
                            <?php if (auth()->user()->can('visitor.edit')): ?>
                                <th scope="col" class="text-center"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr id="task-row-<?= (int) $task['id'] ?>">
                                <td>
                                    <?php if (!empty($task['person_id'])): ?>
                                        <span class="text-decoration-none">
                                            <?= esc($task['first_name'] . ' ' . $task['last_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?= base_url('visitors/view/' . (int) ($task['visitor_id'] ?? 0)) ?>"
                                            class="text-decoration-none">
                                            <?= esc($task['first_name'] . ' ' . $task['last_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $phone = $task['phone'] ?? '';
                                    if ($phone !== '') {
                                        // Show last 4 digits only for privacy
                                        $masked = preg_replace('/\d(?=\d{4})/', '•', $phone);
                                        echo esc($masked ?? $phone);
                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= esc($task['scheduled_date']) ?></td>
                                <td>
                                    <span class="task-notes"><?= esc($task['notes'] ?? '') ?: '<span class="text-muted">—</span>' ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $badgeMap = [
                                        'pending'   => 'warning',
                                        'completed' => 'success',
                                        'cancelled' => 'secondary',
                                    ];
                                    $badgeColor = $badgeMap[$task['status']] ?? 'secondary';
                                    $statusLangMap = [
                                        'pending'   => lang('App.statusPending'),
                                        'completed' => lang('App.statusCompleted'),
                                        'cancelled' => lang('App.statusCancelled'),
                                    ];
                                    $statusLabel = $statusLangMap[$task['status']] ?? ucfirst($task['status']);
                                    ?>
                                    <span class="badge border border-<?= $badgeColor ?> text-<?= $badgeColor ?> fw-normal task-status-badge" id="status-<?= (int) $task['id'] ?>">
                                        <?= esc($statusLabel) ?>
                                    </span>
                                </td>
                                <?php if (auth()->user()->can('visitor.edit')): ?>
                                    <td class="text-center">
                                        <?php if ($task['status'] === 'pending'): ?>
                                            <button
                                                class="btn btn-sm btn-outline-success complete-task-btn"
                                                data-task-id="<?= (int) $task['id'] ?>"
                                                data-visitor="<?= esc($task['first_name'] . ' ' . $task['last_name'], 'attr') ?>">
                                                <i class="bi bi-check2-circle" aria-hidden="true"></i> <?= lang('Visitors.callTasksDoneBtn') ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <?= !empty($task['completed_at']) ? esc($task['completed_at']) : '' ?>
                                            </span>
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

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function() {
        'use strict';

        const COMPLETE_URL = '<?= base_url('followup-workflow/call-tasks/complete') ?>';
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const i18n = {
            completeTitle: <?= json_encode(lang('Visitors.callTasksCompleteTitle')) ?>,
            completeBody: <?= json_encode(lang('Visitors.callTasksCompleteBody')) ?>,
            completeBtnLabel: <?= json_encode(lang('Visitors.callTasksCompleteBtnLabel')) ?>,
            completeSuccess: <?= json_encode(lang('Visitors.taskCompleteSuccess')) ?>,
            statusCompleted: <?= json_encode(lang('App.statusCompleted')) ?>,
            updateFailed: <?= json_encode(lang('Visitors.callTasksUpdateFailed')) ?>,
            networkError: <?= json_encode(lang('Visitors.callTasksNetworkError')) ?>,
        };

        document.querySelectorAll('.complete-task-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const taskId = btn.dataset.taskId;
                const visitor = btn.dataset.visitor;

                showAppConfirm(
                    i18n.completeTitle,
                    i18n.completeBody.replace('{0}', '<strong>' + visitor + '</strong>'),
                    function() {
                        markComplete(taskId, btn);
                    },
                    i18n.completeBtnLabel
                );
            });
        });

        function markComplete(taskId, btn) {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (csrfMeta) {
                headers[csrfHeader] = csrfMeta.content;
            }

            fetch(COMPLETE_URL + '/' + taskId, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers,
                })
                .then(function(r) {
                    const newToken = r.headers.get(csrfHeader);
                    if (newToken && csrfMeta) {
                        csrfMeta.content = newToken;
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (data.success) {
                        showAppToast(i18n.completeSuccess, 'primary');

                        // Update badge and remove action button
                        const badgeEl = document.getElementById('status-' + taskId);
                        if (badgeEl) {
                            badgeEl.className = 'badge border border-success text-success fw-normal task-status-badge';
                            badgeEl.textContent = i18n.statusCompleted;
                        }
                        btn.parentElement.innerHTML = '<span class="text-muted small">' + new Date().toLocaleString() + '</span>';
                    } else {
                        showAppToast(data.message || i18n.updateFailed, 'danger');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                })
                .catch(function() {
                    showAppToast(i18n.networkError, 'danger');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        }
    }());
</script>
<?= $this->endSection() ?>