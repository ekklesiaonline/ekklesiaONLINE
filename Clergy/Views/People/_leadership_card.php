<?php /* Partial: leadership positions card. Required: $leadership, $can_edit, $profileId, $userId */ ?>
            <?php if (!empty($leadership) || $can_edit): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Members.leadership') ?></h6>
                        <?php if ($can_edit && $profileId > 0 && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addLeadershipPositionBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Leadership.addPosition') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leadership)): ?>
                            <p class="text-muted small mb-0"><?= lang('Leadership.noPositions') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($leadership as $position): ?>
                                    <?php
                                    $scopeType = (string) ($position['scope_type'] ?? 'parish');
                                    $scopeLabel = $scopeType === 'province'
                                        ? lang('App.scopeProvince')
                                        : ($scopeType === 'diocese' ? lang('App.scopeDiocese') : lang('App.scopeParish'));
                                    $scopeName = $scopeType === 'province'
                                        ? ($position['province_name'] ?? '')
                                        : ($scopeType === 'diocese' ? ($position['diocese_name'] ?? '') : ($position['parish_name'] ?? ''));

                                    $roleCategoryLabels = [
                                        'clergy' => lang('Leadership.roleCategoryClergy'),
                                        'lay_staff' => lang('Leadership.roleCategoryLayStaff'),
                                    ];
                                    $clergyOrderLabels = [
                                        'bishop' => lang('Leadership.clergyOrderBishop'),
                                        'priest' => lang('Leadership.clergyOrderPriest'),
                                        'deacon' => lang('Leadership.clergyOrderDeacon'),
                                    ];
                                    $employmentTypeLabels = [
                                        'full_time' => lang('Leadership.employmentFullTime'),
                                        'part_time' => lang('Leadership.employmentPartTime'),
                                        'non_stipendiary' => lang('Leadership.employmentNonStipendiary'),
                                        'unpaid' => lang('Leadership.employmentUnpaid'),
                                    ];
                                    ?>
                                    <li class="list-group-item" data-position-id="<?= (int) ($position['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc($position['title'] ?? '') ?></div>
                                                <?php if (!empty($position['role_code'])): ?>
                                                    <small class="text-muted"><?= esc($position['role_code']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="badge border border-<?= !empty($position['active']) ? 'success' : 'secondary' ?> text-<?= !empty($position['active']) ? 'success' : 'secondary' ?> fw-normal">
                                                    <?= !empty($position['active']) ? lang('App.statusActive') : lang('App.statusInactive') ?>
                                                </span>
                                                <?php if ($can_edit): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-leadership-position-btn"
                                                        data-position="<?= esc(json_encode(array_intersect_key($position, array_flip(['id', 'title', 'role_code', 'scope_type', 'role_category', 'clergy_order', 'employment_type', 'started_at', 'ended_at', 'active', 'notes']))), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-leadership-position-btn"
                                                        data-position-id="<?= (int) ($position['id'] ?? 0) ?>"
                                                        data-title="<?= esc($position['title'] ?? '', 'attr') ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            <span class="badge border border-info text-info fw-normal">
                                                <?= esc($scopeLabel) ?><?= $scopeName !== '' ? ': ' . esc($scopeName) : '' ?>
                                            </span>
                                            <?php if (!empty($position['role_category']) && isset($roleCategoryLabels[$position['role_category']])): ?>
                                                <span class="badge border border-primary text-primary fw-normal"><?= esc($roleCategoryLabels[$position['role_category']]) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($position['clergy_order']) && isset($clergyOrderLabels[$position['clergy_order']])): ?>
                                                <span class="badge border border-primary text-primary fw-normal"><?= esc($clergyOrderLabels[$position['clergy_order']]) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($position['employment_type']) && isset($employmentTypeLabels[$position['employment_type']])): ?>
                                                <span class="badge border border-secondary text-secondary fw-normal"><?= esc($employmentTypeLabels[$position['employment_type']]) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1 small text-muted">
                                            <?= lang('Leadership.period') ?>:
                                            <?= !empty($position['started_at']) ? esc($position['started_at']) : '-' ?>
                                            <?= lang('Leadership.to') ?>
                                            <?= !empty($position['ended_at']) ? esc($position['ended_at']) : lang('Leadership.present') ?>
                                        </div>
                                        <?php if (!empty($position['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($position['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
