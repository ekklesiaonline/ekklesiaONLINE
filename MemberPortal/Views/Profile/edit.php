<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('MemberPortal\\Views\\Profile\\_tokens') ?>

<div class="profile-page">

    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><?= lang('App.navDashboard') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('profile') ?>"><?= lang('Profile.pageTitle') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('Profile.editProfile') ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0"><?= lang('Profile.editProfile') ?></h4>
        <a href="<?= base_url('profile') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Profile.back') ?>
        </a>
    </div>

    <form method="post" action="<?= base_url('profile/update') ?>" class="needs-validation" novalidate enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= lang('Profile.sectionPersonal') ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            <i class="bi bi-info-circle" aria-hidden="true"></i> <?= lang('Profile.canonicalIdentityEditHint') ?>
                        </p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="<?= lang('Profile.firstName') ?>"
                                        value="<?= old('first_name') ?? esc($profile['first_name'] ?? '') ?>" maxlength="255">
                                    <label for="first_name"><?= lang('Profile.firstName') ?></label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="<?= lang('Profile.middleName') ?>"
                                        value="<?= old('middle_name') ?? esc($profile['middle_name'] ?? '') ?>" maxlength="255">
                                    <label for="middle_name"><?= lang('Profile.middleName') ?></label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="<?= lang('Profile.lastName') ?>"
                                        value="<?= old('last_name') ?? esc($profile['last_name'] ?? '') ?>" maxlength="255">
                                    <label for="last_name"><?= lang('Profile.lastName') ?></label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" placeholder="<?= lang('Profile.emailReadOnly') ?>" value="<?= esc($user->email) ?>" disabled>
                                    <label for="email"><?= lang('Profile.emailReadOnly') ?></label>
                                </div>
                                <small class="text-muted"><?= lang('Profile.emailChangeContactAdmin') ?></small>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="membership_status" placeholder="<?= lang('Profile.membershipStatusReadOnly') ?>"
                                        value="<?= esc(lang('Profile.status' . ucfirst($profile['membership_status'] ?? 'pending'))) ?>" disabled>
                                    <label for="membership_status"><?= lang('Profile.membershipStatusReadOnly') ?></label>
                                </div>
                                <small class="text-muted"><?= lang('Profile.membershipStatusChangeContactAdmin') ?></small>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="birth_date" name="birth_date"
                                        value="<?= old('birth_date') ?? ($profile['birth_date'] && $profile['birth_date'] !== '0000-00-00' ? $profile['birth_date'] : '') ?>">
                                    <label for="birth_date"><?= lang('Profile.birthDate') ?></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="baptism_date" name="baptism_date"
                                        value="<?= old('baptism_date') ?? ($profile['baptism_date'] && $profile['baptism_date'] !== '0000-00-00' ? $profile['baptism_date'] : '') ?>">
                                    <label for="baptism_date"><?= lang('Profile.baptismDate') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= lang('Profile.profilePicture') ?></h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if (!empty($profile['profile_picture'])): ?>
                            <img src="<?= base_url('media/profile-picture/' . (int) ($user->id ?? 0)) ?>"
                                alt="<?= lang('Profile.profilePicture') ?>"
                                class="img-fluid mb-3 profile-avatar"
                                id="currentPicture"
                                onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                            <div class="mb-3 mx-auto profile-avatar-wrap d-none">
                                <i class="bi bi-person-circle profile-avatar-placeholder" aria-hidden="true"></i>
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-danger" onclick="deletePicture()">
                                    <i class="bi bi-trash" aria-hidden="true"></i> <?= lang('Profile.removePicture') ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="mb-3 mx-auto profile-avatar-wrap">
                                <i class="bi bi-person-circle profile-avatar-placeholder" aria-hidden="true"></i>
                            </div>
                            <small class="text-muted d-block mb-3"><?= lang('Profile.noPictureUploaded') ?></small>
                        <?php endif; ?>

                        <label for="profile_picture" class="form-label"><?= lang('Profile.uploadNewPicture') ?></label>
                        <input type="file"
                            class="form-control"
                            id="profile_picture"
                            name="profile_picture"
                            accept="image/jpeg,image/jpg,image/png,image/gif"
                            onchange="previewProfilePicture(event)">
                        <small class="text-muted d-block mt-2"><?= lang('Profile.acceptedFormats') ?></small>

                        <div id="profile_preview" class="mt-3" style="display: none;">
                            <p class="small text-muted mb-2"><?= lang('Profile.preview') ?></p>
                            <img id="profile_preview_img" src="" alt="<?= lang('Profile.preview') ?>" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $callingCodeOptions = \App\Libraries\PhoneHelper::callingCodeOptions();
        $countryOptions = \App\Libraries\AddressHelper::countryOptions();

        [$storedCallingCode, $storedLocalPhone] = \App\Libraries\PhoneHelper::fromE164($profile['phone'] ?? '');
        $selectedCallingCode = old('phone_country_code', $profile['phone_country_code'] ?? $storedCallingCode ?? '+1');
        $localPhoneValue = old('phone', $storedLocalPhone ?? '');
        $selectedCountry = old('country', $profile['country'] ?? '');
        ?>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= lang('Profile.phone') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="phone_country_code" class="form-label"><?= lang('Profile.phoneCountryCode') ?></label>
                            <select class="form-select" id="phone_country_code" name="phone_country_code">
                                <?php foreach ($callingCodeOptions as $code => $label): ?>
                                    <option value="<?= esc($code) ?>" <?= (string) $selectedCallingCode === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-floating">
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="<?= lang('Profile.phoneNumber') ?>"
                                value="<?= esc($localPhoneValue) ?>">
                            <label for="phone"><?= lang('Profile.phoneNumber') ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= lang('Profile.sectionContact') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="country" class="form-label"><?= lang('Profile.country') ?></label>
                                <select class="form-select" id="country" name="country">
                                    <option value="" <?= $selectedCountry === '' ? 'selected' : '' ?>><?= lang('Profile.selectCountry') ?></option>
                                    <?php foreach ($countryOptions as $code => $label): ?>
                                        <option value="<?= esc($code) ?>" <?= (string) $selectedCountry === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="address" name="address" placeholder="<?= lang('Profile.address') ?>"
                                        value="<?= old('address') ?? esc($profile['address'] ?? '') ?>">
                                    <label for="address"><?= lang('Profile.address') ?></label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="address_line_2" name="address_line_2" placeholder="<?= lang('Profile.addressLine2') ?>"
                                        value="<?= old('address_line_2') ?? esc($profile['address_line_2'] ?? '') ?>">
                                    <label for="address_line_2"><?= lang('Profile.addressLine2') ?></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="city" name="city" placeholder="<?= lang('Profile.city') ?>"
                                        value="<?= old('city') ?? esc($profile['city'] ?? '') ?>" maxlength="255">
                                    <label for="city"><?= lang('Profile.city') ?></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="state" name="state" placeholder="<?= lang('Profile.state') ?>"
                                        value="<?= old('state') ?? esc($profile['state'] ?? '') ?>" maxlength="50">
                                    <label for="state"><?= lang('Profile.state') ?></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="zip" name="zip" placeholder="<?= lang('Profile.zip') ?>"
                                        value="<?= old('zip') ?? esc($profile['zip'] ?? '') ?>" maxlength="20">
                                    <label for="zip"><?= lang('Profile.zip') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spiritual Information Section -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= lang('Profile.spiritualInformation') ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="spiritual_gifts" class="form-label"><?= lang('Profile.spiritualGifts') ?></label>
                        <textarea class="form-control" id="spiritual_gifts" name="spiritual_gifts" rows="4"><?= old('spiritual_gifts') ?? esc($profile['spiritual_gifts'] ?? '') ?></textarea>
                        <small class="text-muted"><?= lang('Profile.spiritualGiftsHint') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parish Affiliations Section -->
        <div class="card mb-3 form-section-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-buildings" aria-hidden="true"></i> <?= lang('Profile.parishAffiliation') ?></h5>
            </div>
            <div class="card-body">
                <?php
                $isElevatedAdmin = $isElevatedAdmin ?? false;
                $adminScope = $adminScope ?? null;
                $primaryParts = array_filter([
                    $parishAffiliation['province'] ?? '',
                    $parishAffiliation['diocese'] ?? '',
                    $parishAffiliation['parish'] ?? '',
                ]);
                // Elevated admins administer a diocese/province/system scope but
                // may also be personally affiliated with one or more parishes
                // (parish_people rows) — show both instead of implying the
                // diocese/province itself is their "primary parish".
                $adminParishNames = ($isElevatedAdmin && $adminScope)
                    ? array_values(array_filter(array_unique(array_column($otherAffiliations ?? [], 'parish_name'))))
                    : [];
                $primaryLabel = $primaryParts
                    ? implode(' / ', $primaryParts)
                    : ($isElevatedAdmin && $adminScope
                        ? $adminScope['name'] . ($adminParishNames !== [] ? ' — ' . lang('Profile.parishesLabel') . ': ' . implode(', ', $adminParishNames) : '')
                        : lang('Profile.noAffiliation'));
                ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= lang('Profile.primaryParish') ?></label>
                    <div class="d-flex justify-content-between align-items-center border rounded p-2 bg-body-tertiary">
                        <span><?= esc($primaryLabel) ?></span>
                    </div>
                    <small class="text-muted"><?= lang('Profile.primaryParishReadOnly') ?></small>
                    <?php if (empty($profile['parish_id']) && !$isElevatedAdmin): ?>
                        <div class="mt-2">
                            <?php if (($registrationStatus ?? '') === 'pending_review'): ?>
                                <button type="button" class="btn btn-warning btn-sm" disabled>
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= lang('Profile.joinParishPendingApproval') ?>
                                </button>
                            <?php else: ?>
                                <a href="<?= base_url('profile/join-parish') ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-building-add" aria-hidden="true"></i> <?= lang('Profile.joinParishTitle') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <label class="form-label fw-semibold"><?= lang('Profile.otherParishes') ?></label>
                <div id="affiliations-container" class="mb-2">
                    <?php
                    $affIndex = 0;
                    foreach ($otherAffiliations as $affiliation):
                        if (!empty($affiliation['is_primary'])) continue;
                        $affProvinceId = (int) ($affiliation['province_id'] ?? 0);
                        $affDioceseId  = (int) ($affiliation['diocese_id'] ?? 0);
                        $affParishId   = (int) ($affiliation['parish_id'] ?? 0);
                        $affStatus     = (string) ($affiliation['membership_status'] ?? 'regular_attender');
                    ?>
                    <div class="row g-2 align-items-end affiliation-row mb-2" data-index="<?= $affIndex ?>" data-selected-diocese="<?= $affDioceseId ?>" data-selected-parish="<?= $affParishId ?>">
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.province') ?></label>
                            <select class="form-select province-select" data-index="<?= $affIndex ?>">
                                <option value=""><?= lang('Profile.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= $province['id'] ?>" <?= (int) $province['id'] === $affProvinceId ? 'selected' : '' ?>><?= esc($province['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.diocese') ?></label>
                            <select class="form-select diocese-select" data-index="<?= $affIndex ?>">
                                <option value=""><?= lang('Profile.selectDiocese') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.parish') ?></label>
                            <select class="form-select parish-select" name="affiliations[<?= $affIndex ?>][parish_id]" data-index="<?= $affIndex ?>" required>
                                <option value=""><?= lang('Profile.selectParish') ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= lang('Profile.affiliationStatus') ?></label>
                            <select class="form-select" name="affiliations[<?= $affIndex ?>][membership_status]">
                                <?php foreach ($membershipStatusOptions as $statusValue => $statusLabel): ?>
                                    <option value="<?= $statusValue ?>" <?= $statusValue === $affStatus ? 'selected' : '' ?>><?= esc($statusLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-affiliation" aria-label="<?= esc(lang('App.delete')) ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <?php $affIndex++; endforeach; ?>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" id="add-affiliation">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Profile.addParish') ?>
                </button>

                <script type="text/template" id="affiliation-row-template">
                    <div class="row g-2 align-items-end affiliation-row mb-2" data-index="__IDX__">
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.province') ?></label>
                            <select class="form-select province-select" data-index="__IDX__">
                                <option value=""><?= lang('Profile.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= $province['id'] ?>"><?= esc($province['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.diocese') ?></label>
                            <select class="form-select diocese-select" data-index="__IDX__">
                                <option value=""><?= lang('Profile.selectDiocese') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= lang('Profile.parish') ?></label>
                            <select class="form-select parish-select" name="affiliations[__IDX__][parish_id]" data-index="__IDX__" required>
                                <option value=""><?= lang('Profile.selectParish') ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= lang('Profile.affiliationStatus') ?></label>
                            <select class="form-select" name="affiliations[__IDX__][membership_status]">
                                <?php foreach ($membershipStatusOptions as $statusValue => $statusLabel): ?>
                                    <option value="<?= $statusValue ?>" <?= $statusValue === 'regular_attender' ? 'selected' : '' ?>><?= esc($statusLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-affiliation" aria-label="<?= esc(lang('App.delete')) ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </script>
            </div>
        </div>

        <!-- Preferences Section -->
        <div class="card mb-3 form-section-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-sliders" aria-hidden="true"></i> <?= lang('Profile.sectionPreferences') ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="locale" class="form-label"><?= lang('Profile.language') ?></label>
                        <select class="form-select" id="locale" name="locale">
                            <option value="en" <?= (old('locale') ?? ($profile['locale'] ?? 'en')) === 'en' ? 'selected' : '' ?>><?= lang('Profile.localeEn') ?></option>
                            <option value="es" <?= (old('locale') ?? ($profile['locale'] ?? 'en')) === 'es' ? 'selected' : '' ?>><?= lang('Profile.localeEs') ?></option>
                            <option value="fr" <?= (old('locale') ?? ($profile['locale'] ?? 'en')) === 'fr' ? 'selected' : '' ?>><?= lang('Profile.localeFr') ?></option>
                        </select>
                        <small class="text-muted"><?= lang('Profile.languageHint') ?></small>
                    </div>
                    <div class="col-md-6">
                        <label for="theme" class="form-label"><?= lang('Profile.theme') ?></label>
                        <select class="form-select" id="theme" name="theme" disabled>
                            <option value="light" selected><?= lang('Profile.themeLight') ?></option>
                            <option value="dark"><?= lang('Profile.themeDark') ?></option>
                            <option value="system"><?= lang('Profile.themeSystem') ?></option>
                        </select>
                        <small class="text-muted"><?= lang('Profile.themeHint') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="card-body d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> <?= lang('Profile.saveChanges') ?>
                </button>
                <a href="<?= base_url('profile') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> <?= lang('App.cancel') ?>
                </a>
            </div>
        </div>
    </form>

</div>

<script>
    (function() {
        const form = document.querySelector('form.needs-validation');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        }
    })();

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    function previewProfilePicture(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile_preview_img').src = e.target.result;
                document.getElementById('profile_preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('profile_preview').style.display = 'none';
        }
    }

    function deletePicture() {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            '<?= esc(lang('Profile.deletePictureConfirm'), 'js') ?>',
            function() {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                };
                if (csrfMeta) {
                    headers[csrfHeader] = csrfMeta.content;
                }

                fetch('<?= base_url('profile/delete-picture') ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers,
                        body: new URLSearchParams({
                            action: 'delete_picture'
                        }).toString(),
                    })
                    .then(response => {
                        const newToken = response.headers.get(csrfHeader);
                        if (newToken && csrfMeta) {
                            csrfMeta.content = newToken;
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            showAppToast('<?= esc(lang('Profile.errorPrefix'), 'js') ?> ' + contractErrorMessage(data, '<?= esc(lang('Profile.errorDeletePicture'), 'js') ?>'), 'danger');
                        }
                    })
                    .catch(error => {
                        showAppToast('<?= esc(lang('Profile.errorDeletePicture'), 'js') ?>', 'danger');
                        console.error('Error:', error);
                    });
            }
        );
    }

    (function () {
        const container = document.getElementById('affiliations-container');
        const template = document.getElementById('affiliation-row-template');
        const addBtn = document.getElementById('add-affiliation');
        const diocesesByProvince = <?= json_encode($diocesesByProvince, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const parishesByDiocese = <?= json_encode($parishesByDiocese, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const selectDioceseText = <?= json_encode(lang('Profile.selectDiocese')) ?>;
        const selectParishText = <?= json_encode(lang('Profile.selectParish')) ?>;
        const excludedParishIds = <?= json_encode([(int) $primaryParishId, (int) $pendingParishId], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function updateDioceseSelect(select, provinceId, selectedDioceseId) {
            select.options.length = 0;
            select.add(new Option(selectDioceseText, ''));
            if (!provinceId || !diocesesByProvince[provinceId]) return;
            diocesesByProvince[provinceId].forEach(function (d) {
                const option = new Option(d.name, d.id);
                if (String(selectedDioceseId) === String(d.id)) option.selected = true;
                select.add(option);
            });
        }

        function updateParishSelect(select, dioceseId, selectedParishId) {
            selectedParishId = selectedParishId || '';
            select.options.length = 0;
            select.add(new Option(selectParishText, ''));
            if (!dioceseId || !parishesByDiocese[dioceseId]) return;
            parishesByDiocese[dioceseId].forEach(function (p) {
                if (excludedParishIds.indexOf(parseInt(p.id, 10)) !== -1 && String(selectedParishId) !== String(p.id)) {
                    return;
                }
                const option = new Option((p.parish_ueid ? '[' + p.parish_ueid + '] ' : '') + p.name, p.id);
                if (String(selectedParishId) === String(p.id)) option.selected = true;
                select.add(option);
            });
        }

        function initRow(row) {
            const provinceSelect = row.querySelector('.province-select');
            const dioceseSelect = row.querySelector('.diocese-select');
            const parishSelect = row.querySelector('.parish-select');
            const selectedDiocese = row.dataset.selectedDiocese;
            const selectedParish = row.dataset.selectedParish;

            provinceSelect.addEventListener('change', function () {
                updateDioceseSelect(dioceseSelect, this.value);
                updateParishSelect(parishSelect, '');
            });
            dioceseSelect.addEventListener('change', function () {
                updateParishSelect(parishSelect, this.value);
            });

            if (provinceSelect.value) {
                updateDioceseSelect(dioceseSelect, provinceSelect.value, selectedDiocese);
                if (selectedDiocese) {
                    updateParishSelect(parishSelect, selectedDiocese, selectedParish);
                }
            }
        }

        if (container) {
            container.querySelectorAll('.affiliation-row').forEach(initRow);
        }

        let nextIndex = container ? container.children.length : 0;

        if (addBtn && container) {
            addBtn.addEventListener('click', function () {
                const html = template.innerHTML.replace(/__IDX__/g, nextIndex);
                container.insertAdjacentHTML('beforeend', html);
                const row = container.lastElementChild;
                initRow(row);
                nextIndex++;
            });
        }

        if (container) {
            container.addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-affiliation');
                if (!btn) return;
                const row = btn.closest('.affiliation-row');
                if (row) row.remove();
            });
        }
    })();
</script>

<?= $this->endSection() ?>