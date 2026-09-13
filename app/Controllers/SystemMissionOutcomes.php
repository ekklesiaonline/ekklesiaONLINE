<?php

namespace App\Controllers;

class SystemMissionOutcomes extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $years = $this->getAvailableYears();
        $currentYear = (int) date('Y');
        $defaultYear = $years[0] ?? $currentYear;

        $selectedYear = (int) ($this->request->getGet('year') ?? $defaultYear);
        if (!in_array($selectedYear, $years, true)) {
            $selectedYear = $defaultYear;
        }

        $compareTo = (int) ($this->request->getGet('compare_to') ?? 0);
        if ($compareTo <= 0 || $compareTo === $selectedYear || !in_array($compareTo, $years, true)) {
            $compareTo = 0;
        }

        $attendanceOverview = $this->getAttendanceOverview($selectedYear);
        $attendanceCompareOverview = $compareTo > 0 ? $this->getAttendanceOverview($compareTo) : null;
        $affiliationOverview = $this->getAffiliationOverview($selectedYear);
        $affiliationCompareOverview = $compareTo > 0 ? $this->getAffiliationOverview($compareTo) : null;
        $activeMembersOverview = $this->getActiveMembersOverview($selectedYear);
        $activeMembersCompareOverview = $compareTo > 0 ? $this->getActiveMembersOverview($compareTo) : null;

        $attendanceMonthly = $this->getAttendanceByMonth($selectedYear);
        $attendanceMonthlyCompare = $compareTo > 0 ? $this->getAttendanceByMonth($compareTo) : $this->emptyMonthSeries();
        $affiliationMonthly = $this->getAffiliationsByMonth($selectedYear);
        $affiliationMonthlyCompare = $compareTo > 0 ? $this->getAffiliationsByMonth($compareTo) : $this->emptyMonthSeries();

        $provinceAttendanceCurrent = $this->getProvinceAttendanceSummary($selectedYear);
        $provinceAttendanceCompare = $compareTo > 0 ? $this->getProvinceAttendanceSummary($compareTo) : [];
        $provinceActiveMembersCurrent = $this->getProvinceActiveMembersSummary($selectedYear);
        $provinceActiveMembersCompare = $compareTo > 0 ? $this->getProvinceActiveMembersSummary($compareTo) : [];

        $provinceAttendanceComparison = $this->mergeProvinceComparisonRows(
            $provinceAttendanceCurrent,
            $provinceAttendanceCompare,
            'total_attendance'
        );
        $provinceActiveMembersComparison = $this->mergeProvinceComparisonRows(
            $provinceActiveMembersCurrent,
            $provinceActiveMembersCompare,
            'active_members'
        );

        return view('System/mission_outcomes', array_merge(
            $this->getCommonViewData(lang('App.navMissionOutcomes')),
            [
                'default_year' => $defaultYear,
                'year' => $selectedYear,
                'compare_to' => $compareTo > 0 ? $compareTo : null,
                'available_years' => $years,
                'attendance_overview' => $attendanceOverview,
                'attendance_compare_overview' => $attendanceCompareOverview,
                'affiliation_overview' => $affiliationOverview,
                'affiliation_compare_overview' => $affiliationCompareOverview,
                'active_members_overview' => $activeMembersOverview,
                'active_members_compare_overview' => $activeMembersCompareOverview,
                'attendance_monthly' => $attendanceMonthly,
                'attendance_monthly_compare' => $attendanceMonthlyCompare,
                'affiliation_monthly' => $affiliationMonthly,
                'affiliation_monthly_compare' => $affiliationMonthlyCompare,
                'province_attendance_comparison' => $provinceAttendanceComparison,
                'province_active_members_comparison' => $provinceActiveMembersComparison,
            ]
        ));
    }

    private function getAvailableYears(): array
    {
        $db = db_connect();
        $years = [];

        if ($this->tableExistsSafe($db, 'service')) {
            $serviceYears = $db->table('service')
                ->select('YEAR(service_date) AS report_year', false)
                ->where('service_date IS NOT NULL', null, false)
                ->groupBy('YEAR(service_date)', false)
                ->orderBy('report_year', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($serviceYears as $row) {
                $year = (int) ($row['report_year'] ?? 0);
                if ($year > 0) {
                    $years[] = $year;
                }
            }
        }

        if ($this->tableExistsSafe($db, 'parish_affiliation')) {
            $affiliationYears = $db->table('parish_affiliation')
                ->select('YEAR(created_at) AS report_year', false)
                ->where('created_at IS NOT NULL', null, false)
                ->groupBy('YEAR(created_at)', false)
                ->orderBy('report_year', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($affiliationYears as $row) {
                $year = (int) ($row['report_year'] ?? 0);
                if ($year > 0) {
                    $years[] = $year;
                }
            }
        }

        $years = array_values(array_unique($years));
        rsort($years);

        if ($years === []) {
            $currentYear = (int) date('Y');
            return [$currentYear, $currentYear - 1];
        }

        return $years;
    }

    private function getAttendanceOverview(int $year): array
    {
        $db = db_connect();
        $row = $db->table('attendance a')
            ->select('COUNT(DISTINCT s.id) AS total_services, COUNT(CASE WHEN a.attended = 1 THEN 1 END) AS total_attendance', false)
            ->join('service s', 's.id = a.service_id', 'inner')
            ->where('s.service_date >=', sprintf('%d-01-01', $year))
            ->where('s.service_date <=', sprintf('%d-12-31', $year))
            ->get()
            ->getRowArray();

        $totalServices = (int) ($row['total_services'] ?? 0);
        $totalAttendance = (int) ($row['total_attendance'] ?? 0);

        return [
            'total_services' => $totalServices,
            'total_attendance' => $totalAttendance,
            'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0.0,
        ];
    }

    private function getAffiliationOverview(int $year): array
    {
        $db = db_connect();
        $row = $db->table('parish_affiliation pa')
            ->select('COUNT(DISTINCT pa.linked_user_id) AS total_affiliations', false)
            ->where('pa.linked_user_id IS NOT NULL', null, false)
            ->where('pa.created_at >=', sprintf('%d-01-01 00:00:00', $year))
            ->where('pa.created_at <=', sprintf('%d-12-31 23:59:59', $year))
            ->get()
            ->getRowArray();

        return [
            'total_affiliations' => (int) ($row['total_affiliations'] ?? 0),
        ];
    }

    private function getActiveMembersOverview(int $year): array
    {
        $db = db_connect();
        $yearEnd = sprintf('%d-12-31', $year);

        $row = $db->table('parish_affiliation pa')
            ->select('COUNT(DISTINCT pa.linked_user_id) AS active_members', false)
            ->where('pa.linked_user_id IS NOT NULL', null, false)
            ->where('pa.is_member', 1)
            ->where('COALESCE(pa.start_date, DATE(pa.created_at)) <=', $yearEnd)
            ->groupStart()
            ->where('pa.end_date', null)
            ->orWhere('pa.end_date >=', $yearEnd)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return [
            'active_members' => (int) ($row['active_members'] ?? 0),
        ];
    }

    private function getAttendanceByMonth(int $year): array
    {
        $db = db_connect();
        $rows = $db->table('attendance a')
            ->select('MONTH(s.service_date) AS month_number, COUNT(CASE WHEN a.attended = 1 THEN 1 END) AS total_attendance', false)
            ->join('service s', 's.id = a.service_id', 'inner')
            ->where('s.service_date >=', sprintf('%d-01-01', $year))
            ->where('s.service_date <=', sprintf('%d-12-31', $year))
            ->groupBy('MONTH(s.service_date)', false)
            ->orderBy('MONTH(s.service_date)', 'ASC', false)
            ->get()
            ->getResultArray();

        return $this->normalizeMonthSeries($rows, 'total_attendance');
    }

    private function getAffiliationsByMonth(int $year): array
    {
        $db = db_connect();
        $rows = $db->table('parish_affiliation pa')
            ->select('MONTH(pa.created_at) AS month_number, COUNT(DISTINCT pa.linked_user_id) AS total_affiliations', false)
            ->where('pa.linked_user_id IS NOT NULL', null, false)
            ->where('pa.created_at >=', sprintf('%d-01-01 00:00:00', $year))
            ->where('pa.created_at <=', sprintf('%d-12-31 23:59:59', $year))
            ->groupBy('MONTH(pa.created_at)', false)
            ->orderBy('MONTH(pa.created_at)', 'ASC', false)
            ->get()
            ->getResultArray();

        return $this->normalizeMonthSeries($rows, 'total_affiliations');
    }

    private function getProvinceAttendanceSummary(int $year): array
    {
        $db = db_connect();

        return $db->table('attendance a')
            ->select('p.id AS province_id, p.province_number, p.name AS province_name, COUNT(CASE WHEN a.attended = 1 THEN 1 END) AS total_attendance', false)
            ->join('service s', 's.id = a.service_id', 'inner')
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->where('s.service_date >=', sprintf('%d-01-01', $year))
            ->where('s.service_date <=', sprintf('%d-12-31', $year))
            ->groupBy('p.id, p.province_number, p.name')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getProvinceActiveMembersSummary(int $year): array
    {
        $db = db_connect();
        $yearEnd = sprintf('%d-12-31', $year);

        return $db->table('parish_affiliation pa')
            ->select('p.id AS province_id, p.province_number, p.name AS province_name, COUNT(DISTINCT pa.linked_user_id) AS active_members', false)
            ->join('parish c', 'c.id = pa.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->where('pa.linked_user_id IS NOT NULL', null, false)
            ->where('pa.is_member', 1)
            ->where('COALESCE(pa.start_date, DATE(pa.created_at)) <=', $yearEnd)
            ->groupStart()
            ->where('pa.end_date', null)
            ->orWhere('pa.end_date >=', $yearEnd)
            ->groupEnd()
            ->groupBy('p.id, p.province_number, p.name')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function normalizeMonthSeries(array $rows, string $valueKey): array
    {
        $series = $this->emptyMonthSeries();

        foreach ($rows as $row) {
            $monthNumber = (int) ($row['month_number'] ?? 0);
            if ($monthNumber >= 1 && $monthNumber <= 12) {
                $series[$monthNumber - 1] = (int) ($row[$valueKey] ?? 0);
            }
        }

        return $series;
    }

    private function emptyMonthSeries(): array
    {
        return array_fill(0, 12, 0);
    }

    private function mergeProvinceComparisonRows(array $currentRows, array $compareRows, string $valueKey): array
    {
        $merged = [];

        foreach ($compareRows as $row) {
            $provinceId = (int) ($row['province_id'] ?? 0);
            if ($provinceId <= 0) {
                continue;
            }

            $merged[$provinceId] = [
                'province_id' => $provinceId,
                'province_number' => (string) ($row['province_number'] ?? ''),
                'province_name' => (string) ($row['province_name'] ?? ''),
                'current_value' => 0,
                'compare_value' => (int) ($row[$valueKey] ?? 0),
            ];
        }

        foreach ($currentRows as $row) {
            $provinceId = (int) ($row['province_id'] ?? 0);
            if ($provinceId <= 0) {
                continue;
            }

            if (!isset($merged[$provinceId])) {
                $merged[$provinceId] = [
                    'province_id' => $provinceId,
                    'province_number' => (string) ($row['province_number'] ?? ''),
                    'province_name' => (string) ($row['province_name'] ?? ''),
                    'current_value' => 0,
                    'compare_value' => 0,
                ];
            }

            $merged[$provinceId]['province_number'] = (string) ($row['province_number'] ?? $merged[$provinceId]['province_number']);
            $merged[$provinceId]['province_name'] = (string) ($row['province_name'] ?? $merged[$provinceId]['province_name']);
            $merged[$provinceId]['current_value'] = (int) ($row[$valueKey] ?? 0);
        }

        foreach ($merged as &$row) {
            $row['delta_value'] = $row['current_value'] - $row['compare_value'];
        }
        unset($row);

        usort($merged, static function (array $left, array $right): int {
            return $right['current_value'] <=> $left['current_value'];
        });

        return $merged;
    }

    private function tableExistsSafe($db, string $table): bool
    {
        try {
            return $db->getFieldNames($table) !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }
}
