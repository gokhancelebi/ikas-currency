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

        foreach ($categories as $category) {
            $collectionModel = CollectionModel::where('ikas_category_id', (string) $category['id'])->first();

            if ($collectionModel && $collectionModel->active == 'active') {
                $productsInCategory = $this->graphql->getProductsInCategory((string) $category['id']);
                $productIds = array_column($productsInCategory, 'id');
                $collectionModel->update([
                    'product_list' => json_encode($productIds),
                ]);

                continue;
            }

            if ($collectionModel && $collectionModel->active == 'passive') {
                continue;
            }

            CollectionModel::create([
                'name' => $category['name'],
                'ikas_category_id' => (string) $category['id'],
                'product_list' => json_encode([]),
                'active' => 'active',
            ]);
        }
    }
}
