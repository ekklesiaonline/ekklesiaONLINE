<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * LoginRateLimitFilter
 *
 * Limits login and registration POST requests to 10 attempts per minute
 * per IP address. Returns HTTP 429 with a Retry-After header when the
 * limit is exceeded.
 *
 * Applied via Filters.php $filters to 'login', 'login/magic-link',
 * 'login/verify-magic-link', 'register', 'session/device-verify*', and
 * 'connect/*' URI patterns.
 * Fires on POST requests; GET requests pass through EXCEPT
 * 'login/verify-magic-link', which performs a token lookup + delete per
 * request and therefore must also be throttled.
 * 'connect/*' requests are counted in a separate per-IP bucket so public
 * connect-card submissions cannot block authentication.
 */
class LoginRateLimitFilter implements FilterInterface
{
    /** Maximum attempts allowed within the window. */
    private const MAX_ATTEMPTS = 10;

    /** Rolling window in seconds. */
    private const WINDOW_SECONDS = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        // Only rate-limit write (POST) requests, plus GET on the magic-link
        // verify endpoint (each request performs a token lookup + delete).
        if (strtoupper((string) $request->getMethod()) !== 'POST'
            && ! self::isMagicLinkVerifyPath($request)) {
            return null;
        }

        $maxAttempts = (int) (env('security.loginRateLimitPerWindow') ?: self::MAX_ATTEMPTS);
        $windowSeconds = (int) (env('security.loginRateLimitWindowSeconds') ?: self::WINDOW_SECONDS);
        if ($maxAttempts <= 0) {
            $maxAttempts = self::MAX_ATTEMPTS;
        }
        if ($windowSeconds <= 0) {
            $windowSeconds = self::WINDOW_SECONDS;
        }

        $cache = \Config\Services::cache();
        $ip    = (string) $request->getIPAddress();
        $key   = self::bucketPrefix($request) . md5($ip);

        $attempts = (int) ($cache->get($key) ?? 0);

        if ($attempts >= $maxAttempts) {
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $windowSeconds)
                ->setBody('Too many attempts. Please wait and try again.');
        }

        // Increment counter; set expiry only on the first attempt.
        if ($attempts === 0) {
            $cache->save($key, 1, $windowSeconds);
        } else {
            $ttl = $cache->getMetaData($key)['expire'] ?? (time() + $windowSeconds);
            $cache->save($key, $attempts + 1, max(1, $ttl - time()));
        }

        return null;
    }

    /**
     * GET on the magic-link verify endpoint performs a DB lookup and
     * delete per request — the only non-POST path this filter throttles.
     */
    private static function isMagicLinkVerifyPath(RequestInterface $request): bool
    {
        return self::requestPath($request) === 'login/verify-magic-link';
    }

    /**
     * Public connect-card intake uses its own counter so card submissions
     * cannot exhaust the shared authentication quota (and vice versa).
     */
    private static function bucketPrefix(RequestInterface $request): string
    {
        $path = self::requestPath($request);

        if ($path === 'connect' || str_starts_with($path, 'connect/')) {
            return 'connect_rl_';
        }

        return 'login_rl_';
    }

    private static function requestPath(RequestInterface $request): string
    {
        $path = method_exists($request, 'getPath')
            ? (string) $request->getPath()
            : (string) $request->getUri()->getPath();

        return ltrim($path, '/');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
