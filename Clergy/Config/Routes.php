<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Clergy module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    // Navbar badge poller – returns pending call-task count as JSON.
    $routes->get('api/followup/pending-calls', '\\Clergy\\Controllers\\VisitorFollowup::pendingCallsApi', ['filter' => 'permission:followup.view']);

    $routes->group('clergy', ['filter' => 'permission:member.view'], function ($routes) {
        $routes->get('dashboard', '\\Clergy\\Controllers\\Dashboard::index');
    });

    $routes->group('members', ['filter' => 'permission:member.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Members::index');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Members::view/$1');
        $routes->get('create', '\\Clergy\\Controllers\\Members::create', ['filter' => 'permission:member.create']);
        $routes->post('store', '\\Clergy\\Controllers\\Members::store', ['filter' => 'permission:member.create']);
        $routes->post('store-non-portal', '\\Clergy\\Controllers\\Members::storeNonPortal', ['filter' => 'permission:member.create']);
        $routes->get('edit/(:num)', '\\Clergy\\Controllers\\Members::edit/$1', ['filter' => 'permission:member.edit']);
        $routes->post('update/(:num)', '\\Clergy\\Controllers\\Members::update/$1', ['filter' => 'permission:member.edit']);
        $routes->post('delete/(:num)', '\\Clergy\\Controllers\\Members::delete/$1', ['filter' => 'permission:member.delete']);
        $routes->post('revoke-portal-access/(:num)', '\\Clergy\\Controllers\\Members::revokePortalAccess/$1', ['filter' => 'permission:member.edit']);
        $routes->post('bulk-delete', '\\Clergy\\Controllers\\Members::bulkDelete', ['filter' => 'permission:member.delete']);

        // Dedup / merge / hard-delete sub-group
        $routes->group('dedup', ['filter' => 'permission:member.delete'], function ($routes) {
            $routes->get('/', '\\Clergy\\Controllers\\MemberMerge::index');
            $routes->get('preview', '\\Clergy\\Controllers\\MemberMerge::preview');
            $routes->post('merge', '\\Clergy\\Controllers\\MemberMerge::merge');
            $routes->get('hard-delete/(:num)', '\\Clergy\\Controllers\\MemberMerge::confirmHardDelete/$1');
            $routes->post('hard-delete/(:num)', '\\Clergy\\Controllers\\MemberMerge::hardDelete/$1');
        });

        $routes->post('attach-parish', '\\Clergy\\Controllers\\MembersAffiliation::attachParish', ['filter' => 'permission:member.edit']);
        $routes->post('detach-parish', '\\Clergy\\Controllers\\MembersAffiliation::detachParish', ['filter' => 'permission:member.edit']);
        $routes->post('set-home-parish', '\\Clergy\\Controllers\\MembersAffiliation::setHomeParish', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/approve/(:num)', '\\Diocese\\Controllers\\RegistrationRequests::approve/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/bulk-action', '\\Diocese\\Controllers\\RegistrationRequests::bulkAction', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/dismiss/(:num)', '\\Diocese\\Controllers\\RegistrationRequests::dismiss/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/ban/(:num)', '\\Diocese\\Controllers\\RegistrationRequests::ban/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/delete/(:num)', '\\Diocese\\Controllers\\RegistrationRequests::delete/$1', ['filter' => 'permission:member.delete']);
        $routes->post('registrations/escalate/(:num)', '\\Diocese\\Controllers\\RegistrationRequests::escalate/$1', ['filter' => 'permission:member.edit']);
        $routes->get('search', '\\Clergy\\Controllers\\Members::search');
    });

    $routes->group('people', ['filter' => 'permission:member.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\People::index');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\People::view/$1');
        $routes->post('enable-portal/(:num)', '\\Clergy\\Controllers\\People::enablePortalAccess/$1');
        $routes->post('toggle-member-tag/(:num)', '\\Clergy\\Controllers\\People::toggleMemberTag/$1', ['filter' => 'permission:member.edit']);
        $routes->post('bulk-toggle-member-tag', '\\Clergy\\Controllers\\People::bulkToggleMemberTag', ['filter' => 'permission:member.edit']);
        $routes->post('store-non-portal', '\\Clergy\\Controllers\\People::storeNonPortal', ['filter' => 'permission:member.create']);
        $routes->post('save-membership-details/(:num)', '\\Clergy\\Controllers\\People::saveMembershipDetails/$1', ['filter' => 'permission:member.edit']);
        $routes->get('leadership-eligibility/(:num)', '\\Clergy\\Controllers\\PeopleLeadership::leadershipEligibility/$1', ['filter' => 'permission:leadership_position.manage']);
        $routes->post('leadership/(:num)', '\\Clergy\\Controllers\\PeopleLeadership::addLeadershipPosition/$1', ['filter' => 'permission:leadership_position.manage']);
        $routes->post('leadership/(:num)/update', '\\Clergy\\Controllers\\PeopleLeadership::updateLeadershipPosition/$1', ['filter' => 'permission:leadership_position.manage']);
        $routes->post('leadership/(:num)/delete', '\\Clergy\\Controllers\\PeopleLeadership::deleteLeadershipPosition/$1', ['filter' => 'permission:leadership_position.manage']);
        $routes->post('donation/(:num)', '\\Clergy\\Controllers\\PeopleFinance::addDonation/$1', ['filter' => 'permission:donation.create']);
        $routes->post('donation/(:num)/update', '\\Clergy\\Controllers\\PeopleFinance::updateDonation/$1', ['filter' => 'permission:donation.edit']);
        $routes->post('donation/(:num)/delete', '\\Clergy\\Controllers\\PeopleFinance::deleteDonation/$1', ['filter' => 'permission:donation.delete']);
        $routes->post('pledge/(:num)', '\\Clergy\\Controllers\\PeopleFinance::addPledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('pledge/(:num)/update', '\\Clergy\\Controllers\\PeopleFinance::updatePledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('pledge/(:num)/delete', '\\Clergy\\Controllers\\PeopleFinance::deletePledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('sacrament/(:num)', '\\Clergy\\Controllers\\PeopleSacraments::addSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/update', '\\Clergy\\Controllers\\PeopleSacraments::updateSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/delete', '\\Clergy\\Controllers\\PeopleSacraments::deleteSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/participant', '\\Clergy\\Controllers\\PeopleSacraments::addSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/participant/(:num)/update', '\\Clergy\\Controllers\\PeopleSacraments::updateSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/participant/(:num)/delete', '\\Clergy\\Controllers\\PeopleSacraments::deleteSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('family/(:num)/create', '\\Clergy\\Controllers\\PeopleFamilies::createFamilyForPerson/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/join', '\\Clergy\\Controllers\\PeopleFamilies::addPersonToFamily/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/leave', '\\Clergy\\Controllers\\PeopleFamilies::removePersonFromFamily/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/relationship', '\\Clergy\\Controllers\\PeopleFamilies::updateFamilyRelationship/$1', ['filter' => 'permission:family.manage']);
        $routes->post('export', '\\Clergy\\Controllers\\People::export');
        $routes->post('bulk-delete', '\\Clergy\\Controllers\\People::bulkDelete', ['filter' => 'permission:member.delete']);

        $routes->group('merge', ['filter' => 'permission:member.delete'], function ($routes) {
            $routes->get('/', '\\Clergy\\Controllers\\PersonMerge::index');
            $routes->get('preview', '\\Clergy\\Controllers\\PersonMerge::preview');
            $routes->post('execute', '\\Clergy\\Controllers\\PersonMerge::merge');
        });
    });

    $routes->group('groups', ['filter' => 'permission:groups.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Groups::index');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Groups::view/$1');
        $routes->get('create', '\\Clergy\\Controllers\\Groups::create', ['filter' => 'permission:groups.manage']);
        $routes->post('store', '\\Clergy\\Controllers\\Groups::store', ['filter' => 'permission:groups.manage']);
        $routes->get('edit/(:num)', '\\Clergy\\Controllers\\Groups::edit/$1', ['filter' => 'permission:groups.manage']);
        $routes->post('update/(:num)', '\\Clergy\\Controllers\\Groups::update/$1', ['filter' => 'permission:groups.manage']);
        $routes->post('delete/(:num)', '\\Clergy\\Controllers\\Groups::delete/$1', ['filter' => 'permission:groups.manage']);
        $routes->post('members/(:num)', '\\Clergy\\Controllers\\Groups::addMember/$1', ['filter' => 'permission:groups.assign']);
        $routes->post('members/(:num)/remove', '\\Clergy\\Controllers\\Groups::removeMember/$1', ['filter' => 'permission:groups.assign']);
        $routes->post('members/(:num)/role', '\\Clergy\\Controllers\\Groups::updateMemberRole/$1', ['filter' => 'permission:groups.assign']);
    });

    $routes->group('messages', ['filter' => 'permission:messaging.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Messages::index');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Messages::view/$1');
        $routes->get('compose', '\\Clergy\\Controllers\\Messages::create', ['filter' => 'permission:messaging.send']);
        $routes->post('store', '\\Clergy\\Controllers\\Messages::store', ['filter' => 'permission:messaging.send']);
        $routes->get('preview/(:num)', '\\Clergy\\Controllers\\Messages::preview/$1', ['filter' => 'permission:messaging.send']);
        $routes->post('send/(:num)', '\\Clergy\\Controllers\\Messages::send/$1', ['filter' => 'permission:messaging.send']);
        $routes->post('cancel/(:num)', '\\Clergy\\Controllers\\Messages::cancel/$1', ['filter' => 'permission:messaging.send']);
        $routes->get('templates', '\\Clergy\\Controllers\\Messages::templates', ['filter' => 'permission:messaging.send']);
        $routes->get('templates/create', '\\Clergy\\Controllers\\Messages::templateForm', ['filter' => 'permission:messaging.send']);
        $routes->get('templates/edit/(:num)', '\\Clergy\\Controllers\\Messages::templateForm/$1', ['filter' => 'permission:messaging.send']);
        $routes->post('templates/save', '\\Clergy\\Controllers\\Messages::templateSave', ['filter' => 'permission:messaging.send']);
        $routes->post('templates/save/(:num)', '\\Clergy\\Controllers\\Messages::templateSave/$1', ['filter' => 'permission:messaging.send']);
        $routes->post('templates/delete/(:num)', '\\Clergy\\Controllers\\Messages::templateDelete/$1', ['filter' => 'permission:messaging.send']);
    });

    $routes->group('visitors', ['filter' => 'permission:visitor.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Visitors::index');
        $routes->get('dashboard', '\\Clergy\\Controllers\\Visitors::dashboard');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Visitors::view/$1');
        $routes->get('create', '\\Clergy\\Controllers\\Visitors::create', ['filter' => 'permission:visitor.create']);
        $routes->post('store', '\\Clergy\\Controllers\\Visitors::store', ['filter' => 'permission:visitor.create']);
        $routes->get('edit/(:num)', '\\Clergy\\Controllers\\Visitors::edit/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('update/(:num)', '\\Clergy\\Controllers\\Visitors::update/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('delete/(:num)', '\\Clergy\\Controllers\\Visitors::delete/$1', ['filter' => 'permission:visitor.delete']);
        $routes->post('promote/(:num)', '\\Clergy\\Controllers\\VisitorLifecycle::promote/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('revert-conversion/(:num)', '\\Clergy\\Controllers\\VisitorLifecycle::revertConversion/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('promote-attender/(:num)', '\\Clergy\\Controllers\\VisitorLifecycle::promoteAttender/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('demote/(:num)', '\\Clergy\\Controllers\\VisitorLifecycle::demoteToVisitor/$1', ['filter' => 'permission:visitor.edit']);
        // AJAX endpoints
        $routes->get('followup-status/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::getFollowupStatus/$1', ['filter' => 'permission:followup.view']);
        $routes->post('followup-trigger/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::triggerFollowupAction/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('assign-sequence/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::assignSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->get('followup-timeline/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::followupTimeline/$1', ['filter' => 'permission:followup.view']);
        // Legacy /visitors/followup-manual/:id — retired name for the same page.
        $routes->get('followup-manual/(:num)', static fn(int $id) => redirect()->to(base_url('visitors/followup-timeline/' . $id)));
        $routes->post('followup-log/mark-done/(:num)',   '\Clergy\Controllers\VisitorFollowup::logMarkDone/$1',   ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/reschedule/(:num)',  '\Clergy\Controllers\VisitorFollowup::logReschedule/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/skip/(:num)',        '\Clergy\Controllers\VisitorFollowup::logSkip/$1',        ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/mark-failed/(:num)', '\Clergy\Controllers\VisitorFollowup::logMarkFailed/$1',  ['filter' => 'permission:followup.manage']);
        $routes->post('notes/add/(:num)', '\Clergy\Controllers\Visitors::addNote/$1', ['filter' => 'permission:visitor.edit']);
    });

    // ── Visitor connection-card import (CSV + QR code)
    $routes->group('visitors/import', ['filter' => 'permission:visitor.create'], function ($routes) {
        $routes->get('/',           '\\Clergy\\Controllers\\Import::connectionCards');
        $routes->post('process',    '\\Clergy\\Controllers\\Import::processConnectionCards');
        $routes->get('qr',          '\\Clergy\\Controllers\\Import::qrCode');
        $routes->get('sample-csv',  '\\Clergy\\Controllers\\Import::sampleCsv');
    });

    // ── Visitor followup reports
    $routes->group('visitors/reports', ['filter' => 'permission:report.view'], function ($routes) {
        $routes->get('followup-completion',        '\\Clergy\\Controllers\\Reports::followupCompletion');
        $routes->get('roi-second-visit',           '\\Clergy\\Controllers\\Reports::roiSecondVisit');
        $routes->get('export-csv/(:segment)',      '\\Clergy\\Controllers\\Reports::exportCsv/$1');
    });

    // ── Legacy /guests/* shims (one release): GETs redirect to /visitors/*,
    // POSTs alias to the same controller actions (bodies can't redirect).
    // The retired mutating GETs land on the visitor detail page instead.
    $routes->get('guests', static fn() => redirect()->to(base_url('visitors')));
    $routes->get('guests/convertToMember/(:num)', static fn(int $id) => redirect()->to(base_url('visitors/view/' . $id)));
    $routes->get('guests/create-account/(:num)', static fn(int $id) => redirect()->to(base_url('visitors/view/' . $id)));
    $routes->get('guests/(:any)', static function (string $rest) {
        return redirect()->to(base_url('visitors/' . $rest));
    });
    $routes->post('guests/store', '\\Clergy\\Controllers\\Visitors::store', ['filter' => 'permission:visitor.create']);
    $routes->post('guests/update/(:num)', '\\Clergy\\Controllers\\Visitors::update/$1', ['filter' => 'permission:visitor.edit']);
    $routes->post('guests/delete/(:num)', '\\Clergy\\Controllers\\Visitors::delete/$1', ['filter' => 'permission:visitor.delete']);
    $routes->post('guests/import/process', '\\Clergy\\Controllers\\Import::processConnectionCards', ['filter' => 'permission:visitor.create']);
    $routes->post('guests/followup-trigger/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::triggerFollowupAction/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/assign-sequence/(:num)', '\\Clergy\\Controllers\\VisitorFollowup::assignSequence/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/mark-done/(:num)',   '\Clergy\Controllers\VisitorFollowup::logMarkDone/$1',   ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/reschedule/(:num)',  '\Clergy\Controllers\VisitorFollowup::logReschedule/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/skip/(:num)',        '\Clergy\Controllers\VisitorFollowup::logSkip/$1',        ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/mark-failed/(:num)', '\Clergy\Controllers\VisitorFollowup::logMarkFailed/$1',  ['filter' => 'permission:followup.manage']);

    // Legacy followup/* routes retired — redirected to new workflow system.
    $routes->get('followup', static fn() => redirect()->to(base_url('followup-workflow/tasks')));
    $routes->get('followup/(:any)', static fn() => redirect()->to(base_url('followup-workflow/tasks')));
    $routes->post('followup/(:any)', static fn() => redirect()->to(base_url('followup-workflow/tasks')));

    $routes->group('events', ['filter' => 'permission:event.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Events::index');
        $routes->get('calendar', '\\Clergy\\Controllers\\Events::calendar');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Events::view/$1');
        $routes->get('create', '\\Clergy\\Controllers\\Events::create', ['filter' => 'permission:event.create']);
        $routes->post('store', '\\Clergy\\Controllers\\Events::store', ['filter' => 'permission:event.create']);
        $routes->get('edit/(:num)', '\\Clergy\\Controllers\\Events::edit/$1', ['filter' => 'permission:event.edit']);
        $routes->post('update/(:num)', '\\Clergy\\Controllers\\Events::update/$1', ['filter' => 'permission:event.edit']);
        $routes->post('delete/(:num)', '\\Clergy\\Controllers\\Events::delete/$1', ['filter' => 'permission:event.delete']);
        $routes->post('register/(:num)', '\\Clergy\\Controllers\\EventsRegistrations::register/$1', ['filter' => 'permission:event.register']);
        $routes->post('registrations/(:num)/status', '\\Clergy\\Controllers\\EventsRegistrations::updateRegistrationStatus/$1', ['filter' => 'permission:event.checkin']);
    });

    $routes->group('event-calendars', ['filter' => 'permission:event.edit'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\EventCalendars::index');
        $routes->post('store', '\\Clergy\\Controllers\\EventCalendars::store');
    });

    // -------------------------------------------------------------------------
    // Follow-up Workflow – sequences, actions, templates, manual trigger
    // -------------------------------------------------------------------------
    $routes->group('followup-workflow', ['filter' => 'permission:followup.view'], function ($routes) {
        // Sequences – list
        $routes->get('sequences', '\\Clergy\\Controllers\\FollowupSequences::sequences');

        // Sequences – create / edit form
        $routes->get('sequences/create', '\\Clergy\\Controllers\\FollowupSequences::edit_sequence/0', ['filter' => 'permission:followup.manage']);
        $routes->get('sequences/edit/(:num)', '\\Clergy\\Controllers\\FollowupSequences::edit_sequence/$1', ['filter' => 'permission:followup.manage']);

        // Sequences – save (create = id 0, update = id > 0)
        $routes->post('sequences/save', '\\Clergy\\Controllers\\FollowupSequences::saveSequence/0', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/save/(:num)', '\\Clergy\\Controllers\\FollowupSequences::saveSequence/$1', ['filter' => 'permission:followup.manage']);

        // Sequences – AJAX toggle / delete / clone-from-upstream
        $routes->post('sequences/toggle/(:num)', '\\Clergy\\Controllers\\FollowupSequences::toggleSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/set-default/(:num)', '\\Clergy\\Controllers\\FollowupSequences::setDefaultSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/delete/(:num)', '\\Clergy\\Controllers\\FollowupSequences::deleteSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/clone/(:num)', '\\Clergy\\Controllers\\FollowupSequences::cloneSequence/$1', ['filter' => 'permission:followup.manage']);

        // Actions – AJAX save (create = id 0, update = id > 0) and delete
        $routes->post('actions/save', '\\Clergy\\Controllers\\FollowupSequences::saveAction/0', ['filter' => 'permission:followup.manage']);
        $routes->post('actions/save/(:num)', '\\Clergy\\Controllers\\FollowupSequences::saveAction/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('actions/delete/(:num)', '\\Clergy\\Controllers\\FollowupSequences::deleteAction/$1', ['filter' => 'permission:followup.manage']);

        // Templates – list
        $routes->get('templates', '\\Clergy\\Controllers\\FollowupTemplates::templates');

        // Templates – AJAX save (create = id 0, update = id > 0) and delete
        $routes->post('templates/save', '\\Clergy\\Controllers\\FollowupTemplates::saveTemplate/0', ['filter' => 'permission:followup.manage']);
        $routes->post('templates/save/(:num)', '\\Clergy\\Controllers\\FollowupTemplates::saveTemplate/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('templates/delete/(:num)', '\\Clergy\\Controllers\\FollowupTemplates::deleteTemplate/$1', ['filter' => 'permission:followup.manage']);

        // Templates – preview
        $routes->get('templates/preview/(:num)', '\\Clergy\\Controllers\\FollowupTemplates::preview_template/$1');

        // Templates – live parsed preview (AJAX, uses real visitor data + parse_template helper)
        $routes->post('templates/preview-parsed', '\\Clergy\\Controllers\\FollowupTemplates::previewParsed');

        // Manual trigger (bypasses scheduling)
        $routes->post('manual-trigger/(:num)/(:num)', '\\Clergy\\Controllers\\FollowupTasks::manual_trigger/$1/$2', ['filter' => 'permission:followup.manage']);

        // Human-task queue (call + manual task types)
        $routes->get('tasks', '\\Clergy\\Controllers\\FollowupTasks::callTasks');
        $routes->post('tasks/complete/(:num)', '\\Clergy\\Controllers\\FollowupTasks::completeCallTask/$1', ['filter' => 'permission:followup.manage']);

        // Legacy /followup-workflow/call-tasks — retired slug. GETs redirect
        // preserving status/type filters; POSTs alias (bodies can't redirect).
        $routes->get('call-tasks', static function () {
            $qs = (string) service('request')->getServer('QUERY_STRING');
            return redirect()->to(base_url('followup-workflow/tasks') . ($qs !== '' ? '?' . $qs : ''));
        });
        $routes->post('call-tasks/complete/(:num)', '\\Clergy\\Controllers\\FollowupTasks::completeCallTask/$1', ['filter' => 'permission:followup.manage']);
    });

    // -------------------------------------------------------------------------
    // Vestry governance — meetings, motions, votes
    // -------------------------------------------------------------------------
    $routes->group('vestry', ['filter' => 'permission:governance.vestry.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Vestry::index');
        $routes->get('create', '\\Clergy\\Controllers\\Vestry::createMeeting', ['filter' => 'permission:governance.vestry.manage']);
        $routes->post('store', '\\Clergy\\Controllers\\Vestry::storeMeeting', ['filter' => 'permission:governance.vestry.manage']);
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Vestry::view/$1');
        $routes->post('meeting/(:num)/motion', '\\Clergy\\Controllers\\Vestry::storeMotion/$1', ['filter' => 'permission:governance.vestry.manage']);
        $routes->post('motion/(:num)/vote', '\\Clergy\\Controllers\\Vestry::castVote/$1', ['filter' => 'permission:vestry.motion.vote']);
        $routes->get('motion/(:num)/close', '\\Clergy\\Controllers\\Vestry::closeMotion/$1', ['filter' => 'permission:governance.vestry.manage']);
    });
});
