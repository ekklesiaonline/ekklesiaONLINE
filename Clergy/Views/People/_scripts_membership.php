<?php /* Partial: membership details inline-edit script. Required: none (CSRF via meta/form token) */ ?>
<script>
(function() {
    const toggleBtn = document.getElementById('toggleMembershipDetailsEdit');
    const displayDiv = document.getElementById('membershipDetailsDisplay');
    const form = document.getElementById('membershipDetailsForm');
    const cancelBtn = document.getElementById('cancelMembershipDetailsEdit');

    function setEditMode(showForm) {
        if (!displayDiv || !form) return;
        displayDiv.classList.toggle('d-none', showForm);
        form.classList.toggle('d-none', !showForm);
        if (toggleBtn) {
            toggleBtn.classList.toggle('d-none', showForm);
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            setEditMode(true);
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            setEditMode(false);
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const formData = new FormData(form);
            const body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (key === '<?= csrf_token() ?>') {
                    body.append(key, value);
                } else {
                    body.append(key, value);
                }
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
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('People.membershipDetailsSaved'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>');
                }
            });
        });
    }
})();
</script>
