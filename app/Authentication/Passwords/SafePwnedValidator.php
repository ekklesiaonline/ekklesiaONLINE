<?php

declare(strict_types=1);

namespace App\Authentication\Passwords;

use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Authentication\Passwords\BaseValidator;
use CodeIgniter\Shield\Authentication\Passwords\PwnedValidator;
use CodeIgniter\Shield\Authentication\Passwords\ValidatorInterface;
use CodeIgniter\Shield\Config\Auth as AuthConfig;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;

/**
 * Wraps Shield's PwnedValidator so an unreachable Have I Been Pwned
 * API degrades gracefully instead of breaking registration/password
 * changes. On any throwable, logs a monitoring warning and returns a
 * passing result so the remaining validators still apply.
 */
class SafePwnedValidator extends BaseValidator implements ValidatorInterface
{
    private const DEFAULT_TIMEOUT_SECONDS = 3;

    private ValidatorInterface $inner;

    public function __construct(AuthConfig $config, ?ValidatorInterface $inner = null)
    {
        parent::__construct($config);
        $this->inner = $inner ?? new PwnedValidator($config);
    }

    public function check(string $password, ?User $user = null): Result
    {
        $timeout = $this->timeoutSeconds();

        // Shield's PwnedValidator resolves the shared cURL service
        // internally, so seed it with our timeout options first.
        Services::resetSingle('curlrequest');
        Services::curlrequest([
            'timeout'         => $timeout,
            'connect_timeout' => $timeout,
        ]);

        try {
            $result = $this->inner->check($password, $user);
        } catch (\Throwable $e) {
            log_message('warning', 'security.pwned_api.unreachable: {message}', [
                'message' => $e->getMessage(),
            ]);

            return new Result(['success' => true]);
        }

        if (! $result->isOK()) {
            $this->error      = $result->reason();
            $this->suggestion = is_string($result->extraInfo()) ? $result->extraInfo() : null;
        }

        return $result;
    }

    private function timeoutSeconds(): int
    {
        $configured = (int) env('security.pwnedValidatorTimeoutSeconds', self::DEFAULT_TIMEOUT_SECONDS);

        return $configured > 0 ? $configured : self::DEFAULT_TIMEOUT_SECONDS;
    }
}
