<?php

namespace Treasurer\Controllers;

/**
 * ExpenseExports
 *
 * Expense export endpoints: CSV and PDF. Extends Expenses to reuse the
 * shared filter/query helpers. Extracted from Expenses (phase 5 split);
 * routes and behavior unchanged.
 */
class ExpenseExports extends Expenses
{

    public function export()
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $expenses = $this->buildFilteredExpensesQuery($parishId, $this->getExpenseFilters())
            ->orderBy('expense.date', 'DESC')
            ->get()
            ->getResultArray();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            lang('Finance.colDate'),
            lang('Finance.colDescription'),
            lang('Finance.colFund'),
            lang('Finance.colFundType'),
            lang('Finance.colCategory'),
            lang('Finance.colAmount'),
            lang('Finance.colApprovedBy'),
            lang('Finance.colNotes'),
        ]);

        foreach ($expenses as $expense) {
            fputcsv($handle, [
                (string) ($expense['date'] ?? ''),
                (string) ($expense['description'] ?? ''),
                (string) ($expense['fund_name'] ?? lang('Finance.unassigned')),
                $this->mapFundTypeLabel((string) ($expense['fund_type'] ?? 'unrestricted')),
                $this->mapExpenseCategoryLabel((string) ($expense['category'] ?? 'other')),
                number_format((float) ($expense['amount'] ?? 0), 2, '.', ''),
                $this->formatApproverName($expense),
                (string) ($expense['notes'] ?? ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filters = $this->getExpenseFilters();
        $this->audit()->log('expense.export_csv', [
            'parish_id' => $parishId,
            'entity_type' => 'expense_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($expenses),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildExpenseExportFilename('csv') . '"')
            ->setBody($csv ?: '');
    }


    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getExpenseFilters();
        $expenses = $this->buildFilteredExpensesQuery($parishId, $filters)
            ->orderBy('expense.date', 'DESC')
            ->get()
            ->getResultArray();
        $summary = $this->expenseModel->getParishSummary($parishId, $filters['start_date'], $filters['end_date']);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildExpensesPdfHtml($parishId, $filters, $expenses, $summary));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('expense.export_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'expense_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($expenses),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildExpenseExportFilename('pdf') . '"')
            ->setBody($dompdf->output());
    }


    private function buildExpensesPdfHtml(int $parishId, array $filters, array $expenses, array $summary): string
    {
        $parish = model('ParishModel')->find($parishId);
        $rows = '';

        foreach ($expenses as $expense) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['description'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapExpenseCategoryLabel((string) ($expense['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($expense['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noExpensesForFilters')) . '</td></tr>';
        }

        $filterParts = [];
        if (!empty($filters['category'])) {
            $filterParts[] = lang('Treasurer.categoryLabel') . ': ' . ucfirst((string) $filters['category']);
        }
        if (!empty($filters['fund_id'])) {
            $filterParts[] = lang('Treasurer.fundIdLabel') . ': ' . (int) $filters['fund_id'];
        }
        if (!empty($filters['start_date'])) {
            $filterParts[] = lang('Treasurer.fromLabel') . ': ' . $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $filterParts[] = lang('Treasurer.toLabel') . ': ' . $filters['end_date'];
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.expensesExportTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #8a1c1c; margin-bottom: 6px; }
                .meta { color: #666; margin-bottom: 18px; }
                .total-box { margin: 18px 0; padding: 14px; border: 1px solid #ddd; background: #fafafa; }
                .total-box strong { color: #8a1c1c; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.expensesExportTitle')) . '</h1>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Treasurer.parishLabel')) . ':</strong> ' . htmlspecialchars((string) ($parish['name'] ?? lang('Treasurer.unknownParish'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.filtersLabel')) . ':</strong> ' . htmlspecialchars($filterParts !== [] ? implode(' | ', $filterParts) : lang('Treasurer.noneLabel')) . '
            </div>
            <div class="total-box">' . htmlspecialchars(lang('Finance.totalExpenses')) . ': <strong>$' . number_format((float) ($summary['total'] ?? 0), 2) . '</strong></div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colDescription')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }


    private function mapExpenseCategoryLabel(string $category): string
    {
        $map = [
            'utilities' => lang('Finance.catUtilities'),
            'maintenance' => lang('Finance.catMaintenance'),
            'salaries' => lang('Finance.catSalaries'),
            'ministry' => lang('Finance.catMinistry'),
            'supplies' => lang('Finance.catSupplies'),
            'other' => lang('Finance.categoryOther'),
        ];

        return $map[$category] ?? ucfirst($category);
    }


    private function mapFundTypeLabel(string $fundType): string
    {
        $map = [
            'restricted' => lang('Treasurer.fundTypeRestricted'),
            'permanent' => lang('Treasurer.fundTypePermanent'),
            'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
        ];

        return $map[$fundType] ?? ucfirst($fundType);
    }


    private function buildExpenseExportFilename(string $extension): string
    {
        return 'expenses_export_' . date('Ymd_His') . '.' . $extension;
    }


    private function formatApproverName(array $expense): string
    {
        $name = trim((string) (($expense['approver_first_name'] ?? '') . ' ' . ($expense['approver_last_name'] ?? '')));

        return $name !== '' ? $name : (!empty($expense['approved_by']) ? 'User #' . (int) $expense['approved_by'] : '-');
    }


    private function formatLocalizedDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $locale = $this->request->getLocale() ?: config('App')->defaultLocale;
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                date_default_timezone_get()
            );
            $formatter->setPattern('MMM d, y h:mm a');
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('Y-m-d H:i', $timestamp);
    }
}
