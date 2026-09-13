<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EventScheduleServiceTest extends CIUnitTestCase
{
    public function testResolveOneDaySchedule(): void
    {
        $post = [
            'schedule_mode' => 'one_day',
            'single_date' => '2026-08-15',
            'single_start_time' => '09:00',
            'single_end_time' => '11:00',
        ];

        $schedule = \App\Services\EventScheduleService::resolveSchedulePayload($post);

        $this->assertSame('one_day', $schedule['schedule_mode']);
        $this->assertSame(0, $schedule['is_all_day']);
        $this->assertSame('2026-08-15 09:00:00', $schedule['start_at']);
        $this->assertSame('2026-08-15 11:00:00', $schedule['end_at']);
    }

    public function testResolveAllDaySchedule(): void
    {
        $post = [
            'schedule_mode' => 'all_day',
            'single_date' => '2026-08-15',
        ];

        $schedule = \App\Services\EventScheduleService::resolveSchedulePayload($post);

        $this->assertSame('all_day', $schedule['schedule_mode']);
        $this->assertSame(1, $schedule['is_all_day']);
        $this->assertSame('2026-08-15 00:00:00', $schedule['start_at']);
        $this->assertSame('2026-08-15 23:59:59', $schedule['end_at']);
    }

    public function testResolveMultiDaySchedule(): void
    {
        $post = [
            'schedule_mode' => 'multi_day',
            'multi_start_date' => '2026-08-15',
            'multi_start_time' => '09:00',
            'multi_end_date' => '2026-08-17',
            'multi_end_time' => '17:00',
        ];

        $schedule = \App\Services\EventScheduleService::resolveSchedulePayload($post);

        $this->assertSame('multi_day', $schedule['schedule_mode']);
        $this->assertSame('2026-08-15 09:00:00', $schedule['start_at']);
        $this->assertSame('2026-08-17 17:00:00', $schedule['end_at']);
    }

    public function testMultiDayEndBeforeStartThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        \App\Services\EventScheduleService::resolveSchedulePayload([
            'schedule_mode' => 'multi_day',
            'multi_start_date' => '2026-08-17',
            'multi_start_time' => '09:00',
            'multi_end_date' => '2026-08-15',
            'multi_end_time' => '17:00',
        ]);
    }

    public function testNormalizeRecurrenceDaysFallsBackToStartDay(): void
    {
        $days = \App\Services\EventScheduleService::normalizeRecurrenceDays([], '2026-08-15 09:00:00');

        $this->assertSame(['sat'], $days);
    }

    public function testNormalizeRecurrenceDaysKeepsProvidedValues(): void
    {
        $days = \App\Services\EventScheduleService::normalizeRecurrenceDays(['Mon', 'wed'], '2026-08-15 09:00:00');

        $this->assertSame(['mon', 'wed'], $days);
    }
}
