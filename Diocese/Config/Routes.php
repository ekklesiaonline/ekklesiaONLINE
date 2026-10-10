<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Diocesan module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('diocese/clergy-review', function ($routes) {
        $routes->get('candidates/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::candidates/$1', ['filter' => 'permission:clergy.appointment.manage']);
        $routes->post('candidates/(:num)/review/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::reviewCandidate/$1/$2', ['filter' => 'permission:clergy.ordination.verify']);
        $routes->post('ordination/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::verifyOrdination/$1', ['filter' => 'permission:clergy.ordination.verify']);
        $routes->post('standing/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::standing/$1', ['filter' => 'permission:clergy.ordination.verify']);
        $routes->post('standing/(:num)/lift', '\\App\\Controllers\\Diocese\\ClergyReview::liftStanding/$1', ['filter' => 'permission:clergy.ordination.verify']);
        $routes->post('office/(:num)/approve', '\\App\\Controllers\\Diocese\\ClergyReview::approveOffice/$1', ['filter' => 'permission:clergy.appointment.manage']);
        $routes->post('office/(:num)/revoke', '\\App\\Controllers\\Diocese\\ClergyReview::revokeOffice/$1', ['filter' => 'permission:clergy.appointment.manage']);
        $routes->post('mission-office/(:num)/approve', '\\App\\Controllers\\Diocese\\ClergyReview::approveMissionOffice/$1', ['filter' => 'permission:clergy.appointment.manage']);
        $routes->post('delegation/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::delegate/$1', ['filter' => 'permission:governance.diocese.approve']);
        $routes->post('parish-vacancy/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::delegateVacancy/$1', ['filter' => 'permission:governance.diocese.approve']);
        $routes->post('report-policy/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::reportPolicy/$1', ['filter' => 'permission:governance.diocese.approve']);
        $routes->post('delegation/revoke/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::revokeDelegation/$1', ['filter' => 'permission:governance.diocese.approve']);
        // Parish hire requests flow into the diocesan approval queue.
        $routes->get('appointment-requests/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::appointmentRequests/$1', ['filter' => 'permission:clergy.appointment.manage']);
        $routes->post('appointment-requests/(:num)/review/(:num)', '\\App\\Controllers\\Diocese\\ClergyReview::reviewAppointmentRequest/$1/$2', ['filter' => 'permission:clergy.appointment.manage']);
    });

    // Diocese-owned clergy accounts — diocesan admins only. Every action
    // re-resolves the admin's diocese server-side; diocese A can never read
    // or mutate diocese B's clergy.
    $routes->group('diocese/clergy', ['filter' => 'permission:clergy.manage'], function ($routes) {
        $routes->get('/', '\\Diocese\\Controllers\\ClergyAccounts::index');
        $routes->get('create', '\\Diocese\\Controllers\\ClergyAccounts::create');
        $routes->post('store', '\\Diocese\\Controllers\\ClergyAccounts::store');
        $routes->get('person-search', '\\Diocese\\Controllers\\ClergyAccounts::personSearch');
        $routes->get('(:num)', '\\Diocese\\Controllers\\ClergyAccounts::show/$1');
        $routes->get('(:num)/edit', '\\Diocese\\Controllers\\ClergyAccounts::edit/$1');
        $routes->post('(:num)/edit', '\\Diocese\\Controllers\\ClergyAccounts::update/$1');
        $routes->post('(:num)/deactivate', '\\Diocese\\Controllers\\ClergyAccounts::deactivate/$1');
        $routes->post('(:num)/resend-invitation', '\\Diocese\\Controllers\\ClergyAccounts::resendInvitation/$1');
    });

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
        $routes->get('finance', '\\Diocese\\Controllers\\FinanceDashboard::index');

        $routes->group('deaneries', ['filter' => 'permission:deanery.view'], function ($routes) {
            $routes->get('/', '\\App\\Controllers\\Diocese\\Deaneries::index');
            $routes->get('manage', '\\App\\Controllers\\Diocese\\Deaneries::manage');
            $routes->post('store', '\\App\\Controllers\\Diocese\\Deaneries::store', ['filter' => 'permission:deanery.manage']);
            $routes->post('assign/(:num)', '\\App\\Controllers\\Diocese\\Deaneries::assign/$1', ['filter' => 'permission:deanery.manage']);
            $routes->post('end/(:num)', '\\App\\Controllers\\Diocese\\Deaneries::end/$1', ['filter' => 'permission:deanery.manage']);
        });
        $routes->post('congregations/(:num)/type', '\\App\\Controllers\\Diocese\\Deaneries::setCongregationType/$1', ['filter' => 'permission:parishes.edit']);

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
