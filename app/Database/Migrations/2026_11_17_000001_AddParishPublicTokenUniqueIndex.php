<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishPublicTokenUniqueIndex extends Migration
{
    private const INDEX_NAME = 'uniq_parish_public_token';

    public function up()
    {
        if (! $this->db->tableExists('parish') || ! $this->db->fieldExists('public_token', 'parish')) {
            return;
        }

        // Uninitialized tokens are NULL (not ''), so the unique key permits any
        // number of parishes awaiting lazy generation via ensurePublicToken().
        // Insert paths that omit public_token (Parishes::store,
        // SyncUeidDirectory) therefore no longer collide.
        $this->forge->modifyColumn('parish', [
            'public_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
            ],
        ]);
        $this->db->table('parish')->where('public_token', '')->update(['public_token' => null]);

        // Rotate any accidental duplicates so the unique key can be created.
        $rows = $this->db->table('parish')
            ->select('id, public_token')
            ->where('public_token IS NOT NULL')
            ->get()
            ->getResultArray();

        $seen = [];
        foreach ($rows as $row) {
            $token = (string) $row['public_token'];
            if (isset($seen[$token])) {
                $token = bin2hex(random_bytes(16));
                $this->db->table('parish')
                    ->where('id', (int) $row['id'])
                    ->update(['public_token' => $token]);
            }
            $seen[$token] = true;
        }

        if (isset($this->db->getIndexData('parish')[self::INDEX_NAME])) {
            return;
        }

        $this->forge->addKey('public_token', false, true, self::INDEX_NAME);
        $this->forge->processIndexes('parish');
    }

    public function down()
    {
        if (! $this->db->tableExists('parish')) {
            return;
        }

        $this->forge->dropKey('parish', self::INDEX_NAME);
    }
}
