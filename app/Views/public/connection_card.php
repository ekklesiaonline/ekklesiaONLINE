<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="card card-public">
    <div class="brand-bar">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>
            <?= esc($parish['name'] ?? lang('Visitors.ccDefaultTitle')) ?>
        </h4>
        <p class="mb-0 mt-1 opacity-75 small"><?= lang('Visitors.ccIntro') ?></p>
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
                    <label class="form-label" for="first_name"><?= lang('Visitors.firstName') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                        value="<?= esc(($old['first_name'] ?? ''), 'html') ?>"
                        autocomplete="given-name" required maxlength="100">
                </div>
                <div class="col-6">
                    <label class="form-label" for="last_name"><?= lang('Visitors.lastName') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                        value="<?= esc(($old['last_name'] ?? ''), 'html') ?>"
                        autocomplete="family-name" required maxlength="100">
                </div>
            </div>

            <!-- Contact info -->
            <div class="mb-3">
                <label class="form-label" for="email"><?= lang('Visitors.ccEmailAddress') ?></label>
                <input type="email" id="email" name="email" class="form-control"
                    value="<?= esc(($old['email'] ?? ''), 'html') ?>"
                    autocomplete="email" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone"><?= lang('Visitors.ccPhoneNumber') ?></label>
                <input type="tel" id="phone" name="phone" class="form-control"
                    value="<?= esc(($old['phone'] ?? ''), 'html') ?>"
                    autocomplete="tel" maxlength="30">
            </div>

            <div class="mb-3">
                <label class="form-label" for="address"><?= lang('Visitors.ccHomeAddress') ?></label>
                <textarea id="address" name="address" class="form-control" rows="2"
                    maxlength="500"><?= esc(($old['address'] ?? ''), 'html') ?></textarea>
            </div>

            <!-- Visit date -->
            <div class="mb-3">
                <label class="form-label" for="visit_date"><?= lang('Visitors.ccDateOfVisit') ?> <span class="text-danger">*</span></label>
                <input type="date" id="visit_date" name="visit_date" class="form-control"
                    value="<?= esc(($old['visit_date'] ?? date('Y-m-d')), 'html') ?>"
                    required max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Interests (checkboxes) -->
            <div class="mb-4">
                <label class="form-label d-block"><?= lang('Visitors.ccAreasOfInterest') ?></label>
                <div class="row g-2">
                    <?php
                    $interestOptions = [
                        'children'    => lang('Visitors.interestChildren'),
                        'youth'       => lang('Visitors.interestYouth'),
                        'music'       => lang('Visitors.interestMusic'),
                        'missions'    => lang('Visitors.interestMissions'),
                        'small_groups' => lang('Visitors.interestSmallGroups'),
                        'prayer'      => lang('Visitors.interestPrayer'),
                        'serving'     => lang('Visitors.interestServing'),
                        'mens'        => lang('Visitors.interestMens'),
                        'women'       => lang('Visitors.interestWomens'),
                        'seniors'     => lang('Visitors.interestSeniors'),
                        'counseling'  => lang('Visitors.interestCounseling'),
                        'evangelism'  => lang('Visitors.interestEvangelism'),
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
                    <i class="bi bi-send me-1" aria-hidden="true"></i> <?= lang('Visitors.ccSubmit') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnNoThanks">
                    <?= lang('Visitors.ccNoThanks') ?>
                </button>
            </div>

            <script>
                document.getElementById('btnNoThanks').addEventListener('click', function() {
                    var closed = window.close();
                    if (!closed) {
                        this.closest('.card-body').innerHTML =
                            '<div class="text-center py-4 text-muted">' +
                            '<i class="bi bi-check2-circle fs-2 d-block mb-2" aria-hidden="true"></i>' +
                            '<?= esc(lang('Visitors.ccCloseWindow'), 'js') ?>' +
                            '</div>';
                    }
                });
            </script>
        </form>
    </div><!-- /card-body -->

    <div class="card-footer text-center text-muted small py-2">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> <?= lang('Visitors.ccPrivacy') ?>
    </div>
</div>

<?= $this->endSection() ?>