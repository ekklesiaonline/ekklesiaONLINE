<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ServicesLiturgicalDaysLocalizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testLiturgicalDaysEndpointReturnsLocalizedLabelsPerLocale(): void
    {
        if (!$this->db->tableExists('liturgical_calendar_entry')) {
            $this->markTestSkipped('liturgical_calendar_entry table is not available.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('liturgical_calendar_entry')->insert([
            'church_id' => null,
            'code' => 'test_maundy_thursday_locale',
            'name' => 'Maundy Thursday',
            'season' => 'Holy Week',
            'observance_type' => 'day',
            'sort_order' => 270,
            'liturgical_color' => 'white',
            'season_icon' => null,
            'observance_icon' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $expectedByLocale = [
            'en' => 'Maundy Thursday',
            'es' => 'Jueves Santo',
            'fr' => 'Jeudi saint',
        ];

        foreach ($expectedByLocale as $locale => $expectedLabel) {
            $payload = $this->callLiturgicalDaysEndpoint('Holy Week', $locale);
            $items = $payload['items'] ?? [];

            $this->assertIsArray($items);
            $this->assertNotEmpty($items);

            $target = null;
            foreach ($items as $item) {
                if ((string) ($item['name'] ?? '') === 'Maundy Thursday') {
                    $target = $item;
                    break;
                }
            }

            $this->assertNotNull($target, 'Expected Maundy Thursday item for locale ' . $locale);
            $this->assertSame($expectedLabel, (string) ($target['label'] ?? ''), 'Localized label mismatch for locale ' . $locale);
        }
    }

    /**
     * Calls Services::liturgicalDays with mocked permission/church scope and returns decoded JSON.
     */
    private function callLiturgicalDaysEndpoint(string $season, string $locale): array
    {
        $request = service('request');
        $request->setLocale($locale);
        $request->setGlobal('get', ['season' => $season]);

        $response = service('response');
        $logger = service('logger');

        $controller = new class extends \Worship\Controllers\Services {
            protected function checkPermission(string $permission): bool
            {
                return true;
            }

            protected function requireParish()
            {
                return null;
            }

            protected function getChurchId()
            {
                return 1;
            }
        };

        $controller->initController($request, $response, $logger);
        $result = $controller->liturgicalDays();

        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);
        $this->assertSame(200, $result->getStatusCode());

        $decoded = json_decode($result->getBody(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
