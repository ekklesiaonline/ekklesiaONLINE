<?php

declare(strict_types=1);

namespace Tests;

use App\Services\DeaneryService;
use App\Services\HierarchyService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class DeaneryScopeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    /** @var array<string, list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        if (! empty($this->created['diocese'])) {
            $deaneryIds = array_column($this->db->table('deanery')->whereIn('diocese_id',
                $this->created['diocese'])->get()->getResultArray(), 'id');
            if ($deaneryIds !== []) {
                $this->db->table('deanery_parish_membership')->whereIn('deanery_id', $deaneryIds)->delete();
                $this->db->table('deanery')->whereIn('id', $deaneryIds)->delete();
            }
        }
        foreach (['parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        parent::tearDown();
    }

    public function testSchemaAndScopedMembershipTransfer(): void
    {
        self::assertTrue($this->db->tableExists('deanery'));
        self::assertTrue($this->db->tableExists('deanery_parish_membership'));
        $provinceId = $this->createRow('province', ['province_number' => 'I', 'name' => 'Test Province']);
        $dioceseA = $this->createRow('diocese', ['province_id' => $provinceId, 'name' => 'Diocese A']);
        $dioceseB = $this->createRow('diocese', ['province_id' => $provinceId, 'name' => 'Diocese B']);
        $otherProvince = $this->createRow('province', ['province_number' => 'II', 'name' => 'Other Province']);
        $dioceseC = $this->createRow('diocese', ['province_id' => $otherProvince, 'name' => 'Diocese C']);
        $parishA = $this->createRow('parish', ['diocese_id' => $dioceseA, 'name' => 'Parish A']);
        $parishB = $this->createRow('parish', ['diocese_id' => $dioceseB, 'name' => 'Parish B']);
        $parishC = $this->createRow('parish', ['diocese_id' => $dioceseC, 'name' => 'Parish C']);
        $service = new DeaneryService($this->db);
        $deaneryA = $service->create($dioceseA, 'a', 'Region A');
        $deaneryB = $service->create($dioceseA, 'b', 'Region B');
        self::assertNotNull($deaneryA);
        self::assertNotNull($deaneryB);
        $hierarchy = new HierarchyService($this->db);
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');

        self::assertFalse($service->assignParish($deaneryA, $parishB, $today, null, 1));
        self::assertFalse($service->assignParish($deaneryA, $parishC, $today, null, 1));
        self::assertTrue($service->assignParish($deaneryA, $parishA, $yesterday, null, 1));
        self::assertFalse($service->assignParish($deaneryB, $parishA, $today, null, 1));
        self::assertTrue($hierarchy->parishInDeanery($parishA, $deaneryA));
        self::assertFalse($hierarchy->parishInDeanery($parishB, $deaneryA));
        self::assertFalse($hierarchy->parishInDeanery($parishC, $deaneryA));
        self::assertFalse($hierarchy->parishInDeanery($parishA, $deaneryB));
        $membershipId = (int) $this->db->table('deanery_parish_membership')->where('parish_id', $parishA)->get()->getRow('id');
        self::assertTrue($service->endMembership($membershipId, $dioceseA, $yesterday));
        self::assertTrue($service->assignParish($deaneryB, $parishA, $today, null, 1));
        self::assertFalse($hierarchy->parishInDeanery($parishA, $deaneryA));
        self::assertTrue($hierarchy->parishInDeanery($parishA, $deaneryB));
        self::assertTrue($hierarchy->parishInDeanery($parishA, $deaneryA, $yesterday));
        self::assertFalse($hierarchy->deaneryInDiocese($deaneryA, $dioceseB));
    }

    private function createRow(string $table, array $values): int
    {
        $this->db->table($table)->insert($values);
        $id = (int) $this->db->insertID();
        $this->created[$table][] = $id;
        return $id;
    }
}
