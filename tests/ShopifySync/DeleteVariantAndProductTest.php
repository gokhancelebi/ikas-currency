<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class DeleteVariantAndProductTest extends LiveTestCase
{
    public function test_delete_variant_and_product(): void
    {
        $sku1 = ShopifyTestData::uniqueSku('DEL1');
        $sku2 = ShopifyTestData::uniqueSku('DEL2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_productv3(
                ShopifyTestData::variableV3ProductData($sku1, $sku2)
            );
            $productId = $create['productId'] ?? null;

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $v2 = ShopifyTestData::findVariantBySku($product, $sku2);
            $this->assertNotNull($v2);

            $delVariant = $this->graphql->delete_product_variant($productId, $v2['id']);
            ShopifyTestData::assertGraphqlOk($this, $delVariant, 'delete_product_variant');

            $afterDeleteVariant = $this->graphql->get_product_by_id_from_shopify($productId);
            $this->assertNull(ShopifyTestData::findVariantBySku($afterDeleteVariant, $sku2));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
