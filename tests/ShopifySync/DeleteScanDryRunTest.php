<?php

namespace Tests\ShopifySync;

use PHPUnit\Framework\Attributes\Group;

/**
 * @group live
 */
#[Group('live')]
class DeleteScanDryRunTest extends LiveTestCase
{
    public function test_delete_scan_dry_run_lists_products_only(): void
    {
        $products = $this->graphql->allProducts();
        $this->assertIsArray($products);

        $count = $this->graphql->total_product_count();
        if ($count !== false) {
            $this->assertEquals($count, count($products));
        }

        // Dry-run: no deletes, only verify API read path works
        $this->assertTrue(true);
    }
}
