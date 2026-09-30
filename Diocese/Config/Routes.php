<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Diocesan module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('chart-accounts', ['filter' => 'permission:diocese.admin'], function ($routes) {
        $routes->get('/', '\\Diocese\\Controllers\\ChartOfAccounts::index');
        $routes->get('create', '\\Diocese\\Controllers\\ChartOfAccounts::create');
        $routes->post('store', '\\Diocese\\Controllers\\ChartOfAccounts::store');
        $routes->get('edit/(:num)', '\\Diocese\\Controllers\\ChartOfAccounts::edit/$1');
        $routes->post('update/(:num)', '\\Diocese\\Controllers\\ChartOfAccounts::update/$1');
        $routes->post('toggle/(:num)', '\\Diocese\\Controllers\\ChartOfAccounts::toggle/$1');
    });

    $routes->group('liturgical-source', function ($routes) {
        $routes->get('/', '\\Diocese\\Controllers\\LiturgicalCalendar::index');
    });

    $routes->group('diocese', ['filter' => 'permission:diocese.admin'], function ($routes) {
        $routes->get('dashboard', '\\Diocese\\Controllers\\Dashboard::index');
        $routes->get('dashboard/recent-activity', '\\Diocese\\Controllers\\Dashboard::recentActivity');
        $routes->get('dashboard/switch/(:num)', '\\Diocese\\Controllers\\Dashboard::switchParish/$1');
        $routes->get('attendance-period-breakdown', '\\Diocese\\Controllers\\Dashboard::attendancePeriodBreakdown');

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
            $routes->get('sequences', '\\Diocese\\Controllers\\FollowupWorkflow::sequences');
            $routes->get('sequences/create', '\\Diocese\\Controllers\\FollowupWorkflow::edit_sequence/0');
            $routes->get('sequences/edit/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::edit_sequence/$1');
            $routes->post('sequences/save', '\\Diocese\\Controllers\\FollowupWorkflow::saveSequence/0');
            $routes->post('sequences/save/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::saveSequence/$1');
            $routes->post('sequences/toggle/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::toggleSequence/$1');
            $routes->post('sequences/delete/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::deleteSequence/$1');
            $routes->post('sequences/set-default/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::setUpstreamDefault/$1');
            $routes->post('sequences/clone/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::cloneSequence/$1');

            $routes->post('actions/save', '\\Diocese\\Controllers\\FollowupWorkflow::saveAction/0');
            $routes->post('actions/save/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::saveAction/$1');
            $routes->post('actions/delete/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::deleteAction/$1');

            $routes->get('templates', '\\Diocese\\Controllers\\FollowupWorkflow::templates');
            $routes->post('templates/save', '\\Diocese\\Controllers\\FollowupWorkflow::saveTemplate/0');
            $routes->post('templates/save/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::saveTemplate/$1');
            $routes->post('templates/delete/(:num)', '\\Diocese\\Controllers\\FollowupWorkflow::deleteTemplate/$1');
        });

        // Email settings
        $routes->get('email-settings', '\\App\\Controllers\\Diocese\\EmailSettingsController::index');
        $routes->post('email-settings', '\\App\\Controllers\\Diocese\\EmailSettingsController::save');
        $routes->post('email-settings/test', '\\App\\Controllers\\Diocese\\EmailSettingsController::test');
    });
});
