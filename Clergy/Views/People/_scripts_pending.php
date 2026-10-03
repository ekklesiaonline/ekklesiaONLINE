<?php /* Partial: pending-registrations bulk action modal script. Required: none */ ?>
<script>
    (function() {
        const modalEl = document.getElementById('pendingActionModal');
        const modalTitle = document.getElementById('pendingActionModalLabel');
        const modalText = document.getElementById('pendingActionText');
        const modalConfirm = document.getElementById('pendingActionConfirm');
        const bulkActionInput = document.getElementById('pendingBulkAction');
        if (!modalEl || !modalTitle || !modalText || !modalConfirm || !bulkActionInput) {
            return;
        }

        const modal = (typeof getModalInstance === 'function')
            ? getModalInstance(modalEl)
            : (typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal === 'function')
                ? new window.bootstrap.Modal(modalEl)
                : null;

        document.querySelectorAll('.pending-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const pane = btn.closest('.tab-pane') || document.getElementById('pendingRegistrationsCard');
                document.querySelectorAll('#pendingRegistrationsCard .tab-pane').forEach(function(otherPane) {
                    if (otherPane !== pane) {
                        otherPane.querySelectorAll('.pending-reg-check').forEach(function(c) { c.checked = false; });
                    }
                });

                const count = pane ? pane.querySelectorAll('.pending-reg-check:checked').length : document.querySelectorAll('.pending-reg-check:checked').length;
                if (count === 0) {
                    alert('<?= esc(lang('Dashboard.pleaseSelectAtLeastOne'), 'js') ?>');
                    return;
                }

                const action = btn.dataset.action || '';
                const title = btn.dataset.title || '';
                const template = btn.dataset.template || '';
                const colorMap = { approve: 'success', dismiss: 'secondary', ban: 'warning', delete: 'danger', escalate: 'info' };
                const color = colorMap[action] || 'secondary';

                if (modalTitle) {
                    modalTitle.textContent = title;
                }
                if (modalText) {
                    modalText.textContent = template.replace('{0}', String(count));
                }
                if (bulkActionInput) {
                    bulkActionInput.value = action;
                }

                // Match the confirm button color to the action
                modalConfirm.className = 'btn btn-' + color;
                if (modal) {
                    modal.show();
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: template.replace('{0}', String(count)),
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: title,
                        cancelButtonText: '<?= esc(lang('App.cancel'), 'js') ?>',
                        confirmButtonColor: getSwalColor(color)
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            document.getElementById('bulkActionForm').submit();
                        }
                    });
                } else if (confirm(template.replace('{0}', String(count)))) {
                    document.getElementById('bulkActionForm').submit();
                }
            });
        });

        function getSwalColor(color) {
            const colors = {
                success: '#198754',
                warning: '#ffc107',
                danger: '#dc3545',
                info: '#0dcaf0',
                secondary: '#6c757d'
            };
            return colors[color] || '#6c757d';
        }
    })();
</script>
