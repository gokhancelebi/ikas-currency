<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class DeleteVariantAndProductTest extends LiveTestCase
{
    public function test_delete_variant_and_product(): void
    {
        $sku1 = IkasTestData::uniqueSku('DEL1');
        $sku2 = IkasTestData::uniqueSku('DEL2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_product(
                IkasTestData::variableProductData($sku1, $sku2)
            );
            $productId = $create['productId'];

            $product = $this->graphql->get_product_by_id((string) $productId);
            $v2 = IkasTestData::findVariantBySku($product, $sku2);
            $this->assertNotNull($v2);

            $delVariant = $this->graphql->delete_product_variant((string) $productId, (string) $v2['id']);
            IkasTestData::assertGraphqlOk($this, $delVariant, 'delete_product_variant');

            $afterDeleteVariant = $this->graphql->get_product_by_id((string) $productId);
            $this->assertNull(IkasTestData::findVariantBySku($afterDeleteVariant, $sku2));

            $this->graphql->product_delete((string) $productId);
            $productId = null;
            $this->assertNull($this->graphql->get_product_by_id((string) $create['productId']));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
