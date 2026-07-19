<?php

namespace Tests\Unit\IkasSync;

use App\Lib\IkasSync\RateService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('tr');
    }

    public function test_parse_turkish_number_formats(): void
    {
        $service = new RateService();

        $this->assertEquals(47.086, $service->parseTurkishNumber('47,086'));
        $this->assertEquals(6071.34, $service->parseTurkishNumber('6.071,34'));
        $this->assertEquals(38.11, $service->parseTurkishNumber('38.1100'));
    }

    public function test_build_kurlar_xml_from_json_items(): void
    {
        $service = new RateService();

        $xml = $service->buildKurlarXml([
            [
                'Kod' => 'USD',
                'Aciklama' => 'Amerikan Doları',
                'Alis' => '46,918',
                'Satis' => '47,086',
                'GuncellenmeZamani' => '16.07.2026 21:12:25',
            ],
        ]);

        $this->assertStringContainsString('<Kod>USD</Kod>', $xml);
        $this->assertStringContainsString('<Satis>47.0860</Satis>', $xml);

        $parsed = simplexml_load_string($xml);
        $this->assertEquals('47.0860', (string) $parsed->Kur->Satis);
    }

    public function test_update_rates_via_http_writes_xml_files(): void
    {
        Http::fake([
            'https://static.altinkaynak.com/public/Currency' => Http::response([
                ['Kod' => 'USD', 'Aciklama' => 'Amerikan Doları', 'Alis' => '46,918', 'Satis' => '47,086', 'GuncellenmeZamani' => '16.07.2026'],
            ]),
            'https://static.altinkaynak.com/public/Gold' => Http::response([
                ['Kod' => 'GA', 'Aciklama' => 'Gram Toptan', 'Alis' => '5.994,27', 'Satis' => '6.115,89', 'GuncellenmeZamani' => '16.07.2026'],
            ]),
        ]);

        $currencyPath = sys_get_temp_dir().'/kurlar-test-'.uniqid().'.xml';
        $goldPath = sys_get_temp_dir().'/altin-test-'.uniqid().'.xml';

        config([
            'ikas.rates.currency_xml' => $currencyPath,
            'ikas.rates.gold_xml' => $goldPath,
        ]);

        try {
            (new RateService())->updateRates();

            $this->assertFileExists($currencyPath);
            $this->assertFileExists($goldPath);

            $service = new RateService();
            $this->assertEquals(47.086, $service->getRate('USD'));
            $this->assertEquals(6115.89, $service->getRate('GA'));
        } finally {
            @unlink($currencyPath);
            @unlink($goldPath);
        }
    }

    public function test_inspect_rates_for_ui_missing_files(): void
    {
        config([
            'ikas.rates.currency_xml' => sys_get_temp_dir().'/missing-kurlar.xml',
            'ikas.rates.gold_xml' => sys_get_temp_dir().'/missing-altin.xml',
        ]);

        $status = (new RateService())->inspectRatesForUi();

        $this->assertFalse($status['ready']);
        $this->assertStringContainsString('yüklenmedi', $status['message']);
    }

    public function test_inspect_rates_for_ui_invalid_xml(): void
    {
        $currencyPath = sys_get_temp_dir().'/kurlar-invalid-'.uniqid().'.xml';
        $goldPath = sys_get_temp_dir().'/altin-invalid-'.uniqid().'.xml';
        file_put_contents($currencyPath, 'not xml');
        file_put_contents($goldPath, 'not xml');

        config([
            'ikas.rates.currency_xml' => $currencyPath,
            'ikas.rates.gold_xml' => $goldPath,
        ]);

        try {
            $status = (new RateService())->inspectRatesForUi();
            $this->assertFalse($status['ready']);
            $this->assertStringContainsString('Döviz kurları', $status['message']);
        } finally {
            @unlink($currencyPath);
            @unlink($goldPath);
        }
    }

    public function test_inspect_rates_for_ui_stale_rates(): void
    {
        $service = new RateService();
        $currencyPath = sys_get_temp_dir().'/kurlar-stale-'.uniqid().'.xml';
        $goldPath = sys_get_temp_dir().'/altin-stale-'.uniqid().'.xml';

        $staleTime = now()->subHours(2)->format('d.m.Y H:i:s');
        file_put_contents($currencyPath, $service->buildKurlarXml([
            ['Kod' => 'USD', 'Aciklama' => 'Amerikan Doları', 'Alis' => '1', 'Satis' => '2', 'GuncellenmeZamani' => $staleTime],
        ]));
        file_put_contents($goldPath, $service->buildKurlarXml([
            ['Kod' => 'GA', 'Aciklama' => 'Gram Toptan', 'Alis' => '1', 'Satis' => '2', 'GuncellenmeZamani' => $staleTime],
        ]));
        touch($currencyPath, now()->subHours(2)->timestamp);
        touch($goldPath, now()->subHours(2)->timestamp);

        config([
            'ikas.rates.currency_xml' => $currencyPath,
            'ikas.rates.gold_xml' => $goldPath,
            'ikas.rates.max_age_seconds' => 3600,
        ]);

        try {
            $status = $service->inspectRatesForUi();
            $this->assertFalse($status['ready']);
            $this->assertStringContainsString('eski', $status['message']);
        } finally {
            @unlink($currencyPath);
            @unlink($goldPath);
        }
    }

    public function test_inspect_rates_for_ui_fresh_rates(): void
    {
        $service = new RateService();
        $currencyPath = sys_get_temp_dir().'/kurlar-fresh-'.uniqid().'.xml';
        $goldPath = sys_get_temp_dir().'/altin-fresh-'.uniqid().'.xml';

        $freshTime = now()->format('d.m.Y H:i:s');
        file_put_contents($currencyPath, $service->buildKurlarXml([
            ['Kod' => 'USD', 'Aciklama' => 'Amerikan Doları', 'Alis' => '1', 'Satis' => '2', 'GuncellenmeZamani' => $freshTime],
        ]));
        file_put_contents($goldPath, $service->buildKurlarXml([
            ['Kod' => 'GA', 'Aciklama' => 'Gram Toptan', 'Alis' => '1', 'Satis' => '2', 'GuncellenmeZamani' => $freshTime],
        ]));

        config([
            'ikas.rates.currency_xml' => $currencyPath,
            'ikas.rates.gold_xml' => $goldPath,
            'ikas.rates.max_age_seconds' => 3600,
        ]);

        try {
            $status = $service->inspectRatesForUi();
            $this->assertTrue($status['ready']);
            $this->assertNotNull($status['kurlar']);
            $this->assertNotNull($status['altin']);
        } finally {
            @unlink($currencyPath);
            @unlink($goldPath);
        }
    }
}
