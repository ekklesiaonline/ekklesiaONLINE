<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('families') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Families.backToList') ?>
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Families.familyInformation') ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('families/store') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="family_name" class="form-label"><?= lang('Families.familyNameRequired') ?></label>
                        <input type="text"
                            class="form-control <?= session('errors.family_name') ? 'is-invalid' : '' ?>"
                            id="family_name"
                            name="family_name"
                            value="<?= old('family_name') ?>"
                            placeholder="<?= lang('Families.familyNamePlaceholder') ?>"
                            required>
                        <?php if (session('errors.family_name')): ?>
                            <div class="invalid-feedback"><?= session('errors.family_name') ?></div>
                        <?php endif; ?>
                        <div class="form-text"><?= lang('Families.familyNameHelp') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="head_of_household_id" class="form-label"><?= lang('Families.headOfHousehold') ?></label>
                        <select class="form-select" id="head_of_household_id" name="head_of_household_id">
                            <option value=""><?= lang('Families.notAssignedCanSetLater') ?></option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= $member['user_id'] ?>"
                                    <?= old('head_of_household_id') == $member['user_id'] ? 'selected' : '' ?>>
                                    <?= esc($member['first_name'] . ' ' . $member['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= lang('Families.headOfHouseholdHelp') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label"><?= lang('Families.familyAddress') ?></label>
                        <textarea class="form-control"
                            id="address"
                            name="address"
                            rows="3"
                            placeholder="<?= lang('Families.familyAddressPlaceholder') ?>"><?= old('address') ?></textarea>
                        <div class="form-text"><?= lang('Families.familyAddressHelp') ?></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy"></i> <?= lang('Families.create') ?>
                        </button>
                        <a href="<?= base_url('families') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="bi bi-info-circle"></i> <?= lang('Families.familyManagement') ?>
                </h6>
                <p class="card-text small">
                    <?= lang('Families.familyManagementIntro') ?>
                </p>
                <ul class="small mb-0">
                    <li><?= lang('Families.tipAddMembers') ?></li>
                    <li><?= lang('Families.tipAssignRelationships') ?></li>
                    <li><?= lang('Families.tipTrackAttendance') ?></li>
                    <li><?= lang('Families.tipGenerateReports') ?></li>
                    <li><?= lang('Families.tipManageContacts') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>