<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Security surface tests for the unauthenticated connect-card intake
 * (GET/POST connect/{token}). Pins the invariants added by the public
 * intake hardening pass:
 *
 *  - unknown / inactive / deleted tokens → uniform 404
 *  - POST requires a session marker set by the GET (bot time-trap)
 *  - visit_date must be Y-m-d within the last 90 days, not future
 *  - interests are restricted to the known slug set
 *  - dedup never matches member/deleted people
 *
 * @internal
 */
final class ConnectCardIntakeFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const TOKEN = 'deadbeefdeadbeefdeadbeefdeadbeef';

    protected function setUp(): void
    {
        parent::setUp();

        helper(['form', 'security']);
        Services::reset(true);
        cache()->clean(); // reset apiRateLimit counters between tests

        CanonicalTestSchema::ensure($this->db);
        foreach (['followup_log', 'person_visit', 'parish_affiliation', 'person', 'parish', 'diocese'] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->withRoutes([
            ['GET', 'connect/(:alphanum)', '\\Clergy\\Controllers\\Import::cardForm/$1'],
            ['POST', 'connect/(:alphanum)', '\\Clergy\\Controllers\\Import::digitalCard/$1'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testUnknownTokenReturns404ForGetAndPost(): void
    {
        $this->get('connect/nonexistenttoken123')->assertStatus(404);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->call('post', 'connect/nonexistenttoken123', $this->cardPayload())
            ->assertStatus(404);
    }

    public function testInactiveAndDeletedParishTokensReturn404(): void
    {
        $this->seedParish('inactivetokenaaaa1111bbbb2222cccc', ['is_active' => 0]);
        $this->seedParish('deletedtokenaaaa1111bbbb2222cccc', ['deleted_at' => date('Y-m-d H:i:s')]);

        $this->get('connect/inactivetokenaaaa1111bbbb2222cccc')->assertStatus(404);
        $this->get('connect/deletedtokenaaaa1111bbbb2222cccc')->assertStatus(404);
    }

    public function testUppercaseTokenVariantReturns404(): void
    {
        $this->seedParish(self::TOKEN);

        $this->get('connect/' . strtoupper(self::TOKEN))->assertStatus(404);
        $this->get('connect/DeadBeefdeadbeefdeadbeefdeadbeef')->assertStatus(404);
    }

    public function testGetFormRendersForValidToken(): void
    {
        $this->seedParish(self::TOKEN);
        $response = $this->get('connect/' . self::TOKEN);
        $response->assertOK();
        $response->assertSee('Test Parish');
    }

    public function testPostWithoutIssuedMarkerIsSilentlyDropped(): void
    {
        $parishId = $this->seedParish(self::TOKEN);

        // No GET, no session marker — must not write, must not reveal failure.
        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload());

        $response->assertOK();
        $this->assertSame(0, $this->countRows('person'));
        $this->assertSame(0, $this->countRows('person_visit'));
        $this->assertSame(0, $this->countRows('parish_affiliation'));
    }

    public function testValidPostProvisionsPersonAffiliationAndVisit(): void
    {
        $parishId = $this->seedParish(self::TOKEN);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'interests' => ['children', 'music'],
            ]));

        $response->assertOK();
        $this->assertSame(1, $this->countRows('person'));
        $this->assertSame(1, $this->countRows('person_visit'));
        $this->assertSame(1, $this->countRows('parish_affiliation'));

        $affiliation = $this->db->table('parish_affiliation')->get()->getRowArray();
        $this->assertSame('visitor', $affiliation['membership_status']);
        $this->assertSame('digital_card', $affiliation['source']);
    }

    public function testPastVisitDateIsRejected(): void
    {
        $this->seedParish(self::TOKEN);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'visit_date' => '2000-01-01',
            ]));

        $response->assertOK();
        $this->assertSame(0, $this->countRows('person_visit'));
    }

    public function testFutureVisitDateIsRejected(): void
    {
        $this->seedParish(self::TOKEN);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'visit_date' => date('Y-m-d', strtotime('+30 days')),
            ]))
            ->assertOK();

        $this->assertSame(0, $this->countRows('person_visit'));
    }

    public function testNonIsoDateFormatIsRejected(): void
    {
        $this->seedParish(self::TOKEN);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'visit_date' => 'next Friday',
            ]))
            ->assertOK();

        $this->assertSame(0, $this->countRows('person_visit'));
    }

    public function testUnknownInterestSlugIsRejected(): void
    {
        $this->seedParish(self::TOKEN);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'interests' => ['children', 'made_up_slug'],
            ]))
            ->assertOK();

        $this->assertSame(0, $this->countRows('person'));
    }

    public function testOversizedAddressIsRejected(): void
    {
        $this->seedParish(self::TOKEN);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'address' => str_repeat('A', 501),
            ]))
            ->assertOK();

        $this->assertSame(0, $this->countRows('person'));
    }

    public function testDedupDoesNotMatchMemberAffiliation(): void
    {
        $parishId = $this->seedParish(self::TOKEN);

        // A member whose contact matches the incoming card must NOT be
        // dedup-matched — a new visitor person is provisioned instead.
        $memberPersonId = $this->seedPerson('member@example.org');
        $this->seedAffiliation($memberPersonId, $parishId, 'member');

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'email' => 'member@example.org',
            ]))
            ->assertOK();

        // Original member: untouched, zero visits.
        $memberVisits = $this->db->table('person_visit')
            ->where('person_id', $memberPersonId)
            ->countAllResults();
        $this->assertSame(0, $memberVisits);

        // A distinct visitor person + affiliation was provisioned.
        $this->assertSame(2, $this->countRows('person'));
        $visitorAffiliations = $this->db->table('parish_affiliation')
            ->where('membership_status', 'visitor')
            ->countAllResults();
        $this->assertSame(1, $visitorAffiliations);
    }

    public function testDedupMatchesExistingVisitor(): void
    {
        $parishId = $this->seedParish(self::TOKEN);
        $visitorPersonId = $this->seedPerson('repeat@example.org');
        $this->seedAffiliation($visitorPersonId, $parishId, 'visitor');

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->withSession(['cc_issued_' . self::TOKEN => time() - 10])
            ->call('post', 'connect/' . self::TOKEN, $this->cardPayload([
                'email' => 'repeat@example.org',
            ]));
        $response->assertOK();

        // Same person, one visit recorded — no duplicate person.
        $this->assertSame(1, $this->countRows('person'));
        $visits = $this->db->table('person_visit')
            ->where('person_id', $visitorPersonId)
            ->countAllResults();
        $this->assertSame(1, $visits);
    }

    public function testConsecutiveParishInsertsWithoutTokenSucceed(): void
    {
        // Mirrors Parishes::store and SyncUeidDirectory multi-row imports,
        // which omit public_token; the unique key must not reject row 2+.
        // The SQLite test schema has no unique key, so mirror the production
        // index here or this test cannot detect a regression. Raw SQL needs
        // the physical (prefixed) table name — 'parish' alone does not exist.
        $parishTable = $this->db->prefixTable('parish');
        $this->db->query("CREATE UNIQUE INDEX IF NOT EXISTS uniq_parish_public_token ON {$parishTable} (public_token)");

        foreach (['Parish A', 'Parish B', 'Parish C'] as $name) {
            $this->assertTrue((bool) $this->db->table('parish')->insert([
                'name'       => $name,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
        }

        $this->assertSame(3, $this->countRows('parish'));
        $this->assertSame(3, $this->db->table('parish')->where('public_token', null)->countAllResults());
    }

    public function testUniqueTokenIndexRejectsDuplicateEmptyTokens(): void
    {
        // Sanity check that the index is enforced: the old NOT NULL DEFAULT ''
        // shape would collide on the second insert.
        $parishTable = $this->db->prefixTable('parish');
        $this->db->query("CREATE UNIQUE INDEX IF NOT EXISTS uniq_parish_public_token ON {$parishTable} (public_token)");

        $row = [
            'name'         => 'Parish Blank',
            'is_active'    => 1,
            'public_token' => '',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        $this->assertTrue((bool) $this->db->table('parish')->insert($row));

        $rejected = false;
        try {
            $rejected = ! $this->db->table('parish')->insert($row);
        } catch (\Throwable $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Duplicate empty public_token must violate the unique key');
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private function cardPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name'  => 'Visitor',
            'email'      => 'jane@example.org',
            'phone'      => '555-0100',
            'address'    => '123 Main St',
            'visit_date' => date('Y-m-d'),
        ], $overrides);
    }

    private function seedParish(string $token, array $overrides = []): int
    {
        $dioceseId = $this->db->table('diocese')->insert([
            'name'       => 'Test Diocese',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) ? (int) $this->db->insertID() : null;

        $this->db->table('parish')->insert(array_merge([
            'diocese_id'   => $dioceseId,
            'name'         => 'Test Parish',
            'is_active'    => 1,
            'public_token' => $token,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function seedPerson(string $email): int
    {
        $personModel = model(\App\Models\PersonModel::class);
        $id = $personModel->insert([
            'first_name' => 'Existing',
            'last_name'  => 'Person',
            'email'      => $email,
            'source'     => 'test',
            'is_active'  => 1,
        ], true);

        if (! is_int($id) || $id <= 0) {
            throw new \RuntimeException('seedPerson failed: ' . json_encode($personModel->errors()));
        }

        return $id;
    }

    private function seedAffiliation(int $personId, int $parishId, string $status): void
    {
        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'membership_status' => $status,
            'source'            => 'test',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->db->table($table)->countAllResults();
    }
}
