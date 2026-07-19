<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class UpdateInventoryFieldsTest extends LiveTestCase
{
    public function test_update_inventory_fields(): void
    {
        $sku = IkasTestData::uniqueSku('INV');
        $productId = null;

        try {
            $create = $this->graphql->create_simple_product(IkasTestData::simpleProductData($sku));
            $productId = $create['productId'];
            $variantId = $create['variantId'];

            $inv = $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variantId,
                'barcode' => $sku,
                'price' => '88.80',
                'compareAtPrice' => '108.80',
                'cost' => '44.40',
                'sku' => $sku,
            ]);
            IkasTestData::assertGraphqlOk($this, $inv, 'update_simple_product_inventory_data');
        } finally {
            $this->cleanupProduct($productId);
        }
    }
}
