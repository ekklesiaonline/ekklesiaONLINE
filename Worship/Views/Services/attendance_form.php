<?php

/**
 * @var array $service
 * @var array $members
 * @var array $guests
 * @var array $attendanceMap
 */
?>
<?= $this->extend('Worship\\Views\\layouts/main') ?>
<?= $this->section('content') ?>
<div class="container">
    <h2><?= lang('Services.attendanceTitle') ?>: <?= esc(liturgy_format_date($service['service_date'])) ?> (<?= esc(service_type_label($service['service_type'] ?? '')) ?>)</h2>
    <div class="mb-3">
        <a href="<?= base_url('services') ?>" class="btn btn-outline-secondary btn-sm">&larr; <?= lang('App.back') ?></a>
    </div>
    <form id="attendanceForm" method="post" action="<?= base_url('attendance/save/' . $service['id']) ?>">
        <?= csrf_field() ?>
        <ul class="nav nav-tabs mb-3" id="attendanceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab" aria-controls="members" aria-selected="true"><?= lang('Services.tabMembers') ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="guests-tab" data-bs-toggle="tab" data-bs-target="#guests" type="button" role="tab" aria-controls="guests" aria-selected="false"><?= lang('Services.tabGuests') ?></button>
            </li>
        </ul>
        <div class="tab-content" id="attendanceTabsContent">
            <div class="tab-pane fade show active" id="members" role="tabpanel" aria-labelledby="members-tab">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllMembers"></th>
                            <th><?= lang('App.name') ?></th>
                            <th><?= lang('Services.colCommunion') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member):
                            $checked = isset($attendanceMap['member'][$member['id']]);
                            $communion = $checked && $attendanceMap['member'][$member['id']]['communion']; ?>
                            <tr>
                                <td><input type="checkbox" class="member-checkbox" name="member_ids[]" value="<?= $member['id'] ?>" <?= $checked ? 'checked' : '' ?>></td>
                                <td><?= esc($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                <td><input type="checkbox" class="communion-checkbox" name="communion_members[]" value="<?= $member['id'] ?>" <?= $communion ? 'checked' : '' ?> <?= !$checked ? 'disabled' : '' ?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="guests" role="tabpanel" aria-labelledby="guests-tab">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllGuests"></th>
                            <th><?= lang('App.name') ?></th>
                            <th><?= lang('Services.colCommunion') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $guest):
                            $checked = isset($attendanceMap['guest'][$guest['id']]);
                            $communion = $checked && $attendanceMap['guest'][$guest['id']]['communion']; ?>
                            <tr>
                                <td><input type="checkbox" class="guest-checkbox" name="guest_ids[]" value="<?= $guest['id'] ?>" <?= $checked ? 'checked' : '' ?>></td>
                                <td><?= esc($guest['first_name'] . ' ' . $guest['last_name']) ?></td>
                                <td><input type="checkbox" class="communion-checkbox" name="communion_guests[]" value="<?= $guest['id'] ?>" <?= $communion ? 'checked' : '' ?> <?= !$checked ? 'disabled' : '' ?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success" id="saveAttendanceBtn">
                <i class="bi bi-save"></i> <?= lang('App.save') ?>
            </button>
        </div>
    </form>
</div>
<script>
    // Bulk select for members
    document.getElementById('selectAllMembers').addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            cb.checked = checked;
            cb.dispatchEvent(new Event('change'));
        });
    });
    // Bulk select for guests
    document.getElementById('selectAllGuests').addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.guest-checkbox').forEach(cb => {
            cb.checked = checked;
            cb.dispatchEvent(new Event('change'));
        });
    });
    // Communion checkbox enable/disable
    function syncCommunionCheckboxes() {
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            const communion = cb.closest('tr').querySelector('.communion-checkbox');
            if (cb.checked) {
                communion.disabled = false;
            } else {
                communion.checked = false;
                communion.disabled = true;
            }
        });
        document.querySelectorAll('.guest-checkbox').forEach(cb => {
            const communion = cb.closest('tr').querySelector('.communion-checkbox');
            if (cb.checked) {
                communion.disabled = false;
            } else {
                communion.checked = false;
                communion.disabled = true;
            }
        });
    }
    document.querySelectorAll('.member-checkbox, .guest-checkbox').forEach(cb => {
        cb.addEventListener('change', syncCommunionCheckboxes);
    });
    // AJAX submit
    document.getElementById('attendanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('saveAttendanceBtn');
        btn.disabled = true;
        fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(resp => resp.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    showAppToast('<?= lang('Services.attendanceSaved') ?>', 'primary');
                } else {
                    showAppToast(data.error || '<?= lang('App.errorGeneric') ?>', 'danger');
                }
            })
            .catch(() => {
                btn.disabled = false;
                showAppToast('<?= lang('App.errorGeneric') ?>', 'danger');
            });
    });
</script>
<?= $this->endSection() ?>