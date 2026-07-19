<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateTagsTest extends LiveTestCase
{
    public function test_update_tags(): void
    {
        $sku = IkasTestData::uniqueSku('TAGS');
        $productId = null;
        $tag = IkasTestData::productTag();

        try {
            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
            $productId = $create['productId'] ?? null;

            $tagResp = $this->graphql->update_product_tags($productId, [$tag, 'LiveTest']);
            IkasTestData::assertGraphqlOk($this, $tagResp, 'update_product_tags');

            $product = $this->graphql->get_product_by_id((string) $productId);
            $tagNames = array_column($product['tags'] ?? [], 'name');
            $this->assertContains($tag, $tagNames);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
