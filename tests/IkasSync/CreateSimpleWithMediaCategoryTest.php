<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateSimpleWithMediaCategoryTest extends LiveTestCase
{
    public function test_create_simple_with_media_and_category(): void
    {
        $sku = IkasTestData::uniqueSku('MEDIA');
        $productId = null;
        $categoryId = null;

        try {
            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
            $productId = $create['productId'];
            $variantId = $create['variantId'];

            $colResp = $this->graphql->create_category('Test Category '.time());
            IkasTestData::assertGraphqlOk($this, $colResp, 'create_category');
            $categoryId = $colResp['data']['saveCategory']['id'] ?? null;

            $addResp = $this->graphql->add_product_to_category((string) $categoryId, [$productId]);
            IkasTestData::assertGraphqlOk($this, $addResp, 'add_product_to_category');

            $mediaResp = $this->graphql->productCreateMedia($productId, IkasTestData::imageUrl());
            IkasTestData::assertGraphqlOk($this, $mediaResp, 'productCreateMedia');
            $this->assertNotEmpty($variantId);
        } finally {
            $this->cleanupProduct($productId);
            $this->cleanupCategory($categoryId);
        }
    }
}
