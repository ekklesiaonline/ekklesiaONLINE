<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiRateLimitFilter implements FilterInterface
{
    private const DEFAULT_WINDOW_SECONDS = 60;
    private const DEFAULT_MAX_ATTEMPTS = 60;

    /**
     * Path-specific tighter limits for high-risk endpoints.
     * Format: [pattern => maxAttemptsPerWindow]
     *
     * @var array<string, int>
     */
    private array $pathLimits = [
        '#^attendance/(mark|bulk-mark)$#' => 40,
        '#^diocesan/registrations/#' => 20,
        '#^members/(store|update|delete|transfer|reassign|attach-parish|detach-parish|set-home-parish)#' => 30,
        '#^members/province-staff/(store|update|delete)#' => 20,
        '#^guests/(store|update|delete)#' => 30,
        '#^followup/(store|update-status|delete)#' => 25,
        '#^events/(store|update|delete|register)#' => 30,
        '#^events/registrations/.+/status$#' => 25,
        '#^donations/(store|delete)#' => 20,
        '#^expenses/(store|update|delete)#' => 20,
        '#^volunteers/(store-team|add-member|remove-member|schedules/)#' => 30,
        '#^families/(store|update|delete|addMember|removeMember)#' => 25,
        '#^pledges/(store|update|delete)#' => 20,
        '#^leadership/(store|update|delete)#' => 20,
        '#^standing/refresh$#' => 15,
        '#^guest-conversions/(convert|revert)#' => 20,
        '#^profile/(update|delete-picture|complete)$#' => 20,
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $method = strtoupper((string) $request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        $windowSeconds = (int) (env('security.apiRateLimitWindowSeconds') ?: self::DEFAULT_WINDOW_SECONDS);
        $defaultLimit = (int) (env('security.apiRateLimitPerWindow') ?: self::DEFAULT_MAX_ATTEMPTS);

        if ($windowSeconds <= 0) {
            $windowSeconds = self::DEFAULT_WINDOW_SECONDS;
        }
        if ($defaultLimit <= 0) {
            $defaultLimit = self::DEFAULT_MAX_ATTEMPTS;
        }

        $path = trim((string) $request->getUri()->getPath(), '/');
        $userId = $this->resolveUserId();
        $pathLimit = $this->resolvePathLimit($path, $defaultLimit, $userId !== null);

        $identity = $this->resolveIdentity($request, $userId);
        $cacheKey = 'api_rl_' . sha1($identity . '|' . $path . '|' . $method);

        $cache = \Config\Services::cache();
        $attempts = (int) ($cache->get($cacheKey) ?? 0);

        if ($attempts >= $pathLimit) {
            $this->logThrottleEvent($request, $path, $method, $pathLimit, $windowSeconds, $userId);

            $response = service('response');
            return $response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $windowSeconds)
                ->setHeader('X-RateLimit-Limit', (string) $pathLimit)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Too many requests. Please try again shortly.',
                    'error' => [
                        'code' => 'RATE_LIMITED',
                        'message' => 'Too many requests. Please try again shortly.',
                    ],
                    'meta' => [
                        'http_status' => 429,
                        'retry_after' => $windowSeconds,
                        'ts' => date('c'),
                    ],
                ]);
        }

        if ($attempts === 0) {
            $cache->save($cacheKey, 1, $windowSeconds);
            return null;
        }

        $ttl = $cache->getMetaData($cacheKey)['expire'] ?? (time() + $windowSeconds);
        $cache->save($cacheKey, $attempts + 1, max(1, (int) $ttl - time()));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function resolveIdentity(RequestInterface $request, ?string $userId): string
    {
        $ip = (string) $request->getIPAddress();
        if ($userId !== null) {
            return 'user:' . $userId . '|ip:' . $ip;
        }

        return 'anon:' . $ip;
    }

    private function resolvePathLimit(string $path, int $defaultLimit, bool $isAuthenticated): int
    {
        $resolvedLimit = $defaultLimit;

        foreach ($this->pathLimits as $pattern => $limit) {
            if (preg_match($pattern, $path) === 1) {
                $resolvedLimit = $limit;
                break;
            }
        }

        if (!$isAuthenticated) {
            $anonLimit = (int) (env('security.apiRateLimitAnonPerWindow') ?: 20);
            if ($anonLimit > 0) {
                $resolvedLimit = min($resolvedLimit, $anonLimit);
            }
        }

        return $resolvedLimit;
    }

    private function resolveUserId(): ?string
    {
        try {
            if (function_exists('auth') && auth()->loggedIn()) {
                return (string) auth()->id();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function logThrottleEvent(RequestInterface $request, string $path, string $method, int $limit, int $windowSeconds, ?string $userId): void
    {
        try {
            service('monitoring')->log('rate_limit.exceeded', [
                'path' => '/' . $path,
                'method' => $method,
                'ip' => (string) $request->getIPAddress(),
                'user_id' => $userId !== null ? (int) $userId : null,
                'limit' => $limit,
                'window_seconds' => $windowSeconds,
            ], 'warning');
        } catch (\Throwable) {
            // No-op: do not block request flow if monitoring fails.
        }
    }
}
