<?php

use App\Controllers\BaseController;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

class FinanceAuditPayloadDonationsTestController extends \Treasurer\Controllers\Donations
{
    private int $forcedParishId = 0;
    private array $auditEvents = [];

    public function setForcedParishId(int $parishId): void
    {
        $this->forcedParishId = $parishId;
    }

    protected function requirePermission(string $permission)
    {
        return null;
    }

    protected function requireParish()
    {
        return null;
    }

    protected function getHomeparishId(): ?int
    {
        return $this->forcedParishId;
    }

    protected function getOperationalParishId(): ?int
    {
        return $this->forcedParishId;
    }

    protected function getProfile(): ?array
    {
        return [
            'parish_id' => $this->forcedParishId,
            'locale' => 'en',
        ];
    }

    protected function audit(): \App\Services\AuditService
    {
        return new class($this) extends \App\Services\AuditService {
            public function __construct(private FinanceAuditPayloadDonationsTestController $owner) {}

            public function log(string $eventType, array $payload = []): void
            {
                $this->owner->recordAuditEvent($eventType, $payload);
            }
        };
    }

    public function recordAuditEvent(string $eventType, array $payload): void
    {
        $this->auditEvents[] = ['event' => $eventType, 'payload' => $payload];
    }

    public function getAuditEvents(): array
    {
        return $this->auditEvents;
    }
}

class FinanceAuditPayloadExpensesTestController extends \Treasurer\Controllers\ExpenseExports
{
    private int $forcedParishId = 0;
    private array $auditEvents = [];

    public function setForcedParishId(int $parishId): void
    {
        $this->forcedParishId = $parishId;
    }

    protected function requirePermission(string $permission)
    {
        return null;
    }

    protected function requireParish()
    {
        return null;
    }

    protected function getHomeparishId(): ?int
    {
        return $this->forcedParishId;
    }

    protected function getOperationalParishId(): ?int
    {
        return $this->forcedParishId;
    }

    protected function getProfile(): ?array
    {
        return [
            'parish_id' => $this->forcedParishId,
            'locale' => 'en',
        ];
    }

    protected function audit(): \App\Services\AuditService
    {
        return new class($this) extends \App\Services\AuditService {
            public function __construct(private FinanceAuditPayloadExpensesTestController $owner) {}

            public function log(string $eventType, array $payload = []): void
            {
                $this->owner->recordAuditEvent($eventType, $payload);
            }
        };
    }

    public function recordAuditEvent(string $eventType, array $payload): void
    {
        $this->auditEvents[] = ['event' => $eventType, 'payload' => $payload];
    }

    public function getAuditEvents(): array
    {
        return $this->auditEvents;
    }
}

/**
 * @internal
 */
final class FinanceAuditPayloadIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->ensureCoreTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testDonationExportEmitsAuditPayloadWithParishContextMeta(): void
    {
        $parishId = $this->insertParish('Audit Donation Parish');
        $this->injectAuthUser(7);

        $request = service('request');
        $request->setGlobal('get', []);

        $controller = new FinanceAuditPayloadDonationsTestController();
        $controller->setForcedParishId($parishId);

        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->export();

        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);

        $events = $controller->getAuditEvents();
        $this->assertCount(1, $events);
        $this->assertSame('donation.export_csv', $events[0]['event']);

        $payload = $events[0]['payload'];
        $this->assertSame($parishId, (int) ($payload['parish_id'] ?? 0));
        $this->assertSame('donation_report', (string) ($payload['entity_type'] ?? ''));

        $context = $payload['meta']['parish_context'] ?? null;
        $this->assertIsArray($context);
        $this->assertSame($parishId, (int) ($context['home_parish_id'] ?? 0));
        $this->assertSame($parishId, (int) ($context['operational_parish_id'] ?? 0));
        $this->assertSame($parishId, (int) ($context['attribution_parish_id'] ?? 0));
        $this->assertSame(BaseController::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT, (string) ($context['attribution_policy'] ?? ''));
    }

    public function testExpenseExportEmitsAuditPayloadWithParishContextMeta(): void
    {
        $parishId = $this->insertParish('Audit Expense Parish');
        $this->injectAuthUser(9);

        $request = service('request');
        $request->setGlobal('get', []);

        $controller = new FinanceAuditPayloadExpensesTestController();
        $controller->setForcedParishId($parishId);

        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->export();

        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);

        $events = $controller->getAuditEvents();
        $this->assertCount(1, $events);
        $this->assertSame('expense.export_csv', $events[0]['event']);

        $payload = $events[0]['payload'];
        $this->assertSame($parishId, (int) ($payload['parish_id'] ?? 0));
        $this->assertSame('expense_report', (string) ($payload['entity_type'] ?? ''));

        $context = $payload['meta']['parish_context'] ?? null;
        $this->assertIsArray($context);
        $this->assertSame($parishId, (int) ($context['home_parish_id'] ?? 0));
        $this->assertSame($parishId, (int) ($context['operational_parish_id'] ?? 0));
        $this->assertSame($parishId, (int) ($context['attribution_parish_id'] ?? 0));
        $this->assertSame(BaseController::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT, (string) ($context['attribution_policy'] ?? ''));
    }

    private function injectAuthUser(int $userId): void
    {
        $this->insertUser($userId);

        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'audit.integration.user.' . $idForTest,
                    'email' => 'audit.integration.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('finance', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userId)
            {
                parent::__construct($config);
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): ?int
            {
                return $this->userId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertParish(string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFund(int $parishId, string $name): int
    {
        $this->db->table($this->resolveTableName('fund'))->insert([
            'parish_id' => $parishId,
            'name' => $name,
            'fund_type' => 'unrestricted',
            'is_active' => 1,
            'is_locked' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertChartAccount(string $code, string $name, string $type, string $category): int
    {
        $this->db->table($this->resolveTableName('chart_of_accounts'))->insert([
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'category' => $category,
            'is_active' => 1,
            'allow_budget' => 1,
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(int $userId): void
    {
        $table = $this->resolveTableName('users');
        $existing = $this->db->table($table)->where('id', $userId)->get()->getRowArray();

        $payload = [
            'username' => 'audit.integration.user.' . $userId,
            'active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $payload['id'] = $userId;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db->table($table)->insert($payload);
            return;
        }

        $this->db->table($table)->where('id', $userId)->update($payload);
    }

    private function resolveTableName(string $table): string
    {
        if ($this->db->tableExists($table)) {
            return $table;
        }

        return $this->db->getPrefix() . $table;
    }

    private function ensureCoreTablesExist(): void
    {
        $this->ensureSqliteTable('users', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            email TEXT NULL,
            active INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->ensureSqliteTable('profile', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parish_id INTEGER NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            locale TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->ensureSqliteTable('parish', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->ensureSqliteTable('chart_of_accounts', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            account_type TEXT NOT NULL,
            category TEXT NULL,
            description TEXT NULL,
            allow_budget INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSqliteTable('fund', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT NULL,
            fund_type TEXT NOT NULL,
            restriction_purpose TEXT NULL,
            chart_account_id INTEGER NULL,
            is_active INTEGER DEFAULT 1,
            is_locked INTEGER DEFAULT 0,
            created_by INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSqliteTable('donation', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NOT NULL,
            fund_id INTEGER NULL,
            user_id INTEGER NULL,
            amount TEXT NOT NULL,
            date TEXT NOT NULL,
            category TEXT NOT NULL,
            chart_account_id INTEGER NULL,
            payment_method TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->ensureSqliteTable('expense', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NOT NULL,
            fund_id INTEGER NULL,
            description TEXT NOT NULL,
            amount REAL NOT NULL,
            category TEXT NOT NULL,
            chart_account_id INTEGER NULL,
            date TEXT NOT NULL,
            approved_by INTEGER NULL,
            notes TEXT NULL,
            receipt_path TEXT NULL,
            receipt_original_name TEXT NULL,
            receipt_mime_type TEXT NULL,
            receipt_file_size INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSqliteTable('donation_receipts', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NOT NULL,
            donation_id INTEGER NOT NULL,
            receipt_number TEXT NOT NULL,
            issued_at TEXT NOT NULL,
            issued_by INTEGER NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureSqliteTable(string $baseTable, string $definition): void
    {
        $prefixedTable = $this->db->getPrefix() . $baseTable;
        $tables = [$baseTable, 'db_' . $baseTable];
        if ($prefixedTable !== $baseTable) {
            $tables[] = $prefixedTable;
        }

        foreach ($tables as $table) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' ' . $definition);
        }
    }
}
