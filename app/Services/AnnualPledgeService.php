<?php

namespace App\Services;

use App\Models\AnnualPledgeModel;

/**
 * AnnualPledgeService
 *
 * Domain logic for annual pledge CRUD: validation rules, parish-scoped
 * lookups, and persistence. HTTP concerns stay in the controller.
 */
class AnnualPledgeService
{
    private AnnualPledgeModel $model;

    public function __construct(?AnnualPledgeModel $model = null)
    {
        $this->model = $model ?? model(AnnualPledgeModel::class);
    }

    /**
     * @return array<string, string>
     */
    public function validationRules(bool $isCreate): array
    {
        $rules = [
            'pledged_amount'     => 'permit_empty|decimal',
            'fulfilled_amount'   => 'permit_empty|decimal',
            'fulfillment_status' => 'required|in_list[pledged,partial,fulfilled,cancelled]',
            'pledged_at'         => 'permit_empty|valid_date',
            'notes'              => 'permit_empty|max_length[500]',
        ];

        if ($isCreate) {
            $rules = ['pledge_year' => 'required|integer|greater_than_equal_to[2000]'] + $rules;
        }

        return $rules;
    }

    /**
     * Pledge scoped to a parish (tenant isolation).
     *
     * @return array<string, mixed>|null
     */
    public function findInParish(int $pledgeId, int $parishId): ?array
    {
        $pledge = $this->model->find($pledgeId);

        if (! is_array($pledge) || (int) ($pledge['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $pledge;
    }

    /**
     * Insert a pledge. Returns the new ID or null on failure
     * (call errors() for details).
     */
    public function create(array $payload): ?int
    {
        if ($this->model->insert($payload) === false) {
            return null;
        }

        return (int) $this->model->getInsertID();
    }

    public function update(int $pledgeId, array $payload): bool
    {
        return (bool) $this->model->update($pledgeId, $payload);
    }

    public function delete(int $pledgeId): bool
    {
        return (bool) $this->model->delete($pledgeId);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->model->errors() ?: [];
    }
}
