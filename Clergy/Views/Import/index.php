<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$importResult = session('import_result');
$importErrors = session('import_errors');
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Guests.backToGuests') ?>
    </a>
</div>

<!-- ══════════════════════════════════════════
     Import Result Banner
══════════════════════════════════════════ -->
<?php if ($importResult !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Import complete:</strong>
        <?= (int) $importResult['inserted'] ?> new guest(s) added,
        <?= (int) $importResult['updated'] ?> existing guest(s) updated.
        <?php if (! empty($importResult['row_errors'])): ?>
            <strong><?= count($importResult['row_errors']) ?> row(s) had errors — see below.</strong>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <?php if (! empty($importResult['row_errors'])): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark fw-semibold">
                <i class="bi bi-exclamation-triangle me-1"></i> Row Errors
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($importResult['row_errors'] as $re): ?>
                    <li class="list-group-item py-2 small">
                        <strong>Row <?= (int) $re['row'] ?>:</strong>
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
                <i class="bi bi-filetype-csv me-1"></i> Import from CSV
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Upload a CSV file with guest connection cards.
                    The first row must be a header row with the exact column names listed below.
                    Existing guests (matched by e-mail or phone) will have their last-visit date updated;
                    new guests will be inserted and automatically enrolled into all active follow-up
                    sequences.
                </p>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Required?</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <tr>
                                <td><code>first_name</code></td>
                                <td><span class="badge border border-danger text-danger fw-normal">Yes</span></td>
                                <td>Given name</td>
                            </tr>
                            <tr>
                                <td><code>last_name</code></td>
                                <td><span class="badge border border-danger text-danger fw-normal">Yes</span></td>
                                <td>Family name</td>
                            </tr>
                            <tr>
                                <td><code>email</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal">No</span></td>
                                <td>Used for deduplication. Must be a valid e-mail if present.</td>
                            </tr>
                            <tr>
                                <td><code>phone</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal">No</span></td>
                                <td>Used for deduplication when e-mail absent.</td>
                            </tr>
                            <tr>
                                <td><code>address</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal">No</span></td>
                                <td>Mailing address (free text).</td>
                            </tr>
                            <tr>
                                <td><code>interests</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal">No</span></td>
                                <td>Comma-separated interest slugs, e.g. <code>children,music</code>.</td>
                            </tr>
                            <tr>
                                <td><code>visit_date</code></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal">No</span></td>
                                <td>YYYY-MM-DD. Defaults to today if blank.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <a href="<?= base_url('guests/import/sample-csv') ?>" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-download me-1"></i> Download sample CSV
                </a>

                <!-- Upload form -->
                <form id="csvImportForm" method="post" action="<?= base_url('guests/import/process') ?>"
                    enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="csv_file">
                            CSV File <span class="text-danger">*</span>
                        </label>
                        <input type="file" id="csv_file" name="csv_file"
                            class="form-control" accept=".csv,.txt" required>
                        <div class="form-text">Max 2 MB. UTF-8 encoding recommended.</div>
                    </div>

                    <div id="csvImportErrors" class="alert alert-danger d-none mb-3"></div>
                    <div id="csvImportProgress" class="d-none mb-3">
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Uploading and processing…</p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnImportCsv">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="importSpinner"></span>
                        <i class="bi bi-upload me-1"></i> Import CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right: QR code / digital card section ─────────────── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-qr-code me-1"></i> Digital Connection Card
            </div>
            <div class="card-body text-center">
                <p class="text-muted small mb-3">
                    Share this QR code or link with guests so they can fill in their own
                    connection card on their phone. New submissions are automatically enrolled
                    in active follow-up sequences.
                </p>

                <!-- QR code image – served from the server-side proxy route -->
                <img src="<?= base_url('guests/import/qr') ?>"
                    alt="QR Code for <?= esc($parish['name']) ?> connection card"
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
                            title="Copy link">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>

                    <!-- Download QR button -->
                    <a href="<?= base_url('guests/import/qr') ?>"
                        download="connection-card-qr.png"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i> Download QR Image
                    </a>

                    <!-- Open in new tab -->
                    <a href="<?= esc($cardUrl) ?>" target="_blank" rel="noopener noreferrer"
                        class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Preview Card Form
                    </a>
                </div>
            </div>
        </div>

        <!-- Interests hint card -->
        <div class="card mt-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-tags me-1"></i> Recognised Interest Slugs
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
        // ── Copy link
        document.getElementById('btnCopyLink')?.addEventListener('click', function() {
            const input = document.getElementById('cardUrlInput');
            if (!input) return;
            navigator.clipboard?.writeText(input.value)
                .then(() => showAppToast('Link copied to clipboard.', 'primary'))
                .catch(() => {
                    input.select();
                    document.execCommand('copy');
                    showAppToast('Link copied.', 'primary');
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
                errBox.textContent = 'Please select a CSV file.';
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
                        let msg = `Import complete: ${r.inserted} new, ${r.updated} updated.`;
                        if (r.row_errors && r.row_errors.length) {
                            msg += ` ${r.row_errors.length} row error(s) — see below.`;
                            renderRowErrors(r.row_errors);
                        }
                        showAppToast(msg, 'primary');
                        form.reset();
                    } else {
                        const msgs = data.errors ?
                            Object.values(data.errors).join('\n') :
                            (data.message ?? 'Import failed.');
                        errBox.textContent = msgs;
                        errBox.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    progress.classList.add('d-none');
                    errBox.textContent = 'Network error. Please try again.';
                    errBox.classList.remove('d-none');
                })
                .finally(() => {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                });
        });

        function renderRowErrors(errors) {
            let html = '<strong>Row Errors:</strong><ul class="mb-0 mt-1">';
            errors.forEach(function(re) {
                html += `<li>Row ${re.row}: ${re.errors.join('; ')}</li>`;
            });
            html += '</ul>';
            errBox.innerHTML = html;
            errBox.classList.remove('d-none');
        }
    }());
</script>

<?= $this->endSection() ?>