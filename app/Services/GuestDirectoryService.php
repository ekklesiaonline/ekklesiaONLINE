<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\PersonModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Pager\Pager;

/**
 * GuestDirectoryService — canonical read/write facade for the guest
 * directory (T4.5).
 *
 * A "guest" is a canonical person with a parish_affiliation whose
 * affiliation_type is 'guest' (or which carries conversion markers left
 * by a guest→member conversion). Contact fields live on `person`
 * (encrypted at rest), visit metadata on `person_visit`, and follow-up /
 * conversion workflow state on `parish_affiliation`.
 *
 * Rows returned here keep the legacy guest-array shape so existing views
 * keep working; the row `id` is the canonical `person.id`.
 */
class GuestDirectoryService
{
    /** Fields merged from the earliest person_visit row. */
    private const VISIT_FIELD_MAP = [
        'visit_date'       => 'visit_date',
        'service_attended' => 'attended_event',
        'notes'            => 'notes',
    ];

    private function db()
    {
        return db_connect();
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Base builder: guest affiliations (or affiliations converted from a
     * guest) joined to the canonical person row.
     */
    /** Correlated subquery fetching the earliest visit date for a person+parish (pa/pe aliases). */
    public function firstVisitDateSql(): string
    {
        return '(SELECT MIN(pvx.visit_date) FROM person_visit pvx'
            . ' WHERE pvx.person_id = pa.person_id AND pvx.parish_id = pa.parish_id)';
    }

    /** Correlated subquery fetching a column from the earliest visit row. */
    private function firstVisitFieldSql(string $column): string
    {
        return '(SELECT pvx.' . $column . ' FROM person_visit pvx'
            . ' WHERE pvx.person_id = pa.person_id AND pvx.parish_id = pa.parish_id'
            . ' ORDER BY pvx.visit_date ASC, pvx.id ASC LIMIT 1)';
    }

    /**
     * Guest-scope join only (pa + pe aliases, no select list) — for
     * reports/metrics that need their own columns.
     */
    public function scopeBuilder(?int $parishId = null): BaseBuilder
    {
        $builder = $this->db()->table('parish_affiliation pa')
            ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
            ->where('pa.deleted_at IS NULL', null, false)
            ->groupStart()
                ->where('pa.affiliation_type', 'guest')
                ->orWhere('pa.converted_to_member_at IS NOT NULL', null, false)
            ->groupEnd();

        if ($parishId !== null && $parishId > 0) {
            $builder->where('pa.parish_id', $parishId);
        }

        return $builder;
    }

    private function baseBuilder(?int $parishId = null): BaseBuilder
    {
        $visitDate = $this->firstVisitDateSql();

        $builder = $this->scopeBuilder($parishId)
            ->select('pe.id AS id, pe.id AS person_id, pa.id AS affiliation_id, pa.parish_id,'
                . ' pe.first_name, pe.last_name, pe.email, pe.phone, pe.email_hash, pe.phone_hash,'
                . ' pe.address, pe.preferred_contact_method, pe.preferred_timezone, pe.opted_out_channels,'
                . ' pe.source, pe.interests,'
                . ' pa.followed_up, pa.converted_to_member_at, pa.linked_user_id AS converted_user_id,'
                . ' pa.converted_by_user_id, pa.membership_status, pa.is_member,'
                . ' pa.created_at, pa.updated_at')
            ->select($visitDate . ' AS visit_date', false)
            ->select($this->firstVisitFieldSql('attended_event') . ' AS service_attended', false)
            ->select($this->firstVisitFieldSql('notes') . ' AS notes', false);

        return $builder;
    }

    /**
     * Fetch a single guest row by canonical person id within a parish.
     */
    public function findByPerson(int $personId, ?int $parishId = null): ?array
    {
        if ($personId <= 0) {
            return null;
        }

        $row = $this->baseBuilder($parishId)
            ->where('pa.person_id', $personId)
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $this->decryptRow($row) : null;
    }

    /**
     * Paginated guest list for a parish with the legacy filter set.
     *
     * @param array{followed_up?: string|int|null, search?: ?string, date_from?: ?string,
     *              date_to?: ?string, followup_stage?: ?string, source?: ?string} $filters
     * @return array{rows: array, pager: Pager}
     */
    public function paginateForParish(int $parishId, array $filters, int $perPage, int $page): array
    {
        $builder = $this->baseBuilder($parishId);

        $followedUp = $filters['followed_up'] ?? null;
        if ($followedUp !== null && $followedUp !== '') {
            $builder->where('pa.followed_up', (int) $followedUp);
        }

        $visitDate = $this->firstVisitDateSql();
        $dateFrom  = $filters['date_from'] ?? null;
        if ($dateFrom !== null && $dateFrom !== '') {
            $builder->where($visitDate . ' >= ' . $this->db()->escape((string) $dateFrom), null, false);
        }
        $dateTo = $filters['date_to'] ?? null;
        if ($dateTo !== null && $dateTo !== '') {
            $builder->where($visitDate . ' <= ' . $this->db()->escape((string) $dateTo), null, false);
        }

        $source = $filters['source'] ?? null;
        if ($source !== null && $source !== '') {
            $builder->where('pe.source', $source);
        }

        $stage = $filters['followup_stage'] ?? null;
        if ($stage !== null && $stage !== '') {
            if ($stage === 'none') {
                $builder->where('pa.person_id NOT IN (SELECT DISTINCT person_id FROM followup_log WHERE status IN ("sent","skipped"))', null, false);
            } elseif ($stage === 'inprogress') {
                $builder->where('pa.person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status IN ("sent","skipped"))', null, false)
                    ->where('pa.person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status = "pending")', null, false);
            } elseif (in_array($stage, ['pending', 'sent', 'failed', 'skipped'], true)) {
                $builder->where(
                    "pa.person_id IN (SELECT DISTINCT person_id FROM followup_log WHERE status = '{$stage}')",
                    null,
                    false
                );
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $emailHash = DataEncryption::emailIndex($search);
            $phoneHash = DataEncryption::phoneIndex($search);

            $builder->groupStart()
                ->like('pe.first_name', $search)
                ->orLike('pe.last_name', $search);
            if ($emailHash !== null) {
                $builder->orWhere('pe.email_hash', $emailHash);
            }
            if ($phoneHash !== null) {
                $builder->orWhere('pe.phone_hash', $phoneHash);
            }
            $builder->groupEnd();
        }

        $total  = (int) (clone $builder)->countAllResults();
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $rows = $builder
            ->orderBy($visitDate, 'DESC', false)
            ->orderBy('pe.id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $rows = array_map(fn(array $row): array => $this->decryptRow($row), $rows);

        $pager = service('pager');
        $pager->store('default', $page, $perPage, $total);

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Most recent guests for a parish (dashboard widgets).
     */
    public function recentForParish(int $parishId, int $limit = 10): array
    {
        $rows = $this->baseBuilder($parishId)
            ->orderBy($this->firstVisitDateSql(), 'DESC', false)
            ->orderBy('pe.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(fn(array $row): array => $this->decryptRow($row), $rows);
    }

    /**
     * Total guests for a parish; optionally excluding converted ones.
     */
    public function countForParish(int $parishId, bool $excludeConverted = true): int
    {
        $builder = $this->baseBuilder($parishId);

        if ($excludeConverted) {
            $builder->where('pa.converted_to_member_at IS NULL', null, false);
        }

        return (int) $builder->countAllResults();
    }

    public function convertedCountForParish(int $parishId): int
    {
        return (int) $this->baseBuilder($parishId)
            ->where('pa.converted_to_member_at IS NOT NULL', null, false)
            ->countAllResults();
    }

    /**
     * Id + name list for pickers (template preview selector).
     */
    public function pickerRowsForParish(int $parishId, int $limit = 50): array
    {
        return $this->baseBuilder($parishId)
            ->select('pe.id AS id, pe.first_name, pe.last_name')
            ->orderBy('pa.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Whether the person has a guest-scoped affiliation in the parish
     * (used for follow-up log scope checks).
     */
    public function affiliationExists(int $personId, int $parishId): bool
    {
        if ($personId <= 0 || $parishId <= 0) {
            return false;
        }

        return $this->db()->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
    }

    /**
     * Decrypt contact fields on a guest row (person stores them encrypted).
     */
    public function decryptRow(array $row): array
    {
        if (array_key_exists('email', $row)) {
            $row['email'] = DataEncryption::decrypt($row['email']) ?? $row['email'];
        }
        if (array_key_exists('phone', $row)) {
            $row['phone'] = DataEncryption::decrypt($row['phone']) ?? $row['phone'];
        }

        return $row;
    }

    /**
     * Channel opt-out check on a guest-shaped row (person.opted_out_channels).
     */
    public function isOptedOut(array $guest, string $channel): bool
    {
        $raw = $guest['opted_out_channels'] ?? null;
        if ($raw === null || $raw === '' || $raw === '[]') {
            return false;
        }

        $channels = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($channels) && in_array($channel, $channels, true);
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Create a guest through the canonical intake chain (person +
     * parish_affiliation(type=guest,status=visitor) + person_visit).
     * Extra guest-form fields (interests, opt-outs, timezone) land on
     * `person`.
     *
     * @return int The canonical person_id (guest row id going forward)
     */
    public function create(array $data, int $parishId): int
    {
        return (int) $this->intake($data, $parishId)['person_id'];
    }

    /**
     * Same as create() but returns the full intake result including
     * `is_new` (whether the person was provisioned vs dedup-matched).
     *
     * @return array{person_id: int, person_visit_id: int, is_new: bool}
     */
    public function intake(array $data, int $parishId): array
    {
        $intake   = GuestIntakeService::record($data, $parishId);
        $personId = (int) $intake['person_id'];

        // Extra guest-form fields land on the person row, but only for
        // newly provisioned persons — a dedup match must not clobber the
        // existing person's recorded preferences.
        if (! empty($intake['is_new']) && $personId > 0) {
            $extra = [];
            foreach (['interests', 'opted_out_channels', 'preferred_timezone'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                    $extra[$field] = $data[$field];
                }
            }
            if ($extra !== []) {
                model(PersonModel::class)->update($personId, $extra);
            }
        }

        return $intake;
    }

    /**
     * Update a guest: person contact/preference fields, the earliest
     * visit row (visit_date / service_attended / notes), and the
     * affiliation followed_up flag.
     */
    public function update(int $personId, int $parishId, array $data): bool
    {
        $personFields = array_intersect_key($data, array_flip([
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'preferred_contact_method',
            'preferred_timezone',
            'opted_out_channels',
            'source',
            'interests',
        ]));

        $db = $this->db();
        $db->transBegin();

        try {
            if ($personFields !== []) {
                model(PersonModel::class)->update($personId, $personFields);
            }

            $visitFields = [];
            if (array_key_exists('visit_date', $data)) {
                $visitFields['visit_date'] = $data['visit_date'];
            }
            if (array_key_exists('service_attended', $data)) {
                $visitFields['attended_event'] = $data['service_attended'];
            }
            if (array_key_exists('notes', $data)) {
                $visitFields['notes'] = $data['notes'];
            }

            if ($visitFields !== []) {
                $visit = $this->firstVisitRow($personId, $parishId);
                if ($visit !== null) {
                    $db->table('person_visit')->where('id', (int) $visit['id'])->update($visitFields);
                } elseif (isset($visitFields['visit_date'])) {
                    $db->table('person_visit')->insert(array_merge($visitFields, [
                        'person_id'  => $personId,
                        'parish_id'  => $parishId,
                        'visit_type' => 'first',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]));
                }
            }

            if (array_key_exists('followed_up', $data)) {
                $db->table('parish_affiliation')
                    ->where('person_id', $personId)
                    ->where('parish_id', $parishId)
                    ->update([
                        'followed_up' => (int) $data['followed_up'],
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'GuestDirectoryService::update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a guest: soft-delete the parish affiliation and, when the
     * person has no remaining live affiliations, the person row itself.
     * followup_log / person_visit history is preserved for audit.
     */
    public function delete(int $personId, int $parishId): bool
    {
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('parish_id', $parishId)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            $remaining = $db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('deleted_at IS NULL', null, false)
                ->countAllResults();

            if ($remaining === 0) {
                model(PersonModel::class)->delete($personId);
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'GuestDirectoryService::delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a guest→member conversion on the affiliation (canonical
     * replacement for the legacy guest.converted_* columns).
     */
    public function markConverted(int $personId, int $parishId, ?int $userId, ?int $convertedByUserId): void
    {
        $update = [
            'followed_up'            => 1,
            'converted_to_member_at' => date('Y-m-d H:i:s'),
            'converted_by_user_id'   => $convertedByUserId,
            'updated_at'             => date('Y-m-d H:i:s'),
        ];
        if ($userId !== null && $userId > 0) {
            $update['linked_user_id'] = $userId;
        }

        $this->db()->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->update($update);
    }

    /**
     * Revert a guest→member conversion: clears the conversion markers and
     * restores the affiliation to guest/visitor.
     */
    public function revertConversion(int $personId, int $parishId, ?int $revertedByUserId): void
    {
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');

        $affiliation = $db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        if (! is_array($affiliation)) {
            return;
        }

        // The lifecycle change goes through the pathway choke point: it
        // derives the register columns and records person_status_history.
        $transition = (new MembershipPathwayService())->transition(
            (int) $affiliation['id'],
            'visitor',
            $revertedByUserId,
            'Conversion reverted'
        );

        if (! ($transition['ok'] ?? false)) {
            return;
        }

        // Non-status columns only — membership_status was already written
        // by the pathway transition above.
        $db->table('parish_affiliation')
            ->where('id', (int) $affiliation['id'])
            ->update([
                'converted_to_member_at' => null,
                'converted_by_user_id'   => null,
                'updated_at'             => $now,
            ]);
    }

    /**
     * Earliest person_visit row for a person within a parish.
     */
    public function firstVisitRow(int $personId, int $parishId): ?array
    {
        $row = $this->db()->table('person_visit')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->orderBy('visit_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }
}
