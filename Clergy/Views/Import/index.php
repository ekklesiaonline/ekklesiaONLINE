<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$importResult = session('import_result');
$importErrors = session('import_errors');
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
</div>

<!-- ══════════════════════════════════════════
     Import Result Banner
══════════════════════════════════════════ -->
<?php if ($importResult !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong><?= lang('Visitors.importComplete') ?></strong>
        <?= lang('Visitors.importNewVisitors', [(int) $importResult['inserted']]) ?>,
        <?= lang('Visitors.importUpdatedVisitors', [(int) $importResult['updated']]) ?>.
        <?php if (! empty($importResult['row_errors'])): ?>
            <strong><?= lang('Visitors.importRowErrorsNote', [count($importResult['row_errors'])]) ?></strong>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <?php if (! empty($importResult['row_errors'])): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark fw-semibold">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> <?= lang('Visitors.importRowErrors') ?>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($importResult['row_errors'] as $re): ?>
                    <li class="list-group-item py-2 small">
                        <strong><?= lang('Visitors.importRow') ?> <?= (int) $re['row'] ?>:</strong>
                        <?= esc(implode('; ', (array) $re['errors'])) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (! empty($importErrors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php foreach ((array) $importErrors as $msg): ?>
            <div><?= esc($msg) ?></div>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Left: CSV upload card ──────────────────────────────── -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> <?= lang('Visitors.importFromCsv') ?>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <?= lang('Visitors.importIntro') ?>
                </p>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Visitors.importColColumn') ?></th>
                                <th scope="col"><?= lang('Visitors.importColRequired') ?></th>
                                <th scope="col"><?= lang('Visitors.importColNotes') ?></th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <tr>
                                <td><code>first_name</code></td>
                                <td><span class="badge border border-danger text-danger fw-normal"><?= lang('App.yes') ?></span></td>
                                <td><?= lang('Visitors.importFirstNameNote') ?></td>
                            </tr>
                            <tr>
                                <td><code>last_name</code></td>
                                <td><span class="badge border border-danger text-danger fw-normal"><?= lang('App.yes') ?></span></td>
                                <td><?= lang('Visitors.importLastNameNote') ?></td>
                            </tr>
                            <tr>
                                <td><code>email</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span></td>
                                <td><?= lang('Visitors.importEmailNote') ?></td>
                            </tr>
                            <tr>
                                <td><code>phone</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span></td>
                                <td><?= lang('Visitors.importPhoneNote') ?></td>
                            </tr>
                            <tr>
                                <td><code>address</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span></td>
                                <td><?= lang('Visitors.importAddressNote') ?></td>
                            </tr>
                            <tr>
                                <td><code>interests</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span></td>
                                <td><?= lang('Visitors.importInterestsNote') ?> <code>children,music</code>.</td>
                            </tr>
                            <tr>
                                <td><code>visit_date</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span></td>
                                <td><?= lang('Visitors.importVisitDateNote') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <a href="<?= base_url('visitors/import/sample-csv') ?>" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-download me-1" aria-hidden="true"></i> <?= lang('Visitors.importSampleCsv') ?>
                </a>

                <!-- Upload form -->
                <form id="csvImportForm" method="post" action="<?= base_url('visitors/import/process') ?>"
                    enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="csv_file">
                            <?= lang('Visitors.importCsvFile') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="file" id="csv_file" name="csv_file"
                            class="form-control" accept=".csv,.txt" required>
                        <div class="form-text"><?= lang('Visitors.importCsvHint') ?></div>
                    </div>

                    <div id="csvImportErrors" class="alert alert-danger d-none mb-3"></div>
                    <div id="csvImportProgress" class="d-none mb-3">
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                        </div>
                        <p class="text-muted small mt-2 mb-0"><?= lang('Visitors.importUploading') ?></p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnImportCsv">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="importSpinner"></span>
                        <i class="bi bi-upload me-1" aria-hidden="true"></i> <?= lang('Visitors.importCsvBtn') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right: QR code / digital card section ─────────────── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-qr-code me-1" aria-hidden="true"></i> <?= lang('Visitors.digitalCardTitle') ?>
            </div>
            <div class="card-body text-center">
                <p class="text-muted small mb-3">
                    <?= lang('Visitors.digitalCardIntro') ?>
                </p>

                <!-- QR code image – served from the server-side proxy route -->
                <img src="<?= base_url('visitors/import/qr') ?>"
                    alt="<?= esc(lang('Visitors.importQrAlt', [$parish['name']])) ?>"
                    class="img-fluid mb-3 border rounded p-2"
                    style="max-width: 220px;"
                    id="qrCodeImg">

                <div class="d-flex flex-column gap-2 align-items-center">
                    <!-- Copy link button -->
                    <div class="input-group" style="max-width: 340px;">
                        <input type="text" class="form-control form-control-sm font-monospace"
                            id="cardUrlInput"
                            value="<?= esc($cardUrl) ?>"
                            readonly>
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="btnCopyLink"
                            title="<?= esc(lang('App.copyLink')) ?>">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Download QR button -->
                    <a href="<?= base_url('visitors/import/qr') ?>"
                        download="connection-card-qr.png"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1" aria-hidden="true"></i> <?= lang('Visitors.downloadQr') ?>
                    </a>

                    <!-- Open in new tab -->
                    <a href="<?= esc($cardUrl) ?>" target="_blank" rel="noopener noreferrer"
                        class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> <?= lang('Visitors.previewCardForm') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Interests hint card -->
        <div class="card mt-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-tags me-1" aria-hidden="true"></i> <?= lang('Visitors.interestSlugsTitle') ?>
            </div>
            <div class="card-body p-2">
                <div class="d-flex flex-wrap gap-1">
                    <?php
                    $slugs = ['children', 'youth', 'music', 'missions', 'small_groups', 'prayer', 'serving', 'mens', 'women', 'seniors', 'counseling', 'evangelism'];
                    foreach ($slugs as $slug): ?>
                        <code class="badge border border-secondary text-body fw-normal small"><?= esc($slug) ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /row -->

<script>
    (function() {
        const i18n = {
            linkCopied: '<?= esc(lang('Visitors.importLinkCopied'), 'js') ?>',
            linkCopiedShort: '<?= esc(lang('Visitors.importLinkCopiedShort'), 'js') ?>',
            selectFile: '<?= esc(lang('Visitors.importSelectFile'), 'js') ?>',
            resultSummary: '<?= esc(lang('Visitors.importResultSummary'), 'js') ?>',
            rowErrorsSuffix: '<?= esc(lang('Visitors.importRowErrorsSuffix'), 'js') ?>',
            importFailed: '<?= esc(lang('Visitors.importFailed'), 'js') ?>',
            networkError: '<?= esc(lang('Visitors.importNetworkError'), 'js') ?>',
            rowErrors: '<?= esc(lang('Visitors.importRowErrors'), 'js') ?>',
            row: '<?= esc(lang('Visitors.importRow'), 'js') ?>',
        };

        // ── Copy link
        document.getElementById('btnCopyLink')?.addEventListener('click', function() {
            const input = document.getElementById('cardUrlInput');
            if (!input) return;
            navigator.clipboard?.writeText(input.value)
                .then(() => showAppToast(i18n.linkCopied, 'primary'))
                .catch(() => {
                    input.select();
                    document.execCommand('copy');
                    showAppToast(i18n.linkCopiedShort, 'primary');
                });
        });

        // ── Ajax CSV submission
        const form = document.getElementById('csvImportForm');
        const btn = document.getElementById('btnImportCsv');
        const spinner = document.getElementById('importSpinner');
        const errBox = document.getElementById('csvImportErrors');
        const progress = document.getElementById('csvImportProgress');

        form?.addEventListener('submit', function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('csv_file');
            if (!fileInput?.files?.length) {
                errBox.textContent = i18n.selectFile;
                errBox.classList.remove('d-none');
                return;
            }

            errBox.classList.add('d-none');
            progress.classList.remove('d-none');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const fd = new FormData(form);

            fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                })
                .then(r => r.json())
                .then(data => {
                    progress.classList.add('d-none');

                    if (data.success) {
                        const r = data.result;
                        let msg = i18n.resultSummary
                            .replace('{0}', r.inserted)
                            .replace('{1}', r.updated);
                        if (r.row_errors && r.row_errors.length) {
                            msg += ` ${r.row_errors.length} ${i18n.rowErrorsSuffix}`;
                            renderRowErrors(r.row_errors);
                        }
                        showAppToast(msg, 'primary');
                        form.reset();
                    } else {
                        const msgs = data.errors ?
                            Object.values(data.errors).join('\n') :
                            (data.message ?? i18n.importFailed);
                        errBox.textContent = msgs;
                        errBox.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    progress.classList.add('d-none');
                    errBox.textContent = i18n.networkError;
                    errBox.classList.remove('d-none');
                })
                .finally(() => {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                });
        });

        function renderRowErrors(errors) {
            let html = `<strong>${i18n.rowErrors}:</strong><ul class="mb-0 mt-1">`;
            errors.forEach(function(re) {
                html += `<li>${i18n.row} ${re.row}: ${re.errors.join('; ')}</li>`;
            });
            html += '</ul>';
            errBox.innerHTML = html;
            errBox.classList.remove('d-none');
        }
    }());
</script>

<?= $this->endSection() ?>