<?= $this->extend('Province\\Views\\layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('Province.manageCalendars') ?></strong>
        <a class="btn btn-sm btn-primary" href="<?= base_url('province/event-calendars/create') ?>"><i class="bi bi-plus-circle"></i> <?= lang('App.addNew') ?></a>
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
                                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('province/event-calendars/edit/' . (int) $calendar['id']) ?>"><?= lang('App.edit') ?></a>
                                    <form method="post" action="<?= base_url('province/event-calendars/delete/' . (int) $calendar['id']) ?>" class="d-inline" onsubmit="return confirm('<?= lang('App.confirmDelete') ?>')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('App.delete') ?></button>
                                    </form>
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
