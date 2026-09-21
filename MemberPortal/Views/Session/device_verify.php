<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?><?= esc($title ?? lang('Auth.verifyDeviceTitle')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 mt-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning-subtle">
                <h4 class="mb-0">
                    <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
                    <?= lang('Auth.verifyNewDeviceHeading') ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if (session()->has('error')): ?>
                    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
                <?php endif; ?>

                <?php if (session()->has('success')): ?>
                    <div class="alert alert-success"><?= esc(session('success')) ?></div>
                <?php endif; ?>

                <?php if (!empty($mailFailed)): ?>
                    <div class="alert alert-warning">
                        <?= lang('Auth.verifyEmailFailed') ?>
                    </div>
                <?php endif; ?>

                <p class="mb-3">
                    <?= lang('Auth.verifyCodeIntro') ?>
                </p>

                <?php if (!empty($isExpired)): ?>
                    <div class="alert alert-warning">
                        <?= lang('Auth.verifyCodeExpired') ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">
                        <?= lang('Auth.verifyCodeExpiresIn', [max(0, (int) ceil(($secondsRemaining ?? 0) / 60))]) ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= base_url('session/device-verify') ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="verificationCode" class="form-label"><?= lang('Auth.verificationCode') ?></label>
                        <input
                            type="text"
                            id="verificationCode"
                            name="code"
                            class="form-control"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="123456"
                            required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <?= lang('Auth.verifyDeviceButton') ?>
                    </button>
                </form>

                <form method="post" action="<?= base_url('session/device-verify/resend') ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary w-100"><?= lang('Auth.resendCode') ?></button>
                </form>

                <div class="text-center mt-3">
                    <a class="text-muted small" href="<?= base_url('logout') ?>"><?= lang('Auth.logoutLink') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>