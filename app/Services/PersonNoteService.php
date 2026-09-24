<?php

namespace App\Services;

use App\Models\PersonNoteModel;
use CodeIgniter\Database\BaseConnection;

/**
 * PersonNoteService
 *
 * Two responsibilities:
 *
 * 1. addNote() — append a staff-authored note (person_note) to a person.
 * 2. feedForPerson() — aggregate every recorded note about a person into a
 *    single chronological feed for the visitor record's Notes tab:
 *      - person_note      → staff notes (author + timestamp)
 *      - person_visit     → intake / per-visit notes
 *      - followup_log     → follow-up outcome notes (mark done, skip…)
 *      - call_task        → queue notes that differ from their log's notes
 *      (task notes mirroring the log's are deduped — resolveForLog copies
 *      the same text to both)
 *
 * Each feed item: date, source, author, body, meta (channel, status,
 * visit_date, service, action label) — the view localizes labels.
 */
class PersonNoteService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Append a staff note for a person. Returns the new note id.
     */
    public function addNote(int $personId, int $parishId, int $userId, string $body): int|false
    {
        $id = model(PersonNoteModel::class)->insert([
            'person_id' => $personId,
            'parish_id' => $parishId,
            'user_id'   => $userId,
            'note'      => $body,
        ], true);

        return $id === false ? false : (int) $id;
    }

    /**
     * Aggregated notes feed for a person, newest first.
     *
     * @return list<array{date: string, source: string, author: ?string, body: string, meta: array}>
     */
    public function feedForPerson(int $personId, int $parishId): array
    {
        $items = [];

        foreach ($this->staffNotes($personId, $parishId) as $row) {
            $items[] = [
                'date'   => (string) ($row['created_at'] ?? ''),
                'source' => 'note',
                'author' => $this->authorLabel($row),
                'body'   => (string) $row['note'],
                'meta'   => [],
            ];
        }

        foreach ($this->visitNotes($personId, $parishId) as $row) {
            $items[] = [
                'date'   => (string) ($row['visit_date'] ?? $row['created_at'] ?? ''),
                'source' => 'visit',
                'author' => null,
                'body'   => (string) $row['notes'],
                'meta'   => [
                    'visit_date' => $row['visit_date'] ?? null,
                    'service'    => $row['attended_event'] ?? null,
                ],
            ];
        }

        foreach ($this->followupNotes($personId, $parishId) as $row) {
            $action = trim((string) ($row['template_name'] ?? ''));
            if ($action === '') {
                $action = trim((string) ($row['instructions'] ?? ''));
            }
            $meta = [
                'channel' => $row['channel'] ?? null,
                'status'  => $row['status'] ?? null,
                'action'  => $action !== '' ? $action : null,
            ];
            $date = (string) ($row['executed_at'] ?? '') !== '' ? (string) $row['executed_at'] : (string) $row['scheduled_date'];

            $logNotes  = trim((string) ($row['notes'] ?? ''));
            $taskNotes = trim((string) ($row['task_notes'] ?? ''));

            if ($logNotes !== '') {
                $items[] = [
                    'date'   => $date,
                    'source' => 'followup',
                    'author' => null,
                    'body'   => $logNotes,
                    'meta'   => $meta,
                ];
            }

            if ($taskNotes !== '' && $taskNotes !== $logNotes) {
                $items[] = [
                    'date'   => (string) ($row['task_completed_at'] ?? '') !== '' ? (string) $row['task_completed_at'] : $date,
                    'source' => 'task',
                    'author' => $this->authorLabel(['author_username' => $row['assignee_username'] ?? null, 'author_name' => $row['assignee_name'] ?? null]),
                    'body'   => $taskNotes,
                    'meta'   => $meta,
                ];
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return $items;
    }

    /**
     * @return list<array>
     */
    private function staffNotes(int $personId, int $parishId): array
    {
        return $this->db->table('person_note pn')
            ->select('pn.id, pn.note, pn.created_at, u.username AS author_username,'
                . ' TRIM(CONCAT(COALESCE(ap.first_name, ""), " ", COALESCE(ap.last_name, ""))) AS author_name')
            ->join('users u', 'u.id = pn.user_id', 'left')
            ->join('profile ap', 'ap.user_id = u.id AND ap.deleted_at IS NULL', 'left')
            ->where('pn.person_id', $personId)
            ->where('pn.parish_id', $parishId)
            ->where('pn.deleted_at IS NULL', null, false)
            ->orderBy('pn.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array>
     */
    private function visitNotes(int $personId, int $parishId): array
    {
        return $this->db->table('person_visit')
            ->select('visit_date, attended_event, notes, created_at')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where("notes IS NOT NULL AND notes != ''", null, false)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array>
     */
    private function followupNotes(int $personId, int $parishId): array
    {
        return $this->db->table('followup_log gfl')
            ->select('gfl.id, gfl.notes, gfl.status, gfl.scheduled_date, gfl.executed_at,'
                . ' fa.channel, fa.instructions, ft.name AS template_name,'
                . ' ct.notes AS task_notes, ct.completed_at AS task_completed_at,'
                . ' au.username AS assignee_username,'
                . ' TRIM(CONCAT(COALESCE(ap.first_name, ""), " ", COALESCE(ap.last_name, ""))) AS assignee_name')
            ->join('followup_action fa', 'fa.id = gfl.action_id', 'left')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->join('call_task ct', 'ct.log_id = gfl.id', 'left')
            ->join('users au', 'au.id = ct.assigned_to', 'left')
            ->join('profile ap', 'ap.user_id = au.id AND ap.deleted_at IS NULL', 'left')
            ->where('gfl.person_id', $personId)
            ->where('gfl.parish_id', $parishId)
            ->get()
            ->getResultArray();
    }

    /**
     * @param array $row Row carrying author_username / author_name keys
     */
    private function authorLabel(array $row): ?string
    {
        $name = trim((string) ($row['author_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($row['author_username'] ?? ''));

        return $username !== '' ? $username : null;
    }
}
