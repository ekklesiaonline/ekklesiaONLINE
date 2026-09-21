<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Province module routes
 * Permission guard: permission:province.admin
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('province', ['filter' => 'permission:province.admin'], function ($routes) {
        $routes->get('dashboard', '\\Province\\Controllers\\Dashboard::index');
        $routes->get('events', '\\Province\\Controllers\\Events::index');
        $routes->get('health', '\\Province\\Controllers\\ProvinceOversight::health');
        $routes->get('directory', '\\Province\\Controllers\\ProvinceOversight::directory');
        $routes->get('events/calendar', '\\Province\\Controllers\\Events::calendar');
        $routes->get('events/create', '\\Province\\Controllers\\Events::create', ['filter' => 'permission:province.event.create']);
        $routes->post('events/store', '\\Province\\Controllers\\Events::store', ['filter' => 'permission:province.event.create']);
        $routes->get('events/edit/(:num)', '\\Province\\Controllers\\Events::edit/$1', ['filter' => 'permission:province.event.edit']);
        $routes->post('events/update/(:num)', '\\Province\\Controllers\\Events::update/$1', ['filter' => 'permission:province.event.edit']);
        $routes->post('events/delete/(:num)', '\\Province\\Controllers\\Events::delete/$1', ['filter' => 'permission:province.event.delete']);
        $routes->get('event-calendars', '\\Province\\Controllers\\EventCalendars::index', ['filter' => 'permission:province.calendar.manage']);
        $routes->get('event-calendars/create', '\\Province\\Controllers\\EventCalendars::create', ['filter' => 'permission:province.calendar.manage']);
        $routes->post('event-calendars/store', '\\Province\\Controllers\\EventCalendars::store', ['filter' => 'permission:province.calendar.manage']);
        $routes->get('event-calendars/edit/(:num)', '\\Province\\Controllers\\EventCalendars::edit/$1', ['filter' => 'permission:province.calendar.manage']);
        $routes->post('event-calendars/update/(:num)', '\\Province\\Controllers\\EventCalendars::update/$1', ['filter' => 'permission:province.calendar.manage']);
        $routes->post('event-calendars/delete/(:num)', '\\Province\\Controllers\\EventCalendars::delete/$1', ['filter' => 'permission:province.calendar.manage']);

        // Legacy redirects/mappings (old /province/calendar/* URLs)
        $routes->get('calendar', static fn() => redirect()->to(base_url('province/events/calendar')));
        $routes->get('calendar/create', static fn() => redirect()->to(base_url('province/events/create')));
        $routes->post('calendar/create', '\\Province\\Controllers\\Events::store');
        $routes->get('calendar/edit/(:num)', static fn($id) => redirect()->to(base_url('province/events/edit/' . $id)));
        $routes->post('calendar/edit/(:num)', '\\Province\\Controllers\\Events::update/$1');
        $routes->post('calendar/delete/(:num)', '\\Province\\Controllers\\Events::delete/$1');
        $routes->get('attendance-period-breakdown', '\\Province\\Controllers\\Dashboard::attendancePeriodBreakdown');
        $routes->get('report-export', '\\Province\\Controllers\\Dashboard::exportReport', ['filter' => 'permission:report.export']);
        // Diocese admins are read-only for province admins; creation is centralized under system admins.
        $routes->get('diocese-admins', '\\App\\Controllers\\ProvinceDioceseAdmins::index', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->get('diocese-admins/reactivate/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::reactivate/$1', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('diocese-admins/store', '\\App\\Controllers\\ProvinceDioceseAdmins::store', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('diocese-admins/update/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::update/$1', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('diocese-admins/reset-password/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::resetPassword/$1', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('diocese-admins/resend-invitation/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::resendInvitation/$1', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('diocese-admins/deactivate/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::deactivate/$1', ['filter' => 'permission:province.diocese_admin.manage']);
        $routes->post('quick-create-parish', '\\Province\\Controllers\\Dashboard::quickCreateParish');
        $routes->post('quick-create-parish', '\\Province\\Controllers\\Dashboard::quickCreateParish');

        // Email settings
        $routes->get('email-settings', '\\App\\Controllers\\Province\\EmailSettingsController::index');
        $routes->post('email-settings', '\\App\\Controllers\\Province\\EmailSettingsController::save');
        $routes->post('email-settings/test', '\\App\\Controllers\\Province\\EmailSettingsController::test');
    });
});
