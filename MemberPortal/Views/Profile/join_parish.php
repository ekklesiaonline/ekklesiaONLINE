<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Profile.pageTitleJoinParish') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$provinces = (isset($provinces) && is_array($provinces)) ? $provinces : [];
$diocesesByProvince = (isset($diocesesByProvince) && is_array($diocesesByProvince)) ? $diocesesByProvince : [];
$parishesByDiocese = (isset($parishesByDiocese) && is_array($parishesByDiocese)) ? $parishesByDiocese : [];

$selectedProvince = (int) old('province_id', $profile['province_id'] ?? 0);
$selectedDiocese = (int) old('diocese_id', $profile['diocese_id'] ?? 0);
$selectedParish = (int) old('parish_id', 0);
?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-8 shadow-sm">
        <div class="card-body">
            <h4 class="mb-3"><?= lang('Profile.joinParishTitle') ?></h4>
            <p class="text-muted mb-4"><?= lang('Profile.joinParishSubtitle') ?></p>

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

            <form action="<?= base_url('profile/join-parish') ?>" method="post" id="joinParishForm">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="firstName"><?= lang('Profile.firstName') ?></label>
                        <input type="text" class="form-control" id="firstName" name="first_name"
                            value="<?= esc(old('first_name', $profile['first_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lastName"><?= lang('Profile.lastName') ?></label>
                        <input type="text" class="form-control" id="lastName" name="last_name"
                            value="<?= esc(old('last_name', $profile['last_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone"><?= lang('Profile.phone') ?></label>
                        <input type="text" class="form-control" id="phone" name="phone"
                            value="<?= esc(old('phone', $profile['phone'] ?? '')) ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="province_id"><?= lang('Profile.province') ?></label>
                        <select class="form-select" id="province_id" name="province_id" required>
                            <option value=""><?= lang('Profile.selectProvince') ?></option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= (int) $province['id'] ?>" <?= (int) $province['id'] === $selectedProvince ? 'selected' : '' ?>>
                                    <?= esc(($province['province_number'] ? $province['province_number'] . ' - ' : '') . $province['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="diocese_id"><?= lang('Profile.diocese') ?></label>
                        <select class="form-select" id="diocese_id" name="diocese_id" required
                            data-selected="<?= $selectedDiocese ?>">
                            <option value=""><?= lang('Profile.selectDiocese') ?></option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="parish_id"><?= lang('Profile.church') ?></label>
                        <select class="form-select" id="parish_id" name="parish_id" required
                            data-selected="<?= $selectedParish ?>">
                            <option value=""><?= lang('Profile.selectChurch') ?></option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= base_url('profile') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> <?= lang('Profile.back') ?>
                    </a>
                    <button type="submit" class="btn btn-primary"><?= lang('Profile.submitForReview') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const diocesesByProvince = <?= json_encode($diocesesByProvince, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const parishesByDiocese = <?= json_encode($parishesByDiocese, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const provinceSelect = document.getElementById('province_id');
        const dioceseSelect = document.getElementById('diocese_id');
        const parishSelect = document.getElementById('parish_id');
        const selectedDiocese = dioceseSelect.dataset.selected || '';
        const selectedParish = parishSelect.dataset.selected || '';

        function updateDioceseSelect(preselect) {
            dioceseSelect.options.length = 0;
            dioceseSelect.add(new Option(<?= json_encode(lang('Profile.selectDiocese')) ?>, ''));
            const provinceId = provinceSelect.value;
            if (!provinceId || !diocesesByProvince[provinceId]) return;
            diocesesByProvince[provinceId].forEach(function (d) {
                const option = new Option(d.name, d.id);
                if (String(preselect) === String(d.id)) option.selected = true;
                dioceseSelect.add(option);
            });
        }

        function updateParishSelect(preselect) {
            parishSelect.options.length = 0;
            parishSelect.add(new Option(<?= json_encode(lang('Profile.selectChurch')) ?>, ''));
            const dioceseId = dioceseSelect.value;
            if (!dioceseId || !parishesByDiocese[dioceseId]) return;
            parishesByDiocese[dioceseId].forEach(function (p) {
                const label = (p.parish_ueid ? '[' + p.parish_ueid + '] ' : '') + p.name;
                const option = new Option(label, p.id);
                if (String(preselect) === String(p.id)) option.selected = true;
                parishSelect.add(option);
            });
        }

        provinceSelect.addEventListener('change', function () {
            updateDioceseSelect();
            updateParishSelect();
        });

        dioceseSelect.addEventListener('change', function () {
            updateParishSelect();
        });

        if (provinceSelect.value) {
            updateDioceseSelect(selectedDiocese);
            if (selectedDiocese) {
                updateParishSelect(selectedParish);
            }
        }
    })();
</script>
<?= $this->endSection() ?>
