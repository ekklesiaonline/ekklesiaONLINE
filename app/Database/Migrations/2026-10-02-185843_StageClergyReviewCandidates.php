<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Existing group and leadership rows are unverified evidence, not entitlements. */
class StageClergyReviewCandidates extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('clergy_transition_candidate')) {
            throw new RuntimeException('Apply clergy/deanery foundation before staging review.');
        }
        $now = date('Y-m-d H:i:s');
        if ($this->db->tableExists('auth_groups_users')) {
            $rows = $this->db->table('auth_groups_users')->select('id, user_id')
                ->where('group', 'clergy')->get()->getResultArray();
            foreach ($rows as $row) {
                $userId = (int) $row['user_id'];
                $this->stage([
                    'source_type' => 'legacy_group',
                    'source_id' => (int) $row['id'],
                    'user_id' => $userId,
                    'person_id' => $this->personId($userId),
                    'proposed_order' => null,
                    'proposed_office' => null,
                    'scope_type' => null,
                    'scope_id' => null,
                    'review_status' => 'pending',
                    'created_at' => $now,
                ]);
            }
        }
        if (! $this->db->tableExists('leadership_position')) {
            return;
        }
        $rows = $this->db->table('leadership_position')->select(
            'id, user_id, person_id, role_code, role_category, clergy_order, scope_type, parish_id, diocese_id, province_id'
        )->get()->getResultArray();
        foreach ($rows as $row) {
            if (($row['role_category'] ?? '') !== 'clergy' && empty($row['clergy_order'])) {
                continue;
            }
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue; // Account-less people are handled by the separate registry intake.
            }
            $order = (string) ($row['clergy_order'] ?? '');
            $scope = (string) ($row['scope_type'] ?? '');
            $column = ['parish' => 'parish_id', 'diocese' => 'diocese_id', 'province' => 'province_id'][$scope] ?? null;
            $role = trim((string) ($row['role_code'] ?? ''));
            $this->stage([
                'source_type' => 'legacy_position',
                'source_id' => (int) $row['id'],
                'user_id' => $userId,
                'person_id' => $this->personId($userId, (int) ($row['person_id'] ?? 0)),
                'proposed_order' => in_array($order, ['bishop', 'priest', 'deacon'], true) ? $order : null,
                'proposed_office' => $role !== '' ? substr($role, 0, 100) : null,
                'scope_type' => $column !== null ? $scope : null,
                'scope_id' => $column !== null && (int) ($row[$column] ?? 0) > 0 ? (int) $row[$column] : null,
                'review_status' => 'pending',
                'created_at' => $now,
            ]);
        }
        // No changes to Shield group memberships or direct permissions.
    }

    public function down()
    {
        $candidate = $this->db->table('clergy_transition_candidate');
        if ($candidate->whereIn('source_type', ['legacy_group', 'legacy_position'])
            ->where('review_status !=', 'pending')->countAllResults() > 0) {
            throw new RuntimeException('Cannot discard reviewed clergy evidence; use a forward-fix.');
        }
        $this->db->table('clergy_transition_candidate')
            ->whereIn('source_type', ['legacy_group', 'legacy_position'])->delete();
    }

    private function stage(array $candidate): void
    {
        $exists = $this->db->table('clergy_transition_candidate')
            ->where('source_type', $candidate['source_type'])
            ->where('source_id', $candidate['source_id'])->countAllResults();
        if ($exists === 0) {
            $this->db->table('clergy_transition_candidate')->insert($candidate);
        }
    }

    private function personId(int $userId, int $positionPersonId = 0): ?int
    {
        $links = $this->db->tableExists('user_person')
            ? $this->db->table('user_person')->select('person_id, is_primary')
                ->where('user_id', $userId)->get()->getResultArray() : [];
        $primary = array_values(array_filter($links, static fn (array $r): bool => (int) $r['is_primary'] === 1));
        if (count($primary) > 1 || (count($primary) === 0 && count($links) > 1)) {
            return null;
        }
        $personId = (int) (($primary[0]['person_id'] ?? $links[0]['person_id'] ?? 0));
        if ($personId <= 0 && $this->db->tableExists('profile')
            && $this->db->fieldExists('person_id', 'profile')) {
            $profile = $this->db->table('profile')->select('person_id')
                ->where('user_id', $userId)->get()->getRowArray();
            $personId = (int) ($profile['person_id'] ?? 0);
        }
        if ($positionPersonId > 0 && $personId > 0 && $personId !== $positionPersonId) {
            return null;
        }
        $personId = $personId ?: $positionPersonId;
        return $personId > 0 && $this->db->table('person')->where('id', $personId)->countAllResults() > 0
            ? $personId : null;
    }
}
