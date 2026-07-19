<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CreateSimpleFullTest extends LiveTestCase
{
    public function test_create_simple_full(): void
    {
        $sku = IkasTestData::uniqueSku('SIMPLE');
        $productId = null;

        try {
            $productData = IkasTestData::simpleProductData($sku);
            $create = $this->graphql->create_simple_product($productData);
            $productId = $create['productId'];
            $variationId = $create['variantId'];

            $inv = $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variationId,
                'barcode' => $sku,
                'price' => '99.90',
                'compareAtPrice' => '129.90',
                'cost' => '50.00',
                'sku' => $sku,
            ]);
            IkasTestData::assertGraphqlOk($this, $inv, 'update_simple_product_inventory_data');

            $this->graphql->update_variation_stock($variationId, 8, $productId);
            $priceResp = $this->graphql->update_multiple_variation_prices($productId, [[
                'variantId' => $variationId,
                'sellPrice' => '109.90',
                'discountPrice' => '139.90',
            ]]);
            IkasTestData::assertGraphqlOk($this, $priceResp, 'update_multiple_variation_prices');

            $product = $this->graphql->get_product_by_id($productId);
            $variant = IkasTestData::findVariantBySku($product, $sku);
            $this->assertNotNull($variant);
            $this->assertEquals('109.90', IkasTestData::variantSellPrice($variant));
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
