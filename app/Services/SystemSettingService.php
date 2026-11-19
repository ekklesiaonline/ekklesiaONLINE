<?php

namespace App\Services;

use App\Models\SystemSettingModel;

class SystemSettingService
{
    private SystemSettingModel $model;

    public function __construct(?SystemSettingModel $model = null)
    {
        $this->model = $model ?? model(SystemSettingModel::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->model->getValue($key, $default);
    }

    public function set(string $key, mixed $value, string $dataType = 'string', ?string $category = null, ?string $description = null): bool
    {
        return $this->model->setValue($key, $value, $dataType, $category, $description);
    }

    public function getAll(): array
    {
        return $this->model->getAll();
    }

    public function getGroupedByCategory(): array
    {
        return $this->model->getGroupedByCategory();
    }

    public function getPasswordPolicy(): array
    {
        return [
            'min_length' => $this->get('password_policy.min_length', 12),
            'require_uppercase' => $this->get('password_policy.require_uppercase', true),
            'require_lowercase' => $this->get('password_policy.require_lowercase', true),
            'require_digit' => $this->get('password_policy.require_digit', true),
            'require_special' => $this->get('password_policy.require_special', true),
            'special_chars' => $this->get('password_policy.special_chars', '@$!%*?&#^_-'),
        ];
    }

    public function getLockoutConfig(): array
    {
        return [
            'max_attempts' => $this->get('lockout.max_attempts', 5),
            'window_seconds' => $this->get('lockout.window_seconds', 900),
            'duration_seconds' => $this->get('lockout.duration_seconds', 1800),
        ];
    }

    public function isMfaRequiredForAdminOps(): bool
    {
        $value = $this->get('mfa.required_for_admin_ops', env('MFA_REQUIRED_FOR_ADMIN_OPS', true));

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Audience scope for the login email-2FA challenge
     * (ScopedEmail2FA::appliesTo):
     *  - 'admins' (default): only users in systemadmin, provinceadmin,
     *    dioceseadmin or parishadmin are challenged at login.
     *  - 'all': every user is challenged.
     *  - 'off': the challenge is disabled entirely.
     *
     * Unrecognized/empty values fall back to 'admins': the setting fails
     * toward enforcing the challenge on the highest-privilege accounts
     * rather than silently disabling it or widening it to everyone.
     */
    public function getLoginEmail2faScope(): string
    {
        $value = $this->get('security.login_2fa_scope', env('security.login_2fa_scope', env('SECURITY_LOGIN_2FA_SCOPE', 'admins')));
        $value = is_string($value) ? strtolower(trim($value)) : 'admins';

        return in_array($value, ['off', 'admins', 'all'], true) ? $value : 'admins';
    }

    public function isPrimaryTransferOtpRequired(): bool
    {
        $value = $this->get('mfa.required_for_primary_transfer', env('SYSTEM_ADMIN_PRIMARY_TRANSFER_OTP_ENABLED', true));

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance.enabled', false);
    }

    public function getMaintenanceMessage(): string
    {
        return (string) $this->get('maintenance.message', 'The system is under maintenance. Please check back later.');
    }
}
