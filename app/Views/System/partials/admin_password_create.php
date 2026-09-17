<?php
$prefix = $prefix ?? 'createAdmin';
$passwordName     = $passwordName ?? 'password';
$confirmName      = $confirmName ?? 'confirm_password';
$passwordLabel    = $passwordLabel ?? lang('System.password');
$confirmLabel     = $confirmLabel ?? lang('System.confirmPassword');
$passwordId       = $prefix . 'Password';
$requirementsId   = $prefix . 'Requirements';
$confirmWrapperId = $prefix . 'ConfirmPasswordWrapper';
$confirmId        = $prefix . 'ConfirmPassword';
$feedbackId       = $prefix . 'ConfirmPasswordFeedback';
$submitId         = $prefix . 'Submit';

// Load policy from DB-driven settings, but allow the including view to override.
$policy = service('systemSettings')->getPasswordPolicy();
$minLength          = $minLength          ?? (int) ($policy['min_length'] ?? 12);
$requireUppercase   = $requireUppercase   ?? (bool) ($policy['require_uppercase'] ?? true);
$requireLowercase   = $requireLowercase   ?? (bool) ($policy['require_lowercase'] ?? true);
$requireDigit       = $requireDigit       ?? (bool) ($policy['require_digit'] ?? true);
$requireSpecial     = $requireSpecial     ?? (bool) ($policy['require_special'] ?? true);
$specialChars       = $specialChars       ?? (string) ($policy['special_chars'] ?? '@$!%*?&#^_-');
$specialRegexJs     = preg_replace_callback('/[\\\\[\]\-\^]/', static fn($m) => '\\' . $m[0], $specialChars);
?>

<div class="col-12">
    <label for="<?= $passwordId ?>" class="form-label"><?= $passwordLabel ?> *</label>
    <input id="<?= $passwordId ?>" name="<?= $passwordName ?>" type="password"
           class="form-control" autocomplete="new-password" minlength="<?= (int) $minLength ?>" required>
    <ul id="<?= $requirementsId ?>" class="list-unstyled small mt-2 mb-0 d-none">
        <li id="<?= $requirementsId ?>Length" class="text-danger<?= $minLength <= 0 ? ' d-none' : '' ?>"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i><?= lang('System.passwordRequirementLength', ['minLength' => $minLength]) ?></li>
        <li id="<?= $requirementsId ?>Upper" class="text-danger<?= $requireUppercase ? '' : ' d-none' ?>"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i><?= lang('System.passwordRequirementUppercase') ?></li>
        <li id="<?= $requirementsId ?>Lower" class="text-danger<?= $requireLowercase ? '' : ' d-none' ?>"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i><?= lang('System.passwordRequirementLowercase') ?></li>
        <li id="<?= $requirementsId ?>Number" class="text-danger<?= $requireDigit ? '' : ' d-none' ?>"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i><?= lang('System.passwordRequirementNumber') ?></li>
        <li id="<?= $requirementsId ?>Special" class="text-danger<?= $requireSpecial ? '' : ' d-none' ?>"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i><?= lang('System.passwordRequirementSpecial') ?></li>
    </ul>
</div>

<div class="col-12 d-none" id="<?= $confirmWrapperId ?>">
    <label for="<?= $confirmId ?>" class="form-label"><?= $confirmLabel ?> *</label>
    <input id="<?= $confirmId ?>" name="<?= $confirmName ?>" type="password"
           class="form-control" autocomplete="new-password" disabled required>
    <div id="<?= $feedbackId ?>" class="small text-danger d-none"><?= lang('System.passwordsDoNotMatch') ?></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('<?= $passwordId ?>');
    const confirmWrapper = document.getElementById('<?= $confirmWrapperId ?>');
    const confirm = document.getElementById('<?= $confirmId ?>');
    const feedback = document.getElementById('<?= $feedbackId ?>');
    const submit = document.getElementById('<?= $submitId ?>');
    const requirements = document.getElementById('<?= $requirementsId ?>');
    const checks = {
        length:  document.getElementById('<?= $requirementsId ?>Length'),
        upper:   document.getElementById('<?= $requirementsId ?>Upper'),
        lower:   document.getElementById('<?= $requirementsId ?>Lower'),
        number:  document.getElementById('<?= $requirementsId ?>Number'),
        special: document.getElementById('<?= $requirementsId ?>Special'),
    };
    const minLength = <?= (int) $minLength ?>;
    const requireUppercase = <?= $requireUppercase ? 'true' : 'false' ?>;
    const requireLowercase = <?= $requireLowercase ? 'true' : 'false' ?>;
    const requireDigit = <?= $requireDigit ? 'true' : 'false' ?>;
    const requireSpecial = <?= $requireSpecial ? 'true' : 'false' ?>;
    const specialRegex = /[<?= $specialRegexJs ?>]/;

    function setValid(item, valid) {
        if (!item) return;
        item.classList.toggle('text-success', valid);
        item.classList.toggle('text-danger', !valid);
        const icon = item.querySelector('i');
        if (icon) {
            icon.className = valid ? 'bi bi-check-circle-fill me-1' : 'bi bi-x-circle-fill me-1';
        }
    }

    function validatePassword() {
        const v = password.value;
        const results = {
            length:  minLength > 0 ? v.length >= minLength : true,
            upper:   requireUppercase ? /[A-Z]/.test(v) : true,
            lower:   requireLowercase ? /[a-z]/.test(v) : true,
            number:  requireDigit ? /[0-9]/.test(v) : true,
            special: requireSpecial ? specialRegex.test(v) : true,
        };
        let allValid = true;
        for (const key in results) {
            if (checks[key]) {
                setValid(checks[key], results[key]);
                if (!results[key]) allValid = false;
            }
        }
        return allValid;
    }

    function updateSubmit() {
        const match = password.value && password.value === confirm.value;
        const mismatch = confirm.value && !match;
        feedback.classList.toggle('d-none', !mismatch);
        submit.disabled = !(password.value && validatePassword() && match);
    }

    password.addEventListener('input', function () {
        if (password.value) {
            requirements.classList.remove('d-none');
            confirmWrapper.classList.remove('d-none');
        } else {
            requirements.classList.add('d-none');
            confirmWrapper.classList.add('d-none');
            confirm.value = '';
            feedback.classList.add('d-none');
        }

        const allValid = validatePassword();
        confirm.disabled = !allValid;
        if (!allValid) {
            confirm.value = '';
            feedback.classList.add('d-none');
        }
        updateSubmit();
    });

    confirm.addEventListener('input', updateSubmit);

    password.closest('form').addEventListener('submit', function (e) {
        if (submit.disabled) {
            e.preventDefault();
        }
    });

    confirm.disabled = true;
    submit.disabled = true;
});
</script>
