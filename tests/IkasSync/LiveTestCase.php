<?php

namespace Tests\IkasSync;

use App\Lib\IkasSync\AuthTokenService;
use App\Lib\IkasSync\GraphqlClient;
use App\Lib\IkasSync\IkasProductGraphQL;
use Tests\TestCase;

abstract class LiveTestCase extends TestCase
{
    protected IkasProductGraphQL $graphql;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('ikas.live_tests')) {
            $this->markTestSkipped('Set IKAS_LIVE_TESTS=true to run live İkas tests.');
        }

        if (empty(config('ikas.client_id')) || empty(config('ikas.client_secret'))) {
            $this->markTestSkipped('IKAS_CLIENT_ID and IKAS_CLIENT_SECRET are required.');
        }

        $this->graphql = new IkasProductGraphQL(new GraphqlClient(new AuthTokenService()));
    }

    protected function cleanupProduct(?string $productId): void
    {
        if ($productId) {
            $this->graphql->product_delete($productId);
        }
    }

    protected function cleanupCategory(?string $categoryId): void
    {
        if ($categoryId) {
            $this->graphql->categoryDelete($categoryId);
        }
    }

    protected function publishProduct(string $productId): void
    {
        $this->graphql->publish_product_all($productId);
    }
}
