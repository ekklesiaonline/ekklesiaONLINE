<?php

namespace Diocese\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\ParishDeletionService;
use App\Services\ParishStaffService;

class Parishes extends BaseController
{
    use AdminHierarchyGuard;

    protected $parishModel;
    protected $profileModel;
    protected $parishRoleAuditModel;
    private ParishStaffService $staffService;
    private ParishDeletionService $deletionService;

    public function __construct()
    {
        $this->parishModel = model('ParishModel');
        $this->parishRoleAuditModel = model('ParishRoleAuditModel');
        $this->staffService = new ParishStaffService();
        $this->deletionService = new ParishDeletionService();
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

            return view('Diocese\Views\Parishes/index', array_merge(
                $paginated,
                [
                    'parishes'           => $paginated['parish'] ?? [],
                    'selectedParishId'   => (int) ($this->getOperationalparishId() ?? 0),
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

        return view('Diocese\Views\Parishes/index', array_merge(
            $paginated,
            [
                'parishes'           => $paginated['parish'] ?? [],
                'selectedParishId'   => (int) ($this->getOperationalparishId() ?? 0),
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

        return view('Diocese\Views\Parishes/create', array_merge($this->getCommonViewData(lang('Parishes.createPageTitle')), [
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
            // Every new parish starts with the best upstream default
            // follow-up sequence cloned into its own scope (diocesan →
            // provincial → global cascade).
            try {
                (new \App\Services\FollowupCloneService())->cloneUpstreamDefaultIntoParish((int) $parishId);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to clone default follow-up sequence for new parish ' . $parishId . ': ' . $e->getMessage());
            }
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

        return view('Diocese\Views\Parishes/edit', array_merge($this->getCommonViewData(lang('Parishes.editPageTitle')), [
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

        if (!$this->deletionService->deleteParish($parishId, $purgeNullableRefs, $this->resolveExistingProfileTable())) {
            return ['success' => false, 'status' => 500, 'message' => lang('Diocesan.parishDeleteFailed')];
        }

        try {
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
            log_message('error', 'Parish delete audit failed: {message}', ['message' => $e->getMessage()]);
            return [
                'success' => true,
                'status' => 200,
                'message' => lang('Diocesan.parishDeleted'),
                'parish' => [
                    'id' => (int) ($parish['id'] ?? 0),
                    'name' => (string) ($parish['name'] ?? ''),
                ],
            ];
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
                'impact' => $this->deletionService->impactCounts($parishId, $this->resolveExistingProfileTable()),
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

        return view('Diocese\Views\Parishes/staff', array_merge($this->getCommonViewData(lang('Parishes.staffPageTitle')), [
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

        if (!$this->validate($this->staffService->staffValidationRules($exists))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transStart();

        try {
            $users = auth()->getProvider();
            $parishScope = $this->parishModel->findWithHierarchy($parishId);

            if ($exists) {
                $userId = $this->staffService->userIdForEmail($email);

                if ($userId === null) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                if ($code = $this->guardTargetUserMutation($userId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
                }

                $user = $users->findById($userId);
                if (!$user) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.userNotFound'));
                }

                $profile = $this->staffService->profileForUser($userId);
                if ($profile && !empty($profile['parish_id']) && (int) $profile['parish_id'] !== $parishId) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.emailBelongsToOtherParish'));
                }

                $this->staffService->ensureStaffProfile(
                    $profile,
                    $userId,
                    $parishId,
                    $parishScope ?? [],
                    (string) $this->request->getPost('first_name'),
                    (string) $this->request->getPost('last_name')
                );

                if (!$this->staffService->attachToParish($userId, $parishId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
                }

                if (!$this->staffService->setHomeParish($userId, $parishId)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.homeParishUpdateFailed'));
                }

                if (!$this->staffService->userHasGroup($userId, $role)) {
                    $user->addGroup($role);
                }

                $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'promoted', $role, 'Assigned role to existing user');
                $db->transComplete();
                if ($db->transStatus() === false) {
                    return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAccountCreateFailed'));
                }

                return redirect()->to('/parish_list/' . $parishId . '/staff')->with('success', lang('Diocesan.staffExistingUserAssigned'));
            }

            $username = $this->staffService->makeUniqueUsername($email);
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $username,
                'email' => $email,
                'password' => (string) $this->request->getPost('password'),
            ]);

            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $user->activate();
            $user->addGroup($role);

            $this->staffService->createStaffProfile(
                (int) $user->id,
                $parishId,
                $parishScope ?? [],
                (string) $this->request->getPost('first_name'),
                (string) $this->request->getPost('last_name'),
                (string) $this->request->getPost('phone')
            );

            if (!$this->staffService->attachToParish((int) $user->id, $parishId)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Diocesan.staffAffiliationCreateFailed'));
            }

            if (!$this->staffService->setHomeParish((int) $user->id, $parishId)) {
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

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.memberNotInParish'));
        }

        $users = auth()->getProvider();
        $user = $users->findById($userId);
        if (!$user) {
            return redirect()->back()->with('error', lang('Diocesan.userNotFound'));
        }

        $user->addGroup($role);

        $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'promoted', $role, 'Assigned role to existing member');

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

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && $role === 'parishadmin' && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotRemoveOwnParishAdminRole'));
        }

        if (!$this->staffService->userHasGroup($userId, $role)) {
            return redirect()->back()->with('error', lang('Diocesan.userMissingRole'));
        }

        $this->staffService->removeGroup($userId, $role);

        $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'role_removed', $role, 'Removed single role from user');

        if (!$this->staffService->userHasAnyGroup($userId)) {
            $this->staffService->addGroupIfMissing($userId, 'member');
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

        if (!$this->staffService->isUserInParish($userId, $parishId)) {
            return redirect()->back()->with('error', lang('Diocesan.userNotInParish'));
        }

        if ($this->isCurrentUser($userId) && !$this->isProvinceAdmin()) {
            return redirect()->back()->with('error', lang('Diocesan.cannotDemoteOwnAccount'));
        }

        $rolesRemoved = 0;
        foreach ($this->allowedAssignableRoles() as $role) {
            if ($this->staffService->userHasGroup($userId, $role)) {
                $this->staffService->removeGroup($userId, $role);
                $rolesRemoved++;
                $this->staffService->logRoleAudit($parishId, (int) ($this->user->id ?? 0), $userId, 'demoted', $role, 'Demotion removed role');
            }
        }

        $this->staffService->addGroupIfMissing($userId, 'member');

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

    protected function isCurrentUser(int $userId): bool
    {
        return (int) ($this->user->id ?? 0) === $userId;
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
            $candidates[] = 'parishes/' . $parishId . '/' . $assetKind . '/' . $normalized;
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
