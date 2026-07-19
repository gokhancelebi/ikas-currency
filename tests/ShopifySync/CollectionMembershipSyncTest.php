<?php

namespace Tests\ShopifySync;

use App\Lib\ShopifySync\CollectionSyncService;
use App\Models\Collection as CollectionModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CollectionMembershipSyncTest extends LiveTestCase
{
    public function test_collection_membership_sync(): void
    {
        $sku = ShopifyTestData::uniqueSku('COLSYNC');
        $productId = null;
        $collectionA = null;
        $collectionB = null;

        try {
            $create = $this->graphql->create_simple_product(ShopifyTestData::simpleProductData($sku));
            $productId = $create['productId'];
            $variationId = $create['variantId'];
            $this->graphql->update_variation_stock($variationId, 3);
            $this->publishProduct($productId);

            $colAResp = $this->graphql->create_collection('TEST-COL-A-'.time());
            ShopifyTestData::assertGraphqlOk($this, $colAResp, 'create_collection A');
            $collectionA = $colAResp['data']['collectionCreate']['collection']['id'] ?? null;

            $colBResp = $this->graphql->create_collection('TEST-COL-B-'.time());
            ShopifyTestData::assertGraphqlOk($this, $colBResp, 'create_collection B');
            $collectionB = $colBResp['data']['collectionCreate']['collection']['id'] ?? null;

            $this->graphql->add_product_to_colletion($collectionA, [$productId]);

            $before = $this->graphql->get_product_collection_ids($productId);
            $this->assertContains($collectionA, $before);

            $this->graphql->remove_product_from_collection($collectionA, [$productId]);
            $this->graphql->add_product_to_colletion($collectionB, [$productId]);

            $after = $this->graphql->get_product_collection_ids($productId);
            $this->assertContains($collectionB, $after);
            $this->assertNotContains($collectionA, $after);

            CollectionModel::create([
                'name' => 'Test Collection A',
                'shopify_collection_id' => (string) $collectionA,
                'product_list' => json_encode([]),
                'active' => 'active',
            ]);

            app(CollectionSyncService::class)->sync();

            $row = CollectionModel::where('shopify_collection_id', $collectionA)->first();
            $this->assertNotNull($row);
            $this->assertIsString($row->product_list);
        } finally {
            CollectionModel::whereIn('shopify_collection_id', array_filter([$collectionA, $collectionB]))->delete();
            $this->cleanupProduct($productId);
            $this->cleanupCollection($collectionA);
            $this->cleanupCollection($collectionB);
        }
    }
}
