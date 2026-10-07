<?php

namespace App\Filters;

use App\Services\ClergyAuthorizationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Adds the scoped ecclesial check on top of the coarse Shield
 * `permission:` gate for parish-scoped clergy actions.
 *
 * Usage: 'filter' => 'clergyScope:<permission>,<targetType>'
 * e.g.   'clergyScope:pastoral.parish.manage,parish'
 *
 * The target id is always resolved server-side from the user's
 * authoritative scope (same pattern as
 * \Diocese\Controllers\ClergyAccounts::adminDioceseId and
 * BaseController::getOperationalParishId); request input is never
 * consulted for scope.
 */
class ClergyScopeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = service('request');

        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $permission = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';
        $targetType = is_array($arguments) ? (string) ($arguments[1] ?? 'parish') : 'parish';

        $userId   = (int) $user->id;
        $targetId = $this->resolveTargetId($userId, $targetType);

        if (
            $permission !== ''
            && $targetId > 0
            && (new ClergyAuthorizationService())->allows($userId, $permission, $targetType, $targetId)
        ) {
            return null;
        }

        $context = [
            'filter'     => self::class,
            'permission' => $permission,
            'scope'      => $targetType,
            'target_id'  => $targetId,
        ];
        if ($targetType === 'parish' && $targetId > 0) {
            $context['parish_id'] = $targetId;
        }

        service('audit')->logAuthorizationDenial('clergy.scope.denied', 'CLERGY_SCOPE_DENIED', $context);

        if ($incoming->isAJAX() || str_contains((string) $incoming->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'This action requires a current ecclesial office or delegation for the parish.',
                    'error'   => [
                        'code'    => 'CLERGY_SCOPE_DENIED',
                        'message' => 'This action requires a current ecclesial office or delegation for the parish.',
                    ],
                    'meta' => [
                        'http_status' => 403,
                        'ts'          => date('c'),
                    ],
                ]);
        }

        return redirect()->to('/dashboard')
            ->with('error', 'This action requires a current ecclesial office or delegation for the parish.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Resolve the scope target server-side. Returns 0 when no authoritative
     * scope exists, which fails closed.
     */
    private function resolveTargetId(int $userId, string $targetType): int
    {
        return match ($targetType) {
            'parish'  => $this->resolveParishId($userId),
            'diocese' => $this->resolveDioceseId($userId),
            default   => 0,
        };
    }

    /**
     * Same server-side parish resolution as BaseController::getOperationalParishId:
     * validated session selection first, then primary parish affiliation,
     * then profile home parish.
     */
    private function resolveParishId(int $userId): int
    {
        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0 && model('ParishModel')->find($selectedParishId)) {
            return $selectedParishId;
        }

        try {
            $affiliation = db_connect()->table('parish_affiliation')
                ->select('parish_id')
                ->where('linked_user_id', $userId)
                ->where('deleted_at IS NULL', null, false)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('updated_at', 'DESC')
                ->get()
                ->getRowArray();
            $parishId = (int) ($affiliation['parish_id'] ?? 0);
            if ($parishId > 0) {
                return $parishId;
            }
        } catch (\Throwable $e) {
            // Fall through to profile home parish.
        }

        $profile = model('ProfileModel')->where('user_id', $userId)->first();

        return (int) ($profile['parish_id'] ?? 0);
    }

    /**
     * Same server-side diocese resolution as
     * \Diocese\Controllers\ClergyAccounts::adminDioceseId.
     */
    private function resolveDioceseId(int $userId): int
    {
        try {
            $row        = model('DioceseAdminPrimaryModel')->getByUser($userId);
            $dioceseId  = (int) ($row['diocese_id'] ?? 0);
            if ($dioceseId <= 0) {
                $profile   = model('ProfileModel')->where('user_id', $userId)->first();
                $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            }
        } catch (\Throwable $e) {
            $dioceseId = 0;
        }

        return $dioceseId;
    }
}
