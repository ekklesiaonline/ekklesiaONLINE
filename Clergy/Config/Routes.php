<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Clergy module routes
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function ($routes) {
    // Navbar badge poller – returns pending call-task count as JSON.
    $routes->get('api/followup/pending-calls', '\\Clergy\\Controllers\\Visitors::pendingCallsApi', ['filter' => 'permission:followup.view']);

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

        $routes->post('attach-parish', '\\Clergy\\Controllers\\Members::attachParish', ['filter' => 'permission:member.edit']);
        $routes->post('detach-parish', '\\Clergy\\Controllers\\Members::detachParish', ['filter' => 'permission:member.edit']);
        $routes->post('set-home-parish', '\\Clergy\\Controllers\\Members::setHomeParish', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/approve/(:num)', '\\Diocesan\\Controllers\\RegistrationRequests::approve/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/bulk-action', '\\Diocesan\\Controllers\\RegistrationRequests::bulkAction', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/dismiss/(:num)', '\\Diocesan\\Controllers\\RegistrationRequests::dismiss/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/ban/(:num)', '\\Diocesan\\Controllers\\RegistrationRequests::ban/$1', ['filter' => 'permission:member.edit']);
        $routes->post('registrations/delete/(:num)', '\\Diocesan\\Controllers\\RegistrationRequests::delete/$1', ['filter' => 'permission:member.delete']);
        $routes->post('registrations/escalate/(:num)', '\\Diocesan\\Controllers\\RegistrationRequests::escalate/$1', ['filter' => 'permission:member.edit']);
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
        $routes->get('leadership-eligibility/(:num)', '\\Clergy\\Controllers\\People::leadershipEligibility/$1', ['filter' => 'permission:member.edit']);
        $routes->post('leadership/(:num)', '\\Clergy\\Controllers\\People::addLeadershipPosition/$1', ['filter' => 'permission:member.edit']);
        $routes->post('leadership/(:num)/update', '\\Clergy\\Controllers\\People::updateLeadershipPosition/$1', ['filter' => 'permission:member.edit']);
        $routes->post('leadership/(:num)/delete', '\\Clergy\\Controllers\\People::deleteLeadershipPosition/$1', ['filter' => 'permission:member.edit']);
        $routes->post('donation/(:num)', '\\Clergy\\Controllers\\People::addDonation/$1', ['filter' => 'permission:donation.create']);
        $routes->post('donation/(:num)/update', '\\Clergy\\Controllers\\People::updateDonation/$1', ['filter' => 'permission:donation.edit']);
        $routes->post('donation/(:num)/delete', '\\Clergy\\Controllers\\People::deleteDonation/$1', ['filter' => 'permission:donation.delete']);
        $routes->post('pledge/(:num)', '\\Clergy\\Controllers\\People::addPledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('pledge/(:num)/update', '\\Clergy\\Controllers\\People::updatePledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('pledge/(:num)/delete', '\\Clergy\\Controllers\\People::deletePledge/$1', ['filter' => 'permission:annual_pledge.manage']);
        $routes->post('sacrament/(:num)', '\\Clergy\\Controllers\\People::addSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/update', '\\Clergy\\Controllers\\People::updateSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/delete', '\\Clergy\\Controllers\\People::deleteSacrament/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/(:num)/participant', '\\Clergy\\Controllers\\People::addSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/participant/(:num)/update', '\\Clergy\\Controllers\\People::updateSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('sacrament/participant/(:num)/delete', '\\Clergy\\Controllers\\People::deleteSacramentParticipant/$1', ['filter' => 'permission:member.edit']);
        $routes->post('family/(:num)/create', '\\Clergy\\Controllers\\People::createFamilyForPerson/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/join', '\\Clergy\\Controllers\\People::addPersonToFamily/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/leave', '\\Clergy\\Controllers\\People::removePersonFromFamily/$1', ['filter' => 'permission:family.manage']);
        $routes->post('family/(:num)/relationship', '\\Clergy\\Controllers\\People::updateFamilyRelationship/$1', ['filter' => 'permission:family.manage']);
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
        $routes->post('promote/(:num)', '\\Clergy\\Controllers\\Visitors::promote/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('revert-conversion/(:num)', '\\Clergy\\Controllers\\Visitors::revertConversion/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('promote-attender/(:num)', '\\Clergy\\Controllers\\Visitors::promoteAttender/$1', ['filter' => 'permission:visitor.edit']);
        $routes->post('demote/(:num)', '\\Clergy\\Controllers\\Visitors::demoteToVisitor/$1', ['filter' => 'permission:visitor.edit']);
        // AJAX endpoints
        $routes->get('followup-status/(:num)', '\\Clergy\\Controllers\\Visitors::getFollowupStatus/$1', ['filter' => 'permission:followup.view']);
        $routes->post('followup-trigger/(:num)', '\\Clergy\\Controllers\\Visitors::triggerFollowupAction/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('assign-sequence/(:num)', '\\Clergy\\Controllers\\Visitors::assignSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->get('followup-manual/(:num)', '\\Clergy\\Controllers\\Visitors::followupManual/$1', ['filter' => 'permission:followup.view']);
        $routes->post('followup-log/mark-done/(:num)',   '\Clergy\Controllers\Visitors::logMarkDone/$1',   ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/reschedule/(:num)',  '\Clergy\Controllers\Visitors::logReschedule/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/skip/(:num)',        '\Clergy\Controllers\Visitors::logSkip/$1',        ['filter' => 'permission:followup.manage']);
        $routes->post('followup-log/mark-failed/(:num)', '\Clergy\Controllers\Visitors::logMarkFailed/$1',  ['filter' => 'permission:followup.manage']);
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
    $routes->post('guests/followup-trigger/(:num)', '\\Clergy\\Controllers\\Visitors::triggerFollowupAction/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/assign-sequence/(:num)', '\\Clergy\\Controllers\\Visitors::assignSequence/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/mark-done/(:num)',   '\Clergy\Controllers\Visitors::logMarkDone/$1',   ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/reschedule/(:num)',  '\Clergy\Controllers\Visitors::logReschedule/$1', ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/skip/(:num)',        '\Clergy\Controllers\Visitors::logSkip/$1',        ['filter' => 'permission:followup.manage']);
    $routes->post('guests/followup-log/mark-failed/(:num)', '\Clergy\Controllers\Visitors::logMarkFailed/$1',  ['filter' => 'permission:followup.manage']);

    // Legacy followup/* routes retired — redirected to new workflow system.
    $routes->get('followup', static fn() => redirect()->to(base_url('followup-workflow/call-tasks')));
    $routes->get('followup/(:any)', static fn() => redirect()->to(base_url('followup-workflow/call-tasks')));
    $routes->post('followup/(:any)', static fn() => redirect()->to(base_url('followup-workflow/call-tasks')));

    $routes->group('events', ['filter' => 'permission:event.view'], function ($routes) {
        $routes->get('/', '\\Clergy\\Controllers\\Events::index');
        $routes->get('calendar', '\\Clergy\\Controllers\\Events::calendar');
        $routes->get('view/(:num)', '\\Clergy\\Controllers\\Events::view/$1');
        $routes->get('create', '\\Clergy\\Controllers\\Events::create', ['filter' => 'permission:event.create']);
        $routes->post('store', '\\Clergy\\Controllers\\Events::store', ['filter' => 'permission:event.create']);
        $routes->get('edit/(:num)', '\\Clergy\\Controllers\\Events::edit/$1', ['filter' => 'permission:event.edit']);
        $routes->post('update/(:num)', '\\Clergy\\Controllers\\Events::update/$1', ['filter' => 'permission:event.edit']);
        $routes->post('delete/(:num)', '\\Clergy\\Controllers\\Events::delete/$1', ['filter' => 'permission:event.delete']);
        $routes->post('register/(:num)', '\\Clergy\\Controllers\\Events::register/$1', ['filter' => 'permission:event.register']);
        $routes->post('registrations/(:num)/status', '\\Clergy\\Controllers\\Events::updateRegistrationStatus/$1', ['filter' => 'permission:event.checkin']);
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
        $routes->get('sequences', '\\Clergy\\Controllers\\FollowupWorkflow::sequences');

        // Sequences – create / edit form
        $routes->get('sequences/create', '\\Clergy\\Controllers\\FollowupWorkflow::edit_sequence/0', ['filter' => 'permission:followup.manage']);
        $routes->get('sequences/edit/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::edit_sequence/$1', ['filter' => 'permission:followup.manage']);

        // Sequences – save (create = id 0, update = id > 0)
        $routes->post('sequences/save', '\\Clergy\\Controllers\\FollowupWorkflow::saveSequence/0', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/save/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::saveSequence/$1', ['filter' => 'permission:followup.manage']);

        // Sequences – AJAX toggle / delete
        $routes->post('sequences/toggle/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::toggleSequence/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('sequences/delete/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::deleteSequence/$1', ['filter' => 'permission:followup.manage']);

        // Actions – AJAX save (create = id 0, update = id > 0) and delete
        $routes->post('actions/save', '\\Clergy\\Controllers\\FollowupWorkflow::saveAction/0', ['filter' => 'permission:followup.manage']);
        $routes->post('actions/save/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::saveAction/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('actions/delete/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::deleteAction/$1', ['filter' => 'permission:followup.manage']);

        // Templates – list
        $routes->get('templates', '\\Clergy\\Controllers\\FollowupWorkflow::templates');

        // Templates – AJAX save (create = id 0, update = id > 0) and delete
        $routes->post('templates/save', '\\Clergy\\Controllers\\FollowupWorkflow::saveTemplate/0', ['filter' => 'permission:followup.manage']);
        $routes->post('templates/save/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::saveTemplate/$1', ['filter' => 'permission:followup.manage']);
        $routes->post('templates/delete/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::deleteTemplate/$1', ['filter' => 'permission:followup.manage']);

        // Templates – preview
        $routes->get('templates/preview/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::preview_template/$1');

        // Templates – live parsed preview (AJAX, uses real visitor data + parse_template helper)
        $routes->post('templates/preview-parsed', '\\Clergy\\Controllers\\FollowupWorkflow::previewParsed');

        // Manual trigger (bypasses scheduling)
        $routes->post('manual-trigger/(:num)/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::manual_trigger/$1/$2', ['filter' => 'permission:followup.manage']);

        // Call tasks
        $routes->get('call-tasks', '\\Clergy\\Controllers\\FollowupWorkflow::callTasks');
        $routes->post('call-tasks/complete/(:num)', '\\Clergy\\Controllers\\FollowupWorkflow::completeCallTask/$1', ['filter' => 'permission:followup.manage']);
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
