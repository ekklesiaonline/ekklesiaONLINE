<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Parish module routes
 *
 * @var RouteCollection $routes
 */

$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('parish', function ($routes) {
        $routes->get('dashboard', '\\Parish\\Controllers\\Dashboard::index', ['filter' => 'permission:dashboard.view']);
        $routes->get('finance', '\\Parish\\Controllers\\FinanceDashboard::index', ['filter' => 'permission:finance.view']);
        $routes->get('governance', '\\Parish\\Controllers\\GovernanceDashboard::index', ['filter' => 'permission:governance.vestry.view']);
        $routes->get('secretary', '\\Parish\\Controllers\\SecretaryDashboard::index', ['filter' => 'permission:report.view']);
        $routes->get('worship', '\\Parish\\Controllers\\WorshipDashboard::index', ['filter' => 'permission:service.view']);
        $routes->get('volunteers', '\\Parish\\Controllers\\VolunteerDashboard::index', ['filter' => 'permission:volunteer.view']);
        $routes->post('report-signatures/submit/(:num)', '\\App\\Controllers\\Parish\\ReportApprovals::submit/$1', ['filter' => 'permission:report.parochial']);
        $routes->post('report-signatures/(:num)/clerk', '\\App\\Controllers\\Parish\\ReportApprovals::certifyClerk/$1', ['filter' => 'permission:governance.report.certify_clerk']);
        $routes->post('report-signatures/(:num)/warden', '\\App\\Controllers\\Parish\\ReportApprovals::approveWarden/$1', ['filter' => 'permission:governance.report.approve_warden']);
        $routes->post('report-signatures/(:num)/clergy', '\\App\\Controllers\\Parish\\ReportApprovals::finalizeClergy/$1', ['filter' => 'permission:governance.report.finalize_clergy']);
        $routes->post('report-signatures/(:num)/diocese', '\\App\\Controllers\\Parish\\ReportApprovals::approveDiocese/$1', ['filter' => 'permission:governance.diocese.approve']);
        $routes->post('report-signatures/(:num)/new-revision', '\\App\\Controllers\\Parish\\ReportApprovals::newRevision/$1', ['filter' => 'permission:report.parochial']);
        $routes->post('report-signatures/lay-office/(:num)/approve', '\\App\\Controllers\\Parish\\ReportApprovals::approveLayOffice/$1', ['filter' => 'permission:governance.vestry.manage']);
        // Read-only clergy visibility + hire requests into the diocesan queue.
        // Parishes never create accounts nor write diocese-owned tables.
        $routes->get('clergy', '\\Parish\\Controllers\\Clergy::index', ['filter' => 'permission:member.view']);
        $routes->get('clergy/person-search', '\\Parish\\Controllers\\Clergy::personSearch', ['filter' => 'permission:member.view']);
        $routes->post('clergy/request', '\\Parish\\Controllers\\Clergy::submitRequest', ['filter' => 'permission:member.view']);
        $routes->get('email-settings', '\\App\\Controllers\\Parish\\EmailSettingsController::index', ['filter' => 'permission:parish.edit']);
        $routes->post('email-settings', '\\App\\Controllers\\Parish\\EmailSettingsController::save', ['filter' => 'permission:parish.edit']);
        $routes->post('email-settings/test', '\\App\\Controllers\\Parish\\EmailSettingsController::test', ['filter' => 'permission:parish.edit']);
    });
});
