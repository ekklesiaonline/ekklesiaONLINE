<?php

namespace App\Services;

use App\Models\DonationModel;

/**
 * FinanceReportsDataService
 *
 * Read-side aggregation for financial reports: scope summaries, diocese
 * breakdowns, province membership summary, and date validation.
 * Extracted from Finance\Controllers\Reports (phase 5 split);
 * behavior unchanged.
 */
class FinanceReportsDataService
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

        $hierarchyLabels = lang('ChartAccountCategories.category');
        if (is_array($hierarchyLabels) && isset($hierarchyLabels[$categoryKey])) {
            return (string) $hierarchyLabels[$categoryKey];
        }

        return ucwords(str_replace(['.', '_'], [' → ', ' '], $categoryKey));
    }

    /**
     * Builds the Trial Balance for a parish as of a specified date.
     *
     * Summarizes the net ending debit and credit balances across all active chart of accounts
     * lines from posted general ledger journal entries up to the specified date.
     *
     * Normal Balances:
     * - Asset (1000s) & Expense (6000s-8000s): Debit normal balance. Net = Debits - Credits.
     * - Liability (2000s), Equity (3000s), & Income (4000s-5000s): Credit normal balance. Net = Credits - Debits.
     *
     * @return array{
     *     as_of_date: string,
     *     parish_id: int,
     *     accounts: array<int, array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         account_type: string,
     *         debit_balance: float,
     *         credit_balance: float,
     *         total_debit: float,
     *         total_credit: float
     *     }>,
     *     total_debits: float,
     *     total_credits: float,
     *     variance: float,
     *     is_balanced: bool,
     *     include_zero: bool
     * }
     */
    public function buildTrialBalance(int $parishId, string $asOfDate, bool $includeZero = false): array
    {
        if (!$this->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $db = db_connect();

        // Subquery aggregating debit and credit totals up to $asOfDate for this parish
        $aggSubquery = $db->table('journal_entry_line jel')
            ->select('jel.chart_account_id, ' .
                'COALESCE(SUM(jel.debit_amount), 0.0) as sum_debit, ' .
                'COALESCE(SUM(jel.credit_amount), 0.0) as sum_credit')
            ->join('journal_entry je', 'je.id = jel.journal_entry_id', 'inner')
            ->where('je.parish_id', $parishId)
            ->where('je.entry_date <=', $asOfDate)
            ->where('je.status', 'posted')
            ->groupBy('jel.chart_account_id');

        $compiledSubquery = $aggSubquery->getCompiledSelect();

        $rows = $db->table('chart_of_accounts coa')
            ->select('coa.id, coa.code, coa.name, coa.account_type, coa.sort_order, ' .
                'COALESCE(agg.sum_debit, 0.0) as total_debit, ' .
                'COALESCE(agg.sum_credit, 0.0) as total_credit')
            ->join("({$compiledSubquery}) agg", 'agg.chart_account_id = coa.id', 'left')
            ->where('coa.is_active', 1)
            ->orderBy("CASE coa.account_type 
                WHEN 'asset' THEN 1 
                WHEN 'liability' THEN 2 
                WHEN 'equity' THEN 3 
                WHEN 'income' THEN 4 
                WHEN 'expense' THEN 5 
                ELSE 6 END", 'ASC', false)
            ->orderBy('coa.sort_order', 'ASC')
            ->orderBy('coa.code', 'ASC')
            ->get()
            ->getResultArray();

        $accounts = [];
        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($rows as $row) {
            $totalDebit = (float) ($row['total_debit'] ?? 0.0);
            $totalCredit = (float) ($row['total_credit'] ?? 0.0);
            $type = strtolower((string) ($row['account_type'] ?? ''));

            $debitBalance = 0.0;
            $creditBalance = 0.0;

            if (in_array($type, ['asset', 'expense'], true)) {
                // Normal debit balance
                $net = $totalDebit - $totalCredit;
                if ($net >= 0) {
                    $debitBalance = round($net, 2);
                } else {
                    $creditBalance = round(abs($net), 2);
                }
            } else {
                // Normal credit balance (liability, equity, income)
                $net = $totalCredit - $totalDebit;
                if ($net >= 0) {
                    $creditBalance = round($net, 2);
                } else {
                    $debitBalance = round(abs($net), 2);
                }
            }

            // If account has zero balances and not includeZero, skip
            if (!$includeZero && $debitBalance == 0.0 && $creditBalance == 0.0) {
                continue;
            }

            $totalDebits += $debitBalance;
            $totalCredits += $creditBalance;

            $accounts[] = [
                'id'             => (int) $row['id'],
                'code'           => (string) $row['code'],
                'name'           => (string) $row['name'],
                'account_type'   => $type,
                'debit_balance'  => $debitBalance,
                'credit_balance' => $creditBalance,
                'total_debit'    => $totalDebit,
                'total_credit'   => $totalCredit,
            ];
        }

        $totalDebits = round($totalDebits, 2);
        $totalCredits = round($totalCredits, 2);
        $variance = round(abs($totalDebits - $totalCredits), 2);
        $isBalanced = $variance <= 0.005;

        return [
            'as_of_date'    => $asOfDate,
            'parish_id'     => $parishId,
            'accounts'      => $accounts,
            'total_debits'  => $totalDebits,
            'total_credits' => $totalCredits,
            'variance'      => $variance,
            'is_balanced'   => $isBalanced,
            'include_zero'  => $includeZero,
        ];
    }

    /**
     * Builds the Statement of Financial Position (Balance Sheet) as of a given date.
     * Assets = Liabilities + Net Assets.
     *
     * @return array<string, mixed>
     */
    public function buildStatementOfFinancialPosition(int $parishId, string $asOfDate): array
    {
        if (!$this->isValidDate($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $db = db_connect();

        // 1. Aggregate posted debits and credits up to $asOfDate
        $aggSubquery = $db->table('journal_entry_line jel')
            ->select('jel.chart_account_id, ' .
                'COALESCE(SUM(jel.debit_amount), 0.0) as sum_debit, ' .
                'COALESCE(SUM(jel.credit_amount), 0.0) as sum_credit')
            ->join('journal_entry je', 'je.id = jel.journal_entry_id', 'inner')
            ->where('je.parish_id', $parishId)
            ->where('je.entry_date <=', $asOfDate)
            ->where('je.status', 'posted')
            ->groupBy('jel.chart_account_id');

        $compiledSubquery = $aggSubquery->getCompiledSelect();

        $rows = $db->table('chart_of_accounts coa')
            ->select('coa.id, coa.code, coa.name, coa.account_type, coa.category, coa.sort_order, ' .
                'COALESCE(agg.sum_debit, 0.0) as total_debit, ' .
                'COALESCE(agg.sum_credit, 0.0) as total_credit')
            ->join("({$compiledSubquery}) agg", 'agg.chart_account_id = coa.id', 'left')
            ->where('coa.is_active', 1)
            ->orderBy('coa.sort_order', 'ASC')
            ->orderBy('coa.code', 'ASC')
            ->get()
            ->getResultArray();

        $currentAssets = [];
        $fixedAssets = [];
        $totalCurrentAssets = 0.0;
        $totalFixedAssets = 0.0;
        $totalAssets = 0.0;

        $currentLiabilities = [];
        $longTermLiabilities = [];
        $totalCurrentLiabilities = 0.0;
        $totalLongTermLiabilities = 0.0;
        $totalLiabilities = 0.0;

        $equityAccounts = [];
        $totalStatedEquity = 0.0;

        $cumulativeIncome = 0.0;
        $cumulativeExpense = 0.0;

        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $name = (string) $row['name'];
            $type = strtolower((string) ($row['account_type'] ?? ''));
            $cat  = strtolower((string) ($row['category'] ?? ''));
            $debit = (float) ($row['total_debit'] ?? 0.0);
            $credit = (float) ($row['total_credit'] ?? 0.0);

            if ($type === 'asset') {
                $balance = round($debit - $credit, 2);
                $accountData = [
                    'id'      => (int) $row['id'],
                    'code'    => $code,
                    'name'    => $name,
                    'balance' => $balance,
                ];

                if (str_starts_with($code, '14') || str_contains($cat, 'fixed')) {
                    $fixedAssets[] = $accountData;
                    $totalFixedAssets += $balance;
                } else {
                    $currentAssets[] = $accountData;
                    $totalCurrentAssets += $balance;
                }
                $totalAssets += $balance;
            } elseif ($type === 'liability') {
                $balance = round($credit - $debit, 2);
                $accountData = [
                    'id'      => (int) $row['id'],
                    'code'    => $code,
                    'name'    => $name,
                    'balance' => $balance,
                ];

                if (str_starts_with($code, '23') || str_contains($cat, 'long_term') || str_contains($cat, 'mortgage')) {
                    $longTermLiabilities[] = $accountData;
                    $totalLongTermLiabilities += $balance;
                } else {
                    $currentLiabilities[] = $accountData;
                    $totalCurrentLiabilities += $balance;
                }
                $totalLiabilities += $balance;
            } elseif ($type === 'equity') {
                $balance = round($credit - $debit, 2);
                $equityAccounts[] = [
                    'id'      => (int) $row['id'],
                    'code'    => $code,
                    'name'    => $name,
                    'balance' => $balance,
                ];
                $totalStatedEquity += $balance;
            } elseif ($type === 'income') {
                $cumulativeIncome += ($credit - $debit);
            } elseif ($type === 'expense') {
                $cumulativeExpense += ($debit - $credit);
            }
        }

        // Cumulative operating surplus / (deficit) up to as-of date
        $currentPeriodNetChange = round($cumulativeIncome - $cumulativeExpense, 2);
        $totalNetAssets = round($totalStatedEquity + $currentPeriodNetChange, 2);

        $totalCurrentAssets = round($totalCurrentAssets, 2);
        $totalFixedAssets = round($totalFixedAssets, 2);
        $totalAssets = round($totalAssets, 2);

        $totalCurrentLiabilities = round($totalCurrentLiabilities, 2);
        $totalLongTermLiabilities = round($totalLongTermLiabilities, 2);
        $totalLiabilities = round($totalLiabilities, 2);

        $totalLiabilitiesAndNetAssets = round($totalLiabilities + $totalNetAssets, 2);
        $variance = round(abs($totalAssets - $totalLiabilitiesAndNetAssets), 2);
        $isBalanced = $variance <= 0.005;

        // Parish Fund Breakdown for equity context
        $fundsSummary = [
            'unrestricted'           => 0.0,
            'temporarily_restricted' => 0.0,
            'permanently_restricted' => 0.0,
            'total'                  => 0.0,
            'funds'                  => [],
        ];

        if ($db->tableExists('fund')) {
            $hasCurrentBalance = $db->fieldExists('current_balance', 'fund');
            $selectCols = 'id, name, fund_type, is_active' . ($hasCurrentBalance ? ', current_balance' : '');
            $fundRows = $db->table('fund')
                ->select($selectCols)
                ->where('parish_id', $parishId)
                ->where('is_active', 1)
                ->get()
                ->getResultArray();

            foreach ($fundRows as $f) {
                $fBal = $hasCurrentBalance ? (float) ($f['current_balance'] ?? 0.0) : 0.0;
                $fType = (string) ($f['fund_type'] ?? 'unrestricted');
                if ($fType === 'temporarily_restricted' || $fType === 'restricted') {
                    $fundsSummary['temporarily_restricted'] += $fBal;
                } elseif ($fType === 'permanently_restricted' || $fType === 'endowment') {
                    $fundsSummary['permanently_restricted'] += $fBal;
                } else {
                    $fundsSummary['unrestricted'] += $fBal;
                }
                $fundsSummary['total'] += $fBal;
                $fundsSummary['funds'][] = [
                    'name'    => (string) $f['name'],
                    'type'    => $fType,
                    'balance' => $fBal,
                ];
            }
        }

        return [
            'as_of_date'                       => $asOfDate,
            'parish_id'                        => $parishId,
            'current_assets'                   => $currentAssets,
            'total_current_assets'             => $totalCurrentAssets,
            'fixed_assets'                     => $fixedAssets,
            'total_fixed_assets'               => $totalFixedAssets,
            'total_assets'                     => $totalAssets,
            'current_liabilities'              => $currentLiabilities,
            'total_current_liabilities'        => $totalCurrentLiabilities,
            'long_term_liabilities'            => $longTermLiabilities,
            'total_long_term_liabilities'      => $totalLongTermLiabilities,
            'total_liabilities'                => $totalLiabilities,
            'equity_accounts'                  => $equityAccounts,
            'total_stated_equity'              => $totalStatedEquity,
            'current_period_net_change'        => $currentPeriodNetChange,
            'total_net_assets'                 => $totalNetAssets,
            'total_liabilities_and_net_assets' => $totalLiabilitiesAndNetAssets,
            'variance'                         => $variance,
            'is_balanced'                      => $isBalanced,
            'funds_summary'                    => $fundsSummary,
        ];
    }

    /**
     * Builds the Statement of Activities (Revenues and Expenses / Changes in Net Assets)
     * for a given date range.
     *
     * @return array<string, mixed>
     */
    public function buildStatementOfActivities(int $parishId, string $startDate, string $endDate): array
    {
        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        $db = db_connect();

        // 1. Aggregate posted debits and credits between $startDate and $endDate
        $aggSubquery = $db->table('journal_entry_line jel')
            ->select('jel.chart_account_id, ' .
                'COALESCE(SUM(jel.debit_amount), 0.0) as sum_debit, ' .
                'COALESCE(SUM(jel.credit_amount), 0.0) as sum_credit')
            ->join('journal_entry je', 'je.id = jel.journal_entry_id', 'inner')
            ->where('je.parish_id', $parishId)
            ->where('je.entry_date >=', $startDate)
            ->where('je.entry_date <=', $endDate)
            ->where('je.status', 'posted')
            ->groupBy('jel.chart_account_id');

        $compiledSubquery = $aggSubquery->getCompiledSelect();

        $rows = $db->table('chart_of_accounts coa')
            ->select('coa.id, coa.code, coa.name, coa.account_type, coa.category, coa.sort_order, ' .
                'COALESCE(agg.sum_debit, 0.0) as total_debit, ' .
                'COALESCE(agg.sum_credit, 0.0) as total_credit')
            ->join("({$compiledSubquery}) agg", 'agg.chart_account_id = coa.id', 'left')
            ->where('coa.is_active', 1)
            ->whereIn('coa.account_type', ['income', 'expense'])
            ->orderBy('coa.sort_order', 'ASC')
            ->orderBy('coa.code', 'ASC')
            ->get()
            ->getResultArray();

        $unrestrictedRevenues = [];
        $restrictedRevenues = [];
        $totalUnrestrictedRevenues = 0.0;
        $totalRestrictedRevenues = 0.0;
        $totalRevenues = 0.0;

        $outreachExpenses = [];
        $ministryExpenses = [];
        $operationsExpenses = [];
        $totalOutreachExpenses = 0.0;
        $totalMinistryExpenses = 0.0;
        $totalOperationsExpenses = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $name = (string) $row['name'];
            $type = strtolower((string) ($row['account_type'] ?? ''));
            $debit = (float) ($row['total_debit'] ?? 0.0);
            $credit = (float) ($row['total_credit'] ?? 0.0);

            if ($type === 'income') {
                $amount = round($credit - $debit, 2);
                if ($amount == 0.0 && $debit == 0.0 && $credit == 0.0) {
                    continue;
                }

                $accountData = [
                    'id'     => (int) $row['id'],
                    'code'   => $code,
                    'name'   => $name,
                    'amount' => $amount,
                ];

                // 4000-4999: Unrestricted, 5000-5999: Restricted/Designated
                if (str_starts_with($code, '5') || str_contains(strtolower($name), 'restricted') || str_contains(strtolower($name), 'designated')) {
                    $restrictedRevenues[] = $accountData;
                    $totalRestrictedRevenues += $amount;
                } else {
                    $unrestrictedRevenues[] = $accountData;
                    $totalUnrestrictedRevenues += $amount;
                }
                $totalRevenues += $amount;
            } elseif ($type === 'expense') {
                $amount = round($debit - $credit, 2);
                if ($amount == 0.0 && $debit == 0.0 && $credit == 0.0) {
                    continue;
                }

                $accountData = [
                    'id'     => (int) $row['id'],
                    'code'   => $code,
                    'name'   => $name,
                    'amount' => $amount,
                ];

                // 6000s: Outreach, 7000s: Ministry & Program, 8000s: Operations & Facilities
                if (str_starts_with($code, '6')) {
                    $outreachExpenses[] = $accountData;
                    $totalOutreachExpenses += $amount;
                } elseif (str_starts_with($code, '7')) {
                    $ministryExpenses[] = $accountData;
                    $totalMinistryExpenses += $amount;
                } else {
                    $operationsExpenses[] = $accountData;
                    $totalOperationsExpenses += $amount;
                }
                $totalExpenses += $amount;
            }
        }

        $totalUnrestrictedRevenues = round($totalUnrestrictedRevenues, 2);
        $totalRestrictedRevenues = round($totalRestrictedRevenues, 2);
        $totalRevenues = round($totalRevenues, 2);

        $totalOutreachExpenses = round($totalOutreachExpenses, 2);
        $totalMinistryExpenses = round($totalMinistryExpenses, 2);
        $totalOperationsExpenses = round($totalOperationsExpenses, 2);
        $totalExpenses = round($totalExpenses, 2);

        $netChange = round($totalRevenues - $totalExpenses, 2);

        return [
            'start_date'                  => $startDate,
            'end_date'                    => $endDate,
            'parish_id'                   => $parishId,
            'unrestricted_revenues'       => $unrestrictedRevenues,
            'total_unrestricted_revenues' => $totalUnrestrictedRevenues,
            'restricted_revenues'         => $restrictedRevenues,
            'total_restricted_revenues'   => $totalRestrictedRevenues,
            'total_revenues'              => $totalRevenues,
            'outreach_expenses'           => $outreachExpenses,
            'total_outreach_expenses'     => $totalOutreachExpenses,
            'ministry_expenses'           => $ministryExpenses,
            'total_ministry_expenses'     => $totalMinistryExpenses,
            'operations_expenses'         => $operationsExpenses,
            'total_operations_expenses'   => $totalOperationsExpenses,
            'total_expenses'              => $totalExpenses,
            'net_change'                  => $netChange,
        ];
    }

    /**
     * Compiles the authoritative Episcopal Parochial Report finance lines (Lines 1 to 12b)
     * following the national Church canons and the official reference contract.
     *
     * @param int $parishId
     * @param int $year
     * @return array<string, mixed>
     */
    public function buildParochialReportFinanceLines(int $parishId, int $year): array
    {
        $db = db_connect();
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        // 1. Line 1 & Line 2: Pledges
        $pledgesCount = 0;
        $pledgesAmount = 0.0;
        if ($db->tableExists('annual_pledge')) {
            $pledges = model(\App\Models\AnnualPledgeModel::class)->getParishPledgeSummary($parishId, $year);
            $pledgesCount = (int) ($pledges['total_pledges'] ?? 0);
            $pledgesAmount = (float) ($pledges['pledged_total'] ?? 0.0);
        }

        // 2. Fetch any manual entries from parish_report_storage
        $storage = [];
        if ($db->tableExists('parish_report_storage')) {
            $storageRow = $db->table('parish_report_storage')
                ->where('parish_id', $parishId)
                ->where('report_year', $year)
                ->get()
                ->getRowArray();
            if ($storageRow) {
                $storage = $storageRow;
            }
        }

        // 3. Check General Ledger entries for the year
        $glEntriesExist = false;
        if ($db->tableExists('journal_entry') && $db->tableExists('journal_entry_line') && $db->tableExists('chart_of_accounts')) {
            $checkCount = $db->table('journal_entry')
                ->where('parish_id', $parishId)
                ->where('entry_date >=', $startDate)
                ->where('entry_date <=', $endDate)
                ->where('status', 'posted')
                ->countAllResults();
            $glEntriesExist = ($checkCount > 0);
        }

        $line3 = 0.0;
        $line4 = 0.0;
        $line5 = 0.0;
        $line6 = 0.0;
        $line7 = 0.0;
        $cashBalance = 0.0;
        $debtAmount = 0.0;
        $rentalIncome = 0.0;
        $rentalExpense = 0.0;
        $ownsProperty = 0;

        $rev3Accounts = [];
        $rev4Accounts = [];
        $rev5Accounts = [];
        $exp6Accounts = [];
        $exp7Accounts = [];
        $cash8Accounts = [];

        if ($glEntriesExist) {
            // Aggregate posted debits and credits by account
            $aggSubquery = $db->table('journal_entry_line jel')
                ->select('jel.chart_account_id, ' .
                    'COALESCE(SUM(jel.debit_amount), 0.0) as sum_debit, ' .
                    'COALESCE(SUM(jel.credit_amount), 0.0) as sum_credit')
                ->join('journal_entry je', 'je.id = jel.journal_entry_id', 'inner')
                ->where('je.parish_id', $parishId)
                ->where('je.entry_date >=', $startDate)
                ->where('je.entry_date <=', $endDate)
                ->where('je.status', 'posted')
                ->groupBy('jel.chart_account_id');

            $compiledSubquery = $aggSubquery->getCompiledSelect();

            $rows = $db->table('chart_of_accounts coa')
                ->select('coa.id, coa.code, coa.name, coa.account_type, coa.category, ' .
                    'COALESCE(agg.sum_debit, 0.0) as total_debit, ' .
                    'COALESCE(agg.sum_credit, 0.0) as total_credit')
                ->join("({$compiledSubquery}) agg", 'agg.chart_account_id = coa.id', 'left')
                ->where('coa.is_active', 1)
                ->whereIn('coa.account_type', ['income', 'expense'])
                ->orderBy('coa.code', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $code = (string) $row['code'];
                $name = (string) $row['name'];
                $type = strtolower((string) ($row['account_type'] ?? ''));
                $debit = (float) ($row['total_debit'] ?? 0.0);
                $credit = (float) ($row['total_credit'] ?? 0.0);

                if ($type === 'income') {
                    $amount = round($credit - $debit, 2);
                    if ($amount == 0.0 && $debit == 0.0 && $credit == 0.0) {
                        continue;
                    }

                    $accData = ['code' => $code, 'name' => $name, 'amount' => $amount];

                    if (str_starts_with($code, '41') || $code === '4320') {
                        // Line 3: Plate, pledge, and unrestricted contributions
                        $line3 += $amount;
                        $rev3Accounts[] = $accData;
                    } elseif (str_starts_with($code, '5')) {
                        // Line 4: Restricted/Designated contributions
                        $line4 += $amount;
                        $rev4Accounts[] = $accData;
                    } else {
                        // Line 5: All other operating revenue (rentals 42xx, fundraising 4310, sales 4330, etc.)
                        $line5 += $amount;
                        $rev5Accounts[] = $accData;
                        if (str_starts_with($code, '42')) {
                            $rentalIncome += $amount;
                        }
                    }
                } elseif ($type === 'expense') {
                    $amount = round($debit - $credit, 2);
                    if ($amount == 0.0 && $debit == 0.0 && $credit == 0.0) {
                        continue;
                    }

                    $accData = ['code' => $code, 'name' => $name, 'amount' => $amount];

                    // Line 7: Capital expenses (8610 Major Repairs, 8611 Capital Purchases)
                    if (str_starts_with($code, '86')) {
                        $line7 += $amount;
                        $exp7Accounts[] = $accData;
                    } else {
                        // Line 6: Non-capital operating expenses (6xxx, 7xxx, 8xxx other)
                        $line6 += $amount;
                        $exp6Accounts[] = $accData;
                        if ($code === '8111') {
                            $rentalExpense += $amount;
                        }
                    }
                }
            }

            $jelTable = $db->prefixTable('journal_entry_line');
            $jeTable = $db->prefixTable('journal_entry');

            // Year-end Cash balances as of Dec 31
            $cashRows = $db->table('chart_of_accounts coa')
                ->select('coa.code, coa.name, ' .
                    '(SELECT COALESCE(SUM(jel.debit_amount - jel.credit_amount), 0.0) ' .
                    " FROM {$jelTable} jel " .
                    " JOIN {$jeTable} je ON je.id = jel.journal_entry_id " .
                    ' WHERE jel.chart_account_id = coa.id AND je.parish_id = ' . $db->escape($parishId) . ' ' .
                    ' AND je.entry_date <= ' . $db->escape($endDate) . ' AND je.status = "posted") as balance')
                ->where('coa.is_active', 1)
                ->where('coa.account_type', 'asset')
                ->where('coa.code LIKE "11%"')
                ->get()
                ->getResultArray();

            foreach ($cashRows as $crow) {
                $cbal = (float) ($crow['balance'] ?? 0.0);
                if ($cbal != 0.0) {
                    $cash8Accounts[] = ['code' => $crow['code'], 'name' => $crow['name'], 'balance' => $cbal];
                    $cashBalance += $cbal;
                }
            }

            // Debt balances as of Dec 31 (2110 AP, 23xx Notes/Mortgage)
            $debtRows = $db->table('chart_of_accounts coa')
                ->select('(SELECT COALESCE(SUM(jel.credit_amount - jel.debit_amount), 0.0) ' .
                    " FROM {$jelTable} jel " .
                    " JOIN {$jeTable} je ON je.id = jel.journal_entry_id " .
                    ' WHERE jel.chart_account_id = coa.id AND je.parish_id = ' . $db->escape($parishId) . ' ' .
                    ' AND je.entry_date <= ' . $db->escape($endDate) . ' AND je.status = "posted") as balance')
                ->where('coa.is_active', 1)
                ->where('coa.account_type', 'liability')
                ->where('(coa.code = "2110" OR coa.code LIKE "23%")')
                ->get()
                ->getResultArray();

            foreach ($debtRows as $drow) {
                $dbal = (float) ($drow['balance'] ?? 0.0);
                if ($dbal > 0) {
                    $debtAmount += $dbal;
                }
            }

            // Property check (Fixed assets 14xx)
            $propCount = $db->table('chart_of_accounts coa')
                ->where('coa.is_active', 1)
                ->where('coa.account_type', 'asset')
                ->where('coa.code LIKE "14%"')
                ->countAllResults();
            $ownsProperty = $propCount > 0 ? 1 : 0;
        }

        // Fallbacks for transactions when GL is unpopulated
        if ($line3 === 0.0 && $line4 === 0.0 && $db->tableExists('donation')) {
            $donationModel = model(\App\Models\DonationModel::class);
            $donationRows = $db->table('donation')
                ->select('amount, is_restricted')
                ->where('parish_id', $parishId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getResultArray();

            foreach ($donationRows as $row) {
                $amount = $donationModel->normalizeAmount($row['amount'] ?? null);
                if ((int) ($row['is_restricted'] ?? 0) === 1) {
                    $line4 += $amount;
                } else {
                    $line3 += $amount;
                }
            }
        }

        if ($line6 === 0.0 && $line7 === 0.0 && $db->tableExists('expense')) {
            $expenseTable = $db->prefixTable('expense');
            $expenseRow = $db->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN is_capital = 0 THEN amount ELSE 0 END), 0) AS noncapital,
                    COALESCE(SUM(CASE WHEN is_capital = 1 THEN amount ELSE 0 END), 0) AS capital
                 FROM {$expenseTable}
                 WHERE parish_id = ? AND date >= ? AND date <= ?",
                [$parishId, $startDate, $endDate]
            )->getRowArray() ?? [];

            $line6 = (float) ($expenseRow['noncapital'] ?? 0.0);
            $line7 = (float) ($expenseRow['capital'] ?? 0.0);
        }

        // Check bank accounts for cash balance fallback if GL was 0
        if ($cashBalance === 0.0 && $db->tableExists('bank_account')) {
            $bankRows = $db->table('bank_account')
                ->select('opening_balance, id')
                ->where('parish_id', $parishId)
                ->where('is_active', 1)
                ->get()
                ->getResultArray();

            foreach ($bankRows as $b) {
                $bBal = (float) ($b['opening_balance'] ?? 0.0);
                if ($db->tableExists('bank_transaction')) {
                    $txTotal = (float) ($db->table('bank_transaction')
                        ->select('COALESCE(SUM(amount), 0) as total')
                        ->where('bank_account_id', (int) $b['id'])
                        ->where('transaction_date <=', $endDate)
                        ->get()
                        ->getRowArray()['total'] ?? 0.0);
                    $bBal += $txTotal;
                }
                $cashBalance += $bBal;
            }
        }

        // Merge manual storage values if present
        if (!empty($storage)) {
            if ($pledgesCount === 0 && isset($storage['confirmed_pledges_count'])) {
                $pledgesCount = (int) $storage['confirmed_pledges_count'];
            }
            if ($pledgesAmount === 0.0 && isset($storage['confirmed_pledges_amount'])) {
                $pledgesAmount = (float) $storage['confirmed_pledges_amount'];
            }
            if ($line5 === 0.0 && isset($storage['other_revenue_amount'])) {
                $line5 = (float) $storage['other_revenue_amount'];
            }
            if ($cashBalance === 0.0 && isset($storage['cash_balance'])) {
                $cashBalance = (float) $storage['cash_balance'];
            }
            if ($debtAmount === 0.0 && isset($storage['debt_amount'])) {
                $debtAmount = (float) $storage['debt_amount'];
            }
            if ($rentalIncome === 0.0 && isset($storage['rental_income'])) {
                $rentalIncome = (float) $storage['rental_income'];
            }
            if ($rentalExpense === 0.0 && isset($storage['rental_expense'])) {
                $rentalExpense = (float) $storage['rental_expense'];
            }
            if (isset($storage['owns_property'])) {
                $ownsProperty = (int) $storage['owns_property'];
            }
        }

        $line3 = round($line3, 2);
        $line4 = round($line4, 2);
        $line5 = round($line5, 2);
        $totalRevenue = round($line3 + $line4 + $line5, 2);

        $line6 = round($line6, 2);
        $line7 = round($line7, 2);
        $totalExpenses = round($line6 + $line7, 2);

        $netOperating = round($totalRevenue - $totalExpenses, 2);
        $cashBalance = round($cashBalance, 2);
        $debtAmount = round($debtAmount, 2);
        $hasDebt = ($debtAmount > 0.0 || (int) ($storage['has_debt'] ?? 0) === 1) ? 1 : 0;
        $rentsToOutside = ($rentalIncome > 0.0 || (int) ($storage['rents_to_outside'] ?? 0) === 1) ? 1 : 0;

        // Endowments and Investments (Line 9)
        $endowmentRestricted = (float) ($storage['endowment_restricted'] ?? 0.0);
        $endowmentUnrestricted = (float) ($storage['endowment_unrestricted'] ?? 0.0);
        $totalEndowment = round($endowmentRestricted + $endowmentUnrestricted, 2);

        $investRestricted = (float) ($storage['investment_restricted'] ?? 0.0);
        $investUnrestricted = (float) ($storage['investment_unrestricted'] ?? 0.0);
        $totalNonendowed = round($investRestricted + $investUnrestricted, 2);

        $totalInvestments = round($totalEndowment + $totalNonendowed, 2);

        return [
            'year'                     => $year,
            'parish_id'                => $parishId,
            'reporting_currency'       => (string) ($storage['reporting_currency'] ?? 'USD'),
            // Stewardship (Lines 1 & 2)
            'confirmed_pledges_count'  => $pledgesCount,
            'confirmed_pledges_amount' => $pledgesAmount,
            // Operating Revenue (Lines 3, 4, 5 & Total A)
            'line_3_plate_pledge'      => $line3,
            'revenue_unrestricted'     => $line3,
            'line_4_restricted'        => $line4,
            'revenue_restricted'       => $line4,
            'line_5_other_revenue'     => $line5,
            'other_revenue'            => $line5,
            'other_revenue_amount'     => $line5,
            'total_revenue'            => $totalRevenue,
            // Operating Expenses (Lines 6, 7 & Total B)
            'line_6_noncapital'        => $line6,
            'expenses_noncapital'      => $line6,
            'line_7_capital'           => $line7,
            'expenses_capital'         => $line7,
            'total_expenses'           => $totalExpenses,
            'net_operating'            => $netOperating,
            // Year-End Cash & Bank Accounts (Line 8)
            'cash_balance'             => $cashBalance,
            'line_8_cash'              => $cashBalance,
            // Endowments & Investments (Line 9a-d)
            'endowment_restricted'     => $endowmentRestricted,
            'endowment_unrestricted'   => $endowmentUnrestricted,
            'total_endowment'          => $totalEndowment,
            'investment_restricted'    => $investRestricted,
            'investment_unrestricted'  => $investUnrestricted,
            'total_nonendowed'         => $totalNonendowed,
            'total_investments'        => $totalInvestments,
            'pct_draw'                 => (float) ($storage['pct_draw'] ?? 0.0),
            'amount_drawn_operating'   => (float) ($storage['amount_drawn_operating'] ?? 0.0),
            'amount_drawn_capital'     => (float) ($storage['amount_drawn_capital'] ?? 0.0),
            'investment_net_gain_loss' => (float) ($storage['investment_net_gain_loss'] ?? 0.0),
            // Debt (Line 10)
            'has_debt'                 => $hasDebt,
            'debt_amount'              => $debtAmount,
            'debt_description'         => (string) ($storage['debt_description'] ?? ''),
            // Property & Rental (Lines 11 & 12)
            'owns_property'            => $ownsProperty,
            'rents_to_outside'         => $rentsToOutside,
            'rental_income'            => $rentalIncome,
            'rental_expense'           => $rentalExpense,
            // Detailed account line items for audit & breakdown
            'revenue_line3_accounts'   => $rev3Accounts,
            'revenue_line4_accounts'   => $rev4Accounts,
            'revenue_line5_accounts'   => $rev5Accounts,
            'expense_line6_accounts'   => $exp6Accounts,
            'expense_line7_accounts'   => $exp7Accounts,
            'cash_line8_accounts'      => $cash8Accounts,
        ];
    }
}

