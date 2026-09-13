<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <?php if (!empty(session('errors'))): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ((array) session('errors') as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('event-calendars/store') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label"><?= lang('Events.calendarName') ?></label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= esc(old('name')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="color_hex" class="form-label"><?= lang('Events.calendarColor') ?></label>
                        <input type="color" id="color_hex" name="color_hex" class="form-control form-control-color" value="<?= esc(old('color_hex', '#0d6efd')) ?>" title="<?= esc(lang('Events.calendarColor')) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="scope_level" class="form-label"><?= lang('Events.scope') ?></label>
                        <select id="scope_level" name="scope_level" class="form-select">
                            <option value="parish"><?= lang('Events.scopeParish') ?></option>
                            <?php if (!empty($can_assign_diocese_scope)): ?>
                                <option value="diocese"><?= lang('Events.scopeDiocese') ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary"><?= lang('Events.saveCalendar') ?></button>
                        <a href="<?= base_url('events/calendar') ?>" class="btn btn-outline-primary"><?= lang('Events.calendarBoard') ?></a>
                        <a href="<?= base_url('events/create') ?>" class="btn btn-outline-secondary"><?= lang('Events.backToCreateEvent') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= lang('Events.calendarName') ?></th>
                                <th><?= lang('Events.scope') ?></th>
                                <th><?= lang('Events.calendarColor') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($calendars ?? []) as $calendar): ?>
                                <tr>
                                    <td><?= esc((string) ($calendar['name'] ?? '')) ?></td>
                                    <td><?= lang((string) ($calendar['scope_level'] ?? 'parish') === 'diocese' ? 'Events.scopeDiocese' : 'Events.scopeParish') ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= esc((string) ($calendar['color_hex'] ?? '#0d6efd')) ?>; color: #fff;">
                                            <?= esc((string) ($calendar['color_hex'] ?? '#0d6efd')) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>