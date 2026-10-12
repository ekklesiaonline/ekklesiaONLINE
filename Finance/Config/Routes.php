<?php

require_once __DIR__ . '/Aliases.php';

use CodeIgniter\Router\RouteCollection;

/**
 * Treasurer module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    $routes->group('treasurer', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('dashboard', '\\Finance\\Controllers\\Dashboard::index');
    });

    $routes->group('donations', ['filter' => 'permission:donation.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Donation::index');
        $routes->get('export', '\\Finance\\Controllers\\Donation::export');
        $routes->get('export-pdf', '\\Finance\\Controllers\\Donation::exportPdf');
        $routes->get('statement', '\\Finance\\Controllers\\Donation::statement');
        $routes->match(['get', 'post'], 'batch-statements', '\\Finance\\Controllers\\Donation::batchStatements');
        $routes->get('receipt/(:num)', '\\Finance\\Controllers\\Donation::receipt/$1', ['filter' => 'permission:donation.view']);
        $routes->get('create', '\\Finance\\Controllers\\Donation::create', ['filter' => 'permission:donation.create']);
        $routes->post('store', '\\Finance\\Controllers\\Donation::store', ['filter' => 'permission:donation.create']);
        $routes->get('edit/(:num)', '\\Finance\\Controllers\\Donation::edit/$1', ['filter' => 'permission:donation.edit']);
        $routes->post('update/(:num)', '\\Finance\\Controllers\\Donation::update/$1', ['filter' => 'permission:donation.edit']);
        $routes->post('delete/(:num)', '\\Finance\\Controllers\\Donation::delete/$1', ['filter' => 'permission:donation.delete']);
    });

    $routes->group('budgets', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Budgets::index');
        $routes->post('save', '\\Finance\\Controllers\\Budgets::save', ['filter' => 'permission:finance.manage']);
    });

    $routes->group('expenses', ['filter' => 'permission:expense.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Expense::index');
        $routes->get('export', '\\Finance\\Controllers\\Expense::export');
        $routes->get('export-pdf', '\\Finance\\Controllers\\Expense::exportPdf');
        $routes->get('receipt/(:num)', '\\Finance\\Controllers\\Expense::receipt/$1');
        $routes->get('create', '\\Finance\\Controllers\\Expense::create', ['filter' => 'permission:expense.create']);
        $routes->post('store', '\\Finance\\Controllers\\Expense::store', ['filter' => 'permission:expense.create']);
        $routes->get('edit/(:num)', '\\Finance\\Controllers\\Expense::edit/$1', ['filter' => 'permission:expense.edit']);
        $routes->post('update/(:num)', '\\Finance\\Controllers\\Expense::update/$1', ['filter' => 'permission:expense.edit']);
        $routes->post('delete/(:num)', '\\Finance\\Controllers\\Expense::delete/$1', ['filter' => 'permission:expense.delete']);
    });

    $routes->group('banking', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Banking::index');
        $routes->get('create', '\\Finance\\Controllers\\Banking::create', ['filter' => 'permission:finance.manage']);
        $routes->post('store', '\\Finance\\Controllers\\Banking::store', ['filter' => 'permission:finance.manage']);
        $routes->get('reconciliations/(:num)', '\\Finance\\Controllers\\BankingReconciliation::reconciliation/$1');
        $routes->post('reconciliations/(:num)/items', '\\Finance\\Controllers\\BankingReconciliation::saveReconciliationItems/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('reconciliations/(:num)/close', '\\Finance\\Controllers\\BankingReconciliation::closeReconciliation/$1', ['filter' => 'permission:finance.manage']);
        $routes->get('(:num)', '\\Finance\\Controllers\\Banking::view/$1');
        $routes->post('(:num)/transactions', '\\Finance\\Controllers\\Banking::storeTransaction/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('(:num)/reconciliations', '\\Finance\\Controllers\\BankingReconciliation::startReconciliation/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('import', '\\Finance\\Controllers\\BankingImport::importStatement', ['filter' => 'permission:finance.manage']);
        $routes->post('(:num)/import', '\\Finance\\Controllers\\BankingImport::importStatement/$1', ['filter' => 'permission:finance.manage']);
        $routes->get('edit/(:num)', '\\Finance\\Controllers\\Banking::edit/$1', ['filter' => 'permission:finance.manage']);
        $routes->get('(:num)/edit', '\\Finance\\Controllers\\Banking::edit/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('update/(:num)', '\\Finance\\Controllers\\Banking::update/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('toggle-active/(:num)', '\\Finance\\Controllers\\Banking::toggleActive/$1', ['filter' => 'permission:finance.manage']);
    });

    $routes->group('funds', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Funds::index');
        $routes->get('export', '\\Finance\\Controllers\\FundExports::export');
        $routes->get('create', '\\Finance\\Controllers\\Funds::create', ['filter' => 'permission:finance.manage']);
        $routes->post('store', '\\Finance\\Controllers\\Funds::store', ['filter' => 'permission:finance.manage']);
        $routes->get('edit/(:num)', '\\Finance\\Controllers\\Funds::edit/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('update/(:num)', '\\Finance\\Controllers\\Funds::update/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('lock/(:num)', '\\Finance\\Controllers\\FundFlags::lock/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('unlock/(:num)', '\\Finance\\Controllers\\FundFlags::unlock/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('activate/(:num)', '\\Finance\\Controllers\\FundFlags::activate/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('deactivate/(:num)', '\\Finance\\Controllers\\FundFlags::deactivate/$1', ['filter' => 'permission:finance.manage']);
        $routes->get('(:num)', '\\Finance\\Controllers\\Funds::view/$1');
    });

    $routes->group('reports', ['filter' => 'permission:report.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\Reports::index');
        $routes->get('trial-balance', '\\Finance\\Controllers\\Reports::trialBalance');
        $routes->get('trial-balance/export-csv', '\\Finance\\Controllers\\ReportExports::trialBalanceCsv');
        $routes->get('trial-balance/export-pdf', '\\Finance\\Controllers\\ReportExports::trialBalancePdf');
        $routes->get('balance-sheet', '\\Finance\\Controllers\\Reports::balanceSheet');
        $routes->get('balance-sheet/export-csv', '\\Finance\\Controllers\\ReportExports::balanceSheetCsv');
        $routes->get('balance-sheet/export-pdf', '\\Finance\\Controllers\\ReportExports::balanceSheetPdf');
        $routes->get('statement-of-activities', '\\Finance\\Controllers\\Reports::statementOfActivities');
        $routes->get('statement-of-activities/export-csv', '\\Finance\\Controllers\\ReportExports::statementOfActivitiesCsv');
        $routes->get('statement-of-activities/export-pdf', '\\Finance\\Controllers\\ReportExports::statementOfActivitiesPdf');
        $routes->get('export', '\\Finance\\Controllers\\ReportExports::export');
        $routes->get('export-pdf', '\\Finance\\Controllers\\ReportExports::exportPdf');
        $routes->get('api/summary', '\\Finance\\Controllers\\Reports::summaryApi');
    });

    $routes->group('journal-entries', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\JournalEntries::index');
        $routes->get('export', '\\Finance\\Controllers\\JournalEntries::export');
        $routes->get('(:num)', '\\Finance\\Controllers\\JournalEntries::view/$1');
    });

    $routes->group('accounting-periods', ['filter' => 'permission:finance.view'], function ($routes) {
        $routes->get('/', '\\Finance\\Controllers\\AccountingPeriods::index');
        $routes->post('generate', '\\Finance\\Controllers\\AccountingPeriods::generate', ['filter' => 'permission:finance.manage']);
        $routes->post('store', '\\Finance\\Controllers\\AccountingPeriods::store', ['filter' => 'permission:finance.manage']);
        $routes->post('close/(:num)', '\\Finance\\Controllers\\AccountingPeriods::close/$1', ['filter' => 'permission:finance.manage']);
        $routes->post('reopen/(:num)', '\\Finance\\Controllers\\AccountingPeriods::reopen/$1', ['filter' => 'permission:finance.manage']);
    });
});

