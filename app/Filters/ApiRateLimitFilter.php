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
        // Public unauthenticated intake: strictest cap.
        '#^connect/#' => 10,
        '#^attendance/(mark|bulk-mark)$#' => 40,
        '#^(diocese/registrations|registrations)/#' => 20,
        '#^members/(store|update|delete|transfer|reassign|attach-parish|detach-parish|set-home-parish)#' => 30,
        '#^members/province-staff/(store|update|delete)#' => 20,
        '#^guests/(store|update|delete)#' => 30,
        '#^visitors/(store|update|delete|promote|revert-conversion|promote-attender|demote)#' => 30,
        '#^followup/(store|update-status|delete)#' => 25,
        '#^events/(store|update|delete|register)#' => 30,
        '#^events/registrations/.+/status$#' => 25,
        '#^donations/(store|delete)#' => 20,
        '#^expenses/(store|update|delete)#' => 20,
        '#^volunteers/(store-team|add-member|remove-member|schedules/|storeTeam|store-schedule|update-schedule|cancel-schedule|confirm-schedule)#' => 30,
        '#^families/(store|update|delete|addMember|removeMember)#' => 25,
        '#^pledges/(store|update|delete)#' => 20,
        '#^leadership/(store|update|delete)#' => 20,
        '#^standing/refresh$#' => 15,
        '#^profile/(update|delete-picture|complete)$#' => 20,
        '#^parochial-report/save$#' => 20,
        '#^parochial-report/email-pdf$#' => 10,
        '#^attendance/bulkMark$#' => 40,
        '#^worship-planning/#' => 30,
        '#^parish/clergy/request$#' => 20,
        '#^(parish|diocese|province|system)/email-settings/test$#' => 10,
        // Outbound credential mail (clergy invitation resend + admin account
        // creation/password reset): same strictest tier as email test sends.
        '#^diocese/clergy/\d+/resend-invitation$#' => 10,
        '#^diocese/(admins|parish-admins)/#' => 15,
        '#^diocese/clergy/#' => 20,
        '#^diocese/clergy-review/#' => 20,
        '#^diocese/email-settings$#' => 20,
        '#^diocese/(deaneries|congregations|followup-workflow)/#' => 30,
        '#^chart-accounts/#' => 30,
        '#^parish_list/#' => 30,
        '#^system/liturgical-source/#' => 20,
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

        $path = $this->normalizePath($request);
        $userId = $this->resolveUserId();
        $pathLimit = $this->resolvePathLimit($path, $defaultLimit, $userId !== null);

        $identity = $this->resolveIdentity($request, $userId);
        // Public connect-card intake shares one bucket per identity across all
        // tokens — otherwise token case variants or token cycling each get a
        // fresh quota.
        $bucketPath = preg_match('#^connect(/|$)#i', $path) === 1 ? 'connect' : $path;

        // Credential-mail resends share one bucket per identity across all
        // recipients — per-target quotas would allow 10/min *per account*.
        if (preg_match('#^diocese/clergy/\d+/resend-invitation$#', $path) === 1) {
            $bucketPath = 'diocese/clergy/resend-invitation';
        }
        $cacheKey = 'api_rl_' . sha1($identity . '|' . $bucketPath . '|' . $method);

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

    /**
     * Route-relative path. When the entry script appears in the request path
     * (`index.php/volunteers/...` — e.g. `$indexPage` enabled or the URI
     * resolved via REQUEST_URI), every anchored $pathLimits pattern misses and
     * endpoints silently fall back to the default limit.
     */
    private function normalizePath(RequestInterface $request): string
    {
        $path = method_exists($request, 'getPath')
            ? (string) $request->getPath()
            : (string) $request->getUri()->getPath();

        $path = trim($path, '/');

        // Strip the front-controller script even when App::$indexPage is ''
        // (pretty URLs): requests can still arrive via /index.php/... and the
        // literal segment is never a valid route.
        $indexPage = basename((string) config('App')->indexPage) ?: 'index.php';
        if ($path === $indexPage || str_starts_with($path, $indexPage . '/')) {
            $path = (string) substr($path, strlen($indexPage) + 1);
        }

        return $path;
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
