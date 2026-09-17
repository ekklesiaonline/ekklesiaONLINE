<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('system/events/calendar') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Events.calendarBoard') ?>
    </a>
    <?php if (!empty($can_manage_event)): ?>
        <a href="<?= base_url('system/events/edit/' . (int) ($event['id'] ?? 0)) ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <h4><?= esc((string) ($event['title'] ?? '')) ?></h4>
        <?php if (!empty($event['calendar_name'])): ?>
            <span class="badge" style="background-color: <?= esc((string) ($event['calendar_color'] ?? '#0d6efd')) ?>; color: #fff;"><?= esc($event['calendar_name']) ?></span>
        <?php endif; ?>
        <span class="badge border border-secondary text-secondary fw-normal"><?= esc(ucfirst((string) ($event['status'] ?? 'published'))) ?></span>

        <hr>

        <div class="row g-3">
            <div class="col-md-6">
                <strong><?= lang('Events.colSchedule') ?></strong>
                <p class="mb-1"><?= esc((string) ($event['start_at'] ?? '-')) ?></p>
                <?php if (!empty($event['end_at'])): ?>
                    <p class="text-muted"><?= lang('Events.scheduleTo') ?> <?= esc((string) $event['end_at']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <strong><?= lang('Events.colLocation') ?></strong>
                <p><?= esc((string) ($event['location'] ?? '-')) ?></p>
            </div>
            <div class="col-md-6">
                <strong><?= lang('Events.visibility') ?></strong>
                <p><?= esc(ucfirst((string) ($event['visibility'] ?? 'members'))) ?></p>
            </div>
            <div class="col-md-6">
                <strong><?= lang('Events.capacity') ?></strong>
                <p><?= !empty($event['capacity']) ? (int) $event['capacity'] : lang('Events.capacityUnlimited') ?></p>
            </div>
            <?php if (!empty($event['description'])): ?>
                <div class="col-12">
                    <strong><?= lang('Events.description') ?></strong>
                    <div><?= nl2br(esc((string) $event['description'])) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($event['cover_path'])): ?>
                <div class="col-12">
                    <strong><?= lang('Events.coverImage') ?></strong>
                    <img src="<?= base_url('media/event-cover/' . (int) ($event['id'] ?? 0)) ?>" alt="Event cover" class="img-fluid rounded mt-2" style="max-height: 320px; object-fit: cover;">
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($registrations)): ?>
    <div class="card mt-3">
        <div class="card-header"><strong><?= lang('Events.registrations') ?> (<?= (int) ($event['registration_count'] ?? 0) ?>)</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Events.colParticipant') ?></th>
                            <th scope="col"><?= lang('Events.colRegistered') ?></th>
                            <th scope="col"><?= lang('Events.colStatus') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                            <tr>
                                <td><?= esc((string) ($registration['participant_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($registration['registered_at'] ?? '-')) ?></td>
                                <td><?= esc(ucfirst((string) ($registration['status'] ?? 'registered'))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
