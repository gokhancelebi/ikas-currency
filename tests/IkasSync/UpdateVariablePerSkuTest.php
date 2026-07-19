<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateVariablePerSkuTest extends LiveTestCase
{
    public function test_update_variable_per_sku(): void
    {
        $sku1 = IkasTestData::uniqueSku('SKU1');
        $sku2 = IkasTestData::uniqueSku('SKU2');
        $productId = null;

        try {
            $create = $this->graphql->create_variable_product(
                IkasTestData::variableProductData($sku1, $sku2)
            );
            $productId = $create['productId'];

            $product = $this->graphql->get_product_by_id((string) $productId);
            $v1 = IkasTestData::findVariantBySku($product, $sku1);
            $v2 = IkasTestData::findVariantBySku($product, $sku2);

            $this->graphql->update_multiple_variation_prices($productId, [
                ['variantId' => $v1['id'], 'sellPrice' => '80.00'],
            ]);

            $after = $this->graphql->get_product_by_id((string) $productId);
            $this->assertEquals('80.00', IkasTestData::variantSellPrice(IkasTestData::findVariantBySku($after, $sku1)));
            $this->assertEquals('91.90', IkasTestData::variantSellPrice(IkasTestData::findVariantBySku($after, $sku2)));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
