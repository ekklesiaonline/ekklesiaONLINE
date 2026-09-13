<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforces province boundaries for province admin workflows.
 *
 * This filter validates and sanitizes shared session scope state used by
 * common routines (selected parish/diocese) to avoid cross-province leakage.
 */
class ProvinceScopeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = service('request');

        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if (!$user || !$user->inGroup('provinceadmin')) {
            return null;
        }

        $provinceId = $this->resolveProvinceId((int) auth()->id());
        if ($provinceId <= 0) {
            session()->remove('province_scope_id');
            session()->remove('province_selected_diocese_id');

            return null;
        }

        // Keep canonical province scope in session for dependent modules.
        session()->set('province_scope_id', $provinceId);

        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0 && !$this->parishBelongsToProvince($selectedParishId, $provinceId)) {
            session()->remove('selected_parish_id');
        }

        $selectedDioceseId = (int) (session()->get('province_selected_diocese_id') ?? 0);
        if ($selectedDioceseId > 0 && !$this->dioceseBelongsToProvince($selectedDioceseId, $provinceId)) {
            session()->remove('province_selected_diocese_id');
        }

        // Validate explicit query/body scope selectors.
        $dioceseIdRaw = $incoming->getGet('diocese_id');
        if ($dioceseIdRaw === null) {
            $dioceseIdRaw = $incoming->getPost('diocese_id');
        }
        $dioceseId = (int) ($dioceseIdRaw ?? 0);
        if ($dioceseId > 0 && !$this->dioceseBelongsToProvince($dioceseId, $provinceId)) {
            return $this->deny($incoming, 'Diocese is outside your province scope.');
        }

        $parishIdRaw = $incoming->getGet('parish_id');
        if ($parishIdRaw === null) {
            $parishIdRaw = $incoming->getPost('parish_id');
        }
        $parishId = (int) ($parishIdRaw ?? 0);
        if ($parishId > 0 && !$this->parishBelongsToProvince($parishId, $provinceId)) {
            return $this->deny($incoming, 'Parish is outside your province scope.');
        }

        // Guard explicit parish switch route: /dashboard/switch/{parishId}
        $segments = $request->getUri()->getSegments();
        if (
            count($segments) >= 3
            && $segments[0] === 'dashboard'
            && $segments[1] === 'switch'
            && ctype_digit((string) $segments[2])
        ) {
            $targetParishId = (int) $segments[2];
            if ($targetParishId > 0 && !$this->parishBelongsToProvince($targetParishId, $provinceId)) {
                return $this->deny($incoming, 'Target parish is outside your province scope.');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function resolveProvinceId(int $userId): int
    {
        $profile = model('ProfileModel')->where('user_id', $userId)->first();

        if (!empty($profile['province_id'])) {
            return (int) $profile['province_id'];
        }

        if (!empty($profile['diocese_id'])) {
            $row = db_connect()->table('diocese')
                ->select('province_id')
                ->where('id', (int) $profile['diocese_id'])
                ->get()
                ->getRowArray();

            return (int) ($row['province_id'] ?? 0);
        }

        if (!empty($profile['parish_id'])) {
            $row = db_connect()->table('parish c')
                ->select('d.province_id')
                ->join('diocese d', 'd.id = c.diocese_id', 'inner')
                ->where('c.id', (int) $profile['parish_id'])
                ->where('c.deleted_at', null)
                ->get()
                ->getRowArray();

            if (!empty($row['province_id'])) {
                return (int) $row['province_id'];
            }
        }

        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0) {
            $row = db_connect()->table('parish c')
                ->select('d.province_id')
                ->join('diocese d', 'd.id = c.diocese_id', 'inner')
                ->where('c.id', $selectedParishId)
                ->where('c.deleted_at', null)
                ->get()
                ->getRowArray();

            if (!empty($row['province_id'])) {
                return (int) $row['province_id'];
            }
        }

        // Fallback for accounts whose profile is not fully hydrated yet:
        // derive province from active member affiliations.
        $affiliatedProvince = db_connect()->table('parish_affiliation mca')
            ->select('d.province_id')
            ->join('parish c', 'c.id = mca.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('c.deleted_at', null)
            ->where('mca.linked_user_id', $userId)
            ->where('mca.is_member', 1)
            ->orderBy('mca.id', 'ASC')
            ->get()
            ->getRowArray();

        if (!empty($affiliatedProvince['province_id'])) {
            return (int) $affiliatedProvince['province_id'];
        }

        return 0;
    }

    private function dioceseBelongsToProvince(int $dioceseId, int $provinceId): bool
    {
        return db_connect()->table('diocese')
            ->where('id', $dioceseId)
            ->where('province_id', $provinceId)
            ->countAllResults() > 0;
    }

    private function parishBelongsToProvince(int $parishId, int $provinceId): bool
    {
        return db_connect()->table('parish c')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('c.id', $parishId)
            ->where('d.province_id', $provinceId)
            ->where('c.deleted_at', null)
            ->countAllResults() > 0;
    }

    private function deny($request, string $message)
    {
        if ($request->isAJAX() || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => $message,
                    'error' => [
                        'code' => 'PROVINCE_SCOPE_DENIED',
                        'message' => $message,
                    ],
                    'meta' => [
                        'http_status' => 403,
                        'ts' => date('c'),
                    ],
                ]);
        }

        return redirect()->to('/province/dashboard')->with('error', $message);
    }
}
