<?php

namespace App\Services;

use App\Models\FollowupSequenceModel;

class FollowupSequenceService
{
    private FollowupSequenceModel $sequenceModel;

    public function __construct(?FollowupSequenceModel $sequenceModel = null)
    {
        $this->sequenceModel = $sequenceModel ?? model(FollowupSequenceModel::class);
    }

    /**
     * Return the default sequence for a parish, or null if none is set.
     */
    public function getDefaultForParish(int $parishId): ?array
    {
        $row = $this->sequenceModel
            ->where('parish_id', $parishId)
            ->where('is_active', 1)
            ->where('is_default', 1)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * Whether the parish has a designated default sequence.
     */
    public function hasDefaultForParish(int $parishId): bool
    {
        return $this->getDefaultForParish($parishId) !== null;
    }

    /**
     * Mark a sequence as the default for its parish and unset any other
     * default in the same parish.
     */
    public function setDefaultForSequence(int $sequenceId, int $parishId): bool
    {
        $db = $this->sequenceModel->db;

        try {
            $db->transStart();

            $db->table('followup_sequence')
                ->where('parish_id', $parishId)
                ->where('id !=', $sequenceId)
                ->update(['is_default' => 0]);

            $this->sequenceModel->update($sequenceId, ['is_default' => 1]);

            $db->transComplete();

            return $db->transStatus() !== false;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Failed to set default follow-up sequence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the global default sequence used as a template for new parishes.
     */
    public function getGlobalDefaultTemplateSequence(): ?array
    {
        $row = $this->sequenceModel
            ->where('parish_id IS NULL', null, false)
            ->where('diocese_id IS NULL', null, false)
            ->where('is_active', 1)
            ->where('is_global_default', 1)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * Return the diocesan default sequence for a diocese, if any.
     */
    public function getDioceseDefaultSequence(int $dioceseId): ?array
    {
        return $this->sequenceModel->findDioceseDefault($dioceseId);
    }

    /**
     * Resolve the upstream sequence that should be cloned into a parish with
     * no default of its own. Cascade order: diocesan default → global
     * default.
     */
    public function resolveUpstreamDefaultForParish(int $parishId): ?array
    {
        $dioceseId = $this->sequenceModel->parishDioceseId($parishId);

        if ($dioceseId > 0) {
            $seq = $this->getDioceseDefaultSequence($dioceseId);
            if ($seq !== null) {
                return $seq;
            }
        }

        return $this->getGlobalDefaultTemplateSequence();
    }

    /**
     * Resolve the upstream sequence a diocese can clone: the global default.
     */
    public function resolveUpstreamDefaultForDiocese(int $dioceseId): ?array
    {
        return $this->getGlobalDefaultTemplateSequence();
    }

    /**
     * Mark a diocesan sequence as the diocese default; unsets other diocesan
     * defaults within the same diocese.
     */
    public function setDioceseDefaultSequence(int $sequenceId, int $dioceseId): bool
    {
        return $this->setScopedDefault($sequenceId, 'diocese_id', $dioceseId, 'is_diocese_default');
    }

    /**
     * Shared scoped-default setter: clears sibling defaults in the same
     * scope, then marks the target row.
     */
    private function setScopedDefault(int $sequenceId, string $scopeColumn, int $scopeId, string $flagColumn): bool
    {
        $db = $this->sequenceModel->db;

        try {
            $db->transStart();

            $db->table('followup_sequence')
                ->where($scopeColumn, $scopeId)
                ->where('id !=', $sequenceId)
                ->update([$flagColumn => 0]);

            $this->sequenceModel->update($sequenceId, [$flagColumn => 1]);

            $db->transComplete();

            return $db->transStatus() !== false;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', "Failed to set {$flagColumn} on follow-up sequence {$sequenceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark a global sequence as the default template and unset all others.
     */
    public function setGlobalDefaultSequence(int $sequenceId): bool
    {
        $db = $this->sequenceModel->db;

        try {
            $db->transStart();

            $db->table('followup_sequence')
                ->where('parish_id IS NULL', null, false)
                ->where('diocese_id IS NULL', null, false)
                ->where('id !=', $sequenceId)
                ->update(['is_global_default' => 0]);

            $this->sequenceModel->update($sequenceId, [
                'is_global_default' => 1,
                'parish_id'         => null,
            ]);

            $db->transComplete();

            return $db->transStatus() !== false;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Failed to set global default follow-up sequence: ' . $e->getMessage());
            return false;
        }
    }
}
