<?php

namespace App\Models;

use App\Libraries\DataEncryption;

class DonationModel extends BaseTenantModel
{
    protected $table            = 'donation';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'fund_id',
        'user_id',
        'amount',
        'date',
        'category',
        'category_id',
        'chart_account_id',
        'payment_method',
        'notes',
        'is_restricted',
        'restriction_purpose',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id'    => 'required|is_not_unique[parish.id]',
        'fund_id'      => 'permit_empty|is_not_unique[fund.id]',
        'amount'       => 'required|decimal|greater_than[0]',
        'date'         => 'required|valid_date',
        'category'     => 'required|max_length[100]',
        'chart_account_id' => 'permit_empty|is_not_unique[chart_of_accounts.id]',
        'is_restricted'    => 'permit_empty|in_list[0,1]',
        'restriction_purpose' => 'permit_empty|max_length[255]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['encryptSensitiveFields'];
    protected $beforeUpdate = ['encryptSensitiveFields', 'captureBeforeUpdate'];
    protected $beforeDelete = ['captureBeforeDelete'];
    protected $afterInsert  = ['syncPledgeFulfillmentAfterInsert'];
    protected $afterUpdate  = ['syncPledgeFulfillmentAfterUpdate'];
    protected $afterDelete  = ['syncPledgeFulfillmentAfterDelete'];
    protected $afterFind    = ['decryptSensitiveFields'];

    /**
     * Get parish donations
     */
    public function getParishDonations($parishId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('donation d')
            ->select('d.*, p.first_name, p.last_name, f.name as fund_name, f.fund_type')
            ->join('profile p', 'p.user_id = d.user_id', 'left')
            ->join('fund f', 'f.id = d.fund_id', 'left')
            ->where('d.parish_id', $parishId)
            ->where('d.deleted_at', null);

        if ($startDate) {
            $builder->where('d.date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('d.date <=', $endDate);
        }

        $rows = $builder->orderBy('d.date', 'DESC')->get()->getResultArray();

        return array_map(fn(array $row): array => $this->decryptDonationRow($row), $rows);
    }

    /**
     * Get donation summary by category
     */
    public function getDonationsByCategory($parishId, $startDate, $endDate)
    {
        $rows = $this->db->table('donation')
            ->select('category, amount')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getResultArray();

        $summary = [];
        foreach ($rows as $row) {
            $category = (string) ($row['category'] ?? 'other');
            if (!isset($summary[$category])) {
                $summary[$category] = ['category' => $category, 'total' => 0.0, 'count' => 0];
            }

            $summary[$category]['total'] += $this->normalizeAmount($row['amount'] ?? null);
            $summary[$category]['count']++;
        }

        return array_values($summary);
    }

    /**
     * Get total donations for period
     */
    public function getTotalDonations($parishId, $startDate, $endDate)
    {
        $rows = $this->db->table('donation')
            ->select('amount')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getResultArray();

        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->normalizeAmount($row['amount'] ?? null);
        }

        return $total;
    }

    public function getDonationsByFund($parishId, $startDate, $endDate): array
    {
        $rows = $this->db->table('donation d')
            ->select('f.id as fund_id, f.name as fund_name, f.fund_type, d.amount')
            ->join('fund f', 'f.id = d.fund_id', 'left')
            ->where('d.parish_id', $parishId)
            ->where('d.deleted_at', null)
            ->where('d.date >=', $startDate)
            ->where('d.date <=', $endDate)
            ->orderBy('f.name', 'ASC')
            ->get()
            ->getResultArray();

        $summary = [];
        foreach ($rows as $row) {
            $fundId = (int) ($row['fund_id'] ?? 0);
            $key = (string) $fundId;
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'fund_id' => $fundId,
                    'fund_name' => $row['fund_name'] ?? null,
                    'fund_type' => $row['fund_type'] ?? null,
                    'total' => 0.0,
                    'count' => 0,
                ];
            }

            $summary[$key]['total'] += $this->normalizeAmount($row['amount'] ?? null);
            $summary[$key]['count']++;
        }

        return array_values($summary);
    }

    /**
     * Get member's giving history
     */
    public function getMemberDonations($userId, $limit = 10)
    {
        return $this->where('user_id', $userId)
            ->orderBy('date', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get a member's donations scoped to a parish.
     */
    public function getMemberDonationsForParish(int $userId, int $parishId, int $limit = 25): array
    {
        $builder = $this->db->table('donation d')
            ->select('d.*, f.name as fund_name, f.fund_type')
            ->join('fund f', 'f.id = d.fund_id', 'left')
            ->where('d.user_id', $userId)
            ->where('d.parish_id', $parishId)
            ->where('d.deleted_at', null);

        $prefix = $this->db->getPrefix();
        if ($this->db->tableExists($prefix . 'donation_receipts') || $this->db->tableExists('donation_receipts')) {
            $builder->select('r.receipt_number, r.id as receipt_id')
                ->join($prefix . 'donation_receipts r', 'r.donation_id = d.id AND r.deleted_at IS NULL', 'left', false);
        } else {
            $builder->select('NULL as receipt_number, NULL as receipt_id');
        }

        $rows = $builder->orderBy('d.date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(fn(array $row): array => $this->decryptDonationRow($row), $rows);
    }

    /**
     * Get donation totals for dashboard cards.
     */
    public function getParishSummary($parishId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('donation')
            ->select('category, amount')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null);

        if ($startDate) {
            $builder->where('date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('date <=', $endDate);
        }

        $rows = $builder->get()->getResultArray();

        $summary = [
            'total' => 0,
            'tithe' => 0,
            'offering' => 0,
            'other' => 0,
        ];

        foreach ($rows as $row) {
            $category = (string) ($row['category'] ?? 'other');
            $amount = $this->normalizeAmount($row['amount'] ?? null);
            $summary['total'] += $amount;

            if (isset($summary[$category])) {
                $summary[$category] += $amount;
            } else {
                $summary['other'] += $amount;
            }
        }

        return $summary;
    }

    /**
    * Get total donations across all parishes (ProvinceAdmin / DioceseAdmin)
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getAllParishesTotalDonations($startDate, $endDate, array $parishIds = [], bool $strictScope = true)
    {
        if ($strictScope && empty($parishIds)) {
            return 0.0;
        }

        $builder = $this->db->table('donation')
            ->select('amount')
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        if (!empty($parishIds)) {
            $builder->whereIn('parish_id', $parishIds);
        }

        $builder->where('deleted_at', null);

        $rows = $builder->get()->getResultArray();

        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->normalizeAmount($row['amount'] ?? null);
        }

        return $total;
    }

    /**
    * Get donations by parish for ProvinceAdmin / DioceseAdmin comparison
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getDonationsByParish($startDate, $endDate, array $parishIds = [], bool $strictScope = true)
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }

        $builder = $this->db->table('donation d')
            ->select('c.id as parish_id, c.name as parish_name, d.amount')
            ->join('parish c', 'c.id = d.parish_id')
            ->where('d.date >=', $startDate)
            ->where('d.date <=', $endDate);

        if (!empty($parishIds)) {
            $builder->whereIn('d.parish_id', $parishIds);
        }

        $builder->where('d.deleted_at', null);

        $rows = $builder->get()->getResultArray();

        $summary = [];
        foreach ($rows as $row) {
            $parishId = (int) ($row['parish_id'] ?? 0);
            $key = (string) $parishId;
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'parish_id' => $parishId,
                    'parish_name' => $row['parish_name'] ?? '',
                    'total_donations' => 0.0,
                    'donation_count' => 0,
                ];
            }

            $summary[$key]['total_donations'] += $this->normalizeAmount($row['amount'] ?? null);
            $summary[$key]['donation_count']++;
        }

        $grouped = array_values($summary);
        usort($grouped, static fn(array $a, array $b): int => ($b['total_donations'] <=> $a['total_donations']));

        return $grouped;
    }

    /**
     * Get monthly donation trend across parishes for the last N months.
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getNetworkMonthlyTrend(int $months = 12, array $parishIds = [], bool $strictScope = true): array
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }

        $startDate = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $builder = $this->db->table('donation')
            ->select('date, amount')
            ->where('date >=', $startDate);

        if (!empty($parishIds)) {
            $builder->whereIn('parish_id', $parishIds);
        }

        $builder->where('deleted_at', null);

        $rows = $builder->get()->getResultArray();
        $summary = [];
        foreach ($rows as $row) {
            $monthKey = date('Y-m', strtotime((string) $row['date']));
            if (!isset($summary[$monthKey])) {
                $summary[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_label' => date('M Y', strtotime($monthKey . '-01')),
                    'total_amount' => 0.0,
                ];
            }
            $summary[$monthKey]['total_amount'] += $this->normalizeAmount($row['amount'] ?? null);
        }

        ksort($summary);

        return array_values($summary);
    }

    /**
     * Get monthly donation trend grouped by parish for the last N months.
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getMonthlyTrendByParish(int $months = 12, array $parishIds = [], bool $strictScope = true): array
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }

        $startDate = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $builder = $this->db->table('donation d')
            ->select('d.parish_id, c.name as parish_name, d.date, d.amount')
            ->join('parish c', 'c.id = d.parish_id')
            ->where('d.date >=', $startDate);

        if (!empty($parishIds)) {
            $builder->whereIn('d.parish_id', $parishIds);
        }

        $builder->where('d.deleted_at', null);

        $rows = $builder->get()->getResultArray();
        $summary = [];
        foreach ($rows as $row) {
            $parishId = (int) ($row['parish_id'] ?? 0);
            $monthKey = date('Y-m', strtotime((string) $row['date']));
            $key = $parishId . '|' . $monthKey;
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'parish_id' => $parishId,
                    'parish_name' => $row['parish_name'] ?? '',
                    'month_key' => $monthKey,
                    'month_label' => date('M Y', strtotime($monthKey . '-01')),
                    'total_amount' => 0.0,
                ];
            }
            $summary[$key]['total_amount'] += $this->normalizeAmount($row['amount'] ?? null);
        }

        $grouped = array_values($summary);
        usort($grouped, static function (array $a, array $b): int {
            if ($a['month_key'] === $b['month_key']) {
                return ((int) $a['parish_id']) <=> ((int) $b['parish_id']);
            }

            return strcmp((string) $a['month_key'], (string) $b['month_key']);
        });

        return $grouped;
    }

    /**
     * Aggregate actual donation totals by chart account for one parish/date range.
     *
     * @return array<int,float>
     */
    public function getChartAccountTotalsForParish(int $parishId, string $startDate, string $endDate): array
    {
        $rows = $this->db->table('donation')
            ->select('chart_account_id, amount')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->where('chart_account_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        $totals = [];
        foreach ($rows as $row) {
            $accountId = (int) ($row['chart_account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            if (!isset($totals[$accountId])) {
                $totals[$accountId] = 0.0;
            }

            $totals[$accountId] += $this->normalizeAmount($row['amount'] ?? null);
        }

        return $totals;
    }

    protected function encryptSensitiveFields(array $payload): array
    {
        $fields = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if ($fields === []) {
            return $payload;
        }

        if (array_key_exists('amount', $fields)) {
            $plainAmount = $fields['amount'] !== null ? (string) $fields['amount'] : null;
            $fields['amount'] = DataEncryption::encrypt($plainAmount);
        }

        $payload['data'] = $fields;

        return $payload;
    }

    protected function decryptSensitiveFields(array $payload): array
    {
        if (!isset($payload['data'])) {
            return $payload;
        }

        if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
            $payload['data'] = array_map(fn(array $row): array => $this->decryptDonationRow($row), $payload['data']);
            return $payload;
        }

        if (is_array($payload['data'])) {
            $payload['data'] = $this->decryptDonationRow($payload['data']);
        }

        return $payload;
    }

    public function decryptDonationRow(array $row): array
    {
        if (array_key_exists('amount', $row)) {
            $row['amount'] = DataEncryption::decrypt($row['amount'] !== null ? (string) $row['amount'] : null);
        }

        return $row;
    }

    public function normalizeAmount(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $plain = DataEncryption::decrypt((string) $value);
        if ($plain === null || $plain === '') {
            return 0.0;
        }

        return (float) $plain;
    }

    public function hasReceipt(int $donationId): bool
    {
        return model('DonationReceiptModel')->getForDonation($donationId) !== null;
    }

    public function getReceipt(int $donationId): ?array
    {
        return model('DonationReceiptModel')->getForDonation($donationId);
    }

    public function issueReceipt(int $donationId): ?array
    {
        $donation = $this->find($donationId);
        if (empty($donation)) {
            return null;
        }

        $parishId = (int) ($donation['parish_id'] ?? 0);
        $issuedBy = auth()->loggedIn() ? (int) auth()->id() : 0;

        if ($parishId <= 0 || $issuedBy <= 0) {
            return null;
        }

        return model('DonationReceiptModel')->issueForDonation($donationId, $parishId, $issuedBy);
    }

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $pendingUpdateSnapshots = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $pendingDeleteSnapshots = [];

    protected function captureBeforeUpdate(array $data): array
    {
        $ids = is_array($data['id'] ?? null) ? $data['id'] : (isset($data['id']) ? [$data['id']] : []);
        foreach ($ids as $id) {
            $row = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
            if ($row) {
                $this->pendingUpdateSnapshots[$id] = $row;
            }
        }

        return $data;
    }

    protected function captureBeforeDelete(array $data): array
    {
        $ids = is_array($data['id'] ?? null) ? $data['id'] : (isset($data['id']) ? [$data['id']] : []);
        foreach ($ids as $id) {
            $row = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
            if ($row) {
                $this->pendingDeleteSnapshots[$id] = $row;
            }
        }

        return $data;
    }

    protected function syncPledgeFulfillmentAfterInsert(array $data): array
    {
        if (!empty($data['result']) && !empty($data['id'])) {
            $id = $data['id'];
            $inserted = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
            if ($inserted) {
                try {
                    service('pledgeFulfillment')->syncForDonation($inserted);
                } catch (\Throwable $e) {
                    log_message('error', 'Pledge fulfillment sync error on insert: {error}', ['error' => $e->getMessage()]);
                }
            }
        }

        return $data;
    }

    protected function syncPledgeFulfillmentAfterUpdate(array $data): array
    {
        if (!empty($data['result'])) {
            $ids = is_array($data['id'] ?? null) ? $data['id'] : (isset($data['id']) ? [$data['id']] : []);
            foreach ($ids as $id) {
                $previous = $this->pendingUpdateSnapshots[$id] ?? null;
                unset($this->pendingUpdateSnapshots[$id]);
                $current = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
                if ($current) {
                    try {
                        service('pledgeFulfillment')->syncForDonation($current, $previous);
                    } catch (\Throwable $e) {
                        log_message('error', 'Pledge fulfillment sync error on update: {error}', ['error' => $e->getMessage()]);
                    }
                } elseif ($previous) {
                    try {
                        service('pledgeFulfillment')->syncForDonationDeleted($previous);
                    } catch (\Throwable $e) {
                        log_message('error', 'Pledge fulfillment sync error on delete: {error}', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        return $data;
    }

    protected function syncPledgeFulfillmentAfterDelete(array $data): array
    {
        if (!empty($data['result'])) {
            $ids = is_array($data['id'] ?? null) ? $data['id'] : (isset($data['id']) ? [$data['id']] : []);
            foreach ($ids as $id) {
                $previous = $this->pendingDeleteSnapshots[$id] ?? null;
                unset($this->pendingDeleteSnapshots[$id]);
                if ($previous) {
                    try {
                        service('pledgeFulfillment')->syncForDonationDeleted($previous);
                    } catch (\Throwable $e) {
                        log_message('error', 'Pledge fulfillment sync error on delete: {error}', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        return $data;
    }
}
