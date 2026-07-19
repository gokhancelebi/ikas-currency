<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateMultiVariantStockAndPriceTest extends LiveTestCase
{
    public function test_update_multi_variant_stock_and_price(): void
    {
        $sku1 = ShopifyTestData::uniqueSku('MV1');
        $sku2 = ShopifyTestData::uniqueSku('MV2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_productv3(
                ShopifyTestData::variableV3ProductData($sku1, $sku2)
            );
            $productId = $create['productId'] ?? null;
            $this->assertNotEmpty($productId);

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $v1 = ShopifyTestData::findVariantBySku($product, $sku1);
            $v2 = ShopifyTestData::findVariantBySku($product, $sku2);
            $this->assertNotNull($v1);
            $this->assertNotNull($v2);

            $this->graphql->update_variation_stock($v1['id'], 11);
            $this->graphql->update_variation_stock($v2['id'], 22);

            $this->graphql->update_multiple_variation_prices($productId, [
                ['id' => $v1['id'], 'price' => '95.00', 'compareAtPrice' => '115.00'],
                ['id' => $v2['id'], 'price' => '97.00', 'compareAtPrice' => '117.00'],
            ]);

            $updated = $this->graphql->get_product_by_id_from_shopify($productId);
            $this->assertEquals('95.00', ShopifyTestData::findVariantBySku($updated, $sku1)['price']);
            $this->assertEquals('97.00', ShopifyTestData::findVariantBySku($updated, $sku2)['price']);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
