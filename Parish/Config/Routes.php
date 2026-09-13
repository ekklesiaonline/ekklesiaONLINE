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
    });
});
