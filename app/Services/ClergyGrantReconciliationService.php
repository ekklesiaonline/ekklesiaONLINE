<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Only direct permissions created by a known office/delegation source are mutable. */
final class ClergyGrantReconciliationService
{
    private BaseConnection $db;
    private ClergyAuthorizationService $authorization;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        // This is a supervised offline reconciliation, not an authenticated
        // request. Check the actual office/grant instead of the CLI's user.
        $this->authorization = new ClergyAuthorizationService($this->db, static fn (): bool => true);
    }

    /** @return array{inspected:int, stale_sources:int, removed_sources:int, removed_permissions:int} */
    public function reconcile(bool $apply = false): array
    {
        $results = ['inspected' => 0, 'stale_sources' => 0,
            'removed_sources' => 0, 'removed_permissions' => 0];
        if (! $this->db->tableExists('permission_role_sync_managed')
            || ! $this->db->tableExists('clergy_access_grant')) {
            return $results;
        }
        $rows = $this->db->table('permission_role_sync_managed')
            ->whereIn('source_type', ['office', 'delegation'])->get()->getResultArray();
        foreach ($rows as $source) {
            $results['inspected']++;
            if ($this->sourceCurrent($source)) {
                continue;
            }
            $results['stale_sources']++;
            if (! $apply) {
                continue;
            }
            $this->db->transBegin();
            try {
                $grantQuery = $this->db->table('clergy_access_grant')
                    ->where('user_id', $source['user_id'])->where('permission', $source['permission'])
                    ->where('revoked_at', null);
                if ($source['source_type'] === 'office') {
                    $grantQuery->where('source_position_id', (int) $source['source_key'])
                        ->where('grant_type', 'office');
                } else {
                    $grantQuery->where('id', (int) $source['source_key'])
                        ->where('grant_type', 'delegation');
                }
                $grantQuery->update(['revoked_at' => date('Y-m-d H:i:s')]);
                $this->db->table('permission_role_sync_managed')->where('id', $source['id'])
                    ->where('source_type', $source['source_type'])->delete();
                $results['removed_sources']++;
                if ($this->db->table('permission_role_sync_managed')
                    ->where('permission_user_id', $source['permission_user_id'])->countAllResults() === 0) {
                    $this->db->table('auth_permissions_users')
                        ->where('id', $source['permission_user_id'])
                        ->where('user_id', $source['user_id'])
                        ->where('permission', $source['permission'])->delete();
                    $results['removed_permissions'] += max(0, $this->db->affectedRows());
                }
                if ($this->db->transStatus() === false) {
                    $this->db->transRollback();
                    throw new \RuntimeException('Grant reconciliation could not be committed.');
                }
                $this->db->transCommit();
            } catch (\Throwable $e) {
                $this->db->transRollback();
                throw $e;
            }
        }
        return $results;
    }

    private function sourceCurrent(array $source): bool
    {
        $id = (int) $source['source_key'];
        if ($id <= 0) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $builder = $this->db->table('clergy_access_grant')
            ->where('user_id', $source['user_id'])->where('permission', $source['permission'])
            ->where('revoked_at', null)->where('starts_at <=', $now)
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >=', $now)->groupEnd();
        if ($source['source_type'] === 'office') {
            $grants = $builder->where('source_position_id', $id)
                ->where('grant_type', 'office')->get()->getResultArray();
            $office = $this->db->table('leadership_position')->where('id', $id)->get()->getRowArray();
            if ($office === null || ! $this->authorization->officeEligible(
                (int) $source['user_id'], $id, (string) $source['permission'])) {
                return false;
            }
            $scope = (string) $office['scope_type'];
            foreach ($grants as $grant) {
                if ($grant['scope_type'] === $scope
                    && (int) $grant['scope_id'] === (int) ($office[$scope . '_id'] ?? 0)) {
                    return true;
                }
            }
            return false;
        }
        $grant = $builder->where('id', $id)->where('grant_type', 'delegation')->get()->getRowArray();
        if ($grant === null) {
            return false;
        }
        $targetType = $grant['scope_type'] === 'diocese' ? 'diocese' : 'parish';
        if (! in_array($grant['scope_type'], ['diocese', 'parish'], true)) {
            return false;
        }
        $effective = $this->authorization->matchingGrant((int) $source['user_id'],
            (string) $source['permission'], $targetType, (int) $grant['scope_id']);
        return $effective !== null && (int) $effective['id'] === $id;
    }
}
