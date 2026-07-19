<?php

namespace Tests\IkasSync;

use App\Lib\IkasSync\CategorySyncService;
use App\Lib\IkasSync\IkasProductGraphQL;
use App\Models\Collection as CollectionModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class SubCategorySyncTest extends LiveTestCase
{
    public function test_subcategory_sync_and_membership(): void
    {
        $sku = IkasTestData::uniqueSku('SUBCAT');
        $productId = null;
        $parentId = null;
        $childId = null;
        $suffix = (string) time();

        try {
            $parentResp = $this->graphql->create_category('Parent '.$suffix);
            IkasTestData::assertGraphqlOk($this, $parentResp, 'create parent category');
            $parentId = $parentResp['data']['saveCategory']['id'] ?? null;

            $childResp = $this->graphql->create_category('Child '.$suffix, (string) $parentId);
            IkasTestData::assertGraphqlOk($this, $childResp, 'create child category');
            $childId = $childResp['data']['saveCategory']['id'] ?? null;

            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
            $productId = $create['productId'] ?? null;

            $this->graphql->add_product_to_category((string) $childId, [(string) $productId]);

            $categories = IkasProductGraphQL::indexCategoriesById($this->graphql->getAllCategories());
            $this->assertArrayHasKey((string) $childId, $categories);
            $this->assertSame((string) $parentId, $categories[(string) $childId]['parentId'] ?? null);

            app(CategorySyncService::class)->sync();

            $childRow = CollectionModel::where('ikas_category_id', (string) $childId)->first();
            $this->assertNotNull($childRow);
            $this->assertSame((string) $parentId, $childRow->ikas_parent_category_id);
            $this->assertStringContainsString('Parent '.$suffix, $childRow->name);
            $this->assertStringContainsString('Child '.$suffix, $childRow->name);

            $productList = json_decode($childRow->product_list ?? '[]', true);
            $this->assertContains($productId, $productList);
        } finally {
            $this->cleanupProduct($productId);
            $this->cleanupCategory($childId);
            $this->cleanupCategory($parentId);
            CollectionModel::whereIn('ikas_category_id', array_filter([(string) $parentId, (string) $childId]))->delete();
        }
    }
}
