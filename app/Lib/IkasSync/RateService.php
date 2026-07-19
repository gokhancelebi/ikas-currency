<?php

namespace App\Lib\IkasSync;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class RateService
{
    /** @var array<string, float|string> */
    private array $rates = [];

    public function updateRates(): void
    {
        $currencyUrl = (string) config('ikas.altinkaynak.currency_url');
        $goldUrl = (string) config('ikas.altinkaynak.gold_url');

        $currencyResponse = Http::timeout(30)->get($currencyUrl);
        $goldResponse = Http::timeout(30)->get($goldUrl);

        if (! $currencyResponse->successful()) {
            throw new \RuntimeException(__('rates.currency_api_failed', ['url' => $currencyUrl]));
        }

        if (! $goldResponse->successful()) {
            throw new \RuntimeException(__('rates.gold_api_failed', ['url' => $goldUrl]));
        }

        /** @var array<int, array<string, string>> $currencyItems */
        $currencyItems = $currencyResponse->json();
        /** @var array<int, array<string, string>> $goldItems */
        $goldItems = $goldResponse->json();

        if (! is_array($currencyItems) || ! is_array($goldItems)) {
            throw new \RuntimeException(__('rates.json_invalid'));
        }

        file_put_contents(
            config('ikas.rates.currency_xml'),
            $this->buildKurlarXml($currencyItems)
        );

        file_put_contents(
            config('ikas.rates.gold_xml'),
            $this->buildKurlarXml($goldItems)
        );
    }

    /**
     * @param  array<int, array<string, string>>  $items
     */
    public function buildKurlarXml(array $items): string
    {
        $lines = ['<?xml version="1.0" encoding="utf-8"?>', '<Kurlar>'];

        foreach ($items as $item) {
            $kod = htmlspecialchars((string) ($item['Kod'] ?? ''), ENT_XML1);
            $aciklama = htmlspecialchars((string) ($item['Aciklama'] ?? ''), ENT_XML1);
            $alis = $this->formatRateForXml((string) ($item['Alis'] ?? '0'));
            $satis = $this->formatRateForXml((string) ($item['Satis'] ?? '0'));
            $zaman = htmlspecialchars((string) ($item['GuncellenmeZamani'] ?? ''), ENT_XML1);

            $lines[] = '<Kur>';
            $lines[] = "<Kod>{$kod}</Kod>";
            $lines[] = "<Aciklama>{$aciklama}</Aciklama>";
            $lines[] = "<Alis>{$alis}</Alis>";
            $lines[] = "<Satis>{$satis}</Satis>";
            $lines[] = "<GuncellenmeZamani>{$zaman}</GuncellenmeZamani>";
            $lines[] = '</Kur>';
        }

        $lines[] = '</Kurlar>';

        return implode('', $lines);
    }

    public function parseTurkishNumber(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    public function formatRateForXml(string $value): string
    {
        return number_format($this->parseTurkishNumber($value), 4, '.', '');
    }

    /**
     * @return array{
     *     ready: bool,
     *     message: string,
     *     kurlar: ?SimpleXMLElement,
     *     altin: ?SimpleXMLElement,
     *     last_rates_at: ?Carbon
     * }
     */
    public function inspectRatesForUi(?int $maxAgeSeconds = null): array
    {
        $maxAgeSeconds ??= (int) config('ikas.rates.max_age_seconds', 3600);
        $currencyPath = (string) config('ikas.rates.currency_xml');
        $goldPath = (string) config('ikas.rates.gold_xml');

        if (! is_readable($currencyPath) || ! is_readable($goldPath)) {
            return $this->ratesUiError(__('rates.not_loaded'));
        }

        $kurlar = $this->loadValidRatesXml($currencyPath);
        if ($kurlar === null) {
            return $this->ratesUiError(__('rates.currency_invalid'));
        }

        $altin = $this->loadValidRatesXml($goldPath);
        if ($altin === null) {
            return $this->ratesUiError(__('rates.gold_invalid'));
        }

        $currencyAt = $this->latestRatesTimestamp($kurlar, $currencyPath);
        $goldAt = $this->latestRatesTimestamp($altin, $goldPath);
        $lastRatesAt = $currencyAt->greaterThan($goldAt) ? $currencyAt : $goldAt;

        if ($lastRatesAt->lt(now()->subSeconds($maxAgeSeconds))) {
            return $this->ratesUiError(__('rates.stale', [
                'age' => $this->formatMaxAgeLabel($maxAgeSeconds),
                'date' => $lastRatesAt->format('d.m.Y H:i'),
            ]));
        }

        return [
            'ready' => true,
            'message' => '',
            'kurlar' => $kurlar,
            'altin' => $altin,
            'last_rates_at' => $lastRatesAt,
        ];
    }

    public function parseGuncellenmeZamani(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed instanceof \DateTime) {
                return Carbon::instance($parsed);
            }
        }

        return null;
    }

    /** @return array{ready: false, message: string, kurlar: null, altin: null, last_rates_at: null} */
    private function ratesUiError(string $message): array
    {
        return [
            'ready' => false,
            'message' => $message,
            'kurlar' => null,
            'altin' => null,
            'last_rates_at' => null,
        ];
    }

    private function loadValidRatesXml(string $path): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_clear_errors();

        if (! $xml instanceof SimpleXMLElement || ! isset($xml->Kur)) {
            return null;
        }

        $entries = isset($xml->Kur[0]) ? $xml->Kur : [$xml->Kur];
        foreach ($entries as $kur) {
            if (trim((string) $kur->Kod) !== '' && trim((string) $kur->Satis) !== '') {
                return $xml;
            }
        }

        return null;
    }

    private function latestRatesTimestamp(SimpleXMLElement $xml, string $path): Carbon
    {
        $latest = null;
        $entries = isset($xml->Kur[0]) ? $xml->Kur : [$xml->Kur];

        foreach ($entries as $kur) {
            $parsed = $this->parseGuncellenmeZamani((string) ($kur->GuncellenmeZamani ?? ''));
            if ($parsed !== null && ($latest === null || $parsed->greaterThan($latest))) {
                $latest = $parsed;
            }
        }

        $fileTime = Carbon::createFromTimestamp((int) filemtime($path));
        if ($latest === null || $fileTime->greaterThan($latest)) {
            $latest = $fileTime;
        }

        return $latest;
    }

    private function formatMaxAgeLabel(int $seconds): string
    {
        if ($seconds % 3600 === 0 && $seconds >= 3600) {
            $hours = (int) ($seconds / 3600);

            return $hours === 1
                ? __('rates.age.one_hour')
                : __('rates.age.hours', ['count' => $hours]);
        }

        if ($seconds % 60 === 0 && $seconds >= 60) {
            $minutes = (int) ($seconds / 60);

            return __('rates.age.minutes', ['count' => $minutes]);
        }

        return __('rates.age.seconds', ['count' => $seconds]);
    }

    /**
     * @return array<string, float|string>
     */
    public function getRates(): array
    {
        $rates = [];

        $currencyPath = config('ikas.rates.currency_xml');
        if (file_exists($currencyPath)) {
            $array = json_decode(json_encode(simplexml_load_file($currencyPath)), true);
            if (isset($array['Kur'])) {
                $kurList = isset($array['Kur'][0]) ? $array['Kur'] : [$array['Kur']];
                foreach ($kurList as $rate) {
                    $rates[$rate['Kod']] = $rate['Satis'];
                }
            }
        }

        $goldPath = config('ikas.rates.gold_xml');
        if (file_exists($goldPath)) {
            $array = json_decode(json_encode(simplexml_load_file($goldPath)), true);
            if (isset($array['Kur'])) {
                $kurList = isset($array['Kur'][0]) ? $array['Kur'] : [$array['Kur']];
                foreach ($kurList as $rate) {
                    $rates[$rate['Kod']] = $rate['Satis'];
                }
            }
        }

        $this->rates = $rates;

        return $rates;
    }

    public function getRate(string $description): float|false
    {
        if ($description === 'TL') {
            return 1;
        }

        $currencyPath = config('ikas.rates.currency_xml');
        if (file_exists($currencyPath)) {
            $rate = $this->findRateInXml($currencyPath, $description);
            if ($rate !== false) {
                return $rate;
            }
        }

        $goldPath = config('ikas.rates.gold_xml');
        if (file_exists($goldPath)) {
            return $this->findRateInXml($goldPath, $description);
        }

        return false;
    }

    private function findRateInXml(string $path, string $description): float|false
    {
        $array = json_decode(json_encode(simplexml_load_file($path)), true);
        $rates = $array['Kur'] ?? [];

        if (isset($rates['Kod'])) {
            $rates = [$rates];
        }

        foreach ($rates as $rate) {
            if (($rate['Aciklama'] ?? '') == $description || ($rate['Kod'] ?? '') == $description) {
                return (float) $rate['Satis'];
            }
        }

        return false;
    }
}
