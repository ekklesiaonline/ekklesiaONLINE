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
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Guests.backToGuests') ?>
    </a>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#guestConvFilterPanel"
            aria-expanded="<?= !empty($search) ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="guestConvFilterPanel" class="collapse <?= !empty($search) ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('guest-conversions') ?>" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label mb-1"><?= lang('Guests.searchGuests') ?></label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?= esc($search ?? '') ?>" placeholder="<?= lang('Guests.searchByNameEmailPhone') ?>">
                </div>
                <div class="col-md-2">
                    <label for="per_page" class="form-label mb-1"><?= lang('Guests.perPage') ?></label>
                    <select class="form-select form-select-sm" id="per_page" name="per_page">
                        <?php foreach ([10, 25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= (int) ($per_page ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-search" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('guest-conversions') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Guests.conversionsTitle') ?></h5>
        <span class="badge border border-secondary text-secondary fw-normal"><?= (int) ($total_rows ?? count($guests ?? [])) ?> <?= lang('Guests.records') ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($guests)): ?>
            <p class="text-muted text-center py-4 mb-0"><?= lang('Guests.noGuestsFound') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Guests.guestLabel') ?></th>
                            <th scope="col"><?= lang('Guests.colVisitDate') ?></th>
                            <th scope="col"><?= lang('Guests.colContact') ?></th>
                            <th scope="col"><?= lang('Guests.colConversion') ?></th>
                            <th scope="col"><?= lang('Guests.colAction') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $guest): ?>
                            <?php $isConverted = !empty($guest['converted_to_member_at']); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc(trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''))) ?></div>
                                    <small class="text-muted"><?= esc($guest['service_attended'] ?? '-') ?></small>
                                </td>
                                <td><?= esc($formatLocalizedDate((string) ($guest['visit_date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td>
                                    <div><?= esc($guest['email'] ?? '-') ?></div>
                                    <small class="text-muted"><?= esc($guest['phone'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <?php if ($isConverted): ?>
                                        <span class="badge border border-success text-success fw-normal"><?= lang('Guests.conversionConverted') ?></span>
                                        <div>
                                            <small class="text-muted">
                                                <?= esc(trim(($guest['converted_first_name'] ?? '') . ' ' . ($guest['converted_last_name'] ?? ''))) ?>
                                                <?php if (!empty($guest['converted_email'])): ?>
                                                    (<?= esc($guest['converted_email']) ?>)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-secondary fw-normal"><?= lang('Guests.conversionNotConverted') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isConverted): ?>
                                        <form method="post" action="<?= base_url('guest-conversions/revert/' . (int) $guest['id']) ?>"
                                            data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                            data-confirm-body="<?= esc(lang('Guests.revertConversionConfirm'), 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('Guests.revert') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= base_url('guest-conversions/convert/' . (int) $guest['id']) ?>" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <select name="user_id" class="form-select form-select-sm" required>
                                                <option value=""><?= lang('Guests.selectMember') ?></option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?= (int) $member['user_id'] ?>">
                                                        <?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                                                        <?php if (!empty($member['email'])): ?>
                                                            - <?= esc($member['email']) ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary"><?= lang('Guests.convert') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (($total_pages ?? 1) > 1): ?>
        <?php
        $currentPage = (int) ($page ?? 1);
        $query = [
            'search' => (string) ($search ?? ''),
            'per_page' => (int) ($per_page ?? 25),
        ];
        ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= lang('Guests.pageOf', [$currentPage, (int) ($total_pages ?? 1)]) ?></small>
            <div class="btn-group">
                <?php if ($currentPage > 1): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('guest-conversions') . '?' . http_build_query(array_merge($query, ['page' => $currentPage - 1])) ?>"><?= lang('App.previous') ?></a>
                <?php endif; ?>
                <?php if ($currentPage < (int) ($total_pages ?? 1)): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('guest-conversions') . '?' . http_build_query(array_merge($query, ['page' => $currentPage + 1])) ?>"><?= lang('App.next') ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>