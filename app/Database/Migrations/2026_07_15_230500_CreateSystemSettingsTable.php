<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'setting_key' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'data_type' => [
                'type' => 'VARCHAR',
                'constraint' => 31,
                'null' => false,
                'default' => 'string',
            ],
            'category' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_locked' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'null' => false,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('setting_key');
        $this->forge->addKey('category');
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('system_settings', true);

        $now = date('Y-m-d H:i:s');
        $settings = [
            [
                'setting_key' => 'password_policy.min_length',
                'setting_value' => '12',
                'data_type' => 'integer',
                'category' => 'policy',
                'description' => 'Minimum length for admin passwords.',
            ],
            [
                'setting_key' => 'password_policy.require_uppercase',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'policy',
                'description' => 'Require at least one uppercase letter.',
            ],
            [
                'setting_key' => 'password_policy.require_lowercase',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'policy',
                'description' => 'Require at least one lowercase letter.',
            ],
            [
                'setting_key' => 'password_policy.require_digit',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'policy',
                'description' => 'Require at least one digit.',
            ],
            [
                'setting_key' => 'password_policy.require_special',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'policy',
                'description' => 'Require at least one special character.',
            ],
            [
                'setting_key' => 'password_policy.special_chars',
                'setting_value' => '@$!%*?&#^_-',
                'data_type' => 'string',
                'category' => 'policy',
                'description' => 'Allowed special characters for password validation.',
            ],
            [
                'setting_key' => 'mfa.required_for_admin_ops',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'security',
                'description' => 'Require MFA for system admin write operations.',
            ],
            [
                'setting_key' => 'mfa.required_for_primary_transfer',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'category' => 'security',
                'description' => 'Require OTP via email for primary system admin transfer.',
            ],
            [
                'setting_key' => 'lockout.max_attempts',
                'setting_value' => '5',
                'data_type' => 'integer',
                'category' => 'security',
                'description' => 'Failed attempts before account lockout.',
            ],
            [
                'setting_key' => 'lockout.window_seconds',
                'setting_value' => '900',
                'data_type' => 'integer',
                'category' => 'security',
                'description' => 'Time window in seconds for counting failed attempts.',
            ],
            [
                'setting_key' => 'lockout.duration_seconds',
                'setting_value' => '1800',
                'data_type' => 'integer',
                'category' => 'security',
                'description' => 'How long an account remains locked in seconds.',
            ],
            [
                'setting_key' => 'maintenance.enabled',
                'setting_value' => '0',
                'data_type' => 'boolean',
                'category' => 'system',
                'description' => 'Enable maintenance mode (blocks non-system-admins).',
            ],
            [
                'setting_key' => 'maintenance.message',
                'setting_value' => 'The system is under maintenance. Please check back later.',
                'data_type' => 'text',
                'category' => 'system',
                'description' => 'Message shown during maintenance mode.',
            ],
        ];

        foreach ($settings as $setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
            $setting['is_locked'] = 0;
            $this->db->table('system_settings')->insert($setting);
        }
    }

    public function down()
    {
        $this->forge->dropTable('system_settings');
    }
}
