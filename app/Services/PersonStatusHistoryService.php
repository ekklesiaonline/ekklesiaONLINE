<?php

namespace App\Services;

use App\Models\PersonStatusHistoryModel;

class PersonStatusHistoryService
{
    private PersonStatusHistoryModel $model;

    public function __construct(?PersonStatusHistoryModel $model = null)
    {
        $this->model = $model ?? model(PersonStatusHistoryModel::class);
    }

    /**
     * Record a status transition.
     *
     * @param array<string, mixed> $extra
     */
    public function record(
        int $personId,
        string $statusType,
        ?string $fromStatus,
        string $toStatus,
        ?int $parishId = null,
        ?int $changedBy = null,
        ?string $reason = null,
        array $extra = []
    ): bool {
        $validTypes = ['affiliation', 'onboarding', 'membership_detail'];
        if (!in_array($statusType, $validTypes, true)) {
            throw new \InvalidArgumentException('Invalid status type: ' . $statusType);
        }

        if ($fromStatus === $toStatus) {
            return true;
        }

        $data = [
            'person_id'   => $personId,
            'parish_id'   => $parishId,
            'status_type' => $statusType,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'changed_by'  => $changedBy,
            'reason'      => $reason,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if (! $this->model->db->tableExists($this->model->getTableName())) {
            return true;
        }

        foreach (['parish_id', 'changed_by', 'reason', 'from_status'] as $nullable) {
            if (!array_key_exists($nullable, $data) || $data[$nullable] === null) {
                $data[$nullable] = null;
            }
        }

        return $this->model->insert($data) !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForPerson(int $personId, ?string $statusType = null, ?int $parishId = null, int $limit = 100): array
    {
        $builder = $this->model->where('person_id', $personId);

        if ($statusType !== null) {
            $builder->where('status_type', $statusType);
        }

        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }

        return $builder
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }
}
