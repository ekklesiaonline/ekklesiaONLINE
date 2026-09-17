<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><?= lang('Volunteer.pageTitleTeams') ?></h4>
    <?php if (!empty($can_manage)): ?>
        <a href="<?= base_url('volunteers/createTeam') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Volunteer.createTeam') ?>
        </a>
    <?php endif; ?>
</div>

<!-- Teams -->
<div class="card mb-4">
    <div class="card-body">
        <?php if (!empty($teams)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th scope="col"><?= lang('Volunteer.team') ?></th><th scope="col"><?= lang('Volunteer.membersLabel') ?></th><th scope="col"></th></tr></thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td><?= esc($team['name'] ?? '') ?></td>
                                <td><span class="badge bg-secondary"><?= (int) ($team['member_count'] ?? 0) ?></span></td>
                                <td>
                                    <a href="<?= base_url('volunteers/viewTeam/' . (int) ($team['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('App.view') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0"><?= lang('Volunteer.noTeamsYet') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Upcoming schedules -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-calendar-week" aria-hidden="true"></i> <?= lang('Volunteer.upcomingSchedules') ?></h5>
        <?php if (!empty($can_schedule)): ?>
            <a href="<?= base_url('volunteers/create-schedule') ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Volunteer.addSchedule') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($upcoming_schedules)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th scope="col"><?= lang('Volunteer.date') ?></th><th scope="col"><?= lang('Volunteer.team') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($upcoming_schedules as $schedule): ?>
                            <tr>
                                <td><?= esc($schedule['scheduled_date'] ?? '-') ?></td>
                                <td><?= esc($schedule['team_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0"><?= lang('Volunteer.noSchedulesYet') ?></p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
