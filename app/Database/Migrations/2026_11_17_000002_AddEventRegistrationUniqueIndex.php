<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enforces at most one non-cancelled member registration per event.
 *
 * Registration is check-then-insert: two concurrent requests for the same
 * member could both pass registrationExistsForMember(). The unique key is
 * the hard backstop.
 *
 * A plain unique key on (event_id, user_id) would be too strict — it would
 * block re-registration after a cancellation and reject legitimately
 * cancelled historical duplicates. Instead the migration adds a generated
 * column that mirrors user_id only while the row is not cancelled, and
 * unique-indexes (event_id, active_user_id). MySQL unique indexes treat
 * NULLs as distinct, so cancelled rows and guest walk-ins (user_id NULL)
 * are unaffected.
 *
 * Pre-existing duplicates (same event_id + user_id, both non-cancelled)
 * are reconciled first by keeping the oldest row and cancelling the rest.
 */
class AddEventRegistrationUniqueIndex extends Migration
{
    private const INDEX_NAME = 'uniq_event_registration_event_user';
    private const COLUMN = 'active_user_id';

    public function up()
    {
        // Generated columns + this UPDATE...JOIN syntax are MySQL-only;
        // the SQLite test fixture never reaches this migration.
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('event_registration')) {
            return;
        }

        $table = $this->db->prefixTable('event_registration');

        // Cancel newer duplicates so the unique key can be created. Keep
        // the oldest checked-in row when one exists — otherwise a member's
        // recorded check-in would be lost to the deduplication.
        $this->db->query(
            'UPDATE ' . $table . ' er
             JOIN (
                 SELECT event_id, user_id,
                        MIN(CASE WHEN status = \'checked_in\' THEN id END) AS checkin_id,
                        MIN(id) AS keep_id
                 FROM ' . $table . '
                 WHERE user_id IS NOT NULL AND status != \'cancelled\'
                 GROUP BY event_id, user_id
                 HAVING COUNT(*) > 1
             ) dup ON dup.event_id = er.event_id AND dup.user_id = er.user_id
             SET er.status = \'cancelled\'
             WHERE er.user_id IS NOT NULL
               AND er.id != COALESCE(dup.checkin_id, dup.keep_id)
               AND er.status != \'cancelled\''
        );

        if (! in_array(self::COLUMN, $this->db->getFieldNames('event_registration'), true)) {
            $this->db->query(
                'ALTER TABLE ' . $table . '
                 ADD COLUMN ' . self::COLUMN . " INT UNSIGNED GENERATED ALWAYS AS (
                     IF(status = 'cancelled', NULL, user_id)
                 ) VIRTUAL"
            );
        }

        if (isset($this->db->getIndexData('event_registration')[self::INDEX_NAME])) {
            return;
        }

        $this->forge->addKey(['event_id', self::COLUMN], false, true, self::INDEX_NAME);
        $this->forge->processIndexes('event_registration');
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->tableExists('event_registration')) {
            return;
        }

        if (isset($this->db->getIndexData('event_registration')[self::INDEX_NAME])) {
            $this->forge->dropKey('event_registration', self::INDEX_NAME);
        }

        if (in_array(self::COLUMN, $this->db->getFieldNames('event_registration'), true)) {
            $this->forge->dropColumn('event_registration', self::COLUMN);
        }
    }
}
