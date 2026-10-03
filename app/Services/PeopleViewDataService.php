<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * M1/M3: Extracted from Clergy\Controllers\People to reduce fat-controller
 * DB usage and improve testability via constructor injection.
 *
 * Handles:
 * - Profile-person link repair (ensureProfilePersonLink)
 * - Status history actor name enrichment (enrichStatusHistory)
 * - Parish-scoped profile lookup for person-management endpoints
 *   (profileForPersonManagement — shared by the People* split controllers)
 */
class PeopleViewDataService
{
    private BaseConnection $db;
    private PersonProfileSyncService $profileSyncService;

    public function __construct(?BaseConnection $db = null, ?PersonProfileSyncService $profileSyncService = null)
    {
        $this->db                = $db ?? db_connect();
        $this->profileSyncService = $profileSyncService ?? new PersonProfileSyncService();
    }

    /**
     * Ensure the profile.person_id and user_person mapping are in sync
     * with the canonical person row.
     *
     * @param int      $profileId The profile ID to repair (preferred).
     * @param int|null $userId    Optional: the user_id to look up by if
     *                            profileId is 0 (used by member-tag toggle).
     */
    public function ensureProfilePersonLink(int $profileId, ?int $userId = null): void
    {
        if (! $this->db->tableExists('profile')) {
            return;
        }

        $profile = null;
        if ($profileId > 0) {
            $profile = $this->db->table('profile')
                ->where('id', $profileId)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();
        } elseif ($userId !== null && $userId > 0) {
            $profile = $this->db->table('profile')
                ->where('user_id', $userId)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();
        }

        if (! $profile) {
            return;
        }

        $personId = (int) ($profile['person_id'] ?? 0);

        if ($personId > 0) {
            $person = $this->db->table('person')
                ->where('id', $personId)
                ->where('deleted_at', null)
                ->countAllResults();

            if ($person > 0) {
                return;
            }
        }

        try {
            $this->profileSyncService->syncFromProfile((int) $profile['id']);
        } catch (\Throwable $e) {
            // Best-effort; do not block the member-tag toggle.
        }
    }

    /**
     * Enrich status history rows with the actor's display name.
     *
     * @param array<int, array<string, mixed>> $history
     * @return array<int, array<string, mixed>>
     */
    public function enrichStatusHistory(array $history): array
    {
        if ($history === []) {
            return $history;
        }

        $actorIds = [];
        foreach ($history as $entry) {
            $actorId = (int) ($entry['changed_by'] ?? 0);
            if ($actorId > 0) {
                $actorIds[] = $actorId;
            }
        }

        $actorNames = [];
        if ($actorIds !== []) {
            $actorIds = array_values(array_unique($actorIds));
            $rows     = $this->db->table('users u')
                ->select('u.id, u.username, ai.secret AS email')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->whereIn('u.id', $actorIds)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $id              = (int) ($row['id'] ?? 0);
                $actorNames[$id] = trim((string) ($row['username'] ?? $row['email'] ?? ''));
            }
        }

        $pathway = new \App\Services\MembershipPathwayService();

        foreach ($history as &$entry) {
            $actorId         = (int) ($entry['changed_by'] ?? 0);
            $entry['actor']  = $actorNames[$actorId] ?? '';

            if (($entry['status_type'] ?? '') === 'affiliation') {
                $entry['classification'] = $pathway->classify(
                    (string) ($entry['from_status'] ?? ''),
                    (string) ($entry['to_status'] ?? '')
                );
            }
        }
        unset($entry);

        return $history;
    }

    /**
     * Load a profile scoped to the current parish for person-management
     * endpoints. Matches on the canonical person_id with linked_user_id as
     * a bridge for profiles whose person_id is unset/dangling.
     */
    public function profileForPersonManagement(int $profileId, int $parishId): ?array
    {
        return $this->db->table('profile p')
            ->select('p.*')
            ->join('parish_affiliation pa', '(pa.person_id = p.person_id OR pa.linked_user_id = p.user_id) AND pa.deleted_at IS NULL', 'inner')
            ->where('p.id', $profileId)
            ->where('pa.parish_id', $parishId)
            ->get()
            ->getRowArray();
    }
}
