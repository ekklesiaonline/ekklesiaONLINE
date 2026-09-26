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

        // Diocese-scoped follow-up sequences/templates (clone cascade source)
        $routes->group('followup-workflow', function ($routes) {
            $routes->get('sequences', '\\Diocesan\\Controllers\\FollowupWorkflow::sequences');
            $routes->get('sequences/create', '\\Diocesan\\Controllers\\FollowupWorkflow::edit_sequence/0');
            $routes->get('sequences/edit/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::edit_sequence/$1');
            $routes->post('sequences/save', '\\Diocesan\\Controllers\\FollowupWorkflow::saveSequence/0');
            $routes->post('sequences/save/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::saveSequence/$1');
            $routes->post('sequences/toggle/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::toggleSequence/$1');
            $routes->post('sequences/delete/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::deleteSequence/$1');
            $routes->post('sequences/set-default/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::setUpstreamDefault/$1');
            $routes->post('sequences/clone/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::cloneSequence/$1');

            $routes->post('actions/save', '\\Diocesan\\Controllers\\FollowupWorkflow::saveAction/0');
            $routes->post('actions/save/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::saveAction/$1');
            $routes->post('actions/delete/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::deleteAction/$1');

            $routes->get('templates', '\\Diocesan\\Controllers\\FollowupWorkflow::templates');
            $routes->post('templates/save', '\\Diocesan\\Controllers\\FollowupWorkflow::saveTemplate/0');
            $routes->post('templates/save/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::saveTemplate/$1');
            $routes->post('templates/delete/(:num)', '\\Diocesan\\Controllers\\FollowupWorkflow::deleteTemplate/$1');
        });

        // Email settings
        $routes->get('email-settings', '\\App\\Controllers\\Diocesan\\EmailSettingsController::index');
        $routes->post('email-settings', '\\App\\Controllers\\Diocesan\\EmailSettingsController::save');
        $routes->post('email-settings/test', '\\App\\Controllers\\Diocesan\\EmailSettingsController::test');
    });
});
