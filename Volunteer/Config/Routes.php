<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Volunteer module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    // Legacy alias paths retained during cutover; keep module ownership without redirect hops.
    $routes->get('volunteers/viewTeam/(:num)', '\\Volunteer\\Controllers\\Volunteer::viewTeam/$1', ['filter' => 'permission:volunteer.manage']);
    $routes->get('volunteers/createTeam', '\\Volunteer\\Controllers\\Volunteer::createTeam', ['filter' => 'permission:volunteer.manage']);
    $routes->get('volunteers/schedule', '\\Volunteer\\Controllers\\Volunteer::schedules', ['filter' => 'permission:volunteer.schedule']);
    $routes->get('volunteers/create-schedule', '\\Volunteer\\Controllers\\Volunteer::createSchedule', ['filter' => 'permission:volunteer.schedule']);
    $routes->get('volunteers/edit-schedule/(:num)', '\\Volunteer\\Controllers\\Volunteer::editSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
    $routes->post('volunteers/storeTeam', '\\Volunteer\\Controllers\\Volunteer::storeTeam', ['filter' => 'permission:volunteer.manage']);
    $routes->post('volunteers/store-schedule', '\\Volunteer\\Controllers\\Volunteer::storeSchedule', ['filter' => 'permission:volunteer.schedule']);
    $routes->post('volunteers/update-schedule/(:num)', '\\Volunteer\\Controllers\\Volunteer::updateSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
    $routes->post('volunteers/cancel-schedule/(:num)', '\\Volunteer\\Controllers\\Volunteer::cancelSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
    $routes->post('volunteers/confirm-schedule/(:num)', '\\Volunteer\\Controllers\\Volunteer::confirmSchedule/$1', ['filter' => 'permission:volunteer.schedule']);

    $routes->group('volunteer', ['filter' => 'permission:volunteer.manage'], function ($routes) {
        $routes->get('dashboard', '\\Volunteer\\Controllers\\Dashboard::index');
    });

    $routes->group('volunteers', ['filter' => 'permission:volunteer.manage'], function ($routes) {
        $routes->get('/', '\\Volunteer\\Controllers\\Volunteer::index');
        $routes->get('view/(:num)', '\\Volunteer\\Controllers\\Volunteer::viewTeam/$1');
        $routes->get('create-team', '\\Volunteer\\Controllers\\Volunteer::createTeam', ['filter' => 'permission:volunteer.manage']);
        $routes->post('store-team', '\\Volunteer\\Controllers\\Volunteer::storeTeam', ['filter' => 'permission:volunteer.manage']);
        $routes->post('add-member', '\\Volunteer\\Controllers\\Volunteer::addMember', ['filter' => 'permission:volunteer.assign']);
        $routes->post('remove-member/(:num)', '\\Volunteer\\Controllers\\Volunteer::removeMember/$1', ['filter' => 'permission:volunteer.assign']);
        $routes->get('schedules', '\\Volunteer\\Controllers\\Volunteer::schedules', ['filter' => 'permission:volunteer.schedule']);
        $routes->get('schedules/create', '\\Volunteer\\Controllers\\Volunteer::createSchedule', ['filter' => 'permission:volunteer.schedule']);
        $routes->get('schedules/edit/(:num)', '\\Volunteer\\Controllers\\Volunteer::editSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
        $routes->get('schedules/team-members/(:num)', '\\Volunteer\\Controllers\\Volunteer::teamMembersApi/$1', ['filter' => 'permission:volunteer.schedule']);
        $routes->post('schedules/store', '\\Volunteer\\Controllers\\Volunteer::storeSchedule', ['filter' => 'permission:volunteer.schedule']);
        $routes->post('schedules/update/(:num)', '\\Volunteer\\Controllers\\Volunteer::updateSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
        $routes->post('schedules/cancel/(:num)', '\\Volunteer\\Controllers\\Volunteer::cancelSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
        $routes->post('schedules/confirm/(:num)', '\\Volunteer\\Controllers\\Volunteer::confirmSchedule/$1', ['filter' => 'permission:volunteer.schedule']);
    });
});
