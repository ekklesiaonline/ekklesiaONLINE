<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataEncryption;
use App\Services\GuestIntakeService;
use App\Services\PersonConversionService;

class GuestConversions extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return view('MemberPortal\\Views\\GuestConversions/index', array_merge($this->getCommonViewData(lang('Guests.conversionsTitle')), [
                'guests' => [],
                'members' => model('MemberParishAffiliationModel')->getParishMembers($churchId),
                'search' => $search,
                'page' => 1,
                'per_page' => $perPage,
                'total_rows' => 0,
                'total_pages' => 1,
            ]));
        }

        $builder = $db->table('guest g')
            ->select('g.*, p.first_name as converted_first_name, p.last_name as converted_last_name, ai.secret as converted_email')
            ->join($profileTable . ' p', 'p.user_id = g.converted_user_id', 'left')
            ->join('users u', 'u.id = g.converted_user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('g.parish_id', $churchId)
            ->orderBy('g.visit_date', 'DESC')
            ->orderBy('g.id', 'DESC');

        if ($search !== '') {
            $emailHash = DataEncryption::emailIndex($search);
            $phoneHash = DataEncryption::phoneIndex($search);

            $builder->groupStart()
                ->like('g.first_name', $search)
                ->orLike('g.last_name', $search);

            if ($emailHash !== null) {
                $builder->orWhere('g.email_hash', $emailHash);
            }
            if ($phoneHash !== null) {
                $builder->orWhere('g.phone_hash', $phoneHash);
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

        $guests = $builder
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();
        $guestModel = model('GuestModel');
        $guests = array_map(fn(array $row): array => $guestModel->decryptGuestRow($row), $guests);
        $members = model('MemberParishAffiliationModel')->getParishMembers($churchId);

        return view('MemberPortal\Views\GuestConversions/index', array_merge($this->getCommonViewData(lang('Guests.conversionsTitle')), [
            'guests' => $guests,
            'members' => $members,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]));
    }

    public function convert($guestId)
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $userId = (int) ($this->request->getPost('user_id') ?? 0);
        if ($userId <= 0) {
            return redirect()->back()->with('error', lang('App.noSelection'));
        }

        $guestModel = model('GuestModel');
        $affiliationModel = model('MemberParishAffiliationModel');

        $guest = $guestModel->find((int) $guestId);
        $churchId = (int) ($this->getOperationalParishId() ?? 0);

        if (!$guest || (int) ($guest['parish_id'] ?? 0) !== $churchId) {
            return redirect()->to('/guest-conversions')->with('error', lang('App.errorNotFound'));
        }

        $guest = $guestModel->find((int) $guestId);
        $personId = (int) ($guest['person_id'] ?? 0);

        if ($personId <= 0) {
            try {
                $intake = GuestIntakeService::record($guest, $churchId);
                $personId = $intake['person_id'];
                $guestModel->update((int) $guestId, ['person_id' => $personId]);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', lang('App.errorFailed'));
            }
        }

        $converted = PersonConversionService::convertToMember(
            $personId,
            $churchId,
            $userId,
            null,
            null,
            (int) auth()->id()
        );

        if (! $converted) {
            return redirect()->back()->with('error', lang('App.errorFailed'));
        }

        // Rebuild the parish snapshot so the converted guest appears in /people.
        (new \App\Services\ParishPeopleRegistryService())->syncParish($churchId);

        return redirect()->to('/guest-conversions')->with('success', lang('Guests.conversionLinkSuccess'));
    }

    public function revert($guestId)
    {
        if ($redirect = $this->requirePermission('guest.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $guestModel = model('GuestModel');
        $guest = $guestModel->find((int) $guestId);
        $churchId = (int) ($this->getOperationalParishId() ?? 0);

        if (!$guest || (int) ($guest['parish_id'] ?? 0) !== $churchId) {
            return redirect()->to('/guest-conversions')->with('error', lang('App.errorNotFound'));
        }

        $guestModel->update((int) $guestId, [
            'converted_to_member_at' => null,
            'converted_user_id' => null,
            'converted_by_user_id' => null,
        ]);

        return redirect()->to('/guest-conversions')->with('success', lang('Guests.conversionRevertSuccess'));
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
        }
        $candidates[] = 'profile';

        foreach ($candidates as $table) {
            try {
                $db->getFieldNames($table);
                return $table;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }
}
