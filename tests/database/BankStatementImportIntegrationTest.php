<?php

namespace Tests\Database;

use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Services\BankReconciliationService;
use App\Services\BankStatementParserService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Treasurer\Controllers\Banking;

class TestBankUploadFile extends UploadedFile
{
    private bool $testIsValid = true;

    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = null,
        ?int $size = null,
        ?int $error = null,
        ?string $clientPath = null
    ) {
        parent::__construct($path, $originalName, $mimeType, $size ?? 1024, $error ?? \UPLOAD_ERR_OK, $clientPath);
    }

    public function setValid(bool $valid): void
    {
        $this->testIsValid = $valid;
    }

    public function isValid(): bool
    {
        return $this->testIsValid && $this->error === \UPLOAD_ERR_OK && is_file($this->path);
    }
}

/**
 * Priority 3.10: Bank Statement File Import (OFX/CSV) Integration Tests.
 *
 * @internal
 */
final class BankStatementImportIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $userId;
    private int $parishId = 301;
    private int $otherParishId = 302;
    private int $accountId;
    private int $otherAccountId;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'finance']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach ([
            'bank_reconciliation_item',
            'bank_reconciliation',
            'bank_transaction',
            'bank_account',
            'parish',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish($this->parishId, 'Grace Church');
        $this->seedParish($this->otherParishId, 'Other Parish');

        $this->userId = $this->insertUser('treasurer.jane', 'treasurer@example.com');
        $this->insertProfile($this->userId, $this->parishId, 'Jane', 'Treasurer');
        $this->injectFinanceAuth($this->userId, ['finance.view', 'finance.manage']);

        session()->set('selected_parish_id', $this->parishId);

        $this->accountId = $this->insertAccount($this->parishId, 'Operating Checking', 5000.00);
        $this->otherAccountId = $this->insertAccount($this->otherParishId, 'Other Church Account', 1000.00);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tempFiles = [];

        Services::reset(true);
        parent::tearDown();
    }

    public function testParserExtractsOfxTransactionsCorrectly(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII

<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <CURDEF>USD
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260315120000
            <TRNAMT>-125.75
            <FITID>OFX20260315001
            <NAME>Church Supplies Direct
            <MEMO>Candles and bulletins
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>CREDIT
            <DTPOSTED>20260320090000
            <TRNAMT>2500.00
            <FITID>OFX20260320002
            <NAME>Sunday Tithes & Offerings
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $filePath = $this->createTempFile($ofxContent, 'statement.ofx');
        $parser = new BankStatementParserService();
        $transactions = $parser->parse($filePath, 'statement.ofx');

        $this->assertCount(2, $transactions);

        $this->assertSame('2026-03-15', $transactions[0]['transaction_date']);
        $this->assertEqualsWithDelta(-125.75, $transactions[0]['amount'], 0.01);
        $this->assertSame('Church Supplies Direct - Candles and bulletins', $transactions[0]['description']);
        $this->assertSame('OFX20260315001', $transactions[0]['reference']);

        $this->assertSame('2026-03-20', $transactions[1]['transaction_date']);
        $this->assertEqualsWithDelta(2500.00, $transactions[1]['amount'], 0.01);
        $this->assertSame('Sunday Tithes & Offerings', $transactions[1]['description']);
        $this->assertSame('OFX20260320002', $transactions[1]['reference']);
    }

    public function testParserExtractsCsvTransactionsSingleAmount(): void
    {
        $csvContent = "Date,Description,Amount,Reference\n" .
                      "2026-04-01,Utility Electric Bill,-340.50,ACH-9821\n" .
                      "2026-04-05,Pledge Direct Deposit,1200.00,DEP-1029\n" .
                      "2026-04-08,Office Paper supplies,($45.20),CHK-402\n";

        $filePath = $this->createTempFile($csvContent, 'statement.csv');
        $parser = new BankStatementParserService();
        $transactions = $parser->parse($filePath, 'statement.csv');

        $this->assertCount(3, $transactions);
        $this->assertSame('2026-04-01', $transactions[0]['transaction_date']);
        $this->assertEqualsWithDelta(-340.50, $transactions[0]['amount'], 0.01);
        $this->assertSame('Utility Electric Bill', $transactions[0]['description']);
        $this->assertSame('ACH-9821', $transactions[0]['reference']);

        $this->assertSame('2026-04-05', $transactions[1]['transaction_date']);
        $this->assertEqualsWithDelta(1200.00, $transactions[1]['amount'], 0.01);

        $this->assertSame('2026-04-08', $transactions[2]['transaction_date']);
        $this->assertEqualsWithDelta(-45.20, $transactions[2]['amount'], 0.01);
    }

    public function testParserExtractsCsvTransactionsDebitCreditColumns(): void
    {
        $csvContent = "Transaction Date,Details,Debit,Credit,Check #\n" .
                      "04/10/2026,Altar Flowers,85.00,,1045\n" .
                      "04/12/2026,Parishioner Donation,,500.00,\n";

        $filePath = $this->createTempFile($csvContent, 'debit_credit.csv');
        $parser = new BankStatementParserService();
        $transactions = $parser->parse($filePath, 'debit_credit.csv');

        $this->assertCount(2, $transactions);
        $this->assertSame('2026-04-10', $transactions[0]['transaction_date']);
        $this->assertEqualsWithDelta(-85.00, $transactions[0]['amount'], 0.01);
        $this->assertSame('Altar Flowers', $transactions[0]['description']);
        $this->assertSame('1045', $transactions[0]['reference']);

        $this->assertSame('2026-04-12', $transactions[1]['transaction_date']);
        $this->assertEqualsWithDelta(500.00, $transactions[1]['amount'], 0.01);
    }

    public function testControllerImportsOfxStatementAndCreatesTransactions(): void
    {
        $ofxContent = <<<OFX
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260501120000
            <TRNAMT>-210.00
            <FITID>OFX-TXN-501
            <NAME>City Water
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>CREDIT
            <DTPOSTED>20260503120000
            <TRNAMT>800.00
            <FITID>OFX-TXN-502
            <NAME>Sunday Offering
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $filePath = $this->createTempFile($ofxContent, 'bank_export.ofx');
        $mockFile = new TestBankUploadFile($filePath, 'bank_export.ofx', 'text/plain', strlen($ofxContent));

        $controller = $this->makeController('POST', [
            'bank_account_id' => $this->accountId,
        ], $mockFile);

        $response = $controller->importStatement($this->accountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(site_url('banking/' . $this->accountId), $response->getHeaderLine('Location'));

        $txnModel = new BankTransactionModel();
        $transactions = $txnModel->where('bank_account_id', $this->accountId)->orderBy('transaction_date', 'ASC')->findAll();

        $this->assertCount(2, $transactions);
        $this->assertSame('2026-05-01', $transactions[0]['transaction_date']);
        $this->assertEqualsWithDelta(-210.00, (float) $transactions[0]['amount'], 0.01);
        $this->assertSame('City Water', $transactions[0]['description']);
        $this->assertSame('OFX-TXN-501', $transactions[0]['reference']);
        $this->assertSame('unmatched', $transactions[0]['status']);

        $this->assertSame('2026-05-03', $transactions[1]['transaction_date']);
        $this->assertEqualsWithDelta(800.00, (float) $transactions[1]['amount'], 0.01);
        $this->assertSame('unmatched', $transactions[1]['status']);

        // Verify audit log
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'banking.statement.import')
            ->where('entity_type', 'bank_statement')
            ->where('entity_id', $this->accountId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $this->assertSame($this->parishId, (int) $auditRow['parish_id']);
    }

    public function testControllerSkipsDuplicateTransactionsOnReimport(): void
    {
        $ofxContent = <<<OFX
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260510120000
            <TRNAMT>-75.00
            <FITID>OFX-DUP-01
            <NAME>Internet Provider
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        // First import
        $filePath1 = $this->createTempFile($ofxContent, 'export1.ofx');
        $mockFile1 = new TestBankUploadFile($filePath1, 'export1.ofx', 'text/plain', strlen($ofxContent));

        $controller1 = $this->makeController('POST', ['bank_account_id' => $this->accountId], $mockFile1);
        $controller1->importStatement($this->accountId);

        $txnModel = new BankTransactionModel();
        $this->assertSame(1, $txnModel->where('bank_account_id', $this->accountId)->countAllResults());

        // Re-import overlapping statement containing the same transaction + 1 new transaction
        $ofxOverlap = <<<OFX
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260510120000
            <TRNAMT>-75.00
            <FITID>OFX-DUP-01
            <NAME>Internet Provider
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>CREDIT
            <DTPOSTED>20260512120000
            <TRNAMT>300.00
            <FITID>OFX-NEW-02
            <NAME>Bookstore Donation
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $filePath2 = $this->createTempFile($ofxOverlap, 'export2.ofx');
        $mockFile2 = new TestBankUploadFile($filePath2, 'export2.ofx', 'text/plain', strlen($ofxOverlap));

        $controller2 = $this->makeController('POST', ['bank_account_id' => $this->accountId], $mockFile2);
        $response = $controller2->importStatement($this->accountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Only 1 new transaction added, total is now 2 (duplicate was skipped)
        $this->assertSame(2, $txnModel->where('bank_account_id', $this->accountId)->countAllResults());
    }

    public function testCrossParishAccountImportBlocked(): void
    {
        $csvContent = "Date,Description,Amount\n2026-06-01,Hacked Deposit,1000.00\n";
        $filePath = $this->createTempFile($csvContent, 'cross_parish.csv');
        $mockFile = new TestBankUploadFile($filePath, 'cross_parish.csv', 'text/csv', strlen($csvContent));

        // User is in Parish 301, attempting to import into otherAccountId (Parish 302)
        $controller = $this->makeController('POST', [
            'bank_account_id' => $this->otherAccountId,
        ], $mockFile);

        $response = $controller->importStatement($this->otherAccountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(site_url('banking'), $response->getHeaderLine('Location'));

        $txnModel = new BankTransactionModel();
        $this->assertSame(0, $txnModel->where('bank_account_id', $this->otherAccountId)->countAllResults());
    }

    public function testInvalidFileTypeRejected(): void
    {
        $pdfContent = "%PDF-1.4 Fake PDF Content";
        $filePath = $this->createTempFile($pdfContent, 'document.pdf');
        $mockFile = new TestBankUploadFile($filePath, 'document.pdf', 'application/pdf', strlen($pdfContent));

        $controller = $this->makeController('POST', [
            'bank_account_id' => $this->accountId,
        ], $mockFile);

        $response = $controller->importStatement($this->accountId);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $txnModel = new BankTransactionModel();
        $this->assertSame(0, $txnModel->where('bank_account_id', $this->accountId)->countAllResults());
    }

    public function testAjaxContractReturnsJsonEnvelope(): void
    {
        $csvContent = "Date,Description,Amount\n2026-07-01,Test Ajax,100.00\n";
        $filePath = $this->createTempFile($csvContent, 'ajax.csv');
        $mockFile = new TestBankUploadFile($filePath, 'ajax.csv', 'text/csv', strlen($csvContent));

        // Successful AJAX upload
        $controller = $this->makeController('POST', [
            'bank_account_id' => $this->accountId,
        ], $mockFile, true);

        $response = $controller->importStatement($this->accountId);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('success', $data['status']);
        $this->assertSame(200, $data['meta']['http_status']);
        $this->assertSame(1, $data['data']['imported']);
        $this->assertSame(0, $data['data']['skipped']);

        // Missing file AJAX upload
        $badController = $this->makeController('POST', [
            'bank_account_id' => $this->accountId,
        ], null, true);

        $badResponse = $badController->importStatement($this->accountId);
        $this->assertSame(422, $badResponse->getStatusCode());

        $badData = json_decode((string) $badResponse->getBody(), true);
        $this->assertFalse($badData['success']);
        $this->assertSame('error', $badData['status']);
        $this->assertSame('FILE_REQUIRED', $badData['error']['code']);
    }

    // -----------------------------------------------------------------------
    // Test Helpers
    // -----------------------------------------------------------------------

    private function createTempFile(string $content, string $name = 'test.ofx'): string
    {
        $path = rtrim(WRITEPATH, '/') . '/' . uniqid('stmt_', true) . '_' . $name;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function makeController(string $method = 'GET', array $post = [], ?UploadedFile $file = null, bool $isAjax = false): Banking
    {
        $request = new class(
            config('App'),
            new URI('http://localhost/banking/import'),
            null,
            new UserAgent()
        ) extends IncomingRequest {
            public ?UploadedFile $mockFile = null;

            public function getFile(string $fileID): ?UploadedFile
            {
                return $this->mockFile;
            }
        };

        $request->mockFile = $file;
        $request->setMethod($method);

        if ($isAjax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        $controller = new Banking();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function seedParish(int $id, string $name): void
    {
        $this->db->table('parish')->insert([
            'id'         => $id,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId, string $first = 'User', string $last = 'Name'): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'first_name' => $first,
            'last_name'  => $last,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAccount(int $parishId, string $name, float $openingBalance = 0.0): int
    {
        $this->db->table('bank_account')->insert([
            'parish_id'       => $parishId,
            'name'            => $name,
            'currency_code'   => 'USD',
            'opening_balance' => $openingBalance,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function injectFinanceAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
            public function __construct(private int $userIdForTest, private array $perms)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'finance.' . $userIdForTest;
                $this->attributes['email']    = 'finance.' . $userIdForTest . '@example.com';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (in_array($permission, $this->perms, true)) {
                        return true;
                    }
                }

                return false;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('finance', $groups, true);
            }
        };

        $auth = new class ($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
