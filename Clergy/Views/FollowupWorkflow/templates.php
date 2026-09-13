<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <?php if (auth()->user()->can('guest.edit')): ?>
        <button type="button" class="btn btn-outline-primary" id="btnNewTemplate">
            <i class="bi bi-plus-circle"></i> <?= lang('Guests.tplNewBtn') ?>
        </button>
    <?php endif; ?>
    <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-diagram-3"></i> <?= lang('Guests.tplSequencesBtn') ?>
    </a>
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Guests.backToGuests') ?>
    </a>
</div>

<?php
$channelBadge = [
    'text'  => 'bg-primary',
    'email' => 'bg-info text-dark',
    'call'  => 'bg-warning text-dark',
    'mail'  => 'bg-secondary',
    'gift'  => 'bg-success',
];
?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($templates)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-text fs-2 d-block mb-2"></i>
                <?= lang('Guests.tplEmpty') ?>
                <?php if (auth()->user()->can('guest.edit')): ?>
                    <a href="#" id="btnNewTemplateEmpty"><?= lang('Guests.tplEmptyCreate') ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('Guests.tplColStep') ?></th>
                            <th><?= lang('Guests.tplColName') ?></th>
                            <th><?= lang('Guests.tplColChannel') ?></th>
                            <th><?= lang('Guests.tplColSubjectPreview') ?></th>
                            <th><?= lang('Guests.tplColScope') ?></th>
                            <th class="text-end"><?= lang('Guests.tplColActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $tpl): ?>
                            <?php if (!is_array($tpl) || !isset($tpl['name']) || !is_string($tpl['name'])) continue; ?>
                            <?php $isGlobal = ($tpl['parish_id'] === null); ?>
                            <tr>
                                <td>
                                    <span class="badge border border-secondary text-body fw-normal">
                                        <?= esc($tpl['step_label'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="fw-semibold"><?= esc($tpl['name']) ?></td>
                                <td>
                                    <span class="badge <?= $channelBadge[$tpl['channel']] ?? 'bg-light text-dark' ?>">
                                        <?= esc($tpl['channel']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php if (!empty($tpl['subject'])): ?>
                                        <?= esc(mb_strimwidth($tpl['subject'], 0, 60, '…')) ?>
                                    <?php else: ?>
                                        <?= esc(mb_strimwidth($tpl['body_text'], 0, 60, '…')) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isGlobal): ?>
                                        <span class="badge border border-info text-info fw-normal"><?= lang('Guests.tplScopeGlobal') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-body fw-normal"><?= lang('Guests.tplScopeChurch') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('followup-workflow/templates/preview/' . $tpl['id']) ?>">
                                                    <i class="bi bi-eye me-2"></i><?= lang('App.preview') ?>
                                                </a>
                                            </li>
                                            <?php if (!$isGlobal && auth()->user()->can('guest.edit')): ?>
                                                <li>
                                                    <button class="dropdown-item btn-edit-template"
                                                        type="button"
                                                        data-id="<?= (int) $tpl['id'] ?>"
                                                        data-channel="<?= esc($tpl['channel'], 'attr') ?>"
                                                        data-name="<?= esc($tpl['name'], 'attr') ?>"
                                                        data-subject="<?= esc($tpl['subject'] ?? '', 'attr') ?>"
                                                        data-body-text="<?= esc($tpl['body_text'], 'attr') ?>"
                                                        data-body-html="<?= esc($tpl['body_html'] ?? '', 'attr') ?>"
                                                        data-variables="<?= esc($tpl['variables'] ?? '', 'attr') ?>">
                                                        <i class="bi bi-pencil me-2"></i><?= lang('App.edit') ?>
                                                    </button>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger btn-delete-template"
                                                        type="button"
                                                        data-id="<?= (int) $tpl['id'] ?>"
                                                        data-name="<?= esc($tpl['name'], 'attr') ?>">
                                                        <i class="bi bi-trash me-2"></i><?= lang('App.delete') ?>
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Create / Edit Template Modal ────────────────────────────────── -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalLabel"><?= lang('Guests.tplModalTitleNew') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tpl_id" value="">

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="tpl_name"><?= lang('Guests.tplFieldName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="tpl_name" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="tpl_channel"><?= lang('Guests.tplFieldChannel') ?> <span class="text-danger">*</span></label>
                        <select id="tpl_channel" class="form-select" required>
                            <option value=""><?= lang('Guests.tplFieldChannelSelect') ?></option>
                            <option value="text"><?= lang('Guests.tplChannelText') ?></option>
                            <option value="email"><?= lang('Guests.tplChannelEmail') ?></option>
                            <option value="call"><?= lang('Guests.tplChannelCall') ?></option>
                            <option value="mail"><?= lang('Guests.tplChannelMail') ?></option>
                            <option value="gift"><?= lang('Guests.tplChannelGift') ?></option>
                        </select>
                    </div>

                    <div class="col-12" id="tpl_subject_row">
                        <label class="form-label" for="tpl_subject"><?= lang('Guests.tplFieldSubject') ?></label>
                        <input type="text" id="tpl_subject" class="form-control" maxlength="255">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="tpl_body_text"><?= lang('Guests.tplFieldBodyText') ?> <span class="text-danger">*</span></label>
                        <textarea id="tpl_body_text" class="form-control font-monospace" rows="5" required
                            placeholder="Hi {first_name}, we loved having you at {church_name}!"></textarea>
                        <div class="form-text">
                            <?= lang('Guests.tplFieldAvailableTokens') ?>
                            <code>{first_name}</code> <code>{last_name}</code> <code>{full_name}</code>
                            <code>{church_name}</code> <code>{clergy_name}</code> <code>{service_time}</code>
                            <code>{church_address}</code> <code>{visit_date}</code> <code>{phone}</code>
                            <code>{next_event_name}</code> <code>{event_date}</code>
                            <code>{interests_list}</code> <code>{interest_*}</code>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="tpl_body_html"><?= lang('Guests.tplFieldBodyHtml') ?> <span class="text-muted small"><?= lang('Guests.tplFieldBodyHtmlOptional') ?></span></label>
                        <textarea id="tpl_body_html" class="form-control font-monospace" rows="5"
                            placeholder="<p>Hi {first_name}, …</p>"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="tpl_variables"><?= lang('Guests.tplFieldVariables') ?> <span class="text-muted small">(<?= lang('Guests.tplFieldVariablesHint') ?>)</span></label>
                        <input type="text" id="tpl_variables" class="form-control"
                            placeholder="{first_name},{church_name},{visit_date}">
                        <div class="form-text"><?= lang('Guests.tplFieldVariablesHelp') ?></div>
                    </div>
                </div>

                <div id="templateModalErrors" class="alert alert-danger mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-outline-info me-auto" id="btnPreviewTemplate">
                    <i class="bi bi-eye"></i> <?= lang('Guests.tplBtnPreview') ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnSaveTemplate">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="templateSpinner"></span>
                    <?= lang('Guests.tplBtnSave') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfName = document.querySelector('meta[name="csrf-token"]')?.getAttribute('name') ?? 'csrf_token';
        const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const saveUrl = '<?= base_url('followup-workflow/templates/save') ?>';
        const deleteUrl = '<?= base_url('followup-workflow/templates/delete/') ?>';

        const modal = document.getElementById('templateModal');
        const bsModal = modal ? new bootstrap.Modal(modal) : null;
        const btnNew = document.getElementById('btnNewTemplate');
        const btnNewEmpty = document.getElementById('btnNewTemplateEmpty');
        const btnSave = document.getElementById('btnSaveTemplate');
        const spinner = document.getElementById('templateSpinner');
        const errBox = document.getElementById('templateModalErrors');
        const channelSel = document.getElementById('tpl_channel');
        const subjectRow = document.getElementById('tpl_subject_row');

        function openNew() {
            if (!bsModal) {
                showAppToast(<?= json_encode(lang('Guests.tplModalUnavailable')) ?>, 'warning');
                return;
            }
            document.getElementById('templateModalLabel').textContent = <?= json_encode(lang('Guests.tplModalTitleNew')) ?>;
            document.getElementById('tpl_id').value = '';
            document.getElementById('tpl_name').value = '';
            channelSel.value = '';
            document.getElementById('tpl_subject').value = '';
            document.getElementById('tpl_body_text').value = '';
            document.getElementById('tpl_body_html').value = '';
            document.getElementById('tpl_variables').value = '';
            toggleSubjectRow('');
            errBox.classList.add('d-none');
            bsModal.show();
        }

        function toggleSubjectRow(channel) {
            if (subjectRow) subjectRow.classList.toggle('d-none', channel !== 'email');
        }

        channelSel?.addEventListener('change', () => toggleSubjectRow(channelSel.value));

        btnNew?.addEventListener('click', openNew);
        btnNewEmpty?.addEventListener('click', function(e) {
            e.preventDefault();
            openNew();
        });

        // Edit existing template
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-edit-template');
            if (!btn) return;
            if (!bsModal) {
                showAppToast(<?= json_encode(lang('Guests.tplModalUnavailable')) ?>, 'warning');
                return;
            }

            document.getElementById('templateModalLabel').textContent = <?= json_encode(lang('Guests.tplModalTitleEdit')) ?>;
            document.getElementById('tpl_id').value = btn.dataset.id;
            document.getElementById('tpl_name').value = btn.dataset.name;
            channelSel.value = btn.dataset.channel;
            toggleSubjectRow(btn.dataset.channel);
            document.getElementById('tpl_subject').value = btn.dataset.subject ?? '';
            document.getElementById('tpl_body_text').value = btn.dataset.bodyText ?? '';
            document.getElementById('tpl_body_html').value = btn.dataset.bodyHtml ?? '';
            document.getElementById('tpl_variables').value = btn.dataset.variables ?? '';
            errBox.classList.add('d-none');
            bsModal.show();
        });

        // Save template
        btnSave?.addEventListener('click', function() {
            const id = document.getElementById('tpl_id').value;
            const url = id ? saveUrl + '/' + id : saveUrl;
            const name = document.getElementById('tpl_name').value.trim();
            const channel = channelSel?.value ?? '';
            const bodyText = document.getElementById('tpl_body_text').value.trim();

            if (!name || !channel || !bodyText) {
                errBox.textContent = <?= json_encode(lang('Guests.tplSaveRequired')) ?>;
                errBox.classList.remove('d-none');
                return;
            }

            btnSave.disabled = true;
            spinner?.classList.remove('d-none');
            errBox.classList.add('d-none');

            const params = new URLSearchParams({
                [csrfName]: getCsrf(),
                name: name,
                channel: channel,
                subject: document.getElementById('tpl_subject').value,
                body_text: bodyText,
                body_html: document.getElementById('tpl_body_html').value,
                variables: document.getElementById('tpl_variables').value,
            });

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bsModal?.hide();
                        showAppToast(<?= json_encode(lang('Guests.tplSaved')) ?>, 'primary');
                        setTimeout(() => location.reload(), 700);
                    } else {
                        const msgs = data.errors ?
                            Object.values(data.errors).join('<br>') :
                            (data.message ?? <?= json_encode(lang('Guests.tplSaveFailed')) ?>);
                        errBox.innerHTML = msgs;
                        errBox.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    errBox.textContent = <?= json_encode(lang('Guests.tplNetworkError')) ?>;
                    errBox.classList.remove('d-none');
                })
                .finally(() => {
                    btnSave.disabled = false;
                    spinner?.classList.add('d-none');
                });
        });

        // Delete template
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-delete-template');
            if (!btn) return;
            const id = btn.dataset.id;
            const name = btn.dataset.name;

            showAppConfirm(
                <?= json_encode(lang('Guests.tplDeleteConfirmTitle')) ?>,
                <?= json_encode(lang('Guests.tplDeleteConfirmBody', ['{name}'])) ?>.replace('{name}', name),
                function() {
                    fetch(deleteUrl + id, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: csrfName + '=' + encodeURIComponent(getCsrf()),
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showAppToast(<?= json_encode(lang('Guests.tplDeleted')) ?>, 'primary');
                                setTimeout(() => location.reload(), 700);
                            } else {
                                showAppToast(data.message ?? <?= json_encode(lang('Guests.tplDeleteFailed')) ?>, 'danger');
                            }
                        })
                        .catch(() => showAppToast(<?= json_encode(lang('Guests.tplNetworkError')) ?>, 'danger'));
                },
                <?= json_encode(lang('App.delete')) ?>
            );
        });

        // Initial state
        toggleSubjectRow(channelSel?.value ?? '');
    });
</script>

<!-- ── Live-Preview Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="tplPreviewModal" tabindex="-1" aria-labelledby="tplPreviewModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tplPreviewModalLabel">
                    <i class="bi bi-eye me-1"></i> <?= lang('Guests.tplPreviewModalTitle') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Guest selector row -->
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label mb-1 small fw-semibold" for="previewGuestSel">
                            <?= lang('Guests.tplPreviewGuestLabel') ?>
                        </label>
                        <select id="previewGuestSel" class="form-select form-select-sm">
                            <option value=""><?= lang('Guests.tplPreviewGuestDefault') ?></option>
                            <?php foreach ($guestsForPreview as $g): ?>
                                <option value="<?= (int) $g['id'] ?>">
                                    <?= esc($g['first_name'] . ' ' . $g['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnRefreshPreview">
                            <span class="spinner-border spinner-border-sm d-none me-1" id="previewSpinner"></span>
                            <i class="bi bi-arrow-clockwise"></i> <?= lang('Guests.tplPreviewRefreshBtn') ?>
                        </button>
                    </div>
                </div>

                <div id="previewAlertBox" class="alert alert-danger d-none mb-3"></div>

                <!-- Subject (email only) -->
                <div id="previewSubjectRow" class="mb-3 d-none">
                    <p class="text-muted small mb-1 fw-semibold"><?= lang('Guests.tplPreviewSubjectLabel') ?></p>
                    <p id="previewSubjectOut" class="p-2 bg-light rounded border small mb-0"></p>
                </div>

                <!-- Plain-text body (always shown) -->
                <div class="mb-3">
                    <p class="text-muted small mb-1 fw-semibold"><?= lang('Guests.tplPreviewBodyTextLabel') ?></p>
                    <pre id="previewBodyTextOut" class="p-3 bg-light rounded border small mb-0" style="white-space: pre-wrap; word-break: break-word;"></pre>
                </div>

                <!-- HTML body (shown only when non-empty) -->
                <div id="previewBodyHtmlRow" class="d-none">
                    <p class="text-muted small mb-1 fw-semibold"><?= lang('Guests.tplPreviewBodyHtmlLabel') ?></p>
                    <div id="previewBodyHtmlOut" class="p-3 border rounded bg-white small"></div>
                    <details class="mt-2">
                        <summary class="text-muted small"><?= lang('Guests.tplPreviewRawHtml') ?></summary>
                        <pre id="previewBodyHtmlRaw" class="p-2 bg-light rounded border small mt-1" style="white-space: pre-wrap; word-break: break-word;"></pre>
                    </details>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const previewUrl = '<?= base_url('followup-workflow/templates/preview-parsed') ?>';
        const csrfName = document.querySelector('meta[name="csrf-token"]')?.getAttribute('name') ?? 'csrf_token';
        const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const previewModal = document.getElementById('tplPreviewModal');
        const bsPreviewModal = previewModal ? new bootstrap.Modal(previewModal) : null;
        const guestSel = document.getElementById('previewGuestSel');
        const refreshBtn = document.getElementById('btnRefreshPreview');
        const previewSpinner = document.getElementById('previewSpinner');
        const alertBox = document.getElementById('previewAlertBox');
        const subjectRow = document.getElementById('previewSubjectRow');
        const subjectOut = document.getElementById('previewSubjectOut');
        const bodyTextOut = document.getElementById('previewBodyTextOut');
        const bodyHtmlRow = document.getElementById('previewBodyHtmlRow');
        const bodyHtmlOut = document.getElementById('previewBodyHtmlOut');
        const bodyHtmlRaw = document.getElementById('previewBodyHtmlRaw');

        function getCurrentTemplateId() {
            return document.getElementById('tpl_id')?.value ?? '';
        }

        function loadPreview() {
            const templateId = getCurrentTemplateId();
            if (!templateId) {
                if (alertBox) {
                    alertBox.textContent = <?= json_encode(lang('Guests.tplPreviewSaveFirst')) ?>;
                    alertBox.classList.remove('d-none');
                }
                return;
            }
            alertBox?.classList.add('d-none');

            refreshBtn && (refreshBtn.disabled = true);
            previewSpinner?.classList.remove('d-none');

            const params = new URLSearchParams({
                [csrfName]: getCsrf(),
                template_id: templateId,
                guest_id: guestSel?.value ?? '',
            });

            fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alertBox.textContent = data.message ?? <?= json_encode(lang('Guests.tplPreviewFailed')) ?>;
                        alertBox.classList.remove('d-none');
                        return;
                    }

                    // Subject
                    if (data.subject) {
                        subjectOut.textContent = data.subject;
                        subjectRow?.classList.remove('d-none');
                    } else {
                        subjectRow?.classList.add('d-none');
                    }

                    // Plain text
                    bodyTextOut.textContent = data.body_text ?? '';

                    // HTML
                    if (data.body_html) {
                        bodyHtmlOut.innerHTML = data.body_html;
                        bodyHtmlRaw.textContent = data.body_html;
                        bodyHtmlRow?.classList.remove('d-none');
                    } else {
                        bodyHtmlRow?.classList.add('d-none');
                    }
                })
                .catch(() => {
                    alertBox.textContent = <?= json_encode(lang('Guests.tplNetworkError')) ?>;
                    alertBox.classList.remove('d-none');
                })
                .finally(() => {
                    refreshBtn && (refreshBtn.disabled = false);
                    previewSpinner?.classList.add('d-none');
                });
        }

        // Open preview from template modal footer button
        document.getElementById('btnPreviewTemplate')?.addEventListener('click', function() {
            const templateId = getCurrentTemplateId();
            if (!templateId) {
                showAppToast(<?= json_encode(lang('Guests.tplPreviewSaveFirstToast')) ?>, 'warning');
                return;
            }
            // Reset state
            alertBox?.classList.add('d-none');
            subjectRow?.classList.add('d-none');
            bodyHtmlRow?.classList.add('d-none');
            bodyTextOut.textContent = '';
            if (bsPreviewModal) {
                bsPreviewModal.show();
                loadPreview();
            } else {
                showAppToast('Preview modal unavailable – please refresh.', 'warning');
            }
        });

        // Refresh on guest change
        guestSel?.addEventListener('change', function() {
            if (previewModal?.classList.contains('show')) {
                loadPreview();
            }
        });

        // Manual refresh button
        refreshBtn?.addEventListener('click', loadPreview);
    });
</script>

<?= $this->endSection() ?>