<?php

namespace App\Lib\IkasSync;

use App\Models\Collection as CollectionModel;

class CategorySyncService
{
    public function __construct(
        private IkasProductGraphQL $graphql
    ) {
    }

    public function sync(): void
    {
        $categories = $this->graphql->getAllCategories();
        $categoriesById = IkasProductGraphQL::indexCategoriesById($categories);

        foreach ($categories as $category) {
            $categoryId = (string) $category['id'];
            $displayName = IkasProductGraphQL::categoryDisplayName($category, $categoriesById);
            $parentId = $category['parentId'] ?? null;
            $collectionModel = CollectionModel::where('ikas_category_id', $categoryId)->first();

            if ($collectionModel && $collectionModel->active == 'active') {
                $productsInCategory = $this->graphql->getProductsInCategory($categoryId);
                $productIds = array_column($productsInCategory, 'id');
                $collectionModel->update([
                    'name' => $displayName,
                    'ikas_parent_category_id' => $parentId,
                    'product_list' => json_encode($productIds),
                ]);

                continue;
            }

            if ($collectionModel && $collectionModel->active == 'passive') {
                continue;
            }

            CollectionModel::create([
                'name' => $displayName,
                'ikas_category_id' => $categoryId,
                'ikas_parent_category_id' => $parentId,
                'product_list' => json_encode([]),
                'active' => 'active',
            ]);
        }
    }
}
