<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="card card-public">
    <div class="brand-bar">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-person-lines-fill me-2"></i>
            <?= esc($church['name'] ?? 'Connection Card') ?>
        </h4>
        <p class="mb-0 mt-1 opacity-75 small">We're glad you're here! Fill in your details below.</p>
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
                    <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                        value="<?= esc(($old['first_name'] ?? ''), 'html') ?>"
                        autocomplete="given-name" required maxlength="100">
                </div>
                <div class="col-6">
                    <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                        value="<?= esc(($old['last_name'] ?? ''), 'html') ?>"
                        autocomplete="family-name" required maxlength="100">
                </div>
            </div>

            <!-- Contact info -->
            <div class="mb-3">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                    value="<?= esc(($old['email'] ?? ''), 'html') ?>"
                    autocomplete="email" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                    value="<?= esc(($old['phone'] ?? ''), 'html') ?>"
                    autocomplete="tel" maxlength="30">
            </div>

            <div class="mb-3">
                <label class="form-label" for="address">Home Address</label>
                <textarea id="address" name="address" class="form-control" rows="2"
                    maxlength="500"><?= esc(($old['address'] ?? ''), 'html') ?></textarea>
            </div>

            <!-- Visit date -->
            <div class="mb-3">
                <label class="form-label" for="visit_date">Date of Visit <span class="text-danger">*</span></label>
                <input type="date" id="visit_date" name="visit_date" class="form-control"
                    value="<?= esc(($old['visit_date'] ?? date('Y-m-d')), 'html') ?>"
                    required max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Interests (checkboxes) -->
            <div class="mb-4">
                <label class="form-label d-block">Areas of Interest</label>
                <div class="row g-2">
                    <?php
                    $interestOptions = [
                        'children'    => "Children's Ministry",
                        'youth'       => 'Youth Ministry',
                        'music'       => 'Music / Worship',
                        'missions'    => 'Missions',
                        'small_groups' => 'Small Groups',
                        'prayer'      => 'Prayer Team',
                        'serving'     => 'Serving',
                        'mens'        => "Men's Ministry",
                        'women'       => "Women's Ministry",
                        'seniors'     => "Seniors' Ministry",
                        'counseling'  => 'Counseling & Support',
                        'evangelism'  => 'Evangelism',
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
                    <i class="bi bi-send me-1"></i> Submit Connection Card
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnNoThanks">
                    No, thanks
                </button>
            </div>

            <script>
                document.getElementById('btnNoThanks').addEventListener('click', function() {
                    var closed = window.close();
                    if (!closed) {
                        this.closest('.card-body').innerHTML =
                            '<div class="text-center py-4 text-muted">' +
                            '<i class="bi bi-check2-circle fs-2 d-block mb-2"></i>' +
                            'You can close this window.' +
                            '</div>';
                    }
                });
            </script>
        </form>
    </div><!-- /card-body -->

    <div class="card-footer text-center text-muted small py-2">
        <i class="bi bi-shield-lock me-1"></i> Your information is kept private and secure.
    </div>
</div>

<?= $this->endSection() ?>