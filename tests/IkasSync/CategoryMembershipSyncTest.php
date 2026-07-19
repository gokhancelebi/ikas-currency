<?php

namespace Tests\IkasSync;

use App\Lib\IkasSync\CategorySyncService;
use App\Models\Collection as CollectionModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CategoryMembershipSyncTest extends LiveTestCase
{
    public function test_category_membership_sync(): void
    {
        $sku = IkasTestData::uniqueSku('COLSYNC');
        $productId = null;
        $categoryA = null;
        $categoryB = null;

        try {
            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
            $productId = $create['productId'];

            $colAResp = $this->graphql->create_category('Membership A '.time());
            IkasTestData::assertGraphqlOk($this, $colAResp, 'create_category A');
            $categoryA = $colAResp['data']['saveCategory']['id'] ?? null;

            $colBResp = $this->graphql->create_category('Membership B '.time());
            IkasTestData::assertGraphqlOk($this, $colBResp, 'create_category B');
            $categoryB = $colBResp['data']['saveCategory']['id'] ?? null;

            $this->graphql->add_product_to_category((string) $categoryA, [$productId]);

            CollectionModel::create([
                'name' => 'Test Collection',
                'ikas_category_id' => (string) $categoryA,
                'product_list' => json_encode([]),
                'active' => 'active',
            ]);

            app(CategorySyncService::class)->sync();

            $row = CollectionModel::where('ikas_category_id', $categoryA)->first();
            $productList = json_decode($row->product_list ?? '[]', true);
            $this->assertContains($productId, $productList);
        } finally {
            $this->cleanupProduct($productId);
            $this->cleanupCategory($categoryA);
            $this->cleanupCategory($categoryB);
            CollectionModel::whereIn('ikas_category_id', array_filter([$categoryA, $categoryB]))->delete();
        }
    }
}
