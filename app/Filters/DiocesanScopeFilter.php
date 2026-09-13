<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AuthGroups;

/**
 * Enforces diocesan boundaries for diocesan admin workflows.
 *
 * This filter validates and sanitizes diocesan scope state used by
 * common routines to avoid cross-diocese leakage in multi-tenant contexts.
 *
 * Diocesan admins can only access parishes within their assigned diocese.
 * Province admins can access all dioceses and parishes (bypass filter).
 */
class DiocesanScopeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = service('request');

        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if (!$user || !$user->inGroup(AuthGroups::DIOCESE_ADMIN)) {
            // Province admins and other roles bypass this filter
            return null;
        }

        // Resolve diocesan scope from user profile
        $dioceseId = $this->resolveDiocesanScopeId((int) auth()->id());
        if ($dioceseId <= 0) {
            auth()->logout();

            return redirect()->to('/login')->with('error', 'Unable to resolve your diocesan scope. Please contact support.');
        }

        // Keep canonical diocesan scope in session for dependent modules
        session()->set('diocesan_scope_id', $dioceseId);

        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0 && !$this->parishBelongsToDiocese($selectedParishId, $dioceseId)) {
            session()->remove('selected_parish_id');
        }

        // Validate explicit query/body scope selectors
        $parishIdRaw = $incoming->getGet('parish_id');
        if ($parishIdRaw === null) {
            $parishIdRaw = $incoming->getPost('parish_id');
        }
        $parishId = (int) ($parishIdRaw ?? 0);
        if ($parishId > 0 && !$this->parishBelongsToDiocese($parishId, $dioceseId)) {
            return $this->deny($incoming, 'Parish is outside your diocesan scope.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after the controller runs
    }

    /**
     * Resolve diocesan scope ID from user profile or parish hierarchy.
     */
    private function resolveDiocesanScopeId(int $userId): int
    {
        $profileModel = model('ProfileModel');
        $profile = $profileModel->where('user_id', $userId)->first();

        if (!empty($profile['diocese_id'])) {
            return (int) $profile['diocese_id'];
        }

        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0) {
            $selectedParish = model('ParishModel')->find($selectedParishId);
            if (!empty($selectedParish['diocese_id'])) {
                return (int) $selectedParish['diocese_id'];
            }
        }

        $parishId = (int) ($profile['parish_id'] ?? 0);
        if ($parishId > 0) {
            $parish = model('ParishModel')->find($parishId);
            if (!empty($parish['diocese_id'])) {
                return (int) $parish['diocese_id'];
            }
        }

        // Fallback for accounts whose profile is not fully hydrated yet:
        // derive diocese from active member affiliations.
        $db = db_connect();
        $hasPrimaryColumn = false;
        try {
            $hasPrimaryColumn = $db->fieldExists('is_primary', 'parish_affiliation');
        } catch (\Throwable $e) {
            $hasPrimaryColumn = false;
        }

        $affiliationQuery = $db->table('parish_affiliation mca')
            ->select('c.diocese_id')
            ->join('parish c', 'c.id = mca.parish_id', 'inner')
            ->where('c.deleted_at', null)
            ->where('mca.linked_user_id', $userId)
            ->where('mca.is_member', 1);

        if ($hasPrimaryColumn) {
            $affiliationQuery->orderBy('mca.is_primary', 'DESC');
        }

        $affiliatedParish = $affiliationQuery
            ->orderBy('mca.id', 'ASC')
            ->get()
            ->getRowArray();

        if (!empty($affiliatedParish['diocese_id'])) {
            return (int) $affiliatedParish['diocese_id'];
        }

        return 0;
    }

    /**
     * Check if a parish belongs to the given diocese.
     */
    private function parishBelongsToDiocese(int $parishId, int $dioceseId): bool
    {
        $count = db_connect()->table('parish')
            ->where('id', $parishId)
            ->where('diocese_id', $dioceseId)
            ->where('deleted_at', null)
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Deny access based on filter rules.
     */
    private function deny(RequestInterface $request, string $message)
    {
        if (
            strtolower((string) $request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')
        ) {
            return service('response')
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => $message,
                    'error' => [
                        'code' => 'DIOCESAN_SCOPE_DENIED',
                        'message' => $message,
                    ],
                    'meta' => [
                        'http_status' => 403,
                        'ts' => date('c'),
                    ],
                ])
                ->setStatusCode(403);
        }

        return redirect()->back()->with('error', $message);
    }
}
