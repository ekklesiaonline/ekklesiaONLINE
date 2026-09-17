<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isActive = (int) ($group['is_active'] ?? 0) === 1;
$statusColor = $isActive ? 'success' : 'secondary';
$capacity = (int) ($group['capacity'] ?? 0);
$activeCount = 0;
foreach (($roster ?? []) as $r) {
    if (($r['status'] ?? '') === 'active') {
        $activeCount++;
    }
}
$roleLabelKeys = [
    'member'    => 'Groups.roleMember',
    'leader'    => 'Groups.roleLeader',
    'co_leader' => 'Groups.roleCoLeader',
    'host'      => 'Groups.roleHost',
];
$roleBadgeColors = [
    'member'    => 'secondary',
    'leader'    => 'primary',
    'co_leader' => 'info',
    'host'      => 'warning',
];
$dayKey = 'Groups.day' . ucfirst((string) ($group['meeting_day'] ?? ''));
$dayLabel = ($group['meeting_day'] ?? '') !== '' ? lang($dayKey) : null;
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('groups') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Groups.backToGroups') ?>
    </a>
    <?php if (!empty($canManage)): ?>
        <a href="<?= base_url('groups/edit/' . (int) $group['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
        </a>
        <form method="post" action="<?= base_url('groups/delete/' . (int) $group['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Groups.confirmDelete'), 'js') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-archive" aria-hidden="true"></i> <?= lang('App.delete') ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Groups.meetingDay') ?></h6>
                <h5 class="mb-0"><?= esc($dayLabel ?? '—') ?><?= !empty($group['meeting_time']) ? ' ' . esc(substr((string) $group['meeting_time'], 0, 5)) : '' ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Groups.location') ?></h6>
                <h5 class="mb-0"><?= esc($group['location'] ?? '—') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Groups.members') ?></h6>
                <h5 class="mb-0"><?= $activeCount ?><?= $capacity > 0 ? ' / ' . $capacity : '' ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('App.colStatus') ?></h6>
                <h5 class="mb-0"><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($isActive ? 'Groups.active' : 'Groups.inactive') ?></span></h5>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($group['description'])): ?>
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-0"><?= esc($group['description']) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($canAssign)): ?>
    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><?= lang('Groups.addMember') ?></h6></div>
        <div class="card-body">
            <p class="text-muted small"><?= lang('Groups.addMemberHelp') ?></p>
            <form method="get" action="<?= base_url('groups/view/' . (int) $group['id']) ?>" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="q" class="form-label"><?= lang('Groups.searchPerson') ?></label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= esc($searchQuery ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                </div>
            </form>
            <form method="post" action="<?= base_url('groups/members/' . (int) $group['id']) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <select class="form-select" id="person_id" name="person_id" required aria-label="<?= esc(lang('Groups.addMember'), 'attr') ?>">
                        <option value="">—</option>
                        <?php foreach (($assignable ?? []) as $person): ?>
                            <option value="<?= (int) $person['person_id'] ?>" <?= !empty($person['already_member']) ? 'disabled' : '' ?>>
                                <?= esc(trim(($person['last_name'] ?? '') . ', ' . ($person['first_name'] ?? ''))) ?><?= !empty($person['already_member']) ? ' — ' . lang('Groups.alreadyMember') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="role" name="role" aria-label="<?= esc(lang('Groups.role'), 'attr') ?>">
                        <?php foreach (($roles ?? []) as $role): ?>
                            <option value="<?= esc($role) ?>"><?= lang($roleLabelKeys[$role] ?? 'Groups.roleMember') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Groups.addMember') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h6 class="mb-0"><?= lang('Groups.roster') ?></h6></div>
    <div class="card-body">
        <?php if (empty($roster)): ?>
            <p class="text-muted mb-0"><?= lang('Groups.noMembers') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Groups.name') ?></th>
                            <th scope="col"><?= lang('Groups.role') ?></th>
                            <th scope="col"><?= lang('Groups.joinedOn') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <?php if (!empty($canAssign)): ?>
                                <th scope="col"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roster as $member): ?>
                            <?php
                            $memberActive = ($member['status'] ?? '') === 'active';
                            $memberColor = $memberActive ? 'success' : 'secondary';
                            $memberRole = (string) ($member['role'] ?? 'member');
                            $memberRoleColor = $roleBadgeColors[$memberRole] ?? 'secondary';
                            $memberRoleKey = $roleLabelKeys[$memberRole] ?? 'Groups.roleMember';
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></th>
                                <td><span class="badge border border-<?= $memberRoleColor ?> text-<?= $memberRoleColor ?> fw-normal"><?= lang($memberRoleKey) ?></span></td>
                                <td><?= esc($member['joined_on'] ?? '—') ?></td>
                                <td><span class="badge border border-<?= $memberColor ?> text-<?= $memberColor ?> fw-normal"><?= lang($memberActive ? 'Groups.active' : 'Groups.inactive') ?></span></td>
                                <?php if (!empty($canAssign)): ?>
                                    <td class="text-end">
                                        <?php if ($memberActive): ?>
                                            <form method="post" action="<?= base_url('groups/members/' . (int) $group['id'] . '/role') ?>" class="d-inline-flex gap-1 align-items-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="person_id" value="<?= (int) $member['person_id'] ?>">
                                                <select name="role" class="form-select form-select-sm" style="width:auto;" aria-label="<?= esc(lang('Groups.role'), 'attr') ?>">
                                                    <?php foreach (($roles ?? []) as $role): ?>
                                                        <option value="<?= esc($role) ?>" <?= $memberRole === $role ? 'selected' : '' ?>><?= lang($roleLabelKeys[$role] ?? 'Groups.roleMember') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="<?= esc(lang('App.save'), 'attr') ?>">
                                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="<?= base_url('groups/members/' . (int) $group['id'] . '/remove') ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Groups.confirmRemove'), 'js') ?>');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="person_id" value="<?= (int) $member['person_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                    <i class="bi bi-person-dash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
