<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('members') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Members.backToMembers') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('members/update/' . $profile['id']) ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <?php
            $callingCodeOptions = \App\Libraries\PhoneHelper::callingCodeOptions();
            $countryOptions = \App\Libraries\AddressHelper::countryOptions();

            [$storedCallingCode, $storedLocalPhone] = \App\Libraries\PhoneHelper::fromE164($profile['phone'] ?? '');
            $selectedCallingCode = old('phone_country_code', $profile['phone_country_code'] ?? $storedCallingCode ?? '+1');
            $localPhoneValue = old('phone', $storedLocalPhone ?? '');
            $selectedCountry = old('country', $profile['country'] ?? '');
            ?>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.profilePicture') ?></h5>

                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <?php if (!empty($profile['profile_picture'])): ?>
                                    <img src="<?= base_url('media/profile-picture/' . (int) ($profile['user_id'] ?? 0)) ?>"
                                        alt="<?= esc(lang('Members.profilePicture')) ?>"
                                        class="img-thumbnail mb-2"
                                        id="currentMemberPicture"
                                        onerror="this.classList.add('d-none');document.getElementById('currentMemberPictureFallback')?.classList.remove('d-none');"
                                        style="max-width: 120px; max-height: 120px; object-fit: cover;">
                                    <div id="currentMemberPictureFallback"
                                        class="bg-light border rounded d-flex align-items-center justify-content-center mb-2 mx-auto d-none"
                                        style="width: 120px; height: 120px;">
                                        <i class="bi bi-person-circle" style="font-size: 3rem; color: #ccc;" aria-hidden="true"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-light border rounded d-flex align-items-center justify-content-center mb-2"
                                        style="width: 120px; height: 120px; margin: 0 auto;">
                                        <i class="bi bi-person-circle" style="font-size: 3rem; color: #ccc;" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <label for="profile_picture" class="form-label"><?= lang('Members.uploadNewPicture') ?></label>
                                <input type="file"
                                    class="form-control"
                                    id="profile_picture"
                                    name="profile_picture"
                                    accept="image/jpeg,image/jpg,image/png,image/gif"
                                    onchange="previewEditMemberPicture(event)">
                                <small class="text-muted d-block mt-1"><?= lang('Members.picAcceptedFormats') ?></small>
                                <div id="edit_member_picture_preview" class="mt-3" style="display: none;">
                                    <p class="small text-muted mb-2"><?= lang('App.preview') ?>:</p>
                                    <img id="edit_member_picture_preview_img" src="" alt="<?= esc(lang('App.preview')) ?>" class="img-thumbnail" style="max-width: 120px; max-height: 120px; object-fit: cover;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.sectionAccountInfo') ?></h5>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= lang('Members.email') ?></label>
                        <input type="email" class="form-control<?= session('errors.email') ? ' is-invalid' : '' ?>" value="<?= esc($profile['email']) ?>" readonly aria-describedby="emailHelp<?= session('errors.email') ? ' emailError' : '' ?>">
                        <small id="emailHelp" class="text-muted d-block"><?= lang('Members.emailCannotChange') ?></small>
                        <?php if (session('errors.email')): ?>
                            <div id="emailError" class="invalid-feedback">
                                <?= esc(session('errors.email')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="membership_status" class="form-label"><?= lang('Members.membershipStatus') ?> <span class="text-danger">*</span></label>
                        <select class="form-select<?= session('errors.membership_status') ? ' is-invalid' : '' ?>" id="membership_status" name="membership_status" required aria-describedby="membershipStatusError">
                            <option value="pending" <?= ($profile['membership_status'] ?? 'regular_attender') === 'pending' ? 'selected' : '' ?>><?= lang('Membership.statusPending') ?></option>
                            <option value="visitor" <?= ($profile['membership_status'] ?? 'regular_attender') === 'visitor' ? 'selected' : '' ?>><?= lang('Membership.statusVisitor') ?></option>
                            <option value="regular_attender" <?= ($profile['membership_status'] ?? 'regular_attender') === 'regular_attender' ? 'selected' : '' ?>><?= lang('Membership.statusRegularAttender') ?></option>
                            <option value="member" <?= ($profile['membership_status'] ?? 'regular_attender') === 'member' ? 'selected' : '' ?>><?= lang('Membership.statusMember') ?></option>
                            <option value="non_active" <?= ($profile['membership_status'] ?? 'regular_attender') === 'non_active' ? 'selected' : '' ?>><?= lang('Membership.statusNonActive') ?></option>
                        </select>
                        <?php if (session('errors.membership_status')): ?>
                            <div id="membershipStatusError" class="invalid-feedback">
                                <?= esc(session('errors.membership_status')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-2"><?= lang('Members.sectionPersonalInfo') ?></h5>
                    <p class="small text-muted mb-3">
                        <i class="bi bi-info-circle" aria-hidden="true"></i> <?= lang('Members.editCanonicalHint') ?>
                    </p>

                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?= lang('Members.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= session('errors.first_name') ? ' is-invalid' : '' ?>" id="first_name" name="first_name" value="<?= esc($profile['first_name']) ?>" required aria-describedby="firstNameError">
                        <?php if (session('errors.first_name')): ?>
                            <div id="firstNameError" class="invalid-feedback">
                                <?= esc(session('errors.first_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="middle_name" class="form-label"><?= lang('Members.middleName') ?></label>
                        <input type="text" class="form-control<?= session('errors.middle_name') ? ' is-invalid' : '' ?>" id="middle_name" name="middle_name" value="<?= esc($profile['middle_name']) ?>" aria-describedby="middleNameError">
                        <?php if (session('errors.middle_name')): ?>
                            <div id="middleNameError" class="invalid-feedback">
                                <?= esc(session('errors.middle_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?= lang('Members.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= session('errors.last_name') ? ' is-invalid' : '' ?>" id="last_name" name="last_name" value="<?= esc($profile['last_name']) ?>" required aria-describedby="lastNameError">
                        <?php if (session('errors.last_name')): ?>
                            <div id="lastNameError" class="invalid-feedback">
                                <?= esc(session('errors.last_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="nickname" class="form-label"><?= lang('Members.nicknameOptional') ?></label>
                        <input type="text" class="form-control<?= session('errors.nickname') ? ' is-invalid' : '' ?>" id="nickname" name="nickname" value="<?= esc($profile['nickname']) ?>" maxlength="100" aria-describedby="nicknameError">
                        <?php if (session('errors.nickname')): ?>
                            <div id="nicknameError" class="invalid-feedback">
                                <?= esc(session('errors.nickname')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="phone_country_code" class="form-label"><?= lang('Members.phoneCountryCode') ?></label>
                        <select class="form-select<?= session('errors.phone_country_code') ? ' is-invalid' : '' ?>" id="phone_country_code" name="phone_country_code" aria-describedby="phoneCountryCodeError">
                            <?php foreach ($callingCodeOptions as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= (string) $selectedCallingCode === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (session('errors.phone_country_code')): ?>
                            <div id="phoneCountryCodeError" class="invalid-feedback">
                                <?= esc(session('errors.phone_country_code')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label"><?= lang('Members.phoneNumber') ?></label>
                        <input type="tel" class="form-control<?= session('errors.phone') ? ' is-invalid' : '' ?>" id="phone" name="phone" value="<?= esc($localPhoneValue) ?>" aria-describedby="phoneError">
                        <?php if (session('errors.phone')): ?>
                            <div id="phoneError" class="invalid-feedback">
                                <?= esc(session('errors.phone')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.sectionAddressInfo') ?></h5>

                    <div class="mb-3">
                        <label for="country" class="form-label"><?= lang('Members.country') ?></label>
                        <select class="form-select<?= session('errors.country') ? ' is-invalid' : '' ?>" id="country" name="country" aria-describedby="countryError">
                            <option value="" <?= $selectedCountry === '' ? 'selected' : '' ?>><?= lang('Members.selectCountry') ?></option>
                            <?php foreach ($countryOptions as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= (string) $selectedCountry === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (session('errors.country')): ?>
                            <div id="countryError" class="invalid-feedback">
                                <?= esc(session('errors.country')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label"><?= lang('Members.streetAddress') ?></label>
                        <input type="text" class="form-control<?= session('errors.address') ? ' is-invalid' : '' ?>" id="address" name="address" value="<?= esc($profile['address']) ?>" aria-describedby="addressError">
                        <?php if (session('errors.address')): ?>
                            <div id="addressError" class="invalid-feedback">
                                <?= esc(session('errors.address')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="address_line_2" class="form-label"><?= lang('Members.addressLine2') ?></label>
                        <input type="text" class="form-control<?= session('errors.address_line_2') ? ' is-invalid' : '' ?>" id="address_line_2" name="address_line_2" value="<?= esc($profile['address_line_2']) ?>" aria-describedby="addressLine2Error">
                        <?php if (session('errors.address_line_2')): ?>
                            <div id="addressLine2Error" class="invalid-feedback">
                                <?= esc(session('errors.address_line_2')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label"><?= lang('Members.city') ?></label>
                            <input type="text" class="form-control<?= session('errors.city') ? ' is-invalid' : '' ?>" id="city" name="city" value="<?= esc($profile['city']) ?>" aria-describedby="cityError">
                            <?php if (session('errors.city')): ?>
                                <div id="cityError" class="invalid-feedback">
                                    <?= esc(session('errors.city')) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label"><?= lang('Members.state') ?></label>
                            <input type="text" class="form-control<?= session('errors.state') ? ' is-invalid' : '' ?>" id="state" name="state" value="<?= esc($profile['state']) ?>" aria-describedby="stateError">
                            <?php if (session('errors.state')): ?>
                                <div id="stateError" class="invalid-feedback">
                                    <?= esc(session('errors.state')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="zip" class="form-label"><?= lang('Members.zip') ?></label>
                        <input type="text" class="form-control<?= session('errors.zip') ? ' is-invalid' : '' ?>" id="zip" name="zip" value="<?= esc($profile['zip']) ?>" aria-describedby="zipError">
                        <?php if (session('errors.zip')): ?>
                            <div id="zipError" class="invalid-feedback">
                                <?= esc(session('errors.zip')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.sectionAdditionalInfo') ?></h5>

                    <div class="mb-3">
                        <label for="birth_date" class="form-label"><?= lang('Members.birthDate') ?></label>
                        <input type="date" class="form-control<?= session('errors.birth_date') ? ' is-invalid' : '' ?>" id="birth_date" name="birth_date" value="<?= esc($profile['birth_date']) ?>" aria-describedby="birthDateError">
                        <?php if (session('errors.birth_date')): ?>
                            <div id="birthDateError" class="invalid-feedback">
                                <?= esc(session('errors.birth_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="baptism_date" class="form-label"><?= lang('Members.baptismDate') ?></label>
                        <input type="date" class="form-control<?= session('errors.baptism_date') ? ' is-invalid' : '' ?>" id="baptism_date" name="baptism_date" value="<?= esc($profile['baptism_date']) ?>" aria-describedby="baptismDateError">
                        <?php if (session('errors.baptism_date')): ?>
                            <div id="baptismDateError" class="invalid-feedback">
                                <?= esc(session('errors.baptism_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="is_confirmed" name="is_confirmed" value="1" <?= !empty($profile['is_confirmed']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_confirmed"><?= lang('Members.isConfirmed') ?></label>
                        </div>
                        <label for="confirmation_date" class="form-label"><?= lang('Members.confirmationDate') ?></label>
                        <input type="date" class="form-control<?= session('errors.confirmation_date') ? ' is-invalid' : '' ?>" id="confirmation_date" name="confirmation_date" value="<?= esc($profile['confirmation_date'] ?? '') ?>" aria-describedby="confirmationDateError">
                        <?php if (session('errors.confirmation_date')): ?>
                            <div id="confirmationDateError" class="invalid-feedback">
                                <?= esc(session('errors.confirmation_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="spiritual_gifts" class="form-label"><?= lang('Members.spiritualGifts') ?></label>
                        <textarea class="form-control<?= session('errors.spiritual_gifts') ? ' is-invalid' : '' ?>" id="spiritual_gifts" name="spiritual_gifts" rows="3" aria-describedby="spiritualGiftsError"><?= esc($profile['spiritual_gifts']) ?></textarea>
                        <?php if (session('errors.spiritual_gifts')): ?>
                            <div id="spiritualGiftsError" class="invalid-feedback">
                                <?= esc(session('errors.spiritual_gifts')) ?>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted"><?= lang('Members.spiritualGiftsHint') ?></small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('members') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Members.updateMember') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewEditMemberPicture(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('edit_member_picture_preview_img').src = e.target.result;
                document.getElementById('edit_member_picture_preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('edit_member_picture_preview').style.display = 'none';
        }
    }
</script>

<?= $this->endSection() ?>