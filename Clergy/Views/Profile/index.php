<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('clergy/dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('ClergyPortal.backToDashboard') ?>
    </a>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ((array) session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form action="<?= base_url('clergy/profile') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="first_name" class="form-label"><?= lang('Clergy.firstName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= esc(old('first_name', $profile['first_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="middle_name" class="form-label"><?= lang('Clergy.middleName') ?></label>
                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= esc(old('middle_name', $profile['middle_name'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="last_name" class="form-label"><?= lang('Clergy.lastName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= esc(old('last_name', $profile['last_name'] ?? '')) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="phone_country_code" class="form-label"><?= lang('ClergyPortal.phoneCountryCode') ?></label>
                    <input type="text" class="form-control" id="phone_country_code" name="phone_country_code" value="<?= esc(old('phone_country_code', $profile['phone_country_code'] ?? '')) ?>" placeholder="+1">
                </div>
                <div class="col-md-5 mb-3">
                    <label for="phone" class="form-label"><?= lang('ClergyPortal.phone') ?></label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= esc(old('phone', $profile['phone'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="birth_date" class="form-label"><?= lang('ClergyPortal.birthDate') ?></label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= esc(old('birth_date', $profile['birth_date'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="address" class="form-label"><?= lang('ClergyPortal.address') ?></label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= esc(old('address', $profile['address'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="address_line_2" class="form-label"><?= lang('ClergyPortal.addressLine2') ?></label>
                    <input type="text" class="form-control" id="address_line_2" name="address_line_2" value="<?= esc(old('address_line_2', $profile['address_line_2'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="city" class="form-label"><?= lang('ClergyPortal.city') ?></label>
                    <input type="text" class="form-control" id="city" name="city" value="<?= esc(old('city', $profile['city'] ?? '')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="state" class="form-label"><?= lang('ClergyPortal.state') ?></label>
                    <input type="text" class="form-control" id="state" name="state" value="<?= esc(old('state', $profile['state'] ?? '')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="zip" class="form-label"><?= lang('ClergyPortal.zip') ?></label>
                    <input type="text" class="form-control" id="zip" name="zip" value="<?= esc(old('zip', $profile['zip'] ?? '')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="country" class="form-label"><?= lang('ClergyPortal.country') ?></label>
                    <input type="text" class="form-control" id="country" name="country" value="<?= esc(old('country', $profile['country'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="locale" class="form-label"><?= lang('ClergyPortal.locale') ?></label>
                    <select class="form-select" id="locale" name="locale">
                        <?php foreach (['en' => 'English', 'es' => 'Español', 'fr' => 'Français'] as $code => $label): ?>
                            <option value="<?= esc($code) ?>" <?= old('locale', $profile['locale'] ?? 'en') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= lang('App.save') ?></button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.ordinationSummary') ?></h6>
                <?php if (!empty($canonical['ordinations'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['ordinations'] as $ordination): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= esc($ordination['order_code'] ?? '-') ?></span>
                                <span class="text-muted small"><?= esc($ordination['ordained_on'] ?? '') ?> · <?= esc($ordination['verification_status'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noOrdinations') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.standingSummary') ?></h6>
                <?php if (!empty($canonical['standings'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['standings'] as $standing): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= esc($standing['status_code'] ?? '-') ?></span>
                                <span class="text-muted small"><?= esc($standing['effective_from'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noStanding') ?></p>
                <?php endif; ?>
                <small class="text-muted d-block mt-2"><?= lang('Clergy.canonicalFieldsReadOnlyHelp') ?></small>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
