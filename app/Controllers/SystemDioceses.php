<?php

namespace App\Controllers;

class SystemDioceses extends BaseController
{
    use SystemAdminService;

    public function storeDiocese()
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $rules = [
            'province_id' => 'required|integer|is_not_unique[province.id]',
            'name' => 'required|min_length[2]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceId = (int) $this->request->getPost('province_id');
        $name = trim((string) $this->request->getPost('name'));

        $db = db_connect();
        $exists = $db->table('diocese')
            ->where('province_id', $provinceId)
            ->where('LOWER(name)', strtolower($name))
            ->limit(1)->get()->getRow() !== null;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', lang('System.dioceseExistsInProvince'));
        }

        $db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit()->log('system.diocese.created', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'diocese',
            'entity_id' => (int) $db->insertID(),
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'new_values' => [
                'province_id' => $provinceId,
                'name' => $name,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.dioceseCreated'));
    }

    public function updateDiocese(int $id)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $rules = [
            'province_id' => 'required|integer|is_not_unique[province.id]',
            'name' => 'required|min_length[2]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceId = (int) $this->request->getPost('province_id');
        $name = trim((string) $this->request->getPost('name'));

        $db = db_connect();
        $diocese = $db->table('diocese')->where('id', $id)->get()->getRowArray();
        if (!$diocese) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.dioceseNotFound'));
        }

        $exists = $db->table('diocese')
            ->where('province_id', $provinceId)
            ->where('LOWER(name)', strtolower($name))
            ->where('id !=', $id)
            ->limit(1)->get()->getRow() !== null;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', lang('System.dioceseExistsInProvince'));
        }

        $db->table('diocese')
            ->where('id', $id)
            ->update([
                'province_id' => $provinceId,
                'name' => $name,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->audit()->log('system.diocese.updated', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'diocese',
            'entity_id' => (int) $id,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => [
                'province_id' => $diocese['province_id'] ?? null,
                'name' => $diocese['name'] ?? null,
            ],
            'new_values' => [
                'province_id' => $provinceId,
                'name' => $name,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.dioceseUpdated'));
    }

    public function deleteDiocese(int $id)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $db = db_connect();
        $diocese = $db->table('diocese')->where('id', $id)->get()->getRowArray();
        if (!$diocese) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.dioceseNotFound'));
        }

        $parishCount = (int) $db->table('parish')->where('diocese_id', $id)->countAllResults();
        if ($parishCount > 0) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.dioceseDeleteBlocked'));
        }

        $db->table('diocese')->where('id', $id)->delete();

        $this->audit()->log('system.diocese.deleted', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'diocese',
            'entity_id' => (int) $id,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => [
                'province_id' => $diocese['province_id'] ?? null,
                'name' => $diocese['name'] ?? null,
            ],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('System.dioceseDeleted'));
    }
}
