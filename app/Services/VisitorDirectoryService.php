<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\PersonModel;
use App\Models\PersonVisitModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Pager\Pager;

/**
 * VisitorDirectoryService — canonical read/write facade for the visitor
 * directory (T4.5).
 *
 * A "visitor" is a canonical person with a parish_affiliation whose
 * membership_status is 'visitor' (or which carries conversion markers left
 * by a visitor→member conversion). Contact fields live on `person`
 * (encrypted at rest), visit metadata on `person_visit`, and follow-up /
 * conversion workflow state on `parish_affiliation`.
 *
 * Rows returned here keep the legacy visitor-array shape so existing views
 * keep working; the row `id` is the canonical `person.id`.
 */
class VisitorDirectoryService
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
     * Base builder: visitor affiliations (or affiliations converted from a
     * visitor) joined to the canonical person row.
     */
    /** Correlated subquery fetching the earliest visit date for a person+parish (pa/pe aliases). */
    public function firstVisitDateSql(): string
    {
        $table = $this->db()->prefixTable('person_visit');

        return '(SELECT MIN(pvx.visit_date) FROM ' . $table . ' pvx'
            . ' WHERE pvx.person_id = pa.person_id AND pvx.parish_id = pa.parish_id)';
    }

    /** Correlated subquery fetching a column from the earliest visit row. */
    private function firstVisitFieldSql(string $column): string
    {
        $table = $this->db()->prefixTable('person_visit');

        return '(SELECT pvx.' . $column . ' FROM ' . $table . ' pvx'
            . ' WHERE pvx.person_id = pa.person_id AND pvx.parish_id = pa.parish_id'
            . ' ORDER BY pvx.visit_date ASC, pvx.id ASC LIMIT 1)';
    }

    /**
     * Visitor-scope join only (pa + pe aliases, no select list) — for
     * reports/metrics that need their own columns.
     *
     * Scope covers the pre-member pipeline: `visitor` and
     * `regular_attender` affiliations, plus any affiliation that was
     * converted from a visitor (membership_status is then 'member' and
     * the row is kept visible via converted_to_member_at).
     */
    public function scopeBuilder(?int $parishId = null): BaseBuilder
    {
        $builder = $this->db()->table('parish_affiliation pa')
            ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
            ->where('pa.deleted_at IS NULL', null, false)
            ->groupStart()
                ->whereIn('pa.membership_status', ['visitor', 'regular_attender'])
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
            ->select($this->firstVisitFieldSql('notes') . ' AS notes', false)
            ->select('(SELECT COUNT(*) FROM ' . $this->db()->prefixTable('person_visit') . ' pvc'
                . ' WHERE pvc.person_id = pa.person_id AND pvc.parish_id = pa.parish_id) AS visit_count', false);

        return $builder;
    }

    /**
     * Fetch a single visitor row by canonical person id within a parish.
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
     * Paginated visitor list for a parish with the legacy filter set.
     *
     * @param array{followed_up?: string|int|null, search?: ?string, date_from?: ?string,
     *              date_to?: ?string, followup_stage?: ?string, source?: ?string,
     *              lifecycle?: ?string} $filters
     * @return array{rows: array, pager: Pager}
     */
    public function paginateForParish(int $parishId, array $filters, int $perPage, int $page): array
    {
        $builder = $this->baseBuilder($parishId);

        // Lifecycle filter: default shows the unconverted pipeline
        // (visitors + regular attenders); 'converted' lists members that
        // came through the visitor pathway; 'all' disables the split.
        $lifecycle = (string) ($filters['lifecycle'] ?? '');
        if ($lifecycle === 'converted') {
            $builder->where('pa.converted_to_member_at IS NOT NULL', null, false);

            // Optional rolling window on the conversion timestamp.
            $convertedWindow = (int) ($filters['converted_window'] ?? 0);
            if ($convertedWindow > 0) {
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$convertedWindow} days"));
                $builder->where('pa.converted_to_member_at >=', $cutoff);
            }
        } elseif ($lifecycle === 'all') {
            // no additional restriction
        } else {
            $builder->where('pa.converted_to_member_at IS NULL', null, false);
            if (in_array($lifecycle, ['visitor', 'regular_attender'], true)) {
                $builder->where('pa.membership_status', $lifecycle);
            }
        }

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
            // Subqueries are parish-correlated so the filtered list matches
            // the pendingDistinctPersonsCount() population exactly — a log
            // belonging to another parish must not leak into this list.
            $logTable = $this->db()->prefixTable('followup_log');
            if ($stage === 'none') {
                $builder->where("pa.person_id NOT IN (SELECT DISTINCT person_id FROM {$logTable} WHERE status IN (\"sent\",\"skipped\") AND parish_id = pa.parish_id)", null, false);
            } elseif ($stage === 'inprogress') {
                $builder->where("pa.person_id IN (SELECT DISTINCT person_id FROM {$logTable} WHERE status IN (\"sent\",\"skipped\") AND parish_id = pa.parish_id)", null, false)
                    ->where("pa.person_id IN (SELECT DISTINCT person_id FROM {$logTable} WHERE status = \"pending\" AND parish_id = pa.parish_id)", null, false);
            } elseif (in_array($stage, ['pending', 'sent', 'failed', 'skipped'], true)) {
                $builder->where(
                    "pa.person_id IN (SELECT DISTINCT person_id FROM {$logTable} WHERE status = '{$stage}' AND parish_id = pa.parish_id)",
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

        // countAllResults(false): the clone shares the connection's alias
        // registry — resetting it would re-prefix aliased columns on the
        // main builder's ->get() under a prefixed connection.
        $total  = (int) (clone $builder)->countAllResults(false);
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
     * Most recent visitors for a parish (dashboard widgets).
     */
    public function recentForParish(int $parishId, int $limit = 10): array
    {
        $rows = $this->baseBuilder($parishId)
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->orderBy($this->firstVisitDateSql(), 'DESC', false)
            ->orderBy('pe.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(fn(array $row): array => $this->decryptRow($row), $rows);
    }

    /**
     * Total visitors for a parish; optionally excluding converted ones.
     */
    public function countForParish(int $parishId, bool $excludeConverted = true): int
    {
        $builder = $this->baseBuilder($parishId);

        if ($excludeConverted) {
            $builder->where('pa.converted_to_member_at IS NULL', null, false);
        }

        return (int) $builder->countAllResults();
    }

    /**
     * Converted-visitor count for the parish. When $windowDays is given,
     * counts only conversions within that rolling window.
     */
    public function convertedCountForParish(int $parishId, ?int $windowDays = null): int
    {
        $builder = $this->baseBuilder($parishId)
            ->where('pa.converted_to_member_at IS NOT NULL', null, false);

        if ($windowDays !== null && $windowDays > 0) {
            $builder->where('pa.converted_to_member_at >=', date('Y-m-d H:i:s', strtotime("-{$windowDays} days")));
        }

        return (int) $builder->countAllResults();
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
     * Per-parish visit-count threshold at which a visitor is *suggested*
     * for promotion to regular_attender. Advisory only — nothing here
     * auto-transitions a status.
     */
    public function attenderSuggestionThreshold(int $parishId): int
    {
        try {
            $raw = (new ParishConfigService())->getSetting($parishId, 'visitor.attender_suggest_threshold', 2);
        } catch (\Throwable $e) {
            $raw = 2;
        }

        return max(1, (int) $raw);
    }

    /**
     * Whether the person has a visitor-domain affiliation in the parish
     * (visitor or regular_attender). Follow-up mutations are scoped to
     * these — members and exited rows are managed from People.
     */
    public function visitorAffiliationExists(int $personId, int $parishId): bool
    {
        if ($personId <= 0 || $parishId <= 0) {
            return false;
        }

        return $this->db()->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->whereIn('membership_status', ['visitor', 'regular_attender'])
            ->where('deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
    }

    /**
     * Decrypt contact fields on a visitor row (person stores them encrypted).
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
     * Channel opt-out check on a visitor-shaped row (person.opted_out_channels).
     */
    public function isOptedOut(array $visitor, string $channel): bool
    {
        $raw = $visitor['opted_out_channels'] ?? null;
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
     * Create a visitor through the canonical intake chain (person +
     * parish_affiliation(type=visitor,status=visitor) + person_visit).
     * Extra visitor-form fields (interests, opt-outs, timezone) land on
     * `person`.
     *
     * @return int The canonical person_id (visitor row id going forward)
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
        $intake   = VisitorIntakeService::record($data, $parishId);
        $personId = (int) $intake['person_id'];

        // Extra visitor-form fields land on the person row, but only for
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
     * Update a visitor: person contact/preference fields, the earliest
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
                    // Through the model so the afterInsert hook schedules
                    // follow-up sequences for this first visit.
                    model(PersonVisitModel::class)->insert(array_merge($visitFields, [
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
            log_message('error', 'VisitorDirectoryService::update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a visitor: soft-delete the parish affiliation and, when the
     * person has no remaining live affiliations, the person row itself.
     * Pending follow-up entries are skipped so dispatch stops; the
     * followup_log / person_visit history is preserved for audit.
     *
     * Refuses rows that are on the parish register (member/inactive) or
     * already converted — those are members managed from the People
     * directory, not visitor-pipeline records.
     */
    public function delete(int $personId, int $parishId): bool
    {
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');

        $affiliation = $db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if (is_array($affiliation)
            && (! empty($affiliation['converted_to_member_at'])
                || in_array((string) $affiliation['membership_status'], ['member', 'inactive'], true))) {
            return false;
        }

        $db->transBegin();
        try {
            $db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('parish_id', $parishId)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            $sync   = new CallTaskSyncService($db);
            $logIds = $sync->pendingLogIdsForPerson($personId, $parishId);

            $db->table('followup_log')
                ->where('person_id', $personId)
                ->where('parish_id', $parishId)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'skipped',
                    'notes'      => 'Visitor deleted',
                    'updated_at' => $now,
                ]);

            // Cancel queued call/manual tasks for the skipped logs.
            $sync->cancelForLogs($logIds);

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
            log_message('error', 'VisitorDirectoryService::delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a visitor→member conversion on the affiliation (canonical
     * replacement for the legacy visitor.converted_* columns).
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
     * Revert a visitor→member conversion: clears the conversion markers and
     * restores the affiliation to visitor/visitor.
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
