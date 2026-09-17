<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php $canEditOfficial = (bool) ($canEditOfficial ?? auth()->user()->can('parishes.manage.official')); ?>
<?php $canEditIdentity = (bool) ($canEditIdentity ?? false); ?>
<?php $canEditTaxId = (bool) ($canEditTaxId ?? false); ?>
<?php $parishData = $parish ?? []; ?>
<?php $parish = is_array($parishData) ? $parishData : []; ?>
<?php $backUrl = $canEditOfficial ? base_url('parish_list') : base_url('dashboard'); ?>

<div class="d-flex gap-2 justify-content-start align-items-center mb-3">
    <a href="<?= base_url('parish_list/' . $parish['id'] . '/staff') ?>" class="btn btn-outline-info"><?= lang('Parishes.btn_manageStaff') ?></a>
    <a href="<?= $backUrl ?>" class="btn btn-outline-secondary"><?= lang('Parishes.btn_back') ?></a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= base_url('parish_list/update/' . $parish['id']) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Logo — editable for all -->
            <h5 class="mb-3"><?= lang('Parishes.sectionParishLogo') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3 text-center">
                    <?php if (!empty($parish['logo'])): ?>
                        <img src="<?= base_url('media/parish-logo/' . (int) $parish['id']) ?>"
                            alt="<?= esc(lang('App.parishLogo')) ?>"
                            class="img-thumbnail mb-2"
                            style="max-width: 150px; max-height: 150px; object-fit: contain;">
                    <?php else: ?>
                        <div class="border bg-body-secondary rounded d-flex align-items-center justify-content-center mb-2"
                            style="width: 150px; height: 150px; margin: 0 auto;">
                            <i class="bi bi-building text-body-tertiary" style="font-size: 3rem;" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <label for="logo_upload" class="form-label"><?= lang('Parishes.labelUploadLogo') ?></label>
                    <input type="file"
                        class="form-control"
                        id="logo_upload"
                        name="logo"
                        accept="image/jpeg,image/jpg,image/png,image/gif"
                        onchange="previewParishLogo(event)">
                    <small class="text-muted d-block mt-2">
                        <?= lang('Parishes.logoHelpText') ?>
                    </small>
                    <div id="logo_preview_box" class="mt-3" style="display: none;">
                        <p class="small text-muted mb-2"><?= lang('Parishes.logoPreviewLabel') ?></p>
                        <img id="logo_preview_img" src="" alt="<?= esc(lang('App.preview')) ?>" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: contain;">
                    </div>
                </div>
            </div>

            <h5 class="mb-3"><?= esc(lang('Parishes.sectionParishBanner')) ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-12 text-center">
                    <?php if (!empty($parish['banner'])): ?>
                        <img src="<?= base_url('media/parish-banner/' . (int) $parish['id']) ?>"
                            alt="<?= esc(lang('App.parishBanner')) ?>"
                            class="img-thumbnail mb-2 w-100"
                            style="max-height: 220px; object-fit: cover;">
                    <?php else: ?>
                        <div class="border bg-body-secondary rounded d-flex align-items-center justify-content-center mb-2"
                            style="width: 100%; height: 180px; margin: 0 auto;">
                            <i class="bi bi-image text-body-tertiary" style="font-size: 3rem;" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <label for="banner_upload" class="form-label"><?= esc(lang('Parishes.labelUploadBanner')) ?></label>
                    <input type="file"
                        class="form-control"
                        id="banner_upload"
                        name="banner"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                        onchange="previewParishBanner(event)">
                    <small class="text-muted d-block mt-2"><?= esc(lang('Parishes.bannerHelpText')) ?></small>
                    <?php if (!empty($parish['banner'])): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="remove_banner" name="remove_banner">
                            <label class="form-check-label" for="remove_banner">
                                <?= esc(lang('Parishes.removeCurrentBanner')) ?>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div id="banner_preview_box" class="mt-3" style="display: none;">
                        <p class="small text-muted mb-2"><?= esc(lang('Parishes.bannerPreviewLabel')) ?></p>
                        <img id="banner_preview_img" src="" alt="<?= esc(lang('App.bannerPreview')) ?>" class="img-thumbnail w-100" style="max-height: 220px; object-fit: cover;">
                    </div>
                </div>
            </div>

            <!-- Congregation Identity — Name/UEID/Diocese read-only unless system admin; Tax ID diocesan-only -->
            <h5 class="mb-3"><?= lang('Parishes.sectionCongregationId') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label"><?= lang('Parishes.labelParishName') ?><?= $canEditIdentity ? ' *' : '' ?></label>
                    <?php if ($canEditIdentity): ?>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name', $parish['name']) ?>" required>
                    <?php else: ?>
                        <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;"><?= esc($parish['name']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= lang('App.colParishUeid') ?></label>
                    <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;"><?= esc($parish['parish_ueid'] ?? '—') ?></p>
                </div>
                <?php if ($canEditIdentity): ?>
                    <div class="col-md-6">
                        <label for="province_id" class="form-label"><?= lang('Parishes.labelProvince') ?> *</label>
                        <select class="form-select" id="province_id" name="province_id" required>
                            <option value=""><?= lang('Parishes.placeholderSelectProvince') ?></option>
                            <?php foreach (($provinces ?? []) as $province): ?>
                                <?php $selectedProvince = old('province_id', $parish['province_id'] ?? ''); ?>
                                <option value="<?= (int) $province['id'] ?>" <?= (string) $selectedProvince === (string) $province['id'] ? 'selected' : '' ?>>
                                    <?= esc($province['province_number'] . ' - ' . $province['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="diocese_id" class="form-label"><?= lang('Parishes.labelDiocese') ?> *</label>
                        <select class="form-select" id="diocese_id" name="diocese_id" required>
                            <option value=""><?= lang('Parishes.placeholderSelectDiocese') ?></option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= lang('Parishes.labelDiocese') ?></label>
                        <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;"><?= esc($parish['diocese_name'] ?? '—') ?></p>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label <?= $canEditTaxId ? 'for="tax_id"' : '' ?> class="form-label"><?= lang('Parishes.labelTaxId') ?></label>
                    <?php if ($canEditTaxId): ?>
                        <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= old('tax_id', $parish['tax_id'] ?? '') ?>" placeholder="<?= esc(lang('Parishes.placeholderTaxIdExample')) ?>">
                    <?php else: ?>
                        <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;"><?= esc($parish['tax_id'] ?? '') !== '' ? esc($parish['tax_id']) : '—' ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Physical Location — editable for all -->
            <h5 class="mb-3"><?= lang('Parishes.sectionLocation') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="street" class="form-label"><?= lang('Parishes.labelStreetAddress') ?></label>
                    <input type="text" class="form-control" id="street" name="street" value="<?= old('street', $parish['street'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="city" class="form-label"><?= lang('Parishes.labelCity') ?></label>
                    <input type="text" class="form-control" id="city" name="city" value="<?= old('city', $parish['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label"><?= lang('Parishes.labelState') ?></label>
                    <input type="text" class="form-control" id="state" name="state" value="<?= old('state', $parish['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="zip" class="form-label"><?= lang('Parishes.labelZip') ?></label>
                    <input type="text" class="form-control" id="zip" name="zip" value="<?= old('zip', $parish['zip'] ?? '') ?>" placeholder="<?= esc(lang('Parishes.placeholderZipExample')) ?>">
                </div>
                <div class="col-md-6">
                    <label for="county" class="form-label"><?= lang('Parishes.labelCounty') ?></label>
                    <input type="text" class="form-control" id="county" name="county" value="<?= old('county', $parish['county'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="country" class="form-label"><?= lang('Parishes.labelCountry') ?></label>
                    <input type="text" class="form-control" id="country" name="country" value="<?= old('country', $parish['country'] ?? '') ?>">
                </div>
            </div>

            <!-- Mailing Address — editable for all -->
            <h5 class="mb-3"><?= lang('Parishes.sectionMailingLocation') ?></h5>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="same_address_toggle_edit">
                <label class="form-check-label" for="same_address_toggle_edit">
                    <?= lang('Parishes.labelSameBuildingMailingAddress') ?>
                </label>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="mailing_street" class="form-label"><?= lang('Parishes.labelMailingStreetAddress') ?></label>
                    <input type="text" class="form-control" id="mailing_street" name="mailing_street" value="<?= old('mailing_street', $parish['mailing_street'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_city" class="form-label"><?= lang('Parishes.labelMailingCity') ?></label>
                    <input type="text" class="form-control" id="mailing_city" name="mailing_city" value="<?= old('mailing_city', $parish['mailing_city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_state" class="form-label"><?= lang('Parishes.labelMailingState') ?></label>
                    <input type="text" class="form-control" id="mailing_state" name="mailing_state" value="<?= old('mailing_state', $parish['mailing_state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_zip" class="form-label"><?= lang('Parishes.labelMailingZip') ?></label>
                    <input type="text" class="form-control" id="mailing_zip" name="mailing_zip" value="<?= old('mailing_zip', $parish['mailing_zip'] ?? '') ?>" placeholder="<?= esc(lang('Parishes.placeholderZipExample')) ?>">
                </div>
                <div class="col-md-6">
                    <label for="mailing_county" class="form-label"><?= lang('Parishes.labelMailingCounty') ?></label>
                    <input type="text" class="form-control" id="mailing_county" name="mailing_county" value="<?= old('mailing_county', $parish['mailing_county'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="mailing_country" class="form-label"><?= lang('Parishes.labelMailingCountry') ?></label>
                    <input type="text" class="form-control" id="mailing_country" name="mailing_country" value="<?= old('mailing_country', $parish['mailing_country'] ?? '') ?>">
                </div>
            </div>

            <!-- Contact Info — editable for all -->
            <h5 class="mb-3"><?= lang('Parishes.sectionContactInfo') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="email" class="form-label"><?= lang('Parishes.labelEmail') ?></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= old('email', $parish['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label"><?= lang('Parishes.labelPhone') ?></label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= old('phone', $parish['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="website" class="form-label"><?= lang('Parishes.labelWebsite') ?></label>
                    <input type="url" class="form-control" id="website" name="website" value="<?= old('website', $parish['website'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label"><?= lang('Parishes.labelAdditionalAddress') ?></label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= old('address', $parish['address'] ?? '') ?></textarea>
                    <small class="form-text text-muted"><?= lang('Parishes.labelAddressHelp') ?></small>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><?= lang('Parishes.btn_saveChanges') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const diocesesByProvince = <?= json_encode($diocesesByProvince ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function populateEditDioceses() {
        const provinceSelect = document.getElementById('province_id');
        const dioceseSelect = document.getElementById('diocese_id');
        if (!provinceSelect || !dioceseSelect) {
            return;
        }

        const selectedProvince = provinceSelect.value;
        const selectedDiocese = '<?= esc((string) old('diocese_id', $parish['diocese_id'] ?? '')) ?>';
        const items = diocesesByProvince[selectedProvince] || [];

        dioceseSelect.innerHTML = '<option value="">' + <?= json_encode(lang('Parishes.placeholderSelectDiocese')) ?> + '</option>';
        items.forEach(function(item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;
            if (selectedDiocese !== '' && String(item.id) === selectedDiocese) {
                option.selected = true;
            }
            dioceseSelect.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const provinceSelect = document.getElementById('province_id');
        if (provinceSelect) {
            populateEditDioceses();
            provinceSelect.addEventListener('change', populateEditDioceses);
        }

        const toggle = document.getElementById('same_address_toggle_edit');
        const keys = ['street', 'city', 'state', 'zip', 'county', 'country'];
        const buildingFields = keys.map((key) => document.getElementById(key)).filter(Boolean);
        const mailingFields = keys.map((key) => document.getElementById('mailing_' + key)).filter(Boolean);

        if (!toggle || buildingFields.length !== keys.length || mailingFields.length !== keys.length) {
            return;
        }

        const getFieldPair = function(id) {
            if (id.startsWith('mailing_')) {
                return {
                    current: document.getElementById(id),
                    mirror: document.getElementById(id.replace('mailing_', '')),
                };
            }

            return {
                current: document.getElementById(id),
                mirror: document.getElementById('mailing_' + id),
            };
        };

        const filledCount = function(fields) {
            return fields.reduce(function(total, field) {
                return total + (String(field.value || '').trim() !== '' ? 1 : 0);
            }, 0);
        };

        const copyAddress = function(sourcePrefix, targetPrefix) {
            keys.forEach(function(key) {
                const source = document.getElementById(sourcePrefix + key);
                const target = document.getElementById(targetPrefix + key);
                if (source && target) {
                    target.value = source.value;
                }
            });
        };

        toggle.addEventListener('change', function() {
            if (!toggle.checked) {
                return;
            }

            const buildingHasMore = filledCount(buildingFields) >= filledCount(mailingFields);
            if (buildingHasMore) {
                copyAddress('', 'mailing_');
            } else {
                copyAddress('mailing_', '');
            }
        });

        [...buildingFields, ...mailingFields].forEach(function(field) {
            field.addEventListener('input', function() {
                if (!toggle.checked) {
                    return;
                }

                const pair = getFieldPair(field.id);
                if (pair.current && pair.mirror && pair.mirror.value !== pair.current.value) {
                    pair.mirror.value = pair.current.value;
                }
            });
        });
    });

    function previewParishLogo(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logo_preview_img').src = e.target.result;
                document.getElementById('logo_preview_box').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('logo_preview_box').style.display = 'none';
        }
    }

    function previewParishBanner(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('banner_preview_img').src = e.target.result;
                document.getElementById('banner_preview_box').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('banner_preview_box').style.display = 'none';
        }
    }
</script>

<?= $this->endSection() ?>