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
class CollectionSyncLiveTest extends LiveTestCase
{
    public function test_collection_sync_live(): void
    {
        $createdCollectionId = null;

        try {
            $manualCollections = $this->graphql->getAllCollections();
            $manualCount = $this->graphql->collectionsCount();
            $this->assertIsArray($manualCollections);

            if ($manualCount !== false) {
                $this->assertEquals($manualCount, count($manualCollections));
            }

            $smartCollection = $this->graphql->getFirstSmartCollection();
            if ($smartCollection !== null) {
                $inManual = false;
                foreach ($manualCollections as $collection) {
                    if (($collection['id'] ?? '') === $smartCollection['id']) {
                        $inManual = true;
                        break;
                    }
                }
                $this->assertFalse($inManual, 'Smart collection must not appear in manual list');
            }

            $title = 'TEST-SYNC-'.time();
            $createResponse = $this->graphql->create_collection($title);
            ShopifyTestData::assertGraphqlOk($this, $createResponse, 'collectionCreate');
            $createdCollectionId = $createResponse['data']['collectionCreate']['collection']['id'] ?? null;
            $this->assertNotEmpty($createdCollectionId);

            sleep(2);

            app(CollectionSyncService::class)->sync();

            $dbRow = CollectionModel::where('shopify_collection_id', $createdCollectionId)->first();
            $this->assertNotNull($dbRow);
            $this->assertEquals('active', $dbRow->active);
            $this->assertEquals($title, $dbRow->name);

            $detail = $this->graphql->getCollectionById($createdCollectionId);
            $this->assertNotNull($detail);
            $this->assertEmpty($detail['ruleSet']['rules'] ?? []);
        } finally {
            if ($createdCollectionId) {
                CollectionModel::where('shopify_collection_id', $createdCollectionId)->delete();
                $this->cleanupCollection($createdCollectionId);
            }
        }
    }
}
