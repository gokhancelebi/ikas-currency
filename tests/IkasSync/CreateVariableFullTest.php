<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateVariableFullTest extends LiveTestCase
{
    public function test_create_variable_full(): void
    {
        $sku1 = IkasTestData::uniqueSku('VARA');
        $sku2 = IkasTestData::uniqueSku('VARB');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_product(
                IkasTestData::variableProductData($sku1, $sku2)
            );
            $productId = $create['productId'] ?? null;
            $this->assertNotEmpty($productId);

            $product = $this->graphql->get_product_by_id((string) $productId);
            $this->assertNotNull(IkasTestData::findVariantBySku($product, $sku1));
            $this->assertNotNull(IkasTestData::findVariantBySku($product, $sku2));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
