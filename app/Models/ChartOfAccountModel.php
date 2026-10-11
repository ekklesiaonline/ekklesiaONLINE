<?php

namespace App\Models;

use CodeIgniter\Model;

class ChartOfAccountModel extends Model
{
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_PREFIX = 'coa_';

    protected $table            = 'chart_of_accounts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['code', 'name', 'account_type', 'category', 'description', 'allow_budget', 'is_active', 'sort_order'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';


    protected function getCacheService()
    {
        return \Config\Services::cache();
    }

    private function trackCacheKey(string $key): void
    {
        $cache = $this->getCacheService();
        $registryKey = self::CACHE_PREFIX . 'keys';
        $keys = $cache->get($registryKey);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            $cache->save($registryKey, $keys, self::CACHE_TTL_SECONDS);
        }
    }

    private function rememberListResult(string $key, array $result): array
    {
        $cache = $this->getCacheService();
        $cache->save($key, $result, self::CACHE_TTL_SECONDS);
        $this->trackCacheKey($key);
        return $result;
    }

    private function invalidateListCaches(): void
    {
        $cache = $this->getCacheService();
        $registryKey = self::CACHE_PREFIX . 'keys';
        $keys = $cache->get($registryKey);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    $cache->delete($key);
                }
            }
        }
        $cache->delete($registryKey);
    }

    public function getActiveByType(?string $type = null): array
    {
        $cacheKey = self::CACHE_PREFIX . 'active_type_' . ($type ?? 'all');
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $builder = $this->where('is_active', 1);

        if ($type !== null) {
            $builder->where('account_type', $type);
        }

        $result = $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
        return $this->rememberListResult($cacheKey, $result);
    }

    public function getBudgetableAccounts(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'budgetable';
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->where('is_active', 1)
            ->where('allow_budget', 1)
            ->whereIn('account_type', ['income', 'expense'])
            ->orderBy('account_type', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();

        return $this->rememberListResult($cacheKey, $result);
    }

    public function getLayStaffAccounts(bool $budgetableOnly = true): array
    {
        $builder = $this->where('is_active', 1)
            ->where('account_type', 'expense')
            ->whereIn('code', ['7150', '7151', '7152', '7153', '7154', '7155', '7156', '7157', '7158']);

        if ($budgetableOnly) {
            $builder->where('allow_budget', 1);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    public function getFundTransferAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'income')
            ->whereIn('code', ['5400', '5451', '5452', '5453', '5454', '5455', '5456'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getPayrollOverheadAccounts(bool $budgetableOnly = true): array
    {
        $builder = $this->where('is_active', 1)
            ->where('account_type', 'expense')
            ->whereIn('code', ['7500', '7510', '7520', '7530']);

        if ($budgetableOnly) {
            $builder->where('allow_budget', 1);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    public function getBankingDetailAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'asset')
            ->whereIn('code', ['1115', '1141', '1142', '1143', '1150'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getGovernanceExpenseAccounts(bool $budgetableOnly = true): array
    {
        $builder = $this->where('is_active', 1)
            ->where('account_type', 'expense')
            ->whereIn('code', ['7600', '7610', '7620', '7630']);

        if ($budgetableOnly) {
            $builder->where('allow_budget', 1);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    public function getFormationSplitAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'expense')
            ->whereIn('code', ['7312', '7313'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getUtilityItemizedAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'expense')
            ->whereIn('code', ['8113', '8114', '8115', '8116'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getFacilityLiabilityAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'liability')
            ->whereIn('code', ['2250', '2260'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getGrantTrackingAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'income')
            ->whereIn('code', ['5500', '5510', '5520', '5530', '5540'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getDiocesanOfferingAccounts(): array
    {
        return $this->where('is_active', 1)
            ->where('account_type', 'income')
            ->whereIn('code', ['5240', '5241', '5242', '5243'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    public function getGroupedActiveAccountsForType(string $type): array
    {
        $cacheKey = self::CACHE_PREFIX . 'grouped_type_' . $type;
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $groups = [];
        $accounts = $this->getActiveByType($type);

        foreach ($accounts as $account) {
            // Hide structural heading rows from transaction dropdowns.
            if (empty($account['category'])) {
                continue;
            }

            $label = $this->resolveSectionLabel((string) $account['code'], $type);
            $groups[$label][] = $account;
        }

        return $this->rememberListResult($cacheKey, $groups);
    }

    public function insert($data = null, bool $returnID = true)
    {
        $result = parent::insert($data, $returnID);
        if ($result !== false) {
            $this->invalidateListCaches();
        }
        return $result;
    }

    public function update($id = null, $data = null): bool
    {
        $result = parent::update($id, $data);
        if ($result) {
            $this->invalidateListCaches();
        }
        return $result;
    }

    public function delete($id = null, bool $purge = false)
    {
        $result = parent::delete($id, $purge);
        if ($result !== false) {
            $this->invalidateListCaches();
        }
        return $result;
    }

    protected function resolveSectionLabel(string $code, string $type): string
    {
        preg_match('/^\d+/', $code, $matches);
        $numericCode = isset($matches[0]) ? (int) $matches[0] : 0;

        if ($type === 'income') {
            if ($numericCode >= 4000 && $numericCode <= 4999) {
                return 'Unrestricted Income (4000s)';
            }

            if ($numericCode >= 5000 && $numericCode <= 5399) {
                return 'Restricted and Designated Income (5000s)';
            }

            if ($numericCode >= 5400 && $numericCode <= 5499) {
                return 'Fund Transfers (5400s)';
            }

            if ($numericCode >= 5500 && $numericCode <= 5599) {
                return 'Grant Tracking (5500s)';
            }

            return 'Other Income';
        }

        if ($type === 'expense') {
            if ($numericCode >= 6000 && $numericCode <= 6999) {
                return 'Outreach Expenses (6000s)';
            }

            if ($numericCode >= 7000 && $numericCode <= 7499) {
                return 'Ministry and Program Expenses (7000s)';
            }

            if ($numericCode >= 7500 && $numericCode <= 7599) {
                return 'Payroll Overhead (7500s)';
            }

            if ($numericCode >= 7600 && $numericCode <= 7699) {
                return 'Governance Expenses (7600s)';
            }

            if ($numericCode >= 8000 && $numericCode <= 8999) {
                return 'Operations and Facilities (8000s)';
            }

            return 'Other Expenses';
        }

        return 'Other';
    }
}
