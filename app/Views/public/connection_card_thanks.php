<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="card card-public text-center">
    <div class="brand-bar">
        <h4 class="mb-0 fw-bold">
            <?= esc($parish['name'] ?? lang('Visitors.ccThanksFallback')) ?>
        </h4>
    </div>

    <div class="card-body p-5">
        <div class="mb-4" style="font-size: 3.5rem; line-height: 1;">
            🙏
        </div>
        <h3 class="fw-bold mb-2"><?= lang('Visitors.ccThanksTitle') ?></h3>
        <p class="text-muted mb-4">
            <?= lang('Visitors.ccThanksReceived') ?><br>
            <?= lang('Visitors.ccThanksAgain') ?>
        </p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <button type="button" class="btn btn-primary" id="btnDone">
                <i class="bi bi-check2 me-1" aria-hidden="true"></i> <?= lang('Visitors.ccDone') ?>
            </button>
            <a href="<?= base_url('connect/' . esc($parish['public_token'] ?? '')) ?>"
                class="btn btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> <?= lang('Visitors.ccFillAnother') ?>
            </a>
        </div>

        <script>
            document.getElementById('btnDone').addEventListener('click', function() {
                var closed = window.close();
                if (!closed) {
                    this.textContent = '<?= esc(lang('Visitors.ccCloseWindow'), 'js') ?>';
                    this.disabled = true;
                }
            });
        </script>
    </div>

    <div class="card-footer text-center text-muted small py-2">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> <?= lang('Visitors.ccPrivacy') ?>
    </div>
</div>

<?= $this->endSection() ?>