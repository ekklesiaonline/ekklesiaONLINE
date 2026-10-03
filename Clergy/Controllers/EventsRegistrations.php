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

        if ($this->isEventAtCapacity($event)) {
            return redirect()->back()->withInput()->with('error', lang('Members.capacityReached'));
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

        if ($this->eventRegistrationModel->insert($payload)) {
            return redirect()->to('/events/view/' . (int) $id)->with('success', lang('Members.registrationSaved'));
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

        if ($this->eventRegistrationModel->update((int) $registrationId, ['status' => $status])) {
            return redirect()->to('/events/view/' . (int) ($registration['event_id'] ?? 0))->with('success', lang('Members.registrationUpdated'));
        }

        return redirect()->back()->with('error', lang('Members.failedUpdateStatus'));
    }
}
