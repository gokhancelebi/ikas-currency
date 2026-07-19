<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;

/**
 * @group live
 */
#[Group('live')]
class DeleteScanDryRunTest extends LiveTestCase
{
    public function test_delete_scan_dry_run(): void
    {
        $products = $this->graphql->allProducts();
        $this->assertIsArray($products);
    }
}
