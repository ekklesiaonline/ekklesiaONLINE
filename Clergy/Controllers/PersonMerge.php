<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\PersonMergeService;
use App\Services\ParishPeopleRegistryService;

class PersonMerge extends BaseController
{
    private function getMergeService(): PersonMergeService
    {
        return new PersonMergeService();
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/people')->with('error', lang('App.errorNoParishScope'));
        }

        $candidates = $this->getMergeService()->findCandidates($parishId);

        return view('Clergy\\Views\\PersonMerge\\index', array_merge(
            $this->getCommonViewData(lang('People.personMergeTitle')),
            ['candidates' => $candidates, 'parish_id' => $parishId]
        ));
    }

    public function preview()
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/people')->with('error', lang('App.errorNoParishScope'));
        }

        $sourceId = (int) $this->request->getGet('source');
        $targetId = (int) $this->request->getGet('target');

        if ($sourceId <= 0 || $targetId <= 0) {
            return redirect()->to('/people/merge')->with('error', lang('People.personMergeSelectBoth'));
        }

        $preview = $this->getMergeService()->getPreview($sourceId, $targetId, $parishId);

        if (empty($preview['ok'])) {
            return redirect()->to('/people/merge')->with('error', $preview['message'] ?? lang('App.errorFailed'));
        }

        return view('Clergy\\Views\\PersonMerge\\preview', array_merge(
            $this->getCommonViewData(lang('People.personMergePreviewTitle')),
            [
                'source'    => $preview['source'] ?? [],
                'target'    => $preview['target'] ?? [],
                'conflicts' => $preview['conflicts'] ?? [],
                'parish_id' => $parishId,
            ]
        ));
    }

    public function merge(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.errorUnauthorized')])->setStatusCode(403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.errorNoParishScope')])->setStatusCode(403);
        }

        $sourceId = (int) $this->request->getPost('source_person_id');
        $targetId = (int) $this->request->getPost('target_person_id');
        $reason   = trim((string) $this->request->getPost('merge_reason'));

        if ($sourceId <= 0 || $targetId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => lang('People.personMergeSelectBoth')])->setStatusCode(400);
        }

        if (strlen($reason) < 8) {
            return $this->response->setJSON(['success' => false, 'message' => lang('People.personMergeReasonRequired')])->setStatusCode(400);
        }

        $allowedOverrides = ['first_name', 'last_name', 'email', 'phone', 'address', 'birthdate', 'gender'];
        $overrides = [];
        foreach ($allowedOverrides as $field) {
            $value = $this->request->getPost("override_{$field}");
            if ($value !== null && $value !== '') {
                $overrides[$field] = (string) $value;
            }
        }

        $result = $this->getMergeService()->merge(
            $sourceId,
            $targetId,
            $parishId,
            (int) (auth()->id() ?? 0),
            $overrides
        );

        if (empty($result['ok'])) {
            return $this->response->setJSON(['success' => false, 'message' => $result['message'] ?? lang('App.errorFailed')])->setStatusCode(500);
        }

        $this->audit()->log('person.merge', [
            'parish_id' => $parishId,
            'entity_type' => 'person',
            'entity_id' => $targetId,
            'new_values' => [
                'source_person_id' => $sourceId,
                'reason' => $reason,
            ],
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => $result['message'] ?? lang('People.personMergeSuccess'),
            'redirect' => base_url('/people/merge'),
        ]);
    }
}
