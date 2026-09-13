<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Member portal routes
 *
 * @var RouteCollection $routes
 */
$routes->get('/', '\\MemberPortal\\Controllers\\PublicHome::index');
$routes->get('locale/set/(:segment)', '\\MemberPortal\\Controllers\\PublicLocale::set/$1');
$routes->get('uploads/(:any)', '\\MemberPortal\\Controllers\\PublicHome::serveUpload/$1');
$routes->get('session/keepalive', '\\MemberPortal\\Controllers\\SessionSecurity::keepalive');

// JSON contract endpoints: controller enforces auth/scope and always returns JSON.
$routes->post('families/addMemberNonPortal', '\\MemberPortal\\Controllers\\Family::addMemberNonPortal');
$routes->post('families/enablePortal/(:num)', '\\MemberPortal\\Controllers\\Family::enablePortal/$1');

$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->get('media/profile-picture/(:num)', '\\MemberPortal\\Controllers\\PublicHome::profilePicture/$1');
    $routes->get('media/church-logo/(:num)', '\\MemberPortal\\Controllers\\PublicHome::churchLogo/$1');
    $routes->get('media/parish-banner/(:num)', '\\MemberPortal\\Controllers\\PublicHome::parishBanner/$1');
    $routes->get('media/event-cover/(:num)', '\\MemberPortal\\Controllers\\PublicHome::eventCover/$1');
    $routes->get('media/event-attachment/(:num)', '\\MemberPortal\\Controllers\\PublicHome::eventAttachment/$1');

    $routes->get('session/device-verify', '\\MemberPortal\\Controllers\\SessionSecurity::deviceVerifyForm');
    $routes->post('session/device-verify', '\\MemberPortal\\Controllers\\SessionSecurity::deviceVerifySubmit');
    $routes->post('session/device-verify/resend', '\\MemberPortal\\Controllers\\SessionSecurity::deviceVerifyResend');

    $routes->get('dashboard', '\\MemberPortal\\Controllers\\Dashboard::index');
    $routes->get('dashboard/followup-stats', '\\MemberPortal\\Controllers\\Dashboard::followupStats');
    $routes->get('dashboard/switch/(:num)', '\\MemberPortal\\Controllers\\Dashboard::switchParish/$1');
    $routes->get('me', '\\MemberPortal\\Controllers\\MyDashboard::index');
    $routes->get('admin/audit-logs', '\\MemberPortal\\Controllers\\AuditLogs::index');
    $routes->get('admin/audit-logs/export-csv', '\\MemberPortal\\Controllers\\AuditLogs::exportCsv');
    $routes->get('admin/audit-logs/export-json', '\\MemberPortal\\Controllers\\AuditLogs::exportJson');
    $routes->get('admin/audit-logs/export-pdf', '\\MemberPortal\\Controllers\\AuditLogs::exportPdf');
    $routes->get('admin/audit-logs/actor-autocomplete', '\\MemberPortal\\Controllers\\AuditLogs::actorAutocomplete');
    $routes->get('admin/audit-logs/load-more', '\\MemberPortal\\Controllers\\AuditLogs::loadMore');
    $routes->get('admin/audit-logs/statistics', '\\MemberPortal\\Controllers\\AuditLogs::statistics');
    $routes->get('admin/audit-logs/realtime-updates', '\\MemberPortal\\Controllers\\AuditLogs::realtimeUpdates');

    $routes->group('member', ['filter' => 'session'], function ($routes) {
        $routes->get('dashboard', '\\MemberPortal\\Controllers\\Dashboard::index');
    });

    $routes->group('profile', ['filter' => 'session'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\Profile::index');
        $routes->get('edit', '\\MemberPortal\\Controllers\\Profile::edit', ['filter' => 'permission:profile.edit']);
        $routes->post('update', '\\MemberPortal\\Controllers\\Profile::update', ['filter' => 'permission:profile.edit']);
        $routes->post('delete-picture', '\\MemberPortal\\Controllers\\Profile::deletePicture');
        $routes->get('complete', '\\MemberPortal\\Controllers\\Profile::complete');
        $routes->post('complete', '\\MemberPortal\\Controllers\\Profile::completeSubmit');
        $routes->get('join-parish', '\\MemberPortal\\Controllers\\Profile::joinParish');
        $routes->post('join-parish', '\\MemberPortal\\Controllers\\Profile::joinParishSubmit');
    });

    $routes->group('families', ['filter' => 'permission:family.view'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\Family::index');
        $routes->get('view/(:num)', '\\MemberPortal\\Controllers\\Family::view/$1');
        $routes->get('create', '\\MemberPortal\\Controllers\\Family::create', ['filter' => 'permission:family.manage']);
        $routes->post('store', '\\MemberPortal\\Controllers\\Family::store', ['filter' => 'permission:family.manage']);
        $routes->get('edit/(:num)', '\\MemberPortal\\Controllers\\Family::edit/$1', ['filter' => 'permission:family.manage']);
        $routes->post('update/(:num)', '\\MemberPortal\\Controllers\\Family::update/$1', ['filter' => 'permission:family.manage']);
        $routes->post('delete/(:num)', '\\MemberPortal\\Controllers\\Family::delete/$1', ['filter' => 'permission:family.manage']);
        $routes->post('addMember', '\\MemberPortal\\Controllers\\Family::addMember', ['filter' => 'permission:family.manage']);
        $routes->post('removeMember/(:num)', '\\MemberPortal\\Controllers\\Family::removeMember/$1', ['filter' => 'permission:family.manage']);
        $routes->get('available-members/(:num)', '\\MemberPortal\\Controllers\\Family::availableMembers/$1', ['filter' => 'permission:family.manage']);
        $routes->get('available-members', '\\MemberPortal\\Controllers\\Family::availableMembers', ['filter' => 'permission:family.manage']);
    });

    $routes->group('pledges', ['filter' => 'permission:finance.manage'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\AnnualPledges::index');
        $routes->post('store', '\\MemberPortal\\Controllers\\AnnualPledges::store');
        $routes->post('update/(:num)', '\\MemberPortal\\Controllers\\AnnualPledges::update/$1');
        $routes->post('delete/(:num)', '\\MemberPortal\\Controllers\\AnnualPledges::delete/$1');
    });

    $routes->group('leadership', ['filter' => 'permission:member.edit'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\LeadershipPositions::index');
        $routes->post('store', '\\MemberPortal\\Controllers\\LeadershipPositions::store');
        $routes->post('update/(:num)', '\\MemberPortal\\Controllers\\LeadershipPositions::update/$1');
        $routes->post('delete/(:num)', '\\MemberPortal\\Controllers\\LeadershipPositions::delete/$1');
    });

    $routes->group('guest-conversions', ['filter' => 'permission:guest.edit'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\GuestConversions::index');
        $routes->post('convert/(:num)', '\\MemberPortal\\Controllers\\GuestConversions::convert/$1');
        $routes->post('revert/(:num)', '\\MemberPortal\\Controllers\\GuestConversions::revert/$1');
    });

    $routes->group('standing', ['filter' => 'permission:member.edit'], function ($routes) {
        $routes->get('/', '\\MemberPortal\\Controllers\\Standing::index');
        $routes->post('refresh', '\\MemberPortal\\Controllers\\Standing::refresh');
        $routes->get('api/summary', '\\MemberPortal\\Controllers\\Standing::summaryApi');
    });
});
