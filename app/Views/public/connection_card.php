<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="card card-public">
    <div class="brand-bar">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>
            <?= esc($parish['name'] ?? lang('Guests.ccDefaultTitle')) ?>
        </h4>
        <p class="mb-0 mt-1 opacity-75 small"><?= lang('Guests.ccIntro') ?></p>
    </div>

    <div class="card-body p-4">

        <?php if (! empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ((array) $errors as $e): ?>
                        <li><?= esc($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('connect/' . esc($token, 'url')) ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Name row -->
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label" for="first_name"><?= lang('Guests.firstName') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                        value="<?= esc(($old['first_name'] ?? ''), 'html') ?>"
                        autocomplete="given-name" required maxlength="100">
                </div>
                <div class="col-6">
                    <label class="form-label" for="last_name"><?= lang('Guests.lastName') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                        value="<?= esc(($old['last_name'] ?? ''), 'html') ?>"
                        autocomplete="family-name" required maxlength="100">
                </div>
            </div>

            <!-- Contact info -->
            <div class="mb-3">
                <label class="form-label" for="email"><?= lang('Guests.ccEmailAddress') ?></label>
                <input type="email" id="email" name="email" class="form-control"
                    value="<?= esc(($old['email'] ?? ''), 'html') ?>"
                    autocomplete="email" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone"><?= lang('Guests.ccPhoneNumber') ?></label>
                <input type="tel" id="phone" name="phone" class="form-control"
                    value="<?= esc(($old['phone'] ?? ''), 'html') ?>"
                    autocomplete="tel" maxlength="30">
            </div>

            <div class="mb-3">
                <label class="form-label" for="address"><?= lang('Guests.ccHomeAddress') ?></label>
                <textarea id="address" name="address" class="form-control" rows="2"
                    maxlength="500"><?= esc(($old['address'] ?? ''), 'html') ?></textarea>
            </div>

            <!-- Visit date -->
            <div class="mb-3">
                <label class="form-label" for="visit_date"><?= lang('Guests.ccDateOfVisit') ?> <span class="text-danger">*</span></label>
                <input type="date" id="visit_date" name="visit_date" class="form-control"
                    value="<?= esc(($old['visit_date'] ?? date('Y-m-d')), 'html') ?>"
                    required max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Interests (checkboxes) -->
            <div class="mb-4">
                <label class="form-label d-block"><?= lang('Guests.ccAreasOfInterest') ?></label>
                <div class="row g-2">
                    <?php
                    $interestOptions = [
                        'children'    => lang('Guests.interestChildren'),
                        'youth'       => lang('Guests.interestYouth'),
                        'music'       => lang('Guests.interestMusic'),
                        'missions'    => lang('Guests.interestMissions'),
                        'small_groups' => lang('Guests.interestSmallGroups'),
                        'prayer'      => lang('Guests.interestPrayer'),
                        'serving'     => lang('Guests.interestServing'),
                        'mens'        => lang('Guests.interestMens'),
                        'women'       => lang('Guests.interestWomens'),
                        'seniors'     => lang('Guests.interestSeniors'),
                        'counseling'  => lang('Guests.interestCounseling'),
                        'evangelism'  => lang('Guests.interestEvangelism'),
                    ];
                    $oldInterests = (array) ($old['interests'] ?? []);
                    foreach ($interestOptions as $slug => $label): ?>
                        <div class="col-6 col-sm-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                    name="interests[]"
                                    id="interest_<?= esc($slug) ?>"
                                    value="<?= esc($slug) ?>"
                                    <?= in_array($slug, $oldInterests, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="interest_<?= esc($slug) ?>">
                                    <?= esc($label) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-1" aria-hidden="true"></i> <?= lang('Guests.ccSubmit') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnNoThanks">
                    <?= lang('Guests.ccNoThanks') ?>
                </button>
            </div>

            <script>
                document.getElementById('btnNoThanks').addEventListener('click', function() {
                    var closed = window.close();
                    if (!closed) {
                        this.closest('.card-body').innerHTML =
                            '<div class="text-center py-4 text-muted">' +
                            '<i class="bi bi-check2-circle fs-2 d-block mb-2" aria-hidden="true"></i>' +
                            '<?= esc(lang('Guests.ccCloseWindow'), 'js') ?>' +
                            '</div>';
                    }
                });
            </script>
        </form>
    </div><!-- /card-body -->

    <div class="card-footer text-center text-muted small py-2">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> <?= lang('Guests.ccPrivacy') ?>
    </div>
</div>

<?= $this->endSection() ?>