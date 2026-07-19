<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;
use Tests\ShopifySync\Support\ShopifyTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateInventoryFieldsTest extends LiveTestCase
{
    public function test_update_inventory_fields(): void
    {
        $sku = ShopifyTestData::uniqueSku('INV');
        $productId = null;

        try {
            $create = $this->graphql->create_simple_product(ShopifyTestData::simpleProductData($sku));
            $productId = $create['productId'];
            $variationId = $create['variantId'];

            $inv = $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variationId,
                'barcode' => 'BAR-'.$sku,
                'price' => '88.80',
                'compareAtPrice' => '108.80',
                'cost' => '44.00',
                'sku' => $sku,
            ]);
            ShopifyTestData::assertGraphqlOk($this, $inv, 'update_simple_product_inventory_data');

            $item = $this->graphql->inventory_item($variationId);
            $this->assertNotNull($item->data->productVariant ?? null);
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
