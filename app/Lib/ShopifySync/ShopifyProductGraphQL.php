<?php

namespace App\Lib\ShopifySync;

use Exception;

class ShopifyProductGraphQL
{
    public function __construct(
        private GraphqlClient $client
    ) {
    }

    function allProducts()
    {
        $products = [];
        $cursor = null;

        $page = 1;
        while (true) {
            if (function_exists('import_running_heartbeat')) {
                import_running_heartbeat();
            }
            echo 'Fetching products ' . $page++ . PHP_EOL;
            SyncStorage::write('page.txt', (string) $page);
            $productsData = $this->products_query(250, $cursor);
            $productData_ = array_map(function ($product) {
                return $product['node'];
            }, $productsData['data']['products']['edges']);
            $products = array_merge($products, $productData_);
            if ($productsData['data']['products']['pageInfo']['hasNextPage'] == false) {
                echo 'All products fetched' . PHP_EOL;
                break;
            }
            if (isset(end($productsData['data']['products']['edges'])['cursor']))
                $cursor = end($productsData['data']['products']['edges'])['cursor'];
            else
                break;
        }

        SyncStorage::writeJson('products.json', $products);

        return $products;
    }

    /**
     * Get product by id from Shopify like products_query funciton but only get one product
     * @param string $product_id
     * @return array
     */
    public function get_product_by_id_from_shopify($product_id)
    {
        $query = <<<'QUERY'
            query product($id: ID!) { product(id: $id) { id title description status totalVariants availablePublicationsCount { count } images(first: 5) { edges { node { id src } } } tags variants(first: 100) { edges { node { id title price sku barcode image { id src } compareAtPrice inventoryQuantity selectedOptions { name value } } } } } }
            QUERY;
        $variables = [
            'id' => $product_id,
        ];
        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/product_by_id_from_shopify.json', $data);
        return $data;
    }

    /**
     * Mağaza toplam ürün adedi — GraphQL productsCount(limit: null).
     * allProducts() zaten sayfalı çeker; bu yalnızca import başındaki sayım doğrulaması içindir.
     * GraphQL başarısız olursa REST fallback: GET /products/count.json
     */
    function total_product_count()
    {
        $query = 'query { productsCount(limit: null) { count precision } }';
        $response = $this->client->request('{"query": "' . $query . '"}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/total_product_count.json', $data);

        $count = $this->parseGraphqlCountField($data, 'productsCount');
        if ($count !== false) {
            return $count;
        }

        if (!empty($data['errors'])) {
            GraphqlClient::logProblem('productsCount GraphQL hatası, REST fallback deneniyor', [
                'errors' => $data['errors'],
            ]);
        }

        return $this->restProductCount();
    }

    /** REST ürün sayısı — GraphQL count fallback */
    public function restProductCount()
    {
        $url = rtrim($this->client->storeDomain(), '/') . '/admin/api/' . $this->client->apiVersion() . '/products/count.json';
        $raw = $this->client->rest($url, [], 'GET');

        if ($raw === false || $raw === '') {
            GraphqlClient::logProblem('products/count.json boş yanıt döndü', ['url' => $url]);
            return false;
        }

        $response = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            GraphqlClient::logProblem('products/count.json JSON parse hatası', [
                'url' => $url,
                'json_error' => json_last_error_msg(),
                'raw' => $raw,
            ]);
            return false;
        }

        if (isset($response->errors)) {
            GraphqlClient::logProblem('Shopify API hata yanıtı (products/count)', [
                'url' => $url,
                'errors' => $response->errors,
            ]);
            return false;
        }

        if (!isset($response->count) || !is_numeric($response->count)) {
            GraphqlClient::logProblem('products/count.json beklenen count alanı yok veya sayı değil', [
                'url' => $url,
                'response' => $response,
            ]);
            return false;
        }

        return (int) $response->count;
    }

    /** GraphQL Count nesnesinden sayıyı çıkarır */
    private function parseGraphqlCountField(?array $data, string $field): int|false
    {
        if (!is_array($data) || !empty($data['errors'])) {
            return false;
        }

        $block = $data['data'][$field] ?? null;
        if (!is_array($block) || !isset($block['count']) || !is_numeric($block['count'])) {
            return false;
        }

        return (int) $block['count'];
    }

    function products_query($first, $after = null)
    {
        $variables = [
            'first' => $first,
            'after' => $after,
        ];

        // Sync cron needs full product/variant fields (images, titles, options)
        $query = <<<'QUERY'
                    query ($first: Int!, $after: String) {
                        products(first: $first, after: $after) {
                            pageInfo {
                                hasNextPage
                            }
                            edges {
                                cursor
                                node {
                                    id
                                    title
                                    description
                                    status
                                    totalVariants
                                    availablePublicationsCount {
                                        count
                                    }
                                    images(first: 5) {
                                        edges {
                                            node {
                                                id
                                                src
                                            }
                                        }
                                    }
                                    tags
                                    variants(first: 100) {
                                        edges {
                                            node {
                                                id
                                                title
                                                price
                                                sku
                                                barcode
                                                image {
                                                    id
                                                    src
                                                }
                                                compareAtPrice
                                                inventoryQuantity
                                                selectedOptions {
                                                    name
                                                    value
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
            QUERY;

        $query = $this->filter_query($query);

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        SyncStorage::write('responses/res.txt', $response);
        SyncStorage::writeJson('responses/products-page.json', json_decode($response, true));

        $data = json_decode($response, true);

        return $data;
    }

    function product_delete($product_id)
    {
        $query = 'mutation productDelete($input: ProductDeleteInput!, $synchronous: Boolean!) { productDelete(synchronous: $synchronous, input: $input) { deletedProductId productDeleteOperation { id status deletedProductId } } }';

        $variables = [
            'synchronous' => false,
            'input' => [
                'id' => $product_id
            ]
        ];

        $delete_product_variant_response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        $delete_product_variant_response = json_decode($delete_product_variant_response);

        SyncStorage::writeJson('responses/delete_response.json', $delete_product_variant_response);

        return true;
    }

    function update_product_tags($product_id, $tags = [])
    {
        $query = 'mutation productUpdateTags($product: ProductUpdateInput!) { productUpdate(product: $product) { product { id tags } userErrors { field message } } }';

        $variables = [
            'product' => [
                'id' => $product_id,
                'tags' => $tags,
            ],
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/update_product_tags.json', $data);

        return $data;
    }

    /** Ürün durumu: ACTIVE, DRAFT, ARCHIVED */
    function update_product_status($product_id, $status)
    {
        $query = 'mutation productUpdateStatus($product: ProductUpdateInput!) { productUpdate(product: $product) { product { id status } userErrors { field message } } }';

        $variables = [
            'product' => [
                'id' => $product_id,
                'status' => $status,
            ],
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/update_product_status.json', $data);

        return $data;
    }

    function create_simple_product($inputs)
    {
        $productQuery = <<<'GRAPHQL'
            mutation CreateProductWithOptions($product: ProductCreateInput!) { productCreate(product: $product) { userErrors { field message } product { id options { id name position values optionValues { id name hasVariants } } variants(first: 5 ) { nodes { id title selectedOptions { name value } } } } } }
            GRAPHQL;

        $product_input = $inputs;

        if (isset($product_input['inventory_data'])) {
            unset($product_input['inventory_data']);
        }

        $variables = [
            'product' => $product_input,
        ];

        $productResponse = $this->client->request('{"query": "' . $productQuery . '", "variables": ' . json_encode($variables) . '}');
        $productData = json_decode($productResponse, true);

        //        SyncStorage::writeJson('responses/product_simple.json', $productData);

        $productId = $productData['data']['productCreate']['product']['id'] ?? null;
        $productVariantId = $productData['data']['productCreate']['product']['variants']['nodes'][0]['id'] ?? null;

        $inputs['inventory_data']['variantId'] = $productVariantId;

        return [
            'productId' => $productId,
            'variantId' => $productVariantId,
        ];
    }

    function update_simple_product_inventory_data($productId, $inventory_data)
    {
        $inventory_data_query = <<<'GRAPHQL'
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $productId, variants: $variants) { product { id } productVariants { id metafields(first: 2) { edges { node { namespace key value } } } } userErrors { field message } } }
            GRAPHQL;

        $variables = [
            'productId' => $productId,
            'variants' => [
                [
                    'id' => $inventory_data['variantId'],
                    'barcode' => $inventory_data['barcode'],
                    'price' => $inventory_data['price'],
                    'compareAtPrice' => $inventory_data['compareAtPrice'],
                    'inventoryItem' => [
                        'cost' => $inventory_data['cost'],
                        'sku' => $inventory_data['sku'],
                        'tracked' => true,
                    ]
                ],
            ],
        ];

        $productVariantSKUResponse = $this->client->request('{"query": "' . $inventory_data_query . '", "variables": ' . json_encode($variables) . '}');
        $productVariantSKUData = json_decode($productVariantSKUResponse, true);

        SyncStorage::writeJson('responses/update_simple_product_inventory_data.json', $productVariantSKUData);

        return $productVariantSKUData;
    }

    function delete_product_variant($productID, $variantID)
    {
        $delete_product_variant_query = 'mutation productVariantsBulkDelete($productId: ID!, $variantsIds: [ID!]!) { productVariantsBulkDelete(productId: $productId, variantsIds: $variantsIds) { product { id } userErrors { field message } } }';

        $variables = [
            'productId' => $productID,
            'variantsIds' => [$variantID],
        ];

        $delete_product_variant_response = $this->client->request('{"query": "' . $delete_product_variant_query . '", "variables": ' . json_encode($variables) . '}');

        $delete_product_variant_data = json_decode($delete_product_variant_response, true);

        SyncStorage::writeJson('responses/delete-product-variant.json', $delete_product_variant_data);

        return $delete_product_variant_data;
    }

    function update_product_variants($productID, $variantData, $variations_shopify_response)
    {
        echo 'Varyasyon güncelleme başladı' . PHP_EOL;

        $not_found_variations = $variantData;

        SyncStorage::writeJson('responses/update_variant_data.json', $variantData);

        // shopify response
        SyncStorage::writeJson('responses/update_variations.json', $variations_shopify_response);

        foreach ($variantData as $key => $variant) {
            $optionNames = array_map(function ($option) {
                // echo $option['name'] . ':' . $option['optionName'] . PHP_EOL;
                return trim($option['name']) . ':' . trim($option['optionName']);
            }, $variant['options']);

            foreach ($variations_shopify_response as $variants) {
                $variantOptions = array_map(function ($variation) {
                    // echo $variation['name'] . ':' . $variation['value'] . PHP_EOL;
                    return trim($variation['name']) . ':' . trim($variation['value']);
                }, $variants['selectedOptions']);

                if (count(array_diff($optionNames, $variantOptions)) == 0) {
                    unset($not_found_variations[$key]);
                } else {
                    continue;
                }

                $variables = [
                    'productId' => $productID,
                    'variants' => [
                        [
                            'id' => $variants['id'],
                            'barcode' => $variant['barcode'],
                            'price' => $variant['price'],
                            'compareAtPrice' => $variant['compareAtPrice'],
                            'inventoryItem' => [
                                'cost' => $variant['cost'],
                                'sku' => $variant['sku'],
                                'tracked' => true,
                            ],
                            //                    'optionValues' => $variations[$key]['options']
                        ],
                    ],
                ];

                $productVariantSKUUQuery = <<<'GRAPHQL'
                    mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $productId, variants: $variants) { product { id } productVariants { id metafields(first: 2) { edges { node { namespace key value } } } } userErrors { field message } } }
                    GRAPHQL;

                $productVariantSKUResponse = $this->client->request('{"query": "' . $productVariantSKUUQuery . '", "variables": ' . json_encode($variables) . '}');
                $productVariantSKUData = json_decode($productVariantSKUResponse, true);

                SyncStorage::writeJson('responses/product-variant-update-sku.json', $productVariantSKUData);

                $this->update_variation_stock($variants['id'], $variant['inventoryQuantity']);

                if (isset($productVariantSKUData['data']['productVariantUpdate']['productVariant'])) {
                    // echo "Product Variant SKU updated to: {$productVariantSKUData['data']['productVariantUpdate']['productVariant']['sku']}\n";
                } else {
                    // echo "Failed to update product variant SKU: " . json_encode($productVariantSKUData, JSON_PRETTY_PRINT) . "\n";
                }
            }
        }

        SyncStorage::writeJson('responses/not-found-variations.json', $not_found_variations);

        foreach ($not_found_variations as $not_found_variaion) {
            echo 'Varyasyon siliniyor: ' . $not_found_variaion['id'] . PHP_EOL;
            $this->delete_product_variant($productID, $not_found_variaion['id']);
        }
    }

    function create_variable_productv3($product_input)
    {
        SyncStorage::writeJson('responses/create_variable_productv3_input.json', $product_input);

        $query = <<<'GRAPHQL'
            mutation productCreate($product: ProductCreateInput!) { productCreate(product: $product) { userErrors { field message } product { id title descriptionHtml options { id name optionValues { id name } } variants(first: 20) { nodes { id title selectedOptions { name value } } } } } }
            GRAPHQL;

        $inputs = [
            'title' => $product_input['title'],
            'descriptionHtml' => $product_input['descriptionHtml'],
            'productOptions' => $product_input['inventory_data']['options'],
        ];

        if (!empty($product_input['productType'])) {
            $inputs['productType'] = $product_input['productType'];
        }
        if (!empty($product_input['vendor'])) {
            $inputs['vendor'] = $product_input['vendor'];
        }

        $variables = [
            'product' => $inputs,
        ];

        $productResponse = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $productData = json_decode($productResponse, true);

        SyncStorage::writeJson('responses/product_create_with_options_v3.json', $productData);

        $productId = $productData['data']['productCreate']['product']['id'] ?? null;
        $productVariantId = $productData['data']['productCreate']['product']['variants']['nodes'][0]['id'] ?? null;

        $inputs['inventory_data']['variantId'] = $productVariantId;

        $product_variants = $this->create_product_variant_bulk_craete_v1($productId, $product_input['inventory_data']['variations']);

        $this->update_product_variants($productId, $product_input['inventory_data']['variations'], $product_variants['data']['productVariantsBulkCreate']['productVariants']);

        return [
            'productId' => $productId, 
        ];
    }

    function create_product_variant_bulk_craete_v1($productId, $variant_data)
    {
        SyncStorage::writeJson('responses/create_product_variant_bulk_create_input.json', $variant_data);

        /*
         * example variants
         *
         *         [
         *   [
         *     'optionValues' => [
         *       [
         *         'optionName' => 'Renk',
         *         'name' => 'Açık Turuncu'
         *       ],
         *       [
         *         'optionName' => 'Ölçü',
         *         'name' => 'Standart'
         *       ]
         *     ]
         *   ],
         *   [
         *     'optionValues' => [
         *       [
         *         'optionName' => 'Renk',
         *         'name' => 'Kırmızı'
         *       ],
         *       [
         *         'optionName' => 'Ölçü',
         *         'name' => 'Standart'
         *       ]
         *     ]
         *   ]
         * ]
         */

        $query = <<<'GRAPHQL'
            mutation ProductVariantsBulkCreate ($productId: ID!,$strategy:ProductVariantsBulkCreateStrategy! $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkCreate(productId: $productId, strategy: $strategy, variants: $variants) { productVariants { id title selectedOptions { name value } } userErrors { field message } } }
            GRAPHQL;

        $variants = [];
        foreach ($variant_data as $variant) {
            $row = [];
            $row['optionValues'] = [];

            $row['price'] = $variant['price'];
            $row['barcode'] = $variant['barcode'];
            $row['inventoryItem'] = [
                'cost' => $variant['cost'],
                'tracked' => true,
            ];
            $row['compareAtPrice'] = $variant['compareAtPrice'];

            foreach ($variant['options'] as $optionValue) {
                $row['optionValues'][] = [
                    'optionName' => $optionValue['name'],
                    'name' => $optionValue['optionName'],
                ];
            }
            $variants[] = $row;
        }

        $variables = [
            'productId' => $productId,
            'strategy' => 'REMOVE_STANDALONE_VARIANT',
            'variants' => $variants,
        ];

        $productVariantsBulkCreateResponse = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $productVariantsBulkCreateData = json_decode($productVariantsBulkCreateResponse, true);

        SyncStorage::writeJson('responses/product-variants-bulk-createv1.json', $productVariantsBulkCreateData);

        return $productVariantsBulkCreateData;
    }

    private function filter_query(string $query)
    {
        // remove new lines
        $query = preg_replace('/\s+/', ' ', $query);

        return $query;
    }

    public function attach_media_to_variant(string $productId, string $variantId, string $mediaId)
    {
        // GraphQL mutation tek satır string (diğerleriyle tutarlı)
        $query = 'mutation ProductVariantAttachMedia($productId: ID!, $variantMedia: [ProductVariantAppendMediaInput!]!) { productVariantAppendMedia(productId: $productId, variantMedia: $variantMedia) { product { id } productVariants { id media(first: 5) { edges { node { mediaContentType } } } } userErrors { field message } } }';

        $variables = [
            'productId' => $productId,
            'variantMedia' => [
                [
                    'variantId' => $variantId,
                    'mediaIds' => [$mediaId],
                ],
            ],
        ];

        $body = json_encode([
            'query' => $query,
            'variables' => $variables,
        ]);

        $response = $this->client->request($body);
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/attach_media_to_variant.json', $data);

        return $data;
    }

    function productCreateMedia($productId, $media_url)
    {
        $productMediaCreateQuery = <<<'GRAPHQL'
            mutation productCreateMedia($media: [CreateMediaInput!]!, $productId: ID!) { productCreateMedia(media: $media, productId: $productId) { media { alt mediaContentType status ... on MediaImage { id image { url } } } mediaUserErrors { field message } product { id title } } }
            GRAPHQL;

        $media = [
            [
                'alt' => 'Media',
                'mediaContentType' => 'IMAGE',
                'originalSource' => $media_url,
            ],
        ];

        $variables = [
            'media' => $media,
            'productId' => $productId,
        ];

        $productMediaCreateResponse = $this->client->request('{"query": "' . $productMediaCreateQuery . '", "variables": ' . json_encode($variables) . '}');
        $productMediaCreateData = json_decode($productMediaCreateResponse, true);

        SyncStorage::writeJson('responses/product-media-create.json', $productMediaCreateData);

        return $productMediaCreateData;
    }

    function primary_location_id()
    {
        static $locationId = null;

        if ($locationId !== null) {
            return $locationId;
        }

        $query = 'query { locations(first: 1) { edges { node { id } } } }';
        $response = $this->client->request('{"query": "' . $query . '"}');
        $data = json_decode($response, true);
        $locationId = $data['data']['locations']['edges'][0]['node']['id'] ?? null;

        return $locationId;
    }

    function inventory_item($variantId)
    {
        $inventory_item_id_query = <<<'GRAPHQL'
            query productVariant($id: ID!) { productVariant(id: $id) { id inventoryQuantity inventoryItem { id tracked inventoryLevels(first: 1) { edges { node { id location { id } } } } } } }
            GRAPHQL;

        $variables = [
            'id' => $variantId,
        ];

        $inventory_item_id_response = $this->client->request('{"query": "' . $inventory_item_id_query . '", "variables": ' . json_encode($variables) . '}');
        $inventory_item_id_data = json_decode($inventory_item_id_response);

        SyncStorage::writeJson('responses/inventory-item-id.json', $inventory_item_id_data);

        return $inventory_item_id_data;
    }

    // Stok: inventorySetQuantities ile mutlak miktar ayarla
    function update_variation_stock($variation_id, $quantity)
    {
        $quantity = intval($quantity);

        $inventory_item = $this->inventory_item($variation_id);
        if (!isset($inventory_item->data->productVariant)) {
            SyncStorage::writeJson('responses/inventory-item-not-found.json', $inventory_item);
            echo 'Inventory item not found for: ' . $variation_id . PHP_EOL;
            return null;
        }

        $variant = $inventory_item->data->productVariant;
        $inventory_quantity = intval($variant->inventoryQuantity);
        $inventory_item_id = $variant->inventoryItem->id;

        if ($quantity === $inventory_quantity) {
            return ['skipped' => true, 'message' => 'Quantity is already ' . $quantity];
        }

        $location_id = null;
        $levels = $variant->inventoryItem->inventoryLevels->edges ?? [];

        if (!empty($levels)) {
            $location_id = $levels[0]->node->location->id;
        }

        if ($location_id === null) {
            $location_id = $this->primary_location_id();
        }

        if ($location_id === null) {
            echo 'No Shopify location found for stock update: ' . $variation_id . PHP_EOL;
            return null;
        }

        if (empty($levels)) {
            $activateQuery = 'mutation inventoryActivate($inventoryItemId: ID!, $locationId: ID!, $available: Int) { inventoryActivate(inventoryItemId: $inventoryItemId, locationId: $locationId, available: $available) { userErrors { field message } } }';
            $activateVars = [
                'inventoryItemId' => $inventory_item_id,
                'locationId' => $location_id,
                'available' => $quantity,
            ];
            $activateResponse = $this->client->request('{"query": "' . $activateQuery . '", "variables": ' . json_encode($activateVars) . '}');
            $activateData = json_decode($activateResponse, true);
            SyncStorage::writeJson('responses/inventory-activate.json', $activateData);

            $activateErrors = $activateData['data']['inventoryActivate']['userErrors'] ?? [];
            if (!empty($activateErrors)) {
                echo 'inventoryActivate error: ' . json_encode($activateErrors, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }

            return $activateData;
        }

        $query = 'mutation inventorySetQuantities($input: InventorySetQuantitiesInput!, $idempotencyKey: String!) { inventorySetQuantities(input: $input) @idempotent(key: $idempotencyKey) { userErrors { field message } inventoryAdjustmentGroup { createdAt } } }';

        $variables = [
            'input' => [
                'name' => 'available',
                'reason' => 'correction',
                'quantities' => [
                    [
                        'inventoryItemId' => $inventory_item_id,
                        'locationId' => $location_id,
                        'quantity' => $quantity,
                        'changeFromQuantity' => null,
                    ],
                ],
            ],
            'idempotencyKey' => hash('sha256', $inventory_item_id . '|' . $location_id . '|' . $quantity . '|' . microtime(true)),
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/inventory-set-quantities.json', $data);

        $errors = $data['data']['inventorySetQuantities']['userErrors'] ?? [];
        if (!empty($errors)) {
            echo 'inventorySetQuantities error: ' . json_encode($errors, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }

        return $data;
    }

    function update_multiple_variation_prices($product_id, $variations)
    {
        // Tek varyant objesi gelirse diziye sar (import.php tek ürün gönderir)
        if (isset($variations['id'])) {
            $variations = [$variations];
        }

        $variables = [
            'productId' => $product_id,
            'variants' => $variations
        ];

        $productVariantSKUUQuery = <<<'GRAPHQL'
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $productId, variants: $variants) { product { id } productVariants { id metafields(first: 2) { edges { node { namespace key value } } } } userErrors { field message } } }
            GRAPHQL;

        $productVariantSKUResponse = $this->client->request('{"query": "' . $productVariantSKUUQuery . '", "variables": ' . json_encode($variables) . '}');
        $productVariantSKUData = json_decode($productVariantSKUResponse, true);

        SyncStorage::writeJson('responses/product-variant-update-sku_variable.json', $productVariantSKUData);

        $errors = $productVariantSKUData['data']['productVariantsBulkUpdate']['userErrors'] ?? [];
        if (!empty($errors)) {
            echo 'productVariantsBulkUpdate error: ' . json_encode($errors, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }

        return $productVariantSKUData;
    }

    public function create_collection($collection_name)
    {
        $query = 'mutation collectionCreate($input: CollectionInput!) { collectionCreate(input: $input) { collection { id title } userErrors { field message } } }';
        $variables = [
            'input' => [
                'title' => $collection_name,
            ],
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/create_collection_response.json', $data);
        return $data;
    }

    public function collectionsCount()
    {
        // Yalnızca manuel (custom) koleksiyonları say — limit: null = 10k üstü tam sayım (2026-07)
        $query = 'query { collectionsCount(query: "collection_type:custom", limit: null) { count precision } }';
        $response = $this->client->request('{"query": "' . $query . '"}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/collections_count.json', $data);

        $count = $this->parseGraphqlCountField($data, 'collectionsCount');
        if ($count !== false) {
            return $count;
        }

        if (!empty($data['errors'])) {
            GraphqlClient::logProblem('collectionsCount GraphQL hatası, REST fallback deneniyor', [
                'errors' => $data['errors'],
            ]);
        }

        return $this->restCustomCollectionsCount();
    }

    /** REST: manuel koleksiyon sayısı (test ve GraphQL fallback) */
    public function restCustomCollectionsCount()
    {
        $url = 'admin/api/' . $this->client->apiVersion() . '/custom_collections/count.json';
        $response = json_decode($this->client->rest($url, [], 'GET'));
        if (isset($response->count)) {
            return (int) $response->count;
        }

        return false;
    }

    /** Shopify'da tek koleksiyon — akıllı/manuel ayrımı için ruleSet */
    public function getCollectionByIdResult(string $collectionId): array
    {
        if (!str_contains($collectionId, 'gid://shopify/Collection/')) {
            $collectionId = 'gid://shopify/Collection/' . $collectionId;
        }

        $query = <<<'QUERY'
            query ($id: ID!) {
                collection(id: $id) {
                    id
                    title
                    handle
                    ruleSet {
                        appliedDisjunctively
                        rules {
                            column
                        }
                    }
                }
            }
            QUERY;

        $variables = ['id' => $collectionId];
        $query = $this->filter_query($query);
        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/collection_by_id.json', $data);

        return [
            'collection' => $data['data']['collection'] ?? null,
            'graphql_errors' => $data['errors'] ?? [],
        ];
    }

    public function getCollectionById(string $collectionId)
    {
        return $this->getCollectionByIdResult($collectionId)['collection'];
    }

    /** Test ve doğrulama: ilk akıllı koleksiyon */
    public function getFirstSmartCollection()
    {
        $data = $this->collectionQuery(1, null, 'collection_type:smart');
        $edges = $data['data']['collections']['edges'] ?? [];

        return $edges[0]['node'] ?? null;
    }

    public function collectionDelete(string $collectionId)
    {
        if (!str_contains($collectionId, 'gid://shopify/Collection/')) {
            $collectionId = 'gid://shopify/Collection/' . $collectionId;
        }

        $query = 'mutation collectionDelete($id: ID!) { collectionDelete(id: $id) { deletedCollectionId userErrors { field message } } }';
        $variables = ['id' => $collectionId];
        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/collection_delete.json', $data);

        return $data;
    }

    public function getAllCollections()
    {
        $collections = [];
        $cursor = null;
        $page = 1;

        while (true) {
            if (function_exists('import_running_heartbeat')) {
                import_running_heartbeat();
            }
            echo 'Fetching collections ' . $page++ . PHP_EOL;
            $collectionsData = $this->collectionQuery(250, $cursor, 'collection_type:custom');
            $edges = $collectionsData['data']['collections']['edges'] ?? [];

            $pageCollections = array_map(function ($collection) {
                return $collection['node'];
            }, $edges);
            $collections = array_merge($collections, $pageCollections);

            if (($collectionsData['data']['collections']['pageInfo']['hasNextPage'] ?? false) == false) {
                echo 'All collections fetched' . PHP_EOL;
                break;
            }

            $lastEdge = end($edges);
            if (isset($lastEdge['cursor'])) {
                $cursor = $lastEdge['cursor'];
            } else {
                break;
            }
        }

        $apiCount = $this->collectionsCount();
        if ($apiCount !== false && count($collections) != $apiCount) {
            GraphqlClient::logProblem('Manuel koleksiyon sayısı uyuşmuyor', [
                'fetched_count' => count($collections),
                'api_count' => $apiCount,
            ]);
        }

        SyncStorage::writeJson('responses/collections.json', $collections);

        return $collections;
    }

    public function collectionQuery($first, $after = null, $search = 'collection_type:custom')
    {
        $query = <<<'QUERY'
            query ($first: Int!, $after: String, $search: String) {
                collections(first: $first, after: $after, query: $search) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        cursor
                        node {
                            id
                            title
                            handle
                        }
                    }
                }
            }
            QUERY;

        $variables = [
            'first' => $first,
            'after' => $after,
            'search' => $search,
        ];

        $query = $this->filter_query($query);

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/collection_response.json', $data);

        return $data;
    }

    /**
     * Ürünün üye olduğu koleksiyon GID listesini döner.
     */
    public function get_product_collection_ids(string $productId): array
    {
        if (!str_contains($productId, 'gid://shopify/Product/')) {
            $productId = 'gid://shopify/Product/' . $productId;
        }

        $query = <<<'QUERY'
            query productCollections($id: ID!) {
                product(id: $id) {
                    collections(first: 250) {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
            QUERY;

        $variables = ['id' => $productId];
        $response = $this->client->request('{"query": ' . json_encode($query) . ', "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/product_collections.json', $data);

        $ids = [];
        foreach ($data['data']['product']['collections']['edges'] ?? [] as $edge) {
            if (!empty($edge['node']['id'])) {
                $ids[] = $edge['node']['id'];
            }
        }

        return $ids;
    }

    public function remove_product_from_collection($collection_id, $product_ids)
    {
        if (!str_contains($collection_id, 'gid://shopify/Collection/')) {
            $collection_id = 'gid://shopify/Collection/' . $collection_id;
        }

        $query = 'mutation collectionRemoveProducts($id: ID!, $productIds: [ID!]!) { collectionRemoveProducts(id: $id, productIds: $productIds) { job { id } userErrors { field message } } }';

        $variables = [
            'id' => $collection_id,
            'productIds' => $product_ids,
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/remove_product_from_collection_response.json', $data);

        return $data;
    }

    public function add_product_to_colletion($collection_id, $product_ids)
    {
        if (!str_contains($collection_id, 'gid://shopify/Collection/')) {
            $collection_id = 'gid://shopify/Collection/' . $collection_id;
        }

        $query = 'mutation collectionAddProducts($id: ID!, $productIds: [ID!]!) { collectionAddProducts(id: $id, productIds: $productIds) { collection { id title products(first: 10) { nodes { id title } } } userErrors { field message } } }';

        $variables = [
            'id' => $collection_id,
            'productIds' => $product_ids
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/add_product_to_collection_response.json', $data);

        return $data;
    }

    public function publications()
    {
        $query = 'query { publications(first: 10) { edges { node { id name } } } }';
        $response = $this->client->request('{"query": "' . $query . '"}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/publications.json', $data);
        return $data;
    }

    public function publish_product($product_id)
    {
        $publications = $this->publications();

        $publication_id = $publications['data']['publications']['edges'][0]['node']['id'];

        // publishablePublishToCurrentChannel
        $query = 'mutation publishablePublish($id: ID!, $input: [PublicationInput!]!) { publishablePublish(id: $id, input: $input) { publishable { availablePublicationsCount { count } resourcePublicationsCount { count } } shop { publicationCount } userErrors { field message } } }';
        $variables = [
            'id' => $product_id,
            'input' => [
                [
                    'publicationId' => $publication_id,
                ]
            ]
        ];
        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);
        SyncStorage::writeJson('responses/publish_product.json', $data);
        return $data;
    }

    // Tüm satış kanallarına yayınla (import yeni ürünlerde kullanır)
    public function publish_product_all($product_id)
    {
        $publications = $this->publications();

        if (empty($publications['data']['publications']['edges'])) {
            throw new Exception('No publications found.');
        }

        $publication_inputs = [];
        foreach ($publications['data']['publications']['edges'] as $publication) {
            $publication_inputs[] = [
                'publicationId' => $publication['node']['id'],
            ];
        }

        $query = 'mutation publishablePublish($id: ID!, $input: [PublicationInput!]!) { publishablePublish(id: $id, input: $input) { publishable { availablePublicationsCount { count } resourcePublicationsCount { count } } userErrors { field message } } }';
        $variables = [
            'id' => $product_id,
            'input' => $publication_inputs,
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');
        $data = json_decode($response, true);

        SyncStorage::writeJson('responses/publish_product.json', $data);

        if (!empty($data['data']['publishablePublish']['userErrors'])) {
            throw new Exception('Error publishing product: ' . json_encode($data['data']['publishablePublish']['userErrors']));
        }

        return $data;
    }

    function update_product_metafields($product_id, array $metafields)
    {
        // GraphQL mutasyonu: productUpdate ile metafields alanını kullanıyoruz.
        // Metodu diğer mutasyonlarınla aynı stil olacak şekilde tek satır string olarak yazdım.
        $query = 'mutation productUpdateMetafields($product: ProductUpdateInput!) { productUpdate(product: $product) { product { id metafields(first: 10) { edges { node { namespace key value } } } } userErrors { field message } } }';

        // $metafields beklenen formatta gelmediyse burada validation/normalize de yapabilirsin
        // Örn. her metafield için namespace, key, type, value zorunlu.
        $normalizedMetafields = [];

        foreach ($metafields as $metafield) {
            // Minimum validasyon: zorunlu alanlar mevcut mu?
            if (
                empty($metafield['namespace']) ||
                empty($metafield['key']) ||
                empty($metafield['type']) ||
                !array_key_exists('value', $metafield)
            ) {
                // Hatalı bir kayıt varsa atla veya exception fırlat
                // Burada sadece continue ile atlıyorum
                continue;
            }

            $normalizedMetafields[] = [
                'namespace' => $metafield['namespace'],
                'key' => $metafield['key'],
                'type' => $metafield['type'],
                'value' => (string) $metafield['value'],  // GraphQL tarafında value her zaman string
            ];
        }

        $variables = [
            'product' => [
                'id' => $product_id,
                'metafields' => $normalizedMetafields,
            ],
        ];

        $response = $this->client->request('{"query": "' . $query . '", "variables": ' . json_encode($variables) . '}');

        $data = json_decode($response, true);

        // Debug / log için kayıt
        SyncStorage::writeJson('responses/update_product_metafields.json', $data);

        return $data;
    }
}
