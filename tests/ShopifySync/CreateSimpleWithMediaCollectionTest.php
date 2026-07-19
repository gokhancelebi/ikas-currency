<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateSimpleWithMediaCollectionTest extends LiveTestCase
{
    public function test_create_simple_with_media_and_collection(): void
    {
        $sku = ShopifyTestData::uniqueSku('MEDIA');
        $productId = null;
        $collectionId = null;

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
            $this->publishProduct($productId);

            $colResp = $this->graphql->create_collection('TEST-COL-'.time());
            ShopifyTestData::assertGraphqlOk($this, $colResp, 'create_collection');
            $collectionId = $colResp['data']['collectionCreate']['collection']['id'] ?? null;
            $this->assertNotEmpty($collectionId);

            $addResp = $this->graphql->add_product_to_colletion($collectionId, [$productId]);
            ShopifyTestData::assertGraphqlOk($this, $addResp, 'add_product_to_colletion');

            $mediaResp = $this->graphql->productCreateMedia($productId, ShopifyTestData::imageUrl());
            ShopifyTestData::assertGraphqlOk($this, $mediaResp, 'productCreateMedia');

            $ids = $this->graphql->get_product_collection_ids($productId);
            $this->assertContains($collectionId, $ids);
        } finally {
            $this->cleanupProduct($productId);
            $this->cleanupCollection($collectionId);
        }
    }
}
