<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Manual Attendance Entry</h5>
            </div>
            <div class="card-body">
                <form id="bulkAttendanceForm" method="post" action="<?= base_url('attendance/bulk') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="service_id" class="form-label">Service</label>
                        <select id="service_id" name="service_id" class="form-select" required>
                            <?php foreach ($services as $service): ?>
                                <?php
                                $serviceDate = (string) ($service['service_date'] ?? '');
                                $serviceTime = (string) ($service['service_time'] ?? '');
                                $serviceName = trim((string) ($service['service'] ?? 'Service'));
                                $label = trim($serviceDate . ' ' . ($serviceTime !== '' ? substr($serviceTime, 0, 5) : '') . ' - ' . $serviceName);
                                ?>
                                <option value="<?= (int) $service['id'] ?>" <?= ((int) $service['id'] === (int) ($selected_service_id ?? 0)) ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="user_ids" class="form-label">Select Members</label>
                        <select id="user_ids" name="user_ids[]" class="form-select" multiple required data-placeholder="Search and select members">
                            <?php foreach ($member_options as $member): ?>
                                <option value="<?= (int) $member['id'] ?>">
                                    <?= esc($member['full_name'] . ($member['email'] !== '' ? ' (' . $member['email'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Use search in the field above to select multiple members.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="attended" name="attended" value="1" checked>
                                <label class="form-check-label" for="attended">Attended</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="communicant" name="communicant" value="1">
                                <label class="form-check-label" for="communicant">Communicant</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Record Attendance for Selected Members</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h6 class="mb-0">Recent Attendance (Selected Service)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="sticky-top">
                            <tr>
                                <th>Member</th>
                                <th class="text-center">A</th>
                                <th class="text-center">C</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentAttendanceRows">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="attendanceToast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="attendanceToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    (() => {
        const serviceSelect = document.getElementById('service_id');
        const rowsEl = document.getElementById('recentAttendanceRows');
        const attendedCheckbox = document.getElementById('attended');
        const communicantCheckbox = document.getElementById('communicant');
        const toastEl = document.getElementById('attendanceToast');
        const toastBody = document.getElementById('attendanceToastBody');
        const bsToast = new bootstrap.Toast(toastEl, {
            delay: 3000
        });

        let csrfName = <?= json_encode($csrf_name ?? csrf_token()) ?>;
        let csrfHash = <?= json_encode($csrf_hash ?? csrf_hash()) ?>;

        const showToast = (message, isSuccess = true) => {
            toastEl.classList.remove('text-bg-dark', 'text-bg-danger', 'text-bg-success');
            toastEl.classList.add(isSuccess ? 'text-bg-success' : 'text-bg-danger');
            toastBody.textContent = message;
            bsToast.show();
        };

        const updateCsrf = (payload) => {
            if (!payload || !payload.csrf || !payload.csrf_name) {
                return;
            }
            csrfName = payload.csrf_name;
            csrfHash = payload.csrf;
        };

        $('#user_ids').select2({
            width: '100%',
            placeholder: $('#user_ids').data('placeholder'),
            ajax: {
                url: '<?= base_url('attendance/search-members') ?>',
                delay: 250,
                data: (params) => ({
                    q: params.term || '',
                    limit: 30
                }),
                processResults: (data) => {
                    updateCsrf(data);
                    const members = (data.members || []).map((member) => ({
                        id: member.id,
                        text: member.full_name + (member.email ? ' (' + member.email + ')' : ''),
                    }));
                    return {
                        results: members
                    };
                },
            },
        });

        attendedCheckbox.addEventListener('change', () => {
            if (!attendedCheckbox.checked) {
                communicantCheckbox.checked = false;
                communicantCheckbox.disabled = true;
            } else {
                communicantCheckbox.disabled = false;
            }
        });

        const renderRows = (records) => {
            if (!Array.isArray(records) || records.length === 0) {
                rowsEl.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No attendance records.</td></tr>';
                return;
            }

            rowsEl.innerHTML = records.map((record) => `
                <tr data-id="${record.id}">
                    <td>
                        <div class="fw-semibold small">${record.full_name}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${record.email || ''}</div>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input js-attended" ${record.attended ? 'checked' : ''}>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input js-communicant" ${record.communicant ? 'checked' : ''} ${record.attended ? '' : 'disabled'}>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary js-save">Save</button>
                        <button type="button" class="btn btn-sm btn-outline-danger js-delete">Delete</button>
                    </td>
                </tr>
            `).join('');

            rowsEl.querySelectorAll('.js-attended').forEach((checkbox) => {
                checkbox.addEventListener('change', (event) => {
                    const row = event.target.closest('tr');
                    const communicant = row.querySelector('.js-communicant');
                    if (!event.target.checked) {
                        communicant.checked = false;
                        communicant.disabled = true;
                    } else {
                        communicant.disabled = false;
                    }
                });
            });
        };

        const loadRecent = async () => {
            const serviceId = serviceSelect.value;
            if (!serviceId) {
                renderRows([]);
                return;
            }

            rowsEl.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>';

            try {
                const response = await fetch(`<?= base_url('attendance/recent') ?>/${serviceId}`);
                const payload = await response.json();
                updateCsrf(payload);

                if (!response.ok || !payload.success) {
                    renderRows([]);
                    return;
                }

                renderRows(payload.records || []);
            } catch (error) {
                renderRows([]);
            }
        };

        rowsEl.addEventListener('click', async (event) => {
            const target = event.target;
            const row = target.closest('tr[data-id]');
            if (!row) {
                return;
            }

            const id = row.getAttribute('data-id');
            const attended = row.querySelector('.js-attended').checked;
            const communicant = row.querySelector('.js-communicant').checked;

            if (target.classList.contains('js-save')) {
                const body = new URLSearchParams();
                body.set(csrfName, csrfHash);
                body.set('attended', attended ? '1' : '0');
                body.set('communicant', communicant ? '1' : '0');

                try {
                    const response = await fetch(`<?= base_url('attendance/update') ?>/${id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body.toString(),
                    });
                    const payload = await response.json();
                    updateCsrf(payload);

                    if (!response.ok || !payload.success) {
                        showToast(payload.message || 'Update failed.', false);
                        return;
                    }

                    showToast(payload.message || 'Attendance updated.');
                    loadRecent();
                } catch (error) {
                    showToast('Network error while updating.', false);
                }
            }

            if (target.classList.contains('js-delete')) {
                showAppConfirm(
                    '<?= esc(lang('App.confirm'), 'js') ?>',
                    'Delete this attendance record?',
                    async function() {
                        const body = new URLSearchParams();
                        body.set(csrfName, csrfHash);

                        try {
                            const response = await fetch(`<?= base_url('attendance/delete') ?>/${id}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: body.toString(),
                            });
                            const payload = await response.json();
                            updateCsrf(payload);

                            if (!response.ok || !payload.success) {
                                showToast(payload.message || 'Delete failed.', false);
                                return;
                            }

                            showToast(payload.message || 'Attendance deleted.');
                            loadRecent();
                        } catch (error) {
                            showToast('Network error while deleting.', false);
                        }
                    }
                );
            }
        });

        serviceSelect.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('service_id', serviceSelect.value);
            window.history.replaceState({}, '', url.toString());
            loadRecent();
        });

        <?php if (!empty($flash_success)): ?>
            showToast(<?= json_encode((string) $flash_success) ?>, true);
        <?php endif; ?>
        <?php if (!empty($flash_error)): ?>
            showToast(<?= json_encode((string) $flash_error) ?>, false);
        <?php endif; ?>

        loadRecent();
    })();
</script>
<?= $this->endSection() ?>