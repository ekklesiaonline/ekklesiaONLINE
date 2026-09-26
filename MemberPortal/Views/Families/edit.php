<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('families') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Families.backToList') ?>
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Families.familyInformation') ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('families/update/' . $family['id']) ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="family_name" class="form-label"><?= lang('Families.familyNameRequired') ?></label>
                        <input type="text"
                            class="form-control <?= session('errors.family_name') ? 'is-invalid' : '' ?>"
                            id="family_name"
                            name="family_name"
                            value="<?= old('family_name', $family['family_name']) ?>"
                            placeholder="<?= lang('Families.familyNamePlaceholder') ?>"
                            required>
                        <?php if (session('errors.family_name')): ?>
                            <div class="invalid-feedback"><?= session('errors.family_name') ?></div>
                        <?php endif; ?>
                        <div class="form-text"><?= lang('Families.familyNameHelp') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="head_of_household_id" class="form-label"><?= lang('Families.headOfHousehold') ?></label>
                        <?= view('partials/_member_select_ajax', [
                            'select_id'    => 'head_of_household_id',
                            'name'         => 'head_of_household_id',
                            'members_only' => false,
                            'placeholder'  => lang('Families.notAssigned'),
                            'selected'     => $head_option ?? [],
                        ]) ?>
                        <div class="form-text"><?= lang('Families.headOfHouseholdHelp') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label"><?= lang('Families.familyAddress') ?></label>
                        <textarea class="form-control"
                            id="address"
                            name="address"
                            rows="3"
                            placeholder="<?= lang('Families.familyAddressPlaceholder') ?>"><?= old('address', $family['address']) ?></textarea>
                        <div class="form-text"><?= lang('Families.familyAddressHelp') ?></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy" aria-hidden="true"></i> <?= lang('Families.updateFamily') ?>
                        </button>
                        <a href="<?= base_url('families/view/' . $family['id']) ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="bi bi-info-circle" aria-hidden="true"></i> <?= lang('Families.editingFamily') ?>
                </h6>
                <p class="card-text small">
                    <?= lang('Families.editingFamilyHelp') ?>
                </p>
                <a href="<?= base_url('families/view/' . $family['id']) ?>" class="btn btn-sm btn-info">
                    <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('Families.viewFamilyDetails') ?>
                </a>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title text-muted"><?= lang('Families.familyId') ?></h6>
                <p class="mb-0"><code>#<?= $family['id'] ?></code></p>
                <hr>
                <h6 class="card-title text-muted"><?= lang('Families.createdOn') ?></h6>
                <p class="mb-0 small"><?= esc($formatLocalizedDate((string) ($family['created_at'] ?? ''), 'MMM d, y', $locale)) ?></p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>