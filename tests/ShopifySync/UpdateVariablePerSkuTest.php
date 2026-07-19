<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateVariablePerSkuTest extends LiveTestCase
{
    public function test_update_variable_per_sku(): void
    {
        $sku1 = ShopifyTestData::uniqueSku('SKU1');
        $sku2 = ShopifyTestData::uniqueSku('SKU2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_productv3(
                ShopifyTestData::variableV3ProductData($sku1, $sku2)
            );
            $productId = $create['productId'] ?? null;

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $v1 = ShopifyTestData::findVariantBySku($product, $sku1);
            $v2 = ShopifyTestData::findVariantBySku($product, $sku2);

            $this->graphql->update_multiple_variation_prices($productId, [
                ['id' => $v1['id'], 'price' => '80.00', 'compareAtPrice' => '100.00'],
            ]);

            $after = $this->graphql->get_product_by_id_from_shopify($productId);
            $this->assertEquals('80.00', ShopifyTestData::findVariantBySku($after, $sku1)['price']);
            $this->assertEquals('91.90', ShopifyTestData::findVariantBySku($after, $sku2)['price']);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
