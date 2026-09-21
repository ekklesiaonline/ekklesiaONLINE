<?php

namespace App\Services;

/**
 * DeviceVerificationPolicy
 *
 * Single source of truth for the development/testing bypass of the
 * new-device verification challenge. Both SessionTrackingFilter (which
 * creates the challenge and sends the email) and DeviceVerificationFilter
 * (which enforces the challenge) must consult this policy so the bypass
 * decision is made before any challenge/email side effects occur.
 *
 * The bypass is NEVER honored in production.
 */
final class DeviceVerificationPolicy
{
    /**
     * Returns true only in testing/development environments AND when the
     * explicit env flag is truthy. Production always returns false.
     */
    public function shouldBypass(): bool
    {
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';

        if (!in_array($environment, ['testing', 'development'], true)) {
            return false;
        }

        $rawFlag = strtolower((string) env('security.disableDeviceVerificationForTesting', '0'));

        return in_array($rawFlag, ['1', 'true', 'yes', 'on'], true);
    }
}
