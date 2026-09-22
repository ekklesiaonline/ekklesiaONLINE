<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$sequenceId = (int) ($sequence['id'] ?? 0);
$isNew      = ($sequenceId === 0);
$saveUrl    = $isNew
    ? base_url('followup-workflow/sequences/save')
    : base_url('followup-workflow/sequences/save/' . $sequenceId);
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.pageTitleSequences') ?>
    </a>
</div>

<!-- ── Sequence details card ───────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header fw-semibold"><?= lang('Visitors.seqCardDetails') ?></div>
    <div class="card-body">
        <form method="post" action="<?= $saveUrl ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="seq_name"><?= lang('Visitors.seqFieldName') ?> <span class="text-danger">*</span></label>
                    <input type="text"
                        id="seq_name"
                        name="name"
                        class="form-control"
                        maxlength="150"
                        required
                        value="<?= esc(old('name', $sequence['name'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="seq_active"><?= lang('Visitors.seqFieldStatus') ?></label>
                    <select id="seq_active" name="is_active" class="form-select">
                        <option value="1" <?= ((int) ($sequence['is_active'] ?? 1)) === 1 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusActive') ?></option>
                        <option value="0" <?= ((int) ($sequence['is_active'] ?? 1)) === 0 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusInactive') ?></option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="seq_description"><?= lang('Visitors.seqFieldDescription') ?></label>
                    <textarea id="seq_description"
                        name="description"
                        class="form-control"
                        rows="2"
                        maxlength="500"><?= esc(old('description', $sequence['description'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg" aria-hidden="true"></i> <?= $isNew ? lang('Visitors.seqBtnCreate') : lang('Visitors.seqBtnSave') ?>
                </button>
                <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary"><?= lang('Visitors.seqBtnCancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php if (!$isNew): ?>
    <!-- ── Action steps card ────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= lang('Visitors.seqCardSteps') ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddAction">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.seqBtnAddStep') ?>
            </button>
        </div>
        <div class="card-body p-0">
            <div id="actionsTableWrapper">
                <?= view('Clergy\Views\FollowupWorkflow/_actions_table', [
                    'actions'    => $actions,
                    'sequenceId' => $sequenceId,
                ]) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ── Action Modal ─────────────────────────────────────────────────── -->
<?php if (!$isNew): ?>
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel"><?= lang('Visitors.seqModalAddStep') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="action_id" value="">
                    <input type="hidden" id="action_sequence_id" value="<?= $sequenceId ?>">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="action_week"><?= lang('Visitors.seqFieldWeek') ?> <span class="text-danger">*</span></label>
                            <input type="number" id="action_week" class="form-control" min="1" max="52" required placeholder="e.g. 1">
                            <div class="form-text"><?= lang('Visitors.seqFieldWeekHint') ?></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="action_day_offset"><?= lang('Visitors.seqFieldDayOffset') ?> <span class="text-danger">*</span></label>
                            <input type="number" id="action_day_offset" class="form-control" min="0" max="365" required placeholder="e.g. 3">
                            <div class="form-text"><?= lang('Visitors.seqFieldDayOffsetHint') ?></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="action_channel"><?= lang('Visitors.seqFieldChannel') ?> <span class="text-danger">*</span></label>
                            <select id="action_channel" class="form-select" required>
                                <option value=""><?= lang('Visitors.seqFieldChannelSelect') ?></option>
                                <option value="text"><?= lang('Visitors.seqChannelText') ?></option>
                                <option value="email"><?= lang('Visitors.seqChannelEmail') ?></option>
                                <option value="call"><?= lang('Visitors.seqChannelCall') ?></option>
                                <option value="mail"><?= lang('Visitors.seqChannelMail') ?></option>
                                <option value="gift"><?= lang('Visitors.seqChannelGift') ?></option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="action_template"><?= lang('Visitors.seqFieldTemplate') ?></label>
                            <select id="action_template" class="form-select">
                                <option value=""><?= lang('Visitors.seqFieldTemplateNone') ?></option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?= (int) $tpl['id'] ?>"
                                        data-channel="<?= esc($tpl['channel']) ?>">
                                        [<?= esc(strtoupper($tpl['channel'])) ?>] <?= esc($tpl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= lang('Visitors.seqFieldTemplateHint') ?></div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="action_automated" value="1">
                                <label class="form-check-label" for="action_automated">
                                    <?= lang('Visitors.seqFieldAutomated') ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="actionModalErrors" class="alert alert-danger mt-3 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('Visitors.seqBtnCancel') ?></button>
                    <button type="button" class="btn btn-primary" id="btnSaveAction">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="actionSpinner"></span>
                        <?= lang('Visitors.seqBtnSaveStep') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!$isNew): ?>
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
            const saveUrl = '<?= base_url('followup-workflow/actions/save') ?>';
            const deleteUrl = '<?= base_url('followup-workflow/actions/delete/') ?>';

            const modal = document.getElementById('actionModal');
            const bsModal = modal ? new bootstrap.Modal(modal) : null;
            const btnAdd = document.getElementById('btnAddAction');
            const btnSave = document.getElementById('btnSaveAction');
            const spinner = document.getElementById('actionSpinner');
            const errBox = document.getElementById('actionModalErrors');
            const channelSel = document.getElementById('action_channel');
            const templateSel = document.getElementById('action_template');

            // Filter template options by channel
            function filterTemplates(channel) {
                Array.from(templateSel.options).forEach(function(opt) {
                    if (opt.value === '') return;
                    const optChannel = opt.dataset.channel ?? '';
                    opt.hidden = (channel !== '' && optChannel !== channel);
                });
                // Reset selection if current pick is hidden
                if (templateSel.selectedOptions[0]?.hidden) {
                    templateSel.value = '';
                }
            }

            channelSel?.addEventListener('change', () => filterTemplates(channelSel.value));

            // Open modal for a new action
            btnAdd?.addEventListener('click', function() {
                if (!bsModal) {
                    showAppToast(<?= json_encode(lang('Visitors.seqModalUnavailable')) ?>, 'warning');
                    return;
                }
                document.getElementById('actionModalLabel').textContent = <?= json_encode(lang('Visitors.seqModalAddStep')) ?>;
                document.getElementById('action_id').value = '';
                document.getElementById('action_week').value = '';
                document.getElementById('action_day_offset').value = '';
                channelSel.value = '';
                templateSel.value = '';
                document.getElementById('action_automated').checked = false;
                filterTemplates('');
                errBox.classList.add('d-none');
                bsModal.show();
            });

            // Open modal to edit an existing action
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-edit-action');
                if (!btn) return;
                if (!bsModal) {
                    showAppToast(<?= json_encode(lang('Visitors.seqModalUnavailable')) ?>, 'warning');
                    return;
                }

                document.getElementById('actionModalLabel').textContent = <?= json_encode(lang('Visitors.seqModalEditStep')) ?>;
                document.getElementById('action_id').value = btn.dataset.id;
                document.getElementById('action_week').value = btn.dataset.week;
                document.getElementById('action_day_offset').value = btn.dataset.dayOffset;
                channelSel.value = btn.dataset.channel;
                filterTemplates(btn.dataset.channel);
                templateSel.value = btn.dataset.templateId ?? '';
                document.getElementById('action_automated').checked = btn.dataset.automated === '1';
                errBox.classList.add('d-none');
                bsModal.show();
            });

            // Save action (create or update)
            btnSave?.addEventListener('click', function() {
                const actionId = document.getElementById('action_id').value;
                const url = actionId ? saveUrl + '/' + actionId : saveUrl;
                const week = document.getElementById('action_week').value.trim();
                const day = document.getElementById('action_day_offset').value.trim();
                const channel = channelSel?.value ?? '';

                if (!week || !day || !channel) {
                    errBox.textContent = <?= json_encode(lang('Visitors.seqValidationRequired')) ?>;
                    errBox.classList.remove('d-none');
                    return;
                }

                btnSave.disabled = true;
                spinner?.classList.remove('d-none');
                errBox.classList.add('d-none');

                const params = new URLSearchParams({
                    sequence_id: '<?= $sequenceId ?>',
                    week_number: week,
                    day_offset: day,
                    channel: channel,
                    template_id: templateSel?.value ?? '',
                    is_automated: document.getElementById('action_automated').checked ? '1' : '0',
                });

                fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: csrfHeaders(),
                        body: params.toString(),
                    })
                    .then(r => {
                        refreshCsrf(r);
                        return r.json();
                    })
                    .then(data => {
                        if (data.success) {
                            bsModal?.hide();
                            renderActions(data.actions);
                            showAppToast(<?= json_encode(lang('Visitors.seqStepSaved')) ?>, 'primary');
                        } else {
                            const msgs = data.errors ?
                                Object.values(data.errors).join('<br>') :
                                (data.message ?? <?= json_encode(lang('Visitors.seqStepSaveFailed')) ?>);
                            errBox.innerHTML = msgs;
                            errBox.classList.remove('d-none');
                        }
                    })
                    .catch(() => {
                        errBox.textContent = <?= json_encode(lang('Visitors.seqNetworkError')) ?>;
                        errBox.classList.remove('d-none');
                    })
                    .finally(() => {
                        btnSave.disabled = false;
                        spinner?.classList.add('d-none');
                    });
            });

            // Delete action
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-delete-action');
                if (!btn) return;
                const id = btn.dataset.id;
                const channel = btn.dataset.channel;

                showAppConfirm(
                    <?= json_encode(lang('Visitors.seqDeleteStepTitle')) ?>,
                    <?= json_encode(lang('Visitors.seqDeleteStepBody', ['{channel}'])) ?>.replace('{channel}', channel),
                    function() {
                        fetch(deleteUrl + id, {
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
                                    renderActions(data.actions);
                                    showAppToast(<?= json_encode(lang('Visitors.seqStepDeleted')) ?>, 'primary');
                                } else {
                                    showAppToast(data.message ?? <?= json_encode(lang('Visitors.seqStepDeleteFailed')) ?>, 'danger');
                                }
                            })
                            .catch(() => showAppToast(<?= json_encode(lang('Visitors.seqNetworkError')) ?>, 'danger'));
                    },
                    <?= json_encode(lang('App.delete')) ?>
                );
            });

            // Re-render action table rows from JSON data
            function renderActions(actions) {
                const wrapper = document.getElementById('actionsTableWrapper');
                if (!actions || actions.length === 0) {
                    wrapper.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-list-task fs-2 d-block mb-2" aria-hidden="true"></i>
                    ${<?= json_encode(lang('Visitors.seqStepsEmpty')) ?>}
                </div>`;
                    return;
                }

                const channelBadge = {
                    text: 'bg-primary',
                    email: 'bg-info text-dark',
                    call: 'bg-warning text-dark',
                    mail: 'bg-secondary',
                    gift: 'bg-success',
                };
                const labelNone = <?= json_encode(lang('Visitors.seqStepNone')) ?>;
                const labelAuto = <?= json_encode(lang('Visitors.seqTypeAuto')) ?>;
                const labelManual = <?= json_encode(lang('Visitors.seqTypeManual')) ?>;

                let rows = actions.map(function(a) {
                    const badge = channelBadge[a.channel] ?? 'bg-light text-dark';
                    const tplName = a.template_name ? escHtml(a.template_name) : '<em class="text-muted">' + labelNone + '</em>';
                    const autoBadge = a.is_automated ?
                        '<span class="badge border border-success text-success fw-normal">' + labelAuto + '</span>' :
                        '<span class="badge border border-secondary text-body fw-normal">' + labelManual + '</span>';

                    return `<tr>
                <td>${escHtml(String(a.week_number))}</td>
                <td>${escHtml(String(a.day_offset))}</td>
                <td><span class="badge ${badge}">${escHtml(a.channel)}</span></td>
                <td>${tplName}</td>
                <td>${autoBadge}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit-action"
                        data-id="${escHtml(String(a.id))}"
                        data-week="${escHtml(String(a.week_number))}"
                        data-day-offset="${escHtml(String(a.day_offset))}"
                        data-channel="${escHtml(a.channel)}"
                        data-template-id="${a.template_id ?? ''}"
                        data-automated="${escHtml(String(a.is_automated))}" aria-label="<?= esc(lang('App.edit')) ?>">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-action"
                        data-id="${escHtml(String(a.id))}"
                        data-channel="${escHtml(a.channel)}" aria-label="<?= esc(lang('App.delete')) ?>">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>`;
                }).join('');

                wrapper.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">${<?= json_encode(lang('Visitors.seqColWeek')) ?>}</th>
                            <th scope="col">${<?= json_encode(lang('Visitors.seqColDayOffset')) ?>}</th>
                            <th scope="col">${<?= json_encode(lang('Visitors.seqColChannel')) ?>}</th>
                            <th scope="col">${<?= json_encode(lang('Visitors.seqColTemplate')) ?>}</th>
                            <th scope="col">${<?= json_encode(lang('Visitors.seqColType')) ?>}</th>
                            <th scope="col" class="text-end">${<?= json_encode(lang('App.colActions')) ?>}</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
            }

            function escHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
        });
    </script>
<?php endif; ?>

<?= $this->endSection() ?>