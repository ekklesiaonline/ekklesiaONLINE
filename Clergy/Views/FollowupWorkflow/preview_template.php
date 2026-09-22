<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelBadge = [
    'text'  => 'border border-primary text-primary fw-normal',
    'email' => 'border border-info text-info fw-normal',
    'call'  => 'border border-warning text-warning fw-normal',
    'mail'  => 'border border-secondary text-secondary fw-normal',
    'gift'  => 'border border-success text-success fw-normal',
];
$badge = $channelBadge[$template['channel']] ?? 'border border-secondary text-body fw-normal';
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('followup-workflow/templates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.tplBackToTemplates') ?>
    </a>
    <?php if (!empty($template['parish_id']) && auth()->user()->can('visitor.edit')): ?>
        <button type="button"
            class="btn btn-outline-secondary btn-edit-template"
            data-id="<?= (int) $template['id'] ?>"
            data-channel="<?= esc($template['channel'], 'attr') ?>"
            data-name="<?= esc($template['name'], 'attr') ?>"
            data-subject="<?= esc($template['subject'] ?? '', 'attr') ?>"
            data-body-text="<?= esc($template['body_text'], 'attr') ?>"
            data-body-html="<?= esc($template['body_html'] ?? '', 'attr') ?>"
            data-variables="<?= esc($template['variables'] ?? '', 'attr') ?>">
            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('Visitors.tplEditTemplateBtn') ?>
        </button>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- Template meta ──────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= lang('Visitors.tplInfoCardTitle') ?></div>
            <div class="card-body">
                <dl class="mb-0 small">
                    <dt class="text-muted"><?= lang('Visitors.tplInfoName') ?></dt>
                    <dd class="fw-semibold"><?= esc($template['name']) ?></dd>

                    <dt class="text-muted mt-2"><?= lang('Visitors.tplInfoChannel') ?></dt>
                    <dd><span class="badge <?= $badge ?>"><?= esc($template['channel']) ?></span></dd>

                    <?php if (!empty($template['subject'])): ?>
                        <dt class="text-muted mt-2"><?= lang('Visitors.tplInfoSubjectLabel') ?></dt>
                        <dd><?= esc($template['subject']) ?></dd>
                    <?php endif; ?>

                    <dt class="text-muted mt-2"><?= lang('Visitors.tplInfoScopeLabel') ?></dt>
                    <dd>
                        <?php if ($template['parish_id'] === null): ?>
                            <span class="badge border border-info text-info fw-normal"><?= lang('Visitors.tplScopeGlobal') ?></span>
                        <?php else: ?>
                            <span class="badge border border-secondary text-body fw-normal"><?= lang('Visitors.tplScopeParish') ?></span>
                        <?php endif; ?>
                    </dd>

                    <?php if (!empty($definedVars)): ?>
                        <dt class="text-muted mt-3"><?= lang('Visitors.tplInfoDeclaredTokens') ?></dt>
                        <dd>
                            <?php foreach ($definedVars as $tag): ?>
                                <code class="me-1"><?= esc($tag) ?></code>
                            <?php endforeach; ?>
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Preview ────────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Plain-text preview -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-file-text me-1" aria-hidden="true"></i> <?= lang('Visitors.tplPreviewPlainText') ?>
                </span>
                <small class="text-muted"><?= lang('Visitors.tplPreviewSampleApplied') ?></small>
            </div>
            <div class="card-body">
                <?php if (!empty($preview['subject'])): ?>
                    <p class="mb-1">
                        <strong><?= lang('Visitors.tplInfoSubjectLabel') ?>:</strong> <?= esc($preview['subject']) ?>
                    </p>
                    <hr>
                <?php endif; ?>
                <pre class="mb-0 small" style="white-space: pre-wrap; word-break: break-word;"><?= esc($preview['body_text']) ?></pre>
            </div>
        </div>

        <!-- HTML preview (only shown when body_html exists) -->
        <?php if (!empty($preview['body_html'])): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-code-slash me-1" aria-hidden="true"></i> <?= lang('Visitors.tplPreviewHtmlTitle') ?>
                    </span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary active" id="btnHtmlRendered"><?= lang('Visitors.tplPreviewRendered') ?></button>
                        <button class="btn btn-outline-secondary" id="btnHtmlSource"><?= lang('Visitors.tplPreviewSourceBtn') ?></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Rendered view in sandboxed iframe -->
                    <iframe id="htmlPreviewFrame"
                        sandbox="allow-same-origin"
                        class="w-100 border-0 rounded-bottom"
                        style="min-height: 260px;"
                        title="<?= esc(lang('Visitors.tplHtmlPreviewLabel')) ?>"></iframe>
                    <!-- Raw source -->
                    <pre id="htmlPreviewSource"
                        class="d-none mb-0 p-3 small"
                        style="white-space: pre-wrap; word-break: break-word;"><?= esc($preview['body_html']) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sample substitution table -->
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-table me-1" aria-hidden="true"></i> <?= lang('Visitors.tplSampleValuesTitle') ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Visitors.tplTokenCol') ?></th>
                            <th scope="col"><?= lang('Visitors.tplSampleValueCol') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sampleVars as $token => $value): ?>
                            <tr>
                                <td><code><?= esc($token) ?></code></td>
                                <td><?= esc($value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /col -->
</div><!-- /row -->

<?php $htmlBody = $preview['body_html'] ?? ''; ?>
<?php if (!empty($htmlBody)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inject HTML into iframe once DOM is ready
            const frame = document.getElementById('htmlPreviewFrame');
            const source = document.getElementById('htmlPreviewSource');
            const rawHtml = <?= json_encode($htmlBody) ?>;

            if (frame) {
                const doc = frame.contentDocument ?? frame.contentWindow?.document;
                if (doc) {
                    doc.open();
                    doc.write(rawHtml);
                    doc.close();
                }
            }

            // Toggle rendered / source
            document.getElementById('btnHtmlRendered')?.addEventListener('click', function() {
                frame?.classList.remove('d-none');
                source?.classList.add('d-none');
                this.classList.add('active');
                document.getElementById('btnHtmlSource')?.classList.remove('active');
            });

            document.getElementById('btnHtmlSource')?.addEventListener('click', function() {
                frame?.classList.add('d-none');
                source?.classList.remove('d-none');
                this.classList.add('active');
                document.getElementById('btnHtmlRendered')?.classList.remove('active');
            });
        });
    </script>
<?php endif; ?>

<?= $this->endSection() ?>