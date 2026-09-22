<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataEncryption;
use App\Services\VisitorDirectoryService;
use App\Services\PersonConversionService;

class VisitorConversions extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }

        $db = db_connect();

        $visitDate = '(SELECT MIN(pvx.visit_date) FROM person_visit pvx'
            . ' WHERE pvx.person_id = pa.person_id AND pvx.parish_id = pa.parish_id)';

        $builder = $db->table('parish_affiliation pa')
            ->select('pe.id AS id, pe.id AS person_id, pa.parish_id,'
                . ' pe.first_name, pe.last_name, pe.email, pe.phone, pe.email_hash, pe.phone_hash,'
                . ' pa.followed_up, pa.converted_to_member_at, pa.linked_user_id AS converted_user_id,'
                . ' pa.converted_by_user_id, pa.membership_status, pa.created_at, pa.updated_at')
            ->select($visitDate . ' AS visit_date', false)
            ->join('person pe', 'pe.id = pa.person_id AND pe.deleted_at IS NULL', 'inner')
            ->join('profile p', 'p.user_id = pa.linked_user_id', 'left')
            ->join('users u', 'u.id = pa.linked_user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->select('p.first_name as converted_first_name, p.last_name as converted_last_name, ai.secret as converted_email')
            ->where('pa.parish_id', $parishId)
            ->where('pa.deleted_at IS NULL', null, false)
            ->groupStart()
                ->where('pa.membership_status', 'visitor')
                ->orWhere('pa.converted_to_member_at IS NOT NULL', null, false)
            ->groupEnd()
            ->orderBy($visitDate, 'DESC', false)
            ->orderBy('pe.id', 'DESC');

        if ($search !== '') {
            $emailHash = DataEncryption::emailIndex($search);
            $phoneHash = DataEncryption::phoneIndex($search);

            $builder->groupStart()
                ->like('pe.first_name', $search)
                ->orLike('pe.last_name', $search);

            if ($emailHash !== null) {
                $builder->orWhere('pe.email_hash', $emailHash);
            }
            if ($phoneHash !== null) {
                $builder->orWhere('pe.phone_hash', $phoneHash);
            }

            $builder->groupEnd();
        }

        $countBuilder = clone $builder;
        $totalRows = (int) $countBuilder->countAllResults();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $visitors = $builder
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $visitorDirectory = new VisitorDirectoryService();
        $visitors = array_map(fn(array $row): array => $visitorDirectory->decryptRow($row), $visitors);
        $members = model('MemberParishAffiliationModel')->getParishMembers($parishId);

        return view('MemberPortal\Views\VisitorConversions/index', array_merge($this->getCommonViewData(lang('Visitors.conversionsTitle')), [
            'visitors' => $visitors,
            'members' => $members,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]));
    }

    public function convert($visitorId)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $userId = (int) ($this->request->getPost('user_id') ?? 0);
        if ($userId <= 0) {
            return redirect()->back()->with('error', lang('App.noSelection'));
        }

        $visitorDirectory = new VisitorDirectoryService();
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        // The visitor id carried by this endpoint is the canonical person_id.
        $personId = (int) $visitorId;
        $visitor    = $visitorDirectory->findByPerson($personId, $parishId);

        if (! $visitor) {
            return redirect()->to('/visitor-conversions')->with('error', lang('App.errorNotFound'));
        }

        $converted = PersonConversionService::convertToMember(
            $personId,
            $parishId,
            $userId,
            null,
            null,
            (int) auth()->id()
        );

        if (! $converted) {
            return redirect()->back()->with('error', lang('App.errorFailed'));
        }

        return redirect()->to('/visitor-conversions')->with('success', lang('Visitors.conversionLinkSuccess'));
    }

    public function revert($visitorId)
    {
        if ($redirect = $this->requirePermission('visitor.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $visitorDirectory = new VisitorDirectoryService();
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $personId = (int) $visitorId;

        $visitor = $visitorDirectory->findByPerson($personId, $parishId);
        if (! $visitor) {
            return redirect()->to('/visitor-conversions')->with('error', lang('App.errorNotFound'));
        }

        $visitorDirectory->revertConversion($personId, $parishId, (int) auth()->id());

        return redirect()->to('/visitor-conversions')->with('success', lang('Visitors.conversionRevertSuccess'));
    }
}
