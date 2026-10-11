<?php

namespace App\Models;

class AccountingPeriodModel extends BaseTenantModel
{
    protected $table            = 'accounting_period';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'fiscal_year',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id'            => '?int',
        'parish_id'     => '?int',
        'fiscal_year'   => '?int',
        'period_number' => '?int',
    ];

    protected $validationRules = [
        'parish_id'     => 'required|integer',
        'fiscal_year'   => 'required|integer',
        'period_number' => 'required|integer',
        'period_name'   => 'required|max_length[50]',
        'start_date'    => 'required|valid_date',
        'end_date'      => 'required|valid_date',
        'status'        => 'permit_empty|in_list[open,soft_closed,closed]',
    ];

    /**
     * Finds the accounting period covering a given transaction date for a parish.
     */
    public function findPeriodForDate(int $parishId, string $date): ?array
    {
        return $this->where('parish_id', $parishId)
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->first();
    }

    /**
     * Checks if a given transaction date falls within a closed accounting period.
     */
    public function isDateInClosedPeriod(int $parishId, string $date): bool
    {
        $period = $this->findPeriodForDate($parishId, $date);

        if (!$period) {
            return false;
        }

        return ($period['status'] ?? 'open') === 'closed';
    }

    /**
     * Checks if a period by ID is closed.
     */
    public function isPeriodClosed(int $periodId): bool
    {
        $period = $this->find($periodId);

        return $period !== null && ($period['status'] ?? 'open') === 'closed';
    }

    /**
     * Closes an accounting period, locking transaction mutations.
     */
    public function closePeriod(int $periodId, ?string $closedAt = null): bool
    {
        return (bool) $this->update($periodId, [
            'status'    => 'closed',
            'closed_at' => $closedAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reopens a closed accounting period.
     */
    public function reopenPeriod(int $periodId): bool
    {
        return (bool) $this->update($periodId, [
            'status'    => 'open',
            'closed_at' => null,
        ]);
    }

    /**
     * Retrieves all accounting periods for a parish.
     */
    public function getParishPeriods(int $parishId, ?int $fiscalYear = null): array
    {
        $builder = $this->where('parish_id', $parishId);

        if ($fiscalYear !== null && $fiscalYear > 0) {
            $builder->where('fiscal_year', $fiscalYear);
        }

        return $builder->orderBy('fiscal_year', 'DESC')
            ->orderBy('period_number', 'ASC')
            ->findAll();
    }
}
