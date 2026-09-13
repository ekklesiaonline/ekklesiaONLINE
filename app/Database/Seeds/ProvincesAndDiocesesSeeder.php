<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProvincesAndDiocesesSeeder extends Seeder
{
    public function run()
    {
        $provinces = [
            ['province_number' => 1, 'name' => 'Province I'],
            ['province_number' => 2, 'name' => 'Province II'],
            ['province_number' => 3, 'name' => 'Province III'],
            ['province_number' => 4, 'name' => 'Province IV'],
            ['province_number' => 5, 'name' => 'Province V'],
            ['province_number' => 6, 'name' => 'Province VI'],
            ['province_number' => 7, 'name' => 'Province VII'],
            ['province_number' => 8, 'name' => 'Province VIII'],
            ['province_number' => 9, 'name' => 'Province IX'],
            ['province_number' => 10, 'name' => 'Province X'],
        ];

        foreach ($provinces as $province) {
            $existing = $this->db->table('province')
                ->where('province_number', $province['province_number'])
                ->get()->getFirstRow('array');
            if (!$existing) {
                $this->db->table('province')->insert($province);
            }
        }

        $firstProvince = $this->db->table('province')
            ->orderBy('id', 'ASC')->get()->getFirstRow('array');

        if ($firstProvince) {
            $dioceses = [
                ['province_id' => $firstProvince['id'], 'name' => 'Sample Diocese One', 'alias_norm' => 'sample diocese one'],
                ['province_id' => $firstProvince['id'], 'name' => 'Sample Diocese Two', 'alias_norm' => 'sample diocese two'],
            ];

            foreach ($dioceses as $diocese) {
                $existing = $this->db->table('diocese')->where('name', $diocese['name'])->get()->getFirstRow('array');
                if (!$existing) {
                    $this->db->table('diocese')->insert($diocese);
                }
            }
        }
    }
}
