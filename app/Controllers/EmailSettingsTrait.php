<?php

namespace App\Controllers;

use App\Services\EmailSettingsService;
use App\Services\GmailOAuthService;
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

        // Regional tiers address their scope via a query param — carry it
        // into the OAuth connect URL so elevated admins land on the same
        // scope they were editing.
        $scopeQuery = in_array($scopeType, ['diocese', 'province'], true) && $scopeId !== null
            ? '?' . $scopeType . '_id=' . $scopeId
            : '';

        $data = [
            'scope_type'      => $scopeType,
            'scope_id'        => $scopeId,
            'can_edit_smtp'   => $canEditSmtp,
            'settings'        => $current['values'],
            'has_smtp_pass'   => $current['has_smtp_pass'],
            'gmail'           => $current['gmail'] ?? [],
            'google_connect_url'    => current_url() . '/google/connect' . $scopeQuery,
            'google_disconnect_url' => current_url() . '/google/disconnect',
            'effective_sender'=> $service->effectiveSender($scope),
            'save_url'        => current_url(),
            'test_url'        => current_url() . '/test',
            'back_url'        => $backUrl,
            'title'           => $title,
            'errors'          => [],
            'editing_section' => '',
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
            ])->setStatusCode(403)
                ->setHeader(config('Security')->headerName, csrf_hash());
        }

        $recipient = trim((string) ($data['test_recipient'] ?? ''));
        $service   = new EmailSettingsService();
        $result    = $service->sendTest(['type' => $scopeType, 'id' => $scopeId], $data, $recipient);
        $status    = $result['success'] ? 200 : 422;

        $this->audit()->log('email_settings.test_send', [
            'user_id'     => (int) (auth()->id() ?? 0),
            'entity_type' => 'email_settings',
            'entity_id'   => $scopeId ?? 0,
            'scope_type'  => $scopeType,
            'result'      => $result['code'],
        ]);

        $payload = [
            'success' => $result['success'],
            'status'  => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'meta'    => ['http_status' => $status, 'ts' => date('c')],
        ];

        if (!$result['success']) {
            $payload['error'] = [
                'code'    => $result['code'],
                'message' => $result['message'],
                'details' => [],
            ];
            $payload['errors'] = [];
        }

        return $this->response->setJSON($payload)
            ->setStatusCode($status)
            ->setHeader(config('Security')->headerName, csrf_hash());
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

        $scopeQuery = in_array($scopeType, ['diocese', 'province'], true) && $scopeId !== null
            ? '?' . $scopeType . '_id=' . $scopeId
            : '';

        $data = [
            'scope_type'      => $scopeType,
            'scope_id'        => $scopeId,
            'can_edit_smtp'   => $canEditSmtp,
            'settings'        => array_merge($current['values'], $submitted),
            'has_smtp_pass'   => $current['has_smtp_pass'],
            'gmail'           => $current['gmail'] ?? [],
            'google_connect_url'    => current_url() . '/google/connect' . $scopeQuery,
            'google_disconnect_url' => current_url() . '/google/disconnect',
            'effective_sender'=> $service->effectiveSender($scope),
            'save_url'        => current_url(),
            'test_url'        => current_url() . '/test',
            'back_url'        => $backUrl,
            'title'           => $title,
            'errors'          => $errors,
            'editing_section' => (string) ($submitted['section'] ?? ''),
        ];

        $viewData = method_exists($this, 'getCommonViewData')
            ? array_merge($this->getCommonViewData($title), $data)
            : $data;

        return view('partials/_email_settings', $viewData);
    }

    /**
     * Start the Google OAuth consent flow for a resolved settings scope.
     * The tier controller must have already enforced its scope guards; this
     * re-checks the route permission and transport-edit rights, then
     * redirects to Google with a signed single-use state.
     *
     * @param array{type: string, id: int|null} $scope
     */
    protected function gmailConnectRedirect(array $scope, string $permission, bool $canEditTransport, string $settingsUrl)
    {
        if (method_exists($this, 'requirePermission')) {
            $redirect = $this->requirePermission($permission);
            if ($redirect) {
                return $redirect;
            }
        }

        if (!$canEditTransport) {
            return redirect()->to($settingsUrl)->with('error', lang('App.accessDenied'));
        }

        $oauth = new GmailOAuthService();
        if (!$oauth->isConfigured()) {
            return redirect()->to($settingsUrl)->with('error', lang('Email.gmailNotConfigured'));
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return redirect()->to('/login');
        }

        try {
            $url = $oauth->connectUrl($scope, $userId);
        } catch (\Throwable) {
            return redirect()->to($settingsUrl)->with('error', lang('Email.gmailOAuthError'));
        }

        return redirect()->to($url);
    }

    /**
     * Revoke the Google grant and reset the tier transport to SMTP.
     *
     * @param array{type: string, id: int|null} $scope
     */
    protected function gmailDisconnectRedirect(array $scope, string $permission, bool $canEditTransport, string $settingsUrl)
    {
        if (method_exists($this, 'requirePermission')) {
            $redirect = $this->requirePermission($permission);
            if ($redirect) {
                return $redirect;
            }
        }

        if (!$canEditTransport) {
            return redirect()->to($settingsUrl)->with('error', lang('App.accessDenied'));
        }

        try {
            (new GmailOAuthService())->disconnect($scope);
        } catch (\Throwable) {
            return redirect()->to($settingsUrl)->with('error', lang('Email.gmailOAuthError'));
        }

        if (method_exists($this, 'audit')) {
            $this->audit()->log('email_settings.gmail_disconnected', [
                'user_id'     => (int) (auth()->id() ?? 0),
                'entity_type' => 'email_settings',
                'entity_id'   => $scope['id'] ?? 0,
                'scope_type'  => (string) $scope['type'],
            ]);
        }

        return redirect()->to($settingsUrl)->with('success', lang('Email.gmailDisconnected'));
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
