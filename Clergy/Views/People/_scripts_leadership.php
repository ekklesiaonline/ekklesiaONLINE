<?php /* Partial: leadership position modal + CRUD script. Required: $profileId */ ?>
<script>
(function() {
    const modalEl = document.getElementById('leadershipPositionModal');
    const form = document.getElementById('leadershipPositionForm');
    const addBtn = document.getElementById('addLeadershipPositionBtn');
    const saveBtn = document.getElementById('saveLeadershipPositionBtn');
    const modalTitle = document.getElementById('leadershipPositionModalLabel');
    const eligAlert = document.getElementById('lp_eligibility_alert');
    const eligText = document.getElementById('lp_eligibility_text');
    const roleCategorySel = document.getElementById('lp_role_category');

    function refreshEligibilityHint() {
        if (!eligAlert || !eligText) return;
        const cat = roleCategorySel ? roleCategorySel.value : '';
        fetch('<?= base_url('people/leadership-eligibility/' . $profileId) ?>?role_category=' + encodeURIComponent(cat), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        .then(function(r) { return r.json(); })
        .then(function(payload) {
            if (payload && payload.success && payload.data && payload.data.message) {
                eligText.textContent = payload.data.message;
                eligAlert.classList.remove('d-none');
            } else {
                eligAlert.classList.add('d-none');
            }
        })
        .catch(function() {
            eligAlert.classList.add('d-none');
        });
    }

    function getLeadershipModal() {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function resetForm() {
        if (!form) return;
        form.reset();
        document.getElementById('lp_position_id').value = '';
        document.getElementById('lp_active').checked = true;
        form.action = '<?= base_url('people/leadership/' . $profileId) ?>';
        if (modalTitle) {
            modalTitle.textContent = '<?= esc(lang('Leadership.addPosition'), 'js') ?>';
        }
    }

    function setField(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            el.value = value || '';
        } else if (el.type === 'checkbox') {
            el.checked = Boolean(value);
        } else {
            el.value = value || '';
        }
    }

    if (roleCategorySel) {
        roleCategorySel.addEventListener('change', refreshEligibilityHint);
    }

    if (addBtn) {
        addBtn.addEventListener('click', function() {
            resetForm();
            refreshEligibilityHint();
            const modal = getLeadershipModal();
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-leadership-position-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const position = JSON.parse(this.getAttribute('data-position') || '{}');
            const positionId = parseInt(position.id || '0', 10);
            if (!positionId) return;

            resetForm();
            document.getElementById('lp_position_id').value = positionId;
            setField('lp_title', position.title);
            setField('lp_role_code', position.role_code);
            setField('lp_scope_type', position.scope_type);
            setField('lp_role_category', position.role_category);
            setField('lp_clergy_order', position.clergy_order);
            setField('lp_employment_type', position.employment_type);
            setField('lp_started_at', position.started_at);
            setField('lp_ended_at', position.ended_at);
            setField('lp_active', position.active);
            setField('lp_notes', position.notes);

            form.action = '<?= base_url('people/leadership/') ?>' + positionId + '/update';
            if (modalTitle) {
                modalTitle.textContent = '<?= esc(lang('Leadership.editPosition'), 'js') ?>';
            }

            refreshEligibilityHint();
            const modal = getLeadershipModal();
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-leadership-position-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const positionId = parseInt(this.getAttribute('data-position-id') || '0', 10);
            const title = (this.getAttribute('data-title') || '').trim();
            if (!positionId) return;

            const message = title
                ? '<?= esc(lang('Leadership.deleteConfirmNamed'), 'js') ?>'.replace('{0}', title)
                : '<?= esc(lang('Leadership.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/leadership/') ?>' + positionId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Leadership.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Leadership.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Leadership.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Leadership.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.dataset.originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const formData = new FormData(form);
            const body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Leadership.saveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Leadership.saveFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Leadership.saveFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Leadership.saveFailed'), 'js') ?>');
                }
            });
        });
    }
})();
</script>
