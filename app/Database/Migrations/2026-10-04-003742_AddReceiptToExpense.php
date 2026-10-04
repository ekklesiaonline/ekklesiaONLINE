<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiptToExpense extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('expense')) {
            $fields = [];
            if (!$this->db->fieldExists('receipt_path', 'expense')) {
                $fields['receipt_path'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'notes',
                ];
            }
            if (!$this->db->fieldExists('receipt_original_name', 'expense')) {
                $fields['receipt_original_name'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'receipt_path',
                ];
            }
            if (!$this->db->fieldExists('receipt_mime_type', 'expense')) {
                $fields['receipt_mime_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'receipt_original_name',
                ];
            }
            if (!$this->db->fieldExists('receipt_file_size', 'expense')) {
                $fields['receipt_file_size'] = [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'receipt_mime_type',
                ];
            }

            if (!empty($fields)) {
                $this->forge->addColumn('expense', $fields);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('expense')) {
            $columnsToDrop = [];
            foreach (['receipt_path', 'receipt_original_name', 'receipt_mime_type', 'receipt_file_size'] as $column) {
                if ($this->db->fieldExists($column, 'expense')) {
                    $columnsToDrop[] = $column;
                }
            }
            if (!empty($columnsToDrop)) {
                $this->forge->dropColumn('expense', $columnsToDrop);
            }
        }
    }
}
