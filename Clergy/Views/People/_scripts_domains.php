<?php /* Partial: shared modal/AJAX script for donations, pledges, sacraments, sacrament participants, and family actions. Required: $profileId */ ?>
<script>
(function() {
    function getModalInstance(modalEl) {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function setFieldValue(id, value) {
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

    function ajaxFormSubmit(form, saveBtn, successMessageKey, errorMessageKey) {
        return new Promise(function(resolve) {
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.dataset.originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const body = new URLSearchParams();
            const formData = new FormData(form);
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
                        showAppToast(result.payload.message || successMessageKey, 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : errorMessageKey;
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
                resolve();
            })
            .catch(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast(errorMessageKey, 'danger');
                } else {
                    alert(errorMessageKey);
                }
                resolve();
            });
        });
    }

    // ── Donations ─────────────────────────────────────────────────────────────
    const donationModalEl = document.getElementById('donationModal');
    const donationForm = document.getElementById('donationForm');
    const addDonationBtn = document.getElementById('addDonationBtn');
    const donationModalTitle = document.getElementById('donationModalLabel');
    const donationSaveBtn = document.getElementById('saveDonationBtn');
    const donationFundSelect = document.getElementById('d_fund_id');
    const fundRestrictionText = document.getElementById('d_fund_restriction_text');

    function syncFundRestrictionSummary() {
        if (!fundRestrictionText || !donationFundSelect) return;
        const option = donationFundSelect.selectedOptions[0];
        if (!option || !option.value) {
            fundRestrictionText.textContent = '<?= esc(lang('Finance.selectFundRestrictionHelp'), 'js') ?>';
            return;
        }

        const type = option.dataset.fundType || 'unrestricted';
        const purpose = option.dataset.restrictionPurpose || '';
        const labels = {
            unrestricted: '<?= esc(lang('Treasurer.fundTypeUnrestricted'), 'js') ?>',
            restricted: '<?= esc(lang('Treasurer.fundTypeRestricted'), 'js') ?>',
            permanent: '<?= esc(lang('Treasurer.fundTypePermanent'), 'js') ?>',
        };
        fundRestrictionText.textContent = purpose ? `${labels[type] || type}: ${purpose}` : (labels[type] || type);
    }

    function applyDonationFundDefault() {
        const accountSelect = document.getElementById('d_chart_account_id');
        const option = donationFundSelect?.selectedOptions[0];
        if (!accountSelect || !option) return;
        const defaultAccountId = option.dataset.defaultChartAccount || '';
        accountSelect.value = defaultAccountId && accountSelect.querySelector(`option[value="${defaultAccountId}"]`) ? defaultAccountId : '';
        const selectedAccount = accountSelect.selectedOptions[0];
        if (selectedAccount?.dataset.category) {
            setFieldValue('d_category', selectedAccount.dataset.category);
        }
    }

    donationFundSelect?.addEventListener('change', function() {
        syncFundRestrictionSummary();
        applyDonationFundDefault();
    });

    function syncDonationChartAccount() {
        const category = document.getElementById('d_category')?.value || '';
        const select = document.getElementById('d_chart_account_id');
        if (!select) return;
        if (select.value) return;
        const match = select.querySelector('option[data-category="' + category + '"]');
        if (match) {
            select.value = match.value;
        }
    }
    document.getElementById('d_category')?.addEventListener('change', function() {
        const select = document.getElementById('d_chart_account_id');
        if (select) select.value = '';
        syncDonationChartAccount();
    });

    function resetDonationForm() {
        if (!donationForm) return;
        donationForm.reset();
        document.getElementById('donation_id').value = '';
        setFieldValue('d_date', '<?= date('Y-m-d') ?>');
        if (donationModalTitle) {
            donationModalTitle.textContent = '<?= esc(lang('Finance.recordDonation'), 'js') ?>';
        }
        donationForm.action = '<?= base_url('people/donation/' . $profileId) ?>';
        syncDonationChartAccount();
        syncFundRestrictionSummary();
    }

    if (addDonationBtn) {
        addDonationBtn.addEventListener('click', function() {
            resetDonationForm();
            const modal = getModalInstance(donationModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-donation-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const donation = JSON.parse(this.getAttribute('data-donation') || '{}');
            const donationId = parseInt(donation.id || '0', 10);
            if (!donationId) return;

            resetDonationForm();
            document.getElementById('donation_id').value = donationId;
            setFieldValue('d_date', donation.date);
            setFieldValue('d_amount', donation.amount);
            setFieldValue('d_fund_id', donation.fund_id);
            setFieldValue('d_category', donation.category);
            setFieldValue('d_chart_account_id', donation.chart_account_id);
            setFieldValue('d_payment_method', donation.payment_method);
            setFieldValue('d_notes', donation.notes);
            syncFundRestrictionSummary();

            donationForm.action = '<?= base_url('people/donation/') ?>' + donationId + '/update';
            if (donationModalTitle) {
                donationModalTitle.textContent = '<?= esc(lang('App.edit'), 'js') ?> <?= esc(lang('Finance.donationsTitle'), 'js') ?>';
            }
            syncFundRestrictionSummary();

            const modal = getModalInstance(donationModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-donation-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const donationId = parseInt(this.getAttribute('data-donation-id') || '0', 10);
            const amount = (this.getAttribute('data-amount') || '').trim();
            if (!donationId) return;

            const message = amount
                ? '<?= esc(lang('Finance.deleteDonationConfirm'), 'js') ?>'.replace('{0}', amount)
                : '<?= esc(lang('Finance.deleteDonationConfirmNoAmount'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/donation/') ?>' + donationId + '/delete', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Treasurer.donationDeleted'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (donationForm) {
        donationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(donationForm, donationSaveBtn, '<?= esc(lang('Treasurer.donationRecorded'), 'js') ?>', '<?= esc(lang('Treasurer.donationRecordFailed'), 'js') ?>');
        });
    }

    // ── Pledges ───────────────────────────────────────────────────────────────
    const pledgeModalEl = document.getElementById('pledgeModal');
    const pledgeForm = document.getElementById('pledgeForm');
    const addPledgeBtn = document.getElementById('addPledgeBtn');
    const pledgeModalTitle = document.getElementById('pledgeModalLabel');
    const pledgeSaveBtn = document.getElementById('savePledgeBtn');

    function resetPledgeForm() {
        if (!pledgeForm) return;
        pledgeForm.reset();
        document.getElementById('p_pledge_id').value = '';
        setFieldValue('p_pledge_year', '<?= (int) date('Y') ?>');
        if (pledgeModalTitle) {
            pledgeModalTitle.textContent = '<?= esc(lang('Pledges.addAnnualPledge'), 'js') ?>';
        }
        pledgeForm.action = '<?= base_url('people/pledge/' . $profileId) ?>';
    }

    if (addPledgeBtn) {
        addPledgeBtn.addEventListener('click', function() {
            resetPledgeForm();
            const modal = getModalInstance(pledgeModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-pledge-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pledge = JSON.parse(this.getAttribute('data-pledge') || '{}');
            const pledgeId = parseInt(pledge.id || '0', 10);
            if (!pledgeId) return;

            resetPledgeForm();
            document.getElementById('p_pledge_id').value = pledgeId;
            setFieldValue('p_pledge_year', pledge.pledge_year);
            setFieldValue('p_pledged_amount', pledge.pledged_amount);
            setFieldValue('p_fulfilled_amount', pledge.fulfilled_amount);
            setFieldValue('p_fulfillment_status', pledge.fulfillment_status);
            setFieldValue('p_pledged_at', pledge.pledged_at);
            setFieldValue('p_notes', pledge.notes);

            pledgeForm.action = '<?= base_url('people/pledge/') ?>' + pledgeId + '/update';
            if (pledgeModalTitle) {
                pledgeModalTitle.textContent = '<?= esc(lang('Pledges.edit'), 'js') ?> <?= esc(lang('Pledges.pageTitle'), 'js') ?>';
            }

            const modal = getModalInstance(pledgeModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-pledge-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pledgeId = parseInt(this.getAttribute('data-pledge-id') || '0', 10);
            const year = this.getAttribute('data-year') || '';
            if (!pledgeId) return;

            const message = year
                ? '<?= esc(lang('Pledges.deleteConfirmNamed'), 'js') ?>'.replace('{0}', year)
                : '<?= esc(lang('Pledges.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/pledge/') ?>' + pledgeId + '/delete', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Pledges.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Pledges.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Pledges.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Pledges.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (pledgeForm) {
        pledgeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(pledgeForm, pledgeSaveBtn, '<?= esc(lang('App.pledgeSavedSuccess'), 'js') ?>', '<?= esc(lang('Pledges.saveFailed'), 'js') ?>');
        });
    }

    // ── Sacraments ────────────────────────────────────────────────────────────
    const sacramentModalEl = document.getElementById('sacramentModal');
    const sacramentForm = document.getElementById('sacramentForm');
    const addSacramentBtn = document.getElementById('addSacramentBtn');
    const sacramentModalTitle = document.getElementById('sacramentModalLabel');
    const sacramentSaveBtn = document.getElementById('saveSacramentBtn');

    function resetSacramentForm() {
        if (!sacramentForm) return;
        sacramentForm.reset();
        document.getElementById('s_sacrament_id').value = '';
        if (sacramentModalTitle) {
            sacramentModalTitle.textContent = '<?= esc(lang('Sacraments.addSacrament'), 'js') ?>';
        }
        sacramentForm.action = '<?= base_url('people/sacrament/' . $profileId) ?>';
    }

    if (addSacramentBtn) {
        addSacramentBtn.addEventListener('click', function() {
            resetSacramentForm();
            const modal = getModalInstance(sacramentModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-sacrament-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const sacrament = JSON.parse(this.getAttribute('data-sacrament') || '{}');
            const sacramentId = parseInt(sacrament.id || '0', 10);
            if (!sacramentId) return;

            resetSacramentForm();
            document.getElementById('s_sacrament_id').value = sacramentId;
            setFieldValue('s_sacrament_type', sacrament.sacrament_type);
            setFieldValue('s_sacrament_date', sacrament.sacrament_date);
            setFieldValue('s_register_ref', sacrament.register_ref);
            setFieldValue('s_certificate_number', sacrament.certificate_number);
            setFieldValue('s_officiant_person_id', sacrament.officiant_person_id);
            setFieldValue('s_location_notes', sacrament.location_notes);
            setFieldValue('s_sacrament_notes', sacrament.notes);

            sacramentForm.action = '<?= base_url('people/sacrament/') ?>' + sacramentId + '/update';
            if (sacramentModalTitle) {
                sacramentModalTitle.textContent = '<?= esc(lang('Sacraments.editSacrament'), 'js') ?>';
            }

            const modal = getModalInstance(sacramentModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-sacrament-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const sacramentId = parseInt(this.getAttribute('data-sacrament-id') || '0', 10);
            const sacramentType = (this.getAttribute('data-sacrament-type') || '').trim();
            if (!sacramentId) return;

            const message = sacramentType
                ? '<?= esc(lang('Sacraments.deleteConfirmNamed'), 'js') ?>'.replace('{0}', sacramentType)
                : '<?= esc(lang('Sacraments.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/sacrament/') ?>' + sacramentId + '/delete', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (sacramentForm) {
        sacramentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(sacramentForm, sacramentSaveBtn, '<?= esc(lang('Sacraments.saveSuccess'), 'js') ?>', '<?= esc(lang('Sacraments.saveFailed'), 'js') ?>');
        });
    }

    // ── Sacrament participants ──────────────────────────────────────────────────
    const sacramentParticipantModalEl = document.getElementById('sacramentParticipantModal');
    const sacramentParticipantForm = document.getElementById('sacramentParticipantForm');
    const saveSacramentParticipantBtn = document.getElementById('saveSacramentParticipantBtn');
    const sacramentParticipantRecordIdField = document.getElementById('sp_sacrament_id');

    document.querySelectorAll('.add-sacrament-participant-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const recordId = this.getAttribute('data-sacrament-id');
            if (!recordId || !sacramentParticipantForm || !sacramentParticipantRecordIdField) return;
            sacramentParticipantForm.reset();
            sacramentParticipantRecordIdField.value = recordId;
            sacramentParticipantForm.action = '<?= base_url('people/sacrament/') ?>' + recordId + '/participant';
            const modal = getModalInstance(sacramentParticipantModalEl);
            if (modal) modal.show();
        });
    });

    if (sacramentParticipantForm) {
        sacramentParticipantForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(sacramentParticipantForm, saveSacramentParticipantBtn, '<?= esc(lang('Sacraments.participantAddSuccess'), 'js') ?>', '<?= esc(lang('Sacraments.participantAddFailed'), 'js') ?>');
        });
    }

    document.querySelectorAll('.participant-role-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const participantId = this.getAttribute('data-participant-id');
            const newRole = this.value;
            const originalValue = this.dataset.originalValue || '';
            if (!participantId || !newRole) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            body.append('participant_role', newRole);

            fetch('<?= base_url('people/sacrament/participant/') ?>' + participantId + '/update', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.participantUpdateSuccess'), 'js') ?>', 'success');
                    }
                    select.dataset.originalValue = newRole;
                    return;
                }
                if (typeof showAppToast === 'function') {
                    showAppToast((result.payload && result.payload.message) || '<?= esc(lang('Sacraments.participantUpdateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.participantUpdateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            });
        });
        select.dataset.originalValue = select.value;
    });

    document.querySelectorAll('.delete-sacrament-participant-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const participantId = parseInt(this.getAttribute('data-participant-id') || '0', 10);
            const name = (this.getAttribute('data-name') || '').trim();
            if (!participantId) return;

            const message = name
                ? '<?= esc(lang('Sacraments.participantDeleteConfirmNamed'), 'js') ?>'.replace('{0}', name)
                : '<?= esc(lang('Sacraments.participantDeleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/sacrament/participant/') ?>' + participantId + '/delete', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.participantDeleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>');
                }
            });
        });
    });

    // ── Family management ─────────────────────────────────────────────────────
    const createFamilyBtn = document.getElementById('createFamilyBtn');
    const joinFamilyBtn = document.getElementById('joinFamilyBtn');
    const addFamilyMemberBtn = document.getElementById('addFamilyMemberBtn');
    const leaveFamilyBtn = document.getElementById('leaveFamilyBtn');
    const createFamilyModalEl = document.getElementById('createFamilyModal');
    const joinFamilyModalEl = document.getElementById('joinFamilyModal');
    const addFamilyMemberModalEl = document.getElementById('addFamilyMemberModal');
    const createFamilyForm = document.getElementById('createFamilyForm');
    const joinFamilyForm = document.getElementById('joinFamilyForm');
    const addFamilyMemberForm = document.getElementById('addFamilyMemberForm');
    const saveCreateFamilyBtn = document.getElementById('saveCreateFamilyBtn');
    const saveJoinFamilyBtn = document.getElementById('saveJoinFamilyBtn');
    const saveAddFamilyMemberBtn = document.getElementById('saveAddFamilyMemberBtn');

    if (createFamilyBtn) {
        createFamilyBtn.addEventListener('click', function() {
            if (createFamilyForm) createFamilyForm.reset();
            const modal = getModalInstance(createFamilyModalEl);
            if (modal) modal.show();
        });
    }

    if (joinFamilyBtn) {
        joinFamilyBtn.addEventListener('click', function() {
            if (joinFamilyForm) joinFamilyForm.reset();
            const modal = getModalInstance(joinFamilyModalEl);
            if (modal) modal.show();
        });
    }

    if (addFamilyMemberBtn) {
        addFamilyMemberBtn.addEventListener('click', function() {
            if (addFamilyMemberForm) addFamilyMemberForm.reset();
            const modal = getModalInstance(addFamilyMemberModalEl);
            if (modal) modal.show();
        });
    }

    if (createFamilyForm) {
        createFamilyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(createFamilyForm, saveCreateFamilyBtn, '<?= esc(lang('Families.createSuccess'), 'js') ?>', '<?= esc(lang('Families.createFailed'), 'js') ?>');
        });
    }

    if (joinFamilyForm) {
        joinFamilyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(joinFamilyForm, saveJoinFamilyBtn, '<?= esc(lang('Families.memberAddSuccess'), 'js') ?>', '<?= esc(lang('Families.failedAddMember'), 'js') ?>');
        });
    }

    if (addFamilyMemberForm) {
        addFamilyMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(addFamilyMemberForm, saveAddFamilyMemberBtn, '<?= esc(lang('Families.memberAddSuccess'), 'js') ?>', '<?= esc(lang('Families.failedAddMember'), 'js') ?>');
        });
    }

    if (leaveFamilyBtn) {
        leaveFamilyBtn.addEventListener('click', function() {
            if (!confirm('<?= esc(lang('Families.leaveConfirm'), 'js') ?>')) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/family/' . $profileId . '/leave') ?>', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Families.memberRemoveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Families.failedRemoveMember'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>');
                }
            });
        });
    }

    document.querySelectorAll('.relationship-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const familyMemberId = this.getAttribute('data-family-member-id');
            const newRelationship = this.value;
            const originalValue = this.dataset.originalValue || '';
            if (!familyMemberId || !newRelationship) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            body.append('relationship', newRelationship);
            body.append('family_member_id', familyMemberId);

            fetch('<?= base_url('people/family/' . $profileId . '/relationship') ?>', {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Families.updateSuccess'), 'js') ?>', 'success');
                    }
                    return;
                }
                if (typeof showAppToast === 'function') {
                    showAppToast((result.payload && result.payload.message) || '<?= esc(lang('Families.updateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.updateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            });
        });
        select.dataset.originalValue = select.value;
    });

    document.querySelectorAll('.remove-family-member-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const familyMemberId = parseInt(this.getAttribute('data-family-member-id') || '0', 10);
            const name = (this.getAttribute('data-name') || '').trim();
            if (!familyMemberId) return;

            const message = name
                ? '<?= esc(lang('Families.removeMemberConfirmNamed'), 'js') ?>'.replace('{0}', name)
                : '<?= esc(lang('Families.removeMemberConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('families/removeMember/') ?>' + familyMemberId, {
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
                        showAppToast(result.payload.message || '<?= esc(lang('Families.memberRemoveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Families.failedRemoveMember'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>');
                }
            });
        });
    });
})();
</script>
