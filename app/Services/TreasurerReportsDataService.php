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

    /**
     * Build detailed fund breakdown for parish-level reports.
     *
     * @return array{
     *     funds: list<array{
     *         fund_id: int|null,
     *         fund_name: string,
     *         fund_type: string,
     *         is_active: int,
     *         donation_count: int,
     *         donations_total: float,
     *         expense_count: int,
     *         expenses_total: float,
     *         net_balance: float
     *     }>,
     *     totals: array{
     *         donation_count: int,
     *         donations_total: float,
     *         expense_count: int,
     *         expenses_total: float,
     *         net_balance: float
     *     }
     * }
     */
    public function buildParishFundBreakdown(int $parishId, string $startDate, string $endDate): array
    {
        $db = db_connect();

        $emptyResult = [
            'funds' => [],
            'totals' => [
                'donation_count' => 0,
                'donations_total' => 0.0,
                'expense_count' => 0,
                'expenses_total' => 0.0,
                'net_balance' => 0.0,
            ],
        ];

        if ($parishId <= 0 || ! $db->tableExists('fund')) {
            return $emptyResult;
        }

        $funds = $db->table('fund')
            ->select('id, name, fund_type, is_active')
            ->where('parish_id', $parishId)
            ->orderBy('is_active', 'DESC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $donationModel = model(DonationModel::class);
        $donationsByFund = [];
        $unassignedDonations = ['count' => 0, 'total' => 0.0];

        if ($db->tableExists('donation')) {
            $donationRows = $db->table('donation')
                ->select('id, fund_id, amount')
                ->where('parish_id', $parishId)
                ->where('deleted_at', null)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getResultArray();

            foreach ($donationRows as $row) {
                $amount = $donationModel->normalizeAmount($row['amount'] ?? null);
                $fundId = (int) ($row['fund_id'] ?? 0);
                if ($fundId > 0) {
                    if (!isset($donationsByFund[$fundId])) {
                        $donationsByFund[$fundId] = ['count' => 0, 'total' => 0.0];
                    }
                    $donationsByFund[$fundId]['count']++;
                    $donationsByFund[$fundId]['total'] += $amount;
                } else {
                    $unassignedDonations['count']++;
                    $unassignedDonations['total'] += $amount;
                }
            }
        }

        $expensesByFund = [];
        $unassignedExpenses = ['count' => 0, 'total' => 0.0];

        if ($db->tableExists('expense')) {
            $expenseRows = $db->table('expense')
                ->select('fund_id, COUNT(id) as count, COALESCE(SUM(amount), 0) as total')
                ->where('parish_id', $parishId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->groupBy('fund_id')
                ->get()
                ->getResultArray();

            foreach ($expenseRows as $row) {
                $fundId = (int) ($row['fund_id'] ?? 0);
                $count = (int) ($row['count'] ?? 0);
                $amount = (float) ($row['total'] ?? 0.0);
                if ($fundId > 0) {
                    $expensesByFund[$fundId] = ['count' => $count, 'total' => $amount];
                } else {
                    $unassignedExpenses['count'] += $count;
                    $unassignedExpenses['total'] += $amount;
                }
            }
        }

        $fundList = [];
        $knownFundIds = [];

        foreach ($funds as $fund) {
            $fId = (int) $fund['id'];
            $knownFundIds[$fId] = true;
            $dCount = (int) ($donationsByFund[$fId]['count'] ?? 0);
            $dTotal = (float) ($donationsByFund[$fId]['total'] ?? 0.0);
            $eCount = (int) ($expensesByFund[$fId]['count'] ?? 0);
            $eTotal = (float) ($expensesByFund[$fId]['total'] ?? 0.0);

            $fundList[] = [
                'fund_id'         => $fId,
                'fund_name'       => (string) $fund['name'],
                'fund_type'       => (string) ($fund['fund_type'] ?? 'unrestricted'),
                'is_active'       => (int) ($fund['is_active'] ?? 1),
                'donation_count'  => $dCount,
                'donations_total' => $dTotal,
                'expense_count'   => $eCount,
                'expenses_total'  => $eTotal,
                'net_balance'     => $dTotal - $eTotal,
            ];
        }

        // Handle any transactions pointing to orphaned/historical fund IDs
        foreach ($donationsByFund as $fId => $dData) {
            if (!isset($knownFundIds[$fId])) {
                $eCount = (int) ($expensesByFund[$fId]['count'] ?? 0);
                $eTotal = (float) ($expensesByFund[$fId]['total'] ?? 0.0);
                $fundList[] = [
                    'fund_id'         => $fId,
                    'fund_name'       => lang('Treasurer.reportFund') . ' #' . $fId,
                    'fund_type'       => 'unrestricted',
                    'is_active'       => 0,
                    'donation_count'  => (int) $dData['count'],
                    'donations_total' => (float) $dData['total'],
                    'expense_count'   => $eCount,
                    'expenses_total'  => $eTotal,
                    'net_balance'     => (float) $dData['total'] - $eTotal,
                ];
                unset($expensesByFund[$fId]);
            }
        }
        foreach ($expensesByFund as $fId => $eData) {
            if (!isset($knownFundIds[$fId])) {
                $fundList[] = [
                    'fund_id'         => $fId,
                    'fund_name'       => lang('Treasurer.reportFund') . ' #' . $fId,
                    'fund_type'       => 'unrestricted',
                    'is_active'       => 0,
                    'donation_count'  => 0,
                    'donations_total' => 0.0,
                    'expense_count'   => (int) $eData['count'],
                    'expenses_total'  => (float) $eData['total'],
                    'net_balance'     => 0.0 - (float) $eData['total'],
                ];
            }
        }

        // Unassigned / general transactions
        if ($unassignedDonations['count'] > 0 || $unassignedExpenses['count'] > 0) {
            $fundList[] = [
                'fund_id'         => null,
                'fund_name'       => lang('Treasurer.reportUnassigned'),
                'fund_type'       => 'unrestricted',
                'is_active'       => 1,
                'donation_count'  => (int) $unassignedDonations['count'],
                'donations_total' => (float) $unassignedDonations['total'],
                'expense_count'   => (int) $unassignedExpenses['count'],
                'expenses_total'  => (float) $unassignedExpenses['total'],
                'net_balance'     => (float) $unassignedDonations['total'] - (float) $unassignedExpenses['total'],
            ];
        }

        $totalDCount = array_sum(array_column($fundList, 'donation_count'));
        $totalDAmount = array_sum(array_column($fundList, 'donations_total'));
        $totalECount = array_sum(array_column($fundList, 'expense_count'));
        $totalEAmount = array_sum(array_column($fundList, 'expenses_total'));

        return [
            'funds' => $fundList,
            'totals' => [
                'donation_count'  => (int) $totalDCount,
                'donations_total' => (float) $totalDAmount,
                'expense_count'   => (int) $totalECount,
                'expenses_total'  => (float) $totalEAmount,
                'net_balance'     => (float) ($totalDAmount - $totalEAmount),
            ],
        ];
    }

    /**
     * Build detailed Account and Category breakdown for parish-level reports.
     *
     * @return array{
     *     income: list<array{
     *         chart_account_id: int|null,
     *         account_code: string,
     *         account_name: string,
     *         category: string,
     *         category_label: string,
     *         transaction_count: int,
     *         total_amount: float,
     *         percentage: float
     *     }>,
     *     expenses: list<array{
     *         chart_account_id: int|null,
     *         account_code: string,
     *         account_name: string,
     *         category: string,
     *         category_label: string,
     *         transaction_count: int,
     *         total_amount: float,
     *         percentage: float
     *     }>,
     *     totals: array{
     *         income_total: float,
     *         income_count: int,
     *         expenses_total: float,
     *         expenses_count: int,
     *         net_balance: float
     *     }
     * }
     */
    public function buildParishAccountCategoryBreakdown(int $parishId, string $startDate, string $endDate): array
    {
        $db = db_connect();

        $emptyResult = [
            'income'   => [],
            'expenses' => [],
            'totals'   => [
                'income_total'   => 0.0,
                'income_count'   => 0,
                'expenses_total' => 0.0,
                'expenses_count' => 0,
                'net_balance'    => 0.0,
            ],
        ];

        if ($parishId <= 0) {
            return $emptyResult;
        }

        $donationModel = model(DonationModel::class);
        $incomeGroups = [];
        $incomeTotal = 0.0;
        $incomeCount = 0;

        if ($db->tableExists('donation')) {
            $builder = $db->table('donation d')
                ->select('d.id, d.chart_account_id, d.category, d.amount')
                ->where('d.parish_id', $parishId)
                ->where('d.deleted_at', null)
                ->where('d.date >=', $startDate)
                ->where('d.date <=', $endDate);

            if ($db->tableExists('chart_of_accounts')) {
                $builder->select('coa.code as account_code, coa.name as account_name')
                    ->join('chart_of_accounts coa', 'coa.id = d.chart_account_id', 'left');
            } else {
                $builder->select('NULL as account_code, NULL as account_name');
            }

            $donationRows = $builder->get()->getResultArray();

            foreach ($donationRows as $row) {
                $amount = $donationModel->normalizeAmount($row['amount'] ?? null);
                $incomeTotal += $amount;
                $incomeCount++;

                $accId = !empty($row['chart_account_id']) ? (int) $row['chart_account_id'] : null;
                $category = (string) ($row['category'] ?? 'other');
                $groupKey = $accId !== null ? 'acc_' . $accId : 'cat_' . $category;

                if (!isset($incomeGroups[$groupKey])) {
                    $code = !empty($row['account_code']) ? (string) $row['account_code'] : '-';
                    $name = !empty($row['account_name']) ? (string) $row['account_name'] : $this->mapCategoryLabel($category, 'income');

                    $incomeGroups[$groupKey] = [
                        'chart_account_id'  => $accId,
                        'account_code'      => $code,
                        'account_name'      => $name,
                        'category'          => $category,
                        'category_label'    => $this->mapCategoryLabel($category, 'income'),
                        'transaction_count' => 0,
                        'total_amount'      => 0.0,
                        'percentage'        => 0.0,
                    ];
                }

                $incomeGroups[$groupKey]['transaction_count']++;
                $incomeGroups[$groupKey]['total_amount'] += $amount;
            }

            foreach ($incomeGroups as &$group) {
                $group['percentage'] = $incomeTotal > 0 ? round(($group['total_amount'] / $incomeTotal) * 100, 1) : 0.0;
            }
            unset($group);

            usort($incomeGroups, static function (array $a, array $b): int {
                if ($a['account_code'] !== '-' && $b['account_code'] !== '-') {
                    return strcmp($a['account_code'], $b['account_code']);
                }
                if ($a['account_code'] !== '-') {
                    return -1;
                }
                if ($b['account_code'] !== '-') {
                    return 1;
                }
                return strcmp($a['account_name'], $b['account_name']);
            });
        }

        $expenseList = [];
        $expenseTotal = 0.0;
        $expenseCount = 0;

        if ($db->tableExists('expense')) {
            $builder = $db->table('expense e')
                ->select('e.chart_account_id, e.category, COUNT(e.id) as transaction_count, COALESCE(SUM(e.amount), 0) as total_amount')
                ->where('e.parish_id', $parishId)
                ->where('e.date >=', $startDate)
                ->where('e.date <=', $endDate);

            if ($db->tableExists('chart_of_accounts')) {
                $builder->select('coa.code as account_code, coa.name as account_name')
                    ->join('chart_of_accounts coa', 'coa.id = e.chart_account_id', 'left')
                    ->groupBy(['e.chart_account_id', 'e.category', 'coa.code', 'coa.name']);
            } else {
                $builder->select('NULL as account_code, NULL as account_name')
                    ->groupBy(['e.chart_account_id', 'e.category']);
            }

            $expenseRows = $builder->get()->getResultArray();

            foreach ($expenseRows as $row) {
                $amount = (float) ($row['total_amount'] ?? 0.0);
                $count = (int) ($row['transaction_count'] ?? 0);
                $expenseTotal += $amount;
                $expenseCount += $count;

                $accId = !empty($row['chart_account_id']) ? (int) $row['chart_account_id'] : null;
                $category = (string) ($row['category'] ?? 'other');
                $code = !empty($row['account_code']) ? (string) $row['account_code'] : '-';
                $name = !empty($row['account_name']) ? (string) $row['account_name'] : $this->mapCategoryLabel($category, 'expense');

                $expenseList[] = [
                    'chart_account_id'  => $accId,
                    'account_code'      => $code,
                    'account_name'      => $name,
                    'category'          => $category,
                    'category_label'    => $this->mapCategoryLabel($category, 'expense'),
                    'transaction_count' => $count,
                    'total_amount'      => $amount,
                    'percentage'        => 0.0,
                ];
            }

            foreach ($expenseList as &$item) {
                $item['percentage'] = $expenseTotal > 0 ? round(($item['total_amount'] / $expenseTotal) * 100, 1) : 0.0;
            }
            unset($item);

            usort($expenseList, static function (array $a, array $b): int {
                if ($a['account_code'] !== '-' && $b['account_code'] !== '-') {
                    return strcmp($a['account_code'], $b['account_code']);
                }
                if ($a['account_code'] !== '-') {
                    return -1;
                }
                if ($b['account_code'] !== '-') {
                    return 1;
                }
                return strcmp($a['account_name'], $b['account_name']);
            });
        }

        return [
            'income'   => array_values($incomeGroups),
            'expenses' => $expenseList,
            'totals'   => [
                'income_total'   => $incomeTotal,
                'income_count'   => $incomeCount,
                'expenses_total' => $expenseTotal,
                'expenses_count' => $expenseCount,
                'net_balance'    => $incomeTotal - $expenseTotal,
            ],
        ];
    }

    /**
     * Map internal donation or expense category code to a localized display label.
     */
    public function mapCategoryLabel(string $category, string $type = 'income'): string
    {
        $categoryKey = strtolower(trim($category));

        $incomeMap = [
            'tithe'         => lang('Finance.categoryTithe'),
            'offering'      => lang('Finance.categoryOffering'),
            'mission'       => lang('Finance.categoryMission'),
            'building fund' => lang('Finance.categoryBuilding'),
            'building_fund' => lang('Finance.categoryBuilding'),
            'other'         => lang('Finance.categoryOther'),
        ];

        $expenseMap = [
            'utilities'      => 'Utilities',
            'maintenance'    => 'Maintenance',
            'ministry'       => 'Ministry',
            'salaries'       => 'Salaries',
            'personnel'      => 'Personnel',
            'outreach'       => 'Outreach',
            'administrative' => 'Administrative',
            'other'          => lang('Finance.categoryOther'),
        ];

        $map = $type === 'income' ? $incomeMap : $expenseMap;
        if (isset($map[$categoryKey])) {
            return $map[$categoryKey];
        }

        return ucwords(str_replace('_', ' ', $categoryKey));
    }
}
