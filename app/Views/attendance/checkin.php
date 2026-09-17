<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Attendance.checkinTitle') ?></h5>
                <span class="badge text-bg-primary"><?= lang('Attendance.kioskMode') ?></span>
            </div>
            <div class="card-body">
                <form id="checkinForm" method="post" action="<?= base_url('attendance/checkin') ?>" data-allow-repeat-submit="true">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="service_id" class="form-label"><?= lang('Attendance.colService') ?></label>
                        <select id="service_id" name="service_id" class="form-select" required>
                            <?php foreach ($services as $service): ?>
                                <?php
                                $serviceDate = (string) ($service['service_date'] ?? '');
                                $serviceTime = (string) ($service['service_time'] ?? '');
                                $serviceName = trim((string) ($service['service'] ?? 'Service'));
                                $label = trim($serviceDate . ' ' . ($serviceTime !== '' ? substr($serviceTime, 0, 5) : '') . ' - ' . $serviceName);
                                ?>
                                <option value="<?= (int) $service['id'] ?>" <?= ((int) $service['id'] === (int) ($default_service_id ?? 0)) ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="member_search" class="form-label"><?= lang('Attendance.memberSearchLabel') ?></label>
                        <input type="text" id="member_search" class="form-control" autocomplete="off" placeholder="<?= esc(lang('Attendance.memberSearchPlaceholder')) ?>" required>
                        <input type="hidden" id="user_id" name="user_id" required>
                        <div id="member_results" class="list-group position-absolute w-100 mt-1 shadow-sm d-none" style="z-index: 10; max-height: 260px; overflow-y: auto;"></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="attended" name="attended" value="1" checked>
                                <label class="form-check-label" for="attended"><?= lang('Attendance.attendedLabel') ?></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="communicant" name="communicant" value="1">
                                <label class="form-check-label" for="communicant"><?= lang('Dashboard.sacramentCommunicant') ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg"><?= lang('Attendance.checkInBtn') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="attendanceToast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="attendanceToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= esc(lang('App.close')) ?>"></button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (() => {
        const form = document.getElementById('checkinForm');
        const memberSearch = document.getElementById('member_search');
        const userIdInput = document.getElementById('user_id');
        const results = document.getElementById('member_results');
        const attendedCheckbox = document.getElementById('attended');
        const communicantCheckbox = document.getElementById('communicant');
        const toastEl = document.getElementById('attendanceToast');
        const toastBody = document.getElementById('attendanceToastBody');
        const bsToast = new bootstrap.Toast(toastEl, {
            delay: 3000
        });

        let csrfName = <?= json_encode($csrf_name ?? csrf_token()) ?>;
        let csrfHash = <?= json_encode($csrf_hash ?? csrf_hash()) ?>;
        let searchTimer = null;

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

        const hideResults = () => {
            results.classList.add('d-none');
            results.innerHTML = '';
        };

        const renderResults = (members) => {
            if (!Array.isArray(members) || members.length === 0) {
                hideResults();
                return;
            }

            results.innerHTML = '';
            members.forEach((member) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.textContent = `${member.full_name}${member.email ? ' (' + member.email + ')' : ''}`;
                button.addEventListener('click', () => {
                    memberSearch.value = member.full_name;
                    userIdInput.value = String(member.id);
                    hideResults();
                });
                results.appendChild(button);
            });
            results.classList.remove('d-none');
        };

        memberSearch.addEventListener('input', () => {
            userIdInput.value = '';
            const term = memberSearch.value.trim();

            if (searchTimer) {
                clearTimeout(searchTimer);
            }

            if (term.length < 2) {
                hideResults();
                return;
            }

            searchTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`<?= base_url('attendance/search-members') ?>?q=${encodeURIComponent(term)}`);
                    const payload = await response.json();
                    updateCsrf(payload);
                    if (!response.ok || !payload.success) {
                        hideResults();
                        return;
                    }
                    renderResults(payload.members || []);
                } catch (error) {
                    hideResults();
                }
            }, 200);
        });

        document.addEventListener('click', (event) => {
            if (!results.contains(event.target) && event.target !== memberSearch) {
                hideResults();
            }
        });

        attendedCheckbox.addEventListener('change', () => {
            if (!attendedCheckbox.checked) {
                communicantCheckbox.checked = false;
                communicantCheckbox.disabled = true;
            } else {
                communicantCheckbox.disabled = false;
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!userIdInput.value) {
                showToast('<?= esc(lang('Attendance.selectMemberFromResults'), 'js') ?>', false);
                return;
            }

            const formData = new FormData(form);
            formData.set(csrfName, csrfHash);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();
                updateCsrf(payload);

                if (!response.ok || !payload.success) {
                    showToast(payload.message || '<?= esc(lang('Attendance.checkInFailed'), 'js') ?>', false);
                    return;
                }

                showToast(payload.message || '<?= esc(lang('Attendance.checkInRecorded'), 'js') ?>');

                memberSearch.value = '';
                userIdInput.value = '';
                attendedCheckbox.checked = true;
                communicantCheckbox.checked = false;
                communicantCheckbox.disabled = false;
                memberSearch.focus();
            } catch (error) {
                showToast('<?= esc(lang('Attendance.networkErrorCheckin'), 'js') ?>', false);
            }
        });
    })();
</script>
<?= $this->endSection() ?>