<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateVariableV3FullTest extends LiveTestCase
{
    public function test_create_variable_product_v3(): void
    {
        $sku1 = ShopifyTestData::uniqueSku('V3A');
        $sku2 = ShopifyTestData::uniqueSku('V3B');
        $productId = null;

        try {
            $productData = ShopifyTestData::variableV3ProductData($sku1, $sku2);
            $create = $this->graphql->create_variable_productv3($productData);
            $productId = $create['productId'] ?? null;
            $this->assertNotEmpty($productId);

            $this->publishProduct($productId);

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $this->assertNotNull(ShopifyTestData::findVariantBySku($product, $sku1));
            $this->assertNotNull(ShopifyTestData::findVariantBySku($product, $sku2));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
