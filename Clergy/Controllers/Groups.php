<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Models\GroupMembershipModel;
use App\Services\SmallGroupService;

class Groups extends BaseController
{
    private SmallGroupService $groups;

    public function __construct()
    {
        $this->groups = new SmallGroupService();
    }

    /**
     * List small groups for the operational parish.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('groups.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        return view('Clergy\Views\Groups/index', array_merge($this->getCommonViewData(lang('Groups.title')), [
            'groups'    => model('SmallGroupModel')->groupsWithCounts($parishId),
            'canManage' => auth()->user()->can('groups.manage'),
        ]));
    }

    /**
     * New group form.
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('groups.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Clergy\Views\Groups/form', array_merge($this->getCommonViewData(lang('Groups.create')), [
            'group' => null,
        ]));
    }

    /**
     * Persist a new group.
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('groups.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        if (! $this->validate($this->groupRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $groupId = model('SmallGroupModel')->insert([
            'parish_id'    => $parishId,
            'name'         => trim((string) $this->request->getPost('name')),
            'description'  => trim((string) ($this->request->getPost('description') ?? '')) ?: null,
            'meeting_day'  => $this->request->getPost('meeting_day') ?: null,
            'meeting_time' => $this->request->getPost('meeting_time') ?: null,
            'location'     => trim((string) ($this->request->getPost('location') ?? '')) ?: null,
            'capacity'     => ($cap = $this->request->getPost('capacity')) !== null && $cap !== '' ? (int) $cap : null,
            'is_active'    => 1,
            'created_by'   => auth()->loggedIn() ? (int) auth()->id() : null,
        ]);

        if ($groupId === false) {
            return redirect()->back()->withInput()->with('error', lang('Groups.saveFailed'));
        }

        $this->audit()->log('groups.create', [
            'parish_id'   => $parishId,
            'entity_type' => 'small_group',
            'entity_id'   => (int) $groupId,
            'new_values'  => ['name' => (string) $this->request->getPost('name')],
        ]);

        return redirect()->to('/groups/view/' . (int) $groupId)->with('success', lang('App.successSaved'));
    }

    /**
     * Group detail: roster management.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('groups.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $id, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        $searchQuery = (string) ($this->request->getGet('q') ?? '');
        $canAssign = auth()->user()->can('groups.assign');

        return view('Clergy\Views\Groups/view', array_merge($this->getCommonViewData($group['name']), [
            'group'       => $group,
            'roster'      => model('GroupMembershipModel')->rosterForGroup((int) $group['id']),
            'roles'       => GroupMembershipModel::roles(),
            'assignable'  => $canAssign ? $this->groups->searchAssignablePeople($parishId, $searchQuery, (int) $group['id']) : [],
            'searchQuery' => $searchQuery,
            'canManage'   => auth()->user()->can('groups.manage'),
            'canAssign'   => $canAssign,
        ]));
    }

    /**
     * Edit group form.
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('groups.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $id, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        return view('Clergy\Views\Groups/form', array_merge($this->getCommonViewData(lang('Groups.edit')), [
            'group' => $group,
        ]));
    }

    /**
     * Persist group edits.
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('groups.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $id, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        if (! $this->validate($this->groupRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ok = model('SmallGroupModel')->update((int) $group['id'], [
            'name'         => trim((string) $this->request->getPost('name')),
            'description'  => trim((string) ($this->request->getPost('description') ?? '')) ?: null,
            'meeting_day'  => $this->request->getPost('meeting_day') ?: null,
            'meeting_time' => $this->request->getPost('meeting_time') ?: null,
            'location'     => trim((string) ($this->request->getPost('location') ?? '')) ?: null,
            'capacity'     => ($cap = $this->request->getPost('capacity')) !== null && $cap !== '' ? (int) $cap : null,
            'is_active'    => (int) $this->request->getPost('is_active') === 1 ? 1 : 0,
        ]);

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', lang('Groups.saveFailed'));
        }

        $this->audit()->log('groups.update', [
            'parish_id'   => $parishId,
            'entity_type' => 'small_group',
            'entity_id'   => (int) $group['id'],
            'new_values'  => ['name' => (string) $this->request->getPost('name')],
        ]);

        return redirect()->to('/groups/view/' . (int) $group['id'])->with('success', lang('App.successSaved'));
    }

    /**
     * Archive (soft-delete) a group. Membership rows are kept for history.
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('groups.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $id, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        if (! model('SmallGroupModel')->delete((int) $group['id'])) {
            return redirect()->back()->with('error', lang('Groups.deleteFailed'));
        }

        // Members of a deleted group lose their Stage 2 placement.
        try {
            (new \App\Services\PathwayStageService())->recomputeParish($parishId);
        } catch (\Throwable $e) {
            log_message('error', 'Pathway recompute after group delete failed: ' . $e->getMessage());
        }

        $this->audit()->log('groups.delete', [
            'parish_id'   => $parishId,
            'entity_type' => 'small_group',
            'entity_id'   => (int) $group['id'],
            'old_values'  => ['name' => (string) ($group['name'] ?? '')],
        ]);

        return redirect()->to('/groups')->with('success', lang('Groups.deleted'));
    }

    /**
     * Add a person to a group.
     */
    public function addMember($groupId)
    {
        if ($redirect = $this->requirePermission('groups.assign')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $groupId, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        $personId = (int) $this->request->getPost('person_id');
        $role = (string) ($this->request->getPost('role') ?? GroupMembershipModel::ROLE_MEMBER);

        $result = $this->groups->addMember((int) $group['id'], $personId, $role);

        if (! $result['ok']) {
            $message = match ((string) ($result['code'] ?? '')) {
                'already_member'       => lang('Groups.alreadyMember'),
                'at_capacity'          => lang('Groups.atCapacity'),
                'invalid_role'         => lang('Groups.invalidRole'),
                'person_not_in_parish' => lang('Groups.personNotInParish'),
                default                => lang('Groups.memberAddFailed'),
            };

            return redirect()->back()->with('error', $message);
        }

        $this->audit()->log('groups.member.add', [
            'parish_id'   => $parishId,
            'entity_type' => 'group_membership',
            'entity_id'   => (int) ($result['id'] ?? 0),
            'new_values'  => ['small_group_id' => (int) $group['id'], 'person_id' => $personId, 'role' => $role],
        ]);

        return redirect()->to('/groups/view/' . (int) $group['id'])->with('success', lang('Groups.memberAdded'));
    }

    /**
     * Remove a person from a group.
     */
    public function removeMember($groupId)
    {
        if ($redirect = $this->requirePermission('groups.assign')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $groupId, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        $personId = (int) $this->request->getPost('person_id');
        $result = $this->groups->removeMember((int) $group['id'], $personId);

        if (! $result['ok']) {
            return redirect()->back()->with('error', lang('Groups.memberRemoveFailed'));
        }

        $this->audit()->log('groups.member.remove', [
            'parish_id'   => $parishId,
            'entity_type' => 'group_membership',
            'entity_id'   => (int) $group['id'],
            'old_values'  => ['person_id' => $personId],
        ]);

        return redirect()->to('/groups/view/' . (int) $group['id'])->with('success', lang('Groups.memberRemoved'));
    }

    /**
     * Change a member's role in a group.
     */
    public function updateMemberRole($groupId)
    {
        if ($redirect = $this->requirePermission('groups.assign')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $group = $this->groups->groupForParish((int) $groupId, $parishId);

        if ($group === null) {
            return redirect()->to('/groups')->with('error', lang('Groups.notFound'));
        }

        $personId = (int) $this->request->getPost('person_id');
        $role = (string) $this->request->getPost('role');

        $result = $this->groups->updateRole((int) $group['id'], $personId, $role);

        if (! $result['ok']) {
            $message = ((string) ($result['code'] ?? '')) === 'invalid_role'
                ? lang('Groups.invalidRole')
                : lang('Groups.memberUpdateFailed');

            return redirect()->back()->with('error', $message);
        }

        $this->audit()->log('groups.member.role', [
            'parish_id'   => $parishId,
            'entity_type' => 'group_membership',
            'entity_id'   => (int) $group['id'],
            'new_values'  => ['person_id' => $personId, 'role' => $role],
        ]);

        return redirect()->to('/groups/view/' . (int) $group['id'])->with('success', lang('Groups.memberUpdated'));
    }

    /**
     * @return array<string, string>
     */
    private function groupRules(): array
    {
        return [
            'name'         => 'required|max_length[120]',
            'description'  => 'permit_empty|max_length[2000]',
            'meeting_day'  => 'permit_empty|in_list[monday,tuesday,wednesday,thursday,friday,saturday,sunday]',
            'meeting_time' => 'permit_empty|regex_match[/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/]',
            'location'     => 'permit_empty|max_length[255]',
            'capacity'     => 'permit_empty|integer|greater_than[0]',
        ];
    }
}
