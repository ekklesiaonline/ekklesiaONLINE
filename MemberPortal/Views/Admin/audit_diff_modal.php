<?php
$colFieldLabel = lang('App.colField');
if ($colFieldLabel === 'App.colField' || trim((string) $colFieldLabel) === '') {
    $colFieldLabel = 'Field';
}
$colChangeLabel = lang('App.colChange');
if ($colChangeLabel === 'App.colChange' || trim((string) $colChangeLabel) === '') {
    $colChangeLabel = 'Change';
}
?>

<!-- Native Bootstrap 5 Modal (zero custom CSS; responds to data-bs-theme natively) -->
<div class="modal fade" id="auditDiffModal" tabindex="-1" aria-labelledby="auditDiffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary fs-5" aria-hidden="true"></i>
                    <h5 class="modal-title" id="auditDiffModalLabel"><?= lang('App.auditDiffModalTitle') ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div id="auditDiffContent">
                    <!-- Diff content loaded asynchronously via native show.bs.modal -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
let activeDiffAbortController = null;
let activityChartInstance = null;
let liveUpdatesInterval = null;
let lastAuditId = 0;
let isCheckingLiveUpdates = false;

const auditDiffRoutes = {
    diff: <?= json_encode(rtrim(base_url('admin/audit-logs/diff'), '/') . '/') ?>,
    statistics: <?= json_encode(base_url('admin/audit-logs/statistics')) ?>,
    realtime: <?= json_encode(base_url('admin/audit-logs/realtime-updates')) ?>,
    autocomplete: <?= json_encode(base_url('admin/audit-logs/actor-autocomplete')) ?>
};

function formatFieldLabel(key) {
    if (!key || typeof key !== 'string') return '';
    const acronyms = {
        'id': 'ID',
        'ip': 'IP',
        'ip_address': 'IP Address',
        'ueid': 'UEID',
        'url': 'URL',
        'dob': 'Date of Birth',
        'created_at': 'Created At',
        'updated_at': 'Updated At',
        'deleted_at': 'Deleted At',
        'visit_date': 'Visit Date',
        'first_name': 'First Name',
        'last_name': 'Last Name',
        'middle_name': 'Middle Name',
        'entity_type': 'Entity Type',
        'entity_id': 'Entity ID',
        'user_id': 'User ID',
        'parish_id': 'Parish ID'
    };
    const lower = key.toLowerCase();
    if (acronyms[lower]) {
        return acronyms[lower];
    }
    return key
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

function showAuditDiff(oldValues, newValues) {
    const diffContent = document.getElementById('auditDiffContent');
    if (!diffContent) {
        return;
    }
    
    if (!oldValues && !newValues) {
        diffContent.innerHTML = '<div class="p-4 text-center text-body-secondary"><i class="bi bi-info-circle fs-3 d-block mb-2 text-muted" aria-hidden="true"></i>' + escapeHtml(<?= json_encode(lang('App.auditNoData') ?? 'No changes to display.') ?>) + '</div>';
        return;
    }
    
    const allKeys = new Set([
        ...Object.keys(oldValues || {}),
        ...Object.keys(newValues || {})
    ]);

    if (allKeys.size === 0) {
        diffContent.innerHTML = '<div class="p-4 text-center text-body-secondary"><i class="bi bi-info-circle fs-3 d-block mb-2 text-muted" aria-hidden="true"></i>' + escapeHtml(<?= json_encode(lang('App.auditNoData') ?? 'No changes to display.') ?>) + '</div>';
        return;
    }

    const langAdded = <?= json_encode(lang('App.statusAdded') ?? 'Added') ?>;
    const langRemoved = <?= json_encode(lang('App.statusRemoved') ?? 'Removed') ?>;
    const langChanged = <?= json_encode(lang('App.statusChanged') ?? 'Changed') ?>;
    const langUnchanged = <?= json_encode(lang('App.statusUnchanged') ?? 'Unchanged') ?>;
    
    let html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover align-middle mb-0">';
    html += '<thead><tr>';
    html += '<th scope="col" style="width: 28%;"><?= esc($colFieldLabel) ?></th>';
    html += '<th scope="col" style="width: 32%;"><?= esc(lang('App.oldValues') ?? 'Old Value') ?></th>';
    html += '<th scope="col" style="width: 32%;"><?= esc(lang('App.newValues') ?? 'New Value') ?></th>';
    html += '<th scope="col" style="width: 8%;" class="text-center"><?= esc($colChangeLabel) ?></th>';
    html += '</tr></thead>';
    html += '<tbody>';
    
    allKeys.forEach(key => {
        const oldValue = oldValues ? oldValues[key] : null;
        const newValue = newValues ? newValues[key] : null;
        
        let changeLabel = '';
        let isRemoved = false;
        let isAdded = false;
        
        if (oldValue === undefined || oldValue === null) {
            changeLabel = `<span class="badge border border-success text-success fw-normal">${escapeHtml(langAdded)}</span>`;
            isAdded = true;
        } else if (newValue === undefined || newValue === null) {
            changeLabel = `<span class="badge border border-danger text-danger fw-normal">${escapeHtml(langRemoved)}</span>`;
            isRemoved = true;
        } else if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            changeLabel = `<span class="badge border border-warning text-warning fw-normal">${escapeHtml(langChanged)}</span>`;
            isRemoved = true;
            isAdded = true;
        } else {
            changeLabel = `<span class="badge border border-secondary text-secondary fw-normal">${escapeHtml(langUnchanged)}</span>`;
        }
        
        const oldDisplay = formatValue(oldValue, isRemoved, false);
        const newDisplay = formatValue(newValue, false, isAdded);
        const fieldTitle = formatFieldLabel(key);
        
        html += `<tr>`;
        html += `<td class="fw-semibold">`;
        if (fieldTitle.toLowerCase() !== key.toLowerCase()) {
            html += `<div class="text-body">${escapeHtml(fieldTitle)}</div>`;
            html += `<div class="small text-body-secondary font-monospace">${escapeHtml(key)}</div>`;
        } else {
            html += `<span class="text-body">${escapeHtml(fieldTitle)}</span>`;
        }
        html += `</td>`;
        html += `<td>${oldDisplay}</td>`;
        html += `<td>${newDisplay}</td>`;
        html += `<td class="text-center">${changeLabel}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    diffContent.innerHTML = html;
}

function formatValue(value, isRemoved = false, isAdded = false) {
    if (value === null || value === undefined) {
        return '<span class="text-body-secondary">-</span>';
    }

    if (typeof value === 'boolean') {
        const badgeColor = value ? 'success' : 'secondary';
        return `<span class="badge border border-${badgeColor} text-${badgeColor} fw-normal">${value ? 'true' : 'false'}</span>`;
    }
    
    if (typeof value === 'object') {
        let json = '';
        try {
            json = JSON.stringify(value, null, 2);
        } catch (e) {
            json = String(value);
        }
        let truncated = false;
        if (json.length > 5000) {
            json = json.slice(0, 5000);
            truncated = true;
        }
        return '<pre class="mb-0 small p-2 rounded bg-body-tertiary border text-break font-monospace" style="max-height: 250px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">'
            + escapeHtml(json)
            + (truncated ? '\n... (truncated)' : '')
            + '</pre>';
    }
    
    let str = String(value);
    let truncated = false;
    if (str.length > 1000) {
        str = str.slice(0, 1000);
        truncated = true;
    }

    if (isRemoved && !isAdded) {
        return `<span class="text-danger text-decoration-line-through text-break">${escapeHtml(str)}${truncated ? '… (truncated)' : ''}</span>`;
    }
    if (isAdded && !isRemoved) {
        return `<span class="text-success fw-medium text-break">${escapeHtml(str)}${truncated ? '… (truncated)' : ''}</span>`;
    }
    if (isRemoved && isAdded) {
        return `<span class="text-body text-break">${escapeHtml(str)}${truncated ? '… (truncated)' : ''}</span>`;
    }
    
    return `<span class="text-body text-break">${escapeHtml(str)}${truncated ? '… (truncated)' : ''}</span>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadAuditDiff(auditId) {
    if (!auditId) return;

    if (activeDiffAbortController) {
        activeDiffAbortController.abort();
    }
    activeDiffAbortController = new AbortController();

    const diffContent = document.getElementById('auditDiffContent');
    if (diffContent) {
        diffContent.innerHTML = '<div class="d-flex flex-column align-items-center justify-content-center py-5 text-body-secondary">'
            + '<div class="spinner-border text-primary mb-2" role="status"><span class="visually-hidden">' + escapeHtml(<?= json_encode(lang('App.auditLoading') ?? 'Loading…') ?>) + '</span></div>'
            + '<div>' + escapeHtml(<?= json_encode(lang('App.auditLoading') ?? 'Loading…') ?>) + '</div>'
            + '</div>';
    }

    const modalTitle = document.getElementById('auditDiffModalLabel');
    if (modalTitle) {
        modalTitle.textContent = (<?= json_encode(lang('App.auditDiffModalTitle') ?? 'Audit Log Diff') ?>) + ' #' + auditId;
    }

    fetch(auditDiffRoutes.diff + auditId, {
        signal: activeDiffAbortController.signal,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(payload) {
            if (!payload || payload.success !== true) {
                showAuditDiff(null, null);
                return;
            }
            const data = payload.data || {};
            showAuditDiff(data.old_values || null, data.new_values || null);
        })
        .catch(function(err) {
            if (err && err.name === 'AbortError') {
                return;
            }
            if (diffContent) {
                diffContent.innerHTML = '<div class="p-4 text-center text-danger"><i class="bi bi-exclamation-triangle fs-3 d-block mb-2" aria-hidden="true"></i>' + escapeHtml(<?= json_encode(lang('App.errorGeneric') ?? 'An error occurred. Please try again.') ?>) + '</div>';
            }
        });
}

function renderActivityChart(topOperations) {
    const canvas = document.getElementById('activityChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    if (activityChartInstance) {
        activityChartInstance.destroy();
        activityChartInstance = null;
    }

    const items = Array.isArray(topOperations) ? topOperations : [];
    if (items.length === 0) {
        return;
    }

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const textColor = isDark ? '#cbd5e1' : '#334155';
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.18)' : 'rgba(15, 23, 42, 0.12)';

    activityChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: items.map(op => op.action),
            datasets: [{
                label: <?= json_encode(lang('App.auditStatsTopOperations')) ?>,
                data: items.map(op => op.count),
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: textColor,
                        maxRotation: 45,
                        minRotation: 0,
                        autoSkip: true
                    },
                    grid: { color: gridColor }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: textColor,
                        precision: 0
                    },
                    grid: { color: gridColor }
                }
            }
        }
    });
}

function loadStatistics() {
    const timeRange = document.getElementById('timeRangeSelector')?.value || '24h';
    
    fetch(auditDiffRoutes.statistics + '?time_range=' + encodeURIComponent(timeRange), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            const todayEl = document.getElementById('statsTodayEvents');
            if (todayEl) todayEl.textContent = data.today_events || 0;
            
            const secEl = document.getElementById('statsSecurityEvents');
            if (secEl) secEl.textContent = data.security_events || 0;
            
            const activeUsersList = document.getElementById('statsActiveUsers');
            if (activeUsersList && data.most_active_users) {
                activeUsersList.innerHTML = data.most_active_users.map(user => 
                    `<li class="d-flex justify-content-between">
                        <span>${escapeHtml(user.name)}</span>
                        <span class="fw-bold">${user.count}</span>
                    </li>`
                ).join('') || '<li class="text-muted">' + (activeUsersList.dataset.noData || 'No data') + '</li>';
            }
            
            const topOpsList = document.getElementById('statsTopOperations');
            if (topOpsList && data.top_operations) {
                topOpsList.innerHTML = data.top_operations.map(op => 
                    `<li class="d-flex justify-content-between">
                        <span>${escapeHtml(op.action)}</span>
                        <span class="fw-bold">${op.count}</span>
                    </li>`
                ).join('') || '<li class="text-muted">' + (topOpsList.dataset.noData || 'No data') + '</li>';
            }
            
            renderActivityChart(data.top_operations || []);
        })
        .catch(error => {
            console.error('Error loading statistics:', error);
        });
}

function getHighestAuditIdOnPage() {
    let maxId = 0;
    document.querySelectorAll('tbody tr[data-audit-id]').forEach(function(row) {
        const id = parseInt(row.getAttribute('data-audit-id') || '0', 10);
        if (id > maxId) {
            maxId = id;
        }
    });
    return maxId;
}

function toggleLiveUpdates() {
    const toggleBtn = document.getElementById('toggleLiveBtn');
    const liveIndicator = document.getElementById('liveIndicator');
    if (!toggleBtn) return;
    
    if (liveUpdatesInterval) {
        clearInterval(liveUpdatesInterval);
        liveUpdatesInterval = null;
        toggleBtn.innerHTML = '<i class="bi bi-play-circle" aria-hidden="true"></i> ' + (toggleBtn.dataset.enableLive || 'Enable Live');
        if (liveIndicator) liveIndicator.style.display = 'none';
    } else {
        toggleBtn.innerHTML = '<i class="bi bi-stop-circle" aria-hidden="true"></i> ' + (toggleBtn.dataset.disableLive || 'Disable Live');
        if (liveIndicator) liveIndicator.style.display = 'inline';
        
        lastAuditId = getHighestAuditIdOnPage();
        
        liveUpdatesInterval = setInterval(() => {
            checkForUpdates();
        }, 10000);
        
        checkForUpdates();
    }
}

function checkForUpdates() {
    if (isCheckingLiveUpdates) return;
    isCheckingLiveUpdates = true;

    fetch(auditDiffRoutes.realtime + '?last_id=' + lastAuditId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            if (data.new_events && Array.isArray(data.new_events) && data.new_events.length > 0) {
                const ids = data.new_events.map(e => parseInt(e.id, 10)).filter(id => !isNaN(id));
                if (ids.length > 0) {
                    const maxNewId = Math.max(...ids);
                    if (maxNewId > lastAuditId) {
                        lastAuditId = maxNewId;
                    }
                }
                showNewEventsNotification(data.new_events.length);
                loadStatistics();
            }
        })
        .catch(error => {
            console.error('Error checking for updates:', error);
        })
        .finally(() => {
            isCheckingLiveUpdates = false;
        });
}

function showNewEventsNotification(count) {
    const existing = document.getElementById('auditLiveToast');
    if (existing) {
        existing.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'auditLiveToast';
    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast-header bg-success text-white">
            <i class="bi bi-bell me-2" aria-hidden="true"></i>
            <strong class="me-auto">${escapeHtml(<?= json_encode(lang('App.auditLive') ?? 'Live') ?>)}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="<?= esc(lang('App.close')) ?>"></button>
        </div>
        <div class="toast-body">
            ${count} new audit event(s) detected. Refresh the page to see them.
        </div>
    `;
    
    document.body.appendChild(toast);
    
    const timeout = setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
    
    const closeBtn = toast.querySelector('.btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            clearTimeout(timeout);
            toast.remove();
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('auditDiffModal');

    // Native Bootstrap 5 modal lifecycle:
    // data-bs-toggle="modal" on buttons opens the modal natively.
    // show.bs.modal triggers on demand diff data loading via event.relatedTarget.
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function(event) {
            const triggerBtn = event.relatedTarget;
            if (!triggerBtn) return;
            const auditId = parseInt(triggerBtn.getAttribute('data-bs-audit-id') || '0', 10);
            if (auditId) {
                loadAuditDiff(auditId);
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            if (activeDiffAbortController) {
                activeDiffAbortController.abort();
                activeDiffAbortController = null;
            }
            const diffContent = document.getElementById('auditDiffContent');
            if (diffContent) {
                diffContent.innerHTML = '';
            }
            const modalTitle = document.getElementById('auditDiffModalLabel');
            if (modalTitle) {
                modalTitle.textContent = <?= json_encode(lang('App.auditDiffModalTitle') ?? 'Audit Log Diff') ?>;
            }
        });
    }

    // Actor autocomplete with debouncing and request cancellation
    const actorNameInput = document.querySelector('input[name="actor_name"]');
    if (actorNameInput) {
        let autocompleteTimeout;
        let autocompleteAbort = null;
        
        actorNameInput.addEventListener('input', function() {
            clearTimeout(autocompleteTimeout);
            if (autocompleteAbort) {
                autocompleteAbort.abort();
            }

            const query = this.value.trim();
            if (query.length < 2) {
                return;
            }
            
            autocompleteTimeout = setTimeout(function() {
                autocompleteAbort = new AbortController();
                fetch(auditDiffRoutes.autocomplete + '?q=' + encodeURIComponent(query), {
                    signal: autocompleteAbort.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        // Dropdown hook for actor autocomplete suggestions
                    })
                    .catch(() => {});
            }, 300);
        });
    }

    // Keyboard shortcut: Ctrl/Cmd + F to toggle filter panel
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
                return;
            }
            e.preventDefault();
            const filterButton = document.querySelector('[data-bs-target="#auditLogFilterPanel"]');
            if (filterButton) {
                filterButton.click();
            }
        }
    });

    // Update URL query parameters on filter change
    function updateURLState() {
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
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

    const filterInputs = document.querySelectorAll('#auditLogFilterPanel input, #auditLogFilterPanel select');
    filterInputs.forEach(input => {
        input.addEventListener('change', updateURLState);
    });

    // Initial statistics load
    loadStatistics();

    // Time range selector change
    const timeRangeSelector = document.getElementById('timeRangeSelector');
    if (timeRangeSelector) {
        timeRangeSelector.addEventListener('change', function() {
            loadStatistics();
        });
    }

    // Toggle live updates button
    const toggleLiveBtn = document.getElementById('toggleLiveBtn');
    if (toggleLiveBtn) {
        toggleLiveBtn.addEventListener('click', toggleLiveUpdates);
    }

    // React to theme change for Chart.js
    document.addEventListener('ekklesiaONLINE:themechange', function(e) {
        if (activityChartInstance) {
            const isDark = (e.detail?.theme || document.documentElement.getAttribute('data-bs-theme') || 'light') === 'dark';
            const textColor = isDark ? '#cbd5e1' : '#334155';
            const gridColor = isDark ? 'rgba(148, 163, 184, 0.18)' : 'rgba(15, 23, 42, 0.12)';
            if (activityChartInstance.options.scales?.x) {
                activityChartInstance.options.scales.x.ticks.color = textColor;
                activityChartInstance.options.scales.x.grid.color = gridColor;
            }
            if (activityChartInstance.options.scales?.y) {
                activityChartInstance.options.scales.y.ticks.color = textColor;
                activityChartInstance.options.scales.y.grid.color = gridColor;
            }
            activityChartInstance.update();
        }
    });
});
</script>
