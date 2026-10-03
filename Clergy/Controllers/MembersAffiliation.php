<?php

namespace Clergy\Controllers;

use App\Services\MembershipStatusService;

/**
 * MembersAffiliation
 *
 * Parish affiliation operations for member records: attach, detach, and
 * set-home-parish. Extends Members to reuse the shared scope helpers
 * (getMemberParishAffiliationModel, isParishWithinCurrentDioceseScope).
 * Extracted from Members (phase 2 split); routes and behavior unchanged.
 */
class MembersAffiliation extends Members
{

    public function attachParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $parishId = (int) $this->request->getPost('parish_id');
        $allowedStatuses = ['visitor', 'regular_attender', 'member', 'inactive', 'transferred', 'deceased'];
        $status = MembershipStatusService::normalizeAffiliationStatus((string) ($this->request->getPost('membership_status') ?? 'visitor'));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'visitor';
        }
        $isHome = (int) ($this->request->getPost('is_primary') ?? 0) === 1;

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $targetParish = $this->parishModel->findWithHierarchy($parishId);
        if (!$targetParish || !$this->isParishWithinCurrentDioceseScope((int) ($targetParish['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $failure = $this->memberAffiliations->attachToParish(
            (int) $profile['user_id'],
            $parishId,
            $status,
            $isHome,
            (int) $profile['id'],
            isset($profile['province_id']) ? (int) $profile['province_id'] : null,
            isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null
        );

        if ($failure !== null) {
            return match ($failure) {
                'attach' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableAttachParish'), 500, 'PARISH_ATTACH_FAILED'), 500),
                'home' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSetHomeParish'), 500, 'HOME_PARISH_UPDATE_FAILED'), 500),
                'sync' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500),
                default => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.attachTransactionFailed'), 500, 'PARISH_ATTACH_TRANSACTION_FAILED'), 500),
            };
        }

        $this->audit()->log('member.parish_attached', [
            'parish_id' => $parishId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'new_values' => [
                'profile_id' => $profileId,
                'parish_id' => $parishId,
                'membership_status' => $status,
                'is_primary' => $isHome,
            ],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.parishAttachedSuccess')]);
    }


    public function detachParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $parishId = (int) $this->request->getPost('parish_id');

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $affiliations = $this->getMemberParishAffiliationModel()->getUserAffiliations((int) $profile['user_id']);
        if (count($affiliations) <= 1) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberMustRemainAttached'), 400, 'MEMBER_LAST_PARISH_CONSTRAINT'), 400);
        }

        $targetParish = $this->parishModel->findWithHierarchy($parishId);
        if (!$targetParish || !$this->isParishWithinCurrentDioceseScope((int) ($targetParish['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $failure = $this->memberAffiliations->detachFromParish(
            (int) $profile['user_id'],
            $parishId,
            (int) $profile['id'],
            isset($profile['province_id']) ? (int) $profile['province_id'] : null,
            isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null
        );

        if ($failure !== null) {
            return match ($failure) {
                'detach' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableDetachParish'), 500, 'PARISH_DETACH_FAILED'), 500),
                'sync' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500),
                default => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.detachTransactionFailed'), 500, 'PARISH_DETACH_TRANSACTION_FAILED'), 500),
            };
        }

        $this->audit()->log('member.parish_detached', [
            'parish_id' => $parishId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'old_values' => [
                'profile_id' => $profileId,
                'parish_id' => $parishId,
            ],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.parishDetachedSuccess')]);
    }


    public function setHomeParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $parishId = (int) $this->request->getPost('parish_id');

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $affiliated = $this->getMemberParishAffiliationModel()->isUserAffiliatedWithParish((int) $profile['user_id'], $parishId, true);
        if (!$affiliated) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotAffiliatedParish'), 400, 'PARISH_AFFILIATION_REQUIRED'), 400);
        }

        $targetParish = $this->parishModel->findWithHierarchy($parishId);
        if (!$targetParish || !$this->isParishWithinCurrentDioceseScope((int) ($targetParish['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $failure = $this->memberAffiliations->setHomeParish(
            (int) $profile['user_id'],
            $parishId,
            (int) $profile['id'],
            isset($profile['province_id']) ? (int) $profile['province_id'] : null,
            isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null
        );

        if ($failure !== null) {
            return match ($failure) {
                'home' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSetHomeParish'), 500, 'HOME_PARISH_UPDATE_FAILED'), 500),
                'sync' => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500),
                default => $this->jsonResponse($this->apiErrorPayload(lang('Clergy.setHomeTransactionFailed'), 500, 'HOME_PARISH_TRANSACTION_FAILED'), 500),
            };
        }

        $this->audit()->log('member.home_parish_changed', [
            'parish_id' => $parishId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'new_values' => [
                'profile_id' => $profileId,
                'parish_id' => $parishId,
            ],
        ]);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.homeParishUpdatedSuccess')]);
    }
}
