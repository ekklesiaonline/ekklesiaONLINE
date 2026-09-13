<?php

namespace App\Services;

use App\Models\PersonOnboardingModel;

class PersonOnboardingService
{
    private PersonOnboardingModel $model;
    private PersonStatusHistoryService $history;

    public function __construct(?PersonOnboardingModel $model = null, ?PersonStatusHistoryService $history = null)
    {
        $this->model   = $model ?? model(PersonOnboardingModel::class);
        $this->history = $history ?? new PersonStatusHistoryService();
    }

    public function start(int $personId, ?int $parishId = null, ?int $changedBy = null): bool
    {
        $existing = $this->model
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('onboarding_status', 'pending')
            ->first();

        if ($existing) {
            return true;
        }

        $result = $this->model->insert([
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'onboarding_status' => 'pending',
            'started_at'        => date('Y-m-d H:i:s'),
        ]) !== false;

        if ($result) {
            $this->history->record(
                $personId,
                'onboarding',
                null,
                'pending',
                $parishId,
                $changedBy,
                'Onboarding started automatically on profile creation.'
            );
        }

        return $result;
    }

    public function advance(int $personId, string $status, ?int $parishId = null, ?int $changedBy = null, ?string $reason = null): bool
    {
        $valid = ['pending', 'in_progress', 'completed', 'rejected'];
        if (!in_array($status, $valid, true)) {
            throw new \InvalidArgumentException('Invalid onboarding status: ' . $status);
        }

        $existing = $this->model
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->first();

        $fromStatus = $existing['onboarding_status'] ?? null;

        $now = date('Y-m-d H:i:s');
        $data = [
            'onboarding_status' => $status,
            'updated_at'        => $now,
        ];

        if ($status === 'completed') {
            $data['completed_at'] = $now;
        }

        if ($existing) {
            $result = $this->model->update((int) $existing['id'], $data);
        } else {
            $data['person_id']  = $personId;
            $data['parish_id']  = $parishId;
            $data['started_at'] = $now;
            $data['created_at'] = $now;

            $result = $this->model->insert($data) !== false;
        }

        if ($result) {
            $this->history->record(
                $personId,
                'onboarding',
                $fromStatus,
                $status,
                $parishId,
                $changedBy,
                $reason
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forPerson(int $personId, ?int $parishId = null): ?array
    {
        $builder = $this->model->where('person_id', $personId);
        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }

        return $builder->first();
    }
}
