<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHierarchyScopeToChartOfAccounts extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('scope_type', 'chart_of_accounts')) {
            $fields['scope_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'system',
                'null'       => false,
            ];
        }
        if (!$this->db->fieldExists('scope_id', 'chart_of_accounts')) {
            $fields['scope_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ];
        }
        if (!$this->db->fieldExists('parent_account_id', 'chart_of_accounts')) {
            $fields['parent_account_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('chart_of_accounts', $fields);
        }

        if ($this->db->DBDriver === 'MySQLi') {
            try {
                $indexes = $this->db->query('SHOW INDEX FROM ' . $this->db->prefixTable('chart_of_accounts') . " WHERE Key_name = 'code'")->getResultArray();
                if (!empty($indexes)) {
                    $this->db->query('ALTER TABLE ' . $this->db->prefixTable('chart_of_accounts') . ' DROP INDEX `code`');
                }
            } catch (\Throwable $e) {
                // Ignore if index did not exist
            }

            try {
                $scopeIndexes = $this->db->query('SHOW INDEX FROM ' . $this->db->prefixTable('chart_of_accounts') . " WHERE Key_name = 'uk_coa_scope_code'")->getResultArray();
                if (empty($scopeIndexes)) {
                    $this->db->query('ALTER TABLE ' . $this->db->prefixTable('chart_of_accounts') . ' ADD UNIQUE KEY `uk_coa_scope_code` (`scope_type`, `scope_id`, `code`)');
                }
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('parent_account_id', 'chart_of_accounts')) {
            $this->forge->dropColumn('chart_of_accounts', 'parent_account_id');
        }
        if ($this->db->fieldExists('scope_id', 'chart_of_accounts')) {
            $this->forge->dropColumn('chart_of_accounts', 'scope_id');
        }
        if ($this->db->fieldExists('scope_type', 'chart_of_accounts')) {
            $this->forge->dropColumn('chart_of_accounts', 'scope_type');
        }

        if ($this->db->DBDriver === 'MySQLi') {
            try {
                $this->db->query('ALTER TABLE ' . $this->db->prefixTable('chart_of_accounts') . ' ADD UNIQUE KEY `code` (`code`)');
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
}
