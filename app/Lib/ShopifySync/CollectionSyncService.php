<?php

namespace App\Lib\ShopifySync;

use App\Models\Collection as CollectionModel;

class CollectionSyncService
{
    public function __construct(
        private ShopifyProductGraphQL $graphql
    ) {
    }

    public function sync(): void
    {
        $collections = $this->graphql->getAllCollections();

        foreach ($collections as $collection) {
            $collectionModel = CollectionModel::where('shopify_collection_id', (string) $collection['id'])->first();

            if ($collectionModel && $collectionModel->active == 'active') {
                $productsInCollection = $this->graphql->add_product_to_colletion($collection['id'], []);
                if (isset($productsInCollection['data']['collectionAddProducts']['collection']['products']['nodes'])) {
                    $productIds = array_column(
                        $productsInCollection['data']['collectionAddProducts']['collection']['products']['nodes'],
                        'id'
                    );
                    $collectionModel->update([
                        'product_list' => json_encode($productIds),
                    ]);
                }

                continue;
            }

            if ($collectionModel && $collectionModel->active == 'passive') {
                continue;
            }

            CollectionModel::create([
                'name' => $collection['title'],
                'shopify_collection_id' => (string) $collection['id'],
                'product_list' => json_encode([]),
                'active' => 'active',
            ]);
        }
    }
}
