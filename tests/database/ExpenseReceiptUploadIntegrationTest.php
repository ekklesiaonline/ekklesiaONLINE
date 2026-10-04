<?php

namespace Tests\Database;

use App\Controllers\BaseController;
use App\Models\ExpenseModel;
use App\Services\ExpenseReceiptUploadService;
use App\Services\UploadPathService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

class TestExpenseUploadFile extends UploadedFile
{
    private bool $testIsValid = true;
    private ?string $forcedMimeType = null;

    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = 'application/pdf',
        ?int $size = 1024,
        ?int $error = \UPLOAD_ERR_OK,
        ?string $clientPath = null
    ) {
        $this->forcedMimeType = $mimeType;
        parent::__construct($path, $originalName, $mimeType, $size, $error ?? \UPLOAD_ERR_OK, $clientPath);
    }

    public function setValid(bool $valid): void
    {
        $this->testIsValid = $valid;
    }

    public function isValid(): bool
    {
        return $this->testIsValid && $this->error === \UPLOAD_ERR_OK && is_file($this->path);
    }

    public function getMimeType(): string
    {
        return $this->forcedMimeType ?? parent::getMimeType();
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false): bool
    {
        $targetPath = rtrim($targetPath, '/') . '/';
        $targetPath = $this->setPath($targetPath);

        if ($this->hasMoved) {
            throw \CodeIgniter\HTTP\Exceptions\HTTPException::forAlreadyMoved();
        }

        if ($name === null) {
            $name = basename($this->getName());
        }
        $destination = $overwrite ? $targetPath . $name : $this->getDestination($targetPath . $name);

        if (!copy($this->path, $destination)) {
            throw new \RuntimeException('Failed to copy test file');
        }
        @unlink($this->path);

        $this->hasMoved = true;
        $this->path = $targetPath;
        $this->name = basename($destination);

        return true;
    }
}

class TestableExpensesController extends \Treasurer\Controllers\Expenses
{
    private int $forcedParishId = 0;
    private array $auditEvents = [];
    private ?UploadedFile $mockedFile = null;

    public function setForcedParishId(int $parishId): void
    {
        $this->forcedParishId = $parishId;
    }

    public function setMockedFile(?UploadedFile $file): void
    {
        $this->mockedFile = $file;
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
            public function __construct(private TestableExpensesController $owner) {}

            public function log(string $eventType, array $payload = []): void
            {
                $this->owner->recordAuditEvent($eventType, $payload);
            }
        };
    }

    public function validate($rules, array $messages = []): bool
    {
        return true;
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
final class ExpenseReceiptUploadIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private array $createdTempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['auth', 'form']);
        Services::reset(true);
        $this->ensureTablesExist();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->createdTempFiles = [];

        Services::reset(true);
        parent::tearDown();
    }

    public function testExpenseReceiptUploadServiceValidatesMimeType(): void
    {
        $tmpPath = $this->createTempFile('dummy plain text content');
        $file = new TestExpenseUploadFile($tmpPath, 'test.txt', 'text/plain', 50);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(lang('Treasurer.errorInvalidReceiptType'));

        ExpenseReceiptUploadService::handleUpload(1, null, $file, false);
    }

    public function testExpenseReceiptUploadServiceValidatesFileSize(): void
    {
        $tmpPath = $this->createTempFile('dummy');
        $oversizedBytes = (10 * 1024 * 1024) + 1; // 10MB + 1 byte
        $file = new TestExpenseUploadFile($tmpPath, 'large.pdf', 'application/pdf', $oversizedBytes);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(lang('Treasurer.errorInvalidReceiptSize'));

        ExpenseReceiptUploadService::handleUpload(1, null, $file, false);
    }

    public function testExpenseReceiptUploadServicePreventsPathTraversal(): void
    {
        $parishId = 42;

        // Path with traversal attempt
        $badPath = 'attachment/expense/42/../../secret.txt';
        $resolved = ExpenseReceiptUploadService::resolveAbsolutePath($badPath, $parishId);
        $this->assertNull($resolved);

        // Path belonging to different parish
        $crossParishPath = 'attachment/expense/99/document/2026/10/file.pdf';
        $resolvedCross = ExpenseReceiptUploadService::resolveAbsolutePath($crossParishPath, $parishId);
        $this->assertNull($resolvedCross);
    }

    public function testExpenseReceiptUploadServiceMovesFileAndReturnsMetadata(): void
    {
        $parishId = 15;
        $pdfContent = '%PDF-1.4 sample invoice receipt test file';
        $tmpPath = $this->createTempFile($pdfContent);
        $file = new TestExpenseUploadFile($tmpPath, 'invoice_1001.pdf', 'application/pdf', strlen($pdfContent));

        $result = ExpenseReceiptUploadService::handleUpload($parishId, null, $file, false);

        $this->assertIsArray($result);
        $this->assertNotNull($result['receipt_path']);
        $this->assertSame('invoice_1001.pdf', $result['receipt_original_name']);
        $this->assertSame('application/pdf', $result['receipt_mime_type']);
        $this->assertSame(strlen($pdfContent), $result['receipt_file_size']);

        $resolved = ExpenseReceiptUploadService::resolveAbsolutePath($result['receipt_path'], $parishId);
        $this->assertNotNull($resolved);
        $this->assertFileExists($resolved);
        $this->createdTempFiles[] = $resolved;

        $this->assertSame($pdfContent, file_get_contents($resolved));
    }

    public function testExpenseReceiptUploadServiceRemovesExistingFileOnRemoval(): void
    {
        $parishId = 20;
        $content = 'receipt content to be removed';
        $tmpPath = $this->createTempFile($content);
        $file = new TestExpenseUploadFile($tmpPath, 'old_receipt.pdf', 'application/pdf', strlen($content));

        $uploadResult = ExpenseReceiptUploadService::handleUpload($parishId, null, $file, false);
        $storedPath = $uploadResult['receipt_path'];
        $absPath = ExpenseReceiptUploadService::resolveAbsolutePath($storedPath, $parishId);
        $this->assertFileExists($absPath);

        // Now remove it
        $removeResult = ExpenseReceiptUploadService::handleUpload($parishId, $storedPath, null, true);

        $this->assertIsArray($removeResult);
        $this->assertNull($removeResult['receipt_path']);
        $this->assertNull($removeResult['receipt_original_name']);
        $this->assertNull($removeResult['receipt_mime_type']);
        $this->assertNull($removeResult['receipt_file_size']);
        $this->assertFileDoesNotExist($absPath);
    }

    public function testExpensesControllerReceiptActionServesFileWithInlineAndDownloadHeaders(): void
    {
        $parishId = $this->insertParish('Receipt Test Parish');
        $this->injectAuthUser(10);

        // Pre-create receipt on disk
        $receiptContent = '%PDF-1.4 receipt test content for view';
        $tmpPath = $this->createTempFile($receiptContent);
        $file = new TestExpenseUploadFile($tmpPath, 'vendor_bill.pdf', 'application/pdf', strlen($receiptContent));
        $uploaded = ExpenseReceiptUploadService::handleUpload($parishId, null, $file, false);
        $absPath = ExpenseReceiptUploadService::resolveAbsolutePath($uploaded['receipt_path'], $parishId);
        $this->createdTempFiles[] = $absPath;

        $fundId = $this->insertFund($parishId, 'Operating Fund');
        $coaId = $this->insertChartAccount('5000', 'Office Supplies', 'expense', 'supplies');

        $expenseModel = new ExpenseModel();
        $expenseId = $expenseModel->insert([
            'parish_id'             => $parishId,
            'fund_id'               => $fundId,
            'date'                  => '2026-10-04',
            'amount'                => 150.00,
            'category'              => 'supplies',
            'chart_account_id'      => $coaId,
            'description'           => 'Paper and toner',
            'receipt_path'          => $uploaded['receipt_path'],
            'receipt_original_name' => 'vendor_bill.pdf',
            'receipt_mime_type'     => 'application/pdf',
            'receipt_file_size'     => strlen($receiptContent),
        ]);

        $controller = new TestableExpensesController();
        $controller->setForcedParishId($parishId);

        // Test Inline View
        $request = service('request');
        $request->setGlobal('get', []);
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->receipt($expenseId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('inline; filename="vendor_bill.pdf"', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame($receiptContent, $response->getBody());

        // Test Download disposition
        $request = service('request');
        $request->setGlobal('get', ['download' => '1']);
        $controller->initController($request, service('response'), service('logger'));

        $responseDownload = $controller->receipt($expenseId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $responseDownload);
        $this->assertStringContainsString('attachment; filename="vendor_bill.pdf"', $responseDownload->getHeaderLine('Content-Disposition'));

        // Verify audit log
        $events = $controller->getAuditEvents();
        $this->assertNotEmpty($events);
        $this->assertSame('expense.receipt_view', $events[0]['event']);
        $this->assertSame($parishId, $events[0]['payload']['parish_id']);
        $this->assertSame('vendor_bill.pdf', $events[0]['payload']['meta']['filename']);
    }

    public function testExpensesControllerReceiptBlocksCrossParishAccess(): void
    {
        $parishA = $this->insertParish('Parish A');
        $parishB = $this->insertParish('Parish B');
        $this->injectAuthUser(11);

        $fundId = $this->insertFund($parishA, 'Parish A Fund');
        $coaId = $this->insertChartAccount('5000', 'Office Supplies', 'expense', 'supplies');

        $expenseModel = new ExpenseModel();
        $expenseId = $expenseModel->insert([
            'parish_id'             => $parishA,
            'fund_id'               => $fundId,
            'date'                  => '2026-10-04',
            'amount'                => 75.00,
            'category'              => 'supplies',
            'chart_account_id'      => $coaId,
            'description'           => 'Supplies A',
            'receipt_path'          => 'attachment/expense/' . $parishA . '/document/2026/10/fake.pdf',
            'receipt_original_name' => 'fake.pdf',
            'receipt_mime_type'     => 'application/pdf',
            'receipt_file_size'     => 100,
        ]);

        $controller = new TestableExpensesController();
        // User belongs to Parish B, attempting to access Parish A expense
        $controller->setForcedParishId($parishB);

        $request = service('request');
        $request->setGlobal('get', []);
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->receipt($expenseId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
    }

    public function testExpensesControllerDeleteRemovesReceiptFileFromDisk(): void
    {
        $parishId = $this->insertParish('Delete Expense Parish');
        $this->injectAuthUser(12);

        $receiptContent = 'Receipt to be deleted with expense';
        $tmpPath = $this->createTempFile($receiptContent);
        $file = new TestExpenseUploadFile($tmpPath, 'delete_me.pdf', 'application/pdf', strlen($receiptContent));
        $uploaded = ExpenseReceiptUploadService::handleUpload($parishId, null, $file, false);
        $absPath = ExpenseReceiptUploadService::resolveAbsolutePath($uploaded['receipt_path'], $parishId);
        $this->assertFileExists($absPath);

        $fundId = $this->insertFund($parishId, 'Fund');
        $coaId = $this->insertChartAccount('5010', 'Utilities', 'expense', 'utilities');

        $expenseModel = new ExpenseModel();
        $expenseId = $expenseModel->insert([
            'parish_id'             => $parishId,
            'fund_id'               => $fundId,
            'date'                  => '2026-10-04',
            'amount'                => 89.00,
            'category'              => 'utilities',
            'chart_account_id'      => $coaId,
            'description'           => 'Electric bill',
            'receipt_path'          => $uploaded['receipt_path'],
            'receipt_original_name' => 'delete_me.pdf',
            'receipt_mime_type'     => 'application/pdf',
            'receipt_file_size'     => strlen($receiptContent),
        ]);

        $controller = new TestableExpensesController();
        $controller->setForcedParishId($parishId);

        $request = service('request');
        $controller->initController($request, service('response'), service('logger'));

        $controller->delete($expenseId);

        // Expense record deleted
        $this->assertNull($expenseModel->find($expenseId));
        // Attached file unlinked from disk
        $this->assertFileDoesNotExist($absPath);
    }

    public function testExpensesControllerStoreWithReceiptUpload(): void
    {
        $parishId = $this->insertParish('Store Expense Parish');
        $this->injectAuthUser(13);

        $fundId = $this->insertFund($parishId, 'Operating Fund');
        $coaId = $this->insertChartAccount('5020', 'Maintenance', 'expense', 'maintenance');

        $receiptContent = '%PDF-1.4 test store upload';
        $tmpPath = $this->createTempFile($receiptContent);
        $file = new TestExpenseUploadFile($tmpPath, 'hardware_store.pdf', 'application/pdf', strlen($receiptContent));

        $controller = new TestableExpensesController();
        $controller->setForcedParishId($parishId);

        $request = new class(
            config('App'),
            new \CodeIgniter\HTTP\URI('http://localhost/expenses/create'),
            null,
            new \CodeIgniter\HTTP\UserAgent()
        ) extends \CodeIgniter\HTTP\IncomingRequest {
            public ?UploadedFile $mockFile = null;

            public function getFile(string $fileID): ?UploadedFile
            {
                return $this->mockFile;
            }
        };
        $request->mockFile = $file;
        $request->setMethod('post');
        $request->setGlobal('post', [
            'fund_id'          => $fundId,
            'chart_account_id' => $coaId,
            'date'             => '2026-10-04',
            'amount'           => '250.75',
            'category'         => 'maintenance',
            'description'      => 'Door repairs',
            'notes'            => 'Emergency repair',
        ]);

        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $expenseModel = new ExpenseModel();
        $inserted = $expenseModel->where('parish_id', $parishId)->first();
        $this->assertNotNull($inserted);
        $this->assertNotEmpty($inserted['receipt_path']);
        $this->assertSame('hardware_store.pdf', $inserted['receipt_original_name']);
        $this->assertSame('application/pdf', $inserted['receipt_mime_type']);

        $absPath = ExpenseReceiptUploadService::resolveAbsolutePath($inserted['receipt_path'], $parishId);
        $this->assertNotNull($absPath);
        $this->assertFileExists($absPath);
        $this->createdTempFiles[] = $absPath;

        $events = $controller->getAuditEvents();
        $this->assertNotEmpty($events);
        $this->assertSame('expense.create', $events[0]['event']);
        $this->assertTrue($events[0]['payload']['new_values']['has_receipt']);
    }

    public function testExpensesControllerUpdateWithReceiptRemoval(): void
    {
        $parishId = $this->insertParish('Update Remove Parish');
        $this->injectAuthUser(14);

        $receiptContent = '%PDF-1.4 to be removed during update';
        $tmpPath = $this->createTempFile($receiptContent);
        $file = new TestExpenseUploadFile($tmpPath, 'initial_receipt.pdf', 'application/pdf', strlen($receiptContent));
        $uploaded = ExpenseReceiptUploadService::handleUpload($parishId, null, $file, false);
        $absPath = ExpenseReceiptUploadService::resolveAbsolutePath($uploaded['receipt_path'], $parishId);
        $this->assertFileExists($absPath);

        $fundId = $this->insertFund($parishId, 'General Fund');
        $coaId = $this->insertChartAccount('5030', 'Postage', 'expense', 'supplies');

        $expenseModel = new ExpenseModel();
        $expenseId = $expenseModel->insert([
            'parish_id'             => $parishId,
            'fund_id'               => $fundId,
            'date'                  => '2026-10-04',
            'amount'                => 45.00,
            'category'              => 'supplies',
            'chart_account_id'      => $coaId,
            'description'           => 'Stamps',
            'receipt_path'          => $uploaded['receipt_path'],
            'receipt_original_name' => 'initial_receipt.pdf',
            'receipt_mime_type'     => 'application/pdf',
            'receipt_file_size'     => strlen($receiptContent),
        ]);

        $controller = new TestableExpensesController();
        $controller->setForcedParishId($parishId);

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', [
            'fund_id'          => $fundId,
            'chart_account_id' => $coaId,
            'date'             => '2026-10-04',
            'amount'           => '45.00',
            'category'         => 'supplies',
            'description'      => 'Stamps updated',
            'remove_receipt'   => '1',
        ]);

        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->update($expenseId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Previous file deleted from disk
        $this->assertFileDoesNotExist($absPath);

        // Expense record updated with null receipt fields
        $updated = $expenseModel->find($expenseId);
        $this->assertNull($updated['receipt_path']);
        $this->assertNull($updated['receipt_original_name']);

        // Audit log reflects receipt removed
        $events = $controller->getAuditEvents();
        $this->assertNotEmpty($events);
        $this->assertSame('expense.update', $events[0]['event']);
        $this->assertTrue($events[0]['payload']['old_values']['has_receipt']);
        $this->assertFalse($events[0]['payload']['new_values']['has_receipt']);
    }

    public function testExpensesControllerUpdateWithReceiptReplacement(): void
    {
        $parishId = $this->insertParish('Update Replace Parish');
        $this->injectAuthUser(15);

        // First receipt
        $initialContent = '%PDF-1.4 first receipt';
        $tmpPath1 = $this->createTempFile($initialContent);
        $file1 = new TestExpenseUploadFile($tmpPath1, 'first.pdf', 'application/pdf', strlen($initialContent));
        $uploaded1 = ExpenseReceiptUploadService::handleUpload($parishId, null, $file1, false);
        $absPath1 = ExpenseReceiptUploadService::resolveAbsolutePath($uploaded1['receipt_path'], $parishId);
        $this->assertFileExists($absPath1);

        $fundId = $this->insertFund($parishId, 'General Fund');
        $coaId = $this->insertChartAccount('5040', 'Software', 'expense', 'ministry');

        $expenseModel = new ExpenseModel();
        $expenseId = $expenseModel->insert([
            'parish_id'             => $parishId,
            'fund_id'               => $fundId,
            'date'                  => '2026-10-04',
            'amount'                => 120.00,
            'category'              => 'ministry',
            'chart_account_id'      => $coaId,
            'description'           => 'Software Subscription',
            'receipt_path'          => $uploaded1['receipt_path'],
            'receipt_original_name' => 'first.pdf',
            'receipt_mime_type'     => 'application/pdf',
            'receipt_file_size'     => strlen($initialContent),
        ]);

        // Second replacement receipt
        $replacementContent = '%PDF-1.4 second replacement receipt';
        $tmpPath2 = $this->createTempFile($replacementContent);
        $file2 = new TestExpenseUploadFile($tmpPath2, 'replacement.pdf', 'application/pdf', strlen($replacementContent));

        $controller = new TestableExpensesController();
        $controller->setForcedParishId($parishId);

        $request = new class(
            config('App'),
            new \CodeIgniter\HTTP\URI('http://localhost/expenses/update/' . $expenseId),
            null,
            new \CodeIgniter\HTTP\UserAgent()
        ) extends \CodeIgniter\HTTP\IncomingRequest {
            public ?UploadedFile $mockFile = null;

            public function getFile(string $fileID): ?UploadedFile
            {
                return $this->mockFile;
            }
        };
        $request->mockFile = $file2;
        $request->setMethod('post');
        $request->setGlobal('post', [
            'fund_id'          => $fundId,
            'chart_account_id' => $coaId,
            'date'             => '2026-10-04',
            'amount'           => '120.00',
            'category'         => 'ministry',
            'description'      => 'Software Subscription updated',
        ]);

        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->update($expenseId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Old file deleted
        $this->assertFileDoesNotExist($absPath1);

        // New file saved
        $updated = $expenseModel->find($expenseId);
        $this->assertSame('replacement.pdf', $updated['receipt_original_name']);

        $absPath2 = ExpenseReceiptUploadService::resolveAbsolutePath($updated['receipt_path'], $parishId);
        $this->assertNotNull($absPath2);
        $this->assertFileExists($absPath2);
        $this->createdTempFiles[] = $absPath2;
    }

    private function createTempFile(string $content): string
    {
        $tmpDir = WRITEPATH . 'uploads/tmp_tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $tmpFile = $tmpDir . '/test_' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($tmpFile, $content);
        $this->createdTempFiles[] = $tmpFile;

        return $tmpFile;
    }

    private function injectAuthUser(int $userId): void
    {
        $this->db->table('users')->insert([
            'id' => $userId,
            'username' => 'testuser' . $userId,
            'email' => 'user' . $userId . '@example.com',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'testuser' . $idForTest,
                    'email' => 'user' . $idForTest . '@example.com',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return true;
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends \CodeIgniter\Shield\Auth {
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
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFund(int $parishId, string $name): int
    {
        $this->db->table('fund')->insert([
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
        $this->db->table('chart_of_accounts')->insert([
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'category' => $category,
            'allow_budget' => 1,
            'is_active' => 1,
            'sort_order' => 10,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureTablesExist(): void
    {
        $prefix = $this->db->getPrefix();

        $definitions = [
            'users' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'profile' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                locale TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'parish' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'chart_of_accounts' => '(
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
            )',
            'fund' => '(
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
            )',
            'expense' => '(
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
                is_capital INTEGER NULL,
                receipt_path TEXT NULL,
                receipt_original_name TEXT NULL,
                receipt_mime_type TEXT NULL,
                receipt_file_size INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
        ];

        foreach ($definitions as $table => $definition) {
            $this->db->query('DROP TABLE IF EXISTS "' . $prefix . $table . '"');
            $this->db->query('CREATE TABLE "' . $prefix . $table . '" ' . $definition);
        }
    }
}
