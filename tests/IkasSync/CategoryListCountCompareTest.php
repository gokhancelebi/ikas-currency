<?php

namespace Tests\IkasSync;

use PHPUnit\Framework\Attributes\Group;
use Tests\IkasSync\Support\IkasTestData;

/**
 * @group live
 */
#[Group('live')]
class CategoryListCountCompareTest extends LiveTestCase
{
    public function test_category_list_count_compare(): void
    {
        $createdIds = [];

        try {
            for ($i = 1; $i <= 2; $i++) {
                $createResponse = $this->graphql->create_category('Count Test '.$i.' '.time());
                IkasTestData::assertGraphqlOk($this, $createResponse, 'create_category '.$i);
                $createdIds[] = $createResponse['data']['saveCategory']['id'] ?? null;
            }

            $all = $this->graphql->getAllCategories();
            $count = $this->graphql->categoriesCount();
            $this->assertGreaterThanOrEqual(count($createdIds), $count);
            $this->assertGreaterThanOrEqual($count, count($all));
        } finally {
            foreach ($createdIds as $id) {
                $this->cleanupCategory($id);
            }
        }
    }
}
