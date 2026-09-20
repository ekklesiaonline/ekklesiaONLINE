<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Load Shield authentication routes except register (overridden below).
service('auth')->routes($routes, ['except' => ['register']]);

// Backward-compatible password recovery aliases.
$routes->addRedirect('forgot-password', 'login/magic-link');
$routes->addRedirect('reset-password', 'login/magic-link');
// Legacy URL redirects for the old "churches" path.
$routes->addRedirect('churches', 'parish_list');
$routes->addRedirect('churches/(:any)', 'parish_list/$1');
// Canonical parish-list URLs.
$routes->addRedirect('parishes', 'parish_list');
$routes->addRedirect('parishes/(:any)', 'parish_list/$1');
$routes->addRedirect('parish', 'parish_list');
$routes->addRedirect('parish/(:any)', 'parish_list/$1');

// Public media endpoints
$routes->get('health', '\\App\\Controllers\\Health::index');
$routes->get('health/ready', '\\App\\Controllers\\Health::ready', ['filter' => ['session', 'permission:system.admin']]);
$routes->get('media/event-cover/(:num)', '\\App\\Controllers\\Media::eventCover/$1');

// Force-reset set-password page (session required; excluded from ForcePasswordResetFilter).
$routes->get('auth/set-password', '\\App\\Controllers\\Auth\\SetPasswordController::show');
$routes->post('auth/set-password', '\\App\\Controllers\\Auth\\SetPasswordController::save');

// Explicit register routes: keep named GET route and custom POST validation flow.
$routes->get('register', '\\App\\Controllers\\Auth\\RegisterController::registerView', ['as' => 'register']);
$routes->post('register', '\\App\\Controllers\\Auth\\RegisterController::registerAction');

// Load role-based module routes
if (is_file(ROOTPATH . 'Province/Config/Routes.php')) {
    require ROOTPATH . 'Province/Config/Routes.php';
}
if (is_file(ROOTPATH . 'Diocesan/Config/Routes.php')) {
    require ROOTPATH . 'Diocesan/Config/Routes.php';
}
if (is_file(ROOTPATH . 'Clergy/Config/Routes.php')) {
    require ROOTPATH . 'Clergy/Config/Routes.php';
}
if (is_file(ROOTPATH . 'Worship/Config/Routes.php')) {
    require ROOTPATH . 'Worship/Config/Routes.php';
}
if (is_file(ROOTPATH . 'Treasurer/Config/Routes.php')) {
    require ROOTPATH . 'Treasurer/Config/Routes.php';
}
if (is_file(ROOTPATH . 'Volunteer/Config/Routes.php')) {
    require ROOTPATH . 'Volunteer/Config/Routes.php';
}
if (is_file(ROOTPATH . 'MemberPortal/Config/Routes.php')) {
    require ROOTPATH . 'MemberPortal/Config/Routes.php';
}

$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('parish_list', function ($routes) {
        $routes->get('/', '\\Diocesan\\Controllers\\Parishes::index');
        $routes->get('create', '\\Diocesan\\Controllers\\Parishes::create');
        $routes->post('store', '\\Diocesan\\Controllers\\Parishes::store');
        $routes->get('edit/(:num)', '\\Diocesan\\Controllers\\Parishes::edit/$1');
        $routes->post('update/(:num)', '\\Diocesan\\Controllers\\Parishes::update/$1');
        $routes->get('delete-impact/(:num)', '\\Diocesan\\Controllers\\Parishes::deleteImpact/$1');
        $routes->post('delete/(:num)', '\\Diocesan\\Controllers\\Parishes::delete/$1');
        $routes->post('bulk-delete', '\\Diocesan\\Controllers\\Parishes::bulkDelete');
        $routes->get('(:num)/staff', '\\Diocesan\\Controllers\\Parishes::staff/$1');
        $routes->post('(:num)/staff/add', '\\Diocesan\\Controllers\\Parishes::addStaff/$1');
        $routes->post('(:num)/staff/promote', '\\Diocesan\\Controllers\\Parishes::promoteMember/$1');
        $routes->post('(:num)/staff/remove-role', '\\Diocesan\\Controllers\\Parishes::removeStaffRole/$1');
        $routes->post('(:num)/staff/demote', '\\Diocesan\\Controllers\\Parishes::demoteStaff/$1');
    });

    $routes->group('system', ['filter' => 'permission:system.admin'], function ($routes) {
        $routes->get('dashboard', '\\App\\Controllers\\SystemDashboard::index');

        $routes->get('overview', '\\App\\Controllers\\SystemOverview::index');
        $routes->get('mission-outcomes', '\\App\\Controllers\\SystemMissionOutcomes::index');
        $routes->get('liturgical-source', '\\Diocesan\\Controllers\\LiturgicalCalendar::index');
        $routes->post('liturgical-source/store', '\\Diocesan\\Controllers\\LiturgicalCalendar::store');
        $routes->get('liturgical-source/edit/(:num)', '\\Diocesan\\Controllers\\LiturgicalCalendar::edit/$1');
        $routes->post('liturgical-source/update/(:num)', '\\Diocesan\\Controllers\\LiturgicalCalendar::update/$1');
        $routes->post('liturgical-source/delete/(:num)', '\\Diocesan\\Controllers\\LiturgicalCalendar::delete/$1');
        $routes->post('provinces/store', '\\App\\Controllers\\SystemProvinces::storeProvince');
        $routes->post('provinces/update/(:num)', '\\App\\Controllers\\SystemProvinces::updateProvince/$1');
        $routes->post('provinces/delete/(:num)', '\\App\\Controllers\\SystemProvinces::deleteProvince/$1');
        $routes->post('dioceses/store', '\\App\\Controllers\\SystemDioceses::storeDiocese');
        $routes->post('dioceses/update/(:num)', '\\App\\Controllers\\SystemDioceses::updateDiocese/$1');
        $routes->post('dioceses/delete/(:num)', '\\App\\Controllers\\SystemDioceses::deleteDiocese/$1');
        // Admin creation endpoints split by role to remove client-tamperable role_group.
        $routes->post('admins/store-system', '\\App\\Controllers\\SystemAdminUsers::storeSystemAdmin');
        $routes->post('admins/store-province', '\\App\\Controllers\\SystemAdminUsers::storeProvinceAdmin');
        $routes->post('admins/store-diocese', '\\App\\Controllers\\SystemAdminUsers::storeDioceseAdmin');
        $routes->post('admins/update/(:num)', '\\App\\Controllers\\SystemAdminUsers::updateAdminUser/$1');
        $routes->post('admins/delete/(:num)', '\\App\\Controllers\\SystemAdminUsers::deleteAdminUser/$1');
        $routes->post('admins/transfer-primary/(:num)', '\\App\\Controllers\\SystemPrimary::transferPrimarySystemAdmin/$1');
        $routes->post('admins/reset-password/(:num)', '\\App\\Controllers\\SystemAdminUsers::resetPassword/$1');
        $routes->post('admins/permissions/(:num)', '\\App\\Controllers\\SystemAdminUsers::updateAdminPermissions/$1');
        $routes->post('admins/resend-invitation/(:num)', '\\App\\Controllers\\SystemAdminUsers::resendAdminInvitation/$1');
        $routes->post('admins/grant-diocese-primary/(:num)', '\\App\\Controllers\\SystemAdminUsers::grantDiocesePrimary/$1');
        $routes->post('admins/grant-province-primary/(:num)', '\\App\\Controllers\\SystemAdminUsers::grantProvincePrimary/$1');
        $routes->post('admins/grant-parish-primary/(:num)', '\\App\\Controllers\\SystemAdminUsers::grantParishPrimary/$1');
        $routes->get('system-admins', '\\App\\Controllers\\SystemAdminUsers::listSystemAdmins');
        $routes->get('province-admins', '\\App\\Controllers\\SystemAdminUsers::listProvinceAdmins');
        $routes->get('diocese-admins', '\\App\\Controllers\\SystemAdminUsers::listDioceseAdmins');

        $routes->get('settings', '\\App\\Controllers\\SystemSettings::index');
        $routes->post('settings/update', '\\App\\Controllers\\SystemSettings::update');
        $routes->get('audit', '\\App\\Controllers\\SystemAudit::index');
        $routes->get('audit/data', '\\App\\Controllers\\SystemAudit::data');

        $routes->get('event-calendars', '\\App\\Controllers\\SystemEventCalendars::index');
        $routes->get('event-calendars/create', '\\App\\Controllers\\SystemEventCalendars::create');
        $routes->post('event-calendars/store', '\\App\\Controllers\\SystemEventCalendars::store');
        $routes->get('event-calendars/edit/(:num)', '\\App\\Controllers\\SystemEventCalendars::edit/$1');
        $routes->post('event-calendars/update/(:num)', '\\App\\Controllers\\SystemEventCalendars::update/$1');
        $routes->post('event-calendars/delete/(:num)', '\\App\\Controllers\\SystemEventCalendars::delete/$1');

        $routes->group('events', function ($routes) {
            $routes->get('/', '\\App\\Controllers\\SystemEvents::index');
            $routes->get('calendar', '\\App\\Controllers\\SystemEvents::calendar');
            $routes->get('view/(:num)', '\\App\\Controllers\\SystemEvents::view/$1');
            $routes->get('create', '\\App\\Controllers\\SystemEvents::create');
            $routes->post('store', '\\App\\Controllers\\SystemEvents::store');
            $routes->get('edit/(:num)', '\\App\\Controllers\\SystemEvents::edit/$1');
            $routes->post('update/(:num)', '\\App\\Controllers\\SystemEvents::update/$1');
            $routes->post('delete/(:num)', '\\App\\Controllers\\SystemEvents::delete/$1');
        });
    });

    $routes->group('attendance', ['filter' => 'session'], function ($routes) {
        $routes->match(['GET', 'POST'], 'checkin', '\App\Controllers\AttendanceController::checkin');
        $routes->get('manual', '\App\Controllers\AttendanceController::manual');
        $routes->post('bulk', '\App\Controllers\AttendanceController::bulk');
        $routes->post('update/(:num)', '\App\Controllers\AttendanceController::update/$1');
        $routes->post('delete/(:num)', '\App\Controllers\AttendanceController::delete/$1');
        $routes->get('search-members', '\App\Controllers\AttendanceController::searchMembers');
        $routes->get('recent/(:num)', '\App\Controllers\AttendanceController::recent/$1');
    });

    // ServiceAttendanceController attendance form/save (new system)
    $routes->group('attendance', ['filter' => 'permission:attendance.view'], function ($routes) {
        $routes->get('form/(:num)', '\App\Controllers\ServiceAttendanceController::attendanceForm/$1');
        $routes->post('save/(:num)', '\App\Controllers\ServiceAttendanceController::saveAttendance/$1');
    });
});

// Public routes

// Digital connection card – accessible without authentication (token-gated per parish)
$routes->get('connect/(:alphanum)', '\\Clergy\\Controllers\\Import::cardForm/$1');
$routes->post('connect/(:alphanum)', '\\Clergy\\Controllers\\Import::digitalCard/$1');
