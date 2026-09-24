<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add the `manual` follow-up channel: a sequence step that produces a task
 * assigned to a parish leader (leadership_position) instead of contacting
 * the visitor directly — e.g. "Send the visitor a social-media invitation".
 *
 * - followup_action gains assigned_to (users.id of the parish leader) and
 *   instructions (the task description shown to the assignee).
 * - call_task (the generic volunteer-task store) gains task_type
 *   ('call'|'manual') and a title for the manual task label.
 */
class AddManualTaskChannel extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('assigned_to', 'followup_action')) {
            $this->forge->addColumn('followup_action', [
                'assigned_to' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'template_id',
                ],
                'instructions' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'assigned_to',
                ],
            ]);
        }

        if (! $this->db->fieldExists('task_type', 'call_task')) {
            $this->forge->addColumn('call_task', [
                'task_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => false,
                    'default'    => 'call',
                    'after'      => 'assigned_to',
                ],
                'title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'task_type',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('instructions', 'followup_action')) {
            $this->forge->dropColumn('followup_action', ['assigned_to', 'instructions']);
        }

        if ($this->db->fieldExists('task_type', 'call_task')) {
            $this->forge->dropColumn('call_task', ['task_type', 'title']);
        }
    }
}
