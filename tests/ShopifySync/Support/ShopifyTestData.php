<?php

namespace Tests\ShopifySync\Support;

class ShopifyTestData
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
        return (string) config('shopify.test_tag', 'SYNC_TEST');
    }

    /** @return array<string, mixed> */
    public static function simpleProductData(string $sku, string $barcode = ''): array
    {
        if ($barcode === '') {
            $barcode = $sku;
        }

        return [
            'title' => 'Test Simple '.$sku,
            'productType' => 'Test',
            'vendor' => 'Test Vendor',
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
    public static function variableV3ProductData(string $sku1, string $sku2): array
    {
        return [
            'title' => 'Test Variable V3 '.$sku1,
            'descriptionHtml' => '<p>Variable v3 test</p>',
            'productType' => 'Test',
            'vendor' => 'Test Vendor',
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

    /** @param  array<string, mixed>  $productData */
    public static function findVariantBySku(array $productData, string $sku): ?array
    {
        $edges = $productData['data']['product']['variants']['edges'] ?? [];
        foreach ($edges as $edge) {
            if (($edge['node']['sku'] ?? '') === $sku) {
                return $edge['node'];
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $products */
    public static function findVariantInAllProducts(array $products, string $sku): ?array
    {
        foreach ($products as $product) {
            foreach ($product['variants']['edges'] ?? [] as $edge) {
                if (($edge['node']['sku'] ?? '') === $sku) {
                    return ['product' => $product, 'variant' => $edge['node']];
                }
            }
        }

        return null;
    }

    public static function assertGraphqlOk($thisTest, ?array $response, string $context = 'GraphQL'): void
    {
        $thisTest->assertIsArray($response);
        $thisTest->assertEmpty($response['errors'] ?? [], $context.' errors: '.json_encode($response['errors'] ?? []));
    }
}
