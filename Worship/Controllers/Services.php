<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;
use App\Services\WorshipServiceDataService;

class Services extends BaseController
{
    protected $serviceModel;
    protected $attendanceModel;
    protected $serviceMetricsModel;
    protected $servicePeopleModel;
    protected $liturgicalCalendarEntryModel;
    private WorshipServiceDataService $serviceData;

    public function __construct()
    {
        $this->serviceModel = model('ServiceModel');
        $this->attendanceModel = model('AttendanceModel');
        $this->serviceMetricsModel = model('ServiceMetricsModel');
        $this->servicePeopleModel = model('ServicePeopleModel');
        $this->liturgicalCalendarEntryModel = model('LiturgicalCalendarEntryModel');
        $this->serviceData = new WorshipServiceDataService();
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

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $servicePage = $this->serviceModel->getParishServicesPaginated($parishId, $perPage, $page);
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
            'service' => $this->serviceData->decodeServiceField($service),
            'attendance' => $attendance,
            'attendance_count' => count($attendance),
            'metrics' => $this->serviceData->decodeMetricJsonFields($this->serviceData->safeFindMetrics((int) $id)),
            'service_people' => $this->serviceData->safeGetServicePeople((int) $id),
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

        $parishId = $this->getOperationalParishId();
        $liturgicalSeasons = $this->serviceData->safeGetLiturgicalSeasons($parishId);

        $selectedLiturgicalSeason = (string) old('liturgical_season', '');
        if ($selectedLiturgicalSeason === '' && !empty($liturgicalSeasons)) {
            $selectedLiturgicalSeason = $liturgicalSeasons[0];
        }

        $liturgicalDays = $selectedLiturgicalSeason !== ''
            ? $this->serviceData->safeGetLiturgicalDays($parishId, $selectedLiturgicalSeason)
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

        $rules = $this->serviceData->getServiceValidationRules();
        $isBaseValidationValid = $this->validate($rules);
        $errors = $isBaseValidationValid ? [] : $this->validator->getErrors();
        $errors = array_merge($errors, $this->serviceData->getServiceBusinessRuleErrors());

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $data = $this->serviceData->getCoreServiceData(true, $this->getOperationalParishId());

        $db = db_connect();
        $db->transStart();

        $inserted = $this->serviceModel->insert($data);
        $serviceId = $inserted ? (int) $this->serviceModel->getInsertID() : 0;

        if ($serviceId > 0) {
            $this->serviceData->upsertRelatedServiceData($serviceId);
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

        $parishId = $this->getOperationalParishId();
        $liturgicalSeasons = $this->serviceData->safeGetLiturgicalSeasons($parishId);
        $selectedLiturgicalSeason = (string) old('liturgical_season', '');

        if ($selectedLiturgicalSeason === '' && !empty($service['liturgical_day'])) {
            $entry = $this->serviceData->safeFindLiturgicalEntryByName($parishId, (string) $service['liturgical_day']);
            $selectedLiturgicalSeason = (string) ($entry['season'] ?? '');
        }

        if ($selectedLiturgicalSeason === '' && !empty($liturgicalSeasons)) {
            $selectedLiturgicalSeason = $liturgicalSeasons[0];
        }

        $liturgicalDays = $selectedLiturgicalSeason !== ''
            ? $this->serviceData->safeGetLiturgicalDays($parishId, $selectedLiturgicalSeason)
            : [];

        $data = array_merge($this->getCommonViewData(lang('Services.pageTitleEdit')), [
            'service' => $this->serviceData->decodeServiceField($service),
            'metrics' => $this->serviceData->decodeMetricJsonFields($this->serviceData->safeFindMetrics((int) $id)),
            'service_people' => $this->serviceData->safeGetServicePeople((int) $id),
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

        $rules = $this->serviceData->getServiceValidationRules();
        $isBaseValidationValid = $this->validate($rules);
        $errors = $isBaseValidationValid ? [] : $this->validator->getErrors();
        $errors = array_merge($errors, $this->serviceData->getServiceBusinessRuleErrors());

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $data = $this->serviceData->getCoreServiceData(false, $this->getOperationalParishId());

        $db = db_connect();
        $db->transStart();

        $updated = $this->serviceModel->update($id, $data);
        if ($updated) {
            $this->serviceData->upsertRelatedServiceData((int) $id);
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
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.noParishScopeAssigned')], 403);
        }

        $season = trim((string) $this->request->getGet('season'));
        if ($season === '') {
            return $this->jsonResponse(['success' => true, 'items' => []]);
        }

        $items = $this->serviceData->safeGetLiturgicalDays($this->getOperationalParishId(), $season);

        foreach ($items as &$item) {
            $dayName = (string) ($item['name'] ?? '');
            $item['label'] = $dayName !== '' ? liturgical_day_label($dayName) : '';
        }
        unset($item);

        return $this->jsonResponse(['success' => true, 'items' => $items]);
    }
}
