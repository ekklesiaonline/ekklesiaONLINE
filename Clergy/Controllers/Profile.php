<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Libraries\PhoneHelper;

/**
 * Clergy portal profile — self-service for clergy accounts.
 *
 * Clergy complete their own contact/locale fields here; canonical fields
 * (ordination, standing) are diocese-owned and shown read-only, sourced from
 * clergy_ordination / clergy_canonical_status. Clergy do not use
 * MemberPortal\Controllers\Profile.
 */
class Profile extends BaseController
{
    public function index()
    {
        $user = auth()->user();
        if ($user === null || ! $user->inGroup('clergy')) {
            return redirect()->to('/login');
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
        if (! $profile) {
            return redirect()->to('/clergy/dashboard')->with('error', lang('App.errorNotFound'));
        }

        return view('Clergy\Views\Profile/index', array_merge(
            $this->getCommonViewData(lang('ClergyPortal.profileTitle')),
            [
                'profile' => $profile,
                'canonical' => $this->canonicalSummary((int) ($profile['person_id'] ?? 0), (int) ($profile['diocese_id'] ?? 0)),
            ]
        ));
    }

    public function update()
    {
        $user = auth()->user();
        if ($user === null || ! $user->inGroup('clergy')) {
            return redirect()->to('/login');
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
        if (! $profile) {
            return redirect()->to('/clergy/dashboard')->with('error', lang('App.errorNotFound'));
        }

        $rules = [
            'first_name' => 'required|string|min_length[2]|max_length[255]',
            'middle_name' => 'permit_empty|string|max_length[255]',
            'last_name' => 'required|string|min_length[2]|max_length[255]',
            'phone_country_code' => 'permit_empty|valid_calling_code',
            'phone' => 'permit_empty|valid_phone_e164',
            'address' => 'permit_empty|string',
            'address_line_2' => 'permit_empty|string',
            'city' => 'permit_empty|string|max_length[255]',
            'state' => 'permit_empty|string|max_length[50]',
            'zip' => 'permit_empty|string|max_length[20]',
            'country' => 'permit_empty|valid_country',
            'birth_date' => 'permit_empty|valid_date',
            'locale' => 'permit_empty|in_list[en,es,fr]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Personal fields only — diocese_id, parish_id, portal_access, and all
        // canonical registry fields are never writable through this endpoint.
        $update = [
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => PhoneHelper::toE164($this->request->getPost('phone_country_code'), $this->request->getPost('phone')),
            'phone_country_code' => $this->request->getPost('phone_country_code') ?: null,
            'address' => $this->request->getPost('address'),
            'address_line_2' => $this->request->getPost('address_line_2') ?: null,
            'city' => $this->request->getPost('city'),
            'state' => $this->request->getPost('state'),
            'zip' => $this->request->getPost('zip'),
            'country' => $this->request->getPost('country') ?: null,
            'birth_date' => $this->request->getPost('birth_date') ?: null,
            'locale' => $this->request->getPost('locale') ?: 'en',
        ];

        $columns = model('ProfileModel')->allowedFields;
        $update = array_intersect_key($update, array_flip($columns));

        model('ProfileModel')->update((int) $profile['id'], $update);
        (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $profile['id']);

        $this->audit()->log('clergy.profile.update', [
            'entity_type' => 'profile',
            'entity_id' => (int) $profile['id'],
        ]);

        return redirect()->to('/clergy/profile')->with('success', lang('App.successUpdated'));
    }

    /**
     * Read-only canonical registry summary for the profile view.
     *
     * @return array{ordinations: array, standings: array}
     */
    private function canonicalSummary(int $personId, int $dioceseId): array
    {
        if ($personId <= 0) {
            return ['ordinations' => [], 'standings' => []];
        }

        $db = db_connect();

        $standingBuilder = $db->table('clergy_canonical_status')->where('person_id', $personId);
        if ($dioceseId > 0) {
            $standingBuilder->where('diocese_id', $dioceseId);
        }

        return [
            'ordinations' => $db->table('clergy_ordination')
                ->where('person_id', $personId)->orderBy('ordained_on', 'ASC')->get()->getResultArray(),
            'standings' => $standingBuilder->orderBy('effective_from', 'DESC')->get()->getResultArray(),
        ];
    }
}
