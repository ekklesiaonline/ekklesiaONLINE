<?= $this->extend('Volunteer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('volunteers/schedules') ?>" class="btn btn-outline-secondary"><?= lang('App.back') ?></a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach (session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (session()->has('error')): ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
        <?php endif; ?>

        <form action="<?= base_url('volunteers/schedules/update/' . (int) $schedule['id']) ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="team_id"><?= lang('Volunteer.team') ?></label>
                    <select class="form-select" id="team_id" name="team_id" required>
                        <option value=""><?= lang('Volunteer.selectTeam') ?></option>
                        <?php foreach ($teams as $team): ?>
                            <?php $selectedTeam = (int) old('team_id', (int) ($schedule['team_id'] ?? 0)); ?>
                            <option value="<?= (int) $team['id'] ?>" <?= $selectedTeam === (int) $team['id'] ? 'selected' : '' ?>>
                                <?= esc($team['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="user_id"><?= lang('Volunteer.volunteer') ?></label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value=""><?= lang('Volunteer.selectVolunteer') ?></option>
                        <?php foreach (($members ?? []) as $member): ?>
                            <?php $selectedUser = (int) old('user_id', (int) ($schedule['user_id'] ?? 0)); ?>
                            <option value="<?= (int) $member['user_id'] ?>" <?= $selectedUser === (int) $member['user_id'] ? 'selected' : '' ?>>
                                <?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="service_date"><?= lang('Volunteer.date') ?></label>
                    <input class="form-control" type="date" id="service_date" name="service_date" value="<?= esc(old('service_date', (string) ($schedule['service_date'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="service_id"><?= lang('Volunteer.linkedService') ?></label>
                    <?php $selectedServiceId = (int) old('service_id', (int) ($schedule['service_id'] ?? 0)); ?>
                    <select class="form-select" id="service_id" name="service_id">
                        <option value=""><?= lang('Volunteer.noLinkedService') ?></option>
                        <?php foreach (($services ?? []) as $svc): ?>
                            <?php
                            $svcTypes  = !empty($svc['service']) ? (array) json_decode((string) $svc['service'], true) : [];
                            $svcLabel  = date('M j, Y', strtotime($svc['service_date']))
                                . (!empty($svc['service_time']) ? ' ' . date('g:i A', strtotime($svc['service_time'])) : '')
                                . (!empty($svcTypes) ? ' — ' . implode(', ', $svcTypes) : '');
                            ?>
                            <option value="<?= (int) $svc['id'] ?>"
                                data-service-date="<?= esc((string) ($svc['service_date'] ?? ''), 'attr') ?>"
                                <?= $selectedServiceId === (int) $svc['id'] ? 'selected' : '' ?>>
                                <?= esc($svcLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= lang('Volunteer.linkedServiceHint') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="service_time_slot"><?= lang('Volunteer.timeSlot') ?></label>
                    <input class="form-control" type="text" id="service_time_slot" name="service_time_slot" value="<?= esc(old('service_time_slot', (string) ($schedule['service_time_slot'] ?? ''))) ?>" placeholder="<?= lang('Volunteer.timeSlotPlaceholder') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="status"><?= lang('Volunteer.status') ?></label>
                    <?php $selectedStatus = (string) old('status', (string) ($schedule['status'] ?? 'scheduled')); ?>
                    <select class="form-select" id="status" name="status" required>
                        <option value="scheduled" <?= $selectedStatus === 'scheduled' ? 'selected' : '' ?>><?= lang('Volunteer.statusScheduled') ?></option>
                        <option value="confirmed" <?= $selectedStatus === 'confirmed' ? 'selected' : '' ?>><?= lang('Volunteer.statusConfirmed') ?></option>
                        <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>><?= lang('Volunteer.statusCancelled') ?></option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-outline-primary"><?= lang('Volunteer.updateSchedule') ?></button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function() {
        const teamSel = document.getElementById('team_id');
        const userSel = document.getElementById('user_id');
        const serviceSel = document.getElementById('service_id');
        const dateInput = document.getElementById('service_date');
        const apiBase = '<?= base_url('volunteers/schedules/team-members') ?>';

        const MSG_LOADING = <?= json_encode(lang('Volunteer.loadingVolunteers')) ?>;
        const MSG_SELECT = <?= json_encode(lang('Volunteer.selectVolunteer')) ?>;
        const MSG_NONE = <?= json_encode(lang('Volunteer.noTeamMembers')) ?>;

        function setOptions(members, preserveId) {
            userSel.innerHTML = '';
            if (members.length === 0) {
                userSel.disabled = true;
                userSel.add(new Option(MSG_NONE, ''));
                return;
            }
            userSel.disabled = false;
            userSel.add(new Option(MSG_SELECT, ''));
            members.forEach(function(m) {
                const opt = new Option(m.name, m.user_id);
                if (m.user_id === preserveId) {
                    opt.selected = true;
                }
                userSel.add(opt);
            });
        }

        function syncDateFromLinkedService() {
            if (!serviceSel || !dateInput) {
                return;
            }

            const selected = serviceSel.options[serviceSel.selectedIndex];
            const serviceDate = selected ? (selected.getAttribute('data-service-date') || '') : '';
            if (serviceDate !== '') {
                dateInput.value = serviceDate;
            }
        }

        teamSel.addEventListener('change', function() {
            const id = parseInt(this.value, 10);
            if (!id) {
                userSel.disabled = true;
                userSel.innerHTML = '<option value="">' + MSG_SELECT + '</option>';
                return;
            }
            userSel.disabled = true;
            userSel.innerHTML = '<option value="">' + MSG_LOADING + '</option>';
            fetch(apiBase + '/' + id, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) {
                    return r.ok ? r.json() : Promise.reject(r.status);
                })
                .then(function(data) {
                    setOptions(data.members || [], 0);
                })
                .catch(function() {
                    setOptions([], 0);
                });
        });

        if (serviceSel) {
            serviceSel.addEventListener('change', syncDateFromLinkedService);
            syncDateFromLinkedService();
        }
    }());
</script>
<?= $this->endSection() ?>