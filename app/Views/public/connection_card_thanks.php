<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="card card-public text-center">
    <div class="brand-bar">
        <h4 class="mb-0 fw-bold">
            <?= esc($church['name'] ?? 'Thank You') ?>
        </h4>
    </div>

    <div class="card-body p-5">
        <div class="mb-4" style="font-size: 3.5rem; line-height: 1;">
            🙏
        </div>
        <h3 class="fw-bold mb-2">Thank You!</h3>
        <p class="text-muted mb-4">
            We've received your connection card. Our team will be in touch soon.<br>
            We look forward to seeing you again!
        </p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <button type="button" class="btn btn-primary" id="btnDone">
                <i class="bi bi-check2 me-1"></i> Done
            </button>
            <a href="<?= base_url('connect/' . esc($church['public_token'] ?? '')) ?>"
                class="btn btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Fill in another card
            </a>
        </div>

        <script>
            document.getElementById('btnDone').addEventListener('click', function() {
                var closed = window.close();
                if (!closed) {
                    this.textContent = 'You can close this window.';
                    this.disabled = true;
                }
            });
        </script>
    </div>

    <div class="card-footer text-center text-muted small py-2">
        <i class="bi bi-shield-lock me-1"></i> Your information is kept private and secure.
    </div>
</div>

<?= $this->endSection() ?>