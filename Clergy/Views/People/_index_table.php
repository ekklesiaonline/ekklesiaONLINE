<?php /* Partial: people directory table + pagination. Required: $people, $canPortalAccessManage, $show_profile_pending_indicator, $current_page, $total_pages, $scope, $status, $selectedPerPage, $extra_filters */ ?>
<div class="card">
    <div class="card-body">
        <?php if (!empty($people)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" style="width:2.5rem">
                                <input class="form-check-input" type="checkbox" id="people-select-all" title="<?= esc(lang('App.selectAll')) ?>">
                            </th>
                            <th scope="col"><?= lang('People.colName') ?></th>
                            <th scope="col"><?= lang('People.colEmail') ?></th>
                            <th scope="col"><?= lang('Members.phone') ?></th>
                            <th scope="col"><?= lang('People.colTags') ?></th>
                            <?php if ($canPortalAccessManage): ?>
                                <th scope="col"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $person): ?>
                            <?php
                            $isMember = (int) ($person['is_member'] ?? 0) === 1;
                            $isGoodStanding = (int) ($person['is_good_standing'] ?? 0) === 1;
                            $portalAccess = strtolower((string) ($person['portal_access'] ?? 'none'));
                            if (!in_array($portalAccess, ['none', 'invited', 'enabled'], true)) {
                                $portalAccess = 'none';
                            }
                            $isNonPortal = in_array($portalAccess, ['none', 'invited'], true);
                            ?>
                            <tr>
                                <td>
                                    <input class="form-check-input people-row-check" type="checkbox"
                                        value="<?= (int) ($person['user_id'] ?? 0) ?>"
                                        data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                        data-profile-id="<?= (int) ($person['profile_id'] ?? 0) ?>"
                                        data-is-member="<?= $isMember ? '1' : '0' ?>"
                                        data-person-name="<?= esc(trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''))), 'attr') ?>"
                                        data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>">
                                </td>
                                <td>
                                    <?php
                                    $profileId = (int) ($person['profile_id'] ?? 0);
                                    $userId = (int) ($person['user_id'] ?? 0);
                                    $fullName = trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
                                    $picFile = (string) ($person['profile_picture'] ?? '');
                                    $picUrl = ($picFile !== '' && $userId > 0)
                                        ? base_url('media/profile-picture/' . $userId)
                                        : '';
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($picUrl !== ''): ?>
                                            <img src="<?= $picUrl ?>" alt="" class="person-avatar" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                                            <span class="person-avatar-fallback d-none"><i class="bi bi-person-circle person-avatar-fallback-icon" aria-hidden="true"></i></span>
                                        <?php else: ?>
                                            <span class="person-avatar-fallback"><i class="bi bi-person-circle person-avatar-fallback-icon" aria-hidden="true"></i></span>
                                        <?php endif; ?>
                                        <div>
                                            <?php if ($profileId > 0): ?>
                                                <a href="<?= base_url('people/view/' . $profileId) ?>" class="people-name-link"><?= esc($fullName) ?></a>
                                            <?php else: ?>
                                                <?= esc($fullName) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= esc((string) ($person['email'] ?? '-')) ?></td>
                                <td><?= esc((string) ($person['phone'] ?? '-')) ?></td>
                                <td>
                                    <span class="badge border border-<?= $isMember ? 'success' : 'secondary' ?> text-<?= $isMember ? 'success' : 'secondary' ?> fw-normal me-1">
                                        <?= $isMember ? lang('People.memberTag') : lang('People.nonMemberTag') ?>
                                    </span>
                                    <?php if ($isMember && $isGoodStanding): ?>
                                        <span class="badge border border-info text-info fw-normal me-1"><?= lang('People.goodStandingBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($show_profile_pending_indicator) && (int) ($person['profile_pending_completion'] ?? 0) === 1): ?>
                                        <span class="badge border border-warning text-warning fw-normal me-1"><?= lang('Members.profilePendingBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($person['leader_titles'])): ?>
                                        <span class="badge border border-primary text-primary fw-normal me-1" title="<?= esc((string) $person['leader_titles'], 'attr') ?>">
                                            <i class="bi bi-award" aria-hidden="true"></i> <?= esc((string) $person['leader_titles']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($person['is_baptized'])): ?><span class="badge border border-success text-success fw-normal me-1">B</span><?php endif; ?>
                                    <?php if (!empty($person['is_confirmed'])): ?><span class="badge border border-success text-success fw-normal me-1">C</span><?php endif; ?>
                                    <?php if (!empty($person['is_communicant'])): ?><span class="badge border border-info text-info fw-normal me-1"><?= lang('People.badgeCommunicant') ?></span><?php endif; ?>
                                    <?php if (!empty($person['is_dependent'])): ?><span class="badge border border-secondary text-secondary fw-normal me-1"><?= lang('People.badgeDependent') ?></span><?php endif; ?>
                                    <?php if (!empty($person['is_under_16'])): ?><span class="badge border border-warning text-warning fw-normal me-1">&lt;16</span><?php endif; ?>
                                    <?php if (!empty($person['has_pledge'])): ?><span class="badge border border-success text-success fw-normal me-1"><?= lang('People.badgePledge') ?></span><?php endif; ?>
                                </td>
                                <?php if ($canPortalAccessManage): ?>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if (auth()->user()->can('member.edit') && $profileId > 0): ?>
                                                <a href="<?= base_url('members/edit/' . $profileId) ?>"
                                                    class="btn btn-sm btn-outline-warning"
                                                    title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('member.edit') && !$isMember): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success mark-member-person-btn"
                                                    data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    title="<?= esc(lang('People.markAsMember'), 'attr') ?>">
                                                    <i class="bi bi-house-check" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($isNonPortal): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary enable-portal-person-btn"
                                                    data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>"
                                                    title="<?= esc(lang('People.enablePortalAccess'), 'attr') ?>">
                                                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('member.delete') && $profileId > 0 && !$isMember): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger row-delete-person-btn"
                                                    data-profile-id="<?= $profileId ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>"
                                                    title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $currentPage = max(1, (int) ($current_page ?? 1));
            $totalPages = max(1, (int) ($total_pages ?? 1));
            $queryBase = array_merge(
                ['scope' => $scope, 'status' => $status, 'per_page' => $selectedPerPage],
                $extra_filters ?? []
            );
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="<?= esc(lang('App.pagination')) ?>">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage <= 1 ? '#' : base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $currentPage - 1]))) ?>">&laquo;</a>
                        </li>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $p]))) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $currentPage + 1]))) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0"><?= lang('People.noPeopleFound') ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
