<?php

namespace Tests\Unit;

use App\Models\DiocesanAttendanceMonthlyModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class DiocesanAttendanceMonthlyModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query('DROP TABLE IF EXISTS diocesan_attendance_monthly');
        $this->db->query('CREATE TABLE diocesan_attendance_monthly (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            diocese_id INTEGER NOT NULL,
            period_month TEXT NOT NULL,
            sunday_services_count INTEGER NOT NULL DEFAULT 0,
            sunday_attended_total INTEGER NOT NULL DEFAULT 0,
            weekday_services_count INTEGER NOT NULL DEFAULT 0,
            weekday_attended_total INTEGER NOT NULL DEFAULT 0
        )');
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        $this->db->query('DROP TABLE IF EXISTS diocesan_attendance_monthly');
        parent::tearDown();
    }

    public function testLastMonthOnMarch31ResolvesToFebruary(): void
    {
        // strtotime('-1 month') on Mar 31 overflows Feb 31 into March.
        Time::setTestNow('2027-03-31 10:00:00');
        $this->db->query(
            "INSERT INTO diocesan_attendance_monthly (diocese_id, period_month, sunday_services_count, sunday_attended_total)
             VALUES (1, '2027-02-01', 4, 200), (1, '2027-03-01', 5, 999)"
        );

        $result = (new DiocesanAttendanceMonthlyModel())->getDiocesanSundayAverageByPeriod(1, 'last_month');

        $this->assertSame('2027-02-01', $result['period_start_month']);
        $this->assertSame('2027-02-01', $result['period_end_month']);
        $this->assertSame(4, $result['sunday_services_count']);
        $this->assertSame(200, $result['sunday_attended_total']);
    }
}
