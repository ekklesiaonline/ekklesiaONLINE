<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= base_url('members') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-exclamation me-1"></i> <?= lang('Members.dedupTitle') ?></span>
        <span class="badge border border-secondary text-secondary fw-normal"><?= count($pairs) ?> <?= lang('Members.dedupPairsFound') ?></span>
    </div>

    <?php if (empty($pairs)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
            <p class="mt-3 mb-0"><?= lang('Members.dedupNoDuplicates') ?></p>
        </div>
    <?php else: ?>
        <div class="card-body pb-0 pt-3 px-3">
            <p class="text-muted small mb-0">
                <i class="bi bi-sort-numeric-up me-1"></i><?= lang('Members.dedupOldestFirst') ?>
            </p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>
                                <?= lang('Members.dedupColPersonA') ?>
                                <span class="badge text-bg-success ms-1 fw-normal" style="font-size:.7em"><?= lang('Members.dedupSuggestedCanonical') ?></span>
                            </th>
                            <th><?= lang('Members.dedupColPersonB') ?></th>
                            <th class="text-center"><?= lang('Members.dedupColMatchReason') ?></th>
                            <th class="text-center"><?= lang('Members.dedupColActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pairs as $pair):
                            $badgeA = match (strtolower($pair['membership_status_a'] ?? 'pending')) {
                                'active'   => 'success',
                                'inactive' => 'secondary',
                                'visitor'  => 'info',
                                default    => 'warning',
                            };
                            $badgeB = match (strtolower($pair['membership_status_b'] ?? 'pending')) {
                                'active'   => 'success',
                                'inactive' => 'secondary',
                                'visitor'  => 'info',
                                default    => 'warning',
                            };
                        ?>
                            <tr>
                                <!-- Person A -->
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('members/view/' . (int) $pair['profile_id_a']) ?>">
                                            <?= esc($pair['display_first_a'] . ' ' . $pair['last_name_a']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted d-block"><?= esc($pair['email_a'] ?: '—') ?></small>
                                    <small class="text-muted d-block"><?= esc($pair['phone_a'] ?: '—') ?></small>
                                    <?php if (!empty($pair['birth_date_a'])): ?>
                                        <small class="text-muted d-block"><?= date('M j, Y', strtotime($pair['birth_date_a'])) ?></small>
                                    <?php endif; ?>
                                    <span class="badge border border-<?= $badgeA ?> text-<?= $badgeA ?> fw-normal mt-1"><?= esc(ucfirst($pair['membership_status_a'] ?? 'pending')) ?></span>
                                    <small class="text-muted d-block mt-1">ID #<?= (int) $pair['profile_id_a'] ?> &middot; joined <?= date('M Y', strtotime($pair['created_at_a'])) ?></small>
                                </td>

                                <!-- Person B -->
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('members/view/' . (int) $pair['profile_id_b']) ?>">
                                            <?= esc($pair['display_first_b'] . ' ' . $pair['last_name_b']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted d-block"><?= esc($pair['email_b'] ?: '—') ?></small>
                                    <small class="text-muted d-block"><?= esc($pair['phone_b'] ?: '—') ?></small>
                                    <?php if (!empty($pair['birth_date_b'])): ?>
                                        <small class="text-muted d-block"><?= date('M j, Y', strtotime($pair['birth_date_b'])) ?></small>
                                    <?php endif; ?>
                                    <span class="badge border border-<?= $badgeB ?> text-<?= $badgeB ?> fw-normal mt-1"><?= esc(ucfirst($pair['membership_status_b'] ?? 'pending')) ?></span>
                                    <small class="text-muted d-block mt-1">ID #<?= (int) $pair['profile_id_b'] ?> &middot; joined <?= date('M Y', strtotime($pair['created_at_b'])) ?></small>
                                </td>

                                <!-- Match reason -->
                                <td class="text-center">
                                    <?php if ($pair['match_reason'] === 'name'): ?>
                                        <span class="badge border border-warning text-warning fw-normal"><i class="bi bi-person-fill"></i> <?= lang('Members.matchByName') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-info text-info fw-normal"><i class="bi bi-telephone-fill"></i> <?= lang('Members.matchByPhone') ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-center">
                                    <a href="<?= base_url('members/dedup/preview?canonical=' . (int) $pair['profile_id_a'] . '&duplicate=' . (int) $pair['profile_id_b']) ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrows-collapse"></i> <?= lang('Members.mergeReview') ?>
                                    </a>
                                    <a href="<?= base_url('members/dedup/preview?canonical=' . (int) $pair['profile_id_b'] . '&duplicate=' . (int) $pair['profile_id_a']) ?>"
                                        class="btn btn-sm btn-outline-secondary ms-1"
                                        title="<?= lang('Members.swapCanonicalHint') ?>">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>