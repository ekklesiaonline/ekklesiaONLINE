<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ChartAccountCategoryService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public static function definitions(): array
    {
        return [
            ['asset', null, 'asset', false],
            ['asset.current', 'asset', 'asset', false],
            ['asset.current.cash', 'asset.current', 'asset', true],
            ['asset.current.receivables', 'asset.current', 'asset', true],
            ['asset.current.prepaid', 'asset.current', 'asset', true],
            ['asset.investments', 'asset', 'asset', true],
            ['asset.fixed', 'asset', 'asset', false],
            ['asset.fixed.land_buildings', 'asset.fixed', 'asset', true],
            ['asset.fixed.equipment', 'asset.fixed', 'asset', true],
            ['asset.fixed.construction', 'asset.fixed', 'asset', true],
            ['asset.contra', 'asset', 'asset', true],
            ['liability', null, 'liability', false],
            ['liability.current', 'liability', 'liability', false],
            ['liability.current.payables', 'liability.current', 'liability', true],
            ['liability.current.payroll', 'liability.current', 'liability', true],
            ['liability.current.deferred', 'liability.current', 'liability', true],
            ['liability.long_term', 'liability', 'liability', false],
            ['liability.long_term.debt', 'liability.long_term', 'liability', true],
            ['equity', null, 'equity', false],
            ['equity.without_restrictions', 'equity', 'equity', false],
            ['equity.without_restrictions.undesignated', 'equity.without_restrictions', 'equity', true],
            ['equity.without_restrictions.board_designated', 'equity.without_restrictions', 'equity', true],
            ['equity.without_restrictions.plant', 'equity.without_restrictions', 'equity', true],
            ['equity.with_restrictions', 'equity', 'equity', false],
            ['equity.with_restrictions.purpose_time', 'equity.with_restrictions', 'equity', true],
            ['equity.with_restrictions.perpetual', 'equity.with_restrictions', 'equity', true],
            ['income', null, 'income', false],
            ['income.without_restrictions', 'income', 'income', false],
            ['income.without_restrictions.contributions', 'income.without_restrictions', 'income', true],
            ['income.without_restrictions.rental', 'income.without_restrictions', 'income', true],
            ['income.without_restrictions.fundraising', 'income.without_restrictions', 'income', true],
            ['income.without_restrictions.investment', 'income.without_restrictions', 'income', true],
            ['income.without_restrictions.grants', 'income.without_restrictions', 'income', true],
            ['income.without_restrictions.other', 'income.without_restrictions', 'income', true],
            ['income.with_restrictions', 'income', 'income', false],
            ['income.with_restrictions.capital', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.designated', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.grants', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.endowment', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.transfer', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.investment_gain', 'income.with_restrictions', 'income', true],
            ['income.with_restrictions.release', 'income.with_restrictions', 'income', true],
            ['expense', null, 'expense', false],
            ['expense.program', 'expense', 'expense', false],
            ['expense.program.diocesan', 'expense.program', 'expense', true],
            ['expense.program.outreach', 'expense.program', 'expense', true],
            ['expense.program.benevolence', 'expense.program', 'expense', true],
            ['expense.program.clergy', 'expense.program', 'expense', true],
            ['expense.program.worship', 'expense.program', 'expense', true],
            ['expense.program.formation', 'expense.program', 'expense', true],
            ['expense.management', 'expense', 'expense', false],
            ['expense.management.lay_personnel', 'expense.management', 'expense', true],
            ['expense.management.payroll', 'expense.management', 'expense', true],
            ['expense.management.governance', 'expense.management', 'expense', true],
            ['expense.management.utilities', 'expense.management', 'expense', true],
            ['expense.management.facilities', 'expense.management', 'expense', true],
            ['expense.management.insurance', 'expense.management', 'expense', true],
            ['expense.management.administration', 'expense.management', 'expense', true],
            ['expense.management.professional', 'expense.management', 'expense', true],
            ['expense.fundraising', 'expense', 'expense', true],
            ['expense.capital', 'expense', 'expense', false],
            ['expense.capital.expenditures', 'expense.capital', 'expense', true],
            ['expense.capital.debt_principal', 'expense.capital', 'expense', true],
            ['expense.capital.depreciation', 'expense.capital', 'expense', true],
        ];
    }

    public function seedDefaults(): void
    {
        $table = $this->db->table('chart_account_category');
        $ids = [];
        foreach (self::definitions() as $index => [$key, $parentKey, $type, $selectable]) {
            $existing = $table->where('category_key', $key)->get()->getRowArray();
            $data = [
                'parent_id' => $parentKey === null ? null : ($ids[$parentKey] ?? null),
                'account_type' => $type,
                'is_selectable' => $selectable ? 1 : 0,
                'is_active' => 1,
                'sort_order' => ($index + 1) * 10,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                $table->where('id', $existing['id'])->update($data);
                $ids[$key] = (int) $existing['id'];
                continue;
            }
            $data['category_key'] = $key;
            $data['created_at'] = date('Y-m-d H:i:s');
            $table->insert($data);
            $ids[$key] = (int) $this->db->insertID();
        }
    }

    public function all(): array
    {
        return $this->db->table('chart_account_category')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray();
    }

    public function selectableForType(string $type): array
    {
        return $this->db->table('chart_account_category')->where(['account_type' => $type, 'is_selectable' => 1, 'is_active' => 1])->orderBy('sort_order')->get()->getResultArray();
    }

    public function findSelectable(int $id, string $type): ?array
    {
        return $this->db->table('chart_account_category')->where(['id' => $id, 'account_type' => $type, 'is_selectable' => 1, 'is_active' => 1])->get()->getRowArray() ?: null;
    }

    public function path(int $id, ?array $all = null): string
    {
        $all ??= $this->all();
        $byId = array_column($all, null, 'id');
        $keys = [];
        while (isset($byId[$id])) {
            array_unshift($keys, (string) $byId[$id]['category_key']);
            $id = (int) ($byId[$id]['parent_id'] ?? 0);
        }
        return implode(' → ', array_map([$this, 'label'], $keys));
    }

    public function label(string $key): string
    {
        $labels = lang('ChartAccountCategories.category');
        return is_array($labels) && isset($labels[$key]) ? (string) $labels[$key] : ucwords(str_replace(['.', '_'], [' → ', ' '], $key));
    }

    public function suggestionKey(?array $parent, string $type, string $code): string
    {
        if (!empty($parent['category_id'])) {
            $category = $this->findSelectable((int) $parent['category_id'], $type);
            if ($category) {
                return (string) $category['category_key'];
            }
        }
        $number = (int) preg_replace('/\D.*$/', '', $code);
        if ($type === 'asset') {
            if (in_array($number, [1230, 1525, 1535, 1545], true)) return 'asset.contra';
            if ($number === 1550) return 'asset.fixed.construction';
            if ($number >= 1540) return 'asset.fixed.equipment';
            if ($number >= 1500) return 'asset.fixed.land_buildings';
            if ($number >= 1400) return 'asset.investments';
            if ($number >= 1300) return 'asset.current.prepaid';
            if ($number >= 1200) return 'asset.current.receivables';
            return 'asset.current.cash';
        }
        if ($type === 'liability') {
            if ($number >= 2400) return 'liability.long_term.debt';
            if ($number >= 2300) return 'liability.current.deferred';
            if ($number >= 2200) return 'liability.current.payroll';
            return 'liability.current.payables';
        }
        if ($type === 'equity') {
            if ($number >= 3400) return 'equity.with_restrictions.perpetual';
            if ($number >= 3300) return 'equity.with_restrictions.purpose_time';
            if ($number >= 3200) return 'equity.without_restrictions.board_designated';
            if ($number === 3120) return 'equity.without_restrictions.plant';
            return 'equity.without_restrictions.undesignated';
        }
        if ($type === 'income') {
            if ($number >= 5600) return 'income.with_restrictions.release';
            if (in_array($number, [5510, 5520], true)) return 'income.with_restrictions.investment_gain';
            if ($number >= 5500) return 'income.with_restrictions.grants';
            if ($number === 5410) return 'income.with_restrictions.endowment';
            if ($number >= 5400) return 'income.with_restrictions.transfer';
            if ($number >= 5300) return 'income.with_restrictions.grants';
            if ($number >= 5100) return $number === 5110 ? 'income.with_restrictions.capital' : 'income.with_restrictions.designated';
            if ($number >= 4600) return 'income.without_restrictions.other';
            if ($number >= 4500) return 'income.without_restrictions.grants';
            if ($number >= 4400) return 'income.without_restrictions.investment';
            if ($number >= 4300) return 'income.without_restrictions.fundraising';
            if ($number >= 4200) return 'income.without_restrictions.rental';
            return 'income.without_restrictions.contributions';
        }
        if ($type === 'expense') {
            if ($number >= 8700) return 'expense.capital.depreciation';
            if ($number === 8630) return 'expense.capital.debt_principal';
            if ($number >= 8600) return 'expense.capital.expenditures';
            if ($number >= 8500) return 'expense.management.professional';
            if ($number >= 8400) return 'expense.management.administration';
            if ($number >= 8300) return 'expense.management.insurance';
            if ($number >= 8200) return 'expense.management.facilities';
            if ($number >= 8100) return 'expense.management.utilities';
            if ($number >= 7600) return 'expense.management.governance';
            if ($number >= 7500) return 'expense.management.payroll';
            if ($number >= 7400) return 'expense.program.formation';
            if ($number >= 7300) return 'expense.program.worship';
            if ($number >= 7200) return 'expense.management.lay_personnel';
            if ($number >= 7100) return 'expense.program.clergy';
            if ($number === 6230) return 'expense.program.benevolence';
            if ($number >= 6200) return 'expense.program.outreach';
            return 'expense.program.diocesan';
        }
        return '';
    }

    public function categoryByKey(string $key): ?array
    {
        return $this->db->table('chart_account_category')->where('category_key', $key)->get()->getRowArray() ?: null;
    }
}
