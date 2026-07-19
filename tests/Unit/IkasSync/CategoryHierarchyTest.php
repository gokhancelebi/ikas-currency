<?php

namespace Tests\Unit\IkasSync;

use App\Lib\IkasSync\IkasProductGraphQL;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    public function test_category_display_name_includes_parent(): void
    {
        $categoriesById = [
            'parent-1' => ['id' => 'parent-1', 'name' => 'Accessories', 'categoryPath' => []],
            'child-1' => [
                'id' => 'child-1',
                'name' => 'Organizers',
                'parentId' => 'parent-1',
                'categoryPath' => ['parent-1'],
            ],
        ];

        $this->assertSame(
            'Accessories > Organizers',
            IkasProductGraphQL::categoryDisplayName($categoriesById['child-1'], $categoriesById)
        );
    }

    public function test_build_product_category_input_includes_path_for_subcategory(): void
    {
        $categoriesById = [
            'parent-1' => ['id' => 'parent-1', 'name' => 'Accessories', 'categoryPath' => []],
            'child-1' => [
                'id' => 'child-1',
                'name' => 'Organizers',
                'parentId' => 'parent-1',
                'categoryPath' => ['parent-1'],
            ],
        ];

        $this->assertSame(
            ['name' => 'Organizers', 'path' => ['Accessories']],
            IkasProductGraphQL::buildProductCategoryInput($categoriesById['child-1'], $categoriesById)
        );
    }

    public function test_build_product_category_input_omits_path_for_root_category(): void
    {
        $categoriesById = [
            'parent-1' => ['id' => 'parent-1', 'name' => 'Accessories', 'categoryPath' => []],
        ];

        $this->assertSame(
            ['name' => 'Accessories'],
            IkasProductGraphQL::buildProductCategoryInput($categoriesById['parent-1'], $categoriesById)
        );
    }
}
