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
    protected $allowedFields    = [
        'code',
        'name',
        'account_type',
        'category',
        'category_id',
        'description',
        'allow_budget',
        'is_active',
        'sort_order',
        'scope_type',
        'scope_id',
        'parent_account_id',
    ];

    protected array $casts = [
        'id'                => '?int',
        'allow_budget'      => '?bool',
        'is_active'         => '?bool',
        'sort_order'        => '?int',
        'scope_id'          => '?int',
        'parent_account_id' => '?int',
        'category_id'       => '?int',
    ];

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

    /**
     * Resolves active accounts inherited by a specific parish:
     * - System (TEC Master) accounts
     * - Diocese accounts for the parish's diocese
     * - Parish-specific accounts
     */
    public function getActiveForParish(int $parishId, ?int $dioceseId = null, ?string $type = null): array
    {
        if ($dioceseId === null && $parishId > 0) {
            $parish = $this->db->table('parish')->select('diocese_id')->where('id', $parishId)->get()->getRowArray();
            if ($parish && !empty($parish['diocese_id'])) {
                $dioceseId = (int) $parish['diocese_id'];
            }
        }

        $cacheKey = self::CACHE_PREFIX . 'parish_' . $parishId . '_d' . ($dioceseId ?? 0) . '_' . ($type ?? 'all');
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $builder = $this->where('is_active', 1);

        $builder->groupStart()
            ->where('scope_type', 'system')
            ->orGroupStart()
                ->where('scope_type', 'diocese')
                ->where('scope_id', (int) ($dioceseId ?? 0))
            ->groupEnd()
            ->orGroupStart()
                ->where('scope_type', 'parish')
                ->where('scope_id', $parishId)
            ->groupEnd()
        ->groupEnd();

        if ($type !== null) {
            $builder->where('account_type', $type);
        }

        $result = $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
        return $this->rememberListResult($cacheKey, $result);
    }

    /**
     * Resolves active accounts inherited by a specific diocese:
     * - System (TEC Master) accounts
     * - Diocese-specific accounts
     */
    public function getActiveForDiocese(int $dioceseId, ?string $type = null): array
    {
        $cacheKey = self::CACHE_PREFIX . 'diocese_' . $dioceseId . '_' . ($type ?? 'all');
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $builder = $this->where('is_active', 1);

        $builder->groupStart()
            ->where('scope_type', 'system')
            ->orGroupStart()
                ->where('scope_type', 'diocese')
                ->where('scope_id', $dioceseId)
            ->groupEnd()
        ->groupEnd();

        if ($type !== null) {
            $builder->where('account_type', $type);
        }

        $result = $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
        return $this->rememberListResult($cacheKey, $result);
    }

    /**
     * Resolves System (TEC Master) active accounts.
     */
    public function getActiveForSystem(?string $type = null): array
    {
        $cacheKey = self::CACHE_PREFIX . 'system_' . ($type ?? 'all');
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $builder = $this->where('is_active', 1)->where('scope_type', 'system');

        if ($type !== null) {
            $builder->where('account_type', $type);
        }

        $result = $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
        return $this->rememberListResult($cacheKey, $result);
    }

    public function getActiveByType(?string $type = null, ?int $parishId = null, ?int $dioceseId = null): array
    {
        if ($parishId !== null && $parishId > 0) {
            return $this->getActiveForParish($parishId, $dioceseId, $type);
        }

        if ($dioceseId !== null && $dioceseId > 0) {
            return $this->getActiveForDiocese($dioceseId, $type);
        }

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

    public function getGroupedActiveAccountsForType(string $type, ?int $parishId = null, ?int $dioceseId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . 'grouped_type_' . $type . '_p' . ($parishId ?? 0) . '_d' . ($dioceseId ?? 0);
        $cached = $this->getCacheService()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $groups = [];
        $accounts = $this->getActiveByType($type, $parishId, $dioceseId);

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

    public function isSystemAccount(array $account): bool
    {
        return ($account['scope_type'] ?? 'system') === 'system';
    }

    public function canMutateAccount(array $account, string $scopeType, int $scopeId): bool
    {
        if (($account['scope_type'] ?? 'system') === 'system') {
            return false;
        }

        return ($account['scope_type'] ?? '') === $scopeType && (int) ($account['scope_id'] ?? 0) === $scopeId;
    }

    /**
     * Resolves system (TEC Master) accounts.
     */
    public function getSystemAccounts(?string $type = null, bool $activeOnly = true): array
    {
        $builder = $this->where('scope_type', 'system');

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        if ($type !== null) {
            $builder->where('account_type', $type);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    /**
     * Checks whether a code already exists within the system scope.
     */
    public function systemCodeExists(string $code, ?int $excludeId = null): bool
    {
        $builder = $this->where('scope_type', 'system')->where('code', $code);

        if ($excludeId !== null && $excludeId > 0) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->first() !== null;
    }

    /**
     * Determines whether a system account can be mutated by a system administrator.
     */
    public function canMutateSystemAccount(array $account): bool
    {
        return ($account['scope_type'] ?? '') === 'system';
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
