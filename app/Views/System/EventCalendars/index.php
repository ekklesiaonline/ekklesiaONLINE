<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('Events.pageTitleCalendars') ?></strong>
        <a class="btn btn-sm btn-primary" href="<?= base_url('system/event-calendars/create') ?>"><i class="bi bi-plus-circle"></i> <?= lang('App.addNew') ?></a>
    </div>
    <div class="card-body">
        <?php if (empty($calendars)): ?>
            <div class="text-center text-muted py-4"><?= lang('App.noResults') ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('Events.calendarName') ?></th>
                            <th><?= lang('Events.calendarColor') ?></th>
                            <th><?= lang('App.colStatus') ?></th>
                            <th><?= lang('App.sortOrder') ?></th>
                            <th class="text-end"><?= lang('App.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendars as $calendar): ?>
                            <tr>
                                <td><?= esc($calendar['name']) ?></td>
                                <td><span class="badge" style="background-color: <?= esc($calendar['color_hex'], 'attr') ?>;"><?= esc($calendar['color_hex']) ?></span></td>
                                <td><?= $calendar['is_active'] ? lang('App.active') : lang('App.inactive') ?></td>
                                <td><?= (int) $calendar['sort_order'] ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('system/event-calendars/edit/' . (int) $calendar['id']) ?>"><?= lang('App.edit') ?></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= base_url('system/event-calendars/delete/' . (int) $calendar['id']) ?>" onclick="return confirm('<?= lang('App.confirmDelete') ?>')"><?= lang('App.delete') ?></a>
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
