<?php

namespace App\Services;

use App\Models\EventInviteeModel;
use App\Models\EventRegistrationModel;
use App\Models\FamilyMemberModel;
use App\Models\FamilyModel;
use App\Models\LeadershipPositionModel;
use App\Models\MemberParishAffiliationModel;
use App\Models\TeamMemberModel;

class EventVisibilityService
{
    private MemberParishAffiliationModel $memberParishAffiliationModel;
    private TeamMemberModel $teamMemberModel;
    private FamilyMemberModel $familyMemberModel;
    private FamilyModel $familyModel;
    private LeadershipPositionModel $leadershipPositionModel;
    private EventInviteeModel $eventInviteeModel;
    private EventRegistrationModel $eventRegistrationModel;

    public function __construct(
        ?MemberParishAffiliationModel $memberParishAffiliationModel = null,
        ?TeamMemberModel $teamMemberModel = null,
        ?FamilyMemberModel $familyMemberModel = null,
        ?FamilyModel $familyModel = null,
        ?LeadershipPositionModel $leadershipPositionModel = null,
        ?EventInviteeModel $eventInviteeModel = null,
        ?EventRegistrationModel $eventRegistrationModel = null
    ) {
        $this->memberParishAffiliationModel = $memberParishAffiliationModel ?? model('MemberParishAffiliationModel');
        $this->teamMemberModel = $teamMemberModel ?? model('TeamMemberModel');
        $this->familyMemberModel = $familyMemberModel ?? model('FamilyMemberModel');
        $this->familyModel = $familyModel ?? model('FamilyModel');
        $this->leadershipPositionModel = $leadershipPositionModel ?? model('LeadershipPositionModel');
        $this->eventInviteeModel = $eventInviteeModel ?? model('EventInviteeModel');
        $this->eventRegistrationModel = $eventRegistrationModel ?? model('EventRegistrationModel');
    }

    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     */
    public function canManageEvent(array $event, array $context): bool
    {
        $eventParishId = (int) ($event['parish_id'] ?? 0);
        $currentParishId = (int) ($context['parish_id'] ?? 0);
        if ($eventParishId === $currentParishId) {
            return true;
        }

        return (string) ($event['scope_level'] ?? 'parish') === 'diocese'
            && (int) ($event['scope_diocese_id'] ?? 0) === (int) ($context['diocese_id'] ?? 0)
            && !empty($context['can_manage_diocese_scope']);
    }

    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     */
    public function isVisibleEvent(array $event, array $context): bool
    {
        $canManage = !empty($context['can_edit_events'])
            && $this->canManageEvent($event, $context);

        if ((string) ($event['status'] ?? 'draft') === 'draft') {
            return $canManage;
        }

        if ($canManage) {
            return true;
        }

        $eventScopeLevel = (string) ($event['scope_level'] ?? 'parish');
        $eventParishId = (int) ($event['parish_id'] ?? 0);
        $eventDioceseId = (int) ($event['scope_diocese_id'] ?? 0);
        $currentParishId = (int) ($context['parish_id'] ?? 0);
        $currentDioceseId = (int) ($context['diocese_id'] ?? 0);

        if ($eventScopeLevel === 'parish') {
            return $eventParishId > 0
                && $eventParishId === $currentParishId
                && $this->isReleasedToParishAudience($event)
                && $this->isAudienceVisibleToCurrentUser($event, $context);
        }

        return $eventScopeLevel === 'diocese'
            && $eventDioceseId > 0
            && $eventDioceseId === $currentDioceseId
            && $this->isReleasedToParishAudience($event)
            && $this->isAudienceVisibleToCurrentUser($event, $context);
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function filterVisibleEvents(array $events, array $context): array
    {
        return array_values(array_filter($events, fn(array $event): bool => $this->isVisibleEvent($event, $context)));
    }

    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     */
    public function canManageRegistrationForEvent(array $event, int $registrationParishId, array $context): bool
    {
        $currentParishId = (int) ($context['parish_id'] ?? 0);
        $eventScopeLevel = (string) ($event['scope_level'] ?? 'parish');
        $eventDioceseId = (int) ($event['scope_diocese_id'] ?? 0);

        if ($eventScopeLevel !== 'diocese') {
            return $registrationParishId === $currentParishId;
        }

        return $eventDioceseId > 0
            && $eventDioceseId === (int) ($context['diocese_id'] ?? 0)
            && !empty($context['can_manage_diocese_scope']);
    }

    /**
     * @param array<string,mixed> $event
     */
    private function isReleasedToParishAudience(array $event): bool
    {
        if ((string) ($event['status'] ?? 'draft') === 'draft') {
            return false;
        }

        $publishAt = trim((string) ($event['publish_at'] ?? ''));
        if ($publishAt === '') {
            return true;
        }

        $publishTimestamp = strtotime($publishAt);

        return $publishTimestamp !== false && $publishTimestamp <= time();
    }

    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     */
    private function isAudienceVisibleToCurrentUser(array $event, array $context): bool
    {
        $visibility = trim((string) ($event['visibility'] ?? 'members'));
        if ($visibility === 'people' || !in_array($visibility, ['members', 'public', 'staff', 'volunteers', 'invited', 'registered_only', 'household'], true)) {
            $visibility = 'members';
        }
        if ($visibility === 'public') {
            return true;
        }

        $parishId = (int) ($context['parish_id'] ?? 0);
        $userId = (int) ($context['user_id'] ?? 0);
        if ($parishId <= 0 || $userId <= 0) {
            return false;
        }

        $affiliation = $this->memberParishAffiliationModel->getAudienceAffiliationForParish($userId, $parishId);
        if (!is_array($affiliation) && !in_array($visibility, ['staff', 'registered_only'], true)) {
            return false;
        }

        if ($visibility === 'staff') {
            return $this->isStaffAudienceVisible($event, $context, $userId);
        }

        if ($visibility === 'volunteers') {
            $teamId = (int) ($event['audience_team_id'] ?? 0);
            return $teamId > 0 && $this->teamMemberModel->isUserOnTeam($teamId, $userId);
        }

        if ($visibility === 'invited') {
            return $this->eventInviteeModel->isUserInvited((int) ($event['id'] ?? 0), $userId);
        }

        if ($visibility === 'registered_only') {
            return $this->eventRegistrationModel->hasActiveRegistrationForUser((int) ($event['id'] ?? 0), $userId);
        }

        if ($visibility === 'household') {
            $familyId = (int) ($event['audience_family_id'] ?? 0);
            if ($familyId <= 0) {
                return false;
            }

            $family = $this->familyModel->find($familyId);
            if (!is_array($family)) {
                return false;
            }

            return (int) ($family['head_of_household_id'] ?? 0) === $userId
                || $this->familyMemberModel->isUserInFamily($familyId, $userId);
        }

        if (array_key_exists('is_member', $affiliation) && $affiliation['is_member'] !== null) {
            return (int) ($affiliation['is_member'] ?? 0) === 1;
        }

        return in_array((string) ($affiliation['membership_status'] ?? ''), ['active', 'inactive'], true);
    }

    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     */
    private function isStaffAudienceVisible(array $event, array $context, int $userId): bool
    {
        $positions = $this->leadershipPositionModel->getActivePositionsForUser($userId);
        if ($positions === []) {
            return false;
        }

        $eventScope = (string) ($event['scope_level'] ?? 'parish');
        $eventParishId = (int) ($event['parish_id'] ?? 0);
        $eventDioceseId = $eventScope === 'diocese'
            ? (int) ($event['scope_diocese_id'] ?? 0)
            : (int) ($context['diocese_id'] ?? 0);

        foreach ($positions as $position) {
            $scopeType = (string) ($position['scope_type'] ?? '');

            if ($eventScope === 'parish' && $scopeType === 'parish' && (int) ($position['parish_id'] ?? 0) === $eventParishId) {
                return true;
            }

            if ($eventScope === 'diocese') {
                if ($scopeType === 'diocese' && (int) ($position['diocese_id'] ?? 0) === $eventDioceseId) {
                    return true;
                }

                if ($scopeType === 'parish' && (int) ($context['diocese_id'] ?? 0) === $eventDioceseId) {
                    return true;
                }
            }
        }

        return false;
    }
}
