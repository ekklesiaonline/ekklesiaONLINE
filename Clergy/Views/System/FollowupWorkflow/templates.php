<?php
$layout    = $layout ?? 'Clergy\\Views\\layouts/main';
$routeBase = $routeBase ?? 'system/followup-workflow';
$scopeMode = $scopeMode ?? 'system';
?>
<?= $this->extend($layout) ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-outline-primary" id="btnNewTemplate">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.tplModalTitleNew') ?>
    </button>
    <a href="<?= base_url($routeBase . '/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.pageTitleSequences') ?>
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($templates)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-text fs-2 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Visitors.tplEmpty') ?>
                <a href="#" id="btnNewTemplateEmpty"><?= lang('Visitors.tplEmptyCreate') ?></a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Visitors.tplColName') ?></th>
                            <th scope="col"><?= lang('Visitors.tplColChannel') ?></th>
                            <th scope="col"><?= lang('Visitors.tplColSubjectPreview') ?></th>
                            <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $tpl): ?>
                            <tr data-template-id="<?= (int) $tpl['id'] ?>">
                                <td>
                                    <?= esc($tpl['name']) ?>
                                    <?php if (($tpl['scope'] ?? 'global') === 'diocese'): ?>
                                        <span class="badge border border-primary text-primary fw-normal ms-1"><?= esc($dioceseName ?? lang('Visitors.seqScopeDiocese')) ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-info text-info fw-normal ms-1"><?= lang('Visitors.seqScopeGlobal') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc(strtoupper($tpl['channel'])) ?></span></td>
                                <td class="text-muted small"><?= esc((string) ($tpl['subject'] ?? $tpl['body_text'] ?? '')) ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <button class="dropdown-item btn-edit-template"
                                                    type="button"
                                                    data-id="<?= (int) $tpl['id'] ?>"
                                                    data-channel="<?= esc($tpl['channel'], 'attr') ?>"
                                                    data-name="<?= esc($tpl['name'], 'attr') ?>"
                                                    data-subject="<?= esc((string) ($tpl['subject'] ?? ''), 'attr') ?>"
                                                    data-body-text="<?= esc($tpl['body_text'], 'attr') ?>"
                                                    data-body-html="<?= esc((string) ($tpl['body_html'] ?? ''), 'attr') ?>"
                                                    data-variables="<?= esc((string) ($tpl['variables'] ?? ''), 'attr') ?>">
                                                    <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger btn-delete-template"
                                                    type="button"
                                                    data-id="<?= (int) $tpl['id'] ?>"
                                                    data-name="<?= esc($tpl['name'], 'attr') ?>">
                                                    <i class="bi bi-trash me-2" aria-hidden="true"></i><?= lang('App.delete') ?>
                                                </button>
                                            </li>
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

<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalLabel"><?= lang('Visitors.tplModalTitleNew') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tpl_id" value="">

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="tpl_name"><?= lang('Visitors.tplFieldName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="tpl_name" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="tpl_channel"><?= lang('Visitors.tplFieldChannel') ?> <span class="text-danger">*</span></label>
                        <select id="tpl_channel" class="form-select" required>
                            <option value=""><?= lang('Visitors.tplFieldChannelSelect') ?></option>
                            <option value="text"><?= lang('Visitors.tplChannelText') ?></option>
                            <option value="email"><?= lang('Visitors.tplChannelEmail') ?></option>
                            <option value="call"><?= lang('Visitors.tplChannelCall') ?></option>
                            <option value="mail"><?= lang('Visitors.tplChannelMail') ?></option>
                            <option value="gift"><?= lang('Visitors.tplChannelGift') ?></option>
                        </select>
                    </div>

                    <div class="col-12 d-none" id="tpl_subject_row">
                        <label class="form-label" for="tpl_subject"><?= lang('Visitors.tplFieldSubject') ?></label>
                        <input type="text" id="tpl_subject" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tpl_body_text"><?= lang('Visitors.tplFieldBodyText') ?> <span class="text-danger">*</span></label>
                        <textarea id="tpl_body_text" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tpl_body_html"><?= lang('Visitors.tplFieldBodyHtml') ?> <?= lang('Visitors.tplFieldBodyHtmlOptional') ?></label>
                        <textarea id="tpl_body_html" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tpl_variables"><?= lang('Visitors.tplFieldVariables') ?></label>
                        <input type="text" id="tpl_variables" class="form-control" placeholder="<?= esc(lang('Visitors.tplFieldVariablesHint'), 'attr') ?>">
                        <div class="form-text"><?= lang('Visitors.tplFieldVariablesHelp') ?></div>
                    </div>
                </div>

                <div id="templateModalErrors" class="alert alert-danger mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('Visitors.seqBtnCancel') ?></button>
                <button type="button" class="btn btn-primary" id="btnSaveTemplate">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="templateSpinner"></span>
                    <?= lang('Visitors.tplBtnSave') ?>
                </button>
            </div>
        </div>
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
        const saveUrl = <?= json_encode(base_url($routeBase . '/templates/save')) ?>;
        const deleteUrl = <?= json_encode(base_url($routeBase . '/templates/delete/')) ?>;

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
            if (!bsModal) return;
            document.getElementById('templateModalLabel').textContent = <?= json_encode(lang('Visitors.tplModalTitleNew')) ?>;
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
        btnNewEmpty?.addEventListener('click', function(e) { e.preventDefault(); openNew(); });

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-edit-template');
            if (!btn) return;
            if (!bsModal) return;
            document.getElementById('templateModalLabel').textContent = <?= json_encode(lang('Visitors.tplModalTitleEdit')) ?>;
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

        btnSave?.addEventListener('click', function() {
            const id = document.getElementById('tpl_id').value;
            const url = id ? saveUrl + '/' + id : saveUrl;
            const name = document.getElementById('tpl_name').value.trim();
            const channel = channelSel?.value ?? '';
            const bodyText = document.getElementById('tpl_body_text').value.trim();

            if (!name || !channel || !bodyText) {
                errBox.textContent = <?= json_encode(lang('Visitors.tplSaveRequired')) ?>;
                errBox.classList.remove('d-none');
                return;
            }

            btnSave.disabled = true;
            spinner?.classList.remove('d-none');
            errBox.classList.add('d-none');

            const params = new URLSearchParams({
                name: name,
                channel: channel,
                subject: document.getElementById('tpl_subject').value,
                body_text: bodyText,
                body_html: document.getElementById('tpl_body_html').value,
                variables: document.getElementById('tpl_variables').value,
            });
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: params.toString(),
            })
                .then(r => { refreshCsrf(r); return r.json(); })
                .then(data => {
                    if (data.success) {
                        bsModal?.hide();
                        showAppToast(<?= json_encode(lang('Visitors.tplSaved')) ?>, 'primary');
                        setTimeout(() => location.reload(), 700);
                    } else {
                        const msgs = data.errors ? Object.values(data.errors).join('<br>') : (data.message ?? <?= json_encode(lang('Visitors.tplSaveFailed')) ?>);
                        errBox.innerHTML = msgs;
                        errBox.classList.remove('d-none');
                    }
                })
                .catch(() => { errBox.textContent = <?= json_encode(lang('Visitors.tplNetworkError')) ?>; errBox.classList.remove('d-none'); })
                .finally(() => { btnSave.disabled = false; spinner?.classList.add('d-none'); });
        });

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-delete-template');
            if (!btn) return;
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            showAppConfirm(
                <?= json_encode(lang('Visitors.tplDeleteConfirmTitle')) ?>,
                <?= json_encode(lang('Visitors.tplDeleteConfirmBody', ['{0}'])) ?>.replace('{0}', name),
                function() {
                    fetch(deleteUrl + id, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: csrfHeaders(),
                    })
                        .then(r => { refreshCsrf(r); return r.json(); })
                        .then(data => {
                            if (data.success) { showAppToast(<?= json_encode(lang('Visitors.tplDeleted')) ?>, 'primary'); setTimeout(() => location.reload(), 700); }
                            else { showAppToast(data.message ?? <?= json_encode(lang('Visitors.tplDeleteFailed')) ?>, 'danger'); }
                        })
                        .catch(() => showAppToast(<?= json_encode(lang('Visitors.tplNetworkError')) ?>, 'danger'));
                },
                <?= json_encode(lang('App.delete')) ?>
            );
        });

        toggleSubjectRow(channelSel?.value ?? '');
    });
</script>

<?= $this->endSection() ?>
