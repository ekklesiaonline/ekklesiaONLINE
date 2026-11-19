<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * RecipientSendLimiter
 *
 * Fixed-window, per-recipient send cap whose read → increment → decision
 * sequence runs under an exclusive per-key file lock, so concurrent
 * requests (from different sessions) cannot both read the same count and
 * each consume one slot (lost update, CWE-362).
 *
 * The window deadline is stored with the counter and never moved by later
 * attempts (including rejected ones).
 *
 * Scope: flock() serializes requests on a single host, matching the
 * configured FileHandler cache. Multi-host deployments need a shared
 * atomic store (e.g. Redis INCR + EXPIREAT) instead.
 */
final class RecipientSendLimiter
{
    /**
     * Reserve one send for $key. Returns true when the cap is exceeded
     * (the attempt is still counted, so retries cannot reset the window).
     */
    public static function reserve(string $key, int $max, int $windowSeconds): bool
    {
        return self::withLock($key, static function () use ($key, $max, $windowSeconds): bool {
            $cache = \Config\Services::cache();
            $now   = time();
            $entry = $cache->get($key);

            if (! is_array($entry) || (int) ($entry['reset'] ?? 0) <= $now) {
                $cache->save($key, ['count' => 1, 'reset' => $now + $windowSeconds], $windowSeconds);

                return false;
            }

            $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
            $cache->save($key, $entry, max(1, (int) $entry['reset'] - $now));

            return $entry['count'] > $max;
        });
    }

    /**
     * Read-only check: true when the allowance is already used up.
     */
    public static function exhausted(string $key, int $max): bool
    {
        $entry = \Config\Services::cache()->get($key);

        return is_array($entry)
            && (int) ($entry['reset'] ?? 0) > time()
            && (int) ($entry['count'] ?? 0) >= $max;
    }

    /**
     * Absolute path of the per-key lock file (public for tests).
     */
    public static function lockPath(string $key): string
    {
        return rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'locks' . DIRECTORY_SEPARATOR . md5($key) . '.lock';
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private static function withLock(string $key, callable $callback)
    {
        $path = self::lockPath($key);
        $dir  = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = @fopen($path, 'c');
        if ($handle === false) {
            // Fail closed: without a lock the cap cannot be enforced safely.
            log_message('warning', 'RecipientSendLimiter: cannot open lock for {key}', ['key' => $key]);

            return true;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return true;
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
