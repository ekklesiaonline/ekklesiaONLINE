<?php

namespace Treasurer\Controllers;

/**
 * FundExports
 *
 * CSV export of the parish fund ledger. Extracted from Funds (treasurer
 * second pass); extends it for scope and audit helpers.
 */
class FundExports extends Funds
{

    /**
     * Export fund balances to CSV.
     */
    public function export()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $status   = (string) ($this->request->getGet('status') ?? 'active');
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $funds = $this->fundModel->getParishFundsWithBalances($parishId, false);

        if ($status !== 'all') {
            $wantActive = $status === 'active' ? 1 : 0;
            $funds      = array_values(array_filter(
                $funds,
                static fn (array $fund): bool => (int) ($fund['is_active'] ?? 0) === $wantActive
            ));
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundSaveFailed'));
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            lang('Finance.fundName'),
            lang('Finance.fundType'),
            lang('Finance.fundRestrictionPurpose'),
            lang('Finance.chartOfAccountField'),
            lang('Finance.fundIncome'),
            lang('Finance.fundExpensesTotal'),
            lang('Finance.fundBalance'),
            lang('Finance.donationsTitle'),
            lang('Finance.expensesTitle'),
            lang('App.colStatus'),
            lang('Finance.fundLockedBadge'),
        ]);

        foreach ($funds as $fund) {
            fputcsv($handle, [
                (string) ($fund['name'] ?? ''),
                (string) ($fund['fund_type'] ?? ''),
                (string) ($fund['restriction_purpose'] ?? ''),
                trim(($fund['chart_account_code'] ?? '') . ' ' . ($fund['chart_account_name'] ?? '')),
                number_format((float) ($fund['total_income'] ?? 0), 2, '.', ''),
                number_format((float) ($fund['total_expenses'] ?? 0), 2, '.', ''),
                number_format((float) ($fund['balance'] ?? 0), 2, '.', ''),
                (int) ($fund['donation_count'] ?? 0),
                (int) ($fund['expense_count'] ?? 0),
                (int) ($fund['is_active'] ?? 0) === 1 ? lang('Finance.fundStatusActive') : lang('Finance.fundStatusInactive'),
                (int) ($fund['is_locked'] ?? 0) === 1 ? lang('App.yes') : lang('App.no'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('fund.export_csv', [
            'parish_id'   => $parishId,
            'entity_type' => 'fund',
            'entity_id'   => null,
            'meta'        => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count'  => count($funds),
                    'status' => $status,
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="funds-' . date('Y-m-d') . '.csv"')
            ->setBody($csv ?: '');
    }
}
