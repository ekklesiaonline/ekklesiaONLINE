<?php

namespace App\Services;

use App\Models\DonationModel;

/**
 * TreasurerReportsDataService
 *
 * Read-side aggregation for treasurer reports: scope summaries, diocese
 * breakdowns, province membership summary, and date validation.
 * Extracted from Treasurer\Controllers\Reports (phase 5 split);
 * behavior unchanged.
 */
class TreasurerReportsDataService
{

    public function buildSummary(string $scope, int $parishId, string $startDate, string $endDate, int $provinceScopeId = 0): array
    {
        $db = db_connect();

        $parishIds = [];
        if ($scope === 'parish' && $parishId > 0) {
            $parishIds = [$parishId];
        } elseif ($scope === 'parish') {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        } else {
            $provinceMembership = $this->getProvinceMembershipSummary($provinceScopeId);

            if ($provinceMembership !== null) {
                return [
                    'parish_count' => (int) ($provinceMembership['parish_count'] ?? 0),
                    'people_count' => (int) ($provinceMembership['people_count'] ?? 0),
                    'visitor_count' => (int) ($provinceMembership['visitor_count'] ?? 0),
                    'member_count' => (int) ($provinceMembership['member_count'] ?? 0),
                    'good_standing_count' => (int) ($provinceMembership['good_standing_count'] ?? 0),
                    'service_count' => (int) ($provinceMembership['service_count'] ?? 0),
                    'attendance_count' => (int) ($provinceMembership['attendance_count'] ?? 0),
                    'donations_total' => (float) ($provinceMembership['donations_total'] ?? 0),
                    'expenses_total' => (float) ($provinceMembership['expenses_total'] ?? 0),
                    'net_balance' => (float) ($provinceMembership['net_balance'] ?? 0),
                ];
            }

            $parishQuery = $db->table('parish c')->select('c.id');
            if ($provinceScopeId > 0) {
                $parishQuery
                    ->join('diocese d', 'd.id = c.diocese_id', 'inner')
                    ->where('d.province_id', $provinceScopeId);
            }

            $parishIds = array_map(
                static fn($row) => (int) $row['id'],
                $parishQuery->get()->getResultArray()
            );
        }

        if (empty($parishIds)) {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        }

        $parishCount = count($parishIds);

        $peopleRow = $db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) as count', false)
            ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.is_member', 1)
            ->where('mca.deleted_at IS NULL', null, false)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $memberRow = $db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) as count', false)
            ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.membership_status', 'member')
            ->where('mca.deleted_at IS NULL', null, false)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $visitorRow = $db->table('parish_affiliation pa')
            ->select('COUNT(DISTINCT pa.person_id) AS visitor_count', false)
            ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
            ->whereIn('pa.parish_id', $parishIds)
            ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->where('pa.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        $goodStandingYear = (int) date('Y', strtotime($endDate));
        $goodStandingRow = $db->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) AS count', false)
            ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id', 'inner')
            ->join('users u', 'u.id = mss.user_id', 'inner')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->whereIn('mca.parish_id', $parishIds)
            ->where('mca.is_member', 1)
            ->where('mca.membership_status', 'member')
            ->where('mss.year', $goodStandingYear)
            ->where('mss.is_good_standing', 1)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        $serviceCount = (int) $db->table('service')
            ->whereIn('parish_id', $parishIds)
            ->where('service_date >=', $startDate)
            ->where('service_date <=', $endDate)
            ->countAllResults();

        $attendanceCount = (int) $db->table('attendance a')
            ->join('service s', 's.id = a.service_id')
            ->whereIn('s.parish_id', $parishIds)
            ->where('a.attended', 1)
            ->where('s.service_date >=', $startDate)
            ->where('s.service_date <=', $endDate)
            ->countAllResults();

        $donationsTotal = model(DonationModel::class)->getAllParishesTotalDonations($startDate, $endDate, $parishIds, false);

        $expenseRow = $db->table('expense')
            ->select('COALESCE(SUM(amount), 0) AS total_amount')
            ->whereIn('parish_id', $parishIds)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getRowArray();

        $expensesTotal = (float) ($expenseRow['total_amount'] ?? 0);

        return [
            'parish_count' => $parishCount,
            'people_count' => (int) ($peopleRow['count'] ?? 0) + (int) ($visitorRow['visitor_count'] ?? 0),
            'visitor_count' => (int) ($visitorRow['visitor_count'] ?? 0),
            'member_count' => (int) ($memberRow['count'] ?? 0),
            'good_standing_count' => (int) ($goodStandingRow['count'] ?? 0),
            'service_count' => $serviceCount,
            'attendance_count' => $attendanceCount,
            'donations_total' => $donationsTotal,
            'expenses_total' => $expensesTotal,
            'net_balance' => $donationsTotal - $expensesTotal,
        ];
    }


    public function buildDioceseBreakdown(int $provinceId, string $startDate, string $endDate): array
    {
        $db = db_connect();
        $dioceses = $db->table('diocese')
            ->select('id, name')
            ->where('province_id', $provinceId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($dioceses)) {
            return [];
        }

        // OPTIMIZATION: Fetch all parishes in one query, then group by diocese (N+1 prevention)
        $dioceseIds = array_map(static fn(array $d) => (int) $d['id'], $dioceses);
        $allParishes = $db->table('parish')
            ->select('id, diocese_id')
            ->whereIn('diocese_id', $dioceseIds)
            ->get()
            ->getResultArray();

        // Group parishes by diocese in memory
        $parishesByDiocese = [];
        foreach ($allParishes as $parish) {
            $dioId = (int) $parish['diocese_id'];
            if (!isset($parishesByDiocese[$dioId])) {
                $parishesByDiocese[$dioId] = [];
            }
            $parishesByDiocese[$dioId][] = (int) $parish['id'];
        }

        $rows = [];
        foreach ($dioceses as $diocese) {
            $dioceseId = (int) $diocese['id'];
            $parishIds = $parishesByDiocese[$dioceseId] ?? [];

            if (empty($parishIds)) {
                $rows[] = [
                    'diocese_id' => $dioceseId,
                    'diocese_name' => (string) $diocese['name'],
                    'parish_count' => 0,
                    'people_count' => 0,
                    'visitor_count' => 0,
                    'member_count' => 0,
                    'good_standing_count' => 0,
                    'service_count' => 0,
                    'attendance_count' => 0,
                    'donations_total' => 0.0,
                    'expenses_total' => 0.0,
                    'net_balance' => 0.0,
                ];
                continue;
            }

            // Build summary for the whole diocese parish set.
            $peopleRow = $db->table('parish_affiliation mca')
                ->select('COUNT(DISTINCT mca.person_id) as count', false)
                ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
                ->join('users u', 'u.id = mca.linked_user_id', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.is_member', 1)
                ->where('mca.deleted_at IS NULL', null, false)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $memberRow = $db->table('parish_affiliation mca')
                ->select('COUNT(DISTINCT mca.person_id) as count', false)
                ->join('person pe', 'pe.id = mca.person_id AND pe.deleted_at IS NULL', 'inner')
                ->join('users u', 'u.id = mca.linked_user_id', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.membership_status', 'member')
                ->where('mca.deleted_at IS NULL', null, false)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $visitorRow = $db->table('parish_affiliation pa')
                ->select('COUNT(DISTINCT pa.person_id) AS visitor_count', false)
                ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
                ->whereIn('pa.parish_id', $parishIds)
                ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
                ->where('pa.converted_to_member_at IS NULL', null, false)
                ->where('pa.deleted_at IS NULL', null, false)
                ->get()
                ->getRowArray();

            $goodStandingYear = (int) date('Y', strtotime($endDate));
            $goodStandingRow = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) AS count', false)
                ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id', 'inner')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
                ->whereIn('mca.parish_id', $parishIds)
                ->where('mca.is_member', 1)
                ->where('mca.membership_status', 'member')
                ->where('mss.year', $goodStandingYear)
                ->where('mss.is_good_standing', 1)
                ->where('agu.id IS NULL')
                ->get()
                ->getRowArray();

            $serviceCount = (int) $db->table('service')
                ->whereIn('parish_id', $parishIds)
                ->where('service_date >=', $startDate)
                ->where('service_date <=', $endDate)
                ->countAllResults();

            $attendanceCount = (int) $db->table('attendance a')
                ->join('service s', 's.id = a.service_id')
                ->whereIn('s.parish_id', $parishIds)
                ->where('a.attended', 1)
                ->where('s.service_date >=', $startDate)
                ->where('s.service_date <=', $endDate)
                ->countAllResults();

            $donationsTotal = model(DonationModel::class)->getAllParishesTotalDonations($startDate, $endDate, $parishIds, false);

            $expenseRow = $db->table('expense')
                ->select('COALESCE(SUM(amount), 0) AS total_amount')
                ->whereIn('parish_id', $parishIds)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getRowArray();

            $expensesTotal = (float) ($expenseRow['total_amount'] ?? 0);

            $rows[] = [
                'diocese_id' => (int) $diocese['id'],
                'diocese_name' => (string) $diocese['name'],
                'parish_count' => count($parishIds),
                'people_count' => (int) ($peopleRow['count'] ?? 0) + (int) ($visitorRow['visitor_count'] ?? 0),
                'visitor_count' => (int) ($visitorRow['visitor_count'] ?? 0),
                'member_count' => (int) ($memberRow['count'] ?? 0),
                'good_standing_count' => (int) ($goodStandingRow['count'] ?? 0),
                'service_count' => $serviceCount,
                'attendance_count' => $attendanceCount,
                'donations_total' => $donationsTotal,
                'expenses_total' => $expensesTotal,
                'net_balance' => $donationsTotal - $expensesTotal,
            ];
        }

        return $rows;
    }


    public function getProvinceMembershipSummary(int $provinceId): ?array
    {
        if ($provinceId <= 0) {
            return null;
        }

        $breakdown = $this->buildDioceseBreakdown($provinceId, date('Y-01-01'), date('Y-m-d'));

        if ($breakdown === []) {
            return [
                'parish_count' => 0,
                'people_count' => 0,
                'visitor_count' => 0,
                'member_count' => 0,
                'good_standing_count' => 0,
                'service_count' => 0,
                'attendance_count' => 0,
                'donations_total' => 0.0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ];
        }

        $summary = [
            'parish_count' => 0,
            'people_count' => 0,
            'visitor_count' => 0,
            'member_count' => 0,
            'good_standing_count' => 0,
            'service_count' => 0,
            'attendance_count' => 0,
            'donations_total' => 0.0,
            'expenses_total' => 0.0,
            'net_balance' => 0.0,
        ];

        foreach ($breakdown as $row) {
            $summary['parish_count'] += (int) ($row['parish_count'] ?? 0);
            $summary['people_count'] += (int) ($row['people_count'] ?? 0);
            $summary['visitor_count'] += (int) ($row['visitor_count'] ?? 0);
            $summary['member_count'] += (int) ($row['member_count'] ?? 0);
            $summary['good_standing_count'] += (int) ($row['good_standing_count'] ?? 0);
            $summary['service_count'] += (int) ($row['service_count'] ?? 0);
            $summary['attendance_count'] += (int) ($row['attendance_count'] ?? 0);
            $summary['donations_total'] += (float) ($row['donations_total'] ?? 0);
            $summary['expenses_total'] += (float) ($row['expenses_total'] ?? 0);
        }

        $summary['net_balance'] = $summary['donations_total'] - $summary['expenses_total'];

        return $summary;
    }


    public function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
