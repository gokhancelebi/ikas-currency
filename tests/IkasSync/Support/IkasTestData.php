<?php

namespace Tests\IkasSync\Support;

use App\Lib\IkasSync\IkasProductGraphQL;

class IkasTestData
{
    public static function uniqueSku(string $suffix = ''): string
    {
        $suffixPart = $suffix !== '' ? '-'.$suffix : '';

        return 'TEST-SYNC-'.time().$suffixPart;
    }

    public static function imageUrl(): string
    {
        return 'https://cdn.shopify.com/s/files/1/0533/2089/files/placeholder-images-image_large.png';
    }

    public static function productTag(): string
    {
        return (string) config('ikas.test_tag', 'SYNC_TEST');
    }

    /** @return array<string, mixed> */
    public static function simpleProductData(string $sku, string $barcode = ''): array
    {
        if ($barcode === '') {
            $barcode = $sku;
        }

        return [
            'title' => 'Test Simple '.$sku,
            'descriptionHtml' => '<p>Live sync test product</p>',
            'inventory_data' => [
                'variations' => [[
                    'barcode' => $barcode,
                    'cost' => '50.00',
                    'price' => '99.90',
                    'sku' => $sku,
                    'compareAtPrice' => '129.90',
                    'options' => [],
                    'inventoryQuantity' => 5,
                ]],
                'options' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function variableProductData(string $sku1, string $sku2): array
    {
        return [
            'title' => 'Test Variable '.$sku1,
            'descriptionHtml' => '<p>Variable test</p>',
            'inventory_data' => [
                'options' => [
                    ['name' => 'Renk', 'values' => [['name' => 'Kirmizi'], ['name' => 'Mavi']]],
                ],
                'variations' => [
                    [
                        'barcode' => $sku1,
                        'cost' => '40.00',
                        'price' => '89.90',
                        'sku' => $sku1,
                        'compareAtPrice' => '109.90',
                        'inventoryQuantity' => 3,
                        'options' => [
                            ['name' => 'Renk', 'optionName' => 'Kirmizi'],
                        ],
                    ],
                    [
                        'barcode' => $sku2,
                        'cost' => '42.00',
                        'price' => '91.90',
                        'sku' => $sku2,
                        'compareAtPrice' => '111.90',
                        'inventoryQuantity' => 7,
                        'options' => [
                            ['name' => 'Renk', 'optionName' => 'Mavi'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function findVariantBySku(?array $product, string $sku): ?array
    {
        if ($product === null) {
            return null;
        }

        foreach ($product['variants'] ?? [] as $variant) {
            if (($variant['sku'] ?? '') === $sku) {
                return $variant;
            }
        }

        return null;
    }

    public static function assertGraphqlOk($thisTest, ?array $response, string $context = 'GraphQL'): void
    {
        $thisTest->assertIsArray($response);
        $thisTest->assertEmpty($response['errors'] ?? [], $context.' errors: '.json_encode($response['errors'] ?? []));
    }

    public static function variantSellPrice(array $variant): string
    {
        return number_format(IkasProductGraphQL::variantSellPrice($variant), 2, '.', '');
    }

    public static function variantStock(array $variant): int
    {
        return IkasProductGraphQL::variantStockCount($variant);
    }
}
