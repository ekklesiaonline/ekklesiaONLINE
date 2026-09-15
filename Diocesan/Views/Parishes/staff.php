<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$roleLabelMap = [
    'provinceadmin' => lang('Dashboard.roleProvinceAdmin'),
    'diocesanadmin' => lang('Dashboard.roleDiocesanAdmin'),
    'parishadmin' => lang('Dashboard.roleParishAdmin'),
    'clergy' => lang('Dashboard.rolePastor'),
    'treasurer' => lang('Dashboard.roleTreasurer'),
    'volunteer_coordinator' => lang('Dashboard.roleVolunteerCoordinator'),
    'member' => lang('Dashboard.roleMember'),
];
?>

<div class="mb-4 d-flex gap-2 justify-content-end">
    <a href="<?= base_url('parish_list/edit/' . $parish['id']) ?>" class="btn btn-secondary"><?= lang('Parishes.btn_backToParish') ?></a>
    <?php if ($isProvinceAdmin): ?>
        <a href="<?= base_url('parish_list') ?>" class="btn btn-outline-secondary"><?= lang('Parishes.btn_allParishes') ?></a>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <?php $parishUeid = trim((string) ($parish['parish_ueid'] ?? '')); ?>
    <strong><?= esc($parish['name']) ?></strong>
    <span class="badge border border-secondary text-secondary fw-normal ms-2"><?= esc($parishUeid !== '' ? $parishUeid : lang('Parishes.textNotApplicable')) ?></span><br>
    <?= lang('Parishes.textOnlyAdminsLeadersShown') ?>
</div>

<div class="alert alert-secondary">
    <strong><?= lang('Parishes.textRoleActionsTitle') ?></strong>
    <ul class="mb-0 mt-1">
        <li><strong><?= lang('Parishes.btn_removeRole') ?></strong> <?= lang('Parishes.textRoleActionRemoveRole') ?></li>
        <li><strong><?= lang('Parishes.btn_demote') ?></strong> <?= lang('Parishes.textRoleActionDemote') ?></li>
    </ul>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Parishes.textCurrentAdminsLeaders') ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($staff)): ?>
                    <p class="text-muted mb-0"><?= lang('Parishes.textNoAdminsLeadersYet') ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><?= lang('App.colName') ?></th>
                                    <th><?= lang('App.colEmail') ?></th>
                                    <th><?= lang('Parishes.textRole') ?></th>
                                    <th><?= lang('App.colActions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $row): ?>
                                    <tr>
                                        <td><?= esc(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                                        <td><?= esc($row['email'] ?: lang('Parishes.textNotApplicable')) ?></td>
                                        <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc($roleLabelMap[$row['role']] ?? ucwords(str_replace('_', ' ', (string) $row['role']))) ?></span></td>
                                        <td>
                                            <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                                <form method="post" action="<?= base_url('parish_list/' . $parish['id'] . '/staff/remove-role') ?>"
                                                    data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                    data-confirm-body="<?= esc(lang('Parishes.textRemoveRoleConfirm'), 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                                                    <input type="hidden" name="role" value="<?= esc($row['role']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"><?= lang('Parishes.btn_removeRole') ?></button>
                                                </form>
                                                <form method="post" action="<?= base_url('parish_list/' . $parish['id'] . '/staff/demote') ?>"
                                                    data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                    data-confirm-body="<?= esc(lang('Parishes.textDemoteConfirm'), 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('Parishes.btn_demote') ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><?= lang('Parishes.textAddNewAdminLeader') ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('parish_list/' . $parish['id'] . '/staff/add') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label"><?= lang('Parishes.textRole') ?> *</label>
                        <select class="form-select" name="role" required>
                            <?php foreach ($allowedRoles as $role): ?>
                                <option value="<?= esc($role) ?>"><?= esc((string) ($roleLabelMap[$role] ?? ucwords(str_replace('_', ' ', (string) $role)))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= lang('Parishes.textFirstNameRequired') ?></label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= lang('Parishes.textLastNameRequired') ?></label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= lang('Parishes.textEmailRequired') ?></label>
                        <input type="email" class="form-control" name="email" required>
                        <div class="form-text"><?= lang('Parishes.textExistingEmailAssignHelp') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('Parishes.textTempPasswordOptional') ?></label>
                        <input type="password" class="form-control" name="password" minlength="8">
                        <div class="form-text"><?= lang('Parishes.textTempPasswordOptionalHelp') ?></div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100"><?= lang('Parishes.btn_createAssignRole') ?></button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><?= lang('Parishes.textPromoteExistingMember') ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('parish_list/' . $parish['id'] . '/staff/promote') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label"><?= lang('Parishes.textMemberRequired') ?></label>
                        <select class="form-select" name="user_id" required>
                            <option value=""><?= lang('Parishes.textSelectMember') ?></option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= (int) $member['user_id'] ?>">
                                    <?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                                    <?php if (!empty($member['group_list'])): ?>
                                        (<?= esc($member['group_list']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('Parishes.textRole') ?> *</label>
                        <select class="form-select" name="role" required>
                            <?php foreach ($allowedRoles as $role): ?>
                                <option value="<?= esc($role) ?>"><?= esc((string) ($roleLabelMap[$role] ?? ucwords(str_replace('_', ' ', (string) $role)))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100"><?= lang('Parishes.btn_assignRole') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0"><?= lang('Parishes.textRecentRoleActivity') ?></h6>
    </div>
    <div class="card-body">
        <?php if (empty($auditLogs)): ?>
            <p class="text-muted mb-0"><?= lang('Parishes.textNoRoleChangesYet') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('Parishes.textWhen') ?></th>
                            <th><?= lang('Parishes.textActor') ?></th>
                            <th><?= lang('Parishes.textAction') ?></th>
                            <th><?= lang('Parishes.textTarget') ?></th>
                            <th><?= lang('Parishes.textRole') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?= esc(date('Y-m-d H:i', strtotime($log['created_at']))) ?></td>
                                <td><?= esc(trim($log['actor_name']) ?: (lang('Parishes.textRoleFormatUserPrefix') . $log['actor_user_id'])) ?></td>
                                <td><?= esc(str_replace('_', ' ', ucfirst($log['action']))) ?></td>
                                <td><?= esc(trim($log['target_name']) ?: (lang('Parishes.textRoleFormatUserPrefix') . $log['target_user_id'])) ?></td>
                                <td><?= esc($log['role'] ?: lang('Parishes.textRoleFormatNoValue')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>