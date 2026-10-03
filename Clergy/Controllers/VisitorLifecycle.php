<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\VisitorDirectoryService;
use App\Services\PersonConversionService;

/**
 * Visitor lifecycle endpoints: member promotion, conversion revert, and
 * regular-attender transitions. Extracted from Visitors (phase 2 split);
 * routes and behavior are unchanged.
 */
class VisitorLifecycle extends BaseController
{
    private VisitorDirectoryService $visitorDirectory;

    public function __construct()
    {
        $this->visitorDirectory = new VisitorDirectoryService();
    }

    /**
     * POST visitors/promote/:personId
     *
     * The single canonical visitor→member promotion endpoint. Three modes:
     *   mark_member     — person only, no portal account.
     *   link_user       — link an existing in-parish user account.
     *   create_account  — create a new portal account (requires member.create).
     *
     * All modes run through PersonConversionService::convertToMember, which
     * transitions the affiliation via MembershipPathwayService (history row
     * written), links/creates the user, marks conversion markers, and skips
     * pending follow-ups.
     */
    public function promote($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (!empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorAlreadyConverted'));
        }

        $rules = [
            'mode' => 'required|in_list[mark_member,link_user,create_account]',
        ];

        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'link_user') {
            $rules['link_user_id'] = 'required|is_natural_no_zero';
        } elseif ($mode === 'create_account') {
            $rules['account_email']    = 'required|valid_email';
            $rules['account_password'] = 'required|min_length[8]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);
        $actor    = (int) auth()->id();

        if ($mode === 'link_user') {
            $linkUserId = (int) $this->request->getPost('link_user_id');

            // The linked account must already belong to this parish.
            $inScope = db_connect()->table('parish_affiliation')
                ->where('linked_user_id', $linkUserId)
                ->where('parish_id', $parishId)
                ->where('deleted_at IS NULL', null, false)
                ->countAllResults() > 0;

            if (! $inScope) {
                return redirect()->back()->with('error', lang('Visitors.promoteLinkOutOfScope'));
            }

            $converted = PersonConversionService::convertToMember(
                $personId, $parishId, $linkUserId, null, null, $actor
            );
        } elseif ($mode === 'create_account') {
            if ($redirect = $this->requirePermission('member.create')) {
                return $redirect;
            }

            $converted = PersonConversionService::convertToMember(
                $personId,
                $parishId,
                null,
                (string) $this->request->getPost('account_email'),
                (string) $this->request->getPost('account_password'),
                $actor
            );
        } else {
            $converted = PersonConversionService::convertToMember(
                $personId, $parishId, null, null, null, $actor
            );
        }

        if (! $converted) {
            return redirect()->back()->with('error', lang('Visitors.visitorConversionFailed'));
        }

        $this->audit()->log('visitor.promote_member', [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'new_values'  => ['membership_status' => 'member'],
            'meta'        => ['mode' => $mode],
        ]);

        // People::view() keys on profile.id, not person.id — resolve the
        // profile so the post-conversion landing is the canonical person page.
        // Visitors promoted without a portal user may have no profile yet;
        // fall back to the (now-converted) visitor detail page in that case.
        $profileId = (int) (db_connect()->table('profile')
            ->where('person_id', $personId)
            ->where('deleted_at IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray()['id'] ?? 0);

        $target = $profileId > 0 ? '/people/view/' . $profileId : '/visitors/view/' . $personId;

        return redirect()->to($target)
            ->with('success', lang('Visitors.promoteMemberSuccess'));
    }


    /**
     * POST visitors/revert-conversion/:personId
     * Undo a visitor→member conversion: status back to visitor, markers cleared.
     */
    public function revertConversion($id)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorNotConverted'));
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);

        $this->visitorDirectory->revertConversion($personId, $parishId, (int) auth()->id());

        $this->audit()->log('visitor.revert_conversion', [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'old_values'  => ['membership_status' => 'member'],
            'new_values'  => ['membership_status' => 'visitor'],
        ]);

        return redirect()->to('/visitors/view/' . $personId)
            ->with('success', lang('Visitors.conversionRevertSuccess'));
    }


    /**
     * POST visitors/promote-attender/:personId
     * Promote a visitor to regular_attender through the canonical pathway.
     */
    public function promoteAttender($id)
    {
        return $this->attenderTransition($id, 'regular_attender');
    }


    /**
     * POST visitors/demote/:personId
     * Move a regular_attender back to visitor through the canonical pathway.
     */
    public function demoteToVisitor($id)
    {
        return $this->attenderTransition($id, 'visitor');
    }


    /**
     * Shared visitor ⇄ regular_attender transition handler. Only those two
     * targets are reachable here — member promotion is Phase-3 territory.
     */
    private function attenderTransition($id, string $toStatus)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $visitor  = $this->visitorDirectory->findByPerson((int) $id, $parishId);

        if (!$visitor) {
            return redirect()->to('/visitors')->with('error', lang('Visitors.visitorNotFound'));
        }

        if (!empty($visitor['converted_to_member_at'])) {
            return redirect()->to('/visitors/view/' . $id)
                ->with('error', lang('Visitors.visitorAlreadyConverted'));
        }

        $personId = (int) ($visitor['person_id'] ?? $visitor['id']);
        $from     = (string) ($visitor['membership_status'] ?? 'visitor');

        if ($from === $toStatus) {
            return redirect()->back()
                ->with('error', lang('Visitors.transitionNoop'));
        }

        $reason = $toStatus === 'regular_attender'
            ? 'Promoted to regular attender'
            : 'Moved back to visitor';

        $result = (new \App\Services\MembershipPathwayService())->transitionForPerson(
            $personId,
            $parishId,
            $toStatus,
            (int) auth()->id(),
            $reason
        );

        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->with('error', lang('Visitors.transitionFailed'));
        }

        $this->audit()->log('visitor.' . ($toStatus === 'regular_attender' ? 'promote_attender' : 'demote'), [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'entity_id'   => $personId,
            'old_values'  => ['membership_status' => $from],
            'new_values'  => ['membership_status' => $toStatus],
        ]);

        $message = $toStatus === 'regular_attender'
            ? lang('Visitors.promoteAttenderSuccess')
            : lang('Visitors.demoteVisitorSuccess');

        return redirect()->back()->with('success', $message);
    }
}
