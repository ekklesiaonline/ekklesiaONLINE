<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Expand only: existing appointments and Shield permissions are not promoted.
 * Rehearse against a clone before applying to the user's MySQL database.
 */
class CreateClergyDeaneryFoundation extends Migration
{
    public function up()
    {
        foreach (['person', 'users', 'diocese', 'parish', 'leadership_position', 'parish_report',
            'parish_report_storage', 'auth_permissions_users'] as $required) {
            if (! $this->db->tableExists($required)) {
                throw new RuntimeException("Missing canonical table: {$required}");
            }
        }
        foreach (['parish_id', 'report_year', 'report_status', 'frozen_snapshot_json', 'finalized_by', 'finalized_at'] as $field) {
            if (! $this->db->fieldExists($field, 'parish_report')) {
                throw new RuntimeException("Unexpected live parish_report schema: missing {$field}");
            }
        }
        foreach (['deanery', 'deanery_parish_membership', 'clergy_ordination', 'clergy_canonical_status',
            'clergy_transition_candidate', 'clergy_access_grant', 'permission_role_sync_managed',
            'parish_report_revision', 'parish_report_signature'] as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException("Clergy migration target already exists: {$table}; review for a partial install.");
            }
        }
        foreach (['deanery_id', 'pastoral_in_charge', 'approved_by_user_id', 'approved_at', 'approval_evidence_reference', 'revoked_at'] as $field) {
            if ($this->db->fieldExists($field, 'leadership_position')) {
                throw new RuntimeException("Leadership migration target already exists: {$field}");
            }
        }
        if ($this->db->fieldExists('congregation_type', 'parish')) {
            throw new RuntimeException('Congregation type column already exists; review for a partial install.');
        }
        foreach (['submitted_by', 'submitted_at', 'snapshot_sha256', 'report_revision',
            'clerk_certified_by', 'clerk_certified_at', 'warden_approved_by', 'warden_approved_at',
            'diocesan_approved_by', 'diocesan_approved_at'] as $field) {
            if ($this->db->fieldExists($field, 'parish_report')) {
                throw new RuntimeException("Report migration target already exists: {$field}");
            }
        }

        $this->create('deanery', [
            'diocese_id' => $this->number(),
            'code' => $this->text(64),
            'name' => $this->text(150),
            'active' => $this->number(1, false, 1),
        ], [['diocese_id', 'code']], [['diocese_id', 'diocese']]);

        $this->create('deanery_parish_membership', [
            'deanery_id' => $this->number(),
            'parish_id' => $this->number(),
            'started_at' => ['type' => 'DATE'],
            'ended_at' => ['type' => 'DATE', 'null' => true],
            'appointed_by_user_id' => $this->number(11, true),
        ], [['parish_id', 'started_at']], [['deanery_id', 'deanery'], ['parish_id', 'parish']]);

        $this->create('clergy_ordination', [
            'person_id' => $this->number(),
            'order_code' => $this->text(16),
            'deacon_track' => $this->text(16, true),
            'ordained_on' => ['type' => 'DATE', 'null' => true],
            'evidence_reference' => $this->text(255, true),
            'verification_status' => $this->text(16, false, 'pending'),
            'verified_by_user_id' => $this->number(11, true),
            'verified_at' => ['type' => 'DATETIME', 'null' => true],
        ], [], [['person_id', 'person']]);

        $this->create('clergy_canonical_status', [
            'person_id' => $this->number(),
            'diocese_id' => $this->number(),
            'status_code' => $this->text(20),
            'effective_from' => ['type' => 'DATE'],
            'effective_to' => ['type' => 'DATE', 'null' => true],
            'evidence_reference' => $this->text(255, true),
            'verified_by_user_id' => $this->number(11, true),
        ], [], [['person_id', 'person'], ['diocese_id', 'diocese']]);

        $this->create('clergy_transition_candidate', [
            'source_type' => $this->text(32),
            'source_id' => $this->number(),
            'user_id' => $this->number(),
            'person_id' => $this->number(11, true),
            'proposed_order' => $this->text(16, true),
            'proposed_office' => $this->text(100, true),
            'scope_type' => $this->text(16, true),
            'scope_id' => $this->number(11, true),
            'review_status' => $this->text(16, false, 'pending'),
            'review_reason_code' => $this->text(32, true),
            'reviewed_by_user_id' => $this->number(11, true),
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
        ], [['source_type', 'source_id']], [['user_id', 'users']]);

        $this->create('clergy_access_grant', [
            'user_id' => $this->number(),
            'permission' => $this->text(100),
            'scope_type' => $this->text(16),
            'scope_id' => $this->number(),
            'source_position_id' => $this->number(11, true),
            'grant_type' => $this->text(16),
            'granted_by_user_id' => $this->number(),
            'evidence_reference' => $this->text(255, true),
            'starts_at' => ['type' => 'DATETIME'],
            'ends_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
        ], [], [['user_id', 'users'], ['source_position_id', 'leadership_position']]);

        $this->create('permission_role_sync_managed', [
            'user_id' => $this->number(),
            'permission' => $this->text(100),
            'permission_user_id' => $this->number(),
            'source_type' => $this->text(16),
            'source_key' => $this->text(64),
        ], [['user_id', 'permission', 'source_type', 'source_key']], [['user_id', 'users']]);

        $this->create('parish_report_revision', [
            'report_id' => $this->number(),
            'revision_number' => $this->number(),
            'report_status' => $this->text(20),
            'snapshot_json' => ['type' => 'LONGTEXT'],
            'snapshot_sha256' => $this->text(64),
            'submitted_by' => $this->number(11, true),
            'clerk_certified_by' => $this->number(11, true),
            'warden_approved_by' => $this->number(11, true),
            'finalized_by' => $this->number(11, true),
            'diocesan_approved_by' => $this->number(11, true),
        ], [['report_id', 'revision_number']], [['report_id', 'parish_report']]);

        $this->create('parish_report_signature', [
            'report_id' => $this->number(),
            'revision_number' => $this->number(),
            'stage_code' => $this->text(24),
            'actor_user_id' => $this->number(),
            'source_position_id' => $this->number(11, true),
            'source_grant_id' => $this->number(11, true),
            'diocese_id' => $this->number(),
            'was_delegated' => $this->number(1, false, 0),
            'snapshot_sha256' => $this->text(64),
            'signed_at' => ['type' => 'DATETIME'],
        ], [['report_id', 'revision_number', 'stage_code']], [['report_id', 'parish_report']]);

        $this->forge->addColumn('leadership_position', [
            'deanery_id' => $this->number(11, true),
            'pastoral_in_charge' => $this->number(1, false, 0),
            'approved_by_user_id' => $this->number(11, true),
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'approval_evidence_reference' => $this->text(255, true),
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addColumn('parish', ['congregation_type' => $this->text(16, false, 'parish')]);
        $this->forge->addColumn('parish_report', [
            'submitted_by' => $this->number(11, true),
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'snapshot_sha256' => $this->text(64, true),
            'report_revision' => $this->number(11, false, 1),
            'clerk_certified_by' => $this->number(11, true),
            'clerk_certified_at' => ['type' => 'DATETIME', 'null' => true],
            'warden_approved_by' => $this->number(11, true),
            'warden_approved_at' => ['type' => 'DATETIME', 'null' => true],
            'diocesan_approved_by' => $this->number(11, true),
            'diocesan_approved_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        if ($this->db->DBDriver === 'MySQLi') {
            // The existing MySQL column is ENUM and would reject deanery without this change.
            $this->db->query('ALTER TABLE ' . $this->db->protectIdentifiers('leadership_position')
                . " MODIFY scope_type ENUM('parish','deanery','diocese','province') NOT NULL DEFAULT 'parish'");
            $this->db->query('CREATE INDEX idx_leadership_deanery_active ON '
                . $this->db->protectIdentifiers('leadership_position') . ' (deanery_id, active)');
        }
        // Existing positions intentionally retain approved_at = NULL. A title
        // or submitted clergy_order must never turn into a verified entitlement.
    }

    public function down()
    {
        foreach (['permission_role_sync_managed', 'clergy_access_grant', 'clergy_ordination', 'clergy_canonical_status', 'deanery_parish_membership', 'deanery', 'clergy_transition_candidate', 'parish_report_revision', 'parish_report_signature'] as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException("Cannot discard reviewed clergy/deanery data in {$table}; restore from backup or forward-fix.");
            }
        }
        if ($this->db->table('leadership_position')->where('deanery_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new RuntimeException('Cannot remove active deanery appointments.');
        }
        if ($this->db->table('parish_report')->where('clerk_certified_by IS NOT NULL', null, false)
            ->orWhere('warden_approved_by IS NOT NULL', null, false)
            ->orWhere('finalized_by IS NOT NULL', null, false)
            ->orWhere('diocesan_approved_by IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new RuntimeException('Cannot remove signed report history; use a reviewed forward migration.');
        }
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE ' . $this->db->protectIdentifiers('leadership_position')
                . " MODIFY scope_type ENUM('parish','diocese','province') NOT NULL DEFAULT 'parish'");
        }
        $this->forge->dropColumn('parish_report', [
            'submitted_by', 'submitted_at', 'snapshot_sha256', 'report_revision',
            'clerk_certified_by', 'clerk_certified_at', 'warden_approved_by', 'warden_approved_at',
            'diocesan_approved_by', 'diocesan_approved_at',
        ]);
        $this->forge->dropColumn('parish', 'congregation_type');
        $this->forge->dropColumn('leadership_position', ['deanery_id', 'pastoral_in_charge', 'approved_by_user_id', 'approved_at', 'approval_evidence_reference', 'revoked_at']);
        foreach (['permission_role_sync_managed', 'clergy_access_grant', 'clergy_transition_candidate', 'clergy_canonical_status', 'clergy_ordination', 'deanery_parish_membership', 'deanery', 'parish_report_revision', 'parish_report_signature'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function create(string $table, array $fields, array $unique = [], array $foreign = []): void
    {
        $this->forge->addField(array_merge([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        ], $fields, [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]));
        $this->forge->addKey('id', true);
        foreach ($unique as $columns) {
            $this->forge->addUniqueKey($columns);
        }
        foreach ($foreign as [$column, $target]) {
            $this->forge->addForeignKey($column, $target, 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->forge->createTable($table, false,
            $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : []);
    }

    private function number(int $width = 11, bool $nullable = false, ?int $default = null): array
    {
        $field = ['type' => 'INT', 'constraint' => $width, 'unsigned' => true, 'null' => $nullable];
        if ($default !== null) {
            $field['default'] = $default;
        }
        return $field;
    }

    private function text(int $width, bool $nullable = false, ?string $default = null): array
    {
        $field = ['type' => 'VARCHAR', 'constraint' => $width, 'null' => $nullable];
        if ($default !== null) {
            $field['default'] = $default;
        }
        return $field;
    }
}
