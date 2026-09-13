<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Diocesan module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('chart-accounts', ['filter' => 'permission:diocese.admin'], function ($routes) {
        $routes->get('/', '\\Diocesan\\Controllers\\ChartOfAccounts::index');
        $routes->get('create', '\\Diocesan\\Controllers\\ChartOfAccounts::create');
        $routes->post('store', '\\Diocesan\\Controllers\\ChartOfAccounts::store');
        $routes->get('edit/(:num)', '\\Diocesan\\Controllers\\ChartOfAccounts::edit/$1');
        $routes->post('update/(:num)', '\\Diocesan\\Controllers\\ChartOfAccounts::update/$1');
        $routes->post('toggle/(:num)', '\\Diocesan\\Controllers\\ChartOfAccounts::toggle/$1');
    });

    $routes->group('liturgical-source', function ($routes) {
        $routes->get('/', '\\Diocesan\\Controllers\\LiturgicalCalendar::index');
    });

    $routes->group('diocese', ['filter' => 'permission:diocese.admin'], function ($routes) {
        $routes->get('dashboard', '\\Diocesan\\Controllers\\Dashboard::index');
        $routes->get('dashboard/recent-activity', '\\Diocesan\\Controllers\\Dashboard::recentActivity');
        $routes->get('dashboard/switch/(:num)', '\\Diocesan\\Controllers\\Dashboard::switchParish/$1');
        $routes->get('attendance-period-breakdown', '\\Diocesan\\Controllers\\Dashboard::attendancePeriodBreakdown');

        $routes->group('admins', ['filter' => 'permission:diocese.primary'], function ($routes) {
            $routes->get('/', '\\App\\Controllers\\DioceseAdminUsers::index');
            $routes->post('store', '\\App\\Controllers\\DioceseAdminUsers::store');
            $routes->post('update/(:num)', '\\App\\Controllers\\DioceseAdminUsers::update/$1');
            $routes->post('reset-password/(:num)', '\\App\\Controllers\\DioceseAdminUsers::resetPassword/$1');
            $routes->post('delete/(:num)', '\\App\\Controllers\\DioceseAdminUsers::delete/$1');
            $routes->post('deactivate/(:num)', '\\App\\Controllers\\DioceseAdminUsers::deactivate/$1');
            $routes->post('reactivate/(:num)', '\\App\\Controllers\\DioceseAdminUsers::reactivate/$1');
        });

        $routes->group('parish-admins', ['filter' => 'permission:diocese.primary'], function ($routes) {
            $routes->get('/', '\\App\\Controllers\\DioceseParishAdmins::index');
            $routes->post('store', '\\App\\Controllers\\DioceseParishAdmins::store');
            $routes->post('update/(:num)', '\\App\\Controllers\\DioceseParishAdmins::update/$1');
            $routes->post('reset-password/(:num)', '\\App\\Controllers\\DioceseParishAdmins::resetPassword/$1');
            $routes->post('delete/(:num)', '\\App\\Controllers\\DioceseParishAdmins::delete/$1');
            $routes->post('deactivate/(:num)', '\\App\\Controllers\\DioceseParishAdmins::deactivate/$1');
            $routes->post('reactivate/(:num)', '\\App\\Controllers\\DioceseParishAdmins::reactivate/$1');
        });
    });
});
