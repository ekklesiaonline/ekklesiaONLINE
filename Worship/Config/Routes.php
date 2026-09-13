<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Worship module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    // Legacy alias path retained during cutover; keep module ownership without redirect hop.
    $routes->post('attendance/bulkMark', '\\Worship\\Controllers\\Attendance::bulkMark', ['filter' => 'permission:attendance.mark']);

    $routes->group('services', ['filter' => 'permission:service.view'], function ($routes) {
        $routes->get('/', '\\Worship\\Controllers\\Service::index');
        $routes->get('view/(:num)', '\\Worship\\Controllers\\Service::view/$1');
        $routes->get('liturgical-days', '\\Worship\\Controllers\\Service::liturgicalDays');
        $routes->get('create', '\\Worship\\Controllers\\Service::create', ['filter' => 'permission:service.create']);
        $routes->post('store', '\\Worship\\Controllers\\Service::store', ['filter' => 'permission:service.create']);
        $routes->get('edit/(:num)', '\\Worship\\Controllers\\Service::edit/$1', ['filter' => 'permission:service.edit']);
        $routes->post('update/(:num)', '\\Worship\\Controllers\\Service::update/$1', ['filter' => 'permission:service.edit']);
        $routes->post('delete/(:num)', '\\Worship\\Controllers\\Service::delete/$1', ['filter' => 'permission:service.delete']);
    });

    $routes->group('attendance', function ($routes) {
        $routes->get('service/(:num)', '\\Worship\\Controllers\\Attendance::service/$1', ['filter' => 'permission:attendance.mark']);
        $routes->post('mark', '\\Worship\\Controllers\\Attendance::mark', ['filter' => 'permission:attendance.mark']);
        $routes->post('bulk-mark', '\\Worship\\Controllers\\Attendance::bulkMark', ['filter' => 'permission:attendance.mark']);
        $routes->get('reports', '\\Worship\\Controllers\\Attendance::reports', ['filter' => 'permission:attendance.view']);
    });

    $routes->group('parochial-report', ['filter' => 'permission:report.view'], function ($routes) {
        $routes->get('/', '\\Worship\\Controllers\\ParochialReport::index');
        $routes->get('print', '\\Worship\\Controllers\\ParochialReport::print');
        $routes->get('export-pdf', '\\Worship\\Controllers\\ParochialReport::exportPdf');
        $routes->post('email-pdf', '\\Worship\\Controllers\\ParochialReport::emailPdf');
        $routes->post('save', '\\Worship\\Controllers\\ParochialReport::save');
    });

    $routes->group('worship-planning', ['filter' => 'permission:worship.view'], function ($routes) {
        $routes->get('/', '\\Worship\\Controllers\\WorshipPlanning::index');
        $routes->get('service/(:num)', '\\Worship\\Controllers\\WorshipPlanning::service/$1');
        $routes->post('service/(:num)/save', '\\Worship\\Controllers\\WorshipPlanning::saveService/$1', ['filter' => 'permission:worship.manage']);
        $routes->get('songs', '\\Worship\\Controllers\\WorshipPlanning::songs');
        $routes->get('songs/edit/(:num)', '\\Worship\\Controllers\\WorshipPlanning::editSong/$1', ['filter' => 'permission:worship.manage']);
        $routes->post('songs/store', '\\Worship\\Controllers\\WorshipPlanning::storeSong', ['filter' => 'permission:worship.manage']);
        $routes->post('songs/update/(:num)', '\\Worship\\Controllers\\WorshipPlanning::updateSong/$1', ['filter' => 'permission:worship.manage']);
        $routes->post('songs/deactivate/(:num)', '\\Worship\\Controllers\\WorshipPlanning::deactivateSong/$1', ['filter' => 'permission:worship.manage']);
        $routes->post('songs/reactivate/(:num)', '\\Worship\\Controllers\\WorshipPlanning::reactivateSong/$1', ['filter' => 'permission:worship.manage']);
        $routes->post('songs/delete/(:num)', '\\Worship\\Controllers\\WorshipPlanning::deleteSong/$1', ['filter' => 'permission:worship.manage']);
        $routes->get('print/(:num)', '\\Worship\\Controllers\\WorshipPlanning::printService/$1');
    });
});
