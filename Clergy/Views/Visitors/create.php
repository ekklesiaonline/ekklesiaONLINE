<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('visitors/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Visitors.personalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?= lang('Visitors.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?= lang('Visitors.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= lang('Visitors.email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label"><?= lang('Visitors.phone') ?></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="visit_date" class="form-label"><?= lang('Visitors.visitDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?= old('visit_date', date('Y-m-d')) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Visitors.visitInformation') ?></h5>

                    <div class="mb-3">
                        <label for="service_attended" class="form-label"><?= lang('Visitors.serviceAttended') ?></label>
                        <input type="text" class="form-control" id="service_attended" name="service_attended" value="<?= old('service_attended') ?>" placeholder="<?= lang('Visitors.serviceAttendedPlaceholder') ?>">
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Visitors.additionalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="source" class="form-label"><?= lang('Visitors.sourceQuestion') ?></label>
                        <select class="form-select" id="source" name="source">
                            <option value=""><?= lang('Visitors.selectSource') ?></option>
                            <option value="website"><?= lang('Visitors.sourceWebsite') ?></option>
                            <option value="social_media"><?= lang('Visitors.sourceSocialMedia') ?></option>
                            <option value="friend"><?= lang('Visitors.sourceFriendFamily') ?></option>
                            <option value="flyer"><?= lang('Visitors.sourceFlyerAdvertisement') ?></option>
                            <option value="event"><?= lang('Visitors.sourceSpecialEvent') ?></option>
                            <option value="other"><?= lang('Visitors.sourceOther') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="interests" class="form-label"><?= lang('Visitors.interestsMinistries') ?></label>
                        <textarea class="form-control" id="interests" name="interests" rows="3"><?= old('interests') ?></textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Visitors.notes') ?></h5>

                    <div class="mb-3">
                        <label for="family_info" class="form-label"><?= lang('Visitors.familyInformation') ?></label>
                        <textarea class="form-control" id="family_info" name="family_info" rows="3"><?= old('family_info') ?></textarea>
                        <small class="text-muted"><?= lang('Visitors.familyInformationHint') ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Visitors.additionalNotes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('visitors') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Visitors.pageTitleAdd') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>