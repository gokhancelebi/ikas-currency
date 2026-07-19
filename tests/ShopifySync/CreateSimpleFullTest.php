<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateSimpleFullTest extends LiveTestCase
{
    public function test_create_simple_product_full_flow(): void
    {
        $sku = ShopifyTestData::uniqueSku('SIMPLE');
        $productId = null;

        try {
            $productData = ShopifyTestData::simpleProductData($sku);
            $variation = $productData['inventory_data']['variations'][0];

            $create = $this->graphql->create_simple_product($productData);
            $productId = $create['productId'] ?? null;
            $variationId = $create['variantId'] ?? null;

            $this->assertNotEmpty($productId);
            $this->assertNotEmpty($variationId);

            $inv = $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variationId,
                'barcode' => $variation['barcode'],
                'price' => '99.90',
                'compareAtPrice' => '129.90',
                'cost' => '50.00',
                'sku' => $sku,
            ]);
            ShopifyTestData::assertGraphqlOk($this, $inv, 'update_simple_product_inventory_data');

            $this->graphql->update_variation_stock($variationId, 5);

            $priceResp = $this->graphql->update_multiple_variation_prices($productId, [[
                'id' => $variationId,
                'price' => '99.90',
                'compareAtPrice' => '129.90',
                'inventoryItem' => ['cost' => '50.00', 'tracked' => true],
            ]]);
            ShopifyTestData::assertGraphqlOk($this, $priceResp, 'update_multiple_variation_prices');

            $this->publishProduct($productId);

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $this->assertEquals($productData['title'], $product['data']['product']['title'] ?? '');

            $variant = ShopifyTestData::findVariantBySku($product, $sku);
            $this->assertNotNull($variant);
            $this->assertEquals('99.90', $variant['price']);
            $this->assertEquals(5, (int) $variant['inventoryQuantity']);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
