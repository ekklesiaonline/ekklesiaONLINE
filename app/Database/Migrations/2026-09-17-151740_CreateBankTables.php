<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the parish banking tables: accounts, transactions,
 * reconciliations, and reconciliation items.
 *
 * bank_account                  — parish-scoped bank account register.
 * bank_transaction              — a ledger line on an account.
 * bank_reconciliation           — a statement reconciliation worksheet.
 * bank_reconciliation_item      — a transaction cleared on a worksheet.
 * bank_transaction_journal_link — reserved link to future journal entries.
 */
class CreateBankTables extends Migration
{
    public function up()
    {
        // bank_account
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'chart_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => false,
            ],
            'institution_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'masked_account_number' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'routing_last4' => [
                'type' => 'VARCHAR',
                'constraint' => 4,
                'null' => true,
            ],
            'currency_code' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'USD',
                'null' => false,
            ],
            'opening_balance' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
                'null' => false,
            ],
            'opened_on' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parish_id');
        $this->forge->createTable('bank_account', true);

        // bank_transaction
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'bank_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'transaction_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
                'null' => false,
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'reference' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['unmatched', 'cleared', 'reconciled'],
                'default' => 'unmatched',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_account_id');
        $this->forge->addKey('transaction_date');
        $this->forge->addKey('status');
        $this->forge->createTable('bank_transaction', true);

        // bank_reconciliation
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'bank_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'statement_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'statement_balance' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
                'null' => false,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['open', 'closed'],
                'default' => 'open',
                'null' => false,
            ],
            'closed_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'closed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_account_id');
        $this->forge->addKey('status');
        $this->forge->createTable('bank_reconciliation', true);

        // bank_reconciliation_item
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'bank_reconciliation_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'bank_transaction_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'is_cleared' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_reconciliation_id');
        $this->forge->addUniqueKey(['bank_reconciliation_id', 'bank_transaction_id']);
        $this->forge->createTable('bank_reconciliation_item', true);

        // bank_transaction_journal_link (reserved for the future journal module)
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'bank_transaction_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'journal_entry_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_transaction_id');
        $this->forge->addUniqueKey(['bank_transaction_id', 'journal_entry_id']);
        $this->forge->createTable('bank_transaction_journal_link', true);
    }

    public function down()
    {
        $this->forge->dropTable('bank_transaction_journal_link', true);
        $this->forge->dropTable('bank_reconciliation_item', true);
        $this->forge->dropTable('bank_reconciliation', true);
        $this->forge->dropTable('bank_transaction', true);
        $this->forge->dropTable('bank_account', true);
    }
}
