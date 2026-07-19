<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;
use Tests\TestCase;

/**
 * @group live
 */
#[Group('live')]
class ImportStartupSyncTest extends LiveTestCase
{
    public function test_all_products_match_rest_count(): void
    {
        $products = $this->graphql->allProducts();
        $this->assertIsArray($products);
        $this->assertGreaterThanOrEqual(0, count($products));

        $apiCount = $this->graphql->total_product_count();
        $this->assertNotFalse($apiCount);
        $this->assertEquals($apiCount, count($products), 'GraphQL product list vs REST count');

        $collections = $this->graphql->getAllCollections();
        $this->assertIsArray($collections);

        $collectionCount = $this->graphql->collectionsCount();
        if ($collectionCount !== false) {
            $this->assertEquals($collectionCount, count($collections), 'Manual collection count vs list');
        }
    }
}
