<?php

/**
 * Partial: _action_modal.php
 *
 * Shared "Add/Edit Step" modal + JS used by both the parish sequence editor
 * and the system-admin global sequence editor.
 *
 * Required variables:
 *   int    $sequenceId – parent sequence ID
 *   array  $templates  – templates available for this scope
 *   string $saveUrl    – POST base for save (no id appended on create)
 *   string $deleteUrl  – POST base for delete (id appended)
 *
 * Optional variables:
 *   array  $leaders    – assignable parish leaders (parish scope only)
 *   bool   $isUpstream – true on upstream (system/diocesan) editors: hides
 *                        the leader picker and explains that call/manual
 *                        assignees resolve to each parish's primary admin
 *                        when the sequence is cloned.
 */

$leaders  = $leaders ?? [];
$isUpstream = $isUpstream ?? false;
?>
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
                            <option value="manual"><?= lang('Visitors.seqChannelManual') ?></option>
                        </select>
                        <div class="form-text" id="action_channel_hint_manual" hidden><?= lang('Visitors.seqChannelManualHint') ?></div>
                        <?php if ($isUpstream): ?>
                            <div class="form-text" id="action_channel_hint_global" hidden><?= lang('Visitors.seqGlobalAssigneeHint') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12" id="pane_manual_assignee" hidden>
                        <label class="form-label" for="action_assigned_to"><?= lang('Visitors.seqFieldAssignee') ?> <span class="text-danger">*</span></label>
                        <select id="action_assigned_to" class="form-select">
                            <option value=""><?= lang('Visitors.seqFieldAssigneeSelect') ?></option>
                            <?php foreach ($leaders as $leader): ?>
                                <option value="<?= (int) $leader['user_id'] ?>">
                                    <?= esc(trim($leader['first_name'] . ' ' . $leader['last_name'])) ?> — <?= esc($leader['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= lang('Visitors.seqFieldAssigneeHint') ?></div>
                    </div>

                    <div class="col-12" id="pane_manual_instructions" hidden>
                        <label class="form-label" for="action_instructions"><?= lang('Visitors.seqFieldInstructions') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="action_instructions" class="form-control" maxlength="255"
                            placeholder="<?= esc(lang('Visitors.seqFieldInstructionsPlaceholder'), 'attr') ?>">
                        <div class="form-text"><?= lang('Visitors.seqFieldInstructionsHint') ?></div>
                    </div>

                    <div class="col-12" id="pane_action_template">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isUpstream = <?= $isUpstream ? 'true' : 'false' ?>;
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
        const saveUrl = <?= json_encode($saveUrl) ?>;
        const deleteUrl = <?= json_encode($deleteUrl) ?>;

        const modal = document.getElementById('actionModal');
        const bsModal = modal ? new bootstrap.Modal(modal) : null;
        const btnAdd = document.getElementById('btnAddAction');
        const btnSave = document.getElementById('btnSaveAction');
        const spinner = document.getElementById('actionSpinner');
        const errBox = document.getElementById('actionModalErrors');
        const channelSel = document.getElementById('action_channel');
        const templateSel = document.getElementById('action_template');
        const assigneeSel = document.getElementById('action_assigned_to');
        const instructionsInput = document.getElementById('action_instructions');

        // Filter template options by channel
        function filterTemplates(channel) {
            Array.from(templateSel.options).forEach(function(opt) {
                if (opt.value === '') return;
                const optChannel = opt.dataset.channel ?? '';
                opt.hidden = (channel !== '' && optChannel !== channel);
            });
            if (templateSel.selectedOptions[0]?.hidden) {
                templateSel.value = '';
            }
        }

        // Manual steps need instructions; parish UI also picks an assignee.
        function toggleChannelPanes(channel) {
            const isManual = channel === 'manual';
            const isHuman = isManual || channel === 'call';
            document.getElementById('pane_manual_assignee').hidden = isUpstream || !isManual;
            document.getElementById('pane_manual_instructions').hidden = !isManual;
            document.getElementById('pane_action_template').hidden = isManual;
            document.getElementById('action_channel_hint_manual').hidden = !isManual;
            const globalHint = document.getElementById('action_channel_hint_global');
            if (globalHint) {
                globalHint.hidden = !isHuman;
            }
        }

        channelSel?.addEventListener('change', () => {
            filterTemplates(channelSel.value);
            toggleChannelPanes(channelSel.value);
        });

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
            if (assigneeSel) assigneeSel.value = '';
            instructionsInput.value = '';
            filterTemplates('');
            toggleChannelPanes('');
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
            toggleChannelPanes(btn.dataset.channel);
            templateSel.value = btn.dataset.templateId ?? '';
            if (assigneeSel) assigneeSel.value = btn.dataset.assignedTo ?? '';
            instructionsInput.value = btn.dataset.instructions ?? '';
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

            const isManual = channel === 'manual';
            const assignedTo = (isManual && !isUpstream) ? (assigneeSel?.value ?? '') : '';
            const instructions = isManual ? (instructionsInput?.value.trim() ?? '') : '';

            if (!week || !day || !channel) {
                errBox.textContent = <?= json_encode(lang('Visitors.seqValidationRequired')) ?>;
                errBox.classList.remove('d-none');
                return;
            }
            if (isManual && !isUpstream && !assignedTo) {
                errBox.textContent = <?= json_encode(lang('Visitors.seqAssigneeRequired')) ?>;
                errBox.classList.remove('d-none');
                return;
            }
            if (isManual && !instructions) {
                errBox.textContent = <?= json_encode(lang('Visitors.seqInstructionsRequired')) ?>;
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
                template_id: isManual ? '' : (templateSel?.value ?? ''),
                instructions: instructions,
            });
            if (!isUpstream) {
                params.set('assigned_to', assignedTo);
            }

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
                text: 'border-primary text-primary',
                email: 'border-info text-info-emphasis',
                call: 'border-warning text-warning-emphasis',
                mail: 'border-secondary text-secondary',
                gift: 'border-success text-success',
                manual: 'border-danger text-danger',
            };
            const labelNone = <?= json_encode(lang('Visitors.seqStepNone')) ?>;

            let rows = actions.map(function(a) {
                const badge = channelBadge[a.channel] ?? 'border-secondary text-secondary';
                let detail;
                if (a.channel === 'manual') {
                    detail = escHtml(a.instructions ?? '');
                    if (a.assignee_name) {
                        detail += '<div class="small text-muted"><i class="bi bi-person-check" aria-hidden="true"></i> '
                            + escHtml(a.assignee_name) + '</div>';
                    }
                } else {
                    detail = a.template_name ? escHtml(a.template_name) : '<em class="text-muted">' + labelNone + '</em>';
                }

                return `<tr>
            <td>${escHtml(String(a.week_number))}</td>
            <td>${escHtml(String(a.day_offset))}</td>
            <td><span class="badge border fw-normal ${badge}">${escHtml(a.channel)}</span></td>
            <td>${detail}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit-action"
                    data-id="${escHtml(String(a.id))}"
                    data-week="${escHtml(String(a.week_number))}"
                    data-day-offset="${escHtml(String(a.day_offset))}"
                    data-channel="${escHtml(a.channel)}"
                    data-template-id="${a.template_id ?? ''}"
                    data-assigned-to="${a.assigned_to ?? ''}"
                    data-instructions="${escHtml(a.instructions ?? '')}" aria-label="<?= esc(lang('App.edit')) ?>">
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
                .replace(/'/g, '&#039;');
        }
    });
</script>
