<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateVariableWithMediaAttachTest extends LiveTestCase
{
    public function test_create_variable_with_media_attach(): void
    {
        $sku1 = ShopifyTestData::uniqueSku('MEDV1');
        $sku2 = ShopifyTestData::uniqueSku('MEDV2');
        $productId = null;

        try {
            $productData = ShopifyTestData::variableV3ProductData($sku1, $sku2);
            $create = $this->graphql->create_variable_productv3($productData);
            $productId = $create['productId'] ?? null;
            $this->assertNotEmpty($productId);

            $mediaResp = $this->graphql->productCreateMedia($productId, ShopifyTestData::imageUrl());
            ShopifyTestData::assertGraphqlOk($this, $mediaResp, 'productCreateMedia');

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $variant = ShopifyTestData::findVariantBySku($product, $sku1);
            $this->assertNotNull($variant);

            $mediaId = $mediaResp['data']['productCreateMedia']['media'][0]['id'] ?? null;
            if ($mediaId) {
                $attach = $this->graphql->attach_media_to_variant($productId, $variant['id'], $mediaId);
                ShopifyTestData::assertGraphqlOk($this, $attach, 'attach_media_to_variant');
            }

            $this->publishProduct($productId);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
