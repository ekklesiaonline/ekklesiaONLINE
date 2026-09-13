<?php

/**
 * Follow-up stats partial for the church admin dashboard.
 *
 * No required variables — all data is loaded via AJAX from:
 *   GET /dashboard/followup-stats
 *
 * Depends on (already present in Clergy/Views/layouts/main.php):
 *   - Bootstrap 5.3
 *   - Bootstrap Icons 1.11
 *   - Chart.js 4.4
 *   - showAppToast(message, level) helper
 */
?>

<div class="row mt-4" id="followupStatsSection">
    <div class="col-12 mb-2 d-flex align-items-center gap-2">
        <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> <?= lang('Dashboard.followupOverviewTitle') ?></h5>
        <div id="fuSpinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></div>
        <small class="text-muted ms-auto" id="fusLastUpdated"></small>
    </div>

    <!-- KPI tiles -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.followupPendingGuests') ?></h6>
                        <h3 class="mb-0" id="fusPendingFollowup">—</h3>
                    </div>
                    <div class="text-warning fs-1">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.followupCallsToday') ?></h6>
                        <h3 class="mb-0" id="fusPendingCalls">—</h3>
                    </div>
                    <div class="text-danger fs-1">
                        <i class="bi bi-telephone-outbound"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card stat-card info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?= lang('Dashboard.followupFailedMessages7d') ?></h6>
                        <h3 class="mb-0" id="fusFailedMsgs">—</h3>
                    </div>
                    <div class="text-info fs-1">
                        <i class="bi bi-envelope-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New guests per week chart -->
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> <?= lang('Dashboard.followupNewGuestsPerWeek') ?></h5>
            </div>
            <div class="card-body" style="min-height: 220px;">
                <div style="height: 200px; max-height: 200px;">
                    <canvas id="newGuestsPerWeekChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';

        const statsUrl = <?= json_encode(base_url('dashboard/followup-stats')) ?>;
        const pendingEl = document.getElementById('fusPendingFollowup');
        const callsEl = document.getElementById('fusPendingCalls');
        const failedEl = document.getElementById('fusFailedMsgs');
        const lastUpEl = document.getElementById('fusLastUpdated');
        const spinner = document.getElementById('fuSpinner');
        const canvas = document.getElementById('newGuestsPerWeekChart');

        let guestChart = null;

        function buildChart(labels, data) {
            if (!canvas) {
                return;
            }
            const ctx = canvas.getContext('2d');
            if (guestChart) {
                guestChart.data.labels = labels;
                guestChart.data.datasets[0].data = data;
                guestChart.update();
                return;
            }
            guestChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: <?= json_encode(lang('Dashboard.followupNewGuestsDatasetLabel')) ?>,
                        data: data,
                        backgroundColor: 'rgba(13, 110, 253, 0.65)',
                        borderColor: '#0d6efd',
                        borderWidth: 1,
                        borderRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                    },
                },
            });
        }

        function setSpinner(on) {
            if (!spinner) {
                return;
            }
            spinner.classList.toggle('d-none', !on);
        }

        function fetchFollowupStats() {
            setSpinner(true);

            fetch(statsUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(res) {
                    if (!res.success) {
                        throw new Error(res.message || 'Server error');
                    }

                    if (pendingEl) {
                        pendingEl.textContent = res.guests_in_followup ?? '—';
                    }
                    if (callsEl) {
                        callsEl.textContent = res.call_tasks_today ?? '—';
                    }
                    if (failedEl) {
                        failedEl.textContent = res.failed_messages_7d ?? '—';
                    }

                    const weekly = Array.isArray(res.new_guests_weekly) ? res.new_guests_weekly : [];
                    buildChart(
                        weekly.map(function(r) {
                            return r.week;
                        }),
                        weekly.map(function(r) {
                            return r.count;
                        })
                    );

                    if (lastUpEl) {
                        const now = new Date();
                        lastUpEl.textContent = <?= json_encode(lang('Dashboard.followupLastUpdated')) ?> + ' ' +
                            now.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                    }
                })
                .catch(function(err) {
                    console.warn('[followupStats] fetch error:', err);
                    showAppToast(<?= json_encode(lang('Dashboard.followupLoadError')) ?>, 'danger');
                })
                .finally(function() {
                    setSpinner(false);
                });
        }

        // Initial load + 60-second polling.
        fetchFollowupStats();
        setInterval(fetchFollowupStats, 60000);
    }());
</script>