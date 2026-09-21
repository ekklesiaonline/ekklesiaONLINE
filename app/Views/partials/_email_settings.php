<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php

/**
 * Email settings form partial.
 *
 * Required data:
 *   - scope_type       string  'system'|'organisation'|'province'|'diocese'|'parish'
 *   - scope_id         int|null
 *   - can_edit_smtp    bool
 *   - settings         array   keyed by short key (from_email, from_name, reply_to, smtp_*, smtp_crypto)
 *   - has_smtp_pass    bool    whether an encrypted password already exists
 *   - effective_sender array   ['from_email','from_name','reply_to']
 *   - save_url         string
 *   - test_url         string
 *   - back_url         string
 *   - title            string
 *   - errors           array<string,string>
 *   - editing_section  string  ''|'identity'|'smtp' — section rendered in edit mode
 */

$settings    = $settings ?? [];
$errors      = $errors ?? [];
$effective   = $effective_sender ?? ['from_email' => '', 'from_name' => '', 'reply_to' => ''];
$canEditSmtp = $can_edit_smtp ?? false;
$value       = static fn(string $key): string => esc((string) ($settings[$key] ?? ''), 'attr');
$hasError    = static fn(string $key): bool => isset($errors[$key]);
$errorFor    = static fn(string $key): string => $hasError($key) ? '<div class="text-danger small mt-1">' . esc((string) $errors[$key]) . '</div>' : '';

$editingSection   = (string) ($editing_section ?? '');
$identityEditing  = $editingSection === 'identity';
$smtpEditing      = $editingSection === 'smtp' && $canEditSmtp;
$identityDisabled = $identityEditing ? '' : ' disabled';
$smtpDisabled     = $smtpEditing ? '' : ' disabled';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= esc($title) ?></h5>
    <a href="<?= esc($back_url) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= lang('App.back') ?>
    </a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= esc((string) $error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <form action="<?= esc($save_url) ?>" method="post" data-email-section="identity"<?= $identityEditing ? ' data-editing="1"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="scope_type" value="<?= esc($scope_type) ?>">
            <?php if ($scope_id !== null): ?>
                <input type="hidden" name="scope_id" value="<?= (int) $scope_id ?>">
            <?php endif; ?>
            <input type="hidden" name="section" value="identity">

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= lang('Email.identityCard') ?></strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary<?= $identityEditing ? ' d-none' : '' ?>" data-edit-toggle>
                        <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= lang('App.edit') ?>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="email_enabled" name="enabled" value="1"<?= (string) ($settings['enabled'] ?? '') !== '0' ? ' checked' : '' ?><?= $identityDisabled ?>>
                        <label class="form-check-label" for="email_enabled"><?= lang('Email.enabledLabel') ?></label>
                        <div class="form-text"><?= lang('Email.enabledHelp') ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="from_email" class="form-label"><?= lang('Email.fromEmail') ?></label>
                        <input type="email" class="form-control<?= $hasError('from_email') ? ' is-invalid' : '' ?>" id="from_email" name="from_email" value="<?= $value('from_email') ?>"<?= $identityDisabled ?>>
                        <?= $errorFor('from_email') ?>
                    </div>
                    <div class="mb-3">
                        <label for="from_name" class="form-label"><?= lang('Email.fromName') ?></label>
                        <input type="text" class="form-control<?= $hasError('from_name') ? ' is-invalid' : '' ?>" id="from_name" name="from_name" value="<?= $value('from_name') ?>"<?= $identityDisabled ?>>
                        <?= $errorFor('from_name') ?>
                    </div>
                    <div class="mb-3">
                        <label for="reply_to" class="form-label"><?= lang('Email.replyTo') ?></label>
                        <input type="email" class="form-control<?= $hasError('reply_to') ? ' is-invalid' : '' ?>" id="reply_to" name="reply_to" value="<?= $value('reply_to') ?>"<?= $identityDisabled ?>>
                        <?= $errorFor('reply_to') ?>
                    </div>
                </div>
                <div class="card-footer<?= $identityEditing ? '' : ' d-none' ?>" data-edit-actions>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-cancel><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-save me-1" aria-hidden="true"></i><?= lang('App.save') ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <form action="<?= esc($save_url) ?>" method="post" data-email-section="smtp"<?= $smtpEditing ? ' data-editing="1"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="scope_type" value="<?= esc($scope_type) ?>">
            <?php if ($scope_id !== null): ?>
                <input type="hidden" name="scope_id" value="<?= (int) $scope_id ?>">
            <?php endif; ?>
            <input type="hidden" name="section" value="smtp">

            <div class="card mb-4<?= $canEditSmtp ? '' : ' opacity-75' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= lang('Email.smtpCard') ?></strong>
                    <?php if ($canEditSmtp): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary<?= $smtpEditing ? ' d-none' : '' ?>" data-edit-toggle>
                            <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= lang('App.edit') ?>
                        </button>
                    <?php else: ?>
                        <span class="badge border border-warning text-warning fw-normal"><?= lang('Email.smtpViewOnly') ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$canEditSmtp): ?>
                        <p class="text-muted small"><?= lang('Email.smtpRestricted') ?></p>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="smtp_host" class="form-label"><?= lang('Email.smtpHost') ?></label>
                        <input type="text" class="form-control<?= $hasError('smtp_host') ? ' is-invalid' : '' ?>" id="smtp_host" name="smtp_host" value="<?= $value('smtp_host') ?>"<?= $smtpDisabled ?>>
                        <?= $errorFor('smtp_host') ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8 mb-3">
                            <label for="smtp_user" class="form-label"><?= lang('Email.smtpUser') ?></label>
                            <input type="text" class="form-control<?= $hasError('smtp_user') ? ' is-invalid' : '' ?>" id="smtp_user" name="smtp_user" value="<?= $value('smtp_user') ?>"<?= $smtpDisabled ?>>
                            <?= $errorFor('smtp_user') ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="smtp_port" class="form-label"><?= lang('Email.smtpPort') ?></label>
                            <input type="number" class="form-control<?= $hasError('smtp_port') ? ' is-invalid' : '' ?>" id="smtp_port" name="smtp_port" value="<?= $value('smtp_port') ?: '587' ?>"<?= $smtpDisabled ?>>
                            <?= $errorFor('smtp_port') ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="smtp_pass" class="form-label"><?= lang('Email.smtpPass') ?></label>
                        <input type="password" class="form-control<?= $hasError('smtp_pass') ? ' is-invalid' : '' ?>" id="smtp_pass" name="smtp_pass" placeholder="<?= $has_smtp_pass ? lang('Email.smtpPassPlaceholderKeep') : lang('Email.smtpPassPlaceholderNew') ?>"<?= $smtpDisabled ?>>
                        <div class="form-text"><?= lang('Email.smtpGmailHelp') ?></div>
                        <?= $errorFor('smtp_pass') ?>
                    </div>
                    <div class="mb-3">
                        <label for="smtp_crypto" class="form-label"><?= lang('Email.smtpCrypto') ?></label>
                        <select class="form-select<?= $hasError('smtp_crypto') ? ' is-invalid' : '' ?>" id="smtp_crypto" name="smtp_crypto"<?= $smtpDisabled ?>>
                            <?php $crypto = $value('smtp_crypto') ?: 'tls'; ?>
                            <option value="tls"<?= $crypto === 'tls' ? ' selected' : '' ?>>TLS</option>
                            <option value="ssl"<?= $crypto === 'ssl' ? ' selected' : '' ?>>SSL</option>
                            <option value=""<?= $crypto === '' ? ' selected' : '' ?>><?= lang('Email.none') ?></option>
                        </select>
                        <?= $errorFor('smtp_crypto') ?>
                    </div>
                </div>
                <?php if ($canEditSmtp): ?>
                    <div class="card-footer<?= $smtpEditing ? '' : ' d-none' ?>" data-edit-actions>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-cancel><?= lang('App.cancel') ?></button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-save me-1" aria-hidden="true"></i><?= lang('App.save') ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card mb-4">
            <div class="card-header"><strong><?= lang('Email.effectiveSender') ?></strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= lang('Email.fromEmail') ?></dt>
                    <dd class="col-sm-8"><?= esc($effective['from_email'] ?: lang('Email.fallbackDefault')) ?></dd>
                    <dt class="col-sm-4"><?= lang('Email.fromName') ?></dt>
                    <dd class="col-sm-8"><?= esc($effective['from_name'] ?: lang('Email.fallbackDefault')) ?></dd>
                    <dt class="col-sm-4"><?= lang('Email.replyTo') ?></dt>
                    <dd class="col-sm-8"><?= esc($effective['reply_to'] ?: lang('Email.fallbackDefault')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><?= lang('Email.testSend') ?></strong></div>
            <div class="card-body">
                <div id="email-test-result" class="alert d-none" role="alert"></div>
                <form id="email-test-form" action="<?= esc($test_url) ?>" method="post" data-allow-repeat-submit="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="scope_type" value="<?= esc($scope_type) ?>">
                    <?php if ($scope_id !== null): ?>
                        <input type="hidden" name="scope_id" value="<?= (int) $scope_id ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="test_recipient" class="form-label"><?= lang('Email.testRecipient') ?></label>
                        <input type="email" class="form-control" id="test_recipient" name="test_recipient" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary" id="email-test-submit">
                        <i class="bi bi-send me-1" aria-hidden="true"></i><?= lang('Email.sendTest') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const sectionForms = Array.from(document.querySelectorAll('form[data-email-section]'));

    const lockOtherSections = function (activeForm) {
        sectionForms.forEach(function (other) {
            if (other === activeForm) {
                return;
            }
            const btn = other.querySelector('[data-edit-toggle]');
            if (btn) {
                btn.disabled = activeForm !== null;
            }
        });
    };

    sectionForms.forEach(function (sectionForm) {
        const editBtn = sectionForm.querySelector('[data-edit-toggle]');
        const actions = sectionForm.querySelector('[data-edit-actions]');
        const cancelBtn = sectionForm.querySelector('[data-edit-cancel]');
        if (!editBtn || !actions || !cancelBtn) {
            return;
        }
        const fields = sectionForm.querySelectorAll('input:not([type="hidden"]), select');

        const setEditing = function (editing) {
            fields.forEach(function (field) {
                field.disabled = !editing;
            });
            editBtn.classList.toggle('d-none', editing);
            actions.classList.toggle('d-none', !editing);
            lockOtherSections(editing ? sectionForm : null);
            if (editing && fields.length > 0) {
                fields[0].focus();
            }
        };

        editBtn.addEventListener('click', function () {
            setEditing(true);
        });
        cancelBtn.addEventListener('click', function () {
            sectionForm.reset();
            setEditing(false);
        });
    });

    sectionForms.forEach(function (sectionForm) {
        if (sectionForm.dataset.editing === '1') {
            lockOtherSections(sectionForm);
        }
    });
})();
</script>

<script>
(function () {
    const form = document.getElementById('email-test-form');
    const result = document.getElementById('email-test-result');
    const submit = document.getElementById('email-test-submit');
    if (!form || !result || !submit) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const csrfInputs = document.querySelectorAll('input[name="<?= csrf_token() ?>"]');
        if (csrfMeta) {
            csrfInputs.forEach(function (input) {
                input.value = csrfMeta.content;
            });
        }
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        };
        if (csrfMeta) {
            headers[csrfHeader] = csrfMeta.content;
        }

        result.className = 'alert alert-info';
        result.textContent = '<?= lang('Email.sendingTest') ?>';
        result.classList.remove('d-none');
        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><?= lang('Email.sendingTest') ?>';

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: new URLSearchParams(new FormData(form)).toString(),
        })
        .then(function (r) {
            const newToken = r.headers.get(csrfHeader);
            if (newToken && csrfMeta) {
                csrfMeta.content = newToken;
                csrfInputs.forEach(function (input) {
                    input.value = newToken;
                });
            }
            return r.json();
        })
        .then(function (data) {
            result.className = data.success ? 'alert alert-success' : 'alert alert-danger';
            result.textContent = data.message || (data.success ? '<?= lang('Email.testSent') ?>' : '<?= lang('Email.testSendFailed') ?>');
            result.classList.remove('d-none');
        })
        .catch(function () {
            result.className = 'alert alert-danger';
            result.textContent = '<?= lang('Email.testSendFailed') ?>';
            result.classList.remove('d-none');
        })
        .finally(function () {
            submit.disabled = false;
            submit.innerHTML = '<i class="bi bi-send me-1" aria-hidden="true"></i><?= lang('Email.sendTest') ?>';
        });
    });
})();
</script>

<?= $this->endSection() ?>
