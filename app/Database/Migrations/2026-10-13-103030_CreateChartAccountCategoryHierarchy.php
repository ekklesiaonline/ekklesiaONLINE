<?php

namespace App\Database\Migrations;

use App\Services\ChartAccountCategoryService;
use CodeIgniter\Database\Migration;

class CreateChartAccountCategoryHierarchy extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('chart_account_category')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'category_key' => ['type' => 'VARCHAR', 'constraint' => 150],
                'parent_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'account_type' => ['type' => 'VARCHAR', 'constraint' => 20],
                'is_selectable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('category_key');
            $this->forge->addKey(['account_type', 'is_selectable', 'is_active'], false, false, 'idx_coa_category_selectable');
            $this->forge->addKey('parent_id');
            $this->forge->createTable('chart_account_category');
        }

        foreach (['chart_of_accounts', 'donation', 'expense'] as $table) {
            if ($this->db->tableExists($table) && !$this->db->fieldExists('category_id', $table)) {
                $this->forge->addColumn($table, [
                    'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'category'],
                ]);
                $this->db->query('CREATE INDEX ' . $this->db->escapeIdentifiers('idx_' . $table . '_category_id') . ' ON ' . $this->db->escapeIdentifiers($this->db->prefixTable($table)) . ' (' . $this->db->escapeIdentifiers('category_id') . ')');
            }
        }

        $service = new ChartAccountCategoryService($this->db);
        $service->seedDefaults();
        $this->backfillAccounts($service);
        $this->backfillTransactions('donation', 'income', $service);
        $this->backfillTransactions('expense', 'expense', $service);
    }

    public function down()
    {
        foreach (['expense', 'donation', 'chart_of_accounts'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('category_id', $table)) {
                $this->forge->dropColumn($table, 'category_id');
            }
        }
        if ($this->db->tableExists('chart_account_category')) {
            $this->forge->dropTable('chart_account_category', true);
        }
    }

    private function backfillAccounts(ChartAccountCategoryService $service): void
    {
        if (!$this->db->tableExists('chart_of_accounts')) {
            return;
        }
        $accounts = $this->db->table('chart_of_accounts')->orderBy('id')->get()->getResultArray();
        $byId = array_column($accounts, null, 'id');
        foreach ($accounts as $account) {
            $parent = !empty($account['parent_account_id']) ? ($byId[$account['parent_account_id']] ?? null) : null;
            $key = $service->suggestionKey($parent, (string) $account['account_type'], (string) $account['code']);
            $category = $service->categoryByKey($key);
            if (!$category) {
                continue;
            }
            $this->db->table('chart_of_accounts')->where('id', $account['id'])->update([
                'category_id' => $category['id'],
                'category' => $category['category_key'],
            ]);
            $byId[$account['id']]['category_id'] = $category['id'];
        }
    }

    private function backfillTransactions(string $table, string $type, ChartAccountCategoryService $service): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $aliases = $type === 'income'
            ? ['tithe' => 'income.without_restrictions.contributions', 'offering' => 'income.without_restrictions.contributions', 'building fund' => 'income.with_restrictions.capital', 'building_fund' => 'income.with_restrictions.capital', 'mission' => 'income.with_restrictions.designated']
            : ['utilities' => 'expense.management.utilities', 'maintenance' => 'expense.management.facilities', 'ministry' => 'expense.program.formation', 'salaries' => 'expense.management.lay_personnel', 'personnel' => 'expense.management.lay_personnel', 'outreach' => 'expense.program.outreach', 'administrative' => 'expense.management.administration'];
        $rows = $this->db->table($table)->select('id, chart_account_id, category')->get()->getResultArray();
        foreach ($rows as $row) {
            $category = null;
            if (!empty($row['chart_account_id'])) {
                $account = $this->db->table('chart_of_accounts')->select('category_id, category')->where('id', $row['chart_account_id'])->get()->getRowArray();
                if (!empty($account['category_id'])) {
                    $category = ['id' => $account['category_id'], 'category_key' => $account['category']];
                }
            }
            if (!$category) {
                $key = $aliases[strtolower(trim((string) $row['category']))] ?? null;
                $category = $key ? $service->categoryByKey($key) : null;
            }
            if ($category) {
                $this->db->table($table)->where('id', $row['id'])->update(['category_id' => $category['id'], 'category' => $category['category_key']]);
            }
        }
    }
}
