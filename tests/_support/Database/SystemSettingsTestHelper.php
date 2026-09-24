<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use CodeIgniter\Database\BaseConnection;

/**
 * Helpers used by tests that rely on system_settings / audit_log but do not use
 * the full production migration stack (the test suite uses SQLite in-memory).
 */
final class SystemSettingsTestHelper
{
    /**
     * Create the system_settings table and seed the default rows used by the
     * SystemSettingService / SystemAdminService when no migration ran.
     */
    public static function createSystemSettingsTableAndSeed(BaseConnection $db): void
    {
        $prefix = $db->getPrefix();
        $table = $prefix . 'system_settings';

        if (!$db->tableExists($table)) {
            $db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT NULL,
                data_type TEXT NOT NULL DEFAULT \'string\',
                category TEXT NULL,
                description TEXT NULL,
                is_locked INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )');
        }

        $db->table('system_settings')->emptyTable();

        $now = date('Y-m-d H:i:s');
        $settings = [
            ['password_policy.min_length', '12', 'integer', 'policy', 'Minimum length for admin passwords.'],
            ['password_policy.require_uppercase', '1', 'boolean', 'policy', 'Require at least one uppercase letter.'],
            ['password_policy.require_lowercase', '1', 'boolean', 'policy', 'Require at least one lowercase letter.'],
            ['password_policy.require_digit', '1', 'boolean', 'policy', 'Require at least one digit.'],
            ['password_policy.require_special', '1', 'boolean', 'policy', 'Require at least one special character.'],
            ['password_policy.special_chars', '@$!%*?&#^_-', 'string', 'policy', 'Allowed special characters for password validation.'],
            ['mfa.required_for_admin_ops', '1', 'boolean', 'security', 'Require MFA for system admin write operations.'],
            ['mfa.required_for_primary_transfer', '1', 'boolean', 'security', 'Require OTP via email for primary system admin transfer.'],
            ['lockout.max_attempts', '5', 'integer', 'security', 'Failed attempts before account lockout.'],
            ['lockout.window_seconds', '900', 'integer', 'security', 'Time window in seconds for counting failed attempts.'],
            ['lockout.duration_seconds', '1800', 'integer', 'security', 'How long an account remains locked in seconds.'],
            ['maintenance.enabled', '0', 'boolean', 'system', 'Enable maintenance mode (blocks non-system-admins).'],
            ['maintenance.message', 'The system is under maintenance. Please check back later.', 'text', 'system', 'Message shown during maintenance mode.'],
        ];

        foreach ($settings as $setting) {
            $db->table('system_settings')->insert([
                'setting_key' => $setting[0],
                'setting_value' => $setting[1],
                'data_type' => $setting[2],
                'category' => $setting[3],
                'description' => $setting[4],
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Create the small subset of auth/core tables required by controller tests
     * when the full migration stack does not run (SQLite in-memory).
     */
    public static function createCoreAuthTables(BaseConnection $db): void
    {
        $prefix = $db->getPrefix();

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT NOT NULL,
            key TEXT NOT NULL,
            value TEXT NULL,
            type TEXT NULL,
            context TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'user_registration_request (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            status TEXT NULL,
            parish_id INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'profile (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parish_id INTEGER NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            phone TEXT NULL,
            locale TEXT NULL,
            is_confirmed INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            secret TEXT NULL,
            secret2 TEXT NULL,
            force_reset INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $columns = [];
        try {
            $columns = array_map('strtolower', $db->getFieldNames($prefix . 'auth_identities'));
        } catch (\Throwable $e) {
            // Table may not exist yet.
        }

        if (! in_array('secret2', $columns, true)) {
            try {
                $db->query('ALTER TABLE ' . $prefix . 'auth_identities ADD COLUMN secret2 TEXT NULL');
            } catch (\Throwable $e) {
                // Ignore when the column already exists or the driver does not support ALTER.
            }
        }

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            selector TEXT NOT NULL,
            hashedValidator TEXT NOT NULL,
            expires TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            email TEXT NULL,
            active INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'province (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_number INTEGER DEFAULT 0,
            name TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'diocese (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            diocese_id INTEGER NULL,
            name TEXT NOT NULL,
            email TEXT NULL,
            phone TEXT NULL,
            address TEXT NULL,
            website TEXT NULL,
            tax_id TEXT NULL,
            deleted_at TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        foreach (['email', 'phone', 'address', 'website', 'street', 'city', 'state', 'zip', 'county', 'country', 'mailing_street', 'mailing_city', 'mailing_state', 'mailing_zip', 'mailing_county', 'mailing_country', 'logo', 'banner', 'tax_id', 'deleted_at'] as $col) {
            try {
                $db->query('ALTER TABLE ' . $prefix . 'parish ADD COLUMN ' . $col . ' TEXT NULL');
            } catch (\Throwable $e) {
                // Column likely already exists.
            }
        }

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_groups_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            "group" TEXT NOT NULL,
            created_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_permissions_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            permission TEXT NOT NULL,
            created_at TEXT NULL
        )');

        $db->resetDataCache();
    }

    /**
     * Create an audit_log table matching the canonical schema used by AuditService.
     */
    public static function createAuditLogTable(BaseConnection $db): void
    {
        // Use the same connection that AuditService will use via db_connect().
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->getPrefix() . 'audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            parish_id INTEGER NULL,
            action TEXT NOT NULL,
            entity_type TEXT NULL,
            entity_id INTEGER NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address TEXT NULL,
            user_agent TEXT NULL,
            created_at TEXT NULL
        )';

        $db->query($sql);
        $db->resetDataCache();
    }
}
