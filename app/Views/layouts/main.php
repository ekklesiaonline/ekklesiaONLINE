<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName, 'attr') ?>">
    <script>
        (function () {
            var storageKey = 'ekklesiard-theme';
            var savedTheme = null;

            try {
                savedTheme = localStorage.getItem(storageKey);
            } catch (error) {
                savedTheme = null;
            }

            var theme = savedTheme;
            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ?
                    'dark' :
                    'light';
            }

            document.documentElement.setAttribute('data-bs-theme', theme);
        }());
    </script>
    <title><?= esc($title ?? 'EkklesiaRD - Parish Management System') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- L4: Shared CSRF helper for native fetch POST calls -->
    <script src="<?= base_url('assets/js/csrf.js') ?>"></script>

    <style>
        :root {
            --app-body-bg: #f1f5f9;
            --app-main-bg: #f8f9fa;
            --app-surface: #ffffff;
            --app-surface-muted: #f8f9fa;
            --app-border: rgba(15, 23, 42, 0.08);
            --app-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --app-sidebar-bg: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            --app-sidebar-color: rgba(255, 255, 255, 0.82);
            --app-sidebar-active-bg: rgba(255, 255, 255, 0.12);
            --app-sidebar-active-color: #ffffff;
            --app-topbar-bg: rgba(255, 255, 255, 0.9);
            --app-chart-text: #334155;
            --app-chart-grid: rgba(15, 23, 42, 0.12);
            --app-chart-tooltip-bg: rgba(255, 255, 255, 0.96);
            --app-chart-tooltip-border: rgba(15, 23, 42, 0.16);
            --app-chart-tooltip-title: #0f172a;
            --app-chart-tooltip-body: #334155;
            --app-card-header-text: #0f172a;
            --app-card-header-muted: #64748b;
            --app-card-header-icon: #334155;
        }

        [data-bs-theme="dark"] {
            --app-body-bg: #0b1220;
            --app-main-bg: #111827;
            --app-surface: #182233;
            --app-surface-muted: #1f2937;
            --app-border: rgba(148, 163, 184, 0.18);
            --app-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.28);
            --app-sidebar-bg: linear-gradient(180deg, #0f172a 0%, #172033 100%);
            --app-sidebar-color: rgba(226, 232, 240, 0.82);
            --app-sidebar-active-bg: rgba(148, 163, 184, 0.16);
            --app-sidebar-active-color: #f8fafc;
            --app-topbar-bg: rgba(24, 34, 51, 0.92);
            --app-chart-text: #cbd5e1;
            --app-chart-grid: rgba(148, 163, 184, 0.18);
            --app-chart-tooltip-bg: rgba(15, 23, 42, 0.96);
            --app-chart-tooltip-border: rgba(148, 163, 184, 0.24);
            --app-chart-tooltip-title: #f8fafc;
            --app-chart-tooltip-body: #cbd5e1;
            --app-card-header-text: #f1f5f9;
            --app-card-header-muted: #cbd5e1;
            --app-card-header-icon: #e2e8f0;
        }

        body {
            background-color: var(--app-body-bg);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: var(--app-sidebar-bg);
            color: white;
            border-right: 1px solid var(--app-border);
        }

        .sidebar .nav-link {
            color: var(--app-sidebar-color);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0.25rem 0;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--app-sidebar-active-bg);
            color: var(--app-sidebar-active-color);
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }

        .main-content {
            background-color: var(--app-main-bg);
            min-height: 100vh;
        }

        .card {
            border: 1px solid var(--app-border);
            box-shadow: var(--app-shadow);
            margin-bottom: 1.5rem;
            background-color: var(--app-surface);
        }

        .card-header,
        .card-footer,
        .modal-content,
        .dropdown-menu,
        .list-group-item,
        .table,
        .accordion-item {
            background-color: var(--app-surface);
        }

        .card-header {
            color: var(--app-card-header-text);
        }

        .card-header .text-muted,
        .card-header small,
        .card-header .small {
            color: var(--app-card-header-muted) !important;
        }

        .card-header .bi {
            color: var(--app-card-header-icon);
        }

        .bg-light,
        .table-light,
        .bg-white,
        .modal-header,
        .modal-footer {
            background-color: var(--app-surface-muted) !important;
        }

        .table,
        .table>:not(caption)>*>* {
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--app-border);
        }

        .table-hover>tbody>tr:hover>* {
            --bs-table-accent-bg: rgba(148, 163, 184, 0.08);
        }

        .stat-card {
            border-left: 4px solid;
        }

        .stat-card.primary {
            border-left-color: #0d6efd;
        }

        .stat-card.success {
            border-left-color: #198754;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.danger {
            border-left-color: #dc3545;
        }

        .stat-card.info {
            border-left-color: #0dcaf0;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .table-actions button,
        .table-actions a {
            margin: 0 0.125rem;
        }

        /* Default all filled buttons to outline style for UI consistency. */
        .btn-primary,
        .btn-secondary,
        .btn-success,
        .btn-danger,
        .btn-warning,
        .btn-info,
        .btn-light,
        .btn-dark {
            --bs-btn-bg: transparent;
            --bs-btn-color: var(--bs-btn-border-color);
            --bs-btn-hover-color: #fff;
            --bs-btn-active-color: #fff;
            --bs-btn-disabled-bg: transparent;
            --bs-btn-disabled-color: var(--bs-btn-border-color);
            --bs-btn-disabled-border-color: var(--bs-btn-border-color);
        }

        .btn-warning,
        .btn-info,
        .btn-light {
            --bs-btn-hover-color: #212529;
            --bs-btn-active-color: #212529;
        }

        .top-header-title {
            margin-bottom: 0;
            font-weight: 600;
        }

        .top-header-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
            text-align: right;
            white-space: nowrap;
            width: 100%;
        }

        .top-header-bar {
            background: var(--app-topbar-bg) !important;
            backdrop-filter: blur(10px);
            border-bottom-color: var(--app-border) !important;
        }

        .theme-toggle-btn {
            min-width: 8.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .theme-toggle-btn .bi {
            line-height: 1;
        }

        .language-toggle-btn {
            min-width: 6.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .language-menu .dropdown-item {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .language-flag {
            width: 1.15rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .top-header-meta {
                gap: 0.5rem;
                white-space: normal;
            }

            .theme-toggle-btn {
                min-width: auto;
            }

            .language-toggle-btn {
                min-width: auto;
            }
        }

        .filter-panel-header {
            padding: 0.5rem 0.75rem;
        }

        .filter-toggle-btn {
            min-width: 6.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            line-height: 1.2;
            padding: 0.3rem 0.8rem;
            white-space: nowrap;
        }

        .filter-toggle-btn .bi {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1;
        }

        .filter-panel-body {
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
        }
    </style>

    <?= $this->renderSection('styles') ?>
</head>

<body>
    <div class="toast-container position-fixed top-0 end-0 p-3" id="appToastContainer" style="z-index: 1100;"></div>

    <!-- Shared confirm modal (populated by showAppConfirm) -->
    <div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmModalLabel" aria-modal="true"
        role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="appConfirmModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <div class="modal-body" id="appConfirmModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger"
                        id="appConfirmModalOkBtn"><?= lang('App.confirm') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Expiry Warning Modal -->
    <div class="modal fade" id="sessionExpiryModal" tabindex="-1" aria-labelledby="sessionExpiryModalLabel"
        aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="sessionExpiryModalLabel">
                        <i class="bi bi-clock-history me-2 text-warning" aria-hidden="true"></i>
                        <?= lang('App.sessionExpiringTitle') ?>
                    </h5>
                </div>
                <div class="modal-body">
                    <p>
                        <?= lang('App.sessionExpiringBody') ?>
                        <strong><span id="sessionCountdownSecs">300</span>
                            <?= lang('App.sessionExpiringSeconds') ?></strong>.
                    </p>
                    <p class="text-muted mb-0">
                        <?= lang('App.sessionExpiringHint') ?>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="sessionStayLoggedInBtn">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        <?= lang('App.sessionStayLoggedIn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <?= view('partials/sidebar') ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <?php
                $headerTitle = $page_title ?? null;
                if (empty($headerTitle)) {
                    $uri = trim((string) uri_string(), '/');
                    $segment = $uri !== '' ? explode('/', $uri)[0] : 'dashboard';
                    $segmentMap = [
                        'dashboard' => 'App.navDashboard',
                        'people' => 'App.navPeople',
                        'members' => 'App.navMembers',
                        'families' => 'App.navFamilies',
                        'services' => 'App.navServices',
                        'attendance' => 'App.navAttendance',
                        'visitors' => 'App.navVisitors',
                        'followup' => 'App.navFollowUp',
                        'donations' => 'App.navDonations',
                        'expenses' => 'App.navExpenses',
                        'budgets' => 'App.navBudgets',
                        'reports' => 'App.navReports',
                        'volunteers' => 'App.navVolunteers',
                        'profile' => 'App.navProfile',
                        'parishes' => 'App.navParishes',
                        'events' => 'App.navEvents',
                        'pledges' => 'App.navPledges',
                        'worship-planning' => 'App.navWorshipPlanning',
                        'chart-accounts' => 'App.navChartOfAccounts',
                        'chart-of-accounts' => 'App.navChartOfAccounts',
                    ];

                    if (isset($segmentMap[$segment])) {
                        $translated = lang($segmentMap[$segment]);
                        $headerTitle = ($translated === $segmentMap[$segment])
                            ? ucwords(str_replace('-', ' ', $segment))
                            : $translated;
                    } else {
                        $headerTitle = ucwords(str_replace('-', ' ', $segment));
                    }
                }

                $scopeIcon = $scope_icon ?? 'bi-parish';
                $scopeName = $scope_name ?? 'All Parishes';
                $loggedInName = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
                if ($loggedInName === '') {
                    $loggedInName = auth()->user()->username ?? auth()->user()->email ?? '';
                }
                if ($loggedInName === '') {
                    $loggedInName = 'User';
                }

                $activeLocale = strtolower((string) service('request')->getLocale());
                if (!in_array($activeLocale, ['en', 'es', 'fr'], true)) {
                    $activeLocale = 'en';
                }

                $localeMap = [
                    'en' => ['label' => 'EN', 'name' => 'English', 'flag' => '🇺🇸'],
                    'es' => ['label' => 'SP', 'name' => 'Spanish', 'flag' => '🇩🇴'],
                    'fr' => ['label' => 'FR', 'name' => 'French', 'flag' => '🇫🇷'],
                ];

                ?>
                <!-- Top Navbar -->
                <div
                    class="top-header-bar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom px-3 sticky-top">
                    <h1 class="h4 top-header-title"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i><?= esc($headerTitle) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="top-header-meta ms-auto">
                            <button type="button" class="btn btn-outline-secondary btn-sm theme-toggle-btn"
                                id="themeToggleBtn" aria-label="<?= esc(lang('App.themeToggle')) ?>">
                                <i class="bi bi-moon-stars" id="themeToggleIcon" aria-hidden="true"></i>
                                <span id="themeToggleLabel"><?= lang('App.themeDark') ?></span>
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle language-toggle-btn"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="<?= esc(lang('App.changeLanguage')) ?>">
                                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                                    <span
                                        class="language-flag"><?= esc((string) $localeMap[$activeLocale]['flag']) ?></span>
                                    <span><?= esc((string) $localeMap[$activeLocale]['label']) ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end language-menu">
                                    <?php foreach ($localeMap as $localeCode => $localeMeta): ?>
                                        <li>
                                            <a class="dropdown-item <?= $activeLocale === $localeCode ? 'active' : '' ?>"
                                                href="<?= base_url('locale/set/' . $localeCode . '?return=' . rawurlencode(current_url() . ((string) service('request')->getServer('QUERY_STRING') !== '' ? ('?' . (string) service('request')->getServer('QUERY_STRING')) : ''))) ?>">
                                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                                <span class="language-flag"><?= esc((string) $localeMeta['flag']) ?></span>
                                                <span><?= esc((string) $localeMeta['name']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <span class="text-muted">
                                <i class="bi <?= esc($scopeIcon) ?>"></i> <?= esc($scopeName) ?>
                            </span>
                            <span class="text-muted">
                                <i class="bi bi-person-circle" aria-hidden="true"></i> <?= esc($loggedInName) ?>
                                <?php if (!empty($role_label)): ?>
                                    <span class="badge text-bg-secondary ms-1" title="<?= esc(lang('App.roleBadgeTitle')) ?>"><?= esc($role_label) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (session()->has('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= esc(session('success')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->has('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= esc(session('error')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->has('errors')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach (session('errors') as $error): ?>
                                <li><?= esc((string) $error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (($profile_pending ?? false) && !str_starts_with(uri_string(), 'profile/complete')): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong><?= esc(lang('Profile.profilePendingWarningTitle')) ?></strong>
                        <?= esc(lang('Profile.profilePendingWarningBody')) ?>
                        <a href="<?= base_url((string) ($profile_nav_url ?? 'profile/complete')) ?>"
                            class="btn btn-sm btn-outline-warning ms-2">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i> <?= esc(lang('Profile.completeProfileNow')) ?>
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Content -->
                <div class="container-fluid px-0">
                    <?= $this->renderSection('content') ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (for AJAX) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        function showAppToast(message, level) {
            var container = document.getElementById('appToastContainer');
            if (!container || typeof bootstrap === 'undefined') {
                return;
            }

            var tone = level === 'danger' ? 'text-bg-danger' : (level === 'warning' ? 'text-bg-warning' : 'text-bg-primary');
            var wrapper = document.createElement('div');
            wrapper.className = 'toast align-items-center border-0 ' + tone;
            wrapper.setAttribute('role', 'alert');
            wrapper.setAttribute('aria-live', 'assertive');
            wrapper.setAttribute('aria-atomic', 'true');
            wrapper.innerHTML = '' +
                '<div class="d-flex">' +
                '<div class="toast-body">' + message + '</div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= esc(lang('App.close')) ?>"></button>' +
                '</div>';

            container.appendChild(wrapper);
            var toast = new bootstrap.Toast(wrapper, {
                delay: 5000
            });
            toast.show();
            wrapper.addEventListener('hidden.bs.toast', function () {
                wrapper.remove();
            });
        }

        function showAppConfirm(title, body, onConfirm, confirmLabel) {
            var modalEl = document.getElementById('appConfirmModal');
            if (!modalEl || typeof bootstrap === 'undefined') {
                if (onConfirm && window.confirm(typeof body === 'string' ? body : title)) {
                    onConfirm();
                }
                return;
            }
            document.getElementById('appConfirmModalLabel').textContent = title;
            document.getElementById('appConfirmModalBody').textContent = body;
            var okBtn = document.getElementById('appConfirmModalOkBtn');
            var newBtn = okBtn.cloneNode(true);
            okBtn.parentNode.replaceChild(newBtn, okBtn);
            newBtn.textContent = confirmLabel || <?= json_encode(lang('App.confirm')) ?>;
            newBtn.addEventListener('click', function () {
                bootstrap.Modal.getInstance(modalEl).hide();
                if (onConfirm) {
                    onConfirm();
                }
            });
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // Forms with data-confirm-title trigger a confirm modal before submitting.
        // Forms with data-confirm-double get a two-step flow: Bootstrap modal, then SweetAlert2.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.getAttribute || !form.getAttribute('data-confirm-title')) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            var t = form.getAttribute('data-confirm-title') || <?= json_encode(lang('App.confirm')) ?>;
            var b = form.getAttribute('data-confirm-body') || t;
            var isDouble = form.hasAttribute('data-confirm-double');

            if (isDouble && typeof Swal !== 'undefined') {
                var swalTitle = form.getAttribute('data-confirm-swal-title') || t;
                showAppConfirm(t, b, function () {
                    Swal.fire({
                        title: swalTitle,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: <?= json_encode(lang('App.confirm')) ?>,
                        cancelButtonText: <?= json_encode(lang('App.cancel')) ?>
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            } else {
                showAppConfirm(t, b, function () {
                    form.submit();
                });
            }
        }, true);

        function mapHttpErrorMessage(status, retryAfter) {
            if (status === 400) {
                return <?= json_encode(lang('App.httpInvalidRequest')) ?>;
            }
            if (status === 401) {
                return <?= json_encode(lang('App.httpUnauthenticated')) ?>;
            }
            if (status === 403) {
                return <?= json_encode(lang('App.httpForbidden')) ?>;
            }
            if (status === 404) {
                return <?= json_encode(lang('App.httpNotFound')) ?>;
            }
            if (status === 409) {
                return <?= json_encode(lang('App.httpConflict')) ?>;
            }
            if (status === 419) {
                return <?= json_encode(lang('App.httpSessionExpired')) ?>;
            }
            if (status === 422) {
                return <?= json_encode(lang('App.httpValidation')) ?>;
            }
            if (status === 429) {
                if (retryAfter) {
                    return <?= json_encode(lang('App.httpTooManyRequestsWithRetry', ['{0}'])) ?>.replace('{0}', retryAfter);
                }

                return <?= json_encode(lang('App.httpTooManyRequests')) ?>;
            }
            if (status >= 500) {
                return <?= json_encode(lang('App.httpServerError')) ?>;
            }

            return <?= json_encode(lang('App.httpRequestFailedRetry')) ?>;
        }

        function firstValidationError(errors) {
            if (!errors || typeof errors !== 'object') {
                return '';
            }

            for (var key in errors) {
                if (!Object.prototype.hasOwnProperty.call(errors, key)) {
                    continue;
                }

                var value = errors[key];
                if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string') {
                    return value[0];
                }
                if (typeof value === 'string' && value.trim() !== '') {
                    return value;
                }
            }

            return '';
        }

        function parseJsonSafely(rawText) {
            if (!rawText || typeof rawText !== 'string') {
                return null;
            }

            try {
                return JSON.parse(rawText);
            } catch (_e) {
                return null;
            }
        }

        function extractResponseErrorMessage(status, payload, fallbackMessage, retryAfter) {
            var message = '';

            if (payload && typeof payload === 'object') {
                if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                    message = payload.message;
                }

                if (!message && payload.errors) {
                    message = firstValidationError(payload.errors);
                }
            }

            if (!message) {
                message = fallbackMessage || mapHttpErrorMessage(status, retryAfter);
            }

            return message;
        }

        function clearInlineValidation(context) {
            var scope = context && context.querySelectorAll ? context : document;

            scope.querySelectorAll('.is-invalid').forEach(function (field) {
                field.classList.remove('is-invalid');
            });

            scope.querySelectorAll('.invalid-feedback[data-inline-generated="true"]').forEach(function (node) {
                node.remove();
            });
        }

        function applyInlineValidation(errors, context) {
            if (!errors || typeof errors !== 'object') {
                return;
            }

            var scope = context && context.querySelectorAll ? context : document;

            Object.keys(errors).forEach(function (fieldName) {
                var rawMessage = errors[fieldName];
                var message = Array.isArray(rawMessage) ? rawMessage[0] : rawMessage;

                if (typeof message !== 'string' || message.trim() === '') {
                    return;
                }

                var selector = '[name="' + String(fieldName).replace(/"/g, '\\"') + '"]';
                var input = scope.querySelector(selector);
                if (!input) {
                    return;
                }

                input.classList.add('is-invalid');

                var feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.dataset.inlineGenerated = 'true';
                feedback.textContent = message;

                input.insertAdjacentElement('afterend', feedback);
            });
        }

        // Attach the CSRF token to every jQuery AJAX request automatically.
        // CI4 accepts the token via the X-CSRF-TOKEN request header.
        (function () {
            var token = document.querySelector('meta[name="csrf-token"]');
            var pendingWrites = {};

            function requestKey(url, method) {
                return String(method || 'GET').toUpperCase() + '::' + String(url || '');
            }

            if (token) {
                $.ajaxSetup({
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));
                    }
                });
            }

            $.ajaxPrefilter(function (options, _originalOptions, jqXHR) {
                var method = String(options.type || options.method || 'GET').toUpperCase();
                if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) {
                    return;
                }

                var key = requestKey(options.url, method);
                var originalBeforeSend = options.beforeSend;
                var originalComplete = options.complete;

                options.beforeSend = function (xhr, settings) {
                    if (pendingWrites[key]) {
                        showAppToast('A similar request is already in progress. Please wait.', 'warning');
                        return false;
                    }

                    pendingWrites[key] = true;

                    if (typeof originalBeforeSend === 'function') {
                        return originalBeforeSend.call(this, xhr, settings);
                    }

                    return true;
                };

                options.complete = function (xhr, textStatus) {
                    delete pendingWrites[key];

                    if (typeof originalComplete === 'function') {
                        originalComplete.call(this, xhr, textStatus);
                    }
                };

                jqXHR.fail(function (_xhr, textStatus) {
                    if (textStatus === 'abort') {
                        delete pendingWrites[key];
                    }
                });
            });

            // Keep the meta token in sync: session CSRF regenerates the hash
            // after each verified request, and AJAX responses echo the new
            // token via the X-CSRF-TOKEN header (csrfTokenHeader filter).
            $(document).ajaxComplete(function (_event, xhr) {
                var newToken = xhr && typeof xhr.getResponseHeader === 'function'
                    ? xhr.getResponseHeader('X-CSRF-TOKEN')
                    : null;
                if (newToken && token) {
                    token.setAttribute('content', newToken);
                }
            });

            $(document).ajaxError(function (_event, xhr) {
                var status = xhr && xhr.status ? xhr.status : 0;
                var retryAfter = xhr && typeof xhr.getResponseHeader === 'function' ?
                    xhr.getResponseHeader('Retry-After') :
                    null;
                var payload = xhr && xhr.responseJSON ? xhr.responseJSON : parseJsonSafely(xhr && xhr.responseText ? xhr.responseText : '');

                clearInlineValidation(document);
                if (status === 422 && payload && payload.errors) {
                    applyInlineValidation(payload.errors, document);
                }

                var message = extractResponseErrorMessage(status, payload, '', retryAfter);
                showAppToast(message, (xhr && xhr.status >= 500) ? 'danger' : 'warning');

                if (status === 403 && payload && payload.redirect) {
                    setTimeout(function () {
                        window.location.href = String(payload.redirect);
                    }, 1200);
                    return;
                }

                // Session or CSRF expired — redirect to login after a short delay
                // so the user sees the toast message before being navigated away.
                if (status === 401 || status === 419) {
                    setTimeout(function () {
                        window.location.href = '<?= base_url('login') ?>';
                    }, 2500);
                }
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form || form.tagName !== 'FORM') {
                    return;
                }

                clearInlineValidation(form);

                if (form.dataset.allowRepeatSubmit === 'true') {
                    return;
                }

                if (form.dataset.isSubmitting === 'true') {
                    event.preventDefault();
                    showAppToast('Submission in progress. Please wait.', 'warning');
                    return;
                }

                form.dataset.isSubmitting = 'true';
                var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitButtons.forEach(function (button) {
                    button.dataset.originalLabel = button.tagName === 'BUTTON' ? button.innerHTML : button.value;
                    button.disabled = true;
                    if (button.tagName === 'BUTTON') {
                        button.innerHTML = 'Processing...';
                    } else {
                        button.value = 'Processing...';
                    }
                });
            });

            window.addEventListener('pageshow', function () {
                document.querySelectorAll('form[data-is-submitting="true"]').forEach(function (form) {
                    form.dataset.isSubmitting = 'false';
                    var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    submitButtons.forEach(function (button) {
                        button.disabled = false;
                        if (button.dataset.originalLabel) {
                            if (button.tagName === 'BUTTON') {
                                button.innerHTML = button.dataset.originalLabel;
                            } else {
                                button.value = button.dataset.originalLabel;
                            }
                        }
                    });
                });
            });

            document.addEventListener('input', function (event) {
                if (!event.target || !event.target.classList) {
                    return;
                }

                if (event.target.classList.contains('is-invalid')) {
                    event.target.classList.remove('is-invalid');
                }
            });
        }());

        (function () {
            if (typeof window.fetch !== 'function') {
                return;
            }

            var nativeFetch = window.fetch.bind(window);
            var pendingWrites = {};

            function requestKey(url, method) {
                return String(method || 'GET').toUpperCase() + '::' + String(url || '');
            }

            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                var method = String((init && init.method) || 'GET').toUpperCase();
                var key = requestKey(url, method);

                if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
                    if (pendingWrites[key]) {
                        showAppToast('A similar request is already in progress. Please wait.', 'warning');
                        return Promise.reject(new Error('duplicate_request_blocked'));
                    }
                    pendingWrites[key] = true;
                }

                return nativeFetch(input, init).then(function (response) {
                    if (!response.ok) {
                        var retryAfter = response.headers && response.headers.get ? response.headers.get('Retry-After') : null;
                        var responseStatus = response.status;
                        return response.clone().text().then(function (rawText) {
                            var payload = parseJsonSafely(rawText);

                            clearInlineValidation(document);
                            if (responseStatus === 422 && payload && payload.errors) {
                                applyInlineValidation(payload.errors, document);
                            }

                            var message = extractResponseErrorMessage(responseStatus, payload, '', retryAfter);
                            showAppToast(message, responseStatus >= 500 ? 'danger' : 'warning');
                            if (responseStatus === 403 && payload && payload.redirect) {
                                setTimeout(function () {
                                    window.location.href = String(payload.redirect);
                                }, 1200);
                                return response;
                            }
                            if (responseStatus === 401 || responseStatus === 419) {
                                setTimeout(function () {
                                    window.location.href = '<?= base_url('login') ?>';
                                }, 2500);
                            }
                            return response;
                        }).catch(function () {
                            var fallback = mapHttpErrorMessage(responseStatus, retryAfter);
                            showAppToast(fallback, responseStatus >= 500 ? 'danger' : 'warning');
                            if (responseStatus === 401 || responseStatus === 419) {
                                setTimeout(function () {
                                    window.location.href = '<?= base_url('login') ?>';
                                }, 2500);
                            }
                            return response;
                        });
                    }
                    return response;
                }).catch(function (error) {
                    if (!error || error.message !== 'duplicate_request_blocked') {
                        showAppToast('Network error. Check your connection and try again.', 'danger');
                    }
                    throw error;
                }).finally(function () {
                    if (pendingWrites[key]) {
                        delete pendingWrites[key];
                    }
                });
            };
        }());

        var i18nTheme = {
            darkMode: '<?= lang('App.themeDark') ?>',
            lightMode: '<?= lang('App.themeLight') ?>'
        };
        (function () {
            var storageKey = 'ekklesiard-theme';
            var root = document.documentElement;
            var toggleBtn = document.getElementById('themeToggleBtn');
            var toggleIcon = document.getElementById('themeToggleIcon');
            var toggleLabel = document.getElementById('themeToggleLabel');

            if (!toggleBtn || !toggleIcon || !toggleLabel) {
                return;
            }

            function currentTheme() {
                return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            }

            function cssVar(name) {
                return getComputedStyle(root).getPropertyValue(name).trim();
            }

            function updateScaleTheme(scaleOptions) {
                if (!scaleOptions) {
                    return;
                }

                scaleOptions.ticks = scaleOptions.ticks || {};
                scaleOptions.grid = scaleOptions.grid || {};
                scaleOptions.border = scaleOptions.border || {};

                scaleOptions.ticks.color = cssVar('--app-chart-text');
                scaleOptions.grid.color = cssVar('--app-chart-grid');
                scaleOptions.border.color = cssVar('--app-chart-grid');
            }

            function applyChartTheme() {
                if (typeof Chart === 'undefined' || !Chart.defaults) {
                    return;
                }

                var textColor = cssVar('--app-chart-text');
                var gridColor = cssVar('--app-chart-grid');
                var tooltipBackground = cssVar('--app-chart-tooltip-bg');
                var tooltipBorder = cssVar('--app-chart-tooltip-border');
                var tooltipTitle = cssVar('--app-chart-tooltip-title');
                var tooltipBody = cssVar('--app-chart-tooltip-body');

                Chart.defaults.color = textColor;
                Chart.defaults.borderColor = gridColor;
                Chart.defaults.plugins.legend.labels.color = textColor;
                Chart.defaults.plugins.tooltip.backgroundColor = tooltipBackground;
                Chart.defaults.plugins.tooltip.borderColor = tooltipBorder;
                Chart.defaults.plugins.tooltip.borderWidth = 1;
                Chart.defaults.plugins.tooltip.titleColor = tooltipTitle;
                Chart.defaults.plugins.tooltip.bodyColor = tooltipBody;
                Chart.defaults.plugins.tooltip.displayColors = true;

                var instances = Object.values(Chart.instances || {});
                instances.forEach(function (chart) {
                    if (!chart || !chart.options) {
                        return;
                    }

                    chart.options.plugins = chart.options.plugins || {};
                    chart.options.plugins.legend = chart.options.plugins.legend || {};
                    chart.options.plugins.legend.labels = chart.options.plugins.legend.labels || {};
                    chart.options.plugins.legend.labels.color = textColor;

                    chart.options.plugins.tooltip = chart.options.plugins.tooltip || {};
                    chart.options.plugins.tooltip.backgroundColor = tooltipBackground;
                    chart.options.plugins.tooltip.borderColor = tooltipBorder;
                    chart.options.plugins.tooltip.borderWidth = 1;
                    chart.options.plugins.tooltip.titleColor = tooltipTitle;
                    chart.options.plugins.tooltip.bodyColor = tooltipBody;

                    if (chart.options.scales) {
                        Object.keys(chart.options.scales).forEach(function (key) {
                            updateScaleTheme(chart.options.scales[key]);
                        });
                    }

                    chart.update();
                });
            }

            function applyTheme(theme) {
                root.setAttribute('data-bs-theme', theme);
                toggleIcon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
                toggleLabel.textContent = theme === 'dark' ? i18nTheme.lightMode : i18nTheme.darkMode;
                toggleBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');

                try {
                    localStorage.setItem(storageKey, theme);
                } catch (error) {
                    // Ignore storage failures.
                }

                applyChartTheme();
                document.dispatchEvent(new CustomEvent('ekklesiard:themechange', {
                    detail: {
                        theme: theme
                    }
                }));
            }

            applyTheme(currentTheme());

            toggleBtn.addEventListener('click', function () {
                applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
            });
        }());
    </script>

    <script>
        // -----------------------------------------------------------------------
        // Session idle-timeout manager
        //
        // Shows a warning modal at 25 minutes of inactivity.
        // Automatically redirects to /logout at 30 minutes unless the user clicks
        // "Stay Logged In", which pings /session/keepalive to reset both the
        // client-side timer and the server-side session idle clock.
        // -----------------------------------------------------------------------
        (function () {
            var WARN_MS = 25 * 60 * 1000; // 25 minutes → show warning
            var LOGOUT_MS = 30 * 60 * 1000; // 30 minutes → auto-logout
            var KEEPALIVE_URL = '<?= base_url('session/keepalive') ?>';
            var LOGOUT_URL = '<?= base_url('logout') ?>';

            var idleTimer;
            var logoutTimer;
            var countdownInterval;
            var modalInstance = null;
            var isKeepalivePending = false;

            function resetTimer() {
                clearTimeout(idleTimer);
                idleTimer = setTimeout(showWarning, WARN_MS);
            }

            function showWarning() {
                var warningDurationMs = LOGOUT_MS - WARN_MS; // 5 minutes
                var countdownEl = document.getElementById('sessionCountdownSecs');
                if (countdownEl) {
                    countdownEl.textContent = Math.round(warningDurationMs / 1000);
                }

                var modalEl = document.getElementById('sessionExpiryModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    modalInstance = new bootstrap.Modal(modalEl, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    modalInstance.show();
                }

                var deadlineMs = Date.now() + warningDurationMs;

                clearInterval(countdownInterval);
                countdownInterval = setInterval(function () {
                    var left = Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
                    if (countdownEl) {
                        countdownEl.textContent = left;
                    }
                    if (left <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = LOGOUT_URL;
                    }
                }, 1000);

                clearTimeout(logoutTimer);
                logoutTimer = setTimeout(function () {
                    window.location.href = LOGOUT_URL;
                }, warningDurationMs);
            }

            function stayLoggedIn() {
                if (isKeepalivePending) {
                    return;
                }

                var btn = document.getElementById('sessionStayLoggedInBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                }

                // Keep the warning modal/countdown active until the server confirms
                // the keepalive write, so a rapid refresh cannot race and log out.
                if (typeof window.fetch !== 'function') {
                    clearInterval(countdownInterval);
                    clearTimeout(logoutTimer);
                    if (modalInstance) {
                        modalInstance.hide();
                        modalInstance = null;
                    }
                    resetTimer();
                    if (btn) {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                    }
                    return;
                }

                isKeepalivePending = true;

                var keepaliveUrl = KEEPALIVE_URL + (KEEPALIVE_URL.indexOf('?') === -1 ? '?' : '&') + '_ts=' + Date.now();

                window.fetch(keepaliveUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('keepalive_http_' + response.status);
                        }
                        return response.json().catch(function () {
                            return {};
                        });
                    })
                    .then(function (payload) {
                        if (!payload || payload.success !== true) {
                            throw new Error('keepalive_payload_invalid');
                        }

                        clearInterval(countdownInterval);
                        clearTimeout(logoutTimer);

                        if (modalInstance) {
                            modalInstance.hide();
                            modalInstance = null;
                        }

                        resetTimer();
                    })
                    .catch(function () {
                        window.location.href = '<?= base_url('login') ?>';
                    })
                    .finally(function () {
                        isKeepalivePending = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.removeAttribute('aria-busy');
                        }
                    });
            }

            // Attach Stay Logged In button after DOM is ready.
            document.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('sessionStayLoggedInBtn');
                if (btn) {
                    btn.addEventListener('click', stayLoggedIn);
                }
            });

            // Any user interaction resets the idle timer.
            ['click', 'keydown', 'mousemove', 'touchstart', 'scroll'].forEach(function (evt) {
                document.addEventListener(evt, resetTimer, {
                    passive: true
                });
            });

            // Kick off the timer.
            resetTimer();
        }());
    </script>

    <!-- ===== Session Expiry Warning Modal ===== -->
    <div class="modal fade" id="sessionExpiryModal" tabindex="-1" aria-labelledby="sessionExpiryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sessionExpiryModalLabel">
                        <i class="bi bi-clock-history me-2" aria-hidden="true"></i><?= lang('App.sessionExpiringSoon') ?>
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><?= lang('App.sessionExpiryBody') ?></p>
                    <p class="mb-0 text-muted small"><?= lang('App.sessionExpirySignedOut') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="sessionStayLoggedInBtn" class="btn btn-primary">
                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= lang('App.sessionStayLoggedIn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?= $this->renderSection('modals') ?>
    <?= $this->renderSection('scripts') ?>
</body>

</html>