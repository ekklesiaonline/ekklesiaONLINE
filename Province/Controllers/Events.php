<?php

namespace Province\Controllers;

use App\Controllers\BaseController;
use App\Services\EventCoverUploadService;
use App\Services\EventPublishService;
use App\Services\EventScheduleService;

class Events extends BaseController
{
    public function index()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $status = (string) ($this->request->getGet('status') ?? 'published');
        $allowedStatuses = ['all', 'draft', 'published', 'cancelled', 'completed'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'published';
        $dioceseId = $this->selectedDioceseId($provinceId);
        if ($dioceseId === false) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        $builder = db_connect()->table('event e')
            ->select('e.id, e.title, e.event_type, e.location, e.start_at, e.end_at, e.status, e.organizer_type, e.host_parish_id, hp.name AS host_parish_name, d.name AS diocese_name')
            ->join('parish hp', 'hp.id = e.host_parish_id', 'left')
            ->join('diocese d', 'd.id = e.organizer_diocese_id', 'left')
            ->groupStart()
                ->where('e.organizer_type', 'province')
                ->where('e.organizer_province_id', $provinceId);

        if ($dioceseId !== null) {
            $builder->orGroupStart()
                ->where('e.organizer_type', 'diocese')
                ->where('e.organizer_diocese_id', $dioceseId)
            ->groupEnd();
        } else {
            $builder->orGroupStart()
                ->where('e.organizer_type', 'diocese')
                ->whereIn('e.organizer_diocese_id', static fn ($b) => $b->select('id')->from('diocese')->where('province_id', $provinceId))
            ->groupEnd();
        }

        $events = $builder->groupEnd()
            ->when($status !== 'all', static fn ($query) => $query->where('e.status', $status))
            ->orderBy('e.start_at', 'ASC')->limit(200)->get()->getResultArray();

        foreach ($events as &$event) {
            if ($event['organizer_type'] === 'province') {
                $event['organizer_label'] = lang('Province.calendarProvinceLabel');
            } elseif ($event['organizer_type'] === 'diocese') {
                $event['organizer_label'] = $event['diocese_name'] ?? '';
            }
        }
        unset($event);
        $dioceses = db_connect()->table('diocese')->select('id, name')->where('province_id', $provinceId)->orderBy('name')->get()->getResultArray();

        return view('Province\Views\Events\index', array_merge($this->getCommonViewData(lang('App.navProvinceEvents')), compact('events', 'dioceses', 'dioceseId', 'status')));
    }

    public function calendar()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        EventPublishService::publishDueDraftEvents();

        $mode = in_array((string) ($this->request->getGet('mode') ?? 'month'), ['month', 'week', 'agenda'], true) ? (string) ($this->request->getGet('mode') ?? 'month') : 'month';
        $anchor = trim((string) ($this->request->getGet('date') ?? date('Y-m-d')));
        try {
            $anchorDate = new \DateTimeImmutable($anchor);
        } catch (\Throwable $e) {
            $anchorDate = new \DateTimeImmutable();
        }

        $dioceses = db_connect()->table('diocese')->select('id, name')->where('province_id', $provinceId)->orderBy('name')->get()->getResultArray();
        $colorPalette = ['#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6610f2', '#0dcaf0', '#d63384', '#20c997', '#6f42c1', '#ffc107'];
        $dioceseColors = [];
        foreach ($dioceses as $index => $diocese) {
            $dioceseColors[(int) $diocese['id']] = $colorPalette[$index % count($colorPalette)];
        }
        $provinceColor = '#6f42c1';

        if ($mode === 'month') {
            $periodStart = $anchorDate->modify('first day of this month')->setTime(0, 0);
            $periodEnd = $anchorDate->modify('last day of this month')->setTime(23, 59, 59);
            $gridStart = $this->startOfWeek($periodStart);
            $gridEnd = $this->endOfWeek($periodEnd);
        } elseif ($mode === 'week') {
            $gridStart = $this->startOfWeek($anchorDate);
            $gridEnd = $this->endOfWeek($anchorDate);
            $periodStart = $gridStart;
            $periodEnd = $gridEnd;
        } else {
            $periodStart = $anchorDate->setTime(0, 0);
            $periodEnd = $anchorDate->modify('+30 days')->setTime(23, 59, 59);
            $gridStart = $periodStart;
            $gridEnd = $periodEnd;
        }

        $nationalColor = '#000000';
        $events = $this->fetchCalendarEventsForRange($provinceId, $gridStart->format('Y-m-d H:i:s'), $gridEnd->format('Y-m-d H:i:s'));
        foreach ($events as &$event) {
            $event['color'] = match ($event['scope_level']) {
                'national' => $nationalColor,
                'province' => $provinceColor,
                default    => $dioceseColors[(int) $event['source_id']] ?? '#6c757d',
            };
            $event['source_label'] = match ($event['scope_level']) {
                'national' => lang('Province.calendarNationalLabel'),
                'province' => lang('Province.calendarProvinceLabel'),
                default    => $event['source_name'],
            };
            $event['list_url'] = match ($event['scope_level']) {
                'national' => base_url('province/events'),
                'province' => base_url('province/events'),
                default    => base_url('province/events?diocese_id=' . (int) $event['source_id'] . '&status=all'),
            };
        }

        $occurrences = $this->buildCalendarOccurrences($events, $gridStart, $gridEnd);
        $dayBuckets = $this->buildCalendarDayBuckets($gridStart, $gridEnd, $occurrences);

        return view('Province\Views\Events\calendar', array_merge($this->getCommonViewData(lang('App.navProvinceCalendar')), [
            'mode' => $mode,
            'anchor' => $anchorDate->format('Y-m-d'),
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'day_buckets' => $dayBuckets,
            'dioceses' => $dioceses,
            'diocese_colors' => $dioceseColors,
            'weeks' => $mode === 'month' ? $this->splitBucketsIntoWeeks($dayBuckets) : null,
        ]));
    }

    private function fetchCalendarEventsForRange(int $provinceId, string $start, string $end): array
    {
        $db = db_connect();

        $columns = "e.id, e.title, e.event_type, e.location, e.start_at, e.end_at, e.is_all_day, e.status, e.recurrence_pattern, e.recurrence_days_json, e.recurrence_until, e.organizer_type AS scope_level";

        $diocesan = $db->table('event e')
            ->select("{$columns}, 'diocese' AS source_scope, e.organizer_diocese_id AS source_id, d.name AS source_name")
            ->join('diocese d', 'd.id = e.organizer_diocese_id', 'inner')
            ->where('e.organizer_type', 'diocese')
            ->where('d.province_id', $provinceId)
            ->where('e.status !=', 'cancelled')
            ->groupStart()
                ->where('e.start_at <', $end)
                ->where("(e.end_at IS NULL OR e.end_at >= " . $db->escape($start) . ")", null, false)
                ->orGroupStart()
                    ->where('e.recurrence_pattern !=', 'none')
                    ->where('e.recurrence_pattern IS NOT NULL', null, false)
                    ->where('e.start_at <', $end)
                    ->groupStart()
                        ->where('e.recurrence_until IS NULL', null, false)
                        ->orWhere('e.recurrence_until >=', $start)
                    ->groupEnd()
                ->groupEnd()
            ->groupEnd()
            ->get()->getResultArray();

        $provinceEvents = $db->table('event e')
            ->select("{$columns}, 'province' AS source_scope, e.organizer_province_id AS source_id, p.name AS source_name")
            ->join('province p', 'p.id = e.organizer_province_id', 'inner')
            ->where('e.organizer_type', 'province')
            ->where('e.organizer_province_id', $provinceId)
            ->where('e.status !=', 'cancelled')
            ->groupStart()
                ->where('e.start_at <', $end)
                ->where("(e.end_at IS NULL OR e.end_at >= " . $db->escape($start) . ")", null, false)
                ->orGroupStart()
                    ->where('e.recurrence_pattern !=', 'none')
                    ->where('e.recurrence_pattern IS NOT NULL', null, false)
                    ->where('e.start_at <', $end)
                    ->groupStart()
                        ->where('e.recurrence_until IS NULL', null, false)
                        ->orWhere('e.recurrence_until >=', $start)
                    ->groupEnd()
                ->groupEnd()
            ->groupEnd()
            ->get()->getResultArray();

        $national = $db->table('event e')
            ->select("{$columns}, 'national' AS source_scope, e.organizer_parish_id AS source_id, c.name AS source_name")
            ->join('parish c', 'c.id = e.organizer_parish_id', 'inner')
            ->where('e.organizer_type', 'national')
            ->where('e.status !=', 'cancelled')
            ->groupStart()
                ->where('e.start_at <', $end)
                ->where("(e.end_at IS NULL OR e.end_at >= " . $db->escape($start) . ")", null, false)
                ->orGroupStart()
                    ->where('e.recurrence_pattern !=', 'none')
                    ->where('e.recurrence_pattern IS NOT NULL', null, false)
                    ->where('e.start_at <', $end)
                    ->groupStart()
                        ->where('e.recurrence_until IS NULL', null, false)
                        ->orWhere('e.recurrence_until >=', $start)
                    ->groupEnd()
                ->groupEnd()
            ->groupEnd()
            ->get()->getResultArray();

        $events = array_merge($diocesan, $provinceEvents, $national);
        usort($events, static fn (array $a, array $b): int => strcmp((string) $a['start_at'], (string) $b['start_at']));
        return $events;
    }

    private function buildCalendarOccurrences(array $events, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        foreach ($events as $event) {
            $pattern = (string) ($event['recurrence_pattern'] ?? 'none');
            if ($pattern === '' || $pattern === 'none') {
                $occurrences = array_merge($occurrences, $this->buildSingleEventOccurrences($event, $rangeStart, $rangeEnd));
            } else {
                $occurrences = array_merge($occurrences, $this->expandRecurringEvent($event, $rangeStart, $rangeEnd));
            }
        }
        return $occurrences;
    }

    private function buildSingleEventOccurrences(array $event, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        $eventStart = new \DateTimeImmutable((string) $event['start_at']);
        $eventEnd = !empty($event['end_at']) ? new \DateTimeImmutable((string) $event['end_at']) : $eventStart;
        $current = clone $eventStart;
        while ($current <= $eventEnd && $current <= $rangeEnd) {
            if ($current >= $rangeStart) {
                $occurrences[] = array_merge($event, ['occurrence_date' => $current->format('Y-m-d')]);
            }
            $current = $current->modify('+1 day');
        }
        return $occurrences;
    }

    private function expandRecurringEvent(array $event, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        $pattern = (string) ($event['recurrence_pattern'] ?? 'none');
        if ($pattern === '' || $pattern === 'none') {
            return $occurrences;
        }

        $eventStart = new \DateTimeImmutable((string) $event['start_at']);
        $untilRaw = (string) ($event['recurrence_until'] ?? '');
        $until = $untilRaw !== '' ? new \DateTimeImmutable($untilRaw . ' 23:59:59') : $rangeEnd;
        if ($until > $rangeEnd) {
            $until = $rangeEnd;
        }

        $cursor = $eventStart > $rangeStart ? $eventStart : $rangeStart;

        if ($pattern === 'daily') {
            $period = new \DatePeriod($cursor, new \DateInterval('P1D'), $until);
            foreach ($period as $date) {
                $occurrences[] = array_merge($event, ['occurrence_date' => $date->format('Y-m-d')]);
            }
            return $occurrences;
        }

        if (in_array($pattern, ['weekly', 'biweekly'], true)) {
            $allowedDays = json_decode((string) ($event['recurrence_days_json'] ?? '[]'), true);
            $allowedDays = is_array($allowedDays) ? array_map('strtolower', $allowedDays) : [];
            if ($allowedDays === []) {
                $allowedDays = [strtolower($eventStart->format('D'))];
            }

            $interval = $pattern === 'biweekly' ? 14 : 7;
            $period = new \DatePeriod($cursor, new \DateInterval('P1D'), $until);
            foreach ($period as $date) {
                $dayName = strtolower($date->format('D'));
                if (!in_array($dayName, $allowedDays, true)) {
                    continue;
                }
                $diffDays = (int) $eventStart->diff($date)->format('%r%a');
                if ($diffDays % $interval !== 0) {
                    continue;
                }
                $occurrences[] = array_merge($event, ['occurrence_date' => $date->format('Y-m-d')]);
            }
            return $occurrences;
        }

        if ($pattern === 'monthly') {
            $period = new \DatePeriod($cursor, new \DateInterval('P1M'), $until);
            $startDay = (int) $eventStart->format('j');
            foreach ($period as $date) {
                $target = $date->setDate((int) $date->format('Y'), (int) $date->format('n'), min($startDay, (int) $date->format('t')));
                if ($target < $eventStart || $target < $rangeStart || $target > $rangeEnd || $target > $until) {
                    continue;
                }
                $occurrences[] = array_merge($event, ['occurrence_date' => $target->format('Y-m-d')]);
            }
            return $occurrences;
        }

        return $occurrences;
    }

    private function buildCalendarDayBuckets(\DateTimeImmutable $start, \DateTimeImmutable $end, array $occurrences): array
    {
        $buckets = [];
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $buckets[$date] = ['date' => $date, 'label' => $current->format('D M j'), 'occurrences' => []];
            $current = $current->modify('+1 day');
        }
        foreach ($occurrences as $occurrence) {
            $date = (string) $occurrence['occurrence_date'];
            if (isset($buckets[$date])) {
                $buckets[$date]['occurrences'][] = $occurrence;
            }
        }
        return $buckets;
    }

    private function splitBucketsIntoWeeks(array $buckets): array
    {
        $weeks = [];
        $currentWeek = [];
        foreach ($buckets as $bucket) {
            $currentWeek[] = $bucket;
            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }
        if ($currentWeek !== []) {
            while (count($currentWeek) < 7) {
                $currentWeek[] = ['date' => '', 'label' => '', 'occurrences' => []];
            }
            $weeks[] = $currentWeek;
        }
        return $weeks;
    }

    private function startOfWeek(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('w');
        return $date->modify('-' . $dayOfWeek . ' days')->setTime(0, 0);
    }

    private function endOfWeek(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('w');
        return $date->modify('+' . (6 - $dayOfWeek) . ' days')->setTime(23, 59, 59);
    }

    protected function provinceId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->inGroup('provinceadmin') || !$user->can('province.admin')) {
            return null;
        }
        $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
        $provinceId = (int) ($profile['province_id'] ?? 0);
        return $provinceId > 0 && db_connect()->table('province')->where('id', $provinceId)->countAllResults() > 0 ? $provinceId : null;
    }

    private function selectedDioceseId(int $provinceId): int|false|null
    {
        $value = (string) ($this->request->getGet('diocese_id') ?? 'all');
        if ($value === '' || $value === 'all') return null;
        if (!ctype_digit($value) || db_connect()->table('diocese')->where('id', (int) $value)->where('province_id', $provinceId)->countAllResults() === 0) return false;
        return (int) $value;
    }
}
