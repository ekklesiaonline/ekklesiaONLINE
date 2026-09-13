<?php

use App\Models\UserSessionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class UserSessionModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildSchema();
    }

    public function testRegisterSessionEnforcesConcurrentLimitByDroppingOldest(): void
    {
        $model = model(UserSessionModel::class);
        $userId = 7;

        // Seed three existing sessions in ascending activity order.
        $this->insertSession($userId, 'oldest', '-30 minutes');
        $this->insertSession($userId, 'middle', '-20 minutes');
        $this->insertSession($userId, 'newest', '-10 minutes');

        $isNew = $model->registerSession(
            $userId,
            'fresh-token',
            '127.0.0.1',
            'Mozilla/5.0 Test Browser',
            hash('sha256', 'Mozilla/5.0 Test Browser')
        );

        $this->assertTrue($isNew);

        $rows = $this->db->table('user_session')
            ->where('user_id', $userId)
            ->orderBy('last_active', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(3, $rows);

        $tokens = array_map(static fn(array $row): string => (string) $row['session_token'], $rows);
        $this->assertNotContains('oldest', $tokens);
        $this->assertContains('fresh-token', $tokens);
    }

    public function testPruneStaleForUserRemovesExpiredRows(): void
    {
        $model = model(UserSessionModel::class);
        $userId = 9;

        $this->insertSession($userId, 'stale-1', '-5 hours');
        $this->insertSession($userId, 'active-1', '-5 minutes');

        $model->pruneStaleForUser($userId, 3600);

        $rows = $this->db->table('user_session')->where('user_id', $userId)->get()->getResultArray();
        $tokens = array_map(static fn(array $row): string => (string) $row['session_token'], $rows);

        $this->assertSame(['active-1'], $tokens);
    }

    public function testMarkVerifiedByTokenClearsNewDeviceFlag(): void
    {
        $model = model(UserSessionModel::class);

        $this->db->table('user_session')->insert([
            'user_id' => 11,
            'session_token' => 'verify-me',
            'ip_address' => '10.0.0.2',
            'user_agent' => 'UA',
            'device_fingerprint' => hash('sha256', 'UA'),
            'is_new_device' => 1,
            'last_active' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $model->markVerifiedByToken('verify-me');

        $row = $this->db->table('user_session')
            ->where('session_token', 'verify-me')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $this->assertSame(0, (int) ($row['is_new_device'] ?? 1));
    }

    private function rebuildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('user_session', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'],
            'session_token' => ['type' => 'VARCHAR', 'constraint' => 64],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'device_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 64],
            'is_new_device' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'last_active' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('session_token');
        $forge->createTable('user_session', true);
    }

    private function insertSession(int $userId, string $token, string $relativeTime): void
    {
        $at = date('Y-m-d H:i:s', strtotime($relativeTime));

        $this->db->table('user_session')->insert([
            'user_id' => $userId,
            'session_token' => $token,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'seeded-agent',
            'device_fingerprint' => hash('sha256', 'seeded-agent-' . $token),
            'is_new_device' => 0,
            'last_active' => $at,
            'created_at' => $at,
        ]);
    }
}
