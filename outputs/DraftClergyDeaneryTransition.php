<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * DRAFT ONLY: generated with php spark make:migration, then moved to outputs/.
 * Not installed in app/Database/Migrations and not run against a database.
 * MySQL 8 expansion + pending review candidates; NO new privileges are issued.
 * Inspect live schema, back up, split/rehearse on a clone before installation.
 */
final class DraftClergyDeaneryTransition extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            throw new RuntimeException('Draft requires reviewed MySQL 8 schema; not for SQLite tests.');
        }
        foreach (['person', 'users', 'profile', 'user_person', 'diocese', 'parish', 'auth_groups_users', 'leadership_position'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException("Required legacy table missing: {$table}");
            }
        }
        if (! $this->db->fieldExists('person_id', 'leadership_position')) {
            throw new RuntimeException('Audit and complete leadership_position.person_id repair first.');
        }
        $this->createDeaneryTables();
        $this->createClergyTables();
        $this->extendLeadershipPosition();
        $this->stageCandidates();
    }

    public function down()
    {
        // A destructive down() could erase reviewed clergy/office history.
        throw new RuntimeException('Forward-fix or restore a pre-migration backup; no automatic data rollback.');
    }

    private function t(string $table): string
    {
        return '`' . str_replace('`', '``', $this->db->prefixTable($table)) . '`';
    }

    private function createDeaneryTables(): void
    {
        $deanery = $this->t('deanery');
        $membership = $this->t('deanery_parish_membership');
        $diocese = $this->t('diocese');
        $parish = $this->t('parish');
        $this->db->query("CREATE TABLE IF NOT EXISTS {$deanery} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            diocese_id INT UNSIGNED NOT NULL,
            code VARCHAR(64) NOT NULL,
            name VARCHAR(150) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL, updated_at DATETIME NULL,
            UNIQUE KEY uq_deanery_diocese_code (diocese_id, code),
            KEY idx_deanery_diocese (diocese_id, active),
            CONSTRAINT fk_clergy_deanery_diocese FOREIGN KEY (diocese_id)
                REFERENCES {$diocese}(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$membership} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            deanery_id INT UNSIGNED NOT NULL,
            parish_id INT UNSIGNED NOT NULL,
            started_at DATE NOT NULL, ended_at DATE NULL,
            appointed_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NULL, updated_at DATETIME NULL,
            UNIQUE KEY uq_dpm_parish_start (parish_id, started_at),
            KEY idx_dpm_deanery_dates (deanery_id, started_at, ended_at),
            CONSTRAINT fk_dpm_deanery FOREIGN KEY (deanery_id)
                REFERENCES {$deanery}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_dpm_parish FOREIGN KEY (parish_id)
                REFERENCES {$parish}(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Same-diocese and nonoverlapping membership intervals require an
        // explicit locked domain-service check; see companion specification.
    }

    private function createClergyTables(): void
    {
        $person = $this->t('person');
        $users = $this->t('users');
        $diocese = $this->t('diocese');
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->t('clergy_ordination') . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            person_id INT UNSIGNED NOT NULL,
            order_code ENUM('deacon','priest','bishop') NOT NULL,
            deacon_track ENUM('vocational','transitional') NULL,
            ordained_on DATE NULL,
            verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
            verified_by_user_id INT UNSIGNED NULL, verified_at DATETIME NULL,
            created_at DATETIME NULL, updated_at DATETIME NULL,
            KEY idx_clergy_order_person (person_id, verification_status, order_code),
            CONSTRAINT fk_clergy_order_person FOREIGN KEY (person_id)
                REFERENCES {$person}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_clergy_order_verifier FOREIGN KEY (verified_by_user_id)
                REFERENCES {$users}(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->t('clergy_canonical_status') . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            person_id INT UNSIGNED NOT NULL, diocese_id INT UNSIGNED NOT NULL,
            status_code ENUM('resident','licensed','transferred','retired','suspended','inhibited') NOT NULL,
            effective_from DATE NOT NULL, effective_to DATE NULL,
            verified_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NULL, updated_at DATETIME NULL,
            KEY idx_clergy_status_dates (person_id, diocese_id, effective_from, effective_to),
            CONSTRAINT fk_canonical_person FOREIGN KEY (person_id)
                REFERENCES {$person}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_canonical_diocese FOREIGN KEY (diocese_id)
                REFERENCES {$diocese}(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->t('clergy_transition_candidate') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_type ENUM('legacy_group','legacy_position') NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL, person_id INT UNSIGNED NULL,
            proposed_order ENUM('deacon','priest','bishop') NULL,
            proposed_office VARCHAR(100) NULL,
            scope_type ENUM('parish','deanery','diocese','province') NULL,
            scope_id INT UNSIGNED NULL,
            review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by_user_id INT UNSIGNED NULL, reviewed_at DATETIME NULL,
            created_at DATETIME NULL,
            UNIQUE KEY uq_clergy_candidate_source (source_type, source_id),
            KEY idx_clergy_candidate_user (user_id, review_status),
            CONSTRAINT fk_candidate_user FOREIGN KEY (user_id)
                REFERENCES {$users}(id) ON DELETE RESTRICT,
            CONSTRAINT fk_candidate_person FOREIGN KEY (person_id)
                REFERENCES {$person}(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Candidate status does not imply canonical ordination or authorization.
    }

    private function extendLeadershipPosition(): void
    {
        if (! $this->db->fieldExists('deanery_id', 'leadership_position')) {
            $this->forge->addColumn('leadership_position', [
                'deanery_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'parish_id'],
            ]);
            $this->db->query('CREATE INDEX idx_leadership_deanery_active ON '
                . $this->t('leadership_position') . ' (deanery_id, active)');
            $this->db->query('ALTER TABLE ' . $this->t('leadership_position')
                . ' ADD CONSTRAINT fk_leadership_deanery FOREIGN KEY (deanery_id) REFERENCES '
                . $this->t('deanery') . ' (id) ON DELETE RESTRICT');
        }
        $this->db->query('ALTER TABLE ' . $this->t('leadership_position')
            . " MODIFY COLUMN scope_type ENUM('parish','deanery','diocese','province') NOT NULL DEFAULT 'parish'");
        // Existing appointments and user_id NOT NULL are intentionally unchanged.
    }

    private function stageCandidates(): void
    {
        $now = date('Y-m-d H:i:s');
        $builder = $this->db->table('clergy_transition_candidate');
        foreach ($this->db->table('auth_groups_users')->select('id, user_id')
            ->where('group', 'clergy')->get()->getResultArray() as $row) {
            $userId = (int) $row['user_id'];
            $this->insertCandidate($builder, [
                'source_type' => 'legacy_group', 'source_id' => (int) $row['id'],
                'user_id' => $userId, 'person_id' => $this->personId($userId),
                'proposed_order' => null, 'proposed_office' => null,
                'scope_type' => null, 'scope_id' => null,
                'review_status' => 'pending', 'created_at' => $now,
            ]);
        }
        foreach ($this->db->table('leadership_position')->select(
            'id, user_id, person_id, role_code, role_category, clergy_order, scope_type, parish_id, diocese_id, province_id'
        )->get()->getResultArray() as $row) {
            if (($row['role_category'] ?? '') !== 'clergy' && empty($row['clergy_order'])) {
                continue;
            }
            $userId = (int) $row['user_id'];
            if ($userId <= 0) { // Unlinked persons are imported separately.
                continue;
            }
            $order = (string) ($row['clergy_order'] ?? '');
            $scopeType = (string) ($row['scope_type'] ?? '');
            $column = ['parish' => 'parish_id', 'diocese' => 'diocese_id', 'province' => 'province_id'][$scopeType] ?? null;
            $role = trim((string) ($row['role_code'] ?? ''));
            $this->insertCandidate($builder, [
                'source_type' => 'legacy_position', 'source_id' => (int) $row['id'],
                'user_id' => $userId,
                'person_id' => $this->personId($userId, (int) ($row['person_id'] ?? 0)),
                'proposed_order' => in_array($order, ['deacon', 'priest', 'bishop'], true) ? $order : null,
                'proposed_office' => $role !== '' ? substr($role, 0, 100) : null,
                'scope_type' => $column !== null ? $scopeType : null,
                'scope_id' => $column !== null && (int) ($row[$column] ?? 0) > 0 ? (int) $row[$column] : null,
                'review_status' => 'pending', 'created_at' => $now,
            ]);
        }
        // No deaneries are inferred, no Shield groups/permissions are changed.
    }

    private function insertCandidate($builder, array $row): void
    {
        $exists = $builder->where('source_type', $row['source_type'])
            ->where('source_id', $row['source_id'])->countAllResults();
        if ($exists === 0) {
            $builder->insert($row);
        }
    }

    private function personId(int $userId, int $positionPersonId = 0): ?int
    {
        $links = $this->db->table('user_person')->select('person_id, is_primary')
            ->where('user_id', $userId)->get()->getResultArray();
        $primary = array_values(array_filter($links, static fn ($r): bool => (int) $r['is_primary'] === 1));
        if (count($primary) > 1 || (count($primary) === 0 && count($links) > 1)) {
            return null; // Ambiguous link: manual review.
        }
        $linked = $primary[0] ?? ($links[0] ?? null);
        $personId = (int) ($linked['person_id'] ?? 0);
        if ($personId === 0) {
            $profile = $this->db->table('profile')->select('person_id')
                ->where('user_id', $userId)->get()->getRowArray();
            $personId = (int) ($profile['person_id'] ?? 0);
        }
        if ($positionPersonId > 0 && $personId > 0 && $positionPersonId !== $personId) {
            return null;
        }
        $personId = $personId ?: $positionPersonId;
        return $personId > 0 && $this->db->table('person')->where('id', $personId)->countAllResults() > 0
            ? $personId : null;
    }
}
