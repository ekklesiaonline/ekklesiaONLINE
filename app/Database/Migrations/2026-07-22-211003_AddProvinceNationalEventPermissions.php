<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProvinceNationalEventPermissions extends Migration
{
    private const PERMISSIONS = [
        ['name' => 'province.event.create', 'description' => 'Create province-scoped events'],
        ['name' => 'province.event.edit',   'description' => 'Edit province-scoped events'],
        ['name' => 'province.event.delete', 'description' => 'Delete province-scoped events'],
        ['name' => 'province.calendar.manage', 'description' => 'Manage province-level event calendars'],
        ['name' => 'national.event.manage',    'description' => 'Create, edit, and delete national Episcopal events'],
        ['name' => 'national.calendar.manage', 'description' => 'Manage the national Episcopal event calendar'],
    ];

    public function up()
    {
        if (!$this->db->tableExists('auth_permissions') || !$this->db->tableExists('auth_groups_permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::PERMISSIONS as $permission) {
            if ($this->db->table('auth_permissions')->where('name', $permission['name'])->countAllResults() === 0) {
                $this->db->table('auth_permissions')->insert([
                    'name'        => $permission['name'],
                    'description' => $permission['description'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        $provincePermissions = array_filter(self::PERMISSIONS, static fn ($p) => str_starts_with($p['name'], 'province.'));
        foreach ($provincePermissions as $permission) {
            if ($this->db->table('auth_groups_permissions')->where('group', 'provinceadmin')->where('permission', $permission['name'])->countAllResults() === 0) {
                $this->db->table('auth_groups_permissions')->insert([
                    'group'      => 'provinceadmin',
                    'permission' => $permission['name'],
                ]);
            }
        }

        $nationalPermissions = array_filter(self::PERMISSIONS, static fn ($p) => str_starts_with($p['name'], 'national.'));
        foreach ($nationalPermissions as $permission) {
            if ($this->db->table('auth_groups_permissions')->where('group', 'systemadmin')->where('permission', $permission['name'])->countAllResults() === 0) {
                $this->db->table('auth_groups_permissions')->insert([
                    'group'      => 'systemadmin',
                    'permission' => $permission['name'],
                ]);
            }
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('auth_permissions') || !$this->db->tableExists('auth_groups_permissions')) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            $this->db->table('auth_groups_permissions')->where('permission', $permission['name'])->delete();
            $this->db->table('auth_permissions')->where('name', $permission['name'])->delete();
        }
    }
}
