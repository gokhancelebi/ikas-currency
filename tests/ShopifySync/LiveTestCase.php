<?php

namespace Tests\ShopifySync;

use App\Lib\ShopifySync\GraphqlClient;
use App\Lib\ShopifySync\ShopifyProductGraphQL;
use Tests\TestCase;

abstract class LiveTestCase extends TestCase
{
    protected ShopifyProductGraphQL $graphql;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('shopify.live_tests')) {
            $this->markTestSkipped('Set SHOPIFY_LIVE_TESTS=true to run live Shopify tests.');
        }

        if (empty(config('shopify.access_token')) || empty(config('shopify.store_domain'))) {
            $this->markTestSkipped('SHOPIFY_ACCESS_TOKEN and SHOPIFY_STORE_DOMAIN are required.');
        }

        $this->graphql = new ShopifyProductGraphQL(new GraphqlClient());
    }

    protected function cleanupProduct(?string $productId): void
    {
        if ($productId) {
            $this->graphql->product_delete($productId);
        }
    }

    protected function cleanupCollection(?string $collectionId): void
    {
        if ($collectionId) {
            $this->graphql->collectionDelete($collectionId);
        }
    }

    protected function publishProduct(string $productId): void
    {
        $this->graphql->publish_product_all($productId);
    }
}
