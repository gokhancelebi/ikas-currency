<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;

/**
 * @group live
 */
#[Group('live')]
class ImportStartupSyncTest extends LiveTestCase
{
    public function test_import_startup_counts(): void
    {
        $count = $this->graphql->total_product_count();
        $products = $this->graphql->allProducts();

        $this->assertNotFalse($count);
        $this->assertCount($count, $products);

        $categories = $this->graphql->getAllCategories();
        $categoryCount = $this->graphql->categoriesCount();
        $this->assertSame($categoryCount, count($categories));
    }
}
