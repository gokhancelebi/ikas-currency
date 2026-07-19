<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateSimpleStockAndPriceTest extends LiveTestCase
{
    public function test_update_simple_stock_and_price(): void
    {
        $sku = IkasTestData::uniqueSku('UPD');
        $productId = null;

        try {
            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
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
            $this->graphql->update_variation_stock($variationId, 5, $productId);
            $this->graphql->update_multiple_variation_prices($productId, [[
                'variantId' => $variationId,
                'sellPrice' => '99.90',
                'discountPrice' => '129.90',
            ]]);

            $this->graphql->update_variation_stock($variationId, 10, $productId);
            $this->graphql->update_multiple_variation_prices($productId, [[
                'variantId' => $variationId,
                'sellPrice' => '109.90',
                'discountPrice' => '139.90',
            ]]);

            $product = $this->graphql->get_product_by_id($productId);
            $variant = IkasTestData::findVariantBySku($product, $sku);
            $this->assertNotNull($variant);
            $this->assertEquals('109.90', IkasTestData::variantSellPrice($variant));
            $this->assertEquals(10, IkasTestData::variantStock($variant));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
