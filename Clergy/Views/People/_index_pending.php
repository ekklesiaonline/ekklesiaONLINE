<?php /* Partial: pending/incomplete registrations card + action modal. Required: $can_moderate_registrations, $pending_registrations_count, $incomplete_registrations_count, $pending_registrations, $incomplete_registrations */ ?>
<?php if (!empty($can_moderate_registrations) && (!empty($pending_registrations_count) || !empty($incomplete_registrations_count))): ?>
    <div class="card mb-3" id="pendingRegistrationsCard">
        <div class="card-header border-bottom-0 pb-0 bg-light">
            <ul class="nav nav-tabs card-header-tabs" id="pendingRegistrationsTabs" role="tablist">
                <?php if (!empty($pending_registrations_count)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ready-tab" data-bs-toggle="tab" data-bs-target="#ready-pane" type="button" role="tab" aria-controls="ready-pane" aria-selected="true">
                            <i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Members.pendingRegistrationsTitle') ?>
                            <span class="badge border border-warning text-warning fw-normal ms-1"><?= (int) $pending_registrations_count ?></span>
                        </button>
                    </li>
                <?php endif; ?>
                <?php if (!empty($incomplete_registrations_count)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= empty($pending_registrations_count) ? 'active' : '' ?>" id="incomplete-tab" data-bs-toggle="tab" data-bs-target="#incomplete-pane" type="button" role="tab" aria-controls="incomplete-pane" aria-selected="<?= empty($pending_registrations_count) ? 'true' : 'false' ?>">
                            <i class="bi bi-person-exclamation" aria-hidden="true"></i> <?= lang('Members.incompleteProfilesTitle') ?>
                            <span class="badge border border-secondary text-secondary fw-normal ms-1"><?= (int) $incomplete_registrations_count ?></span>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <form id="bulkActionForm" action="<?= base_url('members/registrations/bulk-action') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="bulk_action" id="pendingBulkAction" value="">

            <div class="tab-content" id="pendingRegistrationsTabContent">
                <?php if (!empty($pending_registrations_count)): ?>
                    <div class="tab-pane fade show active" id="ready-pane" role="tabpanel" aria-labelledby="ready-tab">
                        <div class="p-2 border-top border-bottom bg-light d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-success pending-action-btn" data-action="approve" data-title="<?= esc(lang('Dashboard.approveLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkApprove'), 'attr') ?>"><i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Dashboard.approveLabel') ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary pending-action-btn" data-action="dismiss" data-title="<?= esc(lang('Dashboard.dismissLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDismiss'), 'attr') ?>"><?= lang('Dashboard.dismissLabel') ?></button>
                            <button type="button" class="btn btn-sm btn-outline-warning pending-action-btn" data-action="ban" data-title="<?= esc(lang('Dashboard.banLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkBan'), 'attr') ?>"><?= lang('Dashboard.banLabel') ?></button>
                            <?php if (auth()->user()?->can('member.delete')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger pending-action-btn" data-action="delete" data-title="<?= esc(lang('App.delete'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDelete'), 'attr') ?>"><?= lang('App.delete') ?></button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-info pending-action-btn" data-action="escalate" data-title="<?= esc(lang('Dashboard.escalateLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkEscalate'), 'attr') ?>"><?= lang('Dashboard.escalateLabel') ?></button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" class="text-center" style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="pending-select-all" onchange="document.querySelectorAll('#ready-pane .pending-reg-check').forEach(c => c.checked = this.checked)"></th>
                                        <th scope="col"><?= lang('App.colName') ?></th>
                                        <th scope="col"><?= lang('App.colEmail') ?></th>
                                        <th scope="col"><?= lang('App.colPhone') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($pending_registrations ?? []) as $req): ?>
                                        <tr>
                                            <td class="text-center"><input type="checkbox" class="form-check-input pending-reg-check" name="request_ids[]" value="<?= (int) ($req['id'] ?? 0) ?>" form="bulkActionForm"></td>
                                            <td><?= esc(trim((string) ($req['first_name'] ?? '') . ' ' . (string) ($req['last_name'] ?? ''))) ?></td>
                                            <td><?= esc((string) ($req['email'] ?? '')) ?></td>
                                            <td><?= esc((string) ($req['phone'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($incomplete_registrations_count)): ?>
                    <div class="tab-pane fade <?= empty($pending_registrations_count) ? 'show active' : '' ?>" id="incomplete-pane" role="tabpanel" aria-labelledby="incomplete-tab">
                        <div class="p-2 border-top border-bottom bg-light d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary pending-action-btn" data-action="dismiss" data-title="<?= esc(lang('Dashboard.dismissLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDismiss'), 'attr') ?>"><?= lang('Dashboard.dismissLabel') ?></button>
                            <button type="button" class="btn btn-sm btn-outline-warning pending-action-btn" data-action="ban" data-title="<?= esc(lang('Dashboard.banLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkBan'), 'attr') ?>"><?= lang('Dashboard.banLabel') ?></button>
                            <?php if (auth()->user()?->can('member.delete')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger pending-action-btn" data-action="delete" data-title="<?= esc(lang('App.delete'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDelete'), 'attr') ?>"><?= lang('App.delete') ?></button>
                            <?php endif; ?>
                            <span class="text-muted small ms-auto"><i class="bi bi-info-circle me-1"></i><?= lang('Members.profilePendingHelp') ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" class="text-center" style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="incomplete-select-all" onchange="document.querySelectorAll('#incomplete-pane .pending-reg-check').forEach(c => c.checked = this.checked)"></th>
                                        <th scope="col"><?= lang('App.colName') ?> / <?= lang('App.username') ?></th>
                                        <th scope="col"><?= lang('App.colEmail') ?></th>
                                        <th scope="col"><?= lang('App.colPhone') ?></th>
                                        <th scope="col"><?= lang('Members.registeredAt') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($incomplete_registrations ?? []) as $req): ?>
                                        <?php
                                            $displayName = trim((string) ($req['first_name'] ?? '') . ' ' . (string) ($req['last_name'] ?? ''));
                                            if ($displayName === '') {
                                                $displayName = (string) ($req['username'] ?? '-');
                                            }
                                        ?>
                                        <tr>
                                            <td class="text-center"><input type="checkbox" class="form-check-input pending-reg-check" name="request_ids[]" value="<?= (int) ($req['id'] ?? 0) ?>" form="bulkActionForm"></td>
                                            <td>
                                                <?= esc($displayName) ?>
                                                <span class="badge border border-secondary text-secondary fw-normal ms-1"><?= lang('Members.profilePendingBadge') ?></span>
                                            </td>
                                            <td><?= esc((string) ($req['email'] ?? '')) ?></td>
                                            <td><?= esc((string) ($req['phone'] ?? '-')) ?></td>
                                            <td><small class="text-muted"><?= esc((string) ($req['created_at'] ?? '-')) ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <div class="modal fade" id="pendingActionModal" tabindex="-1" aria-labelledby="pendingActionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pendingActionModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p id="pendingActionText" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" form="bulkActionForm" id="pendingActionConfirm" class="btn btn-success"><?= lang('App.confirm') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
