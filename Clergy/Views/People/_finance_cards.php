<?php /* Partial: donations + annual pledges cards. Required: $donations, $pledges, $userId */ ?>
            <?php if (!empty($donations) || auth()->user()->can('donation.create')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Finance.donationsTitle') ?></h6>
                        <?php if (auth()->user()->can('donation.create') && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addDonationBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Finance.recordDonation') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <p class="text-muted small mb-0"><?= lang('Finance.noDonationsFound') ?></p>
                        <?php else: ?>
                            <?php
                            $donationTotal = array_sum(array_map(static fn(array $d): float => (float) ($d['amount'] ?? 0), $donations));
                            ?>
                            <p class="fw-semibold small"><?= lang('Finance.totalDonationsLabel') ?>: <?= esc(number_format($donationTotal, 2)) ?></p>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($donations as $donation): ?>
                                    <li class="list-group-item" data-donation-id="<?= (int) ($donation['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc($donation['category'] ?? lang('Finance.categoryOther')) ?></div>
                                                <small class="text-muted"><?= esc($donation['date'] ?? '') ?> — <?= esc($donation['fund_name'] ?? lang('Finance.unassigned')) ?></small>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="fw-semibold"><?= esc(number_format((float) ($donation['amount'] ?? 0), 2)) ?></span>
                                                <?php if (auth()->user()->can('donation.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-donation-btn"
                                                        data-donation="<?= esc(json_encode(array_intersect_key($donation, array_flip(['id', 'date', 'amount', 'fund_id', 'category', 'chart_account_id', 'payment_method', 'notes', 'is_restricted', 'restriction_purpose']))), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (auth()->user()->can('donation.delete')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-donation-btn"
                                                        data-donation-id="<?= (int) ($donation['id'] ?? 0) ?>"
                                                        data-amount="<?= esc(number_format((float) ($donation['amount'] ?? 0), 2)) ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($donation['payment_method'])): ?>
                                            <small class="text-muted"><?= esc($donation['payment_method']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($donation['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($donation['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pledges) || auth()->user()->can('annual_pledge.manage')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Pledges.pageTitle') ?></h6>
                        <?php if (auth()->user()->can('annual_pledge.manage') && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addPledgeBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Pledges.addAnnualPledge') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pledges)): ?>
                            <p class="text-muted small mb-0"><?= lang('Pledges.noPledgesFound') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($pledges as $pledge):
                                    $pledgeStatus = (string) ($pledge['fulfillment_status'] ?? 'pledged');
                                    $statusColorMap = [
                                        'pledged' => 'info',
                                        'partial' => 'warning',
                                        'fulfilled' => 'success',
                                        'cancelled' => 'secondary',
                                    ];
                                    $statusLabelMap = [
                                        'pledged' => lang('Pledges.statusPledged'),
                                        'partial' => lang('Pledges.statusPartial'),
                                        'fulfilled' => lang('Pledges.statusFulfilled'),
                                        'cancelled' => lang('Pledges.statusCancelled'),
                                    ];
                                ?>
                                    <li class="list-group-item" data-pledge-id="<?= (int) ($pledge['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= (int) ($pledge['pledge_year'] ?? 0) ?></div>
                                                <small class="text-muted">
                                                    <?= esc(number_format((float) ($pledge['pledged_amount'] ?? 0), 2)) ?> /
                                                    <?= esc(number_format((float) ($pledge['fulfilled_amount'] ?? 0), 2)) ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="badge bg-<?= esc($statusColorMap[$pledgeStatus] ?? 'info') ?>"><?= esc($statusLabelMap[$pledgeStatus] ?? $pledgeStatus) ?></span>
                                                <?php if (auth()->user()->can('annual_pledge.manage')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-pledge-btn"
                                                        data-pledge="<?= esc(json_encode(array_intersect_key($pledge, array_flip(['id', 'pledge_year', 'pledged_amount', 'fulfilled_amount', 'fulfillment_status', 'pledged_at', 'notes']))), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-pledge-btn"
                                                        data-pledge-id="<?= (int) ($pledge['id'] ?? 0) ?>"
                                                        data-year="<?= (int) ($pledge['pledge_year'] ?? 0) ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($pledge['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($pledge['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
