<?php

namespace App\Services;

class EventScheduleService
{
    /**
     * Resolve schedule fields from form post data.
     *
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public static function resolveSchedulePayload(array $postData): array
    {
        $scheduleMode = trim((string) ($postData['schedule_mode'] ?? 'one_day'));
        if (!in_array($scheduleMode, ['one_day', 'all_day', 'multi_day'], true)) {
            $scheduleMode = 'one_day';
        }

        if ($scheduleMode === 'all_day') {
            $date = trim((string) ($postData['single_date'] ?? ''));
            if ($date === '') {
                throw new \RuntimeException(lang('Events.errorDateRequired'));
            }

            return [
                'schedule_mode' => 'all_day',
                'is_all_day' => 1,
                'start_at' => $date . ' 00:00:00',
                'end_at' => $date . ' 23:59:59',
            ];
        }

        if ($scheduleMode === 'multi_day') {
            $startDate = trim((string) ($postData['multi_start_date'] ?? ''));
            $startTime = trim((string) ($postData['multi_start_time'] ?? ''));
            $endDate = trim((string) ($postData['multi_end_date'] ?? ''));
            $endTime = trim((string) ($postData['multi_end_time'] ?? ''));

            $startAt = self::combineDateAndTime($startDate, $startTime, true);
            $endAt = self::combineDateAndTime($endDate, $endTime, true);

            if (strtotime($endAt) < strtotime($startAt)) {
                throw new \RuntimeException(lang('Members.endDateAfterStart'));
            }

            return [
                'schedule_mode' => 'multi_day',
                'is_all_day' => 0,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ];
        }

        $date = trim((string) ($postData['single_date'] ?? ''));
        $startTime = trim((string) ($postData['single_start_time'] ?? ''));
        $endTime = trim((string) ($postData['single_end_time'] ?? ''));

        $startAt = self::combineDateAndTime($date, $startTime, true);
        $endAt = $endTime !== '' ? self::combineDateAndTime($date, $endTime, true) : '';

        if ($endAt !== '' && strtotime($endAt) < strtotime($startAt)) {
            throw new \RuntimeException(lang('Members.endDateAfterStart'));
        }

        return [
            'schedule_mode' => 'one_day',
            'is_all_day' => 0,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    public static function combineDateAndTime(string $date, string $time, bool $requireTime): string
    {
        if ($date === '') {
            throw new \RuntimeException(lang('Events.errorDateRequired'));
        }

        if ($requireTime && $time === '') {
            throw new \RuntimeException(lang('Events.errorTimeRequired'));
        }

        $dateTime = $date . ' ' . ($time !== '' ? $time : '00:00');
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            throw new \RuntimeException(lang('Events.errorInvalidSchedule'));
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Normalize recurrence day checkboxes to lowercase weekday names.
     *
     * @param array<int, string> $days
     * @return array<int, string>
     */
    public static function normalizeRecurrenceDays(array $days, string $startAt): array
    {
        $allowedDays = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $normalized = array_values(array_intersect($allowedDays, array_map('strtolower', $days)));

        if ($normalized !== []) {
            return $normalized;
        }

        $weekdayMap = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
        $weekdayKey = date('D', strtotime($startAt));

        return isset($weekdayMap[$weekdayKey]) ? [$weekdayMap[$weekdayKey]] : [];
    }
}
