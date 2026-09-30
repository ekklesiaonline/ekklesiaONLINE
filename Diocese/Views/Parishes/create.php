<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('parish_list') ?>" class="btn btn-outline-secondary"><?= lang('Parishes.btn_back') ?></a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= base_url('parish_list/store') ?>">
            <?= csrf_field() ?>

            <h5 class="mb-3"><?= lang('Parishes.sectionCongregationId') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label for="name" class="form-label"><?= lang('Parishes.labelParishName') ?> *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" minlength="3" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label for="diocese_id" class="form-label"><?= lang('Parishes.labelDiocese') ?> *</label>
                    <select class="form-select" id="diocese_id" name="diocese_id" required>
                        <option value=""><?= lang('Parishes.placeholderSelectDiocese') ?></option>
                        <?php foreach (($dioceses ?? []) as $diocese): ?>
                            <option value="<?= (int) ($diocese['id'] ?? 0) ?>" <?= (string) old('diocese_id') === (string) ($diocese['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= esc((string) (($diocese['province_number'] ?? '') . ' - ' . ($diocese['name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h5 class="mb-3"><?= lang('Parishes.sectionLocation') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="street" class="form-label"><?= lang('Parishes.labelStreetAddress') ?></label>
                    <input type="text" class="form-control" id="street" name="street" value="<?= old('street') ?>">
                </div>
                <div class="col-md-4">
                    <label for="city" class="form-label"><?= lang('Parishes.labelCity') ?></label>
                    <input type="text" class="form-control" id="city" name="city" value="<?= old('city') ?>">
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label"><?= lang('Parishes.labelState') ?></label>
                    <input type="text" class="form-control" id="state" name="state" value="<?= old('state') ?>">
                </div>
                <div class="col-md-4">
                    <label for="zip" class="form-label"><?= lang('Parishes.labelZip') ?></label>
                    <input type="text" class="form-control" id="zip" name="zip" value="<?= old('zip') ?>" placeholder="<?= esc(lang('Parishes.placeholderZipExample')) ?>">
                </div>
                <div class="col-md-6">
                    <label for="county" class="form-label"><?= lang('Parishes.labelCounty') ?></label>
                    <input type="text" class="form-control" id="county" name="county" value="<?= old('county') ?>">
                </div>
                <div class="col-md-6">
                    <label for="country" class="form-label"><?= lang('Parishes.labelCountry') ?></label>
                    <input type="text" class="form-control" id="country" name="country" value="<?= old('country', 'United States') ?>">
                </div>
            </div>

            <h5 class="mb-3"><?= lang('Parishes.sectionMailingLocation') ?></h5>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="same_address_toggle_create">
                <label class="form-check-label" for="same_address_toggle_create">
                    <?= lang('Parishes.labelSameBuildingMailingAddress') ?>
                </label>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="mailing_street" class="form-label"><?= lang('Parishes.labelMailingStreetAddress') ?></label>
                    <input type="text" class="form-control" id="mailing_street" name="mailing_street" value="<?= old('mailing_street') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_city" class="form-label"><?= lang('Parishes.labelMailingCity') ?></label>
                    <input type="text" class="form-control" id="mailing_city" name="mailing_city" value="<?= old('mailing_city') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_state" class="form-label"><?= lang('Parishes.labelMailingState') ?></label>
                    <input type="text" class="form-control" id="mailing_state" name="mailing_state" value="<?= old('mailing_state') ?>">
                </div>
                <div class="col-md-4">
                    <label for="mailing_zip" class="form-label"><?= lang('Parishes.labelMailingZip') ?></label>
                    <input type="text" class="form-control" id="mailing_zip" name="mailing_zip" value="<?= old('mailing_zip') ?>" placeholder="<?= esc(lang('Parishes.placeholderZipExample')) ?>">
                </div>
                <div class="col-md-6">
                    <label for="mailing_county" class="form-label"><?= lang('Parishes.labelMailingCounty') ?></label>
                    <input type="text" class="form-control" id="mailing_county" name="mailing_county" value="<?= old('mailing_county') ?>">
                </div>
                <div class="col-md-6">
                    <label for="mailing_country" class="form-label"><?= lang('Parishes.labelMailingCountry') ?></label>
                    <input type="text" class="form-control" id="mailing_country" name="mailing_country" value="<?= old('mailing_country', 'United States') ?>">
                </div>
            </div>

            <h5 class="mb-3"><?= lang('Parishes.sectionContactInfo') ?></h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="email" class="form-label"><?= lang('Parishes.labelEmail') ?></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label"><?= lang('Parishes.labelPhone') ?></label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= old('phone') ?>" maxlength="50">
                </div>
                <div class="col-md-6">
                    <label for="website" class="form-label"><?= lang('Parishes.labelWebsite') ?></label>
                    <input type="url" class="form-control" id="website" name="website" value="<?= old('website') ?>">
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label"><?= lang('Parishes.labelAddress') ?></label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= old('address') ?>" maxlength="500">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><?= lang('Parishes.btn_createParish') ?></button>
                <a href="<?= base_url('parish_list') ?>" class="btn btn-outline-secondary"><?= lang('Parishes.btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('same_address_toggle_create');
        const keys = ['street', 'city', 'state', 'zip', 'county', 'country'];
        const buildingFields = keys.map(function(key) {
            return document.getElementById(key);
        }).filter(Boolean);
        const mailingFields = keys.map(function(key) {
            return document.getElementById('mailing_' + key);
        }).filter(Boolean);

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
</script>

<?= $this->endSection() ?>