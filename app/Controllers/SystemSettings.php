<?php

namespace App\Controllers;

use App\Services\SystemSettingService;

class SystemSettings extends BaseController
{
    use SystemAdminService;

    private SystemSettingService $settings;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settings = service('systemSettings');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $settings = $this->settings->getGroupedByCategory();

        return view('System/settings', array_merge(
            $this->getCommonViewData(lang('System.sectionSettings')),
            [
                'settings' => $settings,
                'categories' => $this->getCategoryLabels(),
            ]
        ));
    }

    public function update()
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $allowedKeys = [
            'password_policy.min_length',
            'password_policy.require_uppercase',
            'password_policy.require_lowercase',
            'password_policy.require_digit',
            'password_policy.require_special',
            'password_policy.special_chars',
            'mfa.required_for_admin_ops',
            'mfa.required_for_primary_transfer',
            'lockout.max_attempts',
            'lockout.window_seconds',
            'lockout.duration_seconds',
            'maintenance.enabled',
            'maintenance.message',
            'visitor.converted_window_days',
        ];

        $rules = [
            'password_policy.min_length' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[128]',
            'password_policy.special_chars' => 'required|max_length[255]',
            'lockout.max_attempts' => 'required|integer|greater_than_equal_to[1]',
            'lockout.window_seconds' => 'required|integer|greater_than_equal_to[60]',
            'lockout.duration_seconds' => 'required|integer|greater_than_equal_to[60]',
            'maintenance.message' => 'permit_empty|max_length[1000]',
            'visitor.converted_window_days' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[3650]',
        ];

        // PHP/HTML convert dotted input names like "password_policy.min_length"
        // to "password_policy_min_length" in $_POST. Accept either form and
        // normalise back to the dotted keys used by the service.
        $rawPost = $this->request->getPost() ?? [];
        $underscoreMap = [];
        foreach ($allowedKeys as $allowedKey) {
            $underscoreMap[str_replace('.', '_', $allowedKey)] = $allowedKey;
        }

        $post = [];
        foreach ($rawPost as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $post[$key] = $value;
            } elseif (isset($underscoreMap[$key])) {
                $post[$underscoreMap[$key]] = $value;
            }
        }

        // HTML checkboxes are not submitted when unchecked. For boolean settings,
        // treat absence as "0" so the OFF state is persisted.
        foreach ($allowedKeys as $allowedKey) {
            if (!array_key_exists($allowedKey, $post) && $this->resolveDataType($allowedKey) === 'boolean') {
                $post[$allowedKey] = '0';
            }
        }

        $submittedKeys = array_intersect($allowedKeys, array_keys($post));

        // CI4 validation treats dotted field names as nested paths, so we validate
        // against a flat underscore-keyed copy of the submitted data.
        $flatPost = [];
        $flatRules = [];
        foreach ($submittedKeys as $key) {
            $flatKey = str_replace('.', '_', $key);
            $flatPost[$flatKey] = $post[$key];
            if (isset($rules[$key])) {
                $flatRules[$flatKey] = $rules[$key];
            }
        }

        if ($flatRules !== [] && !$this->validateData($flatPost, $flatRules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $oldValues = [];
        $newValues = [];

        try {
            $db->transStart();

            foreach ($allowedKeys as $key) {
                if (!array_key_exists($key, $post ?? [])) {
                    continue;
                }

                $rawValue = $post[$key];
                $dataType = $this->resolveDataType($key);
                $category = $this->resolveCategory($key);
                $description = $this->resolveDescription($key);

                $oldValues[$key] = $this->settings->get($key);
                $castValue = $this->castSubmittedValue($rawValue, $dataType);
                $newValues[$key] = $castValue;

                $this->settings->set($key, $castValue, $dataType, $category, $description);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('System.settingsUpdateFailed'), $e);
        }

        $this->audit()->log('system.settings.updated', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'system_settings',
            'entity_id' => 1,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        return redirect()->to(base_url('system/settings'))->with('success', lang('System.settingsUpdated'));
    }

    private function resolveDataType(string $key): string
    {
        return match ($key) {
            'password_policy.min_length',
            'lockout.max_attempts',
            'lockout.window_seconds',
            'lockout.duration_seconds',
            'visitor.converted_window_days' => 'integer',
            'password_policy.require_uppercase',
            'password_policy.require_lowercase',
            'password_policy.require_digit',
            'password_policy.require_special',
            'mfa.required_for_admin_ops',
            'mfa.required_for_primary_transfer',
            'maintenance.enabled' => 'boolean',
            default => 'string',
        };
    }

    private function castSubmittedValue(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer' => (int) $value,
            default => trim((string) $value),
        };
    }

    private function resolveCategory(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'password_policy.') => 'policy',
            str_starts_with($key, 'mfa.') || str_starts_with($key, 'lockout.') => 'security',
            str_starts_with($key, 'maintenance.') => 'system',
            str_starts_with($key, 'visitor.') => 'visitors',
            default => 'system',
        };
    }

    private function resolveDescription(string $key): string
    {
        return match ($key) {
            'password_policy.min_length' => 'Minimum length for admin passwords.',
            'password_policy.require_uppercase' => 'Require at least one uppercase letter.',
            'password_policy.require_lowercase' => 'Require at least one lowercase letter.',
            'password_policy.require_digit' => 'Require at least one digit.',
            'password_policy.require_special' => 'Require at least one special character.',
            'password_policy.special_chars' => 'Allowed special characters for password validation.',
            'mfa.required_for_admin_ops' => 'Require MFA for system admin write operations.',
            'mfa.required_for_primary_transfer' => 'Require OTP via email for primary system admin transfer.',
            'lockout.max_attempts' => 'Failed attempts before account lockout.',
            'lockout.window_seconds' => 'Time window in seconds for counting failed attempts.',
            'lockout.duration_seconds' => 'How long an account remains locked in seconds.',
            'maintenance.enabled' => 'Enable maintenance mode (blocks non-system-admins).',
            'maintenance.message' => 'Message shown during maintenance mode.',
            'visitor.converted_window_days' => 'Rolling window in days for the visitor dashboard "converted to members" KPI tile.',
            default => '',
        };
    }

    private function getCategoryLabels(): array
    {
        return [
            'policy' => lang('System.settingsCategoryPolicy'),
            'security' => lang('System.settingsCategorySecurity'),
            'system' => lang('System.settingsCategorySystem'),
            'visitors' => lang('System.settingsCategoryVisitors'),
        ];
    }
}
