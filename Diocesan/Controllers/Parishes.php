<?php

namespace Diocesan\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;

class Parishes extends BaseController
{
    use AdminHierarchyGuard;

    protected $parishModel;
    protected $profileModel;
    protected $parishRoleAuditModel;

    public function __construct()
    {
        $this->parishModel = model('ParishModel');
        $this->parishRoleAuditModel = model('ParishRoleAuditModel');
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    public function index()
    {
        if (!$this->canAccessParishesModule()) {
            return redirect()->to('/dashboard')->with('error', lang('Diocesan.parishManagementAccessDenied'));
        }

        if ($this->isParishAdminOnly()) {
            $parishId = (int) ($this->getOperationalparishId() ?? 0);
            if ($parishId <= 0) {
                return redirect()->to('/dashboard')->with('error', lang('Diocesan.noParishAssigned'));
            }

            return redirect()->to('/parish_list/edit/' . $parishId);
        }

        $validPerPage = [25, 50, 75, 100];
        $perPage      = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, $validPerPage, true)) {
            $perPage = 25;
        }
        $page            = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search          = trim((string) ($this->request->getGet('search') ?? ''));
        $letter          = strtoupper(trim((string) ($this->request->getGet('letter') ?? '')));
        $filterDioceseId = (int) ($this->request->getGet('diocese_id') ?? 0);
        $filterProvinceId = (int) ($this->request->getGet('province_id') ?? 0);
        $filterMailingDiffers = (int) ($this->request->getGet('mailing_differs') ?? 0) === 1;

        // Letter and search are mutually exclusive — letter wins
        if ($letter !== '' && $search !== '') {
            $search = '';
        }

        $isSystemAdmin = $this->isSystemAdmin();

        if ($this->isProvinceAdmin()) {
            $provinceId        = $this->resolveAdminProvinceId();
            $selectedDioceseId = (int) (session()->get('province_selected_diocese_id') ?? 0);
            $dioceseId         = $selectedDioceseId > 0 ? $selectedDioceseId : null;
            if ($filterDioceseId > 0) {
                $dioceseId = $filterDioceseId;
            }

            $diocesesForFilter = $this->fetchDioceseFilterOptions($provinceId > 0 ? $provinceId : null);

            $paginated = $this->parishModel->paginateWithMemberCount(
                $perPage,
                $page,
                $provinceId > 0 ? $provinceId : null,
                $dioceseId,
                $search !== '' ? $search : null,
                $letter !== '' ? $letter : null,
                $filterMailingDiffers
            );

            return view('Diocesan\Views\Churches/index', array_merge(
                $paginated,
                [
                    'churches'           => $paginated['parish'] ?? [],
                    'selectedChurchId'   => (int) ($this->getOperationalparishId() ?? 0),
                    'selectedparishId'   => (int) ($this->getOperationalparishId() ?? 0),
                    'isProvinceAdmin'    => true,
                    'isDiocesanAdmin'    => false,
                    'isSystemAdmin'      => false,
                    'page_title'         => lang('Parishes.pageTitle'),
                    'search'             => $search,
                    'letter'             => $letter,
                    'filterDioceseId'    => $filterDioceseId,
                    'filterProvinceId'   => 0,
                    'filterMailingDiffers' => $filterMailingDiffers,
                    'perPage'            => $perPage,
                    'diocesesForFilter'  => $diocesesForFilter,
                    'provincesForFilter' => [],
                ]
            ));
        }

        $dioceseId       = null;
        $isDiocesanAdmin = $this->isDioceseAdmin();
        if ($isDiocesanAdmin) {
            $dId = $this->resolveDiocesanScopeId();
            if ($dId > 0) {
                $dioceseId = $dId;
                $filterDioceseId = $dId;
            }
        }

        // System admin: free-form province / diocese filters
        $scopedProvinceId = null;
        $scopedDioceseId  = $dioceseId;
        if ($isSystemAdmin) {
            $scopedProvinceId = $filterProvinceId > 0 ? $filterProvinceId : null;
            $scopedDioceseId  = $filterDioceseId  > 0 ? $filterDioceseId  : null;
        }

        $paginated = $this->parishModel->paginateWithMemberCount(
            $perPage,
            $page,
            $scopedProvinceId,
            $scopedDioceseId,
            $search !== '' ? $search : null,
            $letter !== '' ? $letter : null,
            $filterMailingDiffers
        );

        return view('Diocesan\Views\Churches/index', array_merge(
            $paginated,
            [
                'churches'           => $paginated['parish'] ?? [],
                'selectedChurchId'   => (int) ($this->getOperationalparishId() ?? 0),
                'selectedparishId'   => (int) ($this->getOperationalparishId() ?? 0),
                'isProvinceAdmin'    => false,
                'isDiocesanAdmin'    => $isDiocesanAdmin,
                'isSystemAdmin'      => $isSystemAdmin,
                'page_title'         => lang('Parishes.pageTitle'),
                'search'             => $search,
                'letter'             => $letter,
                'filterDioceseId'    => $filterDioceseId,
                'filterProvinceId'   => $filterProvinceId,
                'filterMailingDiffers' => $filterMailingDiffers,
                'perPage'            => $perPage,
                'diocesesForFilter'  => $isSystemAdmin ? $this->fetchDioceseFilterOptions(null) : [],
                'provincesForFilter' => $isSystemAdmin ? $this->fetchProvinceFilterOptions()    : [],
            ]
        ));
    }

    private function fetchDioceseFilterOptions(?int $provinceId): array
    {
        $q = db_connect()->table('diocese d')
            ->select('d.id, d.name, d.province_id, p.province_number')
            ->join('province p', 'p.id = d.province_id', 'left')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('d.name', 'ASC');
        if ($provinceId) {
            $q->where('d.province_id', $provinceId);
        }
        return $q->get()->getResultArray();
    }

    private function fetchProvinceFilterOptions(): array
    {
        return db_connect()->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function create()
    {
        if (!$this->canCreateParish()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishCreateRestricted'));
        }

        $builder = db_connect()->table('diocese d')
            ->select('d.id, d.name, p.province_number, p.name AS province_name')
            ->join('province p', 'p.id = d.province_id', 'left')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->orderBy('d.name', 'ASC');

        if ($this->isDioceseAdmin() && !$this->isSystemAdmin()) {
            $scopeDioceseId = $this->resolveDiocesanScopeId();
            if ($scopeDioceseId <= 0) {
                return redirect()->to('/parish_list')->with('error', lang('Diocesan.diocesanScopeResolveFailed'));
            }
            $builder->where('d.id', $scopeDioceseId);
        }

        $dioceses = $builder->get()->getResultArray();

        if ($dioceses === []) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.dioceseNotFound'));
        }

        return view('Diocesan\Views\Churches/create', array_merge($this->getCommonViewData(lang('Parishes.createPageTitle')), [
            'dioceses' => $dioceses,
        ]));
    }

    public function store()
    {
        if (!$this->canCreateParish()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Diocesan.parishCreateRestricted')], 403);
        }

        $dioceseId = (int) ($this->request->getPost('diocese_id') ?? 0);
        if ($dioceseId <= 0) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Diocesan.dioceseNotFound')], 400);
        }

        if (!$this->canManageDioceseScope($dioceseId)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Diocesan.selectedParishOutsideScope')], 403);
        }

        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'diocese_id' => 'required|is_not_unique[diocese.id]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|max_length[50]',
            'website' => 'permit_empty|valid_url',
            'address' => 'permit_empty|max_length[500]',
            'street' => 'permit_empty|max_length[255]',
            'city' => 'permit_empty|max_length[100]',
            'state' => 'permit_empty|max_length[50]',
            'zip' => 'permit_empty|max_length[20]',
            'county' => 'permit_empty|max_length[100]',
            'country' => 'permit_empty|max_length[100]',
            'mailing_street' => 'permit_empty|max_length[255]',
            'mailing_city' => 'permit_empty|max_length[100]',
            'mailing_state' => 'permit_empty|max_length[50]',
            'mailing_zip' => 'permit_empty|max_length[20]',
            'mailing_county' => 'permit_empty|max_length[100]',
            'mailing_country' => 'permit_empty|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonResponse(['success' => false, 'errors' => $this->validator->getErrors()], 400);
        }

        $parishId = $this->parishModel->insert([
            'name' => $this->request->getPost('name'),
            'diocese_id' => $dioceseId,
            'email' => $this->request->getPost('email') ?? '',
            'phone' => $this->request->getPost('phone') ?? '',
            'address' => $this->request->getPost('address') ?? '',
            'website' => $this->request->getPost('website') ?? '',
            'street' => $this->request->getPost('street') ?? '',
            'city' => $this->request->getPost('city') ?? '',
            'state' => $this->request->getPost('state') ?? '',
            'zip' => $this->request->getPost('zip') ?? '',
            'county' => $this->request->getPost('county') ?? '',
            'country' => $this->request->getPost('country') ?? '',
            'mailing_street' => $this->request->getPost('mailing_street') ?? '',
            'mailing_city' => $this->request->getPost('mailing_city') ?? '',
            'mailing_state' => $this->request->getPost('mailing_state') ?? '',
            'mailing_zip' => $this->request->getPost('mailing_zip') ?? '',
            'mailing_county' => $this->request->getPost('mailing_county') ?? '',
            'mailing_country' => $this->request->getPost('mailing_country') ?? '',
        ]);

        if ($parishId) {
            try {
                $this->audit()->log('diocese.parish.created', [
                    'user_id' => (int) auth()->id(),
                    'entity_type' => 'parish',
                    'entity_id' => $parishId,
                    'ip_address' => $this->request->getIPAddress(),
                    'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'new_values' => [
                        'name' => $this->request->getPost('name'),
                        'diocese_id' => $dioceseId,
                        'email' => $this->request->getPost('email'),
                        'phone' => $this->request->getPost('phone'),
                    ],
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to log audit event for parish creation: {message}', ['message' => $e->getMessage()]);
            }
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => lang('Parishes.storeSuccess'),
                    'parish_id' => $parishId,
                ]);
            }

            return redirect()->to('/parish_list')->with('success', lang('Parishes.storeSuccess'));
        }

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Parishes.storeFailed')], 500);
        }

        return redirect()->back()->withInput()->with('error', lang('Parishes.storeFailed'));
    }

    public function edit($id)
    {
        if (!$this->canManageParish((int) $id)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishAccessDenied'));
        }

        $parish = $this->parishModel->findWithHierarchy((int) $id);
        if (!$parish) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishNotFound'));
        }

        // Determine edit permissions
        $isSystemAdmin = $this->isSystemAdmin();
        $isDiocesanAdmin = $this->isDioceseAdmin();
        $isParishAdmin = $this->isParishAdminOnly();
        $parishId = (int) $id;
        $operationalparishId = (int) ($this->getOperationalparishId() ?? 0);

        // Tax ID can be edited by:
        // - System admin (all parishes)
        // - Diocesan admin (all their diocese parishes)
        // - Parish admin (only their own parish)
        $canEditTaxId = $isSystemAdmin || $isDiocesanAdmin || ($isParishAdmin && $operationalparishId === $parishId);

        return view('Diocesan\Views\Churches/edit', array_merge($this->getCommonViewData(lang('Parishes.editPageTitle')), [
            'parish' => $parish,
            'provinces' => $this->getProvinceOptions(),
            'diocesesByProvince' => $this->getDiocesesByProvince(),
            'isProvinceAdmin' => $this->isProvinceAdmin(),
            'canEditOfficial' => $isSystemAdmin || auth()->user()->can('parishes.manage.official'),
            'canEditIdentity' => $isSystemAdmin,
            'canEditTaxId' => $canEditTaxId,
        ]));
        // Note: parish is already in common view data
    }

    public function update($id)
    {
        if (!$this->canManageParish((int) $id)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishAccessDenied'));
        }

        $parish = $this->parishModel->find($id);
        if (!$parish) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishNotFound'));
        }

        // Determine edit permissions
        $isSystemAdmin = $this->isSystemAdmin();
        $isDiocesanAdmin = $this->isDioceseAdmin();
        $isParishAdmin = $this->isParishAdminOnly();
        $parishId = (int) $id;
        $operationalparishId = (int) ($this->getOperationalparishId() ?? 0);

        // System admins always manage official parish profile data.
        $canEditOfficial = $isSystemAdmin || auth()->user()->can('parishes.manage.official');
        // Parish Name and Diocese may only be changed by system admins.
        $canEditIdentity = $isSystemAdmin;
        // Tax ID can be edited by system admin, diocesan admin, or parish admin (own parish only)
        $canEditTaxId = $isSystemAdmin || $isDiocesanAdmin || ($isParishAdmin && $operationalparishId === $parishId);

        // Base validation rules (contact info + location/mailing — editable by all parish admins)
        $rules = [
            'email' => 'permit_empty|valid_email|max_length[255]',
            'phone' => 'permit_empty|max_length[50]',
            'website' => 'permit_empty|max_length[255]',
            'address' => 'permit_empty|max_length[500]',
            'logo' => 'permit_empty|uploaded[logo]|max_size[logo,2048]|is_image[logo]|mime_in[logo,image/jpg,image/jpeg,image/png,image/gif]',
            'banner' => 'permit_empty|uploaded[banner]|max_size[banner,4096]|is_image[banner]|mime_in[banner,image/jpg,image/jpeg,image/png,image/gif,image/webp]',
            'street' => 'permit_empty|max_length[255]',
            'city' => 'permit_empty|max_length[100]',
            'state' => 'permit_empty|max_length[50]',
            'zip' => 'permit_empty|max_length[20]',
            'county' => 'permit_empty|max_length[100]',
            'country' => 'permit_empty|max_length[100]',
            'mailing_street' => 'permit_empty|max_length[255]',
            'mailing_city' => 'permit_empty|max_length[100]',
            'mailing_state' => 'permit_empty|max_length[50]',
            'mailing_zip' => 'permit_empty|max_length[20]',
            'mailing_county' => 'permit_empty|max_length[100]',
            'mailing_country' => 'permit_empty|max_length[100]',
        ];

        // Name and diocese are identity fields — only system admins may change them.
        if ($canEditIdentity) {
            $rules = array_merge($rules, [
                'name' => 'required|min_length[3]|max_length[255]',
                'diocese_id' => 'required|is_not_unique[diocese.id]',
            ]);
        }

        // Add tax_id validation rules if user can edit it
        if ($canEditTaxId) {
            $rules = array_merge($rules, [
                'tax_id' => 'permit_empty|max_length[50]',
            ]);
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Base data array (contact info + location/mailing — editable by all parish admins)
        $data = [
            'address' => $this->request->getPost('address'),
            'phone' => $this->request->getPost('phone'),
            'email' => $this->request->getPost('email'),
            'website' => $this->request->getPost('website'),
            'street' => $this->request->getPost('street'),
            'city' => $this->request->getPost('city'),
            'state' => $this->request->getPost('state'),
            'zip' => $this->request->getPost('zip'),
            'county' => $this->request->getPost('county'),
            'country' => $this->request->getPost('country'),
            'mailing_street' => $this->request->getPost('mailing_street'),
            'mailing_city' => $this->request->getPost('mailing_city'),
            'mailing_state' => $this->request->getPost('mailing_state'),
            'mailing_zip' => $this->request->getPost('mailing_zip'),
            'mailing_county' => $this->request->getPost('mailing_county'),
            'mailing_country' => $this->request->getPost('mailing_country'),
        ];

        // Handle logo upload
        $file = $this->request->getFile('logo');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Delete old logo if exists
            if (!empty($parish['logo'])) {
                $oldFile = $this->resolveStoredUploadAbsolutePath((string) $parish['logo'], (int) $id, 'logo');
                if ($oldFile !== null && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $relativeDir = \App\Services\UploadPathService::parishLogoRelativeDir((int) $id);
            $targetDir = \App\Services\UploadPathService::absoluteUploadDir($relativeDir);
            if ($targetDir === null) {
                return redirect()->back()->withInput()->with('error', lang('App.errorGeneric'));
            }

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $newName = $file->getRandomName();
            $file->move($targetDir, $newName, true);
            $data['logo'] = $relativeDir . '/' . $newName;
        }

        // Handle banner upload and explicit removal.
        $removeBanner = (string) ($this->request->getPost('remove_banner') ?? '') === '1';
        $bannerFile = $this->request->getFile('banner');
        $hasNewBanner = $bannerFile && $bannerFile->isValid() && !$bannerFile->hasMoved() && $bannerFile->getError() !== UPLOAD_ERR_NO_FILE;

        if ($removeBanner || $hasNewBanner) {
            if (!empty($parish['banner'])) {
                $oldBannerFile = $this->resolveStoredUploadAbsolutePath((string) $parish['banner'], (int) $id, 'banner');
                if ($oldBannerFile !== null && is_file($oldBannerFile)) {
                    @unlink($oldBannerFile);
                }
            }

            if ($removeBanner && !$hasNewBanner) {
                $data['banner'] = null;
            }
        }

        if ($hasNewBanner) {
            $relativeDir = \App\Services\UploadPathService::parishBannerRelativeDir((int) $id);
            $targetDir = \App\Services\UploadPathService::absoluteUploadDir($relativeDir);
            if ($targetDir === null) {
                return redirect()->back()->withInput()->with('error', lang('App.errorGeneric'));
            }

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $newName = $bannerFile->getRandomName();
            $bannerFile->move($targetDir, $newName, true);
            $data['banner'] = $relativeDir . '/' . $newName;
        }

        // Update identity fields only if user is system admin.
        if ($canEditIdentity) {
            $data = array_merge($data, [
                'name' => $this->request->getPost('name'),
                'diocese_id' => (int) $this->request->getPost('diocese_id'),
            ]);
        }

        // Add tax_id only if user can edit it
        if ($canEditTaxId) {
            $data = array_merge($data, [
                'tax_id' => $this->request->getPost('tax_id'),
            ]);
        }

        // Capture old values for audit logging
        $oldValues = [
            'name' => $parish['name'] ?? null,
            'diocese_id' => $parish['diocese_id'] ?? null,
            'email' => $parish['email'] ?? null,
            'phone' => $parish['phone'] ?? null,
            'address' => $parish['address'] ?? null,
            'tax_id' => $parish['tax_id'] ?? null,
        ];

        $this->parishModel->update($id, $data);

        try {
            $this->audit()->log('diocese.parish.updated', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'parish',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => $oldValues,
                'new_values' => [
                    'name' => $data['name'] ?? $oldValues['name'],
                    'diocese_id' => $data['diocese_id'] ?? $oldValues['diocese_id'],
                    'email' => $data['email'] ?? $oldValues['email'],
                    'phone' => $data['phone'] ?? $oldValues['phone'],
                    'address' => $data['address'] ?? $oldValues['address'],
                    'tax_id' => $data['tax_id'] ?? $oldValues['tax_id'],
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for parish update: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/parish_list')->with('success', lang('Diocesan.parishUpdated'));
    }

    public function delete($id)
    {
        $parishId = (int) $id;
        $purgeNullableRefs = in_array(strtolower((string) $this->request->getVar('purge_nullable_refs')), ['1', 'true', 'yes', 'on'], true);

        if (!$this->canDeleteParish()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishDeleteRestricted'),
            ], 403);
        }

        if (!$this->canManageParish($parishId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishAccessDenied'),
            ], 403);
        }

        $result = $this->deleteParishRecord($parishId, $purgeNullableRefs);
        if (!$result['success']) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message'],
            ], (int) ($result['status'] ?? 500));
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Diocesan.parishDeleted'),
        ]);
    }

    public function bulkDelete()
    {
        $purgeNullableRefs = in_array(strtolower((string) $this->request->getVar('purge_nullable_refs')), ['1', 'true', 'yes', 'on'], true);
        $deleteReason = trim((string) $this->request->getPost('delete_reason'));

        if (!$this->canDeleteParish()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishDeleteRestricted'),
            ], 403);
        }

        $requested = (array) $this->request->getPost('parish_ids');
        $parishIds = array_values(array_unique(array_filter(array_map('intval', $requested), static fn(int $item): bool => $item > 0)));

        if ($parishIds === []) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.bulkDeleteSelectAtLeastOne'),
            ], 400);
        }

        if (strlen($deleteReason) < 8) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('App.bulkDeleteReasonRequired'),
            ], 400);
        }

        $deleted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($parishIds as $parishId) {
            if (!$this->canManageParish($parishId)) {
                $skipped++;
                $errors[] = ['parish_id' => $parishId, 'reason' => 'access_denied'];
                continue;
            }

            $result = $this->deleteParishRecord($parishId, $purgeNullableRefs);
            if ($result['success']) {
                $this->audit()->log('parish.bulk_delete', [
                    'parish_id' => $parishId,
                    'entity_type' => 'parish',
                    'entity_id' => $parishId,
                    'old_values' => [
                        'name' => (string) (($result['parish']['name'] ?? '')),
                    ],
                    'new_values' => [
                        'delete_reason' => $deleteReason,
                        'reason' => $deleteReason,
                        'purge_nullable_refs' => $purgeNullableRefs,
                        'requested_count' => count($parishIds),
                    ],
                ]);
                $deleted++;
                continue;
            }

            $skipped++;
            $errors[] = [
                'parish_id' => $parishId,
                'reason' => (string) ($result['message'] ?? 'delete_failed'),
            ];
        }

        return $this->jsonResponse([
            'success' => $deleted > 0,
            'message' => lang('Diocesan.bulkDeleteResult', [$deleted, $skipped]),
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    protected function deleteParishRecord(int $parishId, bool $purgeNullableRefs): array
    {
        $parish = $this->parishModel->find($parishId);
        $selectedparishId = (int) ($this->getOperationalparishId() ?? 0);

        if ($selectedparishId === $parishId) {
            return ['success' => false, 'status' => 409, 'message' => lang('Diocesan.parishDeleteCurrentSelectionBlocked')];
        }

        try {
            $db = db_connect();
            $db->transBegin();

            if ($purgeNullableRefs) {
                $db->table('parish_role_audit_log')->where('parish_id', $parishId)->delete();
                $profileTable = $this->resolveExistingProfileTable();
                if ($profileTable !== null) {
                    $db->table($profileTable)->where('parish_id', $parishId)->delete();
                }
            }

            if (!$this->parishModel->delete($parishId)) {
                $db->transRollback();
                return ['success' => false, 'status' => 500, 'message' => lang('Diocesan.parishDeleteFailed')];
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return ['success' => false, 'status' => 500, 'message' => lang('Diocesan.parishDeleteFailed')];
            }

            $db->transCommit();

            // Capture old values for audit logging
            $oldValues = [
                'id' => (int) ($parish['id'] ?? 0),
                'name' => (string) ($parish['name'] ?? ''),
                'diocese_id' => $parish['diocese_id'] ?? null,
            ];

            try {
                $this->audit()->log('diocese.parish.deleted', [
                    'user_id' => (int) auth()->id(),
                    'entity_type' => 'parish',
                    'entity_id' => $parishId,
                    'ip_address' => $this->request->getIPAddress(),
                    'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'old_values' => $oldValues,
                    'new_values' => [
                        'delete_reason' => 'individual_delete',
                        'purge_nullable_refs' => $purgeNullableRefs,
                    ],
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to log audit event for parish deletion: {message}', ['message' => $e->getMessage()]);
            }

            return [
                'success' => true,
                'status' => 200,
                'message' => lang('Diocesan.parishDeleted'),
                'parish' => [
                    'id' => (int) ($parish['id'] ?? 0),
                    'name' => (string) ($parish['name'] ?? ''),
                ],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Parish delete failed: {message}', ['message' => $e->getMessage()]);
            return ['success' => false, 'status' => 500, 'message' => lang('Diocesan.parishDeleteFailed')];
        }
    }

    public function deleteImpact($id)
    {
        $parishId = (int) $id;

        if (!$this->canDeleteParish()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishDeleteRestricted'),
            ], 403);
        }

        if (!$this->canManageParish($parishId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishAccessDenied'),
            ], 403);
        }

        $parish = $this->parishModel->select('id, name')->find($parishId);
        if (!$parish) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Diocesan.parishNotFound'),
            ], 404);
        }

        try {
            return $this->jsonResponse([
                'success' => true,
                'parish' => [
                    'id' => (int) $parish['id'],
                    'name' => (string) $parish['name'],
                ],
                'impact' => $this->getDeleteImpactCounts($parishId),
            ]);
        } catch (\Throwable $e) {
            return $this->safeJsonError(lang('Parishes.textUnableLoadDeleteImpact'), 500, $e);
        }
    }

    public function staff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff (read-only on all parishes)
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $parish = $this->parishModel->find($parishId);
        if (!$parish) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishNotFound'));
        }

        return view('Diocesan\Views\Churches/staff', array_merge($this->getCommonViewData(lang('Parishes.staffPageTitle')), [
            'parish'         => $parish, // Explicit: getCommonViewData uses operational parish which may differ
            'staff'          => $this->parishModel->getParishStaff($parishId),
            'members'        => $this->parishModel->getParishMembersWithGroups($parishId),
            'auditLogs'      => $this->parishRoleAuditModel->getRecentByParish($parishId, 40),
            'allowedRoles'   => $this->allowedAssignableRoles(),
            'isProvinceAdmin' => $this->isProvinceAdmin(),
        ]));
    }

    public function addStaff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $role = (string) $this->request->getPost('role');
        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        $email = $this->normalizeEmail((string) $this->request->getPost('email'));
        $exists = $this->emailIdentityExists($email);

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[255]',
            'password' => $exists ? 'permit_empty' : 'required|min_length[8]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transStart();

        try {
            $users = auth()->getProvider();
            $parishScope = $this->parishModel->findWithHierarchy($parishId);

            if ($exists) {
                $identityRow = $db->table('auth_identities')
                    ->select('user_id')
                    ->where('type', 'email_password')
                    ->where('secret', $email)
                    ->get()
                    ->getRowArray();

                if (empty($identityRow['user_id'])) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                $userId = (int) $identityRow['user_id'];

                if ($code = $this->guardTargetUserMutation($userId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
                }

                $user = $users->findById($userId);
                if (!$user) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                $profile = $this->getProfileModel()->where('user_id', $userId)->first();
                if ($profile && !empty($profile['parish_id']) && (int) $profile['parish_id'] !== $parishId) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.emailBelongsToOtherParish'));
                }

                if (!$profile) {
                    $this->getProfileModel()->insert([
                        'user_id' => $userId,
                        'parish_id' => $parishId,
                        'province_id' => isset($parishScope['province_id']) ? (int) $parishScope['province_id'] : null,
                        'diocese_id' => isset($parishScope['diocese_id']) ? (int) $parishScope['diocese_id'] : null,
                        'first_name' => (string) $this->request->getPost('first_name'),
                        'last_name' => (string) $this->request->getPost('last_name'),
                        'membership_status' => 'active',
                    ]);
                } else {
                    $updateProfile = [];
                    if (empty($profile['parish_id'])) {
                        $updateProfile['parish_id'] = $parishId;
                    }
                    if (empty($profile['province_id']) && isset($parishScope['province_id'])) {
                        $updateProfile['province_id'] = (int) $parishScope['province_id'];
                    }
                    if (empty($profile['diocese_id']) && isset($parishScope['diocese_id'])) {
                        $updateProfile['diocese_id'] = (int) $parishScope['diocese_id'];
                    }
                    if (empty($profile['first_name'])) {
                        $updateProfile['first_name'] = (string) $this->request->getPost('first_name');
                    }
                    if (empty($profile['last_name'])) {
                        $updateProfile['last_name'] = (string) $this->request->getPost('last_name');
                    }
                    if ($updateProfile !== []) {
                        $this->getProfileModel()->update((int) $profile['id'], $updateProfile);
                    }
                }

                $affiliationModel = model('MemberParishAffiliationModel');
                if (!$affiliationModel->attachToParish($userId, $parishId, false, 'member')) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
                }

                if (!$affiliationModel->setHomeParish($userId, $parishId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.homeParishUpdateFailed'));
                }

                if (!$this->userHasGroup($userId, $role)) {
                    $user->addGroup($role);
                }

                $this->logRoleAudit($parishId, $userId, 'promoted', $role, 'Assigned role to existing user');
                $db->transComplete();
                if ($db->transStatus() === false) {
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAccountCreateFailed'));
                }

                return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.staffExistingUserAssigned'));
            }

            $username = $this->makeUniqueUsername($email);
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $username,
                'email' => $email,
                'password' => (string) $this->request->getPost('password'),
            ]);

            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $user->activate();
            $user->addGroup($role);

            $this->getProfileModel()->insert([
                'user_id' => $user->id,
                'parish_id' => $parishId,
                'province_id' => isset($parishScope['province_id']) ? (int) $parishScope['province_id'] : null,
                'diocese_id' => isset($parishScope['diocese_id']) ? (int) $parishScope['diocese_id'] : null,
                'first_name' => (string) $this->request->getPost('first_name'),
                'last_name' => (string) $this->request->getPost('last_name'),
                'phone' => (string) $this->request->getPost('phone'),
                'membership_status' => 'active',
            ]);

            $affiliationModel = model('MemberParishAffiliationModel');
            if (!$affiliationModel->attachToParish((int) $user->id, $parishId, false, 'member')) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
            }

            if (!$affiliationModel->setHomeParish((int) $user->id, $parishId)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Diocesan.homeParishUpdateFailed'));
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAccountCreateFailed'));
            }

            return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.staffMemberAdded'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('Parishes.staffCreateError'), $e);
        }
    }

    public function promoteMember($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $role = (string) $this->request->getPost('role');
        $userId = (int) $this->request->getPost('user_id');

        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.memberNotInParish'));
        }

        $users = auth()->getProvider();
        $user = $users->findById($userId);
        if (!$user) {
            return redirect()->back()->with('error', lang('Diocesan.userNotFound'));
        }

        $user->addGroup($role);

        $this->logRoleAudit($parishId, $userId, 'promoted', $role, 'Assigned role to existing member');

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.roleAssigned'));
    }

    public function removeStaffRole($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $userId = (int) $this->request->getPost('user_id');
        $role = (string) $this->request->getPost('role');

        if (!in_array($role, $this->allowedAssignableRoles(), true)) {
            return redirect()->back()->with('error', lang('Diocesan.invalidRoleSelected'));
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && $role === 'parishadmin' && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotRemoveOwnParishAdminRole'));
        }

        if (!$this->userHasGroup($userId, $role)) {
            return redirect()->back()->with('error', lang('Diocesan.userMissingRole'));
        }

        db_connect()->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $role)
            ->delete();

        $this->logRoleAudit($parishId, $userId, 'role_removed', $role, 'Removed single role from user');

        if (!$this->userHasAnyGroup($userId)) {
            $this->addGroupIfMissing($userId, 'member');
        }

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.roleRemoved'));
    }

    public function demoteStaff($id)
    {
        $parishId = (int) $id;
        if (!$this->canManageParish($parishId)) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.parishStaffAccessDenied'));
        }

        // Province admins cannot manage parish staff
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/parish_list')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
        }

        $userId = (int) $this->request->getPost('user_id');

        if ($code = $this->guardTargetUserMutation($userId)) {
            return redirect()->back()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotDemoteOwnAccount'));
        }

        $rolesRemoved = 0;
        foreach ($this->allowedAssignableRoles() as $role) {
            if ($this->userHasGroup($userId, $role)) {
                db_connect()->table('auth_groups_users')
                    ->where('user_id', $userId)
                    ->where('group', $role)
                    ->delete();
                $rolesRemoved++;
                $this->logRoleAudit($parishId, $userId, 'demoted', $role, 'Demotion removed role');
            }
        }

        $this->addGroupIfMissing($userId, 'member');

        if ($rolesRemoved === 0) {
            return redirect()->to('/parish_list/' . $parishId . '/staff')->with('error', lang('Diocesan.noAdminLeaderRolesFound'));
        }

        return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.userDemotedToMember'));
    }

    protected function canAccessParishesModule(): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if (!$this->hasParishEditPermission()) {
            return false;
        }

        return $this->isDioceseAdmin() || $this->isParishAdminOnly();
    }

    protected function canCreateParish(): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return $this->hasParishEditPermission() && $this->isDioceseAdmin();
    }

    protected function canDeleteParish(): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return $this->hasParishEditPermission() && $this->isDioceseAdmin();
    }

    protected function canManageDioceseScope(int $dioceseId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if (!$this->isDioceseAdmin() || !$this->hasParishEditPermission()) {
            return false;
        }

        return $this->resolveDiocesanScopeId() === $dioceseId;
    }

    protected function isParishAdminOnly(): bool
    {
        return (bool) ($this->user
            && $this->user->inGroup('parishadmin')
            && !$this->user->inGroup('provinceadmin')
            && !$this->user->inGroup('dioceseadmin'));
    }

    protected function canManageParish(int $parishId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if (!$this->hasParishEditPermission()) {
            return false;
        }

        if ($this->isProvinceAdmin()) {
            // Province workflow is read-only for parishes.
            return false;
        }

        if ($this->isDioceseAdmin()) {
            $scopeDioceseId = $this->resolveDiocesanScopeId();
            if ($scopeDioceseId <= 0) {
                return false;
            }

            $parish = $this->parishModel->select('id, diocese_id')->find($parishId);
            if (!$parish) {
                return false;
            }

            return (int) ($parish['diocese_id'] ?? 0) === $scopeDioceseId;
        }

        return $this->isParishAdminOnly() && (int) $this->getOperationalparishId() === $parishId;
    }

    protected function hasParishEditPermission(): bool
    {
        return $this->checkPermission('parishes.edit')
            || $this->checkPermission('churches.edit')
            || $this->checkPermission('parish.edit')
            || $this->checkPermission('diocese.admin');
    }

    protected function resolveDiocesanScopeId(): int
    {
        $profile = $this->getProfile();

        if (!empty($profile['diocese_id'])) {
            return (int) $profile['diocese_id'];
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId > 0) {
            $parish = $this->parishModel->find($parishId);
            if (!empty($parish['diocese_id'])) {
                return (int) $parish['diocese_id'];
            }
        }

        return 0;
    }

    protected function allowedAssignableRoles(): array
    {
        // Only diocesan/system administrators may create or remove parish admin accounts.
        if ($this->isParishAdminOnly()) {
            return ['clergy', 'treasurer', 'volunteer_coordinator'];
        }

        return ['parishadmin', 'clergy', 'treasurer', 'volunteer_coordinator'];
    }

    protected function getProvinceOptions(): array
    {
        return db_connect()->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    protected function getDiocesesByProvince(): array
    {
        $builder = db_connect()->table('diocese d')
            ->select('d.id, d.name, d.province_id')
            ->orderBy('d.name', 'ASC');

        // Province admins may only assign parishes to dioceses within their province.
        if ($this->isProvinceAdmin()) {
            $adminProvinceId = $this->resolveAdminProvinceId();
            if ($adminProvinceId > 0) {
                $builder->where('d.province_id', $adminProvinceId);
            }
        }

        $rows = $builder->get()->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $provinceId = (int) ($row['province_id'] ?? 0);
            if ($provinceId <= 0) {
                continue;
            }

            if (!isset($grouped[$provinceId])) {
                $grouped[$provinceId] = [];
            }

            $grouped[$provinceId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $grouped;
    }

    protected function makeUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower((string) strstr($email, '@', true)));
        $base = $base ?: 'user';
        $candidate = $base;
        $counter = 1;

        while (db_connect()->table('users')->where('username', $candidate)->countAllResults() > 0) {
            $candidate = $base . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected function isCurrentUser(int $userId): bool
    {
        return (int) ($this->user->id ?? 0) === $userId;
    }

    protected function isUserInParish(int $userId, int $parishId): bool
    {
        return model('MemberParishAffiliationModel')
            ->isUserAffiliatedWithParish($userId, $parishId, true);
    }

    protected function userHasGroup(int $userId, string $group): bool
    {
        return db_connect()->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $group)
            ->countAllResults() > 0;
    }

    protected function userHasAnyGroup(int $userId): bool
    {
        return db_connect()->table('auth_groups_users')
            ->where('user_id', $userId)
            ->countAllResults() > 0;
    }

    protected function addGroupIfMissing(int $userId, string $group): void
    {
        if ($this->userHasGroup($userId, $group)) {
            return;
        }

        db_connect()->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function logRoleAudit(int $parishId, int $targetUserId, string $action, ?string $role = null, ?string $details = null): void
    {
        $actorUserId = (int) ($this->user->id ?? 0);
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return;
        }

        $this->parishRoleAuditModel->insert([
            'parish_id' => $parishId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'role' => $role,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function getDeleteImpactCounts(int $parishId): array
    {
        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable();
        $profileCount = 0;
        if ($profileTable !== null) {
            $profileCount = (int) $db->table($profileTable)
                ->where('parish_id', $parishId)
                ->countAllResults();
        }

        return [
            'members' => (int) $db->table('parish_affiliation')
                ->where('parish_id', $parishId)
                ->where('is_member', 1)
                ->countAllResults(),
            'profile_unscoped' => $profileCount,
            'parish_role_audit_logs_unscoped' => (int) $db->table('parish_role_audit_log')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'donations' => (int) $db->table('donation')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'services' => (int) $db->table('service')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'attendance' => (int) $db->table('attendance a')
                ->join('service s', 's.id = a.service_id', 'inner')
                ->where('s.parish_id', $parishId)
                ->countAllResults(),
            'events' => (int) $db->table('event')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'event_registrations' => (int) $db->table('event_registration')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'guests' => (int) $db->table('guest')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'expenses' => (int) $db->table('expense')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'families' => (int) $db->table('family')
                ->where('parish_id', $parishId)
                ->countAllResults(),
            'volunteer_teams' => (int) $db->table('team')
                ->where('parish_id', $parishId)
                ->countAllResults(),
        ];
    }

    private function resolveExistingProfileTable(): ?string
    {
        $db = db_connect();
        $candidates = [$db->getPrefix() . 'profile', 'profile'];
        foreach (array_unique($candidates) as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Table doesn't exist.
            }
        }
        return null;
    }

    private function resolveStoredUploadAbsolutePath(string $storedPath, int $parishId, string $assetKind = 'logo'): ?string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
            return null;
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        $assetKind = strtolower(trim($assetKind)) === 'banner' ? 'banner' : 'logo';
        $candidates = [$normalized];
        if (!str_starts_with($normalized, 'parish/')) {
            // Legacy and flat names are resolved to known parish asset roots as fallback.
            $candidates[] = 'parish/' . $parishId . '/' . $assetKind . '/' . $normalized;
            $candidates[] = 'parishes/' . $parishId . '/' . $assetKind . '/' . $normalized;
            $candidates[] = 'churches/' . $parishId . '/' . $assetKind . '/' . $normalized;
        }

        foreach ($candidates as $candidate) {
            $fullPath = realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate));
            if ($fullPath === false || !is_file($fullPath)) {
                continue;
            }

            $uploadsRootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($fullPath, $uploadsRootPrefix)) {
                return $fullPath;
            }
        }

        return null;
    }
}
