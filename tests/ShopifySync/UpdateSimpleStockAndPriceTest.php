<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateSimpleStockAndPriceTest extends LiveTestCase
{
    public function test_update_simple_stock_and_price(): void
    {
        $sku = ShopifyTestData::uniqueSku('UPD');
        $productId = null;

        try {
            $productData = ShopifyTestData::simpleProductData($sku);
            $create = $this->graphql->create_simple_product($productData);
            $productId = $create['productId'];
            $variationId = $create['variantId'];

            $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variationId,
                'barcode' => $sku,
                'price' => '99.90',
                'compareAtPrice' => '129.90',
                'cost' => '50.00',
                'sku' => $sku,
            ]);
            $this->graphql->update_variation_stock($variationId, 5);
            $this->graphql->update_multiple_variation_prices($productId, [[
                'id' => $variationId,
                'price' => '99.90',
                'compareAtPrice' => '129.90',
                'inventoryItem' => ['cost' => '50.00', 'tracked' => true],
            ]]);

            $this->graphql->update_variation_stock($variationId, 10);
            $this->graphql->update_multiple_variation_prices($productId, [[
                'id' => $variationId,
                'price' => '109.90',
                'compareAtPrice' => '139.90',
                'inventoryItem' => ['cost' => '55.00', 'tracked' => true],
            ]]);

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $variant = ShopifyTestData::findVariantBySku($product, $sku);
            $this->assertNotNull($variant);
            $this->assertEquals('109.90', $variant['price']);
            $this->assertEquals(10, (int) $variant['inventoryQuantity']);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
