<?php

namespace Diocese\Controllers;

/**
 * ParishStaff
 *
 * Parish staff management endpoints: staff listing, add, promote, role
 * removal, and demotion. Extends Parishes to reuse the shared scope/role
 * helpers (canManageParish, allowedAssignableRoles, isCurrentUser) and the
 * inherited ParishStaffService. Extracted from Parishes (phase 3 split);
 * routes and behavior unchanged.
 */
class ParishStaff extends Parishes
{

    public function staff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff (read-only on all parishes)
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $parish = $this->parishModel->find($parishId);
        if (!$parish) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishNotFound'));
        }

        return view('Diocese\Views\Parishes/staff', array_merge($this->getCommonViewData(lang('Parishes.staffPageTitle')), [
            'parish'         => $parish, // Explicit: getCommonViewData uses operational parish which may differ
            'staff'          => $this->parishModel->getParishStaff($parishId),
            'members'        => $this->parishModel->getParishMembersWithGroups($parishId),
            'auditLogs'      => $this->parishRoleAuditModel->getRecentByParish($parishId, 40),
            'allowedRoles'   => $this->allowedAssignableRoles(),
            'isProvinceAdmin' => $this->isProvinceAdmin(),
        ]));
    }


    public function addStaff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $role = (string) $this->request->getPost('role');
        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        $email = $this->normalizeEmail((string) $this->request->getPost('email'));
        $exists = $this->emailIdentityExists($email);

        if (!$this->validate($this->staffService->staffValidationRules($exists))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transStart();

        try {
            $users = auth()->getProvider();
            $parishScope = $this->parishModel->findWithHierarchy($parishId);

            if ($exists) {
                $userId = $this->staffService->userIdForEmail($email);

                if ($userId === null) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                if ($code = $this->guardTargetUserMutation($userId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
                }

                $user = $users->findById($userId);
                if (!$user) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                $profile = $this->staffService->profileForUser($userId);
                if ($profile && !empty($profile['parish_id']) && (int) $profile['parish_id'] !== $parishId) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.emailBelongsToOtherParish'));
                }

                $this->staffService->ensureStaffProfile(
                    $profile,
                    $userId,
                    $parishId,
                    $parishScope ?? [],
                    (string) $this->request->getPost('first_name'),
                    (string) $this->request->getPost('last_name')
                );

                if (!$this->staffService->attachToParish($userId, $parishId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
                }

                if (!$this->staffService->setHomeParish($userId, $parishId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.homeParishUpdateFailed'));
                }

                if (!$this->staffService->userHasGroup($userId, $role)) {
                    $user->addGroup($role);
                }

                $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'promoted', $role, 'Assigned role to existing user');
                $db->transComplete();
                if ($db->transStatus() === false) {
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAccountCreateFailed'));
                }

                return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.staffExistingUserAssigned'));
            }

            $username = $this->staffService->makeUniqueUsername($email);
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $username,
                'email' => $email,
                'password' => (string) $this->request->getPost('password'),
            ]);

            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $user->activate();
            $user->addGroup($role);

            $this->staffService->createStaffProfile(
                (int) $user->id,
                $parishId,
                $parishScope ?? [],
                (string) $this->request->getPost('first_name'),
                (string) $this->request->getPost('last_name'),
                (string) $this->request->getPost('phone')
            );

            if (!$this->staffService->attachToParish((int) $user->id, $parishId)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
            }

            if (!$this->staffService->setHomeParish((int) $user->id, $parishId)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Diocesan.homeParishUpdateFailed'));
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAccountCreateFailed'));
            }

            return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.staffMemberAdded'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('Parishes.staffCreateError'), $e);
        }
    }


    public function promoteMember($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $role = (string) $this->request->getPost('role');
        $userId = (int) $this->request->getPost('user_id');

        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.memberNotInParish'));
        }

        $users = auth()->getProvider();
        $user = $users->findById($userId);
        if (!$user) {
            return redirect()->back()->with('error', lang('Diocesan.userNotFound'));
        }

        $user->addGroup($role);

        $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'promoted', $role, 'Assigned role to existing member');

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.roleAssigned'));
    }


    public function removeStaffRole($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $userId = (int) $this->request->getPost('user_id');
        $role = (string) $this->request->getPost('role');

        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && $role === 'parishadmin' && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotRemoveOwnParishAdminRole'));
        }

        if (!$this->staffService->userHasGroup($userId, $role)) {
            return redirect()->back()->with('error', lang('Diocesan.userMissingRole'));
        }

        $this->staffService->removeGroup($userId, $role);

        $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'role_removed', $role, 'Removed single role from user');

        if (!$this->staffService->userHasAnyGroup($userId)) {
            $this->staffService->addGroupIfMissing($userId, 'member');
        }

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.roleRemoved'));
    }


    public function demoteStaff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $userId = (int) $this->request->getPost('user_id');

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotDemoteOwnAccount'));
        }

        $rolesRemoved = 0;
        foreach ($this->allowedAssignableRoles() as $role) {
            if ($this->staffService->userHasGroup($userId, $role)) {
                $this->staffService->removeGroup($userId, $role);
                $rolesRemoved++;
                $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'demoted', $role, 'Demotion removed role');
            }
        }

        $this->staffService->addGroupIfMissing($userId, 'member');

        if ($rolesRemoved === 0) {
            return redirect()->to('/parish_list/' . $parishId . '/staff')->with('error', lang('Diocesan.noAdminLeaderRolesFound'));
        }

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.userDemotedToMember'));
    }
}
