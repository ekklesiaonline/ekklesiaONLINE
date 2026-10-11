<?php

namespace App\Models;

class JournalEntryModel extends BaseTenantModel
{
    protected $table            = 'journal_entry';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'entry_number',
        'period_id',
        'entry_date',
        'source_type',
        'source_id',
        'memo',
        'status',
        'posted_by',
        'posted_at',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected array $casts = [
        'id'          => '?int',
        'parish_id'   => '?int',
        'period_id'   => '?int',
        'source_id'   => '?int',
        'posted_by'   => '?int',
        'created_by'  => '?int',
    ];

    protected $validationRules = [
        'parish_id'    => 'required|integer',
        'entry_number' => 'required|max_length[50]',
        'entry_date'   => 'required|valid_date',
        'status'       => 'permit_empty|in_list[draft,posted,void]',
    ];

    /**
     * Fetch filtered journal entries for a parish with line count and total debit/credit amounts.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredEntries(int $parishId, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $builder = $this->db->table($this->table . ' je')
            ->select('je.*, ' .
                'COALESCE(SUM(jel.debit_amount), 0.0) AS total_debit, ' .
                'COALESCE(SUM(jel.credit_amount), 0.0) AS total_credit, ' .
                'COUNT(jel.id) AS line_count')
            ->join('journal_entry_line jel', 'jel.journal_entry_id = je.id', 'left')
            ->where('je.parish_id', $parishId);

        $this->applyEntryFilters($builder, $filters);

        $results = $builder
            ->groupBy('je.id')
            ->orderBy('je.entry_date', 'DESC')
            ->orderBy('je.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        foreach ($results as &$row) {
            $row['total_debit'] = (float) ($row['total_debit'] ?? 0.0);
            $row['total_credit'] = (float) ($row['total_credit'] ?? 0.0);
            $row['line_count'] = (int) ($row['line_count'] ?? 0);
        }

        return $results;
    }

    /**
     * Count total entries matching the given filters.
     *
     * @param array<string, mixed> $filters
     */
    public function countFilteredEntries(int $parishId, array $filters = []): int
    {
        $builder = $this->db->table($this->table . ' je')
            ->where('je.parish_id', $parishId);

        $this->applyEntryFilters($builder, $filters);

        return (int) $builder->countAllResults();
    }

    /**
     * Compute total debits and credits across all filtered entries for summary cards.
     *
     * @param array<string, mixed> $filters
     * @return array{total_entries: int, total_debits: float, total_credits: float}
     */
    public function getEntriesSummary(int $parishId, array $filters = []): array
    {
        $builder = $this->db->table($this->table . ' je')
            ->select('COUNT(DISTINCT je.id) as total_entries, ' .
                'COALESCE(SUM(jel.debit_amount), 0.0) as total_debits, ' .
                'COALESCE(SUM(jel.credit_amount), 0.0) as total_credits')
            ->join('journal_entry_line jel', 'jel.journal_entry_id = je.id', 'left')
            ->where('je.parish_id', $parishId);

        $this->applyEntryFilters($builder, $filters);

        $row = $builder->get()->getRowArray();

        return [
            'total_entries' => (int) ($row['total_entries'] ?? 0),
            'total_debits'  => (float) ($row['total_debits'] ?? 0.0),
            'total_credits' => (float) ($row['total_credits'] ?? 0.0),
        ];
    }

    /**
     * Retrieve a journal entry with all line items and chart account metadata.
     */
    public function getEntryWithLines(int $entryId, int $parishId): ?array
    {
        $entry = $this->where('id', $entryId)
            ->where('parish_id', $parishId)
            ->first();

        if (!$entry) {
            return null;
        }

        $lines = $this->db->table('journal_entry_line jel')
            ->select('jel.*, coa.code as account_code, coa.name as account_name, coa.account_type')
            ->join('chart_of_accounts coa', 'coa.id = jel.chart_account_id', 'left')
            ->where('jel.journal_entry_id', $entryId)
            ->orderBy('jel.id', 'ASC')
            ->get()
            ->getResultArray();

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as &$line) {
            $line['debit_amount'] = (float) ($line['debit_amount'] ?? 0.0);
            $line['credit_amount'] = (float) ($line['credit_amount'] ?? 0.0);
            $totalDebit += $line['debit_amount'];
            $totalCredit += $line['credit_amount'];
        }

        $entry['lines'] = $lines;
        $entry['total_debit'] = $totalDebit;
        $entry['total_credit'] = $totalCredit;

        return $entry;
    }

    /**
     * Apply filter criteria to the query builder.
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     * @param array<string, mixed> $filters
     */
    protected function applyEntryFilters(&$builder, array $filters): void
    {
        if (!empty($filters['start_date'])) {
            $builder->where('je.entry_date >=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('je.entry_date <=', (string) $filters['end_date']);
        }

        if (!empty($filters['source_type'])) {
            $builder->where('je.source_type', (string) $filters['source_type']);
        }

        if (!empty($filters['status'])) {
            $builder->where('je.status', (string) $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $builder->groupStart()
                ->like('je.entry_number', $term)
                ->orLike('je.memo', $term)
                ->groupEnd();
        }

        if (!empty($filters['chart_account_id']) && (int) $filters['chart_account_id'] > 0) {
            $lineTable = $this->db->prefixTable('journal_entry_line');
            $builder->where("je.id IN (SELECT journal_entry_id FROM {$lineTable} WHERE chart_account_id = " . (int) $filters['chart_account_id'] . ')', null, false);
        }
    }
}

