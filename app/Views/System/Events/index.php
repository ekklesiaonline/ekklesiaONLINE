<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('system/events/calendar') ?>" class="btn btn-outline-primary">
        <i class="bi bi-calendar3"></i> Calendar view
    </a>
    <?php if (auth()->user()->can('system.admin')): ?>
        <a href="<?= base_url('system/events/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle"></i> <?= lang('Events.createEvent') ?>
        </a>
    <?php endif; ?>
    <?php if (auth()->user()->can('system.admin')): ?>
        <a href="<?= base_url('system/event-calendars') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-palette"></i> Manage Calendars
        </a>
    <?php endif; ?>
</div>

<?php
$eventsFiltersActive = trim((string) ($search ?? '')) !== '' || trim((string) ($status ?? '')) !== '';
?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#eventsFilterPanel"
            aria-expanded="<?= $eventsFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="eventsFilterPanel" class="collapse <?= $eventsFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('system/events') ?>" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1"><?= lang('Events.searchPlaceholder') ?></label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="<?= lang('Events.searchPlaceholder') ?>" value="<?= esc($search ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1"><?= lang('Events.colStatus') ?></label>
                    <select class="form-select form-select-sm" name="status">
                        <option value=""><?= lang('Events.allStatuses') ?></option>
                        <option value="published" <?= ($status ?? '') === 'published' ? 'selected' : '' ?>><?= lang('Events.statusPublished') ?></option>
                        <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>><?= lang('Events.statusDraft') ?></option>
                        <option value="completed" <?= ($status ?? '') === 'completed' ? 'selected' : '' ?>><?= lang('Events.statusCompleted') ?></option>
                        <option value="cancelled" <?= ($status ?? '') === 'cancelled' ? 'selected' : '' ?>><?= lang('Events.statusCancelled') ?></option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('system/events') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= lang('Events.colTitle') ?></th>
                        <th><?= lang('Events.colType') ?></th>
                        <th><?= lang('Events.colSchedule') ?></th>
                        <th><?= lang('Events.colLocation') ?></th>
                        <th><?= lang('Events.colStatus') ?></th>
                        <th><?= lang('Events.colRegistrations') ?></th>
                        <th><?= lang('Events.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?= esc((string) ($event['title'] ?? '')) ?></strong>
                                    <?php if (!empty($event['calendar_name'])): ?>
                                        <div class="mt-1">
                                            <span class="badge" style="background-color: <?= esc((string) ($event['calendar_color'] ?? '#0d6efd')) ?>; color: #fff;">
                                                <?= esc((string) $event['calendar_name']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="text-muted small"><?= esc(character_limiter(strip_tags((string) $event['description']), 90)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge border border-info text-info fw-normal"><?= esc(ucfirst((string) ($event['event_type'] ?? 'general'))) ?></span></td>
                                <td>
                                    <?= esc((string) ($event['start_at'] ?? '-')) ?>
                                    <?php if (!empty($event['end_at'])): ?>
                                        <div class="text-muted small"><?= lang('Events.scheduleTo') ?> <?= esc((string) $event['end_at']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($event['location'] ?? '-')) ?></td>
                                <td>
                                    <?php $statusClass = $event['status'] === 'published' ? 'success' : ($event['status'] === 'draft' ? 'secondary' : ($event['status'] === 'completed' ? 'primary' : 'danger')); ?>
                                    <span class="badge border border-<?= $statusClass ?> text-<?= $statusClass ?> fw-normal"><?= esc(ucfirst((string) ($event['status'] ?? 'published'))) ?></span>
                                </td>
                                <td>
                                    <strong><?= (int) ($event['registration_count'] ?? 0) ?></strong>
                                    <div class="text-muted small"><?= lang('Events.checkIns') ?>: <?= (int) ($event['checkin_count'] ?? 0) ?></div>
                                </td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <a href="<?= base_url('system/events/view/' . (int) $event['id']) ?>" class="btn btn-sm btn-info" title="<?= lang('App.view') ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (!empty($event['can_manage'])): ?>
                                            <a href="<?= base_url('system/events/edit/' . (int) $event['id']) ?>" class="btn btn-sm btn-warning" title="<?= lang('App.edit') ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['can_delete'])): ?>
                                            <a href="<?= base_url('system/events/delete/' . (int) $event['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= lang('Events.confirmDelete') ?>')" title="<?= lang('App.delete') ?>">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4"><?= lang('Events.noEvents') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
