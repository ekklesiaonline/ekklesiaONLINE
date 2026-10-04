<?php

namespace Treasurer\Controllers;

/**
 * DonationExports
 *
 * CSV/PDF export endpoints and PDF helpers for the donations ledger.
 * Extracted from Donations (treasurer second pass); extends it for the
 * shared filter/query and profile-resolution helpers.
 */
class DonationExports extends Donations
{
    private const CSV_EXPORT_MAX_ROWS = 50000;
    private const PDF_EXPORT_MAX_ROWS = 3000;

    public function export()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getDonationFilters();

        $query = $this->buildFilteredDonationsQuery($parishId, $filters)
            ->orderBy($this->donationTable() . '.date', 'DESC')
            ->get();

        // Stream rows unbuffered into php://temp (spills to disk past 2 MB)
        // so peak memory stays bounded regardless of result size.
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            lang('Finance.colDate'),
            lang('Finance.colDonor'),
            lang('Finance.colFund'),
            lang('Finance.colFundType'),
            lang('Finance.colCategory'),
            lang('Finance.colAmount'),
            lang('Finance.colPaymentMethod'),
            lang('Finance.colNotes'),
            'Receipt Number',
        ]);

        $rowCount = 0;
        while ($rowCount < self::CSV_EXPORT_MAX_ROWS && is_array($donation = $query->getUnbufferedRow('array'))) {
            fputcsv($handle, [
                (string) ($donation['date'] ?? ''),
                $this->formatDonorName($donation),
                (string) ($donation['fund_name'] ?? lang('Finance.unassigned')),
                $this->mapFundTypeLabel((string) ($donation['fund_type'] ?? 'unrestricted')),
                $this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other')),
                number_format((float) ($donation['amount'] ?? 0), 2, '.', ''),
                (string) ($donation['payment_method'] ?? ''),
                (string) ($donation['notes'] ?? ''),
                (string) ($donation['receipt_number'] ?? ''),
            ]);
            $rowCount++;
        }

        $query->freeResult();
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('donation.export_csv', [
            'parish_id' => $parishId,
            'entity_type' => 'donation_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => $rowCount,
                    'truncated' => $rowCount >= self::CSV_EXPORT_MAX_ROWS,
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildDonationExportFilename('csv') . '"')
            ->setBody($csv ?: '');
    }


    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getDonationFilters();
        $donations = $this->buildFilteredDonationsQuery($parishId, $filters)
            ->orderBy($this->donationTable() . '.date', 'DESC')
            ->limit(self::PDF_EXPORT_MAX_ROWS + 1)
            ->get()
            ->getResultArray();

        $pdfTruncated = count($donations) > self::PDF_EXPORT_MAX_ROWS;
        if ($pdfTruncated) {
            $donations = array_slice($donations, 0, self::PDF_EXPORT_MAX_ROWS);
        }

        $summary = $this->donationModel->getParishSummary($parishId, $filters['start_date'], $filters['end_date']);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildDonationsPdfHtml($parishId, $filters, $donations, $summary, $pdfTruncated));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('donation.export_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'donation_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($donations),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildDonationExportFilename('pdf') . '"')
            ->setBody($dompdf->output());
    }


    private function buildDonationsPdfHtml(int $parishId, array $filters, array $donations, array $summary, bool $truncated = false): string
    {
        $rows = '';

        foreach ($donations as $donation) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->formatDonorName($donation)) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapFundTypeLabel((string) ($donation['fund_type'] ?? 'unrestricted'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($donation['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noDonationsForFilters')) . '</td></tr>';
        }

        $filterParts = [];
        if (!empty($filters['category'])) {
            $filterParts[] = lang('Treasurer.categoryLabel') . ': ' . $this->mapDonationCategoryLabel((string) $filters['category']);
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
            <title>' . htmlspecialchars(lang('Treasurer.donationsExportTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #1f4b8f; margin-bottom: 6px; }
                .meta { color: #666; margin-bottom: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
                .summary { margin: 18px 0; }
                .summary-box { display: inline-block; width: 31%; margin-right: 2%; padding: 12px; border: 1px solid #ddd; background: #fafafa; box-sizing: border-box; }
                .summary-label { color: #666; font-size: 11px; text-transform: uppercase; }
                .summary-value { font-size: 18px; color: #1f4b8f; font-weight: bold; margin-top: 4px; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.donationsExportTitle')) . '</h1>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Treasurer.parishLabel')) . ':</strong> ' . htmlspecialchars($this->getParishName($parishId)) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.filtersLabel')) . ':</strong> ' . htmlspecialchars($filterParts !== [] ? implode(' | ', $filterParts) : lang('Treasurer.noneLabel')) . ($truncated ? '<br><strong>' . htmlspecialchars(sprintf('Showing first %d records. Narrow the filters or use CSV export for the full set.', self::PDF_EXPORT_MAX_ROWS)) . '</strong>' : '') . '
            </div>
            <div class="summary">
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Treasurer.totalDonationsLabel')) . '</div>
                    <div class="summary-value">$' . number_format((float) ($summary['total'] ?? 0), 2) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Finance.categoryTithe')) . '</div>
                    <div class="summary-value">$' . number_format((float) ($summary['tithe'] ?? 0), 2) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Treasurer.offeringsOtherLabel')) . '</div>
                    <div class="summary-value">$' . number_format((float) (($summary['offering'] ?? 0) + ($summary['other'] ?? 0)), 2) . '</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colDonor')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFundType')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }


    private function buildDonationExportFilename(string $extension): string
    {
        return 'donations_export_' . date('Ymd_His') . '.' . $extension;
    }


    private function getParishName(int $parishId): string
    {
        $parish = model('ParishModel')->find($parishId);

        return (string) ($parish['name'] ?? lang('Treasurer.unknownParish'));
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


    private function mapDonationCategoryLabel(string $category): string
    {
        $map = [
            'tithe' => lang('Finance.categoryTithe'),
            'offering' => lang('Finance.categoryOffering'),
            'mission' => lang('Finance.categoryMission'),
            'building fund' => lang('Finance.categoryBuilding'),
            'building_fund' => lang('Finance.categoryBuilding'),
            'other' => lang('Finance.categoryOther'),
        ];

        return $map[$category] ?? ucfirst(str_replace('_', ' ', $category));
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
