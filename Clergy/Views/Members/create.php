<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('members') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Members.backToMembers') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('members/store') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.profilePicture') ?></h5>

                    <div class="mb-3">
                        <label for="profile_picture" class="form-label"><?= lang('Members.uploadPicture') ?></label>
                        <input type="file"
                            class="form-control"
                            id="profile_picture"
                            name="profile_picture"
                            accept="image/jpeg,image/jpg,image/png,image/gif"
                            onchange="previewMemberPicture(event)">
                        <small class="text-muted d-block mt-1"><?= lang('Members.picAcceptedFormats') ?></small>
                        <div id="member_picture_preview" class="mt-3" style="display: none;">
                            <p class="small text-muted mb-2"><?= lang('App.preview') ?>:</p>
                            <img id="member_picture_preview_img" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.sectionAccountInfo') ?></h5>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= lang('Members.email') ?> <span class="text-danger">*</span></label>
                        <input type="email" class="form-control<?= session('errors.email') ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= old('email') ?>" required aria-describedby="emailHelp<?= session('errors.email') ? ' emailError' : '' ?>">
                        <small id="emailHelp" class="text-muted d-block"><?= lang('Members.emailUsedForLogin') ?></small>
                        <?php if (session('errors.email')): ?>
                            <div id="emailError" class="invalid-feedback">
                                <?= esc(session('errors.email')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label"><?= lang('Members.password') ?> <span class="text-danger">*</span></label>
                        <input type="password" class="form-control<?= session('errors.password') ? ' is-invalid' : '' ?>" id="password" name="password" required aria-describedby="passwordHelp<?= session('errors.password') ? ' passwordError' : '' ?>">
                        <small id="passwordHelp" class="text-muted d-block"><?= lang('App.minimumCharsHint') ?></small>
                        <?php if (session('errors.password')): ?>
                            <div id="passwordError" class="invalid-feedback">
                                <?= esc(session('errors.password')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label"><?= lang('Members.role') ?> <span class="text-danger">*</span></label>
                        <select class="form-select<?= session('errors.role') ? ' is-invalid' : '' ?>" id="role" name="role" required aria-describedby="roleError">
                            <?php
                            $roleLabels = [
                                'provinceadmin' => lang('Dashboard.roleProvinceAdmin'),
                                'diocesanadmin' => lang('Dashboard.roleDiocesanAdmin'),
                                'parishadmin' => lang('Dashboard.roleParishAdmin'),
                                'clergy' => lang('Dashboard.rolePastor'),
                                'treasurer' => lang('Dashboard.roleTreasurer'),
                                'volunteer_coordinator' => lang('Dashboard.roleVolunteerCoordinator'),
                                'member' => lang('Dashboard.roleMember'),
                            ];
                            ?>
                            <?php foreach (($allowedRoles ?? []) as $role): ?>
                                <option value="<?= esc($role) ?>" <?= old('role', 'member') == $role ? 'selected' : '' ?>>
                                    <?= esc($roleLabels[$role] ?? ucwords(str_replace('_', ' ', $role))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (session('errors.role')): ?>
                            <div id="roleError" class="invalid-feedback">
                                <?= esc(session('errors.role')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Members.sectionPersonalInfo') ?></h5>

                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?= lang('Members.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= session('errors.first_name') ? ' is-invalid' : '' ?>" id="first_name" name="first_name" value="<?= old('first_name') ?>" required aria-describedby="firstNameError">
                        <?php if (session('errors.first_name')): ?>
                            <div id="firstNameError" class="invalid-feedback">
                                <?= esc(session('errors.first_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="middle_name" class="form-label"><?= lang('Members.middleName') ?></label>
                        <input type="text" class="form-control<?= session('errors.middle_name') ? ' is-invalid' : '' ?>" id="middle_name" name="middle_name" value="<?= old('middle_name') ?>" aria-describedby="middleNameError">
                        <?php if (session('errors.middle_name')): ?>
                            <div id="middleNameError" class="invalid-feedback">
                                <?= esc(session('errors.middle_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?= lang('Members.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= session('errors.last_name') ? ' is-invalid' : '' ?>" id="last_name" name="last_name" value="<?= old('last_name') ?>" required aria-describedby="lastNameError">
                        <?php if (session('errors.last_name')): ?>
                            <div id="lastNameError" class="invalid-feedback">
                                <?= esc(session('errors.last_name')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="nickname" class="form-label"><?= lang('Members.nicknameOptional') ?></label>
                        <input type="text" class="form-control<?= session('errors.nickname') ? ' is-invalid' : '' ?>" id="nickname" name="nickname" value="<?= old('nickname') ?>" maxlength="100" aria-describedby="nicknameError">
                        <?php if (session('errors.nickname')): ?>
                            <div id="nicknameError" class="invalid-feedback">
                                <?= esc(session('errors.nickname')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    $callingCodeOptions = \App\Libraries\PhoneHelper::callingCodeOptions();
                    $countryOptions = \App\Libraries\AddressHelper::countryOptions();
                    ?>

                    <div class="mb-3">
                        <label for="phone_country_code" class="form-label"><?= lang('Members.phoneCountryCode') ?></label>
                        <select class="form-select<?= session('errors.phone_country_code') ? ' is-invalid' : '' ?>" id="phone_country_code" name="phone_country_code" aria-describedby="phoneCountryCodeError">
                            <?php foreach ($callingCodeOptions as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= (string) old('phone_country_code', '+1') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
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
                        <input type="tel" class="form-control<?= session('errors.phone') ? ' is-invalid' : '' ?>" id="phone" name="phone" value="<?= old('phone') ?>" aria-describedby="phoneError">
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
                            <option value="" <?= old('country') === '' ? 'selected' : '' ?>><?= lang('Members.selectCountry') ?></option>
                            <?php foreach ($countryOptions as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= (string) old('country', '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
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
                        <input type="text" class="form-control<?= session('errors.address') ? ' is-invalid' : '' ?>" id="address" name="address" value="<?= old('address') ?>" aria-describedby="addressError">
                        <?php if (session('errors.address')): ?>
                            <div id="addressError" class="invalid-feedback">
                                <?= esc(session('errors.address')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="address_line_2" class="form-label"><?= lang('Members.addressLine2') ?></label>
                        <input type="text" class="form-control<?= session('errors.address_line_2') ? ' is-invalid' : '' ?>" id="address_line_2" name="address_line_2" value="<?= old('address_line_2') ?>" aria-describedby="addressLine2Error">
                        <?php if (session('errors.address_line_2')): ?>
                            <div id="addressLine2Error" class="invalid-feedback">
                                <?= esc(session('errors.address_line_2')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label"><?= lang('Members.city') ?></label>
                            <input type="text" class="form-control<?= session('errors.city') ? ' is-invalid' : '' ?>" id="city" name="city" value="<?= old('city') ?>" aria-describedby="cityError">
                            <?php if (session('errors.city')): ?>
                                <div id="cityError" class="invalid-feedback">
                                    <?= esc(session('errors.city')) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label"><?= lang('Members.state') ?></label>
                            <input type="text" class="form-control<?= session('errors.state') ? ' is-invalid' : '' ?>" id="state" name="state" value="<?= old('state') ?>" aria-describedby="stateError">
                            <?php if (session('errors.state')): ?>
                                <div id="stateError" class="invalid-feedback">
                                    <?= esc(session('errors.state')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="zip" class="form-label"><?= lang('Members.zip') ?></label>
                        <input type="text" class="form-control<?= session('errors.zip') ? ' is-invalid' : '' ?>" id="zip" name="zip" value="<?= old('zip') ?>" aria-describedby="zipError">
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
                        <input type="date" class="form-control<?= session('errors.birth_date') ? ' is-invalid' : '' ?>" id="birth_date" name="birth_date" value="<?= old('birth_date') ?>" aria-describedby="birthDateError">
                        <?php if (session('errors.birth_date')): ?>
                            <div id="birthDateError" class="invalid-feedback">
                                <?= esc(session('errors.birth_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="baptism_date" class="form-label"><?= lang('Members.baptismDate') ?></label>
                        <input type="date" class="form-control<?= session('errors.baptism_date') ? ' is-invalid' : '' ?>" id="baptism_date" name="baptism_date" value="<?= old('baptism_date') ?>" aria-describedby="baptismDateError">
                        <?php if (session('errors.baptism_date')): ?>
                            <div id="baptismDateError" class="invalid-feedback">
                                <?= esc(session('errors.baptism_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="is_confirmed" name="is_confirmed" value="1" <?= old('is_confirmed') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_confirmed"><?= lang('Members.isConfirmed') ?></label>
                        </div>
                        <label for="confirmation_date" class="form-label"><?= lang('Members.confirmationDate') ?></label>
                        <input type="date" class="form-control<?= session('errors.confirmation_date') ? ' is-invalid' : '' ?>" id="confirmation_date" name="confirmation_date" value="<?= old('confirmation_date') ?>" aria-describedby="confirmationDateError">
                        <?php if (session('errors.confirmation_date')): ?>
                            <div id="confirmationDateError" class="invalid-feedback">
                                <?= esc(session('errors.confirmation_date')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="spiritual_gifts" class="form-label"><?= lang('Members.spiritualGifts') ?></label>
                        <textarea class="form-control<?= session('errors.spiritual_gifts') ? ' is-invalid' : '' ?>" id="spiritual_gifts" name="spiritual_gifts" rows="3" aria-describedby="spiritualGiftsError"><?= old('spiritual_gifts') ?></textarea>
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
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Members.createAccountTitle') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewMemberPicture(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('member_picture_preview_img').src = e.target.result;
                document.getElementById('member_picture_preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('member_picture_preview').style.display = 'none';
        }
    }
</script>

<?= $this->endSection() ?>