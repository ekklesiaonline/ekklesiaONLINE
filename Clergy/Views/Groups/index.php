<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (!empty($canManage)): ?>
        <a href="<?= base_url('groups/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Groups.create') ?>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($groups)): ?>
            <p class="text-muted mb-0"><?= lang('Groups.noGroups') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Groups.name') ?></th>
                            <th scope="col"><?= lang('Groups.meetingDay') ?></th>
                            <th scope="col"><?= lang('Groups.location') ?></th>
                            <th scope="col"><?= lang('Groups.capacity') ?></th>
                            <th scope="col"><?= lang('Groups.members') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <?php
                            $isActive = (int) ($group['is_active'] ?? 0) === 1;
                            $statusColor = $isActive ? 'success' : 'secondary';
                            $memberCount = (int) ($group['member_count'] ?? 0);
                            $capacity = (int) ($group['capacity'] ?? 0);
                            $dayKey = 'Groups.day' . ucfirst((string) ($group['meeting_day'] ?? ''));
                            $dayLabel = ($group['meeting_day'] ?? '') !== '' ? lang($dayKey) : '—';
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc($group['name']) ?></th>
                                <td>
                                    <?= esc($dayLabel) ?>
                                    <?= !empty($group['meeting_time']) ? esc(substr((string) $group['meeting_time'], 0, 5)) : '' ?>
                                </td>
                                <td><?= esc($group['location'] ?? '—') ?></td>
                                <td><?= $capacity > 0 ? $memberCount . ' / ' . $capacity : lang('Groups.capacityUnlimited') ?></td>
                                <td><?= $memberCount ?></td>
                                <td><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($isActive ? 'Groups.active' : 'Groups.inactive') ?></span></td>
                                <td class="text-end">
                                    <a href="<?= base_url('groups/view/' . (int) $group['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('App.view') ?>
                                    </a>
                                    <?php if (!empty($canManage)): ?>
                                        <a href="<?= base_url('groups/edit/' . (int) $group['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
