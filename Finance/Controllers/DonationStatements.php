<?php

namespace Finance\Controllers;

/**
 * DonationStatements
 *
 * Member giving-statement, batch-statement, and donation-receipt endpoints.
 * Extracted from Donations (treasurer second pass); extends DonationExports
 * so the legacy Donation route alias resolves every donations surface.
 */
class DonationStatements extends DonationExports
{

    public function statement()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $memberUserId = (int) ($this->request->getGet('statement_member_id') ?? 0);
        $startDate = $this->normalizeDate($this->request->getGet('start_date')) ?? date('Y-01-01');
        $endDate = $this->normalizeDate($this->request->getGet('end_date')) ?? date('Y-m-d');

        if ($memberUserId <= 0) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.selectMemberForStatement'));
        }

        $member = $this->getParishMemberProfile($parishId, $memberUserId);
        if ($member === null) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.memberOutsideParishScope'));
        }

        $result = $this->getStatementService()->generateSingleStatementPdf($parishId, $memberUserId, $startDate, $endDate);
        if ($result === null) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.noContributionsForPeriod'));
        }

        $statementAudit = [
            'count'        => $result['count'],
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'total_amount' => number_format((float) $result['total_amount'], 2, '.', ''),
        ];

        $this->audit()->log('donation.statement_pdf', [
            'parish_id'   => $parishId,
            'entity_type' => 'member_statement',
            'entity_id'   => $memberUserId,
            'new_values'  => $statementAudit,
            'meta'        => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                $statementAudit
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['content']);
    }


    /**
     * Batch export annual member giving statements for a fiscal year
     * as a single combined PDF or ZIP archive.
     */
    public function batchStatements()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $rawYear = $this->request->getGet('year') ?? $this->request->getPost('year');
        $year = $rawYear !== null && $rawYear !== '' ? (int) $rawYear : (int) date('Y');
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $rawFormat = $this->request->getGet('format') ?? $this->request->getPost('format') ?? 'pdf';
        $format = strtolower(trim((string) $rawFormat));
        if (! in_array($format, ['pdf', 'zip'], true)) {
            $format = 'pdf';
        }

        $rawMin = $this->request->getGet('min_amount') ?? $this->request->getPost('min_amount');
        $minAmount = $rawMin !== null && $rawMin !== '' ? (float) $rawMin : 0.01;
        if ($minAmount < 0) {
            $minAmount = 0.0;
        }

        $service = $this->getStatementService();

        if ($format === 'zip') {
            $result = $service->generateZipArchive($parishId, $year, $minAmount);
            $contentType = 'application/zip';
        } else {
            $result = $service->generateCombinedPdf($parishId, $year, $minAmount);
            $contentType = 'application/pdf';
        }

        if ($result === null || $result['count'] === 0) {
            return redirect()->to('/donations')->with('error', lang('Finance.noDonorsFoundForYear', [$year]));
        }

        $batchAudit = [
            'year'         => $year,
            'format'       => $format,
            'donor_count'  => $result['count'],
            'min_amount'   => $minAmount,
            'total_amount' => number_format((float) $result['total_amount'], 2, '.', ''),
        ];

        $this->audit()->log('donation.batch_statements', [
            'parish_id'   => $parishId,
            'entity_type' => 'batch_giving_statements',
            'entity_id'   => $year,
            'new_values'  => $batchAudit,
            'meta'        => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                $batchAudit
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['content']);
    }


    /**
     * Display receipt for a donation.
     */
    public function receipt($donationId)
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $donationId = (int) $donationId;

        $donation = $this->donationModel->find($donationId);
        if (empty($donation) || (int) ($donation['parish_id'] ?? 0) !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.donationNotFound')], 404);
        }

        $receipt = $this->donationModel->getReceipt($donationId);
        if (empty($receipt)) {
            return redirect()->to('/donations')->with('error', 'No receipt has been issued for this donation.');
        }

        $donor = $this->formatDonorName($donation);

        return view('Finance\Views\Donations/receipt', array_merge($this->getCommonViewData('Donation Receipt'), [
            'donation' => $donation,
            'receipt'  => $receipt,
            'donor'    => $donor,
            'parish'   => model('ParishModel')->find($parishId),
        ]));
    }
}
