<!-- Sidebar -->
<nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
    <div class="position-sticky">
        <h4 class="text-center mb-4">
            <i class="bi bi-parish" aria-hidden="true"></i> EkklesiaRD
        </h4>

        <?php $isProvinceAdminSidebar = auth()->user()->inGroup('provinceadmin') || auth()->user()->can('province.admin'); ?>
        <?php $isDioceseAdminSidebar = auth()->user()->inGroup('dioceseadmin') || auth()->user()->can('diocese.admin'); ?>
        <?php $isSystemAdminSidebar = auth()->user()->inGroup('systemadmin'); ?>
        <?php
        $profileNavUrl = (string) ($profile_nav_url ?? 'profile');
        $profilePending = (bool) ($profile_pending ?? false);
        ?>
        <?php
        $isParishAdminSidebar = auth()->user()->inGroup('parishadmin');
        $isTreasurerSidebar = auth()->user()->inGroup('treasurer');
        $isClergySidebar = auth()->user()->inGroup('clergy');
        $isVolunteerCoordSidebar = auth()->user()->inGroup('volunteer_coordinator');
        $isSecretarySidebar = auth()->user()->inGroup('secretary');
        $isVestrySidebar = auth()->user()->inGroup('vestry_member', 'warden', 'clerk');
        $isParishTier = !$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar
            && ($isParishAdminSidebar || $isTreasurerSidebar || $isClergySidebar || $isVolunteerCoordSidebar || $isSecretarySidebar || $isVestrySidebar);

        $dashboardHref = 'me';
        $dashboardActive = (uri_string() == 'me');
        if ($isSystemAdminSidebar) {
            $dashboardHref = 'system/dashboard';
            $dashboardActive = (uri_string() == 'system/dashboard');
        } elseif ($isProvinceAdminSidebar) {
            $dashboardHref = 'province/dashboard';
            $dashboardActive = (uri_string() == 'province/dashboard');
        } elseif ($isDioceseAdminSidebar) {
            $dashboardHref = 'diocese/dashboard';
            $dashboardActive = str_starts_with(uri_string(), 'diocese/dashboard');
        } elseif ($isParishAdminSidebar) {
            $dashboardHref = 'parish/dashboard';
            $dashboardActive = (uri_string() == 'parish/dashboard');
        } elseif ($isTreasurerSidebar) {
            $dashboardHref = 'parish/finance';
            $dashboardActive = (uri_string() == 'parish/finance');
        } elseif ($isSecretarySidebar) {
            $dashboardHref = 'parish/secretary';
            $dashboardActive = (uri_string() == 'parish/secretary');
        } elseif ($isVestrySidebar) {
            $dashboardHref = 'parish/governance';
            $dashboardActive = (uri_string() == 'parish/governance');
        } elseif ($isClergySidebar) {
            $dashboardHref = 'parish/worship';
            $dashboardActive = (uri_string() == 'parish/worship');
        } elseif ($isVolunteerCoordSidebar) {
            $dashboardHref = 'parish/volunteers';
            $dashboardActive = (uri_string() == 'parish/volunteers');
        }
        ?>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $dashboardActive ? 'active' : '' ?>" href="<?= base_url($dashboardHref) ?>">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i> <?= lang('App.navDashboard') ?>
                </a>
            </li>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() == 'me') ? 'active' : '' ?>" href="<?= base_url('me') ?>">
                        <i class="bi bi-person-circle" aria-hidden="true"></i> <?= lang('Dashboard.myDashboard') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isParishTier && $isParishAdminSidebar && !$isTreasurerSidebar && !$isClergySidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() == 'parish/finance') ? 'active' : '' ?>" href="<?= base_url('parish/finance') ?>">
                        <i class="bi bi-cash-coin" aria-hidden="true"></i> <?= lang('Dashboard.parishFinanceDashboard') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isParishTier && $isParishAdminSidebar && !$isClergySidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() == 'parish/worship') ? 'active' : '' ?>" href="<?= base_url('parish/worship') ?>">
                        <i class="bi bi-bible" aria-hidden="true"></i> <?= lang('Dashboard.parishWorshipDashboard') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isParishTier && $isParishAdminSidebar && !$isVestrySidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() == 'parish/governance') ? 'active' : '' ?>" href="<?= base_url('parish/governance') ?>">
                        <i class="bi bi-people-fill" aria-hidden="true"></i> <?= lang('Dashboard.parishGovernanceDashboard') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isParishTier && $isParishAdminSidebar && !$isVolunteerCoordSidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() == 'parish/volunteers') ? 'active' : '' ?>" href="<?= base_url('parish/volunteers') ?>">
                        <i class="bi bi-people" aria-hidden="true"></i> <?= lang('Dashboard.parishVolunteerDashboard') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isSystemAdminSidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/overview') ? 'active' : '' ?>" href="<?= base_url('system/overview') ?>">
                        <i class="bi bi-speedometer" aria-hidden="true"></i> <?= lang('App.navSystemOverview') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/settings') ? 'active' : '' ?>" href="<?= base_url('system/settings') ?>">
                        <i class="bi bi-gear" aria-hidden="true"></i> <?= lang('System.sectionSettings') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/system-admins') ? 'active' : '' ?>" href="<?= base_url('system/system-admins') ?>">
                        <i class="bi bi-person-lock" aria-hidden="true"></i> <?= lang('App.navSystemAdmins') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/province-admins') ? 'active' : '' ?>" href="<?= base_url('system/province-admins') ?>">
                        <i class="bi bi-person-badge" aria-hidden="true"></i> <?= lang('App.navProvinceAdmins') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/diocese-admins') ? 'active' : '' ?>" href="<?= base_url('system/diocese-admins') ?>">
                        <i class="bi bi-person-badge-fill" aria-hidden="true"></i> <?= lang('App.navDioceseAdmins') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/mission-outcomes') ? 'active' : '' ?>" href="<?= base_url('system/mission-outcomes') ?>">
                        <i class="bi bi-graph-up-arrow" aria-hidden="true"></i> <?= lang('App.navMissionOutcomes') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/liturgical-source') ? 'active' : '' ?>" href="<?= base_url('system/liturgical-source') ?>">
                        <i class="bi bi-journal-bookmark" aria-hidden="true"></i> <?= lang('App.navLiturgicalSource') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'system/event-calendars') ? 'active' : '' ?>" href="<?= base_url('system/event-calendars') ?>">
                        <i class="bi bi-palette" aria-hidden="true"></i> <?= lang('App.navCalendars') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with(uri_string(), 'system/events') ? 'active' : '' ?>" href="<?= base_url('system/events') ?>">
                        <i class="bi bi-calendar2-event" aria-hidden="true"></i> <?= lang('App.navSystemEvents') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= uri_string() === 'system/events/calendar' ? 'active' : '' ?>" href="<?= base_url('system/events/calendar') ?>">
                        <i class="bi bi-calendar3" aria-hidden="true"></i> <?= lang('App.navSystemCalendar') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (uri_string() === 'parish_list') ? 'active' : '' ?>" href="<?= base_url('parish_list') ?>">
                        <i class="bi bi-building" aria-hidden="true"></i> <?= lang('App.navParishList') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isProvinceAdminSidebar && !$isSystemAdminSidebar): ?>
                <li class="nav-item"><a class="nav-link <?= str_starts_with(uri_string(), 'province/events/calendar') ? 'active' : '' ?>" href="<?= base_url('province/events/calendar') ?>"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= lang('App.navProvinceCalendar') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= uri_string() === 'province/events' ? 'active' : '' ?>" href="<?= base_url('province/events') ?>"><i class="bi bi-calendar2-event" aria-hidden="true"></i> <?= lang('App.navProvinceEvents') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= str_starts_with(uri_string(), 'province/health') ? 'active' : '' ?>" href="<?= base_url('province/health') ?>"><i class="bi bi-heart-pulse" aria-hidden="true"></i> <?= lang('App.navProvinceHealth') ?></a></li>
                <li class="nav-item"><a class="nav-link <?= str_starts_with(uri_string(), 'province/directory') ? 'active' : '' ?>" href="<?= base_url('province/directory') ?>"><i class="bi bi-diagram-3" aria-hidden="true"></i> <?= lang('App.navProvinceDirectory') ?></a></li>
                <?php if (auth()->user()->can('province.diocese_admin.manage')): ?>
                    <li class="nav-item"><a class="nav-link <?= (uri_string() === 'province/diocese-admins') ? 'active' : '' ?>" href="<?= base_url('province/diocese-admins') ?>"><i class="bi bi-person-badge-fill" aria-hidden="true"></i> <?= lang('App.navDioceseAdmins') ?></a></li>
                <?php endif; ?>
                <?php if (auth()->user()->can('report.export')): ?>
                    <li class="nav-item"><a class="nav-link <?= (uri_string() === 'province/report-export') ? 'active' : '' ?>" href="<?= base_url('province/report-export') ?>"><i class="bi bi-download" aria-hidden="true"></i> <?= lang('App.navProvinceReports') ?></a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && auth()->user()->can('parishes.edit')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'parish_list')) ? 'active' : '' ?>" href="<?= base_url('parish_list') ?>">
                        <i class="bi bi-building" aria-hidden="true"></i> <?= lang('App.navParishList') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isDioceseAdminSidebar && !$isSystemAdminSidebar): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'chart-accounts')) ? 'active' : '' ?>" href="<?= base_url('chart-accounts') ?>">
                        <i class="bi bi-diagram-3" aria-hidden="true"></i> <?= lang('App.navChartOfAccounts') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isDioceseAdminSidebar && !$isSystemAdminSidebar && auth()->user()->can('diocese.primary')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'diocese/admins')) ? 'active' : '' ?>" href="<?= base_url('diocese/admins') ?>">
                        <i class="bi bi-person-badge-fill" aria-hidden="true"></i> <?= lang('App.navDioceseAdmins') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'diocese/parish-admins')) ? 'active' : '' ?>" href="<?= base_url('diocese/parish-admins') ?>">
                        <i class="bi bi-people-fill" aria-hidden="true"></i> <?= lang('App.navParishAdmins') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('member.view')): ?>
                <?php if (!$isProvinceAdminSidebar): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (str_starts_with(uri_string(), 'people')) ? 'active' : '' ?>" href="<?= base_url('people') ?>">
                            <i class="bi bi-people-fill" aria-hidden="true"></i> <?= lang('App.navPeople') ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'members') && !str_starts_with(uri_string(), 'members/dedup')) ? 'active' : '' ?>" href="<?= base_url('members') ?>">
                        <i class="bi bi-people" aria-hidden="true"></i> <?= $isProvinceAdminSidebar ? lang('App.navStaff') : lang('App.navMembers') ?>
                    </a>
                </li>
                <?php if (!$isProvinceAdminSidebar && auth()->user()->can('member.delete')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= str_starts_with(uri_string(), 'members/dedup') ? 'active' : '' ?>" href="<?= base_url('members/dedup') ?>">
                            <i class="bi bi-person-exclamation" aria-hidden="true"></i> <?= lang('Members.navDedup') ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('family.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'families')) ? 'active' : '' ?>" href="<?= base_url('families') ?>">
                        <i class="bi bi-house-heart" aria-hidden="true"></i> <?= lang('App.navFamilies') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('service.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'services')) ? 'active' : '' ?>" href="<?= base_url('services') ?>">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i> <?= lang('App.navServices') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && ($isDioceseAdminSidebar || $isProvinceAdminSidebar)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'liturgical-source')) ? 'active' : '' ?>" href="<?= base_url('liturgical-source') ?>">
                        <i class="bi bi-journal-bookmark" aria-hidden="true"></i> <?= lang('App.navLiturgicalSource') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && auth()->user()->can('attendance.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'attendance')) ? 'active' : '' ?>" href="<?= base_url('attendance/reports') ?>">
                        <i class="bi bi-clipboard-check" aria-hidden="true"></i> <?= lang('App.navAttendance') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('guest.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'guests')) ? 'active' : '' ?>" href="<?= base_url('guests/dashboard') ?>">
                        <i class="bi bi-house-heart" aria-hidden="true"></i> <?= lang('App.navGuestHub') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('member.edit')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'leadership')) ? 'active' : '' ?>" href="<?= base_url('leadership') ?>">
                        <i class="bi bi-person-badge" aria-hidden="true"></i> <?= lang('App.navLeadership') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'standing')) ? 'active' : '' ?>" href="<?= base_url('standing') ?>">
                        <i class="bi bi-award" aria-hidden="true"></i> <?= lang('App.navGoodStanding') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && (auth()->user()->can('donation.view') || auth()->user()->can('expense.view') || auth()->user()->can('finance.view') || auth()->user()->can('finance.manage'))): ?>
                <?php $isFinanceSectionActive = (str_starts_with(uri_string(), 'donations') || str_starts_with(uri_string(), 'expenses') || str_starts_with(uri_string(), 'budgets') || str_starts_with(uri_string(), 'pledges') || str_starts_with(uri_string(), 'banking')); ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isFinanceSectionActive ? 'active' : '' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#financeMenu">
                        <i class="bi bi-cash-coin" aria-hidden="true"></i> <?= lang('App.navFinance') ?>
                    </a>
                    <ul class="collapse nav flex-column ms-3 <?= $isFinanceSectionActive ? 'show' : '' ?>" id="financeMenu">
                        <li><a class="nav-link <?= str_starts_with(uri_string(), 'donations') ? 'active' : '' ?>" href="<?= base_url('donations') ?>"><?= lang('App.navDonations') ?></a></li>
                        <li><a class="nav-link <?= str_starts_with(uri_string(), 'expenses') ? 'active' : '' ?>" href="<?= base_url('expenses') ?>"><?= lang('App.navExpenses') ?></a></li>
                        <li><a class="nav-link <?= str_starts_with(uri_string(), 'budgets') ? 'active' : '' ?>" href="<?= base_url('budgets') ?>"><?= lang('App.navBudgets') ?></a></li>
                        <li><a class="nav-link <?= str_starts_with(uri_string(), 'banking') ? 'active' : '' ?>" href="<?= base_url('banking') ?>"><?= lang('App.navBanking') ?></a></li>
                        <?php if (auth()->user()->can('finance.manage')): ?>
                            <li><a class="nav-link <?= (str_starts_with(uri_string(), 'pledges')) ? 'active' : '' ?>" href="<?= base_url('pledges') ?>"><?= lang('App.navPledges') ?></a></li>
                        <?php endif; ?>

                    </ul>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && auth()->user()->can('volunteer.manage')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'volunteers')) ? 'active' : '' ?>" href="<?= base_url('volunteers') ?>">
                        <i class="bi bi-person-badge" aria-hidden="true"></i> <?= lang('App.navVolunteers') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && auth()->user()->can('volunteer.schedule')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'volunteers/schedules') || str_starts_with(uri_string(), 'volunteers/schedule') || str_starts_with(uri_string(), 'volunteers/create-schedule') || str_starts_with(uri_string(), 'volunteers/edit-schedule')) ? 'active' : '' ?>" href="<?= base_url('volunteers/schedules') ?>">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i> <?= lang('App.navVolunteerSchedules') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && auth()->user()->can('event.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'events')) ? 'active' : '' ?>" href="<?= base_url('events') ?>">
                        <i class="bi bi-calendar2-event" aria-hidden="true"></i> <?= lang('App.navEvents') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && auth()->user()->can('worship.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'worship-planning')) ? 'active' : '' ?>" href="<?= base_url('worship-planning') ?>">
                        <i class="bi bi-music-note-beamed" aria-hidden="true"></i> <?= lang('App.navWorshipPlanning') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && auth()->user()->can('report.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'reports')) ? 'active' : '' ?>" href="<?= base_url('reports') ?>">
                        <i class="bi bi-bar-chart" aria-hidden="true"></i> <?= lang('App.navReports') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (!$isSystemAdminSidebar && !$isProvinceAdminSidebar && !$isDioceseAdminSidebar && auth()->user()->can('report.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'parochial-report')) ? 'active' : '' ?>" href="<?= base_url('parochial-report') ?>">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= lang('App.navParochialReport') ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isSystemAdminSidebar || $isProvinceAdminSidebar || $isDioceseAdminSidebar || auth()->user()->inGroup('parishadmin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (str_starts_with(uri_string(), 'admin/audit-logs')) ? 'active' : '' ?>" href="<?= base_url('admin/audit-logs') ?>">
                        <i class="bi bi-journal-text" aria-hidden="true"></i> <?= lang('App.navAuditLogs') ?>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item mt-3 pt-3 border-top">
                <a class="nav-link <?= str_starts_with(uri_string(), 'profile') ? 'active' : '' ?>" href="<?= base_url($profileNavUrl) ?>">
                    <i class="bi bi-person-circle" aria-hidden="true"></i> <?= lang('App.navProfile') ?>
                    <?php if ($profilePending): ?>
                        <span class="badge border border-warning text-warning fw-normal ms-2"><?= lang('App.statusPending') ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= base_url('logout') ?>">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i> <?= lang('App.navLogout') ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
