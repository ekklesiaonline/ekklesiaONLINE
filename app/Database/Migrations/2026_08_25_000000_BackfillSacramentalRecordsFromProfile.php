<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfill sacramental_record rows from existing profile.baptism_date /
 * profile.confirmation_date so that sacramental_record becomes the canonical
 * source of truth for baptism/confirmation status without losing data that
 * was previously only stored on the profile.
 *
 * Only inserts a row when NO matching sacramental_record already exists for
 * the same (person_id, parish_id, sacrament_type). Rows created here are
 * tagged with a `notes` prefix of "Backfilled from profile" so down() can
 * remove them safely.
 */
class BackfillSacramentalRecordsFromProfile extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('sacramental_record') || !$this->db->tableExists('profile')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Baptism: profile.baptism_date IS NOT NULL and person_id/parish_id set.
        // Compare as CHAR to avoid MySQL strict-mode rejecting '0000-00-00' as
        // an invalid DATE literal.
        $this->db->query(
            "INSERT INTO sacramental_record
                (parish_id, person_id, sacrament_type, sacrament_date, notes, created_at, updated_at)
             SELECT p.parish_id, p.person_id, 'baptism', p.baptism_date, 'Backfilled from profile baptism_date', '{$now}', '{$now}'
             FROM profile p
             WHERE p.baptism_date IS NOT NULL
               AND CAST(p.baptism_date AS CHAR) != '0000-00-00'
               AND p.person_id IS NOT NULL
               AND p.person_id > 0
               AND p.parish_id IS NOT NULL
               AND p.parish_id > 0
               AND p.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM sacramental_record sr
                   WHERE sr.person_id = p.person_id
                     AND sr.parish_id = p.parish_id
                     AND LOWER(sr.sacrament_type) = 'baptism'
               )"
        );

        // Confirmation: profile.confirmation_date IS NOT NULL OR profile.is_confirmed = 1.
        // Use the date if available; otherwise NULL date (the record still
        // establishes that the sacrament happened).
        $this->db->query(
            "INSERT INTO sacramental_record
                (parish_id, person_id, sacrament_type, sacrament_date, notes, created_at, updated_at)
             SELECT p.parish_id, p.person_id, 'confirmation',
                    CASE WHEN (p.confirmation_date IS NOT NULL AND CAST(p.confirmation_date AS CHAR) != '0000-00-00')
                         THEN p.confirmation_date ELSE NULL END,
                    'Backfilled from profile confirmation_date', '{$now}', '{$now}'
             FROM profile p
             WHERE (
                   (p.confirmation_date IS NOT NULL AND CAST(p.confirmation_date AS CHAR) != '0000-00-00')
                   OR p.is_confirmed = 1
                  )
               AND p.person_id IS NOT NULL
               AND p.person_id > 0
               AND p.parish_id IS NOT NULL
               AND p.parish_id > 0
               AND p.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM sacramental_record sr
                   WHERE sr.person_id = p.person_id
                     AND sr.parish_id = p.parish_id
                     AND LOWER(sr.sacrament_type) = 'confirmation'
               )"
        );
    }

    public function down()
    {
        if (!$this->db->tableExists('sacramental_record')) {
            return;
        }

        // Only remove rows this migration created.
        $this->db->query(
            "DELETE FROM sacramental_record WHERE notes LIKE 'Backfilled from profile%'"
        );
    }
}
