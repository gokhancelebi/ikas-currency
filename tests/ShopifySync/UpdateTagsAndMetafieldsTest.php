<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateTagsAndMetafieldsTest extends LiveTestCase
{
    public function test_update_tags_and_metafields(): void
    {
        $sku = ShopifyTestData::uniqueSku('TAGS');
        $productId = null;
        $tag = ShopifyTestData::productTag();

        try {
            $create = $this->graphql->create_simple_product(ShopifyTestData::simpleProductData($sku));
            $productId = $create['productId'] ?? null;

            $tagResp = $this->graphql->update_product_tags($productId, [$tag, 'LiveTest']);
            ShopifyTestData::assertGraphqlOk($this, $tagResp, 'update_product_tags');

            $metaResp = $this->graphql->update_product_metafields($productId, [[
                'namespace' => 'sync_test',
                'key' => 'source',
                'type' => 'single_line_text_field',
                'value' => 'shopify-sync',
            ]]);
            ShopifyTestData::assertGraphqlOk($this, $metaResp, 'update_product_metafields');

            $product = $this->graphql->get_product_by_id_from_shopify($productId);
            $tags = $product['data']['product']['tags'] ?? [];
            $this->assertContains($tag, $tags);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
