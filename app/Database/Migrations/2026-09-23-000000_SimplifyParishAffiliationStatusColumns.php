<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SimplifyParishAffiliationStatusColumns — Phase 2 of the membership
 * status simplification (docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md).
 *
 *  - is_member becomes a STORED generated column:
 *      is_member = (membership_status IN ('member','inactive'))
 *    the resolved "on the parish register" semantics — inactive members
 *    stay on the register. No write path may set it anymore.
 *  - canonical_status is dropped: it was only ever SELECTed, and writers
 *    just copied membership_status.
 *  - is_good_standing is dropped: never written; readers join
 *    member_standing_summary for the real value.
 *  - affiliation_type is narrowed to the lifecycle-derivable buckets
 *    (guest|attendee|member) when no clergy/staff rows exist; clergy/staff
 *    are a leadership_position concern, not a lifecycle bucket. If legacy
 *    rows use those values the narrowing is skipped and logged.
 *  - vw_parish_active_affiliation (a non-migration compat view present in
 *    older dumps) is dropped because it selects the removed columns.
 */
class SimplifyParishAffiliationStatusColumns extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        // Compat view from pre-migration dumps references dropped columns.
        $this->db->query('DROP VIEW IF EXISTS `vw_parish_active_affiliation`');

        if ($this->db->fieldExists('is_member', 'parish_affiliation')) {
            $this->db->query(
                'ALTER TABLE `parish_affiliation`
                 MODIFY COLUMN `is_member` TINYINT(1)
                 GENERATED ALWAYS AS (`membership_status` IN (\'member\',\'inactive\')) STORED'
            );
        }

        foreach (['canonical_status', 'is_good_standing'] as $column) {
            if ($this->db->fieldExists($column, 'parish_affiliation')) {
                $this->forge->dropColumn('parish_affiliation', $column);
            }
        }

        if ($this->db->fieldExists('affiliation_type', 'parish_affiliation')) {
            $legacy = (int) $this->db->table('parish_affiliation')
                ->whereIn('affiliation_type', ['clergy', 'staff'])
                ->countAllResults();

            if ($legacy === 0) {
                $this->db->query(
                    "ALTER TABLE `parish_affiliation`
                     MODIFY COLUMN `affiliation_type` ENUM('guest','attendee','member')
                     NOT NULL DEFAULT 'guest'"
                );
            } else {
                log_message(
                    'warning',
                    'SimplifyParishAffiliationStatusColumns: ' . $legacy . ' parish_affiliation row(s) '
                    . 'still use clergy/staff affiliation_type — enum narrowing skipped. Reclassify them '
                    . '(leadership_position owns clergy/staff) and re-run the narrowing manually.'
                );
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        if ($this->db->fieldExists('affiliation_type', 'parish_affiliation')) {
            $this->db->query(
                "ALTER TABLE `parish_affiliation`
                 MODIFY COLUMN `affiliation_type` ENUM('guest','attendee','member','clergy','staff')
                 NOT NULL DEFAULT 'guest'"
            );
        }

        if (! $this->db->fieldExists('is_good_standing', 'parish_affiliation')) {
            $this->forge->addColumn('parish_affiliation', [
                'is_good_standing' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_member',
                ],
            ]);
        }

        if (! $this->db->fieldExists('canonical_status', 'parish_affiliation')) {
            $this->forge->addColumn('parish_affiliation', [
                'canonical_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'membership_status',
                ],
            ]);
            $this->db->query('UPDATE `parish_affiliation` SET `canonical_status` = `membership_status`');
        }

        if ($this->db->fieldExists('is_member', 'parish_affiliation')) {
            $this->db->query(
                'ALTER TABLE `parish_affiliation`
                 MODIFY COLUMN `is_member` TINYINT(1) NOT NULL DEFAULT 0'
            );
            $this->db->query(
                "UPDATE `parish_affiliation`
                 SET `is_member` = (`membership_status` IN ('member','inactive'))"
            );
        }
    }
}
