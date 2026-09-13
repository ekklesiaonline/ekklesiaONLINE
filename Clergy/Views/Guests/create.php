<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Guests.backToGuests') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('guests/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.personalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?= lang('Guests.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?= lang('Guests.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= lang('Guests.email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label"><?= lang('Guests.phone') ?></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="visit_date" class="form-label"><?= lang('Guests.visitDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?= old('visit_date', date('Y-m-d')) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.visitInformation') ?></h5>

                    <div class="mb-3">
                        <label for="service_attended" class="form-label"><?= lang('Guests.serviceAttended') ?></label>
                        <input type="text" class="form-control" id="service_attended" name="service_attended" value="<?= old('service_attended') ?>" placeholder="<?= lang('Guests.serviceAttendedPlaceholder') ?>">
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.additionalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="source" class="form-label"><?= lang('Guests.sourceQuestion') ?></label>
                        <select class="form-select" id="source" name="source">
                            <option value=""><?= lang('Guests.selectSource') ?></option>
                            <option value="website"><?= lang('Guests.sourceWebsite') ?></option>
                            <option value="social_media"><?= lang('Guests.sourceSocialMedia') ?></option>
                            <option value="friend"><?= lang('Guests.sourceFriendFamily') ?></option>
                            <option value="flyer"><?= lang('Guests.sourceFlyerAdvertisement') ?></option>
                            <option value="event"><?= lang('Guests.sourceSpecialEvent') ?></option>
                            <option value="other"><?= lang('Guests.sourceOther') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="interests" class="form-label"><?= lang('Guests.interestsMinistries') ?></label>
                        <textarea class="form-control" id="interests" name="interests" rows="3"><?= old('interests') ?></textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.notes') ?></h5>

                    <div class="mb-3">
                        <label for="family_info" class="form-label"><?= lang('Guests.familyInformation') ?></label>
                        <textarea class="form-control" id="family_info" name="family_info" rows="3"><?= old('family_info') ?></textarea>
                        <small class="text-muted"><?= lang('Guests.familyInformationHint') ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Guests.additionalNotes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('guests') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= lang('Guests.pageTitleAdd') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>