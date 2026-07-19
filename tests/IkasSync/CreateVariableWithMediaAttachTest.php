<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateVariableWithMediaAttachTest extends LiveTestCase
{
    public function test_create_variable_with_media(): void
    {
        $sku1 = IkasTestData::uniqueSku('MEDV1');
        $sku2 = IkasTestData::uniqueSku('MEDV2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_product(
                IkasTestData::variableProductData($sku1, $sku2)
            );
            $productId = $create['productId'] ?? null;

            $product = $this->graphql->get_product_by_id((string) $productId);
            $variant = IkasTestData::findVariantBySku($product, $sku1);
            $this->assertNotNull($variant);

            $attach = $this->graphql->attach_media_to_variant(
                (string) $productId,
                (string) $variant['id'],
                IkasTestData::imageUrl()
            );
            IkasTestData::assertGraphqlOk($this, $attach, 'attach_media_to_variant');
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
