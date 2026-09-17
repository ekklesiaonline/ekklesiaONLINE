<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Treasurer module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('treasurer', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('dashboard', '\\Treasurer\\Controllers\\Dashboard::index');
    });

    $routes->group('donations', ['filter' => 'permission:donation.view'], function ($routes) {
        $routes->get('/', '\\Treasurer\\Controllers\\Donation::index');
        $routes->get('export', '\\Treasurer\\Controllers\\Donation::export');
        $routes->get('export-pdf', '\\Treasurer\\Controllers\\Donation::exportPdf');
        $routes->get('statement', '\\Treasurer\\Controllers\\Donation::statement');
        $routes->get('receipt/(:num)', '\\Treasurer\\Controllers\\Donation::receipt/$1', ['filter' => 'permission:donation.view']);
        $routes->get('create', '\\Treasurer\\Controllers\\Donation::create', ['filter' => 'permission:donation.create']);
        $routes->post('store', '\\Treasurer\\Controllers\\Donation::store', ['filter' => 'permission:donation.create']);
        $routes->post('delete/(:num)', '\\Treasurer\\Controllers\\Donation::delete/$1', ['filter' => 'permission:donation.delete']);
    });

    $routes->group('budgets', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Treasurer\\Controllers\\Budgets::index');
        $routes->post('save', '\\Treasurer\\Controllers\\Budgets::save', ['filter' => 'permission:finance.manage']);
    });

    $routes->group('expenses', ['filter' => 'permission:expense.view'], function ($routes) {
        $routes->get('/', '\\Treasurer\\Controllers\\Expense::index');
        $routes->get('export', '\\Treasurer\\Controllers\\Expense::export');
        $routes->get('export-pdf', '\\Treasurer\\Controllers\\Expense::exportPdf');
        $routes->get('create', '\\Treasurer\\Controllers\\Expense::create', ['filter' => 'permission:expense.create']);
        $routes->post('store', '\\Treasurer\\Controllers\\Expense::store', ['filter' => 'permission:expense.create']);
        $routes->get('edit/(:num)', '\\Treasurer\\Controllers\\Expense::edit/$1', ['filter' => 'permission:expense.edit']);
        $routes->post('update/(:num)', '\\Treasurer\\Controllers\\Expense::update/$1', ['filter' => 'permission:expense.edit']);
        $routes->post('delete/(:num)', '\\Treasurer\\Controllers\\Expense::delete/$1', ['filter' => 'permission:expense.delete']);
    });

    $routes->group('banking', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Treasurer\\Controllers\\Banking::index');
        $routes->get('create', '\\Treasurer\\Controllers\\Banking::create', ['filter' => 'permission:finance.manage']);
        $routes->post('store', '\\Treasurer\\Controllers\\Banking::store', ['filter' => 'permission:finance.manage']);
        $routes->get('reconciliations/(:num)', '\\Treasurer\\Controllers\\Banking::reconciliation/$1');
        $routes->post('reconciliations/(:num)/items', '\\Treasurer\\Controllers\\Banking::saveReconciliationItems/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('reconciliations/(:num)/close', '\\Treasurer\\Controllers\\Banking::closeReconciliation/$1', ['filter' => 'permission:finance.manage']);
        $routes->get('(:num)', '\\Treasurer\\Controllers\\Banking::view/$1');
        $routes->post('(:num)/transactions', '\\Treasurer\\Controllers\\Banking::storeTransaction/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('(:num)/reconciliations', '\\Treasurer\\Controllers\\Banking::startReconciliation/$1', ['filter' => 'permission:finance.manage']);
    });

    $routes->group('reports', ['filter' => 'permission:report.view'], function ($routes) {
        $routes->get('/', '\\Treasurer\\Controllers\\Reports::index');
        $routes->get('export', '\\Treasurer\\Controllers\\Reports::export');
        $routes->get('export-pdf', '\\Treasurer\\Controllers\\Reports::exportPdf');
        $routes->get('api/summary', '\\Treasurer\\Controllers\\Reports::summaryApi');
    });
});
