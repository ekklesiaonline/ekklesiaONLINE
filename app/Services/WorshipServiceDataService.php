<?php

namespace App\Services;

/**
 * WorshipServiceDataService
 *
 * Data/persistence helpers for worship service records: validation rules,
 * business-rule checks, POST payload assembly, related-data upserts, JSON
 * field decoders, and defensive liturgical/metric lookups. Extracted from
 * Worship\Controllers\Services (phase 5 split); behavior unchanged.
 */
class WorshipServiceDataService
{
    protected $serviceMetricsModel;
    protected $servicePeopleModel;
    protected $liturgicalCalendarEntryModel;

    public function __construct()
    {
        $this->serviceMetricsModel = model('ServiceMetricsModel');
        $this->servicePeopleModel = model('ServicePeopleModel');
        $this->liturgicalCalendarEntryModel = model('LiturgicalCalendarEntryModel');
    }

    public function getServiceValidationRules(): array
    {
        return [
            'date' => 'required|valid_date[Y-m-d]',
            'service' => 'permit_empty',
            'time' => 'permit_empty|regex_match[/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/]',
            'liturgical_day' => 'permit_empty|max_length[255]',
            'liturgical_season' => 'permit_empty|max_length[80]',
            'place' => 'permit_empty|max_length[255]',
            'preacher' => 'permit_empty|max_length[255]',
            'celebrant' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[5000]',

            'format_of_service' => 'permit_empty|in_list[In-Person,Hybrid,Online]',
            'attendance_classification' => 'permit_empty|in_list[Regular,Special]',
            'service_family' => 'permit_empty|in_list[holy_communion,daily_office,other]',
            'service_schedule' => 'permit_empty|in_list[sunday,weekday,private]',
            'reported_present_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'reported_communion_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'online_live_views' => 'permit_empty|integer|greater_than_equal_to[0]',
            'online_recorded_views_7d' => 'permit_empty|integer|greater_than_equal_to[0]',
            'online_peak_viewers' => 'permit_empty|integer|greater_than_equal_to[0]',
            'languages_csv' => 'permit_empty|max_length[2000]',

            'baptism_adult' => 'permit_empty|integer|greater_than_equal_to[0]',
            'baptism_child' => 'permit_empty|integer|greater_than_equal_to[0]',
            'confirmation_adult' => 'permit_empty|integer|greater_than_equal_to[0]',
            'confirmation_teen' => 'permit_empty|integer|greater_than_equal_to[0]',
            'reception_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'marriage_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'burial_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'eucharist_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'daily_office_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'online_live_viewers' => 'permit_empty|integer|greater_than_equal_to[0]',
            'online_recorded_views' => 'permit_empty|integer|greater_than_equal_to[0]',

            'people_name.*' => 'permit_empty|max_length[255]',
            'people_role.*' => 'permit_empty|max_length[255]',
        ];
    }


    public function getServiceBusinessRuleErrors(): array
    {
        $errors = [];

        $format = service('request')->getPost('format_of_service');
        $liveViews = service('request')->getPost('online_live_views');
        if (in_array($format, ['Hybrid', 'Online'], true) && ($liveViews === null || $liveViews === '')) {
            $errors['online_live_views'] = lang('Services.validationLiveViewsRequired');
        }

        $present = service('request')->getPost('reported_present_count');
        $communion = service('request')->getPost('reported_communion_count');
        if ($present !== null && $present !== '' && $communion !== null && $communion !== '' && (int) $communion > (int) $present) {
            $errors['reported_communion_count'] = lang('Services.validationCommunionExceedsPresent');
        }

        $serviceFamily = service('request')->getPost('service_family');
        $serviceSchedule = service('request')->getPost('service_schedule');
        if ($serviceSchedule === 'private' && $serviceFamily !== 'holy_communion') {
            $errors['service_schedule'] = lang('Services.validationPrivateOnlyHolyCommunion');
        }

        $peak = service('request')->getPost('online_peak_viewers');
        if ($peak !== null && $peak !== '' && $liveViews !== null && $liveViews !== '' && (int) $peak > (int) $liveViews) {
            $errors['online_peak_viewers'] = lang('Services.validationPeakExceedsLive');
        }

        return $errors;
    }


    public function getCoreServiceData(bool $includeParishId, ?int $operationalParishId = null): array
    {
        $serviceArray = service('request')->getPost('service');
        $serviceJson = null;
        if (!empty($serviceArray) && is_array($serviceArray)) {
            $serviceJson = json_encode(array_values($serviceArray));
        }

        $data = [
            'service_date' => service('request')->getPost('date'),
            'service' => $serviceJson,
            'liturgical_day' => service('request')->getPost('liturgical_day') ?: null,
            'place' => service('request')->getPost('place') ?: null,
            'service_time' => trim(service('request')->getPost('time') ?? '') ?: null,
            'preacher' => service('request')->getPost('preacher') ?: null,
            'celebrant' => service('request')->getPost('celebrant') ?: null,
            'notes' => service('request')->getPost('notes') ?: null,
        ];

        if ($includeParishId) {
            $data['parish_id'] = $operationalParishId;
        }

        return $data;
    }


    public function upsertRelatedServiceData(int $serviceId): void
    {
        $db = db_connect();

        if ($db->tableExists('service_metric')) {
            $metricsData = [
                'service_id' => $serviceId,
                'format_of_service' => service('request')->getPost('format_of_service') ?: null,
                'attendance_classification' => service('request')->getPost('attendance_classification') ?: null,
                'service_family' => service('request')->getPost('service_family') ?: null,
                'service_schedule' => service('request')->getPost('service_schedule') ?: null,
                'reported_present_count' => $this->toNullableInt('reported_present_count'),
                'reported_communion_count' => $this->toNullableInt('reported_communion_count'),
                'online_live_views' => $this->toNullableInt('online_live_views'),
                'online_recorded_views_7d' => $this->toNullableInt('online_recorded_views_7d'),
                'online_peak_viewers' => $this->toNullableInt('online_peak_viewers'),
                'languages_json' => $this->csvToJson(service('request')->getPost('languages_csv')),
                // Sacramental counts (merged from service_sacramental_counts)
                'baptism_adult' => $this->toNullableInt('baptism_adult'),
                'baptism_child' => $this->toNullableInt('baptism_child'),
                'confirmation_adult' => $this->toNullableInt('confirmation_adult'),
                'confirmation_teen' => $this->toNullableInt('confirmation_teen'),
                'reception_count' => $this->toNullableInt('reception_count'),
                'marriage_count' => $this->toNullableInt('marriage_count'),
                'burial_count' => $this->toNullableInt('burial_count'),
                'eucharist_count' => $this->toNullableInt('eucharist_count'),
                'daily_office_count' => $this->toNullableInt('daily_office_count'),
                // Parochial Report online viewer counts
                'online_live_viewers' => $this->toNullableInt('online_live_viewers'),
                'online_recorded_views' => $this->toNullableInt('online_recorded_views'),
            ];

            $existing = $this->serviceMetricsModel->find($serviceId);
            if ($existing) {
                // Always update — never delete an existing metrics row on edit,
                // because previously-saved parochial report fields (eucharist_count,
                // online_live_viewers, etc.) must not be silently wiped if the user
                // submits the form with blank counts.
                $this->serviceMetricsModel->update($serviceId, $metricsData);
            } elseif ($this->hasNonNullPayload($metricsData, ['service_id'])) {
                // Only insert a new row when at least one field has data.
                $this->serviceMetricsModel->insert($metricsData);
            }
        }

        if ($db->tableExists('service_people')) {
            $this->servicePeopleModel->where('service_id', $serviceId)->delete();

            $peopleNames = (array) (service('request')->getPost('people_name') ?? []);
            $peopleRoles = (array) (service('request')->getPost('people_role') ?? []);
            $maxRows = max(count($peopleNames), count($peopleRoles));
            $people = [];

            for ($i = 0; $i < $maxRows; $i++) {
                $name = trim((string) ($peopleNames[$i] ?? ''));
                $role = trim((string) ($peopleRoles[$i] ?? ''));

                if ($name === '') {
                    continue;
                }

                $people[] = [
                    'service_id' => $serviceId,
                    'person_name' => $name,
                    'role_name' => $role !== '' ? $role : null,
                    'sort_order' => $i,
                ];
            }

            if ($people !== []) {
                $this->servicePeopleModel->insertBatch($people);
            }
        }
    }


    public function hasNonNullPayload(array $data, array $excludeKeys = []): bool
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }


    public function csvToJson(?string $csv): ?string
    {
        if ($csv === null) {
            return null;
        }

        $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $csv)), static fn($v) => $v !== '')));
        return empty($values) ? null : json_encode($values);
    }


    public function toNullableInt(string $field): ?int
    {
        $raw = service('request')->getPost($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }


    public function decodeMetricJsonFields(array $metrics): array
    {
        if (empty($metrics)) {
            return [];
        }

        foreach (['languages_json', 'place_labels_json'] as $field) {
            $decoded = !empty($metrics[$field]) ? json_decode((string) $metrics[$field], true) : [];
            $metrics[$field] = is_array($decoded) ? $decoded : [];
        }

        return $metrics;
    }


    public function decodeServiceField(array $service): array
    {
        if (isset($service['service']) && !empty($service['service'])) {
            $decoded = json_decode((string) $service['service'], true);
            $service['service'] = is_array($decoded) ? $decoded : [];
        } else {
            $service['service'] = [];
        }

        return $service;
    }


    public function safeFindMetrics(int $serviceId): array
    {
        return $this->serviceMetricsModel->find($serviceId) ?? [];
    }


    public function safeGetServicePeople(int $serviceId): array
    {
        return $this->servicePeopleModel
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }


    public function safeGetLiturgicalSeasons($parishId): array
    {
        return $this->liturgicalCalendarEntryModel->getSeasonsForDropdown($parishId);
    }


    public function safeGetLiturgicalDays($parishId, string $season): array
    {
        return $this->liturgicalCalendarEntryModel->getDaysBySeason($parishId, $season);
    }


    public function safeFindLiturgicalEntryByName($parishId, string $name): ?array
    {
        return $this->liturgicalCalendarEntryModel->findByName($parishId, $name);
    }
}
