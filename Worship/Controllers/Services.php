<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;

class Services extends BaseController
{
    protected $serviceModel;
    protected $attendanceModel;
    protected $serviceMetricsModel;
    protected $servicePeopleModel;
    protected $liturgicalCalendarEntryModel;

    public function __construct()
    {
        $this->serviceModel = model('ServiceModel');
        $this->attendanceModel = model('AttendanceModel');
        $this->serviceMetricsModel = model('ServiceMetricsModel');
        $this->servicePeopleModel = model('ServicePeopleModel');
        $this->liturgicalCalendarEntryModel = model('LiturgicalCalendarEntryModel');
    }

    /**
     * Display list of services
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('service.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $servicePage = $this->serviceModel->getParishServicesPaginated($churchId, $perPage, $page);
        $pagerLinks = service('pager')->makeLinks($page, $perPage, (int) ($servicePage['total'] ?? 0));
        $totalItems = (int) ($servicePage['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        $data = array_merge($this->getCommonViewData(lang('Services.pageTitle')), [
            'services' => $servicePage['rows'] ?? [],
            'pager_links' => $pagerLinks,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
        ]);

        return view('Worship\Views\Services/index', $data);
    }

    /**
     * Display single service
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('service.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->serviceModel->find($id);

        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/services')->with('error', lang('Services.serviceNotFound'));
        }

        // Get attendance for this service
        $attendance = $this->attendanceModel->getServiceAttendance($id);

        $data = array_merge($this->getCommonViewData(lang('Services.serviceDetails')), [
            'service' => $this->decodeServiceField($service),
            'attendance' => $attendance,
            'attendance_count' => count($attendance),
            'metrics' => $this->decodeMetricJsonFields($this->safeFindMetrics((int) $id)),
            'service_people' => $this->safeGetServicePeople((int) $id),
        ]);

        return view('Worship\Views\Services/view', $data);
    }

    /**
     * Show create service form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('service.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $churchId = $this->getOperationalParishId();
        $liturgicalSeasons = $this->safeGetLiturgicalSeasons($churchId);

        $selectedLiturgicalSeason = (string) old('liturgical_season', '');
        if ($selectedLiturgicalSeason === '' && !empty($liturgicalSeasons)) {
            $selectedLiturgicalSeason = $liturgicalSeasons[0];
        }

        $liturgicalDays = $selectedLiturgicalSeason !== ''
            ? $this->safeGetLiturgicalDays($churchId, $selectedLiturgicalSeason)
            : [];

        return view('Worship\Views\Services/create', array_merge($this->getCommonViewData(lang('Services.pageTitleAdd')), [
            'liturgical_seasons' => $liturgicalSeasons,
            'liturgical_days' => $liturgicalDays,
            'selected_liturgical_season' => $selectedLiturgicalSeason,
        ]));
    }

    /**
     * Store new service
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('service.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = $this->getServiceValidationRules();
        $isBaseValidationValid = $this->validate($rules);
        $errors = $isBaseValidationValid ? [] : $this->validator->getErrors();
        $errors = array_merge($errors, $this->getServiceBusinessRuleErrors());

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $data = $this->getCoreServiceData(true);

        $db = db_connect();
        $db->transStart();

        $inserted = $this->serviceModel->insert($data);
        $serviceId = $inserted ? (int) $this->serviceModel->getInsertID() : 0;

        if ($serviceId > 0) {
            $this->upsertRelatedServiceData($serviceId);
        }

        $db->transComplete();

        if ($inserted && $db->transStatus() !== false) {
            $this->audit()->log('service.create', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'service',
                'entity_id' => (int) $serviceId,
                'new_values' => $data,
            ]);

            return redirect()->to('/services')->with('success', lang('Services.createSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Services.createFailed'));
    }

    /**
     * Show edit service form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('service.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->serviceModel->find($id);

        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/services')->with('error', lang('Services.serviceNotFound'));
        }

        $churchId = $this->getOperationalParishId();
        $liturgicalSeasons = $this->safeGetLiturgicalSeasons($churchId);
        $selectedLiturgicalSeason = (string) old('liturgical_season', '');

        if ($selectedLiturgicalSeason === '' && !empty($service['liturgical_day'])) {
            $entry = $this->safeFindLiturgicalEntryByName($churchId, (string) $service['liturgical_day']);
            $selectedLiturgicalSeason = (string) ($entry['season'] ?? '');
        }

        if ($selectedLiturgicalSeason === '' && !empty($liturgicalSeasons)) {
            $selectedLiturgicalSeason = $liturgicalSeasons[0];
        }

        $liturgicalDays = $selectedLiturgicalSeason !== ''
            ? $this->safeGetLiturgicalDays($churchId, $selectedLiturgicalSeason)
            : [];

        $data = array_merge($this->getCommonViewData(lang('Services.pageTitleEdit')), [
            'service' => $this->decodeServiceField($service),
            'metrics' => $this->decodeMetricJsonFields($this->safeFindMetrics((int) $id)),
            'service_people' => $this->safeGetServicePeople((int) $id),
            'liturgical_seasons' => $liturgicalSeasons,
            'liturgical_days' => $liturgicalDays,
            'selected_liturgical_season' => $selectedLiturgicalSeason,
        ]);

        return view('Worship\Views\Services/edit', $data);
    }

    /**
     * Update service
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('service.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->serviceModel->find($id);

        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/services')->with('error', lang('Services.serviceNotFound'));
        }

        $rules = $this->getServiceValidationRules();
        $isBaseValidationValid = $this->validate($rules);
        $errors = $isBaseValidationValid ? [] : $this->validator->getErrors();
        $errors = array_merge($errors, $this->getServiceBusinessRuleErrors());

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $data = $this->getCoreServiceData(false);

        $db = db_connect();
        $db->transStart();

        $updated = $this->serviceModel->update($id, $data);
        if ($updated) {
            $this->upsertRelatedServiceData((int) $id);
        }

        $db->transComplete();

        if ($updated && $db->transStatus() !== false) {
            $this->audit()->log('service.update', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'service',
                'entity_id' => (int) $id,
                'old_values' => [
                    'service_date' => (string) ($service['service_date'] ?? ''),
                    'service' => $service['service'] ?? null,
                    'liturgical_day' => (string) ($service['liturgical_day'] ?? ''),
                    'place' => (string) ($service['place'] ?? ''),
                    'service_time' => $service['service_time'] ?? null,
                    'preacher' => (string) ($service['preacher'] ?? ''),
                    'celebrant' => (string) ($service['celebrant'] ?? ''),
                    'notes' => $service['notes'] ?? null,
                ],
                'new_values' => $data,
            ]);

            return redirect()->to('/services/view/' . $id)->with('success', lang('Services.updateSuccess'));
        }

        return redirect()->back()->withInput()->with('error', lang('Services.updateFailed'));
    }

    /**
     * Delete service
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('service.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->serviceModel->find($id);

        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.serviceNotFound')], 404);
        }

        if ($this->serviceModel->delete($id)) {
            $this->audit()->log('service.delete', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'service',
                'entity_id' => (int) $id,
                'old_values' => [
                    'service_date' => (string) ($service['service_date'] ?? ''),
                    'service' => $service['service'] ?? null,
                    'liturgical_day' => (string) ($service['liturgical_day'] ?? ''),
                    'place' => (string) ($service['place'] ?? ''),
                    'service_time' => $service['service_time'] ?? null,
                    'preacher' => (string) ($service['preacher'] ?? ''),
                    'celebrant' => (string) ($service['celebrant'] ?? ''),
                    'notes' => $service['notes'] ?? null,
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Services.deleteSuccess')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Services.deleteFailed')], 500);
    }

    /**
     * AJAX source for liturgical day dropdown by season.
     */
    public function liturgicalDays()
    {
        if (!$this->checkPermission('service.view')) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.forbidden')], 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.noChurchScopeAssigned')], 403);
        }

        $season = trim((string) $this->request->getGet('season'));
        if ($season === '') {
            return $this->jsonResponse(['success' => true, 'items' => []]);
        }

        $items = $this->safeGetLiturgicalDays($this->getOperationalParishId(), $season);

        foreach ($items as &$item) {
            $dayName = (string) ($item['name'] ?? '');
            $item['label'] = $dayName !== '' ? liturgical_day_label($dayName) : '';
        }
        unset($item);

        return $this->jsonResponse(['success' => true, 'items' => $items]);
    }

    private function getServiceValidationRules(): array
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

    private function getServiceBusinessRuleErrors(): array
    {
        $errors = [];

        $format = $this->request->getPost('format_of_service');
        $liveViews = $this->request->getPost('online_live_views');
        if (in_array($format, ['Hybrid', 'Online'], true) && ($liveViews === null || $liveViews === '')) {
            $errors['online_live_views'] = lang('Services.validationLiveViewsRequired');
        }

        $present = $this->request->getPost('reported_present_count');
        $communion = $this->request->getPost('reported_communion_count');
        if ($present !== null && $present !== '' && $communion !== null && $communion !== '' && (int) $communion > (int) $present) {
            $errors['reported_communion_count'] = lang('Services.validationCommunionExceedsPresent');
        }

        $serviceFamily = $this->request->getPost('service_family');
        $serviceSchedule = $this->request->getPost('service_schedule');
        if ($serviceSchedule === 'private' && $serviceFamily !== 'holy_communion') {
            $errors['service_schedule'] = lang('Services.validationPrivateOnlyHolyCommunion');
        }

        $peak = $this->request->getPost('online_peak_viewers');
        if ($peak !== null && $peak !== '' && $liveViews !== null && $liveViews !== '' && (int) $peak > (int) $liveViews) {
            $errors['online_peak_viewers'] = lang('Services.validationPeakExceedsLive');
        }

        return $errors;
    }

    private function getCoreServiceData(bool $includeChurchId): array
    {
        $serviceArray = $this->request->getPost('service');
        $serviceJson = null;
        if (!empty($serviceArray) && is_array($serviceArray)) {
            $serviceJson = json_encode(array_values($serviceArray));
        }

        $data = [
            'service_date' => $this->request->getPost('date'),
            'service' => $serviceJson,
            'liturgical_day' => $this->request->getPost('liturgical_day') ?: null,
            'place' => $this->request->getPost('place') ?: null,
            'service_time' => trim($this->request->getPost('time') ?? '') ?: null,
            'preacher' => $this->request->getPost('preacher') ?: null,
            'celebrant' => $this->request->getPost('celebrant') ?: null,
            'notes' => $this->request->getPost('notes') ?: null,
        ];

        if ($includeChurchId) {
            $data['parish_id'] = $this->getOperationalParishId();
        }

        return $data;
    }

    private function upsertRelatedServiceData(int $serviceId): void
    {
        $db = db_connect();

        if ($db->tableExists('service_metric')) {
            $metricsData = [
                'service_id' => $serviceId,
                'format_of_service' => $this->request->getPost('format_of_service') ?: null,
                'attendance_classification' => $this->request->getPost('attendance_classification') ?: null,
                'service_family' => $this->request->getPost('service_family') ?: null,
                'service_schedule' => $this->request->getPost('service_schedule') ?: null,
                'reported_present_count' => $this->toNullableInt('reported_present_count'),
                'reported_communion_count' => $this->toNullableInt('reported_communion_count'),
                'online_live_views' => $this->toNullableInt('online_live_views'),
                'online_recorded_views_7d' => $this->toNullableInt('online_recorded_views_7d'),
                'online_peak_viewers' => $this->toNullableInt('online_peak_viewers'),
                'languages_json' => $this->csvToJson($this->request->getPost('languages_csv')),
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

            $peopleNames = (array) ($this->request->getPost('people_name') ?? []);
            $peopleRoles = (array) ($this->request->getPost('people_role') ?? []);
            $maxRows = max(count($peopleNames), count($peopleRoles));

            for ($i = 0; $i < $maxRows; $i++) {
                $name = trim((string) ($peopleNames[$i] ?? ''));
                $role = trim((string) ($peopleRoles[$i] ?? ''));

                if ($name === '') {
                    continue;
                }

                $this->servicePeopleModel->insert([
                    'service_id' => $serviceId,
                    'person_name' => $name,
                    'role_name' => $role !== '' ? $role : null,
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function hasNonNullPayload(array $data, array $excludeKeys = []): bool
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

    private function csvToJson(?string $csv): ?string
    {
        if ($csv === null) {
            return null;
        }

        $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $csv)), static fn($v) => $v !== '')));
        return empty($values) ? null : json_encode($values);
    }

    private function toNullableInt(string $field): ?int
    {
        $raw = $this->request->getPost($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    private function decodeMetricJsonFields(array $metrics): array
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

    private function decodeServiceField(array $service): array
    {
        if (isset($service['service']) && !empty($service['service'])) {
            $decoded = json_decode((string) $service['service'], true);
            $service['service'] = is_array($decoded) ? $decoded : [];
        } else {
            $service['service'] = [];
        }

        return $service;
    }

    private function safeFindMetrics(int $serviceId): array
    {
        $db = db_connect();
        if (!$db->tableExists('service_metric')) {
            return [];
        }

        return $this->serviceMetricsModel->find($serviceId) ?? [];
    }

    private function safeGetServicePeople(int $serviceId): array
    {
        $db = db_connect();
        if (!$db->tableExists('service_people')) {
            return [];
        }

        return $this->servicePeopleModel
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    private function safeGetLiturgicalSeasons($churchId): array
    {
        $db = db_connect();
        if (!$db->tableExists('liturgical_calendar_entry')) {
            return [];
        }

        return $this->liturgicalCalendarEntryModel->getSeasonsForDropdown($churchId);
    }

    private function safeGetLiturgicalDays($churchId, string $season): array
    {
        $db = db_connect();
        if (!$db->tableExists('liturgical_calendar_entry')) {
            return [];
        }

        return $this->liturgicalCalendarEntryModel->getDaysBySeason($churchId, $season);
    }

    private function safeFindLiturgicalEntryByName($churchId, string $name): ?array
    {
        $db = db_connect();
        if (!$db->tableExists('liturgical_calendar_entry')) {
            return null;
        }

        return $this->liturgicalCalendarEntryModel->findByName($churchId, $name);
    }
}
