<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateMultiVariantStockAndPriceTest extends LiveTestCase
{
    public function test_update_multi_variant_stock_and_price(): void
    {
        $sku1 = IkasTestData::uniqueSku('MV1');
        $sku2 = IkasTestData::uniqueSku('MV2');
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
                ['variantId' => $v1['id'], 'sellPrice' => '95.00', 'discountPrice' => '115.00'],
                ['variantId' => $v2['id'], 'sellPrice' => '97.00', 'discountPrice' => '117.00'],
            ]);
            $this->graphql->update_variation_stock($v1['id'], 11, $productId);
            $this->graphql->update_variation_stock($v2['id'], 12, $productId);

            $updated = $this->graphql->get_product_by_id((string) $productId);
            $this->assertEquals('95.00', IkasTestData::variantSellPrice(IkasTestData::findVariantBySku($updated, $sku1)));
            $this->assertEquals('97.00', IkasTestData::variantSellPrice(IkasTestData::findVariantBySku($updated, $sku2)));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
