<?php

namespace MemberPortal\Controllers;

/**
 * FamiliesPages
 *
 * Family directory page endpoints: list, view, create/store, edit/update,
 * delete. Extends Families to reuse the shared member-scope helpers and
 * models. Extracted from Families (phase 4 split); routes and behavior
 * unchanged. The legacy `Family` alias now extends this class so all
 * `Family::*` route strings keep resolving.
 */
class FamiliesPages extends Families
{

    /**
     * Display list of families
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('family.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $families = $this->familyModel->getParishFamiliesWithCounts($parishId);

        return view('MemberPortal\Views\Families/index', array_merge($this->getCommonViewData(lang('Families.pageTitle')), [
            'families' => $families,
            'parish_id' => $parishId,
        ]));
    }


    /**
     * View family details
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('family.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $family = $this->familyModel->getFamilyWithMembers($id);

        if (!$family || $family['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/families')->with('error', lang('Families.notFoundError'));
        }

        return view('MemberPortal\Views\Families/view', array_merge($this->getCommonViewData(lang('Families.pageTitleView')), ['family' => $family]));
    }


    /**
     * Show create family form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('MemberPortal\Views\Families/create', array_merge(
            $this->getCommonViewData(lang('Families.pageTitleAdd')),
            ['head_option' => $this->resolveHeadOption((int) $this->getOperationalParishId(), (int) old('head_of_household_id'))]
        ));
    }


    /**
     * Store new family
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'family_name' => 'required|min_length[3]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $headOfHouseholdId = (int) ($this->request->getPost('head_of_household_id') ?? 0);
        if ($headOfHouseholdId <= 0) {
            $headOfHouseholdId = null;
        }

        if ($headOfHouseholdId !== null && ! $this->isParishAffiliatedMember($headOfHouseholdId, $parishId)) {
            return redirect()->back()->withInput()->with('error', lang('Families.invalidHeadSelection'));
        }

        $data = [
            'parish_id' => $parishId,
            'family_name' => $this->request->getPost('family_name'),
            'head_of_household_id' => $headOfHouseholdId,
            'address' => $this->request->getPost('address'),
        ];

        $db = db_connect();
        $db->transBegin();

        try {
            $familyId = (int) $this->familyModel->insert($data, true);
            if ($familyId <= 0) {
                throw new \RuntimeException('Failed to create family record');
            }

            $this->ensureHeadOfHouseholdMembership($familyId, $headOfHouseholdId);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to create family transaction');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', lang('Families.createFailed'));
        }

        if ($familyId) {
            $this->audit()->log('family.create', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'family',
                'entity_id' => (int) $familyId,
                'new_values' => $data,
            ]);

            return redirect()->to('/families')->with('success', lang('Families.createSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Families.createFailed'));
    }


    /**
     * Show edit family form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $family = $this->familyModel->find($id);

        if (!$family || $family['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/families')->with('error', lang('Families.notFoundError'));
        }

        $parishId = $this->getOperationalParishId();
        $headUserId = (int) old('head_of_household_id', (int) ($family['head_of_household_id'] ?? 0));

        return view('MemberPortal\Views\Families/edit', array_merge($this->getCommonViewData(lang('Families.pageTitleEdit')), [
            'family' => $family,
            'head_option' => $this->resolveHeadOption((int) $parishId, $headUserId),
        ]));
    }


    /**
     * Update family
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $family = $this->familyModel->find($id);

        if (!$family || $family['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/families')->with('error', lang('Families.notFoundError'));
        }

        $rules = [
            'family_name' => 'required|min_length[3]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $headOfHouseholdId = (int) ($this->request->getPost('head_of_household_id') ?? 0);
        if ($headOfHouseholdId <= 0) {
            $headOfHouseholdId = null;
        }

        if ($headOfHouseholdId !== null && ! $this->isParishAffiliatedMember($headOfHouseholdId, $parishId)) {
            return redirect()->back()->withInput()->with('error', lang('Families.invalidHeadSelection'));
        }

        $data = [
            'family_name' => $this->request->getPost('family_name'),
            'head_of_household_id' => $headOfHouseholdId,
            'address' => $this->request->getPost('address'),
        ];

        $updated = false;
        $db = db_connect();
        $db->transBegin();

        try {
            $updated = $this->familyModel->update($id, $data);
            if (! $updated) {
                throw new \RuntimeException('Failed to update family record');
            }

            $this->ensureHeadOfHouseholdMembership((int) $id, $headOfHouseholdId);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to update family transaction');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $updated = false;
        }

        if ($updated) {
            $this->audit()->log('family.update', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'family',
                'entity_id' => (int) $id,
                'old_values' => [
                    'family_name' => (string) ($family['family_name'] ?? ''),
                    'head_of_household_id' => $family['head_of_household_id'] ?? null,
                    'address' => $family['address'] ?? null,
                ],
                'new_values' => $data,
            ]);

            return redirect()->to('/families')->with('success', lang('Families.updateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Families.updateFailed'));
    }


    /**
     * Delete family
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $family = $this->familyModel->find($id);

        if (!$family || $family['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.notFoundError'), 404, 'FAMILY_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($this->familyModel->delete($id)) {
            $this->audit()->log('family.delete', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'family',
                'entity_id' => (int) $id,
                'old_values' => [
                    'family_name' => (string) ($family['family_name'] ?? ''),
                    'head_of_household_id' => $family['head_of_household_id'] ?? null,
                    'address' => $family['address'] ?? null,
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Families.deleteSuccess')]);
        }

        return $this->jsonResponse($this->apiErrorPayload(lang('Families.deleteFailed'), 500, 'FAMILY_DELETE_FAILED'), 500);
    }
}
