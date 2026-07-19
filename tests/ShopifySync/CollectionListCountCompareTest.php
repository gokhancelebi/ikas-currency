<?php

namespace Tests\ShopifySync;

use App\Lib\ShopifySync\SyncStorage;
use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class CollectionListCountCompareTest extends LiveTestCase
{
    public function test_collection_list_count_compare(): void
    {
        $createdIds = [];
        $stamp = time();

        try {
            $countBefore = $this->graphql->collectionsCount();
            $listBefore = $this->graphql->getAllCollections();
            $restCountBefore = $this->graphql->restCustomCollectionsCount();

            if ($countBefore !== false) {
                $this->assertEquals($countBefore, count($listBefore));
            }

            if ($countBefore !== false && $restCountBefore !== false) {
                $this->assertEquals($restCountBefore, $countBefore);
            }

            for ($i = 1; $i <= 2; $i++) {
                $title = 'TEST-LIST-'.$i.'-'.$stamp;
                $createResponse = $this->graphql->create_collection($title);
                ShopifyTestData::assertGraphqlOk($this, $createResponse, 'create_collection '.$i);
                $collectionId = $createResponse['data']['collectionCreate']['collection']['id'] ?? null;
                $this->assertNotEmpty($collectionId);
                $createdIds[] = $collectionId;
            }

            sleep(2);

            $countAfter = $this->graphql->collectionsCount();
            $listAfter = $this->graphql->getAllCollections();
            $restCountAfter = $this->graphql->restCustomCollectionsCount();

            if ($countBefore !== false && $countAfter !== false) {
                $this->assertEquals(count($createdIds), $countAfter - $countBefore);
            }

            if ($countAfter !== false) {
                $this->assertEquals($countAfter, count($listAfter));
            }

            if ($countAfter !== false && $countAfter > 0) {
                $this->assertNotEmpty($listAfter, 'getAllCollections must not be empty when count > 0');
            }

            foreach ($createdIds as $collectionId) {
                $found = false;
                foreach ($listAfter as $collection) {
                    if (($collection['id'] ?? '') === $collectionId) {
                        $found = true;
                        break;
                    }
                }
                $this->assertTrue($found, 'Created collection must appear in getAllCollections');
            }

            SyncStorage::writeJson('responses/collection_compare_report.json', [
                'before' => ['graphql_count' => $countBefore, 'rest_count' => $restCountBefore, 'list_count' => count($listBefore)],
                'after' => ['graphql_count' => $countAfter, 'rest_count' => $restCountAfter, 'list_count' => count($listAfter)],
                'created_ids' => $createdIds,
            ]);
        } finally {
            foreach ($createdIds as $collectionId) {
                $this->cleanupCollection($collectionId);
            }
        }
    }
}
