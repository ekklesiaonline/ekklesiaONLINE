<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?><?= lang('Auth.setPasswordTitle') ?><?= $this->endSection() ?>

<?php
$minLength = (int) ($passwordPolicy['min_length'] ?? 12);
$requireUppercase = (bool) ($passwordPolicy['require_uppercase'] ?? true);
$requireLowercase = (bool) ($passwordPolicy['require_lowercase'] ?? true);
$requireDigit = (bool) ($passwordPolicy['require_digit'] ?? true);
$requireSpecial = (bool) ($passwordPolicy['require_special'] ?? true);
$specialChars = esc((string) ($passwordPolicy['special_chars'] ?? '@$!%*?&#^_-'));

$parts = [];
if ($requireUppercase) {
    $parts[] = lang('System.passwordRequiresUppercase');
}
if ($requireLowercase) {
    $parts[] = lang('System.passwordRequiresLowercase');
}
if ($requireDigit) {
    $parts[] = lang('System.passwordRequiresDigit');
}
if ($requireSpecial) {
    $parts[] = lang('System.passwordRequiresSpecial');
}
?>

<?= $this->section('main') ?>
<div class="row justify-content-center mt-5">
    <div class="col-sm-10 col-md-6 col-lg-5">

        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h4 class="mb-1"><?= lang('Auth.setPasswordTitle') ?></h4>
                <p class="text-body-secondary small mb-3">
                    <?= lang('Auth.setPasswordDescription') ?>
                </p>

                <?php if (session()->has('errors')) : ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">
                            <?php foreach (session('errors') as $error) : ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                <?php endif ?>

                <form action="<?= site_url('auth/set-password') ?>" method="post" id="setPasswordForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="new_password" class="form-label"><?= lang('Auth.resetNewPassword') ?></label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control"
                            autocomplete="new-password"
                            required
                            minlength="<?= (int) $minLength ?>">
                        <div class="form-text">
                            <ul class="list-unstyled small mb-0" id="passwordRequirements">
                                <li id="req-length" class="text-danger"><?= lang('System.passwordTooShort', [$minLength]) ?></li>
                                <?php if ($requireUppercase): ?><li id="req-uppercase" class="text-danger"><?= lang('System.passwordRequiresUppercase') ?></li><?php endif; ?>
                                <?php if ($requireLowercase): ?><li id="req-lowercase" class="text-danger"><?= lang('System.passwordRequiresLowercase') ?></li><?php endif; ?>
                                <?php if ($requireDigit): ?><li id="req-digit" class="text-danger"><?= lang('System.passwordRequiresDigit') ?></li><?php endif; ?>
                                <?php if ($requireSpecial): ?><li id="req-special" class="text-danger"><?= lang('System.passwordRequiresSpecial') ?></li><?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="new_password_confirm" class="form-label"><?= lang('Auth.resetConfirmPass') ?></label>
                        <input
                            type="password"
                            id="new_password_confirm"
                            name="new_password_confirm"
                            class="form-control"
                            autocomplete="new-password"
                            required
                            minlength="<?= (int) $minLength ?>">
                        <div class="invalid-feedback" id="confirmMismatch">
                            <?= lang('System.passwordsDoNotMatch') ?>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled><?= lang('Auth.setPasswordButton') ?></button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>
<?= $this->endSection() ?>

<?php $this->section('pageScripts') ?>
<script>
    const passwordPolicy = <?= json_encode($passwordPolicy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('new_password_confirm');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('setPasswordForm');

    const requirements = {
        length: { el: document.getElementById('req-length'), test: v => v.length >= (passwordPolicy.min_length ?? 12) },
        uppercase: { el: document.getElementById('req-uppercase'), test: v => /[A-Z]/.test(v) },
        lowercase: { el: document.getElementById('req-lowercase'), test: v => /[a-z]/.test(v) },
        digit: { el: document.getElementById('req-digit'), test: v => /\d/.test(v) },
        special: { el: document.getElementById('req-special'), test: v => {
            if (!passwordPolicy.require_special) return true;
            const chars = passwordPolicy.special_chars ?? '@$!%*?&#^_';
            return new RegExp('[' + chars.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ']').test(v);
        }},
    };

    function updateValidation() {
        const value = passwordInput.value;
        let allMet = true;
        for (const key in requirements) {
            const item = requirements[key];
            if (!item.el) continue;
            const met = item.test(value);
            item.el.classList.toggle('text-success', met);
            item.el.classList.toggle('text-danger', !met);
            if (!met) allMet = false;
        }

        const confirmValue = confirmInput.value;
        const match = value !== '' && confirmValue !== '' && value === confirmValue;
        if (confirmValue !== '' && value !== confirmValue) {
            confirmInput.classList.add('is-invalid');
            allMet = false;
        } else {
            confirmInput.classList.remove('is-invalid');
        }

        submitBtn.disabled = !(allMet && match);
    }

    passwordInput.addEventListener('input', updateValidation);
    confirmInput.addEventListener('input', updateValidation);
    form.addEventListener('submit', (e) => {
        updateValidation();
        if (submitBtn.disabled) {
            e.preventDefault();
        }
    });
</script>
<?php $this->endSection() ?>
