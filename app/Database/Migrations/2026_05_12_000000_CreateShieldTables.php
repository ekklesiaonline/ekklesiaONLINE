<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CreateShieldTables
 *
 * Creates the missing Shield auth tables that are required for the system.
 * These tables must exist before seeding permissions and roles.
 */
class CreateShieldTables extends Migration
{
    public function up()
    {
        // auth_groups (roles)
            if (!$this->tableExists('auth_groups')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'unique' => true,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'description' => [
                    'type' => 'TEXT',
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
            $this->forge->createTable('auth_groups', true);
        }

        // auth_permissions
            if (!$this->tableExists('auth_permissions')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'unique' => true,
                ],
                'description' => [
                    'type' => 'TEXT',
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
            $this->forge->createTable('auth_permissions', true);
        }

        // auth_groups_permissions (junction table)
            if (!$this->tableExists('auth_groups_permissions')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'group' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'permission' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('group');
            $this->forge->addKey('permission');
            $this->forge->createTable('auth_groups_permissions', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('auth_groups_permissions', true);
        $this->forge->dropTable('auth_permissions', true);
        $this->forge->dropTable('auth_groups', true);
    }

    private function tableExists(string $table): bool
    {
        try {
            $result = $this->db->tableExists($table);
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
