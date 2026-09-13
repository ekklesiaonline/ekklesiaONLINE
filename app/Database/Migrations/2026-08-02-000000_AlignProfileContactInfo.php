<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlignProfileContactInfo extends Migration
{
    public function up()
    {
        $profileTable = $this->resolveExistingTable(['profile']);

        if ($profileTable === null) {
            return;
        }

        $this->addColumns($profileTable);
        $this->backfillContactData($profileTable);
    }

    public function down()
    {
        $profileTable = $this->resolveExistingTable(['profile']);

        if ($profileTable === null) {
            return;
        }

        if ($this->db->fieldExists('phone_country_code', $profileTable)) {
            $this->forge->dropColumn($profileTable, 'phone_country_code');
        }

        if ($this->db->fieldExists('address_line_2', $profileTable)) {
            $this->forge->dropColumn($profileTable, 'address_line_2');
        }
    }

    private function addColumns(string $profileTable): void
    {
        if (!$this->db->fieldExists('phone_country_code', $profileTable)) {
            $this->forge->addColumn($profileTable, [
                'phone_country_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'phone',
                ],
            ]);
        }

        if (!$this->db->fieldExists('address_line_2', $profileTable)) {
            $this->forge->addColumn($profileTable, [
                'address_line_2' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'address',
                ],
            ]);
        }
    }

    private function backfillContactData(string $profileTable): void
    {
        $defaultCallingCode = '+1';

        $this->db->query(
            'UPDATE ' . $profileTable . ' SET phone_country_code = ? ' .
            'WHERE phone_country_code IS NULL AND phone IS NOT NULL AND phone <> ""',
            [$defaultCallingCode]
        );

        $countryMappings = [
            'dominican republic' => 'DO',
            'republica dominicana' => 'DO',
            'república dominicana' => 'DO',
            'dominique' => 'DO',
            'united states' => 'US',
            'estados unidos' => 'US',
            'etats-unis' => 'US',
            'spain' => 'ES',
            'españa' => 'ES',
            'espagne' => 'ES',
            'france' => 'FR',
            'francia' => 'FR',
            'germany' => 'DE',
            'alemania' => 'DE',
            'allemagne' => 'DE',
            'united kingdom' => 'GB',
            'reino unido' => 'GB',
            'royaume-uni' => 'GB',
            'italy' => 'IT',
            'italia' => 'IT',
            'brasil' => 'BR',
            'brazil' => 'BR',
            'colombia' => 'CO',
            'mexico' => 'MX',
            'méxico' => 'MX',
            'peru' => 'PE',
            'pérou' => 'PE',
            'perú' => 'PE',
            'argentina' => 'AR',
            'chile' => 'CL',
            'canada' => 'CA',
            'canadá' => 'CA',
            'panama' => 'PA',
            'panamá' => 'PA',
            'puerto rico' => 'PR',
        ];

        foreach ($countryMappings as $text => $iso) {
            $this->db->query(
                'UPDATE ' . $profileTable . ' SET country = ? ' .
                'WHERE LOWER(TRIM(country)) = ? AND (country <> ? OR country IS NULL)',
                [$iso, $text, $iso]
            );
        }
    }

    private function resolveExistingTable(array $baseCandidates): ?string
    {
        foreach ($baseCandidates as $candidate) {
            $prefixed = $this->db->getPrefix() . $candidate;
            foreach ([$prefixed, $candidate] as $table) {
                try {
                    if ($this->db->tableExists($table)) {
                        return $table;
                    }
                } catch (\Throwable $e) {
                    // Try next.
                }
            }
        }

        return null;
    }
}
