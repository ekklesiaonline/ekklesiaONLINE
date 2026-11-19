<?php

declare(strict_types=1);

use App\Libraries\RecipientSendLimiter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * RecipientSendLimiter: fixed-window counting plus cross-process lock
 * exclusivity (the guarantee that closes the concurrent-send race).
 *
 * @internal
 */
final class RecipientSendLimiterTest extends CIUnitTestCase
{
    private const KEY = 'limiter_test_rl_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Services::injectMock('cache', new MockCache());
    }

    protected function tearDown(): void
    {
        @unlink(RecipientSendLimiter::lockPath(self::KEY));
        $this->resetServices();
        parent::tearDown();
    }

    public function testAllowsUpToMaxThenRejects(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->assertFalse(RecipientSendLimiter::reserve(self::KEY, 3, 3600), "Send #{$i} must be allowed.");
        }

        $this->assertTrue(RecipientSendLimiter::reserve(self::KEY, 3, 3600));
        $this->assertTrue(RecipientSendLimiter::exhausted(self::KEY, 3));
    }

    public function testRejectedAttemptsDoNotMoveWindowDeadline(): void
    {
        RecipientSendLimiter::reserve(self::KEY, 1, 3600);
        $reset = Services::cache()->get(self::KEY)['reset'];

        RecipientSendLimiter::reserve(self::KEY, 1, 3600);
        RecipientSendLimiter::reserve(self::KEY, 1, 3600);

        $entry = Services::cache()->get(self::KEY);
        $this->assertSame($reset, $entry['reset']);
        $this->assertSame(3, $entry['count']);
    }

    public function testExpiredWindowStartsFresh(): void
    {
        Services::cache()->save(self::KEY, ['count' => 99, 'reset' => time() - 1], 60);

        $this->assertFalse(RecipientSendLimiter::reserve(self::KEY, 3, 3600));
        $this->assertSame(1, Services::cache()->get(self::KEY)['count']);
    }

    /**
     * While one process holds the per-recipient lock, a separate PHP
     * process cannot acquire it — so reserve() calls from concurrent
     * requests are serialized and cannot lose updates.
     */
    public function testLockIsExclusiveAcrossProcesses(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is unavailable.');
        }

        $path = RecipientSendLimiter::lockPath(self::KEY);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'c');
        $this->assertNotFalse($handle);
        $this->assertTrue(flock($handle, LOCK_EX));

        try {
            $this->assertSame('blocked', $this->tryLockInChild($path));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertSame('acquired', $this->tryLockInChild($path), 'Lock must be free once released.');
    }

    private function tryLockInChild(string $path): string
    {
        $code = '$h = fopen(' . var_export($path, true) . ', "c");'
            . 'echo flock($h, LOCK_EX | LOCK_NB) ? "acquired" : "blocked";';

        $proc = proc_open(
            [PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($proc);

        $out = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $out;
    }
}
