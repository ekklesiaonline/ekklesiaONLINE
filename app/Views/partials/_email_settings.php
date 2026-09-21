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
 */

$settings    = $settings ?? [];
$errors      = $errors ?? [];
$effective   = $effective_sender ?? ['from_email' => '', 'from_name' => '', 'reply_to' => ''];
$canEditSmtp = $can_edit_smtp ?? false;
$value       = static fn(string $key): string => esc((string) ($settings[$key] ?? ''), 'attr');
$hasError    = static fn(string $key): bool => isset($errors[$key]);
$errorFor    = static fn(string $key): string => $hasError($key) ? '<div class="text-danger small mt-1">' . esc((string) $errors[$key]) . '</div>' : '';
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= esc($back_url) ?>" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= lang('App.back') ?>
    </a>
    <h5 class="mb-0"><?= esc($title) ?></h5>
</div>

<?php if (session()->has('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= esc(session('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= esc(session('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
        <form action="<?= esc($save_url) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="scope_type" value="<?= esc($scope_type) ?>">
            <?php if ($scope_id !== null): ?>
                <input type="hidden" name="scope_id" value="<?= (int) $scope_id ?>">
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header"><strong><?= lang('Email.identityCard') ?></strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="from_email" class="form-label"><?= lang('Email.fromEmail') ?></label>
                        <input type="email" class="form-control<?= $hasError('from_email') ? ' is-invalid' : '' ?>" id="from_email" name="from_email" value="<?= $value('from_email') ?>">
                        <?= $errorFor('from_email') ?>
                    </div>
                    <div class="mb-3">
                        <label for="from_name" class="form-label"><?= lang('Email.fromName') ?></label>
                        <input type="text" class="form-control<?= $hasError('from_name') ? ' is-invalid' : '' ?>" id="from_name" name="from_name" value="<?= $value('from_name') ?>">
                        <?= $errorFor('from_name') ?>
                    </div>
                    <div class="mb-3">
                        <label for="reply_to" class="form-label"><?= lang('Email.replyTo') ?></label>
                        <input type="email" class="form-control<?= $hasError('reply_to') ? ' is-invalid' : '' ?>" id="reply_to" name="reply_to" value="<?= $value('reply_to') ?>">
                        <?= $errorFor('reply_to') ?>
                    </div>
                </div>
            </div>

            <div class="card mb-4<?= $canEditSmtp ? '' : ' opacity-75' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= lang('Email.smtpCard') ?></strong>
                    <?php if (!$canEditSmtp): ?>
                        <span class="badge border border-warning text-warning fw-normal"><?= lang('Email.smtpViewOnly') ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$canEditSmtp): ?>
                        <p class="text-muted small"><?= lang('Email.smtpRestricted') ?></p>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="smtp_host" class="form-label"><?= lang('Email.smtpHost') ?></label>
                        <input type="text" class="form-control<?= $hasError('smtp_host') ? ' is-invalid' : '' ?>" id="smtp_host" name="smtp_host" value="<?= $value('smtp_host') ?>"<?= $canEditSmtp ? '' : ' readonly' ?>>
                        <?= $errorFor('smtp_host') ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8 mb-3">
                            <label for="smtp_user" class="form-label"><?= lang('Email.smtpUser') ?></label>
                            <input type="text" class="form-control<?= $hasError('smtp_user') ? ' is-invalid' : '' ?>" id="smtp_user" name="smtp_user" value="<?= $value('smtp_user') ?>"<?= $canEditSmtp ? '' : ' readonly' ?>>
                            <?= $errorFor('smtp_user') ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="smtp_port" class="form-label"><?= lang('Email.smtpPort') ?></label>
                            <input type="number" class="form-control<?= $hasError('smtp_port') ? ' is-invalid' : '' ?>" id="smtp_port" name="smtp_port" value="<?= $value('smtp_port') ?: '587' ?>"<?= $canEditSmtp ? '' : ' readonly' ?>>
                            <?= $errorFor('smtp_port') ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="smtp_pass" class="form-label"><?= lang('Email.smtpPass') ?></label>
                        <input type="password" class="form-control<?= $hasError('smtp_pass') ? ' is-invalid' : '' ?>" id="smtp_pass" name="smtp_pass" placeholder="<?= $has_smtp_pass ? lang('Email.smtpPassPlaceholderKeep') : lang('Email.smtpPassPlaceholderNew') ?>"<?= $canEditSmtp ? '' : ' readonly' ?>>
                        <?= $errorFor('smtp_pass') ?>
                    </div>
                    <div class="mb-3">
                        <label for="smtp_crypto" class="form-label"><?= lang('Email.smtpCrypto') ?></label>
                        <select class="form-select<?= $hasError('smtp_crypto') ? ' is-invalid' : '' ?>" id="smtp_crypto" name="smtp_crypto"<?= $canEditSmtp ? '' : ' disabled' ?>>
                            <?php $crypto = $value('smtp_crypto') ?: 'tls'; ?>
                            <option value="tls"<?= $crypto === 'tls' ? ' selected' : '' ?>>TLS</option>
                            <option value="ssl"<?= $crypto === 'ssl' ? ' selected' : '' ?>>SSL</option>
                            <option value=""<?= $crypto === '' ? ' selected' : '' ?>><?= lang('Email.none') ?></option>
                        </select>
                        <?= $errorFor('smtp_crypto') ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1" aria-hidden="true"></i><?= lang('App.save') ?>
                </button>
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
                <form action="<?= esc($test_url) ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="scope_type" value="<?= esc($scope_type) ?>">
                    <?php if ($scope_id !== null): ?>
                        <input type="hidden" name="scope_id" value="<?= (int) $scope_id ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="test_recipient" class="form-label"><?= lang('Email.testRecipient') ?></label>
                        <input type="email" class="form-control" id="test_recipient" name="test_recipient" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-send me-1" aria-hidden="true"></i><?= lang('Email.sendTest') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
