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
class CategorySyncLiveTest extends LiveTestCase
{
    public function test_category_sync_live(): void
    {
        $categoryId = null;

        $createdCategoryId = null;

        try {
            $createResponse = $this->graphql->create_category('Sync Test Category '.time());
            IkasTestData::assertGraphqlOk($this, $createResponse, 'create_category');
            $categoryId = $createResponse['data']['saveCategory']['id'] ?? null;
            $createdCategoryId = (string) $categoryId;
            $this->assertNotEmpty($categoryId);

            app(CategorySyncService::class)->sync();

            $dbRow = CollectionModel::where('ikas_category_id', $createdCategoryId)->first();
            $this->assertNotNull($dbRow);
            $this->assertEquals($createResponse['data']['saveCategory']['name'], $dbRow->name);
        } finally {
            if ($createdCategoryId !== null) {
                CollectionModel::where('ikas_category_id', $createdCategoryId)->delete();
            }
            $this->cleanupCategory($categoryId);
        }
    }
}
