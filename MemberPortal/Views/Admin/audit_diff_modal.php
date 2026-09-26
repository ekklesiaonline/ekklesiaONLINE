<!-- Modal -->
<div class="modal fade" id="auditDiffModal" tabindex="-1" aria-labelledby="auditDiffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditDiffModalLabel"><?= lang('App.auditDiffModalTitle') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div id="auditDiffContent">
                    <!-- Diff content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function showAuditDiff(oldValues, newValues) {
    const diffContent = document.getElementById('auditDiffContent');
    
    if (!oldValues && !newValues) {
        diffContent.innerHTML = '<p class="text-muted">No changes to display.</p>';
        return;
    }
    
    let html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-bordered" style="color: var(--bs-body-color);">';
    html += '<thead><tr><th scope="col">Field</th><th scope="col">Old Value</th><th scope="col">New Value</th><th scope="col">Change</th></tr></thead>';
    html += '<tbody>';
    
    const allKeys = new Set([
        ...Object.keys(oldValues || {}),
        ...Object.keys(newValues || {})
    ]);
    
    allKeys.forEach(key => {
        const oldValue = oldValues ? oldValues[key] : null;
        const newValue = newValues ? newValues[key] : null;
        
        let changeType = 'unchanged';
        let changeClass = '';
        let changeLabel = '';
        
        if (oldValue === undefined || oldValue === null) {
            changeType = 'added';
            changeClass = 'table-success';
            changeLabel = '<span class="badge bg-success">Added</span>';
        } else if (newValue === undefined || newValue === null) {
            changeType = 'removed';
            changeClass = 'table-danger';
            changeLabel = '<span class="badge bg-danger">Removed</span>';
        } else if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            changeType = 'changed';
            changeClass = 'table-warning';
            changeLabel = '<span class="badge bg-warning">Changed</span>';
        }
        
        const oldDisplay = formatValue(oldValue);
        const newDisplay = formatValue(newValue);
        
        html += `<tr class="${changeClass}">`;
        html += `<td><strong>${escapeHtml(key)}</strong></td>`;
        html += `<td>${oldDisplay}</td>`;
        html += `<td>${newDisplay}</td>`;
        html += `<td>${changeLabel}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    diffContent.innerHTML = html;
    
    const modalEl = document.getElementById('auditDiffModal');
    if (modalEl) {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        modalEl.setAttribute('data-bs-theme', currentTheme);
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function formatValue(value) {
    if (value === null || value === undefined) {
        return '<span class="text-muted">-</span>';
    }
    
    if (typeof value === 'object') {
        return '<pre class="mb-0 small">' + escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';
    }
    
    return escapeHtml(String(value));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Actor autocomplete functionality
document.addEventListener('DOMContentLoaded', function() {
    const actorNameInput = document.querySelector('input[name="actor_name"]');
    if (actorNameInput) {
        let autocompleteTimeout;
        
        actorNameInput.addEventListener('input', function() {
            clearTimeout(autocompleteTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                return;
            }
            
            autocompleteTimeout = setTimeout(function() {
                fetch('/admin/audit-logs/actor-autocomplete?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        // Could be enhanced with a dropdown UI
                        console.log('Actor autocomplete results:', data);
                    })
                    .catch(error => {
                        console.error('Actor autocomplete error:', error);
                    });
            }, 300);
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F: Focus filter panel
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const filterPanel = document.getElementById('auditLogFilterPanel');
            if (filterPanel) {
                const filterButton = document.querySelector('[data-bs-target="#auditLogFilterPanel"]');
                if (filterButton) {
                    filterButton.click();
                }
            }
        }
        
        // Ctrl/Cmd + R: Refresh page
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            window.location.reload();
        }
        
        // Escape: Close modal
        if (e.key === 'Escape') {
            const modal = document.getElementById('auditDiffModal');
            if (modal && modal.classList.contains('show')) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });

    // URL state management
    function updateURLState() {
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        // Update URL with current filter values
        const parishId = document.querySelector('select[name="parish_id"]')?.value;
        const action = document.querySelector('select[name="action"]')?.value;
        const entityType = document.querySelector('select[name="entity_type"]')?.value;
        const actorName = document.querySelector('input[name="actor_name"]')?.value;
        const quickFilter = document.querySelector('select[name="quick_filter"]')?.value;
        const startDate = document.querySelector('input[name="start_date"]')?.value;
        const endDate = document.querySelector('input[name="end_date"]')?.value;
        
        if (parishId && parishId !== '0') params.set('parish_id', parishId);
        else params.delete('parish_id');
        
        if (action) params.set('action', action);
        else params.delete('action');
        
        if (entityType) params.set('entity_type', entityType);
        else params.delete('entity_type');
        
        if (actorName) params.set('actor_name', actorName);
        else params.delete('actor_name');
        
        if (quickFilter) params.set('quick_filter', quickFilter);
        else params.delete('quick_filter');
        
        if (startDate) params.set('start_date', startDate);
        else params.delete('start_date');
        
        if (endDate) params.set('end_date', endDate);
        else params.delete('end_date');
        
        const newUrl = url.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }

    // Update URL when filters change
    const filterInputs = document.querySelectorAll('#auditLogFilterPanel input, #auditLogFilterPanel select');
    filterInputs.forEach(input => {
        input.addEventListener('change', updateURLState);
    });

    // Load statistics
    loadStatistics();

    // Time range selector
    const timeRangeSelector = document.getElementById('timeRangeSelector');
    if (timeRangeSelector) {
        timeRangeSelector.addEventListener('change', function() {
            loadStatistics();
        });
    }

    // Diff buttons: payload is fetched on demand so the listing stays slim.
    document.querySelectorAll('.audit-diff-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const auditId = parseInt(this.dataset.auditId || '0', 10);
            if (!auditId) return;

            const diffContent = document.getElementById('auditDiffContent');
            if (diffContent) {
                diffContent.innerHTML = '<p class="text-muted">Loading…</p>';
            }

            const modalEl = document.getElementById('auditDiffModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();

            fetch('/admin/audit-logs/diff/' + auditId)
                .then(function(response) { return response.json(); })
                .then(function(payload) {
                    if (!payload || payload.success !== true) {
                        showAuditDiff(null, null);
                        return;
                    }
                    const data = payload.data || {};
                    showAuditDiff(data.old_values || null, data.new_values || null);
                })
                .catch(function() {
                    showAuditDiff(null, null);
                });
        });
    });
});

function loadStatistics() {
    const timeRange = document.getElementById('timeRangeSelector')?.value || '24h';
    
    fetch('/admin/audit-logs/statistics?time_range=' + timeRange)
        .then(response => response.json())
        .then(data => {
            // Update today's events
            document.getElementById('statsTodayEvents').textContent = data.today_events || 0;
            
            // Update security events
            document.getElementById('statsSecurityEvents').textContent = data.security_events || 0;
            
            // Update most active users
            const activeUsersList = document.getElementById('statsActiveUsers');
            if (activeUsersList && data.most_active_users) {
                activeUsersList.innerHTML = data.most_active_users.map(user => 
                    `<li class="d-flex justify-content-between">
                        <span>${escapeHtml(user.name)}</span>
                        <span class="fw-bold">${user.count}</span>
                    </li>`
                ).join('') || '<li class="text-muted">' + (activeUsersList.dataset.noData || 'No data') + '</li>';
            }
            
            // Update top operations
            const topOpsList = document.getElementById('statsTopOperations');
            if (topOpsList && data.top_operations) {
                topOpsList.innerHTML = data.top_operations.map(op => 
                    `<li class="d-flex justify-content-between">
                        <span>${escapeHtml(op.action)}</span>
                        <span class="fw-bold">${op.count}</span>
                    </li>`
                ).join('') || '<li class="text-muted">' + (topOpsList.dataset.noData || 'No data') + '</li>';
            }
            
            // Load activity chart
            loadActivityChart(timeRange);
        })
        .catch(error => {
            console.error('Error loading statistics:', error);
        });
}

function loadActivityChart(timeRange) {
    fetch('/admin/audit-logs/statistics?time_range=' + timeRange)
        .then(response => response.json())
        .then(data => {
            // For now, we'll use the same endpoint. In a real implementation,
            // you'd want a separate endpoint for chart data
            // This is a placeholder for the chart implementation
            console.log('Activity chart data would be loaded here');
        })
        .catch(error => {
            console.error('Error loading activity chart:', error);
        });
}

// Real-time updates functionality
let liveUpdatesInterval = null;
let lastAuditId = 0;

function toggleLiveUpdates() {
    const toggleBtn = document.getElementById('toggleLiveBtn');
    const liveIndicator = document.getElementById('liveIndicator');
    
    if (liveUpdatesInterval) {
        // Stop live updates
        clearInterval(liveUpdatesInterval);
        liveUpdatesInterval = null;
        toggleBtn.innerHTML = '<i class="bi bi-play-circle" aria-hidden="true"></i> ' + (toggleBtn.dataset.enableLive || 'Enable Live');
        liveIndicator.style.display = 'none';
    } else {
        // Start live updates
        toggleBtn.innerHTML = '<i class="bi bi-stop-circle" aria-hidden="true"></i> ' + (toggleBtn.dataset.disableLive || 'Disable Live');
        liveIndicator.style.display = 'inline';
        
        // Get the highest audit ID from the current table
        const firstRow = document.querySelector('tbody tr');
        if (firstRow) {
            const firstRowId = parseInt(firstRow.getAttribute('data-audit-id') || '0');
            if (firstRowId > 0) {
                lastAuditId = firstRowId;
            }
        }
        
        // Poll for updates every 10 seconds
        liveUpdatesInterval = setInterval(() => {
            checkForUpdates();
        }, 10000);
        
        // Initial check
        checkForUpdates();
    }
}

function checkForUpdates() {
    fetch('/admin/audit-logs/realtime-updates?last_id=' + lastAuditId)
        .then(response => response.json())
        .then(data => {
            if (data.new_events && data.new_events.length > 0) {
                // Update last ID
                const newLastId = Math.max(...data.new_events.map(e => e.id));
                if (newLastId > lastAuditId) {
                    lastAuditId = newLastId;
                }
                
                // Show notification
                showNewEventsNotification(data.new_events.length);
                
                // Optionally refresh the table or statistics
                loadStatistics();
            }
        })
        .catch(error => {
            console.error('Error checking for updates:', error);
        });
}

function showNewEventsNotification(count) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast-header bg-success text-white">
            <i class="bi bi-bell me-2" aria-hidden="true"></i>
            <strong class="me-auto">New Audit Events</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${count} new audit event(s) detected. Refresh the page to see them.
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
    
    // Handle close button
    const closeBtn = toast.querySelector('.btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            toast.remove();
        });
    }
}

// Toggle live updates button
const toggleLiveBtn = document.getElementById('toggleLiveBtn');
if (toggleLiveBtn) {
    toggleLiveBtn.addEventListener('click', toggleLiveUpdates);
}
</script>
