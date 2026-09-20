<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.login')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-1"><?= esc(lang('Auth.loginHeading')) ?></h4>
            <p class="text-body-secondary small mb-4"><?= esc(lang('Auth.loginSubheading')) ?></p>

            <?php if (session('error') !== null): ?>
                <div class="alert alert-danger" role="alert"><?= esc((string) session('error')) ?></div>
            <?php elseif (session('errors') !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?php if (is_array(session('errors'))): ?>
                        <?php foreach (session('errors') as $error): ?>
                            <?= esc((string) $error) ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?= esc((string) session('errors')) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (session('message') !== null): ?>
                <div class="alert alert-success" role="alert"><?= esc((string) session('message')) ?></div>
            <?php endif; ?>

            <form action="<?= esc(url_to('login'), 'attr') ?>" method="post">
                <?= csrf_field() ?>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="loginEmail" name="email" inputmode="email" autocomplete="email" placeholder="<?= esc(lang('Auth.loginEmail'), 'attr') ?>" value="<?= esc((string) old('email'), 'attr') ?>" required>
                    <label for="loginEmail"><?= esc(lang('Auth.loginEmail')) ?></label>
                </div>

                <div class="input-group mb-3">
                    <div class="form-floating">
                        <input type="password" class="form-control" id="loginPassword" name="password" autocomplete="current-password" placeholder="<?= esc(lang('Auth.loginPassword'), 'attr') ?>" required>
                        <label for="loginPassword"><?= esc(lang('Auth.loginPassword')) ?></label>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="passwordVisibilityToggle" aria-label="<?= esc(lang('Auth.showPassword'), 'attr') ?>" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember"<?php if (old('remember')): ?> checked<?php endif; ?>>
                        <label class="form-check-label" for="remember"><?= esc(lang('Auth.loginRemember')) ?></label>
                    </div>
                <?php endif; ?>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><?= esc(lang('Auth.loginButton')) ?></button>
                </div>

                <?php if (setting('Auth.allowMagicLinkLogins')): ?>
                    <p class="text-center mt-3 mb-0"><?= esc(lang('Auth.loginForgot')) ?> <a href="<?= esc(url_to('magic-link'), 'attr') ?>"><?= esc(lang('Auth.useMagicLink')) ?></a></p>
                <?php endif; ?>

                <?php if (setting('Auth.allowRegistration')): ?>
                    <p class="text-center mt-2 mb-0"><?= esc(lang('Auth.noAccount')) ?> <a href="<?= esc(url_to('register'), 'attr') ?>"><?= esc(lang('Auth.register')) ?></a></p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
    (function () {
        const passwordInput = document.getElementById('loginPassword');
        const toggleButton = document.getElementById('passwordVisibilityToggle');
        const toggleIcon = toggleButton.querySelector('i');
        const showLabel = <?= json_encode((string) lang('Auth.showPassword'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const hideLabel = <?= json_encode((string) lang('Auth.hidePassword'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        toggleButton.addEventListener('click', function () {
            const showing = passwordInput.type === 'text';
            passwordInput.type = showing ? 'password' : 'text';
            toggleIcon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            toggleButton.setAttribute('aria-label', showing ? showLabel : hideLabel);
            toggleButton.setAttribute('aria-pressed', showing ? 'false' : 'true');
            passwordInput.focus();
        });
    }());
</script>
<?= $this->endSection() ?>
