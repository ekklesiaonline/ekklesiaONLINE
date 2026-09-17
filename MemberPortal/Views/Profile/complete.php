<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Profile.pageTitleComplete') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$provinces = (isset($provinces) && is_array($provinces)) ? $provinces : [];
$diocesesByProvince = (isset($diocesesByProvince) && is_array($diocesesByProvince)) ? $diocesesByProvince : [];
$parishesByDiocese = (isset($parishesByDiocese) && is_array($parishesByDiocese)) ? $parishesByDiocese : [];

$selectedProvince = old('province_id', $registration_request['province_id'] ?? $profile['province_id'] ?? null);
$selectedDiocese = old('diocese_id', $profile['diocese_id'] ?? $registration_request['diocese_id'] ?? null);
$selectedParish = old(
    'parish_id',
    $registration_request['requested_parish_id']
        ?? $registration_request['parish_id']
        ?? $registration_request['requested_parish_id']
        ?? $registration_request['parish_id']
        ?? $profile['parish_id']
        ?? null
);

$selectedProvinceLabel = '-';
foreach ($provinces as $province) {
    if ((string) $selectedProvince === (string) $province['id']) {
        $selectedProvinceLabel = ($province['province_number'] ? $province['province_number'] . ' - ' : '') . $province['name'];
        break;
    }
}

$selectedDioceseLabel = '-';
$dioceseOptions = $diocesesByProvince[(string) $selectedProvince] ?? [];
foreach ($dioceseOptions as $diocese) {
    if ((string) $selectedDiocese === (string) $diocese['id']) {
        $selectedDioceseLabel = (string) $diocese['name'];
        break;
    }
}

$selectedParishLabel = '-';
$parishOptions = $parishesByDiocese[(string) $selectedDiocese] ?? [];
foreach ($parishOptions as $parish) {
    if ((string) $selectedParish === (string) $parish['id']) {
        $selectedParishLabel = (string) $parish['name'];
        $parishUeid = trim((string) ($parish['parish_ueid'] ?? ''));
        if ($parishUeid !== '') {
            $selectedParishLabel .= ' [' . $parishUeid . ']';
        }
        break;
    }
}

$legacyCompletionMode = (bool) ($legacy_completion_mode ?? false);

$callingCodeOptions = \App\Libraries\PhoneHelper::callingCodeOptions();
[$storedCallingCode, $storedLocalPhone] = \App\Libraries\PhoneHelper::fromE164($profile['phone'] ?? '');
$selectedCallingCode = old('phone_country_code', $profile['phone_country_code'] ?? $storedCallingCode ?? '+1');
$localPhoneValue = old('phone', $storedLocalPhone ?? '');
?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-8 shadow-sm">
        <div class="card-body">
            <h4 class="mb-3"><?= lang('Profile.completeYourProfile') ?></h4>
            <p class="text-muted mb-4"><?= lang('Profile.completeSubtitle') ?></p>

            <?php if ($legacyCompletionMode): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <?= esc(lang('Profile.profilePendingWarningBody')) ?>
                </div>
            <?php endif; ?>

            <?php if (session('error') !== null): ?>
                <div class="alert alert-danger" role="alert"><?= esc((string) session('error')) ?></div>
            <?php endif; ?>
            <?php if (session('errors') !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <?= esc(is_string($error) ? $error : json_encode($error)) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('profile/complete') ?>" method="post" id="completeProfileForm">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="firstName"><?= lang('Profile.firstName') ?></label>
                        <input type="text" class="form-control" id="firstName" name="first_name" value="<?= esc(old('first_name', $profile['first_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lastName"><?= lang('Profile.lastName') ?></label>
                        <input type="text" class="form-control" id="lastName" name="last_name" value="<?= esc(old('last_name', $profile['last_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone_country_code"><?= lang('Profile.phoneCountryCode') ?></label>
                        <select class="form-select" id="phone_country_code" name="phone_country_code">
                            <?php foreach ($callingCodeOptions as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= (string) $selectedCallingCode === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="phone"><?= lang('Profile.phoneNumber') ?></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= esc($localPhoneValue) ?>">
                    </div>
                    <div class="col-md-12">
                        <?php if ($legacyCompletionMode): ?>
                            <p class="text-muted mb-0">
                                <?= esc(lang('Profile.profilePendingWarningTitle')) ?>
                            </p>
                        <?php else: ?>
                            <dl class="row mb-0 mt-2">
                                <dt class="col-sm-3"><?= lang('Profile.province') ?></dt>
                                <dd class="col-sm-9 mb-2"><?= esc($selectedProvinceLabel) ?></dd>

                                <dt class="col-sm-3"><?= lang('Profile.diocese') ?></dt>
                                <dd class="col-sm-9 mb-2"><?= esc($selectedDioceseLabel) ?></dd>

                                <dt class="col-sm-3"><?= lang('Profile.parish') ?></dt>
                                <dd class="col-sm-9 mb-0"><?= esc($selectedParishLabel) ?></dd>
                            </dl>

                            <input type="hidden" name="province_id" value="<?= esc((string) $selectedProvince) ?>">
                            <input type="hidden" name="diocese_id" value="<?= esc((string) $selectedDiocese) ?>">
                            <input type="hidden" name="parish_id" value="<?= esc((string) $selectedParish) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= base_url('logout') ?>" class="btn btn-outline-secondary"><?= lang('Profile.signOut') ?></a>
                    <button type="submit" class="btn btn-primary"><?= lang('Profile.submitForReview') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>