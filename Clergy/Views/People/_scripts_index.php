<?php /* Partial: directory bulk-select, member-tag, delete, and portal-enable scripts. Required: none (CSRF via meta/form token) */ ?>
<script>
    // ── Bulk select ──────────────────────────────────────────────────────────
    const selectAllChk = document.getElementById('people-select-all');
    const exportBtn = document.getElementById('bulk-export-btn');
    const exportIdsDiv = document.getElementById('bulk-export-ids');
    const exportForm = document.getElementById('people-export-form');
    const bulkDeletePeopleBtn = document.getElementById('bulk-delete-people-btn');
    const bulkMarkMemberBtn = document.getElementById('bulk-mark-member-btn');
    const bulkRemoveMemberBtn = document.getElementById('bulk-remove-member-btn');
    const enablePersonPortalModalEl = document.getElementById('enablePersonPortalModal');
    const enablePersonPortalForm = document.getElementById('enablePersonPortalForm');
    const enablePersonPortalLabel = document.getElementById('enablePersonPortalLabel');
    const confirmEnablePersonPortalBtn = document.getElementById('confirmEnablePersonPortalBtn');

    function selectedPeopleCheckboxes() {
        return Array.from(document.querySelectorAll('.people-row-check:checked'));
    }

    function selectedPeopleProfileIds() {
        return selectedPeopleCheckboxes()
            .map(function(chk) {
                return parseInt(chk.getAttribute('data-profile-id') || '0', 10);
            })
            .filter(function(id) {
                return Number.isInteger(id) && id > 0;
            });
    }

    function syncExportButton() {
        const checked = selectedPeopleCheckboxes();
        exportBtn.classList.toggle('d-none', checked.length === 0);
        if (bulkDeletePeopleBtn) {
            bulkDeletePeopleBtn.classList.toggle('d-none', checked.length === 0);
        }
        if (bulkMarkMemberBtn) {
            bulkMarkMemberBtn.classList.toggle('d-none', checked.length === 0);
        }
        if (bulkRemoveMemberBtn) {
            bulkRemoveMemberBtn.classList.toggle('d-none', checked.length === 0);
        }

        // Rebuild hidden inputs for the export form
        exportIdsDiv.innerHTML = '';
        checked.forEach(function(chk) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = chk.value;
            exportIdsDiv.appendChild(input);
        });
    }

    if (selectAllChk) {
        selectAllChk.addEventListener('change', function() {
            document.querySelectorAll('.people-row-check').forEach(function(chk) {
                chk.checked = selectAllChk.checked;
            });
            syncExportButton();
        });
    }

    document.querySelectorAll('.people-row-check').forEach(function(chk) {
        chk.addEventListener('change', function() {
            const all = document.querySelectorAll('.people-row-check');
            const done = document.querySelectorAll('.people-row-check:checked');
            if (selectAllChk) {
                selectAllChk.indeterminate = done.length > 0 && done.length < all.length;
                selectAllChk.checked = done.length === all.length && all.length > 0;
            }
            syncExportButton();
        });
    });

    // ── Export form: loading state + auto-reset after download starts ─────────
    if (exportForm) {
        exportForm.addEventListener('submit', function() {
            const originalLabel = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <?= lang('App.loading') ?>';

            // File downloads don't navigate the page, so reset UI after the
            // browser has had time to start receiving the attachment (~2 s).
            setTimeout(function() {
                // Uncheck all rows
                document.querySelectorAll('.people-row-check').forEach(function(chk) {
                    chk.checked = false;
                });
                if (selectAllChk) {
                    selectAllChk.checked = false;
                    selectAllChk.indeterminate = false;
                }
                exportIdsDiv.innerHTML = '';
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalLabel;
                exportBtn.classList.add('d-none');
                if (bulkDeletePeopleBtn) {
                    bulkDeletePeopleBtn.classList.add('d-none');
                }
                if (bulkMarkMemberBtn) {
                    bulkMarkMemberBtn.classList.add('d-none');
                }
                if (bulkRemoveMemberBtn) {
                    bulkRemoveMemberBtn.classList.add('d-none');
                }
            }, 2000);
        });
    }

    // ── Split single/bulk delete on People screen ───────────────────────────
    function getModalInstance(modalEl) {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    function setReasonFieldError(input, feedbackId, message) {
        if (!input) return;
        input.classList.toggle('is-invalid', !!message);
        const feedback = document.getElementById(feedbackId);
        if (feedback) feedback.textContent = message || '';
    }

    function submitPeopleDelete(profileIds, reason, triggerBtn) {
        if (!Array.isArray(profileIds) || profileIds.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        let reasonInput = null,
            feedbackId = '';
        if (profileIds.length === 1) {
            reasonInput = document.getElementById('singleDeletePersonReasonInput');
            feedbackId = 'singleDeletePersonReasonFeedback';
        } else {
            reasonInput = document.getElementById('bulkDeletePeopleReasonInput');
            feedbackId = 'bulkDeletePeopleReasonFeedback';
        }
        setReasonFieldError(reasonInput, feedbackId, '');
        if (!reason || reason.trim().length < 8) {
            setReasonFieldError(reasonInput, feedbackId, '<?= esc(lang('App.bulkDeleteReasonRequired'), 'js') ?>');
            if (reasonInput) reasonInput.focus();
            return;
        }

        if (triggerBtn) {
            triggerBtn.disabled = true;
        }

        const body = new URLSearchParams();
        profileIds.forEach(function(id) {
            body.append('profile_ids[]', String(id));
        });
        body.append('delete_reason', reason.trim());
        body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        fetch('<?= base_url('people/bulk-delete') ?>', {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return {
                        ok: response.ok,
                        payload
                    };
                });
            })
            .then(function(result) {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
                if (result.payload && result.payload.errors && result.payload.errors.delete_reason) {
                    setReasonFieldError(reasonInput, feedbackId, result.payload.errors.delete_reason);
                    if (reasonInput) reasonInput.focus();
                    return;
                }
                setReasonFieldError(reasonInput, feedbackId, '');
                if (result.ok && (result.payload.success || Number(result.payload.deleted || 0) > 0)) {
                    window.location.reload();
                    return;
                }
                showAppToast(contractErrorMessage(result.payload, '<?= esc(lang('Members.deleteError'), 'js') ?>'), 'danger');
            })
            .catch(function() {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
                setReasonFieldError(reasonInput, feedbackId, '');
                showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
            });
    }

    function openSingleDeletePersonModal(checkboxEl) {
        const modalEl = document.getElementById('singleDeletePersonModal');
        const infoEl = document.getElementById('singleDeletePersonInfo');
        const reasonInput = document.getElementById('singleDeletePersonReasonInput');
        const modal = getModalInstance(modalEl);

        const profileId = parseInt(checkboxEl.getAttribute('data-profile-id') || '0', 10);
        const name = (checkboxEl.getAttribute('data-person-name') || '').trim();
        const email = (checkboxEl.getAttribute('data-person-email') || '').trim();
        window.__pendingPeopleSingleDelete = profileId > 0 ? [profileId] : [];

        if (!modal || !reasonInput) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        if (infoEl) {
            infoEl.textContent = email ? `${name} (${email})` : name;
        }
        reasonInput.value = '';
        modal.show();
    }

    if (bulkDeletePeopleBtn) {
        bulkDeletePeopleBtn.addEventListener('click', function() {
            const selected = selectedPeopleCheckboxes();
            if (selected.length === 0) {
                showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
                return;
            }

            if (selected.length === 1) {
                openSingleDeletePersonModal(selected[0]);
                return;
            }

            const profileIds = selectedPeopleProfileIds();
            if (profileIds.length === 0) {
                showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
                return;
            }

            window.__pendingPeopleBulkDelete = profileIds;

            const modalEl = document.getElementById('bulkDeletePeopleModal');
            const summaryEl = document.getElementById('bulkDeletePeopleSummary');
            const reasonInput = document.getElementById('bulkDeletePeopleReasonInput');
            const modal = getModalInstance(modalEl);

            if (!modal || !reasonInput) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            summaryEl.textContent = `Delete ${profileIds.length} selected users?`;
            reasonInput.value = '';
            modal.show();
        });
    }

    const confirmSingleDeletePersonBtn = document.getElementById('confirmSingleDeletePersonBtn');
    if (confirmSingleDeletePersonBtn) {
        confirmSingleDeletePersonBtn.addEventListener('click', function() {
            const reason = (document.getElementById('singleDeletePersonReasonInput') || {
                value: ''
            }).value;
            submitPeopleDelete(window.__pendingPeopleSingleDelete || [], reason, this);
        });
    }

    const confirmBulkDeletePeopleBtn = document.getElementById('confirmBulkDeletePeopleBtn');
    if (confirmBulkDeletePeopleBtn) {
        confirmBulkDeletePeopleBtn.addEventListener('click', function() {
            const reason = (document.getElementById('bulkDeletePeopleReasonInput') || {
                value: ''
            }).value;
            submitPeopleDelete(window.__pendingPeopleBulkDelete || [], reason, this);
        });
    }

    // ── Single-row delete from people list ────────────────────────────────────
    document.querySelectorAll('.row-delete-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const profileId = parseInt(this.getAttribute('data-profile-id') || '0', 10);
            const name = (this.getAttribute('data-person-name') || '').trim();
            const email = (this.getAttribute('data-person-email') || '').trim();
            if (!profileId) {
                return;
            }
            window.__pendingPeopleSingleDelete = [profileId];

            const modalEl = document.getElementById('singleDeletePersonModal');
            const infoEl = document.getElementById('singleDeletePersonInfo');
            const reasonInput = document.getElementById('singleDeletePersonReasonInput');
            const modal = getModalInstance(modalEl);

            if (!modal || !reasonInput) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            if (infoEl) {
                infoEl.textContent = email ? `${name} (${email})` : name;
            }
            reasonInput.value = '';
            modal.show();
        });
    });

    // ── Bulk toggle member tag ────────────────────────────────────────────────
    function bulkToggleMemberTag(isMember) {
        const selected = selectedPeopleCheckboxes();
        if (selected.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        const userIds = selected
            .map(function(chk) {
                return parseInt(chk.getAttribute('data-user-id') || '0', 10);
            })
            .filter(function(id) {
                return id > 0;
            });

        if (userIds.length === 0) {
            return;
        }

        const action = isMember === 1 ? '<?= esc(lang('People.markAsMember'), 'js') ?>' : '<?= esc(lang('People.removeMemberTag'), 'js') ?>';
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            action + ' (' + userIds.length + ' ' + (userIds.length === 1 ? 'person' : 'people') + ')?',
            function() {
                const body = new URLSearchParams();
                userIds.forEach(function(id) {
                    body.append('user_ids[]', String(id));
                });
                body.append('is_member', String(isMember));
                body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

                fetch('<?= base_url('people/bulk-toggle-member-tag') ?>', {
                        method: 'POST',
                        body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(payload) {
                        if (payload.success) {
                            window.location.reload();
                        } else {
                            showAppToast(payload.message || 'Failed to update member tag.', 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
                    });
            }
        );
    }

    if (bulkMarkMemberBtn) {
        bulkMarkMemberBtn.addEventListener('click', function() {
            bulkToggleMemberTag(1);
        });
    }
    if (bulkRemoveMemberBtn) {
        bulkRemoveMemberBtn.addEventListener('click', function() {
            bulkToggleMemberTag(0);
        });
    }

    // ── Single-row move to members ──────────────────────────────────────────
    function toggleSingleMemberTag(userId, isMember, personName) {
        if (!Number.isInteger(userId) || userId <= 0) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        const action = isMember === 1 ? '<?= esc(lang('People.markAsMember'), 'js') ?>' : '<?= esc(lang('People.removeMemberTag'), 'js') ?>';
        const label = (personName || '').trim() !== '' ? (personName + '?') : 'this person?';

        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            action + ' ' + label,
            function() {
                const body = new URLSearchParams();
                body.append('is_member', String(isMember));
                body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

                fetch('<?= base_url('people/toggle-member-tag/') ?>' + encodeURIComponent(String(userId)), {
                        method: 'POST',
                        body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(payload) {
                        if (payload.success) {
                            window.location.reload();
                        } else {
                            showAppToast(payload.message || 'Failed to update member tag.', 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
                    });
            }
        );
    }

    document.querySelectorAll('.mark-member-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const userId = parseInt(this.getAttribute('data-user-id') || '0', 10);
            const personName = this.getAttribute('data-person-name') || '';
            toggleSingleMemberTag(userId, 1, personName);
        });
    });

    document.querySelectorAll('.enable-portal-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const enablePersonPortalModal = getModalInstance(enablePersonPortalModalEl);
            if (!enablePersonPortalForm || !enablePersonPortalModal) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            const userId = this.getAttribute('data-user-id') || '';
            const personName = (this.getAttribute('data-person-name') || '').trim();
            const currentEmail = (this.getAttribute('data-person-email') || '').trim();

            enablePersonPortalForm.querySelector('input[name="user_id"]').value = userId;
            enablePersonPortalForm.querySelector('input[name="email"]').value = currentEmail;
            if (enablePersonPortalLabel) {
                enablePersonPortalLabel.textContent = personName;
            }

            enablePersonPortalModal.show();
        });
    });

    if (enablePersonPortalForm) {
        enablePersonPortalForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const userId = (enablePersonPortalForm.querySelector('input[name="user_id"]') || {
                value: ''
            }).value;
            const email = (enablePersonPortalForm.querySelector('input[name="email"]') || {
                value: ''
            }).value;
            if (!userId) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            if (confirmEnablePersonPortalBtn) {
                confirmEnablePersonPortalBtn.disabled = true;
            }

            fetch('<?= base_url('people/enable-portal/') ?>' + encodeURIComponent(userId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
                    },
                    body: JSON.stringify({
                        email: email
                    }),
                })
                .then(function(response) {
                    return response.json().then(function(payload) {
                        return {
                            ok: response.ok,
                            payload: payload,
                        };
                    });
                })
                .then(function(result) {
                    if (result.ok && result.payload && result.payload.success) {
                        showAppToast(result.payload.message || '<?= esc(lang('People.portalAccessEnabled'), 'js') ?>', 'primary');
                        window.location.reload();
                        return;
                    }

                    showAppToast(contractErrorMessage(result.payload, '<?= esc(lang('People.portalAccessEnableFailed'), 'js') ?>'), 'danger');
                })
                .catch(function() {
                    showAppToast('<?= esc(lang('People.portalAccessEnableFailed'), 'js') ?>', 'danger');
                })
                .finally(function() {
                    if (confirmEnablePersonPortalBtn) {
                        confirmEnablePersonPortalBtn.disabled = false;
                    }
                });
        });
    }
</script>
