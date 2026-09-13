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
 * Applied via Filters.php $filters to the 'login' and 'register' URI patterns.
 * Only fires on POST requests; GET requests (showing the form) pass through.
 */
class LoginRateLimitFilter implements FilterInterface
{
    /** Maximum attempts allowed within the window. */
    private const MAX_ATTEMPTS = 10;

    /** Rolling window in seconds. */
    private const WINDOW_SECONDS = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        // Only rate-limit write (POST) requests.
        if (strtoupper((string) $request->getMethod()) !== 'POST') {
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
        $key   = 'login_rl_' . md5($ip);

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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
