<?php

use App\Libraries\MonthRangeGuard;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: monthly summary rebuilds (attendance:sync-diocesan-aggregates,
 * attendance:sync-sunday-service-metrics) replace whole months, so ranges that
 * start or end mid-month must be rejected before any write.
 *
 * @internal
 */
final class MonthRangeGuardTest extends CIUnitTestCase
{
    private const TODAY = '2026-06-20';

    public function testFullMonthRangeIsAccepted(): void
    {
        $this->assertNull(MonthRangeGuard::check('2025-07-01', '2025-07-31', self::TODAY));
    }

    public function testMultiMonthRangeEndingOnMonthEndIsAccepted(): void
    {
        $this->assertNull(MonthRangeGuard::check('2025-01-01', '2025-03-31', self::TODAY));
    }

    public function testLeapYearFebruaryEndIsAccepted(): void
    {
        $this->assertNull(MonthRangeGuard::check('2024-02-01', '2024-02-29', self::TODAY));
    }

    public function testRangeEndingTodayIsAccepted(): void
    {
        // Nightly cron shape: 1st of previous month -> today.
        $this->assertNull(MonthRangeGuard::check('2026-05-01', self::TODAY, self::TODAY));
    }

    public function testMidMonthFromIsRejected(): void
    {
        // Finding example: would drop the July 6 and 13 services.
        $error = MonthRangeGuard::check('2025-07-15', '2025-07-31', self::TODAY);

        $this->assertNotNull($error);
        $this->assertStringContainsString('--from', $error);
        $this->assertStringContainsString('2025-07-01', $error);
    }

    public function testMidMonthToInPastMonthIsRejected(): void
    {
        $error = MonthRangeGuard::check('2025-07-01', '2025-07-15', self::TODAY);

        $this->assertNotNull($error);
        $this->assertStringContainsString('--to', $error);
        $this->assertStringContainsString('2025-07-31', $error);
    }

    public function testDiocesanSyncRebuildsBuckets(): void
    {
        $this->assertTrue(MonthRangeGuard::rebuildsMonthlyBuckets(false, 0));
        $this->assertTrue(MonthRangeGuard::rebuildsMonthlyBuckets(true, 0));
    }

    public function testParishOnlySyncSkipsBuckets(): void
    {
        $this->assertFalse(MonthRangeGuard::rebuildsMonthlyBuckets(false, 9));
    }

    public function testMidMonthStartRejectedOnDiocesanPathOnly(): void
    {
        // Diocesan path: guard applies and rejects.
        $this->assertTrue(MonthRangeGuard::rebuildsMonthlyBuckets(false, 0));
        $this->assertNotNull(MonthRangeGuard::check('2025-07-15', '2025-07-31', self::TODAY));

        // Parish path: command skips the guard, so the same range is allowed.
        $this->assertFalse(MonthRangeGuard::rebuildsMonthlyBuckets(false, 9));
    }

    public function testScheduledRollingWindowSnapsAndPassesGuard(): void
    {
        // Nightly refresh: --rolling-days 35 --rebuild-period-buckets.
        $from = MonthRangeGuard::rollingStart(35, true, self::TODAY);

        $this->assertSame('2026-05-01', $from); // raw start would be 2026-05-17
        $this->assertNull(MonthRangeGuard::check($from, self::TODAY, self::TODAY));
    }

    public function testParishRollingWindowKeepsExactStart(): void
    {
        $this->assertSame('2026-05-17', MonthRangeGuard::rollingStart(35, false, self::TODAY));
    }

    public function testRollingWindowCrossingYearBoundarySnaps(): void
    {
        $this->assertSame('2025-12-01', MonthRangeGuard::rollingStart(35, true, '2026-01-10'));
    }

    public function testNonLeapFebruaryEndBeforeThe28thIsRejected(): void
    {
        // 2025-02-28 is the month end; 2025-02-27 must be rejected.
        $error = MonthRangeGuard::check('2025-02-01', '2025-02-27', self::TODAY);

        $this->assertNotNull($error);
        $this->assertStringContainsString('2025-02-28', $error);
    }
}
