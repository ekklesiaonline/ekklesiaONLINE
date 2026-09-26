<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;

class Attendance extends BaseController
{
    protected $attendanceModel;
    protected $serviceModel;
    protected $profileModel;
    protected $memberParishAffiliationModel;
    protected $serviceMetricsModel;

    public function __construct()
    {
        $this->attendanceModel = model('AttendanceModel');
        $this->serviceModel = model('ServiceModel');
        $this->serviceMetricsModel = model('ServiceMetricsModel');
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    protected function getMemberParishAffiliationModel()
    {
        if ($this->memberParishAffiliationModel === null) {
            $this->memberParishAffiliationModel = model('MemberParishAffiliationModel');
        }

        return $this->memberParishAffiliationModel;
    }

    /**
     * Display attendance for a specific service
     */
    public function service($serviceId)
    {
        if ($redirect = $this->requirePermission('attendance.mark')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->serviceModel->find($serviceId);

        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/services')->with('error', lang('Services.serviceNotFound'));
        }

        // Full-roster marking was replaced by the search-first manual flow to
        // keep the page payload bounded on large parishes.
        return redirect()->to('/attendance/manual?service_id=' . (int) $serviceId);
    }

    /**
     * Mark attendance (AJAX)
     */
    public function mark()
    {
        if ($redirect = $this->requirePermission('attendance.mark')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.noParishScopeAssigned')], 403);
        }

        $serviceId = $this->request->getPost('service_id');
        $userId = $this->request->getPost('user_id');
        $attended = (bool)$this->request->getPost('attended');
        $communicant = (bool)$this->request->getPost('communicant');

        // Communicant cannot be true without attendance
        if (!$attended) {
            $communicant = false;
        }

        // Verify service belongs to parish
        $service = $this->serviceModel->find($serviceId);
        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.invalidService')], 404);
        }

        $isAffiliated = $this->getMemberParishAffiliationModel()
            ->isRecordableMember((int) $userId, (int) ($this->getOperationalParishId() ?? 0));
        if (!$isAffiliated) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Services.invalidMemberSelection')], 422);
        }

        if ($this->attendanceModel->markAttendance($serviceId, $userId, $attended, $communicant)) {
            $this->attendanceModel->syncServiceMetrics($serviceId);

            $this->audit()->log('attendance.mark', [
                'parish_id' => (int) $this->getOperationalParishId(),
                'entity_type' => 'attendance',
                'entity_id' => (int) $serviceId,
                'new_values' => [
                    'service_id' => (int) $serviceId,
                    'user_id' => (int) $userId,
                    'attended' => (bool) $attended,
                    'communicant' => (bool) $communicant,
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Services.attendanceMarked')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Services.attendanceMarkFailed')], 500);
    }

    /**
     * Bulk mark attendance
     */
    public function bulkMark()
    {
        if ($redirect = $this->requirePermission('attendance.mark')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $serviceId = $this->request->getPost('service_id');
        $attendedUsers = $this->request->getPost('attended') ?? [];
        $communicants = $this->request->getPost('communicant') ?? [];

        // Verify service belongs to parish
        $service = $this->serviceModel->find($serviceId);
        if (!$service || $service['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->back()->with('error', lang('Services.invalidService'));
        }

        // Get active members for this parish via affiliation records.
        $members = $this->getMemberParishAffiliationModel()->getParishMembers((int) $this->getOperationalParishId(), 'active');

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Optimized: Build batch data instead of individual inserts/updates
            $dataToUpsert = [];

            foreach ($members as $member) {
                $attended = in_array($member['user_id'], $attendedUsers);
                // Communicant is only valid when the member also attended
                $communicant = $attended && in_array($member['user_id'], $communicants);

                $dataToUpsert[] = [
                    'service_id' => $serviceId,
                    'user_id' => $member['user_id'],
                    'attended' => $attended,
                    'communicant' => $communicant,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            // Use batch upsert for better performance
            if (!empty($dataToUpsert)) {
                // Delete existing records for this service first
                $this->attendanceModel->where('service_id', $serviceId)->delete();
                // Insert all new records in one batch
                $this->attendanceModel->insertBatch($dataToUpsert);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->back()->with('error', lang('Services.attendanceSaveFailed'));
            }

            $this->audit()->log('attendance.bulk_mark', [
                'parish_id' => (int) $this->getOperationalParishId(),
                'entity_type' => 'attendance',
                'entity_id' => (int) $serviceId,
                'new_values' => [
                    'service_id' => (int) $serviceId,
                    'member_count' => count($members),
                    'attendance_payload_count' => count($attendedUsers),
                    'communicant_payload_count' => count($communicants),
                ],
            ]);

            return redirect()->to('/services/view/' . $serviceId)->with('success', lang('Services.attendanceSaved'));
        } catch (\Exception $e) {
            return $this->safeRedirectError(lang('Services.attendanceSaveException'), $e);
        }
    }

    /**
     * Attendance reports
     */
    public function reports()
    {
        if ($redirect = $this->requirePermission('attendance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();

        // Get date range from query params (default: current year-to-date).
        $startDateInput = $this->request->getGet('start_date');
        $endDateInput = $this->request->getGet('end_date');
        $startDate = $startDateInput ?: date('Y-01-01');
        $endDate = $endDateInput ?: date('Y-m-d');
        $hasCustomFilter = ($startDateInput !== null && $startDateInput !== '')
            || ($endDateInput !== null && $endDateInput !== '');

        // Use the same service feed as /services so ordering and displayed values match.
        $detailedStats = $this->serviceModel->getParishServices($parishId, null, $startDate, $endDate);

        // Load service metrics when available so report tiles reflect form-submitted values.
        $metricsByServiceId = [];
        $db = db_connect();
        if ($db->tableExists('service_metric') && !empty($detailedStats)) {
            $serviceIds = array_map(static fn($row) => (int) ($row['id'] ?? 0), $detailedStats);
            $serviceIds = array_values(array_filter($serviceIds, static fn($id) => $id > 0));

            if (!empty($serviceIds)) {
                $metricsRows = $this->serviceMetricsModel
                    ->whereIn('service_id', $serviceIds)
                    ->findAll();

                foreach ($metricsRows as $metricsRow) {
                    $metricsByServiceId[(int) $metricsRow['service_id']] = $metricsRow;
                }
            }
        }

        $regularInPerson = 0;
        $specialInPerson = 0;
        $weekdayAttendanceTotal = 0;
        $weekdayServiceCount = 0;
        $sundayAttendanceTotal = 0;
        $sundayServiceCount = 0;
        $totalCommunicants = 0;
        $totalAttendance = 0;
        $regularServiceCount = 0;
        $specialServiceCount = 0;
        $inPersonServiceCount = 0;

        foreach ($detailedStats as $row) {
            $serviceId = (int) ($row['id'] ?? 0);
            $metrics = $metricsByServiceId[$serviceId] ?? [];
            $attendanceFallback = (int) ($row['total_attended'] ?? 0);
            $communicantsFallback = (int) ($row['total_communicants'] ?? 0);

            $reportedPresent = $metrics['reported_present_count'] ?? null;
            $reportedCommunion = $metrics['reported_communion_count'] ?? null;

            $attendance = $reportedPresent !== null ? (int) $reportedPresent : $attendanceFallback;
            $communicants = $reportedCommunion !== null ? (int) $reportedCommunion : $communicantsFallback;

            $totalAttendance += $attendance;

            // Accumulate total communicants
            $totalCommunicants += $communicants;

            // Use attendance_classification from metrics, default to 'Regular'
            $classification = $metrics['attendance_classification'] ?? 'Regular';

            $formatOfService = $metrics['format_of_service'] ?? null;
            $isInPerson = $formatOfService === null || $formatOfService === ''
                ? true
                : in_array($formatOfService, ['In-Person', 'Hybrid'], true);

            // Infer schedule from service date when metrics are missing.
            $schedule = $metrics['service_schedule'] ?? null;
            if ($schedule === null || $schedule === '') {
                $serviceDate = (string) ($row['service_date'] ?? '');
                $schedule = ($serviceDate !== '' && strtotime($serviceDate) !== false && date('w', strtotime($serviceDate)) === '0')
                    ? 'sunday'
                    : 'weekday';
            }

            if ($isInPerson && $classification === 'Regular') {
                $regularInPerson += $attendance;
            }

            if ($isInPerson && $classification === 'Special') {
                $specialInPerson += $attendance;
            }

            if ($classification === 'Regular') {
                $regularServiceCount++;
            }

            if ($classification === 'Special') {
                $specialServiceCount++;
            }

            if ($isInPerson) {
                $inPersonServiceCount++;
            }

            // AWA counts all weekday services (both Regular and Special)
            if ($schedule === 'weekday') {
                $weekdayAttendanceTotal += $attendance;
                $weekdayServiceCount++;
            }

            if ($schedule === 'sunday' && $classification === 'Regular') {
                $sundayAttendanceTotal += $attendance;
                $sundayServiceCount++;
            }
        }

        $attendanceKpis = [
            'total_in_person_regular' => $regularInPerson,
            'total_in_person_special' => $specialInPerson,
            'average_weekday_attendance' => $weekdayServiceCount > 0 ? round($weekdayAttendanceTotal / $weekdayServiceCount, 1) : 0,
            'average_sunday_attendance' => $sundayServiceCount > 0 ? round($sundayAttendanceTotal / $sundayServiceCount, 1) : 0,
            'total_communicants' => (int) $totalCommunicants,
            'snapshot_services_logged' => count($detailedStats),
            'snapshot_in_person_services' => $inPersonServiceCount,
            'snapshot_regular_services' => $regularServiceCount,
            'snapshot_special_services' => $specialServiceCount,
            'snapshot_sunday_services' => $sundayServiceCount,
            'snapshot_weekday_services' => $weekdayServiceCount,
            'snapshot_avg_per_service' => count($detailedStats) > 0 ? round($totalAttendance / count($detailedStats), 1) : 0,
        ];

        $data = array_merge($this->getCommonViewData(lang('Attendance.pageTitleReports')), [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'attendance_kpis' => $attendanceKpis,
            'has_custom_filter' => $hasCustomFilter,
        ]);

        return view('Worship\Views\Attendance/reports', $data);
    }
}
