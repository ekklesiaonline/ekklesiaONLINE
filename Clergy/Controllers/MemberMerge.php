<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\MemberMergeService;

/**
 * MemberMerge controller
 *
 * Routes (all require members.delete):
 *   GET  members/dedup               → index()        — list duplicate pairs
 *   GET  members/dedup/preview       → preview()      — side-by-side field comparison
 *   POST members/dedup/merge         → merge()        — execute merge
 *   GET  members/dedup/hard-delete/:id → confirmHardDelete() — confirmation page
 *   POST members/dedup/hard-delete/:id → hardDelete()        — execute hard delete
 */
class MemberMerge extends BaseController
{
    use AdminHierarchyGuard;

    private function getMergeService(): MemberMergeService
    {
        return new MemberMergeService();
    }

    // -------------------------------------------------------------------------
    // Duplicate list
    // -------------------------------------------------------------------------

    public function index()
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to(base_url('members'))->with('error', lang('App.notAuthorized'));
        }

        $pairs = $this->getMergeService()->findDuplicatesForParish($parishId);

        return view('Clergy\Views\MemberMerge\index', array_merge(
            $this->getCommonViewData(lang('Members.dedupTitle')),
            ['pairs' => $pairs]
        ));
    }

    // -------------------------------------------------------------------------
    // Merge preview
    // -------------------------------------------------------------------------

    public function preview()
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->back()->with('error', lang('App.notAuthorized'));
        }

        $canonicalId  = (int) $this->request->getGet('canonical');
        $duplicateId  = (int) $this->request->getGet('duplicate');

        if ($canonicalId <= 0 || $duplicateId <= 0) {
            return redirect()->to(base_url('members/dedup'))->with('error', 'Select two profile to compare.');
        }

        $result = $this->getMergeService()->getMergePreview($canonicalId, $duplicateId, $parishId);

        if (!$result['ok']) {
            return redirect()->to(base_url('members/dedup'))->with('error', $result['message']);
        }

        return view('Clergy\Views\MemberMerge\preview', array_merge(
            $this->getCommonViewData(lang('Members.mergePreviewTitle')),
            [
                'canonical'  => $result['canonical'],
                'duplicate'  => $result['duplicate'],
            ]
        ));
    }

    // -------------------------------------------------------------------------
    // Execute merge
    // -------------------------------------------------------------------------

    public function merge(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notAuthorized')])->setStatusCode(403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notAuthorized')])->setStatusCode(403);
        }

        $canonicalId = (int) $this->request->getPost('canonical_profile_id');
        $duplicateId = (int) $this->request->getPost('duplicate_profile_id');
        $reason      = trim((string) $this->request->getPost('merge_reason'));

        if ($canonicalId <= 0 || $duplicateId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Both canonical and duplicate profile IDs are required.'])->setStatusCode(400);
        }

        if (strlen($reason) < 8) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please provide a reason for merging (min 8 characters).'])->setStatusCode(400);
        }

        $duplicateProfile = $this->loadProfileForParish($duplicateId, $parishId);
        if ($duplicateProfile !== null) {
            if ($code = $this->guardTargetUserMutation((int) ($duplicateProfile['user_id'] ?? 0), true)) {
                return $this->response->setJSON(['success' => false, 'message' => $this->hierarchyGuardMessage($code)])->setStatusCode(403);
            }
        }

        // Collect per-field override choices (radio buttons: "canonical" or "duplicate")
        $fieldChoices = (array) $this->request->getPost('field_choice');
        $fieldValues  = (array) $this->request->getPost('field_value');
        $fieldOverrides = [];
        foreach ($fieldChoices as $field => $choice) {
            if ($choice === 'duplicate' && array_key_exists($field, $fieldValues)) {
                $fieldOverrides[$field] = $fieldValues[$field];
            }
        }

        $result = $this->getMergeService()->merge($canonicalId, $duplicateId, $parishId, $fieldOverrides);

        if (!$result['ok']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $result['message'],
                'error'   => ['code' => $result['code'], 'message' => $result['message']],
            ])->setStatusCode($result['http_status']);
        }

        $this->audit()->log('member.merge', array_merge(
            [
                'canonical_profile_id' => $canonicalId,
                'duplicate_profile_id' => $duplicateId,
                'merge_reason'         => $reason,
                'field_overrides'      => array_keys($fieldOverrides),
                'duplicate_user_id'    => $result['data']['duplicate_user_id'] ?? null,
            ],
            $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT)
        ));

        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'Profiles merged successfully.',
            'redirect' => base_url('members/view/' . $canonicalId),
        ]);
    }

    // -------------------------------------------------------------------------
    // Hard-delete confirmation page
    // -------------------------------------------------------------------------

    public function confirmHardDelete(int $profileId)
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->back()->with('error', lang('App.notAuthorized'));
        }

        $profile = $this->loadProfileForParish($profileId, $parishId);
        if ($profile === null) {
            return redirect()->to(base_url('members'))->with('error', lang('Clergy.memberNotFound'));
        }

        return view('Clergy\Views\MemberMerge\hard_delete_confirm', array_merge(
            $this->getCommonViewData(lang('Members.hardDeleteTitle')),
            ['profile' => $profile]
        ));
    }

    // -------------------------------------------------------------------------
    // Execute hard delete
    // -------------------------------------------------------------------------

    public function hardDelete(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notAuthorized')])->setStatusCode(403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notAuthorized')])->setStatusCode(403);
        }

        $profile = $this->loadProfileForParish($profileId, $parishId);
        if ($profile === null) {
            return $this->response->setJSON(['success' => false, 'message' => lang('Clergy.memberNotFound')])->setStatusCode(404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0), true)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->hierarchyGuardMessage($code)])->setStatusCode(403);
        }

        // Security gate 1: caller must type the member's exact full name
        $expectedName   = strtolower(trim($profile['first_name'] . ' ' . $profile['last_name']));
        $confirmedName  = strtolower(trim((string) $this->request->getPost('confirm_name')));
        if ($confirmedName !== $expectedName) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'The name you entered does not match. Please type the member\'s full name exactly.',
                'error'   => ['code' => 'NAME_MISMATCH', 'message' => 'Name confirmation failed.'],
            ])->setStatusCode(422);
        }

        // Security gate 2: reason required
        $reason = trim((string) $this->request->getPost('delete_reason'));
        if (strlen($reason) < 10) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A deletion reason of at least 10 characters is required.',
                'error'   => ['code' => 'REASON_TOO_SHORT', 'message' => 'Reason too short.'],
            ])->setStatusCode(422);
        }

        // Security gate 3: irreversible checkbox
        if ($this->request->getPost('acknowledge_irreversible') !== '1') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'You must acknowledge that this action is irreversible.',
                'error'   => ['code' => 'ACK_MISSING', 'message' => 'Irreversible acknowledgment missing.'],
            ])->setStatusCode(422);
        }

        // Write the audit entry BEFORE deletion (while the profile still exists)
        $this->audit()->log('member.hard_delete', array_merge(
            [
                'profile_id' => $profileId,
                'user_id'    => (int) $profile['user_id'],
                'full_name'  => $profile['first_name'] . ' ' . $profile['last_name'],
                'reason'     => $reason,
            ],
            $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT)
        ));

        $result = $this->getMergeService()->hardDelete($profileId, $parishId);

        if (!$result['ok']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $result['message'],
                'error'   => ['code' => $result['code'], 'message' => $result['message']],
            ])->setStatusCode($result['http_status']);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'Member permanently deleted.',
            'redirect' => base_url('members'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private function loadProfileForParish(int $profileId, int $parishId): ?array
    {
        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return null;
        }

        $row = $db->table($profileTable . ' p')
            ->select('p.*, COALESCE(ai.secret, \'\') AS email')
            ->join('auth_identities ai', 'ai.user_id = p.user_id AND ai.type = \'email_password\'', 'left')
            ->where('p.id', $profileId)
            ->where('p.parish_id', $parishId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
        }
        $candidates[] = 'profile';

        foreach ($candidates as $table) {
            try {
                $db->getFieldNames($table);
                return $table;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }
}
