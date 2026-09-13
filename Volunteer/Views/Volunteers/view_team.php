<?= $this->extend('Volunteer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= esc($team['name']) ?></h2>
    <a href="<?= base_url('volunteers') ?>" class="btn btn-outline-secondary"><?= lang('App.back') ?></a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <p class="mb-1"><strong><?= lang('Volunteer.description') ?>:</strong> <?= esc($team['description'] ?? lang('App.notApplicable')) ?></p>
        <p class="mb-0"><strong><?= lang('Volunteer.membersLabel') ?>:</strong> <?= count($members ?? []) ?></p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><?= lang('Volunteer.teamMembers') ?></h6>
    </div>
    <div class="card-body">
        <?php if (auth()->user()->can('volunteer.assign')): ?>
            <form action="<?= base_url('volunteers/add-member') ?>" method="post" class="row g-2 align-items-end mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                <div class="col-md-6">
                    <label class="form-label" for="user_id"><?= lang('Volunteer.addVolunteer') ?></label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value=""><?= lang('Volunteer.selectMember') ?></option>
                        <?php foreach (($available_members ?? []) as $profile): ?>
                            <option value="<?= (int) $profile['user_id'] ?>">
                                <?= esc(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="role"><?= lang('Volunteer.role') ?></label>
                    <input class="form-control" type="text" id="role" name="role" value="member" placeholder="<?= lang('Volunteer.rolePlaceholder') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><?= lang('Volunteer.add') ?></button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($members)): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('Volunteer.volunteer') ?></th>
                            <th><?= lang('Volunteer.role') ?></th>
                            <?php if (auth()->user()->can('volunteer.assign')): ?>
                                <th class="text-end"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= esc(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></td>
                                <td><span class="text-muted"><?= esc($member['role'] ?? lang('Volunteer.roleMember')) ?></span></td>
                                <?php if (auth()->user()->can('volunteer.assign')): ?>
                                    <td class="text-end">
                                        <form action="<?= base_url('volunteers/remove-member/' . (int) $member['id']) ?>" method="post" class="d-inline"
                                            data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                            data-confirm-body="<?= esc(lang('Volunteer.removeVolunteerConfirm'), 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('Volunteer.remove') ?></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0"><?= lang('Volunteer.noMembersAssigned') ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><?= lang('Volunteer.recentSchedules') ?></h6>
        <a href="<?= base_url('volunteers/schedules?team_id=' . (int) $team['id']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calendar3"></i> <?= lang('Volunteer.viewAllSchedules') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($schedules)): ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($schedules as $schedule): ?>
                    <?php $scheduleStatus = (string) ($schedule['status'] ?? 'scheduled'); ?>
                    <?php
                    $statusColor = match ($scheduleStatus) {
                        'confirmed'  => 'success',
                        'cancelled'  => 'secondary',
                        default      => 'primary',
                    };
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <?= date('M j, Y', strtotime($schedule['service_date'])) ?>
                            &mdash; <?= esc(($schedule['first_name'] ?? '') . ' ' . ($schedule['last_name'] ?? '')) ?>
                        </span>
                        <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal">
                            <?= lang('Volunteer.status' . ucfirst($scheduleStatus)) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted mb-0"><?= lang('Volunteer.noSchedulesYet') ?></p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>