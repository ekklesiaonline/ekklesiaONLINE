<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Services\MemberDashboardDataService;

/**
 * Personal dashboard for the logged-in user.
 *
 * Surfaces the user's own membership status, sacraments, leadership
 * positions, family, pledges, giving history, attendance, and onboarding
 * milestones — all sourced from existing per-person services/models.
 */
class MyDashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if (!$this->canAccessMyDashboard($user)) {
            return redirect()->to('/profile')->with('error', lang('Auth.notEnoughPrivilege'));
        }

        // Resolve the user's canonical person_id and parish_id from profile.
        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();

        if (!$profile) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $personId = (int) ($profile['person_id'] ?? 0);
        $parishId = (int) ($profile['parish_id'] ?? 0);

        // If the profile has no person_id, try to auto-repair the link.
        if ($personId <= 0) {
            $personId = $this->ensureProfilePersonLink((int) $profile['id'], (int) $user->id);
        }

        if ($personId <= 0 || $parishId <= 0) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $service = new MemberDashboardDataService();
        $data    = $service->assemble((int) $user->id, $personId, $parishId);

        $viewData = array_merge($this->getCommonViewData(lang('Dashboard.myDashboard')), $data);

        return view('MemberPortal\Views\MyDashboard\index', $viewData);
    }

    /**
     * Attempt to ensure a profile has a person_id link.
     * Returns the resolved person_id (0 if unsuccessful).
     */
    private function ensureProfilePersonLink(int $profileId, int $userId): int
    {
        // Delegate to the same logic used in Clergy\Controllers\People::view
        // by calling the People controller's helper if available.
        // For now, try a direct lookup via user_person mapping.
        $db = db_connect();

        $personRow = $db->table('user_person')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($personRow) {
            $personId = (int) ($personRow['person_id'] ?? 0);
            if ($personId > 0) {
                $db->table('profile')->where('id', $profileId)->update(['person_id' => $personId]);
                return $personId;
            }
        }

        return 0;
    }

    /**
     * Download the member's annual giving statement as a PDF.
     */
    public function statement()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if (!$this->canAccessMyDashboard($user)) {
            return redirect()->to('/profile')->with('error', lang('Auth.notEnoughPrivilege'));
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
        if (!$profile) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $parishId = (int) ($profile['parish_id'] ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $requestedYear = (int) ($this->request->getGet('year') ?? date('Y'));
        $currentYear = (int) date('Y');
        if ($requestedYear < 2000 || $requestedYear > ($currentYear + 1)) {
            $requestedYear = $currentYear;
        }

        $startDate = sprintf('%04d-01-01', $requestedYear);
        $endDate = ($requestedYear === $currentYear) ? date('Y-m-d') : sprintf('%04d-12-31', $requestedYear);

        $donationModel = model('DonationModel');
        $rows = $donationModel->db->table('donation d')
            ->select('d.*, f.name as fund_name, f.fund_type')
            ->join('fund f', 'f.id = d.fund_id', 'left')
            ->where('d.parish_id', $parishId)
            ->where('d.user_id', (int) $user->id)
            ->where('d.deleted_at', null)
            ->where('d.date >=', $startDate)
            ->where('d.date <=', $endDate)
            ->orderBy('d.date', 'ASC')
            ->get()
            ->getResultArray();

        $donations = array_map(fn(array $row): array => $donationModel->decryptDonationRow($row), $rows);

        $totalAmount = array_reduce($donations, static function (float $carry, array $donation): float {
            return $carry + (float) ($donation['amount'] ?? 0);
        }, 0.0);

        $parish = model('ParishModel')->find($parishId);
        $parishName = (string) ($parish['name'] ?? lang('Treasurer.unknownParish'));

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildStatementPdfHtml($parishName, $profile, $user, $startDate, $endDate, $donations, $totalAmount));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('donation.statement_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'member_statement',
            'entity_id' => (int) $user->id,
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($donations),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'downloaded_by' => 'member',
                ]
            ),
        ]);

        $filename = 'giving_statement_' . $requestedYear . '_' . (int) $user->id . '_' . date('Ymd_His') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    /**
     * Display a receipt for the logged-in member's own donation.
     */
    public function receipt($donationId)
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if (!$this->canAccessMyDashboard($user)) {
            return redirect()->to('/profile')->with('error', lang('Auth.notEnoughPrivilege'));
        }

        $donationId = (int) $donationId;
        if ($donationId <= 0) {
            return redirect()->to('/me')->with('error', lang('Treasurer.donationNotFound'));
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
        if (!$profile) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $parishId = (int) ($profile['parish_id'] ?? 0);

        $donationModel = model('DonationModel');
        $donation = $donationModel->db->table('donation d')
            ->select('d.*, f.name as fund_name')
            ->join('fund f', 'f.id = d.fund_id', 'left')
            ->where('d.id', $donationId)
            ->where('d.deleted_at', null)
            ->get()
            ->getRowArray();

        if ($donation) {
            $donation = $donationModel->decryptDonationRow($donation);
        }

        if (empty($donation) || (int) ($donation['user_id'] ?? 0) !== (int) $user->id) {
            return redirect()->to('/me')->with('error', lang('Treasurer.donationNotFound'));
        }

        $receipt = model('DonationReceiptModel')->getForDonation($donationId);
        if (empty($receipt)) {
            return redirect()->to('/me')->with('error', lang('Dashboard.noReceiptIssued'));
        }

        $donor = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
        if ($donor === '') {
            $donor = (string) ($user->username ?? (lang('Finance.memberPrefix') . (int) $user->id));
        }

        $donationParishId = (int) ($donation['parish_id'] ?? $parishId);
        $parish = model('ParishModel')->find($donationParishId);

        $this->audit()->log('donation.view_receipt', [
            'parish_id' => $donationParishId,
            'entity_type' => 'donation_receipt',
            'entity_id' => (int) ($receipt['id'] ?? $donationId),
            'meta' => array_merge(
                $this->buildParishContextMeta($donationParishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'donation_id' => $donationId,
                    'receipt_number' => (string) ($receipt['receipt_number'] ?? ''),
                    'viewed_by' => 'member',
                ]
            ),
        ]);

        return view('MemberPortal\Views\MyDashboard\receipt', array_merge($this->getCommonViewData(lang('Dashboard.viewReceipt')), [
            'donation' => $donation,
            'receipt'  => $receipt,
            'donor'    => $donor,
            'parish'   => $parish,
        ]));
    }

    private function buildStatementPdfHtml(string $parishName, array $profile, object $user, string $startDate, string $endDate, array $donations, float $totalAmount): string
    {
        $memberName = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
        if ($memberName === '') {
            $memberName = (string) ($user->username ?? (lang('Finance.memberPrefix') . (int) $user->id));
        }
        $userEmail = (string) ($user->email ?? '-');

        $rows = '';
        foreach ($donations as $donation) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['payment_method'] ?? '-')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($donation['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noContributionsForPeriod')) . '</td></tr>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.contributionStatementTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #1f4b8f; margin-bottom: 4px; }
                h2 { color: #444; font-size: 16px; margin-top: 0; }
                .meta { margin-bottom: 18px; color: #555; }
                .totals { margin: 18px 0; padding: 14px; border: 1px solid #ddd; background: #fafafa; }
                .totals strong { color: #1f4b8f; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.contributionStatementTitle')) . '</h1>
            <h2>' . htmlspecialchars($parishName) . '</h2>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Finance.memberLabel')) . ':</strong> ' . htmlspecialchars($memberName) . '<br>
                <strong>' . htmlspecialchars(lang('App.colEmail')) . ':</strong> ' . htmlspecialchars($userEmail) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.statementPeriodLabel')) . ':</strong> ' . htmlspecialchars($startDate) . ' ' . htmlspecialchars(lang('Treasurer.reportTo')) . ' ' . htmlspecialchars($endDate) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '
            </div>
            <div class="totals">
                ' . htmlspecialchars(lang('Treasurer.totalContributionsLabel')) . ': <strong>$' . number_format($totalAmount, 2) . '</strong>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colPaymentMethod')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }

    private function mapDonationCategoryLabel(string $category): string
    {
        $map = [
            'tithe' => lang('Finance.categoryTithe'),
            'offering' => lang('Finance.categoryOffering'),
            'mission' => lang('Finance.categoryMission'),
            'building' => lang('Finance.categoryBuilding'),
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
