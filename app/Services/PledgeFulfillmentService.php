<?php

namespace App\Services;

use App\Models\AnnualPledgeModel;
use App\Models\DonationModel;
use CodeIgniter\Database\BaseConnection;

/**
 * PledgeFulfillmentService
 *
 * Handles automated calculation, synchronization, and state management for
 * annual pledge fulfillment based on member donations categorized as tithe or pledge.
 */
class PledgeFulfillmentService
{
    private const TITHE_OR_PLEDGE_CATEGORIES = ['tithe', 'pledge', 'tithes', 'pledges'];
    private const PLEDGE_CHART_ACCOUNT_CODES = ['4120'];

    private BaseConnection $db;
    private AnnualPledgeModel $pledgeModel;
    private DonationModel $donationModel;
    private AuditService $auditService;

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $chartAccountCache = [];

    public function __construct(
        ?BaseConnection $db = null,
        ?AnnualPledgeModel $pledgeModel = null,
        ?DonationModel $donationModel = null,
        ?AuditService $auditService = null
    ) {
        $this->db            = $db ?? db_connect();
        $this->pledgeModel   = $pledgeModel ?? model(AnnualPledgeModel::class);
        $this->donationModel = $donationModel ?? model(DonationModel::class);
        $this->auditService  = $auditService ?? service('audit');
    }

    /**
     * Determines whether a given donation category and/or chart account represents a tithe or pledge.
     */
    public function isTitheOrPledge(string $category, ?int $chartAccountId = null): bool
    {
        $normalizedCategory = strtolower(trim($category));
        if (in_array($normalizedCategory, self::TITHE_OR_PLEDGE_CATEGORIES, true)) {
            return true;
        }

        if ($chartAccountId !== null && $chartAccountId > 0) {
            $account = $this->getChartAccount($chartAccountId);
            if ($account !== null) {
                $accCategory = strtolower(trim((string) ($account['category'] ?? '')));
                if (in_array($accCategory, self::TITHE_OR_PLEDGE_CATEGORIES, true)) {
                    return true;
                }

                $code = trim((string) ($account['code'] ?? ''));
                if (in_array($code, self::PLEDGE_CHART_ACCOUNT_CODES, true)) {
                    return true;
                }

                $name = strtolower(trim((string) ($account['name'] ?? '')));
                if (str_contains($name, 'tithe') || str_contains($name, 'pledge')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recalculates and updates the annual_pledge.fulfilled_amount and fulfillment_status
     * for a given member, parish, and year based on all active matching tithe/pledge donations.
     *
     * @return array<string, mixed>|null The updated pledge record, or null if no pledge exists.
     */
    public function recalculatePledge(int $userId, int $parishId, int $year): ?array
    {
        if ($userId <= 0 || $parishId <= 0 || $year <= 0) {
            return null;
        }

        if (!$this->db->tableExists('annual_pledge') || !$this->db->tableExists('donation')) {
            return null;
        }

        $pledge = $this->pledgeModel
            ->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('pledge_year', $year)
            ->first();

        if ($pledge === null) {
            return null;
        }

        $startDate = sprintf('%04d-01-01', $year);
        $endDate   = sprintf('%04d-12-31', $year);

        $builder = $this->db->table('donation')
            ->select('id, amount, category, chart_account_id, date')
            ->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        if ($this->db->fieldExists('deleted_at', 'donation')) {
            $builder->where('deleted_at', null);
        }

        $donations = $builder->get()->getResultArray();

        $totalFulfilled = 0.0;
        foreach ($donations as $donation) {
            $cat = (string) ($donation['category'] ?? '');
            $coaId = isset($donation['chart_account_id']) && is_numeric($donation['chart_account_id'])
                ? (int) $donation['chart_account_id']
                : null;

            if ($this->isTitheOrPledge($cat, $coaId)) {
                $totalFulfilled += $this->donationModel->normalizeAmount($donation['amount'] ?? null);
            }
        }

        $totalFulfilled = round($totalFulfilled, 2);

        $currentStatus = (string) ($pledge['fulfillment_status'] ?? 'pledged');
        $newStatus     = $currentStatus;

        // If explicitly cancelled by administrator, respect cancelled status
        if ($currentStatus !== 'cancelled') {
            $pledgedAmount = (float) ($pledge['pledged_amount'] ?? 0);
            if ($pledgedAmount > 0 && $totalFulfilled >= $pledgedAmount) {
                $newStatus = 'fulfilled';
            } elseif ($totalFulfilled > 0) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'pledged';
            }
        }

        $oldFulfilled = (float) ($pledge['fulfilled_amount'] ?? 0);
        $needsUpdate  = abs($oldFulfilled - $totalFulfilled) > 0.0001 || $newStatus !== $currentStatus;

        if ($needsUpdate) {
            $this->pledgeModel->update((int) $pledge['id'], [
                'fulfilled_amount'   => $totalFulfilled,
                'fulfillment_status' => $newStatus,
            ]);

            $this->auditService->log('annual_pledge.fulfillment_update', [
                'parish_id'   => $parishId,
                'entity_type' => 'annual_pledge',
                'entity_id'   => (int) $pledge['id'],
                'user_id'     => $userId,
                'pledge_year' => $year,
                'old_values'  => [
                    'fulfilled_amount'   => $oldFulfilled,
                    'fulfillment_status' => $currentStatus,
                ],
                'new_values'  => [
                    'fulfilled_amount'   => $totalFulfilled,
                    'fulfillment_status' => $newStatus,
                ],
            ]);

            $pledge['fulfilled_amount']   = $totalFulfilled;
            $pledge['fulfillment_status'] = $newStatus;
        }

        return $pledge;
    }

    /**
     * Automatically synchronize pledge fulfillment when a donation is created or updated.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed>|null $previous
     */
    public function syncForDonation(array $current, ?array $previous = null): void
    {
        $currentUserId   = isset($current['user_id']) && is_numeric($current['user_id']) ? (int) $current['user_id'] : 0;
        $currentParishId = isset($current['parish_id']) && is_numeric($current['parish_id']) ? (int) $current['parish_id'] : 0;
        $currentYear     = $this->extractYear((string) ($current['date'] ?? ''));

        if ($currentUserId > 0 && $currentParishId > 0 && $currentYear > 0) {
            $this->recalculatePledge($currentUserId, $currentParishId, $currentYear);
        }

        if ($previous !== null) {
            $previousUserId   = isset($previous['user_id']) && is_numeric($previous['user_id']) ? (int) $previous['user_id'] : 0;
            $previousParishId = isset($previous['parish_id']) && is_numeric($previous['parish_id']) ? (int) $previous['parish_id'] : 0;
            $previousYear     = $this->extractYear((string) ($previous['date'] ?? ''));

            if ($previousUserId > 0 && $previousParishId > 0 && $previousYear > 0) {
                $isDifferentTarget = $previousUserId !== $currentUserId
                    || $previousParishId !== $currentParishId
                    || $previousYear !== $currentYear;

                if ($isDifferentTarget) {
                    $this->recalculatePledge($previousUserId, $previousParishId, $previousYear);
                }
            }
        }
    }

    /**
     * Automatically synchronize pledge fulfillment when a donation is deleted.
     *
     * @param array<string, mixed> $deleted
     */
    public function syncForDonationDeleted(array $deleted): void
    {
        $userId   = isset($deleted['user_id']) && is_numeric($deleted['user_id']) ? (int) $deleted['user_id'] : 0;
        $parishId = isset($deleted['parish_id']) && is_numeric($deleted['parish_id']) ? (int) $deleted['parish_id'] : 0;
        $year     = $this->extractYear((string) ($deleted['date'] ?? ''));

        if ($userId > 0 && $parishId > 0 && $year > 0) {
            $this->recalculatePledge($userId, $parishId, $year);
        }
    }

    /**
     * Synchronize all annual pledges for a specific parish (optionally scoped to a year).
     *
     * @return array{total_checked: int, updated: int, unchanged: int}
     */
    public function syncParishPledges(int $parishId, ?int $year = null, bool $dryRun = false): array
    {
        if ($parishId <= 0 || !$this->db->tableExists('annual_pledge')) {
            return ['total_checked' => 0, 'updated' => 0, 'unchanged' => 0];
        }

        $builder = $this->db->table('annual_pledge')
            ->select('id, user_id, parish_id, pledge_year, pledged_amount, fulfilled_amount, fulfillment_status')
            ->where('parish_id', $parishId);

        if ($year !== null && $year > 0) {
            $builder->where('pledge_year', $year);
        }

        $pledges = $builder->get()->getResultArray();

        $totalChecked = count($pledges);
        $updatedCount = 0;
        $unchangedCount = 0;

        foreach ($pledges as $pledge) {
            $uid = (int) ($pledge['user_id'] ?? 0);
            $pYear = (int) ($pledge['pledge_year'] ?? 0);

            if ($uid <= 0 || $pYear <= 0) {
                $unchangedCount++;
                continue;
            }

            if ($dryRun) {
                // Compute without writing
                $simulated = $this->simulatePledgeCalculation($pledge);
                if ($simulated['needs_update']) {
                    $updatedCount++;
                } else {
                    $unchangedCount++;
                }
            } else {
                $result = $this->recalculatePledge($uid, $parishId, $pYear);
                $wasUpdated = $result !== null && (
                    abs((float) ($pledge['fulfilled_amount'] ?? 0) - (float) ($result['fulfilled_amount'] ?? 0)) > 0.0001
                    || ($pledge['fulfillment_status'] ?? '') !== ($result['fulfillment_status'] ?? '')
                );

                if ($wasUpdated) {
                    $updatedCount++;
                } else {
                    $unchangedCount++;
                }
            }
        }

        return [
            'total_checked' => $totalChecked,
            'updated'       => $updatedCount,
            'unchanged'     => $unchangedCount,
        ];
    }

    /**
     * Synchronize all annual pledges across all parishes.
     *
     * @return array{total_checked: int, updated: int, unchanged: int}
     */
    public function syncAllPledges(?int $year = null, bool $dryRun = false): array
    {
        if (!$this->db->tableExists('annual_pledge')) {
            return ['total_checked' => 0, 'updated' => 0, 'unchanged' => 0];
        }

        $parishes = $this->db->table('parish')->select('id')->get()->getResultArray();

        $overall = ['total_checked' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($parishes as $parish) {
            $pId = (int) ($parish['id'] ?? 0);
            if ($pId > 0) {
                $res = $this->syncParishPledges($pId, $year, $dryRun);
                $overall['total_checked'] += $res['total_checked'];
                $overall['updated']       += $res['updated'];
                $overall['unchanged']     += $res['unchanged'];
            }
        }

        return $overall;
    }

    /**
     * @param array<string, mixed> $pledge
     * @return array{expected_fulfilled: float, expected_status: string, needs_update: bool}
     */
    private function simulatePledgeCalculation(array $pledge): array
    {
        $userId   = (int) ($pledge['user_id'] ?? 0);
        $parishId = (int) ($pledge['parish_id'] ?? 0);
        $year     = (int) ($pledge['pledge_year'] ?? 0);

        $startDate = sprintf('%04d-01-01', $year);
        $endDate   = sprintf('%04d-12-31', $year);

        $builder = $this->db->table('donation')
            ->select('amount, category, chart_account_id')
            ->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        if ($this->db->fieldExists('deleted_at', 'donation')) {
            $builder->where('deleted_at', null);
        }

        $donations = $builder->get()->getResultArray();

        $totalFulfilled = 0.0;
        foreach ($donations as $donation) {
            $cat = (string) ($donation['category'] ?? '');
            $coaId = isset($donation['chart_account_id']) && is_numeric($donation['chart_account_id'])
                ? (int) $donation['chart_account_id']
                : null;

            if ($this->isTitheOrPledge($cat, $coaId)) {
                $totalFulfilled += $this->donationModel->normalizeAmount($donation['amount'] ?? null);
            }
        }

        $totalFulfilled = round($totalFulfilled, 2);

        $currentStatus = (string) ($pledge['fulfillment_status'] ?? 'pledged');
        $newStatus     = $currentStatus;

        if ($currentStatus !== 'cancelled') {
            $pledgedAmount = (float) ($pledge['pledged_amount'] ?? 0);
            if ($pledgedAmount > 0 && $totalFulfilled >= $pledgedAmount) {
                $newStatus = 'fulfilled';
            } elseif ($totalFulfilled > 0) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'pledged';
            }
        }

        $oldFulfilled = (float) ($pledge['fulfilled_amount'] ?? 0);
        $needsUpdate  = abs($oldFulfilled - $totalFulfilled) > 0.0001 || $newStatus !== $currentStatus;

        return [
            'expected_fulfilled' => $totalFulfilled,
            'expected_status'    => $newStatus,
            'needs_update'       => $needsUpdate,
        ];
    }

    private function extractYear(string $dateString): int
    {
        $trimmed = trim($dateString);
        if ($trimmed === '') {
            return 0;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return 0;
        }

        return (int) date('Y', $timestamp);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getChartAccount(int $chartAccountId): ?array
    {
        if (array_key_exists($chartAccountId, $this->chartAccountCache)) {
            return $this->chartAccountCache[$chartAccountId];
        }

        if (!$this->db->tableExists('chart_of_accounts')) {
            $this->chartAccountCache[$chartAccountId] = null;
            return null;
        }

        $account = $this->db->table('chart_of_accounts')
            ->where('id', $chartAccountId)
            ->get()
            ->getRowArray();

        $this->chartAccountCache[$chartAccountId] = is_array($account) ? $account : null;

        return $this->chartAccountCache[$chartAccountId];
    }
}
