<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lift-evidence columns on clergy_canonical_status, mirroring the
 * approval_evidence_reference/approved_by_user_id/revoked_at pattern on
 * leadership_position. A lift closes a suspended/inhibited row via
 * effective_to; these columns record who lifted it and on what evidence.
 */
class AddLiftColumnsToClergyCanonicalStatus extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clergy_canonical_status', [
            'lift_evidence_reference' => $this->text(255, true),
            'lifted_by_user_id'       => $this->number(11, true),
            'lifted_at'               => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('clergy_canonical_status', [
            'lift_evidence_reference', 'lifted_by_user_id', 'lifted_at',
        ]);
    }

    private function number(int $width = 11, bool $nullable = false, ?int $default = null): array
    {
        $field = ['type' => 'INT', 'constraint' => $width, 'unsigned' => true, 'null' => $nullable];
        if ($default !== null) {
            $field['default'] = $default;
        }
        return $field;
    }

    private function text(int $width, bool $nullable = false, ?string $default = null): array
    {
        $field = ['type' => 'VARCHAR', 'constraint' => $width, 'null' => $nullable];
        if ($default !== null) {
            $field['default'] = $default;
        }
        return $field;
    }
}
