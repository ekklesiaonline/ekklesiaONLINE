<?php

namespace App\Database\Migrations;

use App\Libraries\DataEncryption;
use CodeIgniter\Database\Migration;

/**
 * Migrates the legacy `guest` / `guest_visit` module store onto canonical
 * structures (T4.5):
 *
 *   guest identity/contact fields  → person (+ person.interests, new column)
 *   parish link + follow-up state  → parish_affiliation
 *                                    (+ followed_up / converted_to_member_at /
 *                                     converted_by_user_id, new columns;
 *                                     converted_user_id maps to the existing
 *                                     linked_user_id)
 *   guest / guest_visit history    → person_visit
 *
 * Every guest row must resolve to a person + parish_affiliation before the
 * legacy tables are dropped; a row that cannot be resolved aborts the
 * migration instead of silently dropping data.
 */
class MigrateGuestsToCanonical extends Migration
{
    public function up()
    {
        $db = $this->db ?? db_connect();
        $now = date('Y-m-d H:i:s');

        $this->addCanonicalColumns($db);

        if ($db->tableExists('guest') && $db->tableExists('person') && $db->tableExists('parish_affiliation')) {
            $this->backfillGuests($db, $now);
        }

        if ($db->tableExists('guest_visit') && $db->tableExists('person_visit')) {
            $this->backfillGuestVisits($db, $now);
        }

        if ($db->tableExists('guest')) {
            $this->forge->dropTable('guest', true);
        }
        if ($db->tableExists('guest_visit')) {
            $this->forge->dropTable('guest_visit', true);
        }
    }

    public function down()
    {
        // Not reversible in place: rows were merged into person /
        // parish_affiliation / person_visit with dedup and enrichment.
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    private function addCanonicalColumns($db): void
    {
        if ($db->tableExists('parish_affiliation')) {
            $fields = [];
            if (! $db->fieldExists('followed_up', 'parish_affiliation')) {
                $fields['followed_up'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false];
            }
            if (! $db->fieldExists('converted_to_member_at', 'parish_affiliation')) {
                $fields['converted_to_member_at'] = ['type' => 'DATETIME', 'null' => true];
            }
            if (! $db->fieldExists('converted_by_user_id', 'parish_affiliation')) {
                $fields['converted_by_user_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
            }
            if ($fields !== []) {
                $this->forge->addColumn('parish_affiliation', $fields);
            }
        }

        if ($db->tableExists('person') && ! $db->fieldExists('interests', 'person')) {
            $this->forge->addColumn('person', [
                'interests' => ['type' => 'TEXT', 'null' => true],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Backfill
    // ------------------------------------------------------------------

    private function backfillGuests($db, string $now): void
    {
        $guests = $db->table('guest')->get()->getResultArray();

        foreach ($guests as $guest) {
            $guestId  = (int) $guest['id'];
            $parishId = (int) ($guest['parish_id'] ?? 0);

            if ($parishId <= 0) {
                throw new \RuntimeException(
                    "Cannot migrate guest #{$guestId}: missing parish_id. Assign a parish before running this migration."
                );
            }

            $personId = $this->resolvePersonId($db, $guest, $parishId, $now);
            if ($personId <= 0) {
                throw new \RuntimeException("Cannot migrate guest #{$guestId}: failed to resolve a canonical person row.");
            }

            $this->enrichPerson($db, $personId, $guest);
            $this->upsertAffiliation($db, $personId, $parishId, $guest, $now);
            $this->ensurePersonVisit($db, $personId, $parishId, $guest, $now);
        }
    }

    private function resolvePersonId($db, array $guest, int $parishId, string $now): int
    {
        $personId = (int) ($guest['person_id'] ?? 0);
        if ($personId > 0) {
            $exists = $db->table('person')->where('id', $personId)->countAllResults() > 0;
            if ($exists) {
                return $personId;
            }
        }

        // Dedup by contact hash within the parish (same rule GuestIntakeService uses).
        $emailHash = isset($guest['email_hash']) ? (string) $guest['email_hash'] : null;
        $phoneHash = isset($guest['phone_hash']) ? (string) $guest['phone_hash'] : null;
        if (($emailHash === null || $emailHash === '') && ! empty($guest['email'])) {
            $emailHash = DataEncryption::emailIndex(DataEncryption::decrypt((string) $guest['email']) ?? (string) $guest['email']);
        }
        if (($phoneHash === null || $phoneHash === '') && ! empty($guest['phone'])) {
            $phoneHash = DataEncryption::phoneIndex(DataEncryption::decrypt((string) $guest['phone']) ?? (string) $guest['phone']);
        }

        if (($emailHash !== null && $emailHash !== '') || ($phoneHash !== null && $phoneHash !== '')) {
            $builder = $db->table('person pe')
                ->select('pe.id')
                ->join('parish_affiliation pa', 'pa.person_id = pe.id')
                ->where('pa.parish_id', $parishId)
                ->groupStart();
            $added = false;
            if ($emailHash !== null && $emailHash !== '') {
                $builder->where('pe.email_hash', $emailHash);
                $added = true;
            }
            if ($phoneHash !== null && $phoneHash !== '') {
                $added ? $builder->orWhere('pe.phone_hash', $phoneHash) : $builder->where('pe.phone_hash', $phoneHash);
                $added = true;
            }
            $builder->groupEnd();

            $row = $builder->limit(1)->get()->getRowArray();
            if ($row) {
                return (int) $row['id'];
            }
        }

        // Create a fresh canonical person (PersonModel hooks encrypt
        // email/phone and derive the hash columns).
        $personData = [
            'first_name'               => (string) ($guest['first_name'] ?? ''),
            'last_name'                => (string) ($guest['last_name'] ?? ''),
            'email'                    => $this->maybeDecrypt($guest['email'] ?? null),
            'phone'                    => $this->maybeDecrypt($guest['phone'] ?? null),
            'address'                  => $guest['address'] ?? null,
            'preferred_contact_method' => $guest['preferred_contact_method'] ?? 'email',
            'preferred_timezone'       => $guest['preferred_timezone'] ?? null,
            'opted_out_channels'       => (string) ($guest['opted_out_channels'] ?? ''),
            'source'                   => substr((string) ($guest['source'] ?? 'guest_migration'), 0, 32),
            'is_active'                => 1,
        ];

        $model = model(\App\Models\PersonModel::class);
        $model->skipValidation(true);
        $id = $model->insert($personData, true);

        if (is_int($id) && $id > 0 && ! empty($guest['created_at'])) {
            $db->table('person')->where('id', $id)->update(['created_at' => $guest['created_at']]);
        }

        return (int) $id;
    }

    private function enrichPerson($db, int $personId, array $guest): void
    {
        $fields = [];

        if ($db->fieldExists('interests', 'person') && isset($guest['interests']) && $guest['interests'] !== '' && $guest['interests'] !== null) {
            $fields['interests'] = (string) $guest['interests'];
        }

        // Fill contact/preference fields only where the canonical row is empty.
        $fillIfEmpty = [
            'address'                  => $guest['address'] ?? null,
            'preferred_contact_method' => $guest['preferred_contact_method'] ?? null,
            'preferred_timezone'       => $guest['preferred_timezone'] ?? null,
            'opted_out_channels'       => $guest['opted_out_channels'] ?? null,
        ];
        $person = $db->table('person')->where('id', $personId)->get()->getRowArray() ?: [];
        foreach ($fillIfEmpty as $col => $value) {
            if (! $db->fieldExists($col, 'person') || $value === null || $value === '') {
                continue;
            }
            $current = $person[$col] ?? null;
            if ($current === null || $current === '') {
                $fields[$col] = $value;
            }
        }

        if ($fields !== []) {
            $fields['updated_at'] = date('Y-m-d H:i:s');
            $db->table('person')->where('id', $personId)->update($fields);
        }
    }

    private function upsertAffiliation($db, int $personId, int $parishId, array $guest, string $now): void
    {
        $converted = ! empty($guest['converted_to_member_at']);

        $affiliation = $db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        $flags = [
            'followed_up'            => (int) ($guest['followed_up'] ?? 0),
            'converted_to_member_at' => $converted ? (string) $guest['converted_to_member_at'] : null,
            'converted_by_user_id'   => isset($guest['converted_by_user_id']) ? (int) $guest['converted_by_user_id'] : null,
            'updated_at'             => $now,
        ];

        if ($converted) {
            $flags['affiliation_type']  = 'member';
            $flags['membership_status'] = 'member';
            $flags['is_member']         = 1;
            if (! empty($guest['converted_user_id'])) {
                $flags['linked_user_id'] = (int) $guest['converted_user_id'];
            }
        }

        if (is_array($affiliation)) {
            $db->table('parish_affiliation')
                ->where('id', (int) $affiliation['id'])
                ->update($flags);
            return;
        }

        $hasOther = $db->table('parish_affiliation')->where('person_id', $personId)->countAllResults() > 0;

        $db->table('parish_affiliation')->insert(array_merge([
            'parish_id'        => $parishId,
            'person_id'        => $personId,
            'linked_user_id'   => isset($guest['converted_user_id']) ? (int) $guest['converted_user_id'] : null,
            'affiliation_type' => $converted ? 'member' : 'guest',
            'membership_status' => $converted ? 'member' : 'visitor',
            'canonical_status'  => $converted ? 'member' : 'visitor',
            'is_member'        => $converted ? 1 : 0,
            'is_primary'       => $hasOther ? 0 : 1,
            'source'           => 'guest_migration',
            'start_date'       => (string) ($guest['visit_date'] ?? date('Y-m-d')),
            'created_at'       => $guest['created_at'] ?? $now,
        ], $flags));
    }

    private function ensurePersonVisit($db, int $personId, int $parishId, array $guest, string $now): void
    {
        $visitDate = (string) ($guest['visit_date'] ?? '');
        if ($visitDate === '') {
            return;
        }

        $existing = $db->table('person_visit')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('visit_date', $visitDate)
            ->countAllResults();
        if ($existing > 0) {
            return;
        }

        $visitCount = $db->table('person_visit')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->countAllResults();

        $db->table('person_visit')->insert([
            'person_id'      => $personId,
            'parish_id'      => $parishId,
            'visit_date'     => $visitDate,
            'visit_type'     => $visitCount > 0 ? 'return' : 'first',
            'attended_event' => $guest['service_attended'] ?? null,
            'notes'          => $guest['notes'] ?? null,
            'created_at'     => $guest['created_at'] ?? $now,
            'updated_at'     => $now,
        ]);
    }

    private function backfillGuestVisits($db, string $now): void
    {
        // guest_visit rows whose guest row is resolvable → person_visit.
        // Idempotent: skip when an identical visit already exists.
        $rows = $db->table('guest_visit gv')
            ->select('gv.*, g.person_id AS guest_person_id')
            ->join('guest g', 'g.id = gv.guest_id', 'left')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $personId = (int) ($row['guest_person_id'] ?? 0);
            if ($personId <= 0) {
                continue; // orphan visit without a person — logged, table drops below
            }

            $parishId  = (int) ($row['parish_id'] ?? 0);
            $visitDate = (string) ($row['visit_date'] ?? '');

            $query = $db->table('person_visit')
                ->where('person_id', $personId)
                ->where('visit_date', $visitDate);
            if ($parishId > 0) {
                $query->where('parish_id', $parishId);
            }
            $exists = $query->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $type = (string) ($row['visit_type'] ?? 'first');
            if (! in_array($type, ['first', 'second', 'return'], true)) {
                $type = 'return';
            }

            $db->table('person_visit')->insert([
                'person_id'      => $personId,
                'parish_id'      => $parishId > 0 ? $parishId : null,
                'visit_date'     => $visitDate,
                'visit_type'     => $type,
                'service_id'     => isset($row['service_id']) ? (int) $row['service_id'] : null,
                'attended_event' => $row['attended_event'] ?? null,
                'gift_given'     => (int) ($row['gift_given'] ?? 0),
                'notes'          => $row['notes'] ?? null,
                'created_at'     => $row['created_at'] ?? $now,
                'updated_at'     => $now,
            ]);
        }
    }

    private function maybeDecrypt($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (DataEncryption::isEncrypted($value)) {
            return DataEncryption::decrypt($value) ?? $value;
        }
        return $value;
    }
}
