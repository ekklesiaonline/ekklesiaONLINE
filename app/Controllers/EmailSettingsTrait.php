<?php

namespace App\Controllers;

use App\Services\EmailSettingsService;
use CodeIgniter\Controller;

/**
 * EmailSettingsTrait
 *
 * Reusable GET/POST handlers for the per-tier email settings forms.
 * Any controller using this trait must extend a controller with permission
 * helpers (BaseController in this app).
 */
trait EmailSettingsTrait
{
    /**
     * Render the email settings form.
     *
     * @param string   $scopeType     'system'|'organisation'|'province'|'diocese'|'parish'
     * @param int|null $scopeId
     * @param string   $permission    Shield permission required
     * @param bool     $canEditSmtp   Whether SMTP credentials may be edited here
     * @param string   $backUrl
     * @param string   $title
     */
    protected function emailSettingsIndex(
        string $scopeType,
        ?int $scopeId,
        string $permission,
        bool $canEditSmtp,
        string $backUrl,
        string $title
    ) {
        if (method_exists($this, 'requirePermission')) {
            $redirect = $this->requirePermission($permission);
            if ($redirect) {
                return $redirect;
            }
        }

        $service = new EmailSettingsService();
        $scope   = ['type' => $scopeType, 'id' => $scopeId];
        $current = $service->currentSettings($scope);

        $data = [
            'scope_type'      => $scopeType,
            'scope_id'        => $scopeId,
            'can_edit_smtp'   => $canEditSmtp,
            'settings'        => $current['values'],
            'has_smtp_pass'   => $current['has_smtp_pass'],
            'effective_sender'=> $service->effectiveSender($scope),
            'save_url'        => current_url(),
            'test_url'        => current_url() . '/test',
            'back_url'        => $backUrl,
            'title'           => $title,
            'errors'          => [],
        ];

        $viewData = method_exists($this, 'getCommonViewData')
            ? array_merge($this->getCommonViewData($title), $data)
            : $data;

        return view('partials/_email_settings', $viewData);
    }

    /**
     * Save the email settings form.
     */
    protected function emailSettingsSave(
        string $scopeType,
        ?int $scopeId,
        string $permission,
        bool $canEditSmtp,
        string $backUrl,
        string $title
    ) {
        if (method_exists($this, 'requirePermission')) {
            $redirect = $this->requirePermission($permission);
            if ($redirect) {
                return $redirect;
            }
        }

        $data = $this->request->getPost() ?? [];
        $submittedScopeType = (string) ($data['scope_type'] ?? $scopeType);
        $submittedScopeId   = isset($data['scope_id']) ? (int) $data['scope_id'] : $scopeId;

        if ($submittedScopeType !== $scopeType || $submittedScopeId !== $scopeId) {
            return redirect()->back()->with('error', lang('App.errorForbidden'));
        }

        $service = new EmailSettingsService();
        $scope   = ['type' => $scopeType, 'id' => $scopeId];
        $errors  = $service->save($scope, $data, $canEditSmtp);

        if ($errors !== []) {
            return $this->renderEmailSettingsWithErrors($scopeType, $scopeId, $permission, $canEditSmtp, $backUrl, $title, $errors, $data);
        }

        $this->auditEmailSettingsChange($scopeType, $scopeId);

        return redirect()->to(current_url())
            ->with('success', lang('Email.settingsSaved'));
    }

    /**
     * Send a test email from the settings page.
     */
    protected function emailSettingsTest(
        string $scopeType,
        ?int $scopeId,
        string $permission,
        bool $canEditSmtp
    ) {
        if (method_exists($this, 'requirePermission')) {
            $redirect = $this->requirePermission($permission);
            if ($redirect) {
                return $redirect;
            }
        }

        $data = $this->request->getPost() ?? [];
        $submittedScopeType = (string) ($data['scope_type'] ?? $scopeType);
        $submittedScopeId   = isset($data['scope_id']) ? (int) $data['scope_id'] : $scopeId;

        if ($submittedScopeType !== $scopeType || $submittedScopeId !== $scopeId) {
            return $this->response->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => lang('App.errorForbidden'),
                'error'   => ['code' => 403, 'message' => lang('App.errorForbidden')],
                'meta'    => ['http_status' => 403, 'ts' => date('c')],
            ])->setStatusCode(403);
        }

        $recipient = trim((string) ($data['test_recipient'] ?? ''));
        $service   = new EmailSettingsService();
        $result    = $service->sendTest(['type' => $scopeType, 'id' => $scopeId], $data, $recipient);

        return $this->response->setJSON([
            'success' => $result['success'],
            'status'  => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'meta'    => ['http_status' => $result['success'] ? 200 : 422, 'ts' => date('c')],
        ])->setStatusCode($result['success'] ? 200 : 422);
    }

    private function renderEmailSettingsWithErrors(
        string $scopeType,
        ?int $scopeId,
        string $permission,
        bool $canEditSmtp,
        string $backUrl,
        string $title,
        array $errors,
        array $submitted
    ) {
        $service = new EmailSettingsService();
        $scope   = ['type' => $scopeType, 'id' => $scopeId];
        $current = $service->currentSettings($scope);

        $data = [
            'scope_type'      => $scopeType,
            'scope_id'        => $scopeId,
            'can_edit_smtp'   => $canEditSmtp,
            'settings'        => array_merge($current['values'], $submitted),
            'has_smtp_pass'   => $current['has_smtp_pass'],
            'effective_sender'=> $service->effectiveSender($scope),
            'save_url'        => current_url(),
            'test_url'        => current_url() . '/test',
            'back_url'        => $backUrl,
            'title'           => $title,
            'errors'          => $errors,
        ];

        $viewData = method_exists($this, 'getCommonViewData')
            ? array_merge($this->getCommonViewData($title), $data)
            : $data;

        return view('partials/_email_settings', $viewData)->with('errors', $errors);
    }

    private function auditEmailSettingsChange(string $scopeType, ?int $scopeId): void
    {
        if (!method_exists($this, 'audit')) {
            return;
        }

        $this->audit()->log('email_settings.updated', [
            'user_id'     => (int) (auth()->id() ?? 0),
            'entity_type' => 'email_settings',
            'entity_id'   => $scopeId ?? 0,
            'scope_type'  => $scopeType,
            'ip_address'  => $this->request->getIPAddress(),
            'user_agent'  => (string) $this->request->getUserAgent(),
            'timestamp'   => date('Y-m-d H:i:s'),
        ]);
    }
}
