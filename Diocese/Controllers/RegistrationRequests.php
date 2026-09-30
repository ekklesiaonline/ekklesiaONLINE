<?php

namespace Diocese\Controllers;

use App\Controllers\BaseController;
use App\Services\RegistrationApprovalService;

class RegistrationRequests extends BaseController
{
    public function approve(int $requestId)
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $role = (string) ($this->request->getPost('role') ?? '');
        $actorUserId = (int) (auth()->id() ?? 0);

        $service = new RegistrationApprovalService();
        $error = $service->approve($actorUserId, $requestId, $role !== '' ? $role : null, $scope);

        if ($error !== null) {
            return redirect()->to('/diocese/dashboard')->with('error', $error);
        }

        // Rebuild the parish snapshot so the approved member appears immediately.
        $request = model('UserRegistrationRequestModel')->find($requestId);
        $approvedParishId = (int) (
            $request['requested_parish_id']
            ?? $request['parish_id']
            ?? $request['requested_parish_id']
            ?? $request['parish_id']
            ?? 0
        );


        return redirect()->to('/diocese/dashboard')->with('success', lang('Diocesan.registrationApproved'));
    }

    public function bulkAction()
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $requestIds = array_map('intval', (array) ($this->request->getPost('request_ids') ?? []));
        if ($requestIds === []) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.noPendingRegistrations'));
        }

        $action = (string) ($this->request->getPost('bulk_action') ?? 'approve');
        $validActions = ['approve', 'dismiss', 'ban', 'delete', 'escalate'];
        if (!in_array($action, $validActions, true)) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.invalidBulkAction'));
        }

        $user = auth()->user();
        if ($action === 'delete' && ($user === null || !$user->can('member.delete'))) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.errorForbidden'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);
        $service = new RegistrationApprovalService();

        $successCount = 0;
        $firstError = null;
        foreach ($requestIds as $requestId) {
            $error = match ($action) {
                'approve' => $service->approve($actorUserId, $requestId, null, $scope),
                'dismiss' => $service->dismiss($actorUserId, $requestId, $scope),
                'ban' => $service->ban($actorUserId, $requestId, $scope),
                'delete' => $service->delete($actorUserId, $requestId, $scope),
                'escalate' => $service->escalate($actorUserId, $requestId, $scope),
            };

            if ($error === null) {
                $successCount++;
            } elseif ($firstError === null) {
                $firstError = $error;
            }
        }

        $total = count($requestIds);

        $successKey = match ($action) {
            'approve' => 'Diocesan.bulkApprovedSuccess',
            'dismiss' => 'Diocesan.bulkDismissedSuccess',
            'ban' => 'Diocesan.bulkBannedSuccess',
            'delete' => 'Diocesan.bulkDeletedSuccess',
            'escalate' => 'Diocesan.bulkEscalatedSuccess',
        };
        $partialKey = match ($action) {
            'approve' => 'Diocesan.bulkApprovedPartial',
            'dismiss' => 'Diocesan.bulkDismissedPartial',
            'ban' => 'Diocesan.bulkBannedPartial',
            'delete' => 'Diocesan.bulkDeletedPartial',
            'escalate' => 'Diocesan.bulkEscalatedPartial',
        };

        if ($successCount === $total) {
            return redirect()->to('/diocese/dashboard')->with('success', lang($successKey, [$successCount]));
        }

        if ($successCount > 0) {
            return redirect()->to('/diocese/dashboard')->with('warning', lang($partialKey, [$successCount, $total - $successCount]));
        }

        return redirect()->to('/diocese/dashboard')->with('error', $firstError ?? lang('Diocesan.bulkActionFailed'));
    }

    public function escalate(int $requestId)
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);
        $notes = (string) ($this->request->getPost('escalate_notes') ?? '');
        $service = new RegistrationApprovalService();
        $error = $service->escalate($actorUserId, $requestId, $scope, $notes !== '' ? $notes : null);

        if ($error !== null) {
            return redirect()->to('/diocese/dashboard')->with('error', $error);
        }

        return redirect()->to('/diocese/dashboard')->with('success', lang('Diocesan.registrationEscalated'));
    }

    public function dismiss(int $requestId)
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);
        $service = new RegistrationApprovalService();
        $error = $service->dismiss($actorUserId, $requestId, $scope);

        if ($error !== null) {
            return redirect()->to('/diocese/dashboard')->with('error', $error);
        }

        return redirect()->to('/diocese/dashboard')->with('success', lang('Diocesan.registrationUpdated'));
    }

    public function ban(int $requestId)
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);
        $service = new RegistrationApprovalService();
        $error = $service->ban($actorUserId, $requestId, $scope);

        if ($error !== null) {
            return redirect()->to('/diocese/dashboard')->with('error', $error);
        }

        return redirect()->to('/diocese/dashboard')->with('success', lang('Diocesan.registrationUpdated'));
    }

    public function delete(int $requestId)
    {
        $scope = $this->resolveParishModerationScope();
        if ($scope === null) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('Diocesan.accessDeniedDiocesanAdmin'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);
        $service = new RegistrationApprovalService();
        $error = $service->delete($actorUserId, $requestId, $scope);

        if ($error !== null) {
            return redirect()->to('/diocese/dashboard')->with('error', $error);
        }

        return redirect()->to('/diocese/dashboard')->with('success', lang('Diocesan.registrationDeleted'));
    }

    private function resolveParishModerationScope(): ?array
    {
        $user = auth()->user();
        if (!$user || !$user->inGroup('parishadmin')) {
            return null;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return null;
        }

        $parish = model('ParishModel')->findWithHierarchy($parishId);
        if (!$parish || empty($parish['diocese_id']) || empty($parish['province_id'])) {
            return null;
        }

        return [
            'parish_id' => $parishId,
            'diocese_id' => (int) $parish['diocese_id'],
            'province_id' => (int) $parish['province_id'],
        ];
    }
}
