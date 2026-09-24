<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (auth()->user()->can('family.manage')): ?>
        <a href="<?= base_url('families/edit/' . $family['id']) ?>" class="btn btn-outline-warning">
            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('Families.editFamily') ?>
        </a>
    <?php endif; ?>
    <a href="<?= base_url('families') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Families.backToList') ?>
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Families.familyInformation') ?></h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th scope="row" style="width: 200px;"><?= lang('Families.colFamilyName') ?>:</th>
                        <td><strong><?= esc($family['family_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= lang('Families.colHeadOfHousehold') ?>:</th>
                        <td>
                            <?php $headName = (string) ($family['head_name'] ?? ''); ?>
                            <?php if ($headName !== ''): ?>
                                <?= esc($headName) ?>
                            <?php else: ?>
                                <span class="text-muted"><?= lang('Families.notAssigned') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?= lang('Families.colAddress') ?>:</th>
                        <td><?= esc($family['address'] ?: lang('Families.notProvided')) ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= lang('Families.createdOn') ?>:</th>
                        <td><?= esc($formatLocalizedDate((string) ($family['created_at'] ?? ''), 'MMM d, y', $locale)) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= lang('Families.membersSection') ?></h5>
                <?php if (auth()->user()->can('family.manage')): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addNonPortalMemberModal">
                            <i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Families.addNonPortalMember') ?>
                        </button>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Families.addMember') ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($family['members'])): ?>
                    <p class="text-muted text-center py-3"><?= lang('Families.noMembersInFamily') ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col"><?= lang('App.colName') ?></th>
                                    <th scope="col"><?= lang('App.colEmail') ?></th>
                                    <th scope="col"><?= lang('Families.relationship') ?></th>
                                    <th scope="col"><?= lang('Families.portalAccess') ?></th>
                                    <th scope="col"><?= lang('App.colStatus') ?></th>
                                    <?php if (auth()->user()->can('family.manage')): ?>
                                        <th scope="col"><?= lang('App.colActions') ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($family['members'] as $member): ?>
                                    <tr>
                                        <td><?= esc($member['full_name']) ?></td>
                                        <td><?= esc($member['email'] ?: lang('App.notApplicable')) ?></td>
                                        <td>
                                            <?php if ($member['relationship']): ?>
                                                <?php
                                                $relationshipMap = [
                                                    'Head' => lang('Families.relationshipHead'),
                                                    'Spouse' => lang('Families.relationshipSpouse'),
                                                    'Child' => lang('Families.relationshipChild'),
                                                    'Parent' => lang('Families.relationshipParent'),
                                                    'Sibling' => lang('Families.relationshipSibling'),
                                                    'Other' => lang('Families.relationshipOther'),
                                                ];
                                                $relationshipLabel = $relationshipMap[$member['relationship']] ?? $member['relationship'];
                                                ?>
                                                <span class="badge border border-secondary text-secondary fw-normal"><?= esc($relationshipLabel) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?= lang('Families.notSet') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $portalAccess = strtolower((string) ($member['portal_access'] ?? 'none'));
                                            if (!in_array($portalAccess, ['none', 'invited', 'enabled'], true)) {
                                                $portalAccess = 'none';
                                            }
                                            $portalBadge = $portalAccess === 'enabled' ? 'success' : ($portalAccess === 'invited' ? 'warning' : 'secondary');
                                            ?>
                                            <span class="badge border border-<?= esc($portalBadge) ?> text-<?= esc($portalBadge) ?> fw-normal portal-status-badge" data-user-id="<?= (int) $member['user_id'] ?>" data-portal-access="<?= esc($portalAccess, 'attr') ?>">
                                                <?= esc(ucfirst($portalAccess)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $memberStatus = (string) ($member['membership_status'] ?? '');
                                            $statusLabelMap = [
                                                'member' => lang('App.statusActive'),
                                                'regular_attender' => lang('App.statusRegular_attender'),
                                                'inactive' => lang('App.statusInactive'),
                                                'pending' => lang('App.statusPending'),
                                                'visitor' => lang('App.statusVisitor'),
                                            ];
                                            $statusLabel = $statusLabelMap[$memberStatus] ?? \App\Services\MembershipStatusService::label($memberStatus);
                                            $statusBadge = $memberStatus === 'member' ? 'success' : 'secondary';
                                            ?>
                                            <span class="badge border border-<?= $statusBadge ?> text-<?= $statusBadge ?> fw-normal">
                                                <?= esc($statusLabel) ?>
                                            </span>
                                        </td>
                                        <?php if (auth()->user()->can('family.manage')): ?>
                                            <td>
                                                <div class="table-actions d-inline-flex flex-nowrap gap-1 align-items-center">
                                                    <?php if ($portalAccess === 'none'): ?>
                                                        <button
                                                            class="btn btn-sm btn-outline-success enable-portal-btn"
                                                            data-user-id="<?= (int) $member['user_id'] ?>"
                                                            data-member-name="<?= esc($member['full_name'], 'attr') ?>"
                                                            title="<?= lang('Families.enablePortalAccess') ?>">
                                                            <i class="bi bi-person-check" aria-hidden="true"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button onclick="removeMember(<?= $member['family_member_id'] ?>)"
                                                        class="btn btn-sm btn-danger" title="<?= lang('Families.removeFromFamily') ?>">
                                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= lang('Families.quickStats') ?></h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span><?= lang('Families.totalMembers') ?>:</span>
                    <strong class="h4 mb-0"><?= count($family['members']) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span><?= lang('Families.activeMembers') ?>:</span>
                    <strong class="h4 mb-0">
                        <?= count(array_filter($family['members'], fn($m) => ($m['membership_status'] ?? '') === 'member')) ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<?php if (auth()->user()->can('family.manage')): ?>
    <div class="modal fade" id="addNonPortalMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('Families.addNonPortalMember') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addNonPortalMemberForm">
                    <div class="modal-body">
                        <input type="hidden" name="family_id" value="<?= (int) $family['id'] ?>">
                        <input type="hidden" name="portal_access" value="none">
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.firstName') ?></label>
                            <input type="text" class="form-control" name="first_name" required minlength="2" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.lastName') ?></label>
                            <input type="text" class="form-control" name="last_name" required minlength="2" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.relationship') ?></label>
                            <select name="relationship" class="form-select">
                                <option value=""><?= lang('Families.selectRelationship') ?></option>
                                <option value="Head"><?= lang('Families.relationshipHead') ?></option>
                                <option value="Spouse"><?= lang('Families.relationshipSpouse') ?></option>
                                <option value="Child"><?= lang('Families.relationshipChild') ?></option>
                                <option value="Parent"><?= lang('Families.relationshipParent') ?></option>
                                <option value="Sibling"><?= lang('Families.relationshipSibling') ?></option>
                                <option value="Other"><?= lang('Families.relationshipOther') ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.birthDate') ?></label>
                            <input type="date" class="form-control" name="birth_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.usernameOptional') ?></label>
                            <input type="text" class="form-control" name="username" maxlength="255">
                        </div>
                        <div id="addNonPortalErrors" class="alert alert-danger d-none mb-0"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= lang('Families.createNonPortalMember') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('Families.addFamilyMember') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addMemberForm">
                    <div class="modal-body">
                        <input type="hidden" name="family_id" value="<?= $family['id'] ?>">
                        <div class="mb-3">
                            <label for="member_search" class="form-label"><?= lang('App.search') ?></label>
                            <input type="text" id="member_search" class="form-control" placeholder="<?= esc(lang('Families.selectMember'), 'attr') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.memberRequired') ?></label>
                            <select id="available_member_select" name="user_id" class="form-select" required>
                                <option value=""><?= lang('Families.selectMember') ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('Families.relationship') ?></label>
                            <select name="relationship" class="form-select">
                                <option value=""><?= lang('Families.selectRelationship') ?></option>
                                <option value="Head"><?= lang('Families.relationshipHead') ?></option>
                                <option value="Spouse"><?= lang('Families.relationshipSpouse') ?></option>
                                <option value="Child"><?= lang('Families.relationshipChild') ?></option>
                                <option value="Parent"><?= lang('Families.relationshipParent') ?></option>
                                <option value="Sibling"><?= lang('Families.relationshipSibling') ?></option>
                                <option value="Other"><?= lang('Families.relationshipOther') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= lang('Families.addMember') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="enablePortalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('Families.enablePortalAccess') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="enablePortalForm">
                    <div class="modal-body">
                        <input type="hidden" name="family_id" value="<?= (int) $family['id'] ?>">
                        <input type="hidden" name="user_id" value="">
                        <p id="enablePortalMemberLabel" class="small text-muted"></p>
                        <div class="mb-3">
                            <label class="form-label"><?= lang('App.colEmail') ?></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="alert alert-info py-2 mb-3">
                            <?= esc(lang('Families.portalEnableResetNotice')) ?>
                        </div>
                        <div id="enablePortalErrors" class="alert alert-danger d-none mb-0"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-success"><?= lang('Families.enablePortalAccess') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    <?php if (auth()->user()->can('family.manage')): ?>

        function contractErrorMessage(data, fallback) {
            if (!data || typeof data !== 'object') {
                return fallback;
            }

            if (typeof data.code === 'string' && data.message) {
                return `${data.message} (${data.code})`;
            }

            const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
            const message =
                (data.error && typeof data.error.message === 'string' && data.error.message) ||
                (typeof data.message === 'string' && data.message) ||
                fallback;

            return code ? `${message} (${code})` : message;
        }

        function renderContractErrors(container, data) {
            if (!container) {
                return;
            }

            const errors = Array.isArray(data?.errors) ? data.errors : [];
            if (!errors.length) {
                container.classList.add('d-none');
                container.innerHTML = '';
                return;
            }

            const list = errors.map((item) => {
                const field = item?.field ? `${item.field}: ` : '';
                const message = item?.message || 'Request failed.';
                return `<li>${field}${message}</li>`;
            }).join('');

            container.innerHTML = `<ul class="mb-0">${list}</ul>`;
            container.classList.remove('d-none');
        }

        document.getElementById('addMemberForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('<?= base_url('families/addMember') ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showAppToast(contractErrorMessage(data, '<?= esc(lang('Families.failedAddMember'), 'js') ?>'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                });
        });

        const memberSearchInput = document.getElementById('member_search');
        const memberSelect = document.getElementById('available_member_select');
        const modalEl = document.getElementById('addMemberModal');
        let memberSearchTimer = null;

        function populateAvailableMembers(items) {
            if (!memberSelect) {
                return;
            }

            memberSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '<?= esc(lang('Families.selectMember'), 'js') ?>';
            memberSelect.appendChild(placeholder);

            items.forEach(function(item) {
                const option = document.createElement('option');
                option.value = item.user_id;
                option.textContent = `${item.first_name || ''} ${item.last_name || ''}`.trim();
                memberSelect.appendChild(option);
            });
        }

        function loadAvailableMembers(query = '') {
            fetch(`<?= base_url('families/available-members/' . (int) $family['id']) ?>?q=${encodeURIComponent(query)}&limit=50`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        populateAvailableMembers(data.data);
                        return;
                    }

                    showAppToast(contractErrorMessage(data, '<?= esc(lang('Families.failedLoadMembers'), 'js') ?>'), 'warning');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAppToast('<?= esc(lang('Families.failedLoadMembers'), 'js') ?>', 'warning');
                });
        }

        modalEl?.addEventListener('shown.bs.modal', function() {
            if (memberSearchInput) {
                memberSearchInput.value = '';
            }
            loadAvailableMembers('');
        });

        memberSearchInput?.addEventListener('input', function() {
            const query = this.value || '';
            if (memberSearchTimer !== null) {
                clearTimeout(memberSearchTimer);
            }

            memberSearchTimer = setTimeout(function() {
                loadAvailableMembers(query);
            }, 250);
        });

        function removeMember(id) {
            showAppConfirm(
                '<?= esc(lang('App.confirm'), 'js') ?>',
                '<?= esc(lang('Families.confirmRemoveMember'), 'js') ?>',
                function() {
                    fetch(`<?= base_url('families/removeMember/') ?>${id}`, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                showAppToast(contractErrorMessage(data, '<?= esc(lang('Families.failedRemoveMember'), 'js') ?>'), 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                        });
                }
            );
        }

        const addNonPortalForm = document.getElementById('addNonPortalMemberForm');
        const addNonPortalErrors = document.getElementById('addNonPortalErrors');
        const enablePortalForm = document.getElementById('enablePortalForm');
        const enablePortalErrors = document.getElementById('enablePortalErrors');
        const enablePortalModalEl = document.getElementById('enablePortalModal');
        const enablePortalMemberLabel = document.getElementById('enablePortalMemberLabel');

        function getModalInstance(modalEl) {
            if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
                return null;
            }

            return window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        addNonPortalForm?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(addNonPortalForm);
            const payload = Object.fromEntries(formData.entries());
            payload.family_id = parseInt(payload.family_id || '0', 10);
            payload.is_child = true;

            renderContractErrors(addNonPortalErrors, null);

            fetch('<?= base_url('families/addMemberNonPortal') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
                    },
                    body: JSON.stringify(payload),
                })
                .then(response => response.json())
                .then((data) => {
                    if (data.code === 'NON_PORTAL_MEMBER_CREATED' && data.success) {
                        location.reload();
                        return;
                    }

                    renderContractErrors(addNonPortalErrors, data);
                    if (!Array.isArray(data?.errors) || !data.errors.length) {
                        showAppToast(contractErrorMessage(data, '<?= esc(lang('Families.failedAddMember'), 'js') ?>'), 'danger');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                });
        });

        document.querySelectorAll('.enable-portal-btn').forEach((button) => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const memberName = this.getAttribute('data-member-name') || '';
                const enablePortalModal = getModalInstance(enablePortalModalEl);
                if (!enablePortalForm || !enablePortalModal) {
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                    return;
                }

                enablePortalForm.querySelector('input[name="user_id"]').value = userId;
                if (enablePortalMemberLabel) {
                    enablePortalMemberLabel.textContent = memberName;
                }

                renderContractErrors(enablePortalErrors, null);
                enablePortalForm.querySelector('input[name="email"]').value = '';
                enablePortalModal.show();
            });
        });

        enablePortalForm?.addEventListener('submit', function(e) {
            e.preventDefault();

            const userId = enablePortalForm.querySelector('input[name="user_id"]').value;
            const familyId = enablePortalForm.querySelector('input[name="family_id"]').value;
            const email = enablePortalForm.querySelector('input[name="email"]').value;

            renderContractErrors(enablePortalErrors, null);

            fetch(`<?= base_url('families/enablePortal') ?>/${encodeURIComponent(userId)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
                    },
                    body: JSON.stringify({
                        family_id: parseInt(familyId, 10),
                        email,
                    }),
                })
                .then(response => response.json())
                .then((data) => {
                    if (data.code === 'PORTAL_ACCESS_ENABLED' && data.success) {
                        location.reload();
                        return;
                    }

                    renderContractErrors(enablePortalErrors, data);
                    if (!Array.isArray(data?.errors) || !data.errors.length) {
                        showAppToast(contractErrorMessage(data, '<?= esc(lang('App.errorGeneric'), 'js') ?>'), 'danger');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                });
        });
    <?php endif; ?>
</script>

<?= $this->endSection() ?>