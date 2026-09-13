<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CreateUserPermissionsTable
 *
 * Creates a table to support custom user-level permission overrides.
 * This allows granting or revoking specific permissions for individual users
 * beyond what their group memberships provide.
 */
class CreateUserPermissionsTable extends Migration
{
    public function up()
    {
        if (!$this->tableExists('auth_users_permissions')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                ],
                'permission' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'type' => [
                    'type' => 'ENUM',
                    'constraint' => ['grant', 'revoke'],
                    'default' => 'grant',
                    'comment' => 'Grant adds permission, revoke removes it from group permissions',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                    'comment' => 'User ID who granted/revoked this permission',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('user_id');
            $this->forge->addKey('permission');
            $this->forge->addUniqueKey(['user_id', 'permission']);
            $this->forge->createTable('auth_users_permissions', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('auth_users_permissions', true);
    }

    private function tableExists(string $table): bool
    {
        try {
            return $this->db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}