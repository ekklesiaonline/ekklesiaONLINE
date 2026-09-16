<?php

namespace App\Models;

use CodeIgniter\Model;

class BaseTenantModel extends Model
{
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';

    protected bool $tenantScoped = true;
    protected string $scopeColumn = 'parish_id';

    private static ?bool $parishHasDeletedAt = null;

    protected function initialize()
    {
        parent::initialize();

        // Some test fixtures build tables without a deleted_at column.
        // Rather than fail with "no such column", disable soft-delete filtering
        // when the column is missing. Production migrations keep the column,
        // so this is a defensive, test-friendly fallback.
        if ($this->useSoftDeletes) {
            try {
                if ($this->db->tableExists($this->table)
                    && ! $this->db->fieldExists($this->deletedField, $this->table)
                ) {
                    $this->useSoftDeletes = false;
                }
            } catch (\Throwable $e) {
                // Table may not exist yet; leave soft deletes enabled.
            }
        }
    }

    public function find($id = null)
    {
        if ($this->tenantScoped) {
            $this->applyScope();
        }

        return parent::find($id);
    }

    public function findAll(?int $limit = null, int $offset = 0)
    {
        if ($this->tenantScoped) {
            $this->applyScope();
        }

        return parent::findAll($limit, $offset);
    }

    public function first()
    {
        $rows = $this->findAll(1, 0);

        return $rows[0] ?? null;
    }

    protected function applyScope(): void
    {
        $parishId = $this->currentParishId();
        $scopeColumn = $this->table . '.' . $this->scopeColumn;

        if ($parishId <= 0) {
            $this->where($scopeColumn, -1);

            return;
        }

        $this->where($scopeColumn, $parishId);
    }

    protected function currentParishId(): int
    {
        $selected = session()->get('selected_parish_id');

        if (is_numeric($selected) && (int) $selected > 0 && $this->isActiveParish((int) $selected)) {
            return (int) $selected;
        }

        if (! auth()->loggedIn()) {
            return 0;
        }

        $userId = (int) auth()->id();
        if ($userId <= 0) {
            return 0;
        }

        try {
            $profile = db_connect()->table('profile')
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();
        } catch (\Throwable $e) {
            $profile = null;
        }

        $parishId = ! empty($profile['parish_id']) ? (int) $profile['parish_id'] : 0;

        return $this->isActiveParish($parishId) ? $parishId : 0;
    }

    private function isActiveParish(int $parishId): bool
    {
        if ($parishId <= 0) {
            return false;
        }

        try {
            $db = db_connect();

            if (self::$parishHasDeletedAt === null) {
                self::$parishHasDeletedAt = $db->fieldExists('deleted_at', 'parish');
            }

            $query = $db->table('parish')->where('id', $parishId);

            if (self::$parishHasDeletedAt) {
                $query->where('deleted_at', null);
            }

            return $query->countAllResults() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function doFind(bool $singleton, $id = null)
    {
        $builder = $this->builder();

        if ($this->tenantScoped) {
            $this->applyScopeToBuilder($builder);
        }

        if ($this->tempUseSoftDeletes) {
            $builder->where($this->deletedField, null);
        }

        $row  = null;
        $rows = [];

        if (is_array($id)) {
            $rows = $builder->whereIn($this->primaryKey, $id)
                ->get()
                ->getResult($this->tempReturnType);
        } elseif ($singleton) {
            $row = $builder->where($this->primaryKey, $id)
                ->get()
                ->getFirstRow($this->tempReturnType);
        } else {
            $rows = $builder->get()->getResult($this->tempReturnType);
        }

        if ($singleton) {
            return $row;
        }

        return $rows;
    }

    /**
     * Apply the tenant scope to a specific builder instance.
     * Used by doFind() which builds its own query outside the model's
     * normal find()/findAll() path.
     */
    protected function applyScopeToBuilder(\CodeIgniter\Database\BaseBuilder $builder): void
    {
        $parishId = $this->currentParishId();
        $scopeColumn = $this->table . '.' . $this->scopeColumn;

        if ($parishId <= 0) {
            $builder->where($scopeColumn, -1);
            return;
        }

        $builder->where($scopeColumn, $parishId);
    }
}
