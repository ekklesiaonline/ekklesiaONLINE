<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * Canonical parish-scoped member search for AJAX pickers.
 *
 * Returns the same {members: [{id, full_name, email, ...}]} envelope as
 * AttendanceController::searchMembers so Select2/native typeaheads can
 * share one response shape across modules.
 *
 * Query params:
 *   q            search term (name, email, username, phone index, or user id)
 *   limit        1..50 (default 20)
 *   members_only "0" to include visitor/regular_attender rows (default "1")
 */
class MemberSearch extends BaseController
{
    public function index()
    {
        if (!$this->checkPermission('member.view')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'MEMBERS_VIEW_FORBIDDEN'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $term = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = max(1, min((int) ($this->request->getGet('limit') ?? 20), 50));
        $membersOnly = (string) ($this->request->getGet('members_only') ?? '1') !== '0';

        $rows = model('MemberParishAffiliationModel')
            ->searchMemberOptions($parishId, $term, $membersOnly, $limit);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Members loaded.',
            'members' => $rows,
            'csrf'      => csrf_hash(),
            'csrf_name' => csrf_token(),
        ]);
    }
}
