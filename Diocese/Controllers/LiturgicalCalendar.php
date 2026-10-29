<?php

namespace Diocese\Controllers;

use App\Controllers\BaseController;

class LiturgicalCalendar extends BaseController
{
    protected $liturgicalCalendarEntryModel;
    private ?bool $supportsOwnershipMetadata = null;

    public function __construct()
    {
        $this->liturgicalCalendarEntryModel = model('LiturgicalCalendarEntryModel');
    }

    public function index()
    {
        if (!$this->canViewLiturgicalSource()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $this->applyLocaleForView();
        $this->disableResponseCaching();

        $entries = $this->getScopedEntries();

        return view('Diocese\Views\LiturgicalCalendar/index', [
            'page_title' => lang('Diocesan.liturgicalTitle'),
            'entries' => $entries,
            'seasons' => $this->getSeasonOptions(),
            'observance_types' => $this->getObservanceTypeOptions(),
            'liturgical_colors' => $this->getColorOptions(),
            'current_parish_id' => $this->getOperationalparishId(),
            'is_province_admin' => $this->isProvinceAdmin(),
            'can_manage_liturgical_source' => $this->canManageLiturgicalSource(),
            'liturgical_write_base' => $this->liturgicalWriteBasePath(),
        ]);
    }

    public function store()
    {
        if (!$this->canManageLiturgicalSource()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $rules = [
            'name' => 'required|max_length[255]',
            'season' => 'required|in_list[' . implode(',', $this->getSeasonOptions()) . ']',
            'observance_type' => 'required|in_list[' . implode(',', $this->getObservanceTypeOptions()) . ']',
            'sort_order' => 'required|integer|greater_than_equal_to[0]',
            'liturgical_color' => 'required|in_list[' . implode(',', $this->getColorOptions()) . ']',
            'season_icon' => 'permit_empty|max_length[120]',
            'observance_icon' => 'permit_empty|max_length[120]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = trim((string) $this->request->getPost('name'));
        $season = (string) $this->request->getPost('season');

        $data = [
            'parish_id' => null,
            'code' => $this->buildUniqueCode($season, $name),
            'name' => $name,
            'season' => $season,
            'observance_type' => (string) $this->request->getPost('observance_type'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'liturgical_color' => (string) $this->request->getPost('liturgical_color'),
            'season_icon' => $this->request->getPost('season_icon') ?: null,
            'observance_icon' => $this->request->getPost('observance_icon') ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if ($this->supportsOwnershipMetadata()) {
            $data['source_tier'] = 'system';
            $data['is_system_template'] = 1;
            $data['created_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
            $data['owner_province_id'] = null;
            $data['owner_diocese_id'] = null;
        }

        if (!$this->liturgicalCalendarEntryModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.liturgicalEntryAddFailed'));
        }

        $entryId = $this->liturgicalCalendarEntryModel->getInsertID();

        try {
            $this->audit()->log('diocese.liturgical_entry.created', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'liturgical_entry',
                'entity_id' => $entryId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'new_values' => [
                    'name' => $name,
                    'season' => $season,
                    'observance_type' => $data['observance_type'],
                    'liturgical_color' => $data['liturgical_color'],
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for liturgical entry creation: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('success', lang('Diocesan.liturgicalEntryAdded'));
    }

    public function edit($id)
    {
        if (!$this->canManageLiturgicalSource()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $this->applyLocaleForView();
        $this->disableResponseCaching();

        $entry = $this->liturgicalCalendarEntryModel->find((int) $id);
        if (!$entry || !$this->canModifyEntry($entry)) {
            return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('error', lang('Diocesan.liturgicalEntryNotEditable'));
        }

        return view('Diocese\Views\LiturgicalCalendar/edit', [
            'page_title' => lang('Diocesan.liturgicalEditEntryTitle'),
            'entry' => $entry,
            'seasons' => $this->getSeasonOptions(),
            'observance_types' => $this->getObservanceTypeOptions(),
            'liturgical_colors' => $this->getColorOptions(),
            'liturgical_write_base' => $this->liturgicalWriteBasePath(),
        ]);
    }

    public function update($id)
    {
        if (!$this->canManageLiturgicalSource()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $entry = $this->liturgicalCalendarEntryModel->find((int) $id);
        if (!$entry || !$this->canModifyEntry($entry)) {
            return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('error', lang('Diocesan.liturgicalEntryNotEditable'));
        }

        $rules = [
            'name' => 'required|max_length[255]',
            'season' => 'required|in_list[' . implode(',', $this->getSeasonOptions()) . ']',
            'observance_type' => 'required|in_list[' . implode(',', $this->getObservanceTypeOptions()) . ']',
            'sort_order' => 'required|integer|greater_than_equal_to[0]',
            'liturgical_color' => 'required|in_list[' . implode(',', $this->getColorOptions()) . ']',
            'season_icon' => 'permit_empty|max_length[120]',
            'observance_icon' => 'permit_empty|max_length[120]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = trim((string) $this->request->getPost('name'));
        $season = (string) $this->request->getPost('season');

        // Capture old values for audit logging
        $oldValues = [
            'name' => $entry['name'] ?? null,
            'season' => $entry['season'] ?? null,
            'observance_type' => $entry['observance_type'] ?? null,
            'liturgical_color' => $entry['liturgical_color'] ?? null,
        ];

        $data = [
            'name' => $name,
            'season' => $season,
            'observance_type' => (string) $this->request->getPost('observance_type'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'liturgical_color' => (string) $this->request->getPost('liturgical_color'),
            'season_icon' => $this->request->getPost('season_icon') ?: null,
            'observance_icon' => $this->request->getPost('observance_icon') ?: null,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if (!$this->liturgicalCalendarEntryModel->update((int) $id, $data)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.liturgicalEntryUpdateFailed'));
        }

        try {
            $this->audit()->log('diocese.liturgical_entry.updated', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'liturgical_entry',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => $oldValues,
                'new_values' => [
                    'name' => $name,
                    'season' => $season,
                    'observance_type' => $data['observance_type'],
                    'liturgical_color' => $data['liturgical_color'],
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for liturgical entry update: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('success', lang('Diocesan.liturgicalEntryUpdated'));
    }

    public function delete($id)
    {
        if (!$this->canManageLiturgicalSource()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $entry = $this->liturgicalCalendarEntryModel->find((int) $id);
        if (!$entry || !$this->canModifyEntry($entry)) {
            return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('error', lang('Diocesan.liturgicalEntryNotEditable'));
        }

        // Capture old values for audit logging
        $oldValues = [
            'name' => $entry['name'] ?? null,
            'season' => $entry['season'] ?? null,
            'observance_type' => $entry['observance_type'] ?? null,
            'liturgical_color' => $entry['liturgical_color'] ?? null,
        ];

        $this->liturgicalCalendarEntryModel->delete((int) $id);

        try {
            $this->audit()->log('diocese.liturgical_entry.deleted', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'liturgical_entry',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => $oldValues,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for liturgical entry deletion: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(base_url($this->liturgicalWriteBasePath()))->with('success', lang('Diocesan.liturgicalEntryDeleted'));
    }

    private function getScopedEntries(): array
    {
        $isProvinceAdmin = $this->isProvinceAdmin();
        $currentparishId = (int) ($this->getOperationalparishId() ?? 0);
        $currentDioceseId = (int) $this->resolveDiocesanScopeId();
        $currentProvinceId = (int) $this->resolveCurrentProvinceScopeId();

        $builder = $this->liturgicalCalendarEntryModel->builder();
        $select = 'id, parish_id, code, name, season, observance_type, sort_order, liturgical_color, season_icon, observance_icon, is_active';

        if ($this->supportsOwnershipMetadata()) {
            $select .= ', source_tier, is_system_template, created_by_user_id, owner_province_id, owner_diocese_id';
            $builder->select($select);

            if ($isProvinceAdmin) {
                $builder->groupStart()
                    ->where('source_tier', 'system')
                    ->orGroupStart()
                    ->whereIn('source_tier', ['province', 'diocese'])
                    ->where('owner_province_id', $currentProvinceId)
                    ->groupEnd()
                    ->groupEnd();
            } else {
                $builder->groupStart()
                    ->where('source_tier', 'system')
                    ->orGroupStart()
                    ->where('source_tier', 'province')
                    ->where('owner_province_id', $currentProvinceId)
                    ->groupEnd()
                    ->orGroupStart()
                    ->where('source_tier', 'diocese')
                    ->where('owner_diocese_id', $currentDioceseId)
                    ->groupEnd()
                    ->groupEnd();
            }
        } else {
            $builder->select($select);
            if ($currentparishId > 0) {
                $builder->groupStart()
                    ->where('parish_id', $currentparishId)
                    ->orWhere('parish_id', null)
                    ->groupEnd();
            } elseif (!$isProvinceAdmin) {
                $builder->where('parish_id', null);
            }
        }

        $builder->orderBy('season', 'ASC');
        $builder->orderBy('sort_order', 'ASC');

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['can_edit'] = $this->canModifyEntry($row);
        }
        unset($row);

        return $rows;
    }

    private function canModifyEntry(array $entry): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if (!$this->canManageLiturgicalSource()) {
            return false;
        }

        if ($this->supportsOwnershipMetadata()) {
            $isSystemTemplate = (int) ($entry['is_system_template'] ?? 1) === 1 || (string) ($entry['source_tier'] ?? 'system') === 'system';
            if ($isSystemTemplate) {
                return false;
            }

            $createdByUserId = (int) ($entry['created_by_user_id'] ?? 0);
            return $createdByUserId > 0 && $createdByUserId === (int) (auth()->id() ?? 0);
        }

        $parishId = (int) $this->getOperationalparishId();
        return !empty($entry['parish_id']) && (int) $entry['parish_id'] === $parishId;
    }

    private function canViewLiturgicalSource(): bool
    {
        return $this->isSystemAdmin() || $this->isDioceseAdmin() || $this->isProvinceAdmin();
    }

    private function canManageLiturgicalSource(): bool
    {
        return $this->isSystemAdmin();
    }

    private function liturgicalWriteBasePath(): string
    {
        return $this->isSystemAdmin() ? 'system/liturgical-source' : 'liturgical-source';
    }

    private function resolveCurrentProvinceScopeId(): int
    {
        $sessionProvinceId = (int) (session()->get('province_scope_id') ?? 0);
        if ($sessionProvinceId > 0) {
            return $sessionProvinceId;
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        if ($dioceseId > 0) {
            $diocese = db_connect()->table('diocese')
                ->select('province_id')
                ->where('id', $dioceseId)
                ->get()
                ->getRowArray();
            return (int) ($diocese['province_id'] ?? 0);
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId > 0) {
            $row = db_connect()->table('parish c')
                ->select('d.province_id')
                ->join('diocese d', 'd.id = c.diocese_id', 'inner')
                ->where('c.id', $parishId)
                ->get()
                ->getRowArray();
            return (int) ($row['province_id'] ?? 0);
        }

        return 0;
    }

    private function supportsOwnershipMetadata(): bool
    {
        if ($this->supportsOwnershipMetadata !== null) {
            return $this->supportsOwnershipMetadata;
        }

        $db = db_connect();
        $table = $this->liturgicalCalendarEntryModel->resolvedTable();
        if (!$db->tableExists($table)) {
            $this->supportsOwnershipMetadata = false;
            return false;
        }

        $fields = array_map('strtolower', $db->getFieldNames($table));
        $required = ['source_tier', 'is_system_template', 'created_by_user_id', 'owner_province_id', 'owner_diocese_id'];

        foreach ($required as $field) {
            if (!in_array($field, $fields, true)) {
                $this->supportsOwnershipMetadata = false;
                return false;
            }
        }

        $this->supportsOwnershipMetadata = true;
        return true;
    }

    private function buildUniqueCode(string $season, string $name): string
    {
        $base = strtolower($season . '_' . $name);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base) ?? 'liturgical_entry';
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'liturgical_entry';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->liturgicalCalendarEntryModel->where('code', $candidate)->first()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function getSeasonOptions(): array
    {
        return [
            'Advent',
            'Christmas',
            'Epiphany',
            'Lent',
            'Holy Week',
            'Easter',
            'Pentecost',
            'Season after Pentecost',
        ];
    }

    private function getObservanceTypeOptions(): array
    {
        return ['sunday', 'feast', 'day', 'other'];
    }

    private function getColorOptions(): array
    {
        return ['blue', 'purple', 'white', 'green', 'red', 'rose', 'black', 'gold'];
    }

    private function applyLocaleForView(): void
    {
        $locale = strtolower((string) ($this->request->getLocale() ?: config('App')->defaultLocale));
        service('request')->setLocale($locale);
        service('language')->setLocale($locale);
    }

    private function disableResponseCaching(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');
    }
}
