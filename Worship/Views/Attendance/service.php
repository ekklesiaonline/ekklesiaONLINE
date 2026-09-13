<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <p class="text-muted mb-0">
            <?php
            $displayName = esc($service['liturgical_day'] ?? '');
            if (empty($displayName)) {
                $services = json_decode($service['service'] ?? '[]', true);
                if (is_array($services) && !empty($services)) {
                    $displayName = implode(', ', array_map(static fn($value): string => esc(service_type_label((string) $value)), $services));
                } else {
                    $displayName = lang('Services.serviceFallback');
                }
            }
            echo $displayName;
            ?> - <?= esc(liturgy_format_date((string) ($service['service_date'] ?? ''))) ?>
        </p>
    </div>
    <a href="<?= base_url('services/view/' . $service['id']) ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Services.backToService') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('attendance/bulkMark') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">

            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                    <?= lang('Services.selectAll') ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                    <?= lang('Services.deselectAll') ?>
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                            </th>
                            <th><?= lang('App.colName') ?></th>
                            <th><?= lang('App.colEmail') ?></th>
                            <th><?= lang('App.colPhone') ?></th>
                            <th style="width: 100px;"><?= lang('Services.colCommunion') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <?php
                            $isAttended = isset($attendance[$member['user_id']]) && $attendance[$member['user_id']]['attended'];
                            $isCommunicant = isset($attendance[$member['user_id']]) && $attendance[$member['user_id']]['communicant'];
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="attendance-check" name="attended[]" value="<?= $member['user_id'] ?>" <?= $isAttended ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <strong><?= esc($member['first_name'] . ' ' . $member['last_name']) ?></strong>
                                </td>
                                <td><?= esc($member['email']) ?></td>
                                <td><?= esc($member['phone'] ?? '-') ?></td>
                                <td class="text-center">
                                    <input type="checkbox" name="communicant[]" value="<?= $member['user_id'] ?>" <?= $isCommunicant ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <strong><?= lang('Services.totalSelected') ?>: </strong>
                    <span id="selectedCount">0</span> / <?= count($members) ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= lang('Services.saveAttendance') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAll(checkbox) {
        const checks = document.querySelectorAll('.attendance-check');
        checks.forEach(check => check.checked = checkbox.checked);
        updateCount();
    }

    function selectAll() {
        document.getElementById('checkAll').checked = true;
        toggleAll(document.getElementById('checkAll'));
    }

    function deselectAll() {
        document.getElementById('checkAll').checked = false;
        toggleAll(document.getElementById('checkAll'));
    }

    function updateCount() {
        const checked = document.querySelectorAll('.attendance-check:checked').length;
        document.getElementById('selectedCount').textContent = checked;
    }

    // Update count on individual checkbox change
    document.querySelectorAll('.attendance-check').forEach(check => {
        check.addEventListener('change', updateCount);
    });

    // Initial count
    updateCount();
</script>

<?= $this->endSection() ?>