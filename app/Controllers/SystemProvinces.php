<?php

namespace App\Controllers;

class SystemProvinces extends BaseController
{
    use SystemAdminService;

    public function storeProvince()
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $rules = [
            'province_number' => 'required|max_length[10]',
            'name' => 'required|min_length[2]|max_length[150]',
            'sort_order' => 'permit_empty|integer|greater_than_equal_to[0]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceNumber = trim((string) $this->request->getPost('province_number'));
        $name = trim((string) $this->request->getPost('name'));
        $sortOrder = (int) ($this->request->getPost('sort_order') ?? 0);

        $db = db_connect();

        $exists = $db->table('province')
            ->where('province_number', $provinceNumber)
            ->limit(1)->get()->getRow() !== null;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', lang('System.provinceNumberExists'));
        }

        $db->table('province')->insert([
            'province_number' => $provinceNumber,
            'name' => $name,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit()->log('system.province.created', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'province',
            'entity_id' => (int) $db->insertID(),
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'new_values' => [
                'province_number' => $provinceNumber,
                'name' => $name,
                'sort_order' => $sortOrder,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.provinceCreated'));
    }

    public function updateProvince(int $id)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $rules = [
            'province_number' => 'required|max_length[10]',
            'name' => 'required|min_length[2]|max_length[150]',
            'sort_order' => 'permit_empty|integer|greater_than_equal_to[0]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceNumber = trim((string) $this->request->getPost('province_number'));
        $name = trim((string) $this->request->getPost('name'));
        $sortOrder = (int) ($this->request->getPost('sort_order') ?? 0);

        $db = db_connect();
        $province = $db->table('province')->where('id', $id)->get()->getRowArray();
        if (!$province) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.provinceNotFound'));
        }

        $exists = $db->table('province')
            ->where('province_number', $provinceNumber)
            ->where('id !=', $id)
            ->limit(1)->get()->getRow() !== null;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', lang('System.provinceNumberExists'));
        }

        $db->table('province')
            ->where('id', $id)
            ->update([
                'province_number' => $provinceNumber,
                'name' => $name,
                'sort_order' => $sortOrder,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->audit()->log('system.province.updated', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'province',
            'entity_id' => (int) $id,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => [
                'province_number' => $province['province_number'] ?? null,
                'name' => $province['name'] ?? null,
                'sort_order' => $province['sort_order'] ?? null,
            ],
            'new_values' => [
                'province_number' => $provinceNumber,
                'name' => $name,
                'sort_order' => $sortOrder,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.provinceUpdated'));
    }

    public function deleteProvince(int $id)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $db = db_connect();
        $province = $db->table('province')->where('id', $id)->get()->getRowArray();
        if (!$province) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.provinceNotFound'));
        }

        $dioceseCount = (int) $db->table('diocese')->where('province_id', $id)->countAllResults();
        if ($dioceseCount > 0) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.provinceDeleteBlocked'));
        }

        $db->table('province')->where('id', $id)->delete();

        $this->audit()->log('system.province.deleted', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'province',
            'entity_id' => (int) $id,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => [
                'province_number' => $province['province_number'] ?? null,
                'name' => $province['name'] ?? null,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.provinceDeleted'));
    }
}
