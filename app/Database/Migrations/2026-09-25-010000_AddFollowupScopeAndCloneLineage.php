<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add diocese scope and clone lineage to the follow-up tables.
 *
 * Scope model: exactly one of parish_id / diocese_id is set; both NULL
 * means the row is global. Diocese-scoped sequences and templates act as
 * publishable defaults/templates that parishes clone; provinces are not
 * a follow-up scope.
 *
 * Lineage: cloned parish rows record their upstream source
 * (source_sequence_id / source_action_id / source_template_id) so edits to
 * upstream records can propagate into existing clones. A clone that the
 * parish has edited locally is marked is_customized = 1 and is skipped by
 * propagation, preserving local work.
 */
class AddFollowupScopeAndCloneLineage extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('diocese_id', 'followup_sequence')) {
            $this->forge->addColumn('followup_sequence', [
                'diocese_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'parish_id',
                ],
                'source_sequence_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'diocese_id',
                ],
                'is_diocese_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_global_default',
                ],
                'is_customized' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_diocese_default',
                ],
            ]);
        }

        if (! $this->db->fieldExists('source_action_id', 'followup_action')) {
            $this->forge->addColumn('followup_action', [
                'source_action_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'instructions',
                ],
                'is_customized' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'source_action_id',
                ],
            ]);
        }

        if (! $this->db->fieldExists('diocese_id', 'followup_template')) {
            $this->forge->addColumn('followup_template', [
                'diocese_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'parish_id',
                ],
                'source_template_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'diocese_id',
                ],
                'is_customized' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'source_template_id',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->dropColumnsWithForeignKeys('followup_action', ['source_action_id', 'is_customized']);
        $this->dropColumnsWithForeignKeys('followup_template', ['diocese_id', 'source_template_id', 'is_customized']);
        $this->dropColumnsWithForeignKeys('followup_sequence', [
            'diocese_id',
            'source_sequence_id',
            'is_diocese_default',
            'is_customized',
        ]);
    }

    /**
     * Drops the listed columns after removing any foreign key that
     * references them; MySQL refuses to drop a column while a
     * constraint still points at it.
     */
    private function dropColumnsWithForeignKeys(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $foreignKeys = $this->db->getForeignKeyData($table);
        foreach ($columns as $column) {
            if (! $this->db->fieldExists($column, $table)) {
                continue;
            }

            foreach ($foreignKeys as $fk) {
                if (in_array($column, (array) ($fk->column_name ?? []), true)) {
                    $this->forge->dropForeignKey($table, $fk->constraint_name);
                }
            }

            $this->forge->dropColumn($table, $column);
        }
    }
}
