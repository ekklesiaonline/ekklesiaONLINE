<?php

namespace Clergy\Controllers;

/**
 * EventsRegistrations
 *
 * Event registration endpoints: public registration and check-in/status
 * management. Extends Events to reuse the shared scope/visibility helpers
 * (getViewableParishEvent, canManageRegistrationForEvent, isEventAtCapacity).
 * Extracted from Events (phase 2 split); routes and behavior unchanged.
 */
class EventsRegistrations extends Events
{

    public function register($id)
    {
        if ($redirect = $this->requirePermission('event.register')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->getViewableParishEvent((int) $id);
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        if ((int) ($event['requires_registration'] ?? 1) !== 1) {
            return redirect()->to('/events/view/' . (int) $id)->with('error', lang('Members.registrationNotRequired'));
        }

        $rules = [
            'user_id'     => 'permit_empty|integer|greater_than[0]',
            'guest_name'  => 'permit_empty|max_length[255]',
            'guest_email' => 'permit_empty|valid_email|max_length[255]',
            'guest_phone' => 'permit_empty|max_length[50]',
            'notes'       => 'permit_empty|max_length[2000]',
        ];

        // Validate the POST payload itself: validating the merged request
        // would let query/cookie values stand in for the fields being saved.
        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $memberUserId = (int) ($this->request->getPost('user_id') ?? 0);
        $guestName = trim((string) ($this->request->getPost('guest_name') ?? ''));

        if ($memberUserId <= 0 && $guestName === '') {
            return redirect()->back()->withInput()->with('error', lang('Members.selectMemberOrVisitor'));
        }

        if ($memberUserId > 0) {
            $isAffiliated = $this->memberParishAffiliationModel
                ->isUserAffiliatedWithParish($memberUserId, (int) ($this->getOperationalParishId() ?? 0), true);

            if (!$isAffiliated) {
                return redirect()->back()->withInput()->with('error', lang('Members.memberOutsideScope'));
            }

            if ($this->eventRegistrationModel->registrationExistsForMember((int) $id, $memberUserId)) {
                return redirect()->back()->withInput()->with('error', lang('Members.alreadyRegistered'));
            }
        }

        $payload = [
            'event_id' => (int) $id,
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'user_id' => $memberUserId > 0 ? $memberUserId : null,
            'guest_name' => $memberUserId > 0 ? null : $guestName,
            'guest_email' => $memberUserId > 0 ? null : trim((string) ($this->request->getPost('guest_email') ?? '')),
            'guest_phone' => $memberUserId > 0 ? null : trim((string) ($this->request->getPost('guest_phone') ?? '')),
            'status' => 'registered',
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            // Serialize concurrent registrations on the event row so the
            // capacity check and the insert cannot race each other. (The
            // test suite runs on a shared SQLite connection, which has no
            // row-lock syntax — production is MySQL/InnoDB.)
            if ($db->DBDriver === 'MySQLi') {
                $db->query('SELECT id FROM ' . $db->prefixTable('event') . ' WHERE id = ? FOR UPDATE', [(int) $id]);
            }

            if ($this->isEventAtCapacity($event)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Members.capacityReached'));
            }

            $inserted = (bool) $this->eventRegistrationModel->insert($payload);
            if ($inserted && $db->transCommit() === false) {
                // Commit failed after a successful insert — nothing was
                // persisted, so do not report the registration as saved.
                $db->transRollback();
                $inserted = false;
            } elseif (! $inserted) {
                $db->transRollback();
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', lang('Members.failedSaveRegistration'));
        }

        if ($inserted) {
            return redirect()->to('/events/view/' . (int) $id)->with('success', lang('Members.registrationSaved'));
        }

        // Insert failed: a concurrent request that committed between our
        // existence check and insert surfaces here — report it as the
        // duplicate it is (the unique key on (event_id, user_id) is the
        // hard backstop, and may reject even when DBDebug suppresses errors).
        if ($memberUserId > 0
            && $this->eventRegistrationModel->registrationExistsForMember((int) $id, $memberUserId)) {
            return redirect()->back()->withInput()->with('error', lang('Members.alreadyRegistered'));
        }

        return redirect()->back()->withInput()->with('error', lang('Members.failedSaveRegistration'));
    }


    public function updateRegistrationStatus($registrationId)
    {
        if ($redirect = $this->requirePermission('event.checkin')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $registration = $this->eventRegistrationModel->find((int) $registrationId);
        if (!is_array($registration)) {
            return redirect()->to('/events')->with('error', lang('Members.registrationNotFound'));
        }

        $event = $this->getViewableParishEvent((int) ($registration['event_id'] ?? 0));
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        if (!$this->canManageRegistrationForEvent($event, (int) ($registration['parish_id'] ?? 0))) {
            return redirect()->to('/events')->with('error', lang('Members.unauthorizedAction'));
        }

        $status = trim((string) ($this->request->getPost('status') ?? ''));
        if (!in_array($status, ['registered', 'checked_in', 'cancelled'], true)) {
            return redirect()->back()->with('error', lang('Members.invalidStatus'));
        }

        if ($status === 'checked_in' && $this->isEventAtCapacity($event, (int) $registrationId)) {
            return redirect()->back()->with('error', lang('Members.cannotCheckIn'));
        }

        // Reactivating a cancelled row must respect capacity too — a
        // cancelled → registered transition takes a seat as well.
        if ($status === 'registered'
            && (string) ($registration['status'] ?? '') === 'cancelled'
            && $this->isEventAtCapacity($event)) {
            return redirect()->back()->with('error', lang('Members.capacityReached'));
        }

        // A member who re-registered has a newer active row; reactivating
        // this stale row would violate the (event_id, user_id) uniqueness.
        if ($status !== 'cancelled' && (int) ($registration['user_id'] ?? 0) > 0) {
            $hasActiveSibling = $this->eventRegistrationModel
                ->where('event_id', (int) $registration['event_id'])
                ->where('user_id', (int) $registration['user_id'])
                ->where('status !=', 'cancelled')
                ->where('id !=', (int) $registrationId)
                ->countAllResults() > 0;
            if ($hasActiveSibling) {
                return redirect()->back()->with('error', lang('Members.alreadyRegistered'));
            }
        }

        if ($this->eventRegistrationModel->update((int) $registrationId, ['status' => $status])) {
            return redirect()->to('/events/view/' . (int) ($registration['event_id'] ?? 0))->with('success', lang('Members.registrationUpdated'));
        }

        return redirect()->back()->with('error', lang('Members.failedUpdateStatus'));
    }
}
