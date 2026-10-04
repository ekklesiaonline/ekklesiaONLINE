<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DonationModel;
use App\Models\ParishModel;
use App\Models\ProfileModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/**
 * GivingStatementService
 *
 * Domain service for annual and batch member contribution statements
 * (IRS / donor tax letters). Handles donor aggregation, tax substantiation
 * disclosures, combined multi-page PDF generation, and ZIP packaging.
 */
class GivingStatementService
{
    private DonationModel $donationModel;
    private ParishModel $parishModel;
    private ProfileModel $profileModel;

    public function __construct(
        ?DonationModel $donationModel = null,
        ?ParishModel $parishModel = null,
        ?ProfileModel $profileModel = null
    ) {
        $this->donationModel = $donationModel ?? model(DonationModel::class);
        $this->parishModel   = $parishModel ?? model(ParishModel::class);
        $this->profileModel  = $profileModel ?? model(ProfileModel::class);
    }

    /**
     * Get distinct fiscal years with recorded donations for a parish.
     *
     * @return list<int>
     */
    public function getAvailableYears(int $parishId): array
    {
        $db = $this->donationModel->db;
        $table = $db->prefixTable('donation');

        if (! $db->tableExists('donation')) {
            $curYear = (int) date('Y');
            return [$curYear, $curYear - 1];
        }

        $rows = $db->table($table)
            ->select('date')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $years = [];
        foreach ($rows as $row) {
            $rawDate = (string) ($row['date'] ?? '');
            if (strlen($rawDate) >= 4) {
                $y = (int) substr($rawDate, 0, 4);
                if ($y >= 2000 && $y <= 2100) {
                    $years[$y] = true;
                }
            }
        }

        $curYear = (int) date('Y');
        $years[$curYear] = true;
        $years[$curYear - 1] = true;

        $sorted = array_keys($years);
        rsort($sorted, SORT_NUMERIC);

        return $sorted;
    }

    /**
     * Aggregate all member donations in a parish for a specific fiscal year.
     *
     * @return list<array<string, mixed>>
     */
    public function getParishAnnualDonorStatements(int $parishId, int $year, float $minAmount = 0.01): array
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate   = sprintf('%04d-12-31', $year);

        return $this->getDonorStatementsForPeriod($parishId, $startDate, $endDate, $year, $minAmount);
    }

    /**
     * Aggregate donations for a custom period (or single member).
     *
     * @return list<array<string, mixed>>
     */
    public function getDonorStatementsForPeriod(
        int $parishId,
        string $startDate,
        string $endDate,
        ?int $year = null,
        float $minAmount = 0.01,
        ?int $specificUserId = null
    ): array {
        $db = $this->donationModel->db;
        if (! $db->tableExists('donation')) {
            return [];
        }

        $donationTable = $db->prefixTable('donation');
        $fundTable     = $db->prefixTable('fund');

        $builder = $db->table($donationTable . ' d')
            ->select('d.*, f.name AS fund_name, f.fund_type')
            ->join($fundTable . ' f', 'f.id = d.fund_id', 'left')
            ->where('d.parish_id', $parishId)
            ->where('d.deleted_at', null)
            ->where('d.user_id IS NOT NULL', null, false)
            ->where('d.user_id >', 0)
            ->where('d.date >=', $startDate)
            ->where('d.date <=', $endDate)
            ->orderBy('d.date', 'ASC');

        if ($specificUserId !== null && $specificUserId > 0) {
            $builder->where('d.user_id', $specificUserId);
        }

        $rows = $builder->get()->getResultArray();
        if ($rows === []) {
            return [];
        }

        // Group rows by user_id and decrypt amounts
        $donorDonations = [];
        foreach ($rows as $row) {
            $row = $this->donationModel->decryptDonationRow($row);
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $donorDonations[$userId][] = $row;
        }

        if ($donorDonations === []) {
            return [];
        }

        $userIds = array_keys($donorDonations);

        // Fetch profiles
        $profiles = [];
        if ($db->tableExists('profile')) {
            $profileRows = $this->profileModel->whereIn('user_id', $userIds)->findAll();
            foreach ($profileRows as $p) {
                $profiles[(int) $p['user_id']] = $p;
            }
        }

        // Fetch emails from auth_identities
        $emails = [];
        if ($db->tableExists('auth_identities')) {
            $identityRows = $db->table('auth_identities')
                ->select('user_id, secret AS email')
                ->where('type', 'email_password')
                ->whereIn('user_id', $userIds)
                ->get()
                ->getResultArray();
            foreach ($identityRows as $iRow) {
                $emails[(int) $iRow['user_id']] = (string) ($iRow['email'] ?? '');
            }
        }

        $effectiveYear = $year ?? (int) substr($startDate, 0, 4);
        $statements = [];

        foreach ($donorDonations as $userId => $donations) {
            $totalAmount = 0.0;
            $fundTotals = [];
            $categoryTotals = [];

            foreach ($donations as $donation) {
                $amount = (float) ($donation['amount'] ?? 0);
                $totalAmount += $amount;

                $fundName = (string) ($donation['fund_name'] ?? lang('Finance.unassigned'));
                if ($fundName === '') {
                    $fundName = lang('Finance.unassigned');
                }
                $fundTotals[$fundName] = ($fundTotals[$fundName] ?? 0.0) + $amount;

                $cat = (string) ($donation['category'] ?? 'other');
                $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0.0) + $amount;
            }

            if ($totalAmount < $minAmount) {
                continue;
            }

            $profile = $profiles[$userId] ?? [];
            $firstName = trim((string) ($profile['first_name'] ?? ''));
            $lastName  = trim((string) ($profile['last_name'] ?? ''));
            $donorName = trim($firstName . ' ' . $lastName);

            if ($donorName === '') {
                $donorName = lang('Finance.memberPrefix') . $userId;
            }

            $email = $emails[$userId] ?? (string) ($profile['email'] ?? '');

            // Address assembly
            $address1 = trim((string) ($profile['address'] ?? ($profile['street'] ?? '')));
            $address2 = trim((string) ($profile['address_line_2'] ?? ''));
            $city     = trim((string) ($profile['city'] ?? ''));
            $state    = trim((string) ($profile['state'] ?? ''));
            $zip      = trim((string) ($profile['zip'] ?? ''));
            $country  = trim((string) ($profile['country'] ?? ''));

            $statements[] = [
                'user_id'         => $userId,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'donor_name'      => $donorName,
                'email'           => $email,
                'address_line_1'  => $address1,
                'address_line_2'  => $address2,
                'city'            => $city,
                'state'           => $state,
                'zip'             => $zip,
                'country'         => $country,
                'year'            => $effectiveYear,
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'total_amount'    => $totalAmount,
                'donations'       => $donations,
                'fund_totals'     => $fundTotals,
                'category_totals' => $categoryTotals,
            ];
        }

        /** @var list<array<string, mixed>> $statements */
        usort($statements, static function (array $a, array $b): int {
            $cmpLast = strcasecmp((string) $a['last_name'], (string) $b['last_name']);
            if ($cmpLast !== 0) {
                return $cmpLast;
            }
            return strcasecmp((string) $a['donor_name'], (string) $b['donor_name']);
        });

        return $statements;
    }

    /**
     * Generate HTML for a member giving statement (tax substantiation letter).
     *
     * @param array<string, mixed> $statement
     * @param array<string, mixed> $parish
     */
    public function buildStatementHtml(array $statement, array $parish, bool $isStandalone = true): string
    {
        $parishName = (string) ($parish['name'] ?? lang('Treasurer.unknownParish'));
        $parishTaxId = trim((string) ($parish['tax_id'] ?? ''));
        $parishStreet = trim((string) ($parish['street'] ?? ($parish['address'] ?? '')));
        $parishCity = trim((string) ($parish['city'] ?? ''));
        $parishState = trim((string) ($parish['state'] ?? ''));
        $parishZip = trim((string) ($parish['zip'] ?? ''));
        $parishPhone = trim((string) ($parish['phone'] ?? ''));
        $parishEmail = trim((string) ($parish['email'] ?? ''));

        $parishAddressParts = array_filter([$parishStreet, array_filter([$parishCity, $parishState, $parishZip]) ? trim($parishCity . ', ' . $parishState . ' ' . $parishZip) : '']);
        $parishAddressStr = implode(' • ', $parishAddressParts);

        $donorName = (string) ($statement['donor_name'] ?? '');
        $userId = (int) ($statement['user_id'] ?? 0);
        $email = (string) ($statement['email'] ?? '');
        $startDate = (string) ($statement['start_date'] ?? '');
        $endDate = (string) ($statement['end_date'] ?? '');
        $year = (int) ($statement['year'] ?? date('Y'));
        $totalAmount = (float) ($statement['total_amount'] ?? 0);
        $donations = (array) ($statement['donations'] ?? []);
        $fundTotals = (array) ($statement['fund_totals'] ?? []);

        // Donor address block
        $donorAddress1 = (string) ($statement['address_line_1'] ?? '');
        $donorAddress2 = (string) ($statement['address_line_2'] ?? '');
        $donorCity = (string) ($statement['city'] ?? '');
        $donorState = (string) ($statement['state'] ?? '');
        $donorZip = (string) ($statement['zip'] ?? '');

        $donorCityStateZip = trim($donorCity . ($donorCity !== '' && $donorState !== '' ? ', ' : ' ') . $donorState . ' ' . $donorZip);

        // Transaction rows
        $rowsHtml = '';
        foreach ($donations as $donation) {
            $catLabel = $this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other'));
            $fundName = (string) ($donation['fund_name'] ?? lang('Finance.unassigned'));
            $rowsHtml .= '<tr>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars((string) ($donation['date'] ?? '')) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($fundName) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($catLabel) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars((string) ($donation['payment_method'] ?? '-')) . '</td>'
                . '<td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 500;">$' . number_format((float) ($donation['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        // Fund subtotals table rows
        $fundSummaryHtml = '';
        if (count($fundTotals) > 1) {
            $fundSummaryHtml .= '<div style="margin-top: 14px; margin-bottom: 16px;">'
                . '<div style="font-size: 11px; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">'
                . htmlspecialchars(lang('Finance.colFund') . ' ' . lang('Dashboard.summaryLabel'))
                . '</div>'
                . '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
            foreach ($fundTotals as $fName => $fSum) {
                $fundSummaryHtml .= '<tr>'
                    . '<td style="padding: 3px 0; color: #334155;">' . htmlspecialchars((string) $fName) . '</td>'
                    . '<td style="padding: 3px 0; text-align: right; color: #0f172a; font-weight: 600;">$' . number_format((float) $fSum, 2) . '</td>'
                    . '</tr>';
            }
            $fundSummaryHtml .= '</table></div>';
        }

        $taxDisclaimer = lang('Finance.taxComplianceNotice');
        if ($taxDisclaimer === 'Finance.taxComplianceNotice') {
            $taxDisclaimer = 'Thank you for your generous financial support of ' . $parishName . '. No goods or services were provided in exchange for this contribution other than intangible religious benefits.';
        }

        $officialTitle = lang('Finance.officialTaxReceipt');
        if ($officialTitle === 'Finance.officialTaxReceipt') {
            $officialTitle = 'Annual Contribution Tax Statement';
        }

        $periodLabel = lang('Treasurer.statementPeriodLabel');
        if ($periodLabel === 'Treasurer.statementPeriodLabel') {
            $periodLabel = 'Tax Year';
        }

        $bodyContent = '
        <div class="statement-card">
            <!-- Header -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
                <tr>
                    <td style="vertical-align: top; width: 60%;">
                        <div style="font-size: 18px; font-weight: bold; color: #1e3a8a; margin-bottom: 4px;">' . htmlspecialchars($parishName) . '</div>
                        ' . ($parishAddressStr !== '' ? '<div style="font-size: 10px; color: #475569; margin-bottom: 2px;">' . htmlspecialchars($parishAddressStr) . '</div>' : '') . '
                        ' . ($parishPhone !== '' || $parishEmail !== '' ? '<div style="font-size: 10px; color: #475569; margin-bottom: 2px;">' . htmlspecialchars(trim($parishPhone . ' • ' . $parishEmail, ' •')) . '</div>' : '') . '
                        ' . ($parishTaxId !== '' ? '<div style="font-size: 10px; color: #1e293b; font-weight: bold; margin-top: 4px;">' . htmlspecialchars(lang('Finance.taxIdLabel')) . ': ' . htmlspecialchars($parishTaxId) . '</div>' : '') . '
                    </td>
                    <td style="vertical-align: top; width: 40%; text-align: right;">
                        <div style="font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">' . htmlspecialchars($officialTitle) . '</div>
                        <div style="font-size: 12px; color: #2563eb; font-weight: bold; margin-top: 3px;">' . htmlspecialchars($periodLabel) . ': ' . $year . '</div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">' . htmlspecialchars($startDate) . ' — ' . htmlspecialchars($endDate) . '</div>
                        <div style="font-size: 9px; color: #94a3b8; margin-top: 3px;">' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ': ' . date('Y-m-d H:i') . '</div>
                    </td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 2px solid #e2e8f0; margin-bottom: 16px;">

            <!-- Recipient & Summary Grid -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
                <tr>
                    <!-- Donor Info -->
                    <td style="vertical-align: top; width: 55%; padding-right: 15px;">
                        <div style="font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">' . htmlspecialchars(lang('Finance.colDonor')) . '</div>
                        <div style="font-size: 14px; font-weight: bold; color: #0f172a;">' . htmlspecialchars($donorName) . '</div>
                        ' . ($donorAddress1 !== '' ? '<div style="font-size: 11px; color: #334155; margin-top: 2px;">' . htmlspecialchars($donorAddress1) . '</div>' : '') . '
                        ' . ($donorAddress2 !== '' ? '<div style="font-size: 11px; color: #334155;">' . htmlspecialchars($donorAddress2) . '</div>' : '') . '
                        ' . ($donorCityStateZip !== '' ? '<div style="font-size: 11px; color: #334155;">' . htmlspecialchars($donorCityStateZip) . '</div>' : '') . '
                        <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                            ' . ($email !== '' ? htmlspecialchars($email) . ' • ' : '') . 'ID #' . $userId . '
                        </div>
                    </td>
                    <!-- Total Giving Summary Box -->
                    <td style="vertical-align: top; width: 45%;">
                        <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px 14px;">
                            <div style="font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">' . htmlspecialchars(lang('Finance.totalTaxDeductible')) . '</div>
                            <div style="font-size: 20px; font-weight: bold; color: #1e3a8a; margin-top: 4px; margin-bottom: 2px;">$' . number_format($totalAmount, 2) . '</div>
                            <div style="font-size: 10px; color: #475569;">' . count($donations) . ' ' . htmlspecialchars(lang('Finance.donationsTitle')) . '</div>
                            ' . $fundSummaryHtml . '
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Itemized Table -->
            <div style="margin-top: 8px; margin-bottom: 18px;">
                <div style="font-size: 11px; font-weight: bold; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">' . htmlspecialchars(lang('Finance.itemizedContributions')) . '</div>
                <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                    <thead>
                        <tr style="background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                            <th style="padding: 6px 8px; text-align: left; color: #334155;">' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                            <th style="padding: 6px 8px; text-align: left; color: #334155;">' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                            <th style="padding: 6px 8px; text-align: left; color: #334155;">' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                            <th style="padding: 6px 8px; text-align: left; color: #334155;">' . htmlspecialchars(lang('Finance.colPaymentMethod')) . '</th>
                            <th style="padding: 6px 8px; text-align: right; color: #334155;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #cbd5e1; background-color: #f8fafc;">
                            <td colspan="4" style="padding: 8px; font-weight: bold; text-align: right; color: #0f172a;">' . htmlspecialchars(lang('Treasurer.totalContributionsLabel')) . ':</td>
                            <td style="padding: 8px; font-weight: bold; text-align: right; color: #1e3a8a; font-size: 11px;">$' . number_format($totalAmount, 2) . '</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Tax Compliance Disclaimer -->
            <div style="border: 1px solid #e2e8f0; background-color: #fafaf9; border-radius: 4px; padding: 10px 12px; margin-top: 14px; margin-bottom: 24px;">
                <div style="font-size: 10px; color: #44403c; line-height: 1.45;">
                    <strong>' . htmlspecialchars(lang('Finance.taxSubstantiationNotice')) . ':</strong> '
                    . htmlspecialchars($taxDisclaimer) . '
                </div>
            </div>

            <!-- Sign-off Block -->
            <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-size: 9px; color: #94a3b8; text-transform: uppercase;">' . htmlspecialchars(lang('Finance.officialTaxRecordNotice')) . '</div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <div style="display: inline-block; border-top: 1px solid #94a3b8; width: 180px; padding-top: 4px; text-align: center; font-size: 10px; color: #475569;">
                            ' . htmlspecialchars(lang('Finance.authorizedTreasurerSignature')) . '
                        </div>
                    </td>
                </tr>
            </table>
        </div>';

        if (! $isStandalone) {
            return $bodyContent;
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($donorName . ' - ' . $year . ' Giving Statement') . '</title>
            <style>
                @page { margin: 15mm 15mm 15mm 15mm; }
                body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 0; }
                .statement-card { padding: 4px; }
            </style>
        </head>
        <body>
            ' . $bodyContent . '
        </body>
        </html>';
    }

    /**
     * Generate a single multi-page PDF containing statements for all qualifying donors.
     *
     * @return array{content: string, filename: string, count: int, total_amount: float}|null
     */
    public function generateCombinedPdf(int $parishId, int $year, float $minAmount = 0.01): ?array
    {
        $statements = $this->getParishAnnualDonorStatements($parishId, $year, $minAmount);
        if ($statements === []) {
            return null;
        }

        $parish = $this->parishModel->find($parishId);
        if (! is_array($parish)) {
            $parish = ['id' => $parishId, 'name' => lang('Treasurer.unknownParish')];
        }

        $grandTotal = 0.0;
        $pagesHtml = '';
        $count = count($statements);

        foreach ($statements as $idx => $st) {
            $grandTotal += (float) ($st['total_amount'] ?? 0);
            $isLast = ($idx === $count - 1);
            $breakStyle = $isLast ? '' : 'style="page-break-after: always;"';

            $pagesHtml .= '<div class="statement-page" ' . $breakStyle . '>'
                . $this->buildStatementHtml($st, $parish, false)
                . '</div>';
        }

        $fullHtml = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Annual Contribution Statements - ' . $year . '</title>
            <style>
                @page { margin: 14mm 15mm 14mm 15mm; }
                body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 0; }
                .statement-page { padding: 4px; }
            </style>
        </head>
        <body>
            ' . $pagesHtml . '
        </body>
        </html>';

        $dompdf = $this->createDompdfInstance();
        $dompdf->loadHtml($fullHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $cleanParishName = $this->sanitizeFilenamePart((string) ($parish['name'] ?? 'Parish'));
        $filename = sprintf('Annual_Statements_%d_%s_%s.pdf', $year, $cleanParishName, date('Ymd_His'));

        return [
            'content'      => (string) $dompdf->output(),
            'filename'     => $filename,
            'count'        => $count,
            'total_amount' => $grandTotal,
        ];
    }

    /**
     * Generate a ZIP archive containing individual PDF statements for each donor.
     *
     * @return array{content: string, filename: string, count: int, total_amount: float}|null
     */
    public function generateZipArchive(int $parishId, int $year, float $minAmount = 0.01): ?array
    {
        $statements = $this->getParishAnnualDonorStatements($parishId, $year, $minAmount);
        if ($statements === []) {
            return null;
        }

        $parish = $this->parishModel->find($parishId);
        if (! is_array($parish)) {
            $parish = ['id' => $parishId, 'name' => lang('Treasurer.unknownParish')];
        }

        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        $cacheDir = WRITEPATH . 'cache';
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $tmpZip = $cacheDir . '/stmts_' . $parishId . '_' . $year . '_' . bin2hex(random_bytes(6)) . '.zip';
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $grandTotal = 0.0;
        foreach ($statements as $st) {
            $grandTotal += (float) ($st['total_amount'] ?? 0);
            $donorHtml = $this->buildStatementHtml($st, $parish, true);

            $dompdf = $this->createDompdfInstance();
            $dompdf->loadHtml($donorHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfBytes = (string) $dompdf->output();

            $donorNamePart = $this->sanitizeFilenamePart((string) ($st['donor_name'] ?? 'Donor'));
            $pdfName = sprintf('Tax_Statement_%d_%s_%d.pdf', $year, $donorNamePart, (int) $st['user_id']);

            $zip->addFromString($pdfName, $pdfBytes);
            unset($dompdf);
        }

        $zip->close();

        $content = file_get_contents($tmpZip);
        @unlink($tmpZip);

        if ($content === false) {
            return null;
        }

        $cleanParishName = $this->sanitizeFilenamePart((string) ($parish['name'] ?? 'Parish'));
        $zipFilename = sprintf('Annual_Statements_%d_%s_%s.zip', $year, $cleanParishName, date('Ymd_His'));

        return [
            'content'      => $content,
            'filename'     => $zipFilename,
            'count'        => count($statements),
            'total_amount' => $grandTotal,
        ];
    }

    /**
     * Generate a single member statement PDF.
     *
     * @return array{content: string, filename: string, count: int, total_amount: float}|null
     */
    public function generateSingleStatementPdf(int $parishId, int $userId, string $startDate, string $endDate): ?array
    {
        $statements = $this->getDonorStatementsForPeriod($parishId, $startDate, $endDate, null, 0.0, $userId);
        if ($statements === []) {
            return null;
        }

        $st = $statements[0];
        $parish = $this->parishModel->find($parishId);
        if (! is_array($parish)) {
            $parish = ['id' => $parishId, 'name' => lang('Treasurer.unknownParish')];
        }

        $html = $this->buildStatementHtml($st, $parish, true);

        $dompdf = $this->createDompdfInstance();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $donorNamePart = $this->sanitizeFilenamePart((string) ($st['donor_name'] ?? 'Donor'));
        $filename = sprintf('Statement_%s_%d_%s.pdf', $donorNamePart, $userId, date('Ymd_His'));

        return [
            'content'      => (string) $dompdf->output(),
            'filename'     => $filename,
            'count'        => count((array) ($st['donations'] ?? [])),
            'total_amount' => (float) ($st['total_amount'] ?? 0),
        ];
    }

    private function createDompdfInstance(): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        return new Dompdf($options);
    }

    private function sanitizeFilenamePart(string $raw): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw) ?? 'Statement';
        $clean = trim(preg_replace('/_+/', '_', $clean) ?? 'Statement', '_');

        return $clean !== '' ? $clean : 'Statement';
    }

    private function mapDonationCategoryLabel(string $category): string
    {
        $map = [
            'tithe'         => lang('Finance.categoryTithe'),
            'offering'      => lang('Finance.categoryOffering'),
            'mission'       => lang('Finance.categoryMission'),
            'building fund' => lang('Finance.categoryBuilding'),
            'building_fund' => lang('Finance.categoryBuilding'),
            'other'         => lang('Finance.categoryOther'),
        ];

        return $map[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }
}
