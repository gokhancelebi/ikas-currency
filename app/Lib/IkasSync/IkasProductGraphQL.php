<?php

namespace App\Lib\IkasSync;

use Exception;

class IkasProductGraphQL
{
    private const PRODUCT_LIST_FIELDS = <<<'GQL'
        id
        name
        type
        totalStock
        description
        variants {
            id
            sku
            isActive
            barcodeList
            variantValues { variantTypeName variantValueName }
            prices { sellPrice discountPrice buyPrice currency }
            stocks { stockCount stockLocationId }
            images { imageId isMain order fileName }
        }
        tags { id name }
        categories { id name }
GQL;

    private ?string $stockLocationId = null;

    private ?string $priceListId = null;

    public function __construct(
        private GraphqlClient $client
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function allProducts(): array
    {
        $products = [];
        $page = 1;
        $limit = 100;

        while (true) {
            if (function_exists('import_running_heartbeat')) {
                import_running_heartbeat();
            }

            echo 'Fetching products '.$page.PHP_EOL;
            SyncStorage::write('page.txt', (string) $page);

            $response = $this->listProductPage($page, $limit);
            $block = $response['data']['listProduct'] ?? [];
            $pageProducts = $block['data'] ?? [];
            $products = array_merge($products, $pageProducts);

            if (empty($block['hasNext'])) {
                echo 'All products fetched'.PHP_EOL;
                break;
            }

            $page++;
        }

        SyncStorage::writeJson('products.json', $products);

        return $products;
    }

    /** @return array<string, mixed> */
    public function listProductPage(int $page, int $limit): array
    {
        $query = 'query ListProduct($pagination: PaginationInput) { listProduct(pagination: $pagination) { count hasNext limit page data { '.self::PRODUCT_LIST_FIELDS.' } } }';

        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/products-page-'.$page.'.json', $response);

        return $response;
    }

    public function total_product_count(): int|false
    {
        $query = 'query { listProduct(pagination: { page: 1, limit: 1 }) { count } }';
        $response = $this->client->request(['query' => $query]);
        SyncStorage::writeJson('responses/total_product_count.json', $response);

        if (! empty($response['errors'])) {
            GraphqlClient::logProblem('listProduct count hatası', ['errors' => $response['errors']]);

            return false;
        }

        $count = $response['data']['listProduct']['count'] ?? null;
        if (! is_numeric($count)) {
            return false;
        }

        return (int) $count;
    }

    /** @return array<string, mixed>|null */
    public function get_product_by_id(string $productId): ?array
    {
        $query = 'query GetProduct($id: StringFilterInput) { listProduct(id: $id, pagination: { page: 1, limit: 1 }) { data { '.self::PRODUCT_LIST_FIELDS.' } } }';

        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'id' => ['eq' => $productId],
            ],
        ]);

        SyncStorage::writeJson('responses/product_by_id.json', $response);

        return $response['data']['listProduct']['data'][0] ?? null;
    }

    public function product_delete(string $productId): bool
    {
        $query = 'mutation DeleteProducts($idList: [String!]!) { deleteProductList(idList: $idList) }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => ['idList' => [$productId]],
        ]);

        SyncStorage::writeJson('responses/delete_product.json', $response);

        return (bool) ($response['data']['deleteProductList'] ?? false);
    }

    /** @param  array<int, string>  $tags */
    public function update_product_tags(string $productId, array $tags): array
    {
        $product = $this->get_product_by_id($productId);
        $existing = array_column($product['tags'] ?? [], 'name');
        $all = array_values(array_unique(array_merge($existing, $tags)));

        return $this->updateProductFields($productId, [
            'tags' => array_map(fn (string $name) => ['name' => $name], $all),
        ]);
    }

    /** Metafield yok; açıklamaya yazarak test senaryosunu destekler */
    public function update_product_metafields(string $productId, array $metafields): array
    {
        $description = '';
        foreach ($metafields as $field) {
            $description .= ($field['namespace'] ?? 'meta').'.'.($field['key'] ?? 'key').': '.($field['value'] ?? '')."\n";
        }

        return $this->updateProductFields($productId, ['description' => trim($description)]);
    }

    /** @param  array<string, mixed>  $fields */
    public function updateProductFields(string $productId, array $fields): array
    {
        $query = 'mutation UpdateProduct($input: UpdateProductInput!) { updateProduct(input: $input) { id name description tags { id name } categories { id name } } }';
        $input = array_merge(['id' => $productId], $fields);

        $response = $this->client->request([
            'query' => $query,
            'variables' => ['input' => $input],
        ]);

        SyncStorage::writeJson('responses/update_product.json', $response);

        return $response;
    }

    /** @return array{productId: ?string, variantId: ?string} */
    public function create_simple_product(array $inputs): array
    {
        $inventory = $inputs['inventory_data']['variations'][0] ?? [];
        $sku = (string) ($inventory['sku'] ?? 'SKU-'.time());

        $query = 'mutation CreateProduct($input: CreateProductInput!) { createProduct(input: $input) { id name variants { id sku } } }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'name' => (string) ($inputs['title'] ?? $inputs['name'] ?? 'Test Product'),
                    'description' => (string) ($inputs['descriptionHtml'] ?? $inputs['description'] ?? ''),
                    'type' => 'PHYSICAL',
                    'variants' => [[
                        'sku' => $sku,
                        'isActive' => true,
                        'prices' => [self::buildIkasPriceInput(
                            (float) ($inventory['price'] ?? 0),
                            (float) ($inventory['compareAtPrice'] ?? 0),
                            (float) ($inventory['cost'] ?? 0)
                        )],
                    ]],
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/create_simple_product.json', $response);

        $product = $response['data']['createProduct'] ?? [];
        $productId = $product['id'] ?? null;
        $variantId = $product['variants'][0]['id'] ?? null;

        if ($productId && $variantId && isset($inventory['inventoryQuantity'])) {
            $this->update_variation_stock($variantId, (int) $inventory['inventoryQuantity'], $productId);
        }

        return ['productId' => $productId, 'variantId' => $variantId];
    }

    /** @return array{productId: ?string} */
    public function create_variable_product(array $productInput): array
    {
        $variations = $productInput['inventory_data']['variations'] ?? [];

        $variants = [];
        foreach ($variations as $variation) {
            $variantValues = [];
            foreach ($variation['options'] ?? [] as $opt) {
                $variantValues[] = [
                    'variantTypeName' => (string) ($opt['name'] ?? ''),
                    'variantValueName' => (string) ($opt['optionName'] ?? ''),
                ];
            }

            $variants[] = [
                'sku' => (string) ($variation['sku'] ?? ''),
                'isActive' => true,
                'variantValues' => $variantValues,
                'prices' => [self::buildIkasPriceInput(
                    (float) ($variation['price'] ?? 0),
                    (float) ($variation['compareAtPrice'] ?? 0),
                    (float) ($variation['cost'] ?? 0)
                )],
            ];
        }

        $query = 'mutation CreateProduct($input: CreateProductInput!) { createProduct(input: $input) { id name variants { id sku variantValues { variantTypeName variantValueName } } } }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'name' => (string) ($productInput['title'] ?? 'Variable Product'),
                    'description' => (string) ($productInput['descriptionHtml'] ?? ''),
                    'type' => 'PHYSICAL',
                    'variants' => $variants,
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/create_variable_product.json', $response);

        $productId = $response['data']['createProduct']['id'] ?? null;
        $createdVariants = $response['data']['createProduct']['variants'] ?? [];

        foreach ($createdVariants as $createdVariant) {
            foreach ($variations as $variation) {
                if (($createdVariant['sku'] ?? '') !== ($variation['sku'] ?? '')) {
                    continue;
                }
                if (isset($variation['inventoryQuantity'])) {
                    $this->update_variation_stock($createdVariant['id'], (int) $variation['inventoryQuantity'], $productId);
                }
            }
        }

        return ['productId' => $productId];
    }

  /** @param  array<string, mixed>  $inventoryData */
    public function update_simple_product_inventory_data(string $productId, array $inventoryData): array
    {
        $variantId = (string) ($inventoryData['variantId'] ?? '');

        $priceResult = $this->update_multiple_variation_prices($productId, [[
            'variantId' => $variantId,
            'price' => (float) ($inventoryData['price'] ?? 0),
            'compareAtPrice' => (float) ($inventoryData['compareAtPrice'] ?? 0),
            'cost' => (float) ($inventoryData['cost'] ?? 0),
        ]]);

        if (! empty($inventoryData['sku'])) {
            $this->updateVariantSku($productId, $variantId, (string) $inventoryData['sku']);
        }

        return $priceResult;
    }

    public function updateVariantSku(string $productId, string $variantId, string $sku): array
    {
        $query = 'mutation UpdateProduct($input: UpdateProductInput!) { updateProduct(input: $input) { id variants { id sku } } }';
        $product = $this->get_product_by_id($productId);
        if ($product === null) {
            return ['errors' => [['message' => 'Product not found']]];
        }

        $variants = [];
        foreach ($product['variants'] ?? [] as $variant) {
            $row = [
                'id' => $variant['id'],
                'sku' => ($variant['id'] ?? '') === $variantId ? $sku : ($variant['sku'] ?? ''),
            ];
            $variants[] = $row;
        }

        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'id' => $productId,
                    'variants' => $variants,
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/update_variant_sku.json', $response);

        return $response;
    }

    public function delete_product_variant(string $productId, string $variantId): array
    {
        $query = 'mutation RemoveVariant($input: RemoveVariantFromProductInput!) { removeVariantFromProduct(input: $input) }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'productId' => $productId,
                    'variantId' => $variantId,
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/delete_variant.json', $response);

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variantData
     * @param  array<int, array<string, mixed>>  $existingVariants
     */
    public function update_product_variants(string $productId, array $variantData, array $existingVariants): void
    {
        $notFound = $variantData;

        foreach ($variantData as $key => $variant) {
            $optionKey = $this->buildOptionKey($variant['options'] ?? []);

            foreach ($existingVariants as $existing) {
                $existingKey = $this->buildOptionKeyFromVariantValues($existing['variantValues'] ?? []);
                if ($optionKey !== '' && $optionKey !== $existingKey) {
                    continue;
                }

                if ($optionKey === '' && ($variant['sku'] ?? '') !== ($existing['sku'] ?? '')) {
                    continue;
                }

                unset($notFound[$key]);

                $this->update_multiple_variation_prices($productId, [[
                    'variantId' => $existing['id'],
                    'price' => (float) ($variant['price'] ?? 0),
                    'compareAtPrice' => (float) ($variant['compareAtPrice'] ?? 0),
                    'cost' => (float) ($variant['cost'] ?? 0),
                ]]);

                if (isset($variant['inventoryQuantity'])) {
                    $this->update_variation_stock($existing['id'], (int) $variant['inventoryQuantity'], $productId);
                }
            }
        }

        foreach ($notFound as $missing) {
            if (! empty($missing['id'])) {
                $this->delete_product_variant($productId, (string) $missing['id']);
            }
        }
    }

    public function update_variation_stock(string $variantId, int $quantity, ?string $productId = null): ?array
    {
        if ($productId === null) {
            $productId = $this->findProductIdByVariantId($variantId);
        }

        if ($productId === null) {
            GraphqlClient::logProblem('Stok güncellemesi için productId bulunamadı', ['variantId' => $variantId]);

            return null;
        }

        $query = 'mutation SaveVariantStocks($input: SaveVariantStocksInput!) { saveVariantStocks(input: $input) { errors { errorCode } } }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'stockInputs' => [[
                        'deleted' => false,
                        'productId' => $productId,
                        'variantId' => $variantId,
                        'stockLocationId' => $this->primary_stock_location_id(),
                        'stockCount' => $quantity,
                    ]],
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/save_variant_stocks.json', $response);

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variations
     */
    public function update_multiple_variation_prices(string $productId, array $variations): array
    {
        if (isset($variations['variantId']) || isset($variations['id'])) {
            $variations = [$variations];
        }

        $variantPriceInputs = [];
        foreach ($variations as $variation) {
            $variantId = (string) ($variation['variantId'] ?? $variation['id'] ?? '');
            if ($variantId === '') {
                continue;
            }

            $price = self::buildIkasPriceInput(
                (float) ($variation['sellPrice'] ?? $variation['price'] ?? 0),
                (float) ($variation['discountPrice'] ?? $variation['compareAtPrice'] ?? 0),
                (float) ($variation['buyPrice'] ?? $variation['cost'] ?? 0)
            );

            $variantPriceInputs[] = [
                'deleted' => false,
                'productId' => $productId,
                'variantId' => $variantId,
                'price' => $price,
            ];
        }

        $query = 'mutation UpdateVariantPrices($input: UpdateVariantPricesInput!) { updateVariantPrices(input: $input) { errors { errorCode } } }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [
                    'priceListId' => $this->default_price_list_id(),
                    'variantPriceInputs' => $variantPriceInputs,
                ],
            ],
        ]);

        SyncStorage::writeJson('responses/update_variant_prices.json', $response);

        return $response;
    }

    public function create_category(string $categoryName, ?string $parentId = null): array
    {
        $input = [
            'name' => $categoryName,
            'isAutomated' => false,
        ];

        if ($parentId !== null && $parentId !== '') {
            $input['parentId'] = $parentId;
        }

        $query = 'mutation CreateCategory($input: CreateCategoryInput!) { createCategory(input: $input) { id name parentId categoryPath } }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => ['input' => $input],
        ]);

        if (isset($response['data']['createCategory'])) {
            $response['data']['saveCategory'] = $response['data']['createCategory'];
        }

        SyncStorage::writeJson('responses/create_category.json', $response);

        return $response;
    }

    public function categoriesCount(): int|false
    {
        $categories = $this->getAllCategories();

        return count($categories);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllCategories(): array
    {
        $query = 'query { listCategory { id name parentId isAutomated categoryPath } }';
        $response = $this->client->request(['query' => $query]);
        SyncStorage::writeJson('responses/categories.json', $response);

        $categories = $response['data']['listCategory'] ?? [];
        if (! is_array($categories)) {
            return [];
        }

        return array_values(array_filter($categories, function ($category) {
            return empty($category['isAutomated']);
        }));
    }

    /** @param  array<int, array<string, mixed>>  $categories */
    /** @return array<string, array<string, mixed>> */
    public static function indexCategoriesById(array $categories): array
    {
        $indexed = [];
        foreach ($categories as $category) {
            if (! empty($category['id'])) {
                $indexed[(string) $category['id']] = $category;
            }
        }

        return $indexed;
    }

    /** @param  array<string, mixed>  $category */
    /** @param  array<string, array<string, mixed>>  $categoriesById */
    public static function categoryDisplayName(array $category, array $categoriesById): string
    {
        $parts = [];
        foreach (self::categoryPathIds($category) as $parentId) {
            $parentName = $categoriesById[$parentId]['name'] ?? null;
            if ($parentName !== null && $parentName !== '') {
                $parts[] = $parentName;
            }
        }

        $parts[] = (string) ($category['name'] ?? '');

        return implode(' > ', array_filter($parts));
    }

    /** @param  array<string, mixed>  $category */
    /** @return array<int, string> */
    public static function categoryPathIds(array $category): array
    {
        $path = $category['categoryPath'] ?? [];

        return is_array($path) ? array_values(array_filter($path)) : [];
    }

    /** @param  array<string, mixed>  $category */
    /** @return array{name: string, path?: array<int, string>} */
    public static function buildProductCategoryInput(array $category): array
    {
        $input = ['name' => (string) ($category['name'] ?? '')];
        $path = self::categoryPathIds($category);

        if ($path !== []) {
            $input['path'] = $path;
        }

        return $input;
    }

    /**
     * @param  array<int, string>  $categoryIds
     * @param  array<string, array<string, mixed>>  $categoriesById
     * @return array<int, array{name: string, path?: array<int, string>}>
     */
    private function buildProductCategoryInputs(array $categoryIds, array $categoriesById): array
    {
        $inputs = [];

        foreach ($categoryIds as $categoryId) {
            $category = $categoriesById[$categoryId] ?? null;
            if ($category === null) {
                continue;
            }

            $inputs[] = self::buildProductCategoryInput($category);
        }

        return $inputs;
    }

  /** @return array<string, mixed>|null */
    public function getCategoryById(string $categoryId): ?array
    {
        foreach ($this->getAllCategories() as $category) {
            if (($category['id'] ?? '') === $categoryId) {
                return $category;
            }
        }

        return null;
    }

    public function categoryDelete(string $categoryId): array
    {
        $query = 'mutation DeleteCategoryList($idList: [String!]!) { deleteCategoryList(idList: $idList) }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => ['idList' => [$categoryId]],
        ]);

        SyncStorage::writeJson('responses/delete_category.json', $response);

        return $response;
    }

    /** @param  array<int, string>  $productIds */
    public function add_product_to_category(string $categoryId, array $productIds): array
    {
        $results = [];
        $categoriesById = self::indexCategoriesById($this->getAllCategories());

        foreach ($productIds as $productId) {
            $product = $this->get_product_by_id($productId);
            if ($product === null) {
                continue;
            }

            $category = $categoriesById[$categoryId] ?? null;
            if ($category === null) {
                continue;
            }

            $categoryIds = array_column($product['categories'] ?? [], 'id');
            if (! in_array($categoryId, $categoryIds, true)) {
                $categoryIds[] = $categoryId;
            }

            $results[] = $this->updateProductFields($productId, [
                'categories' => $this->buildProductCategoryInputs($categoryIds, $categoriesById),
            ]);
        }

        if ($productIds === []) {
            $category = $categoriesById[$categoryId] ?? null;

            return [
                'data' => [
                    'category' => $category,
                    'products' => $this->getProductsInCategory($categoryId),
                ],
            ];
        }

        return $results[0] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getProductsInCategory(string $categoryId): array
    {
        $all = $this->allProducts();

        return array_values(array_filter($all, function ($product) use ($categoryId) {
            foreach ($product['categories'] ?? [] as $category) {
                if (($category['id'] ?? '') === $categoryId) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @param  array<int, string>  $productIds */
    public function remove_product_from_category(string $categoryId, array $productIds): array
    {
        $results = [];
        $categoriesById = self::indexCategoriesById($this->getAllCategories());

        foreach ($productIds as $productId) {
            $product = $this->get_product_by_id($productId);
            if ($product === null) {
                continue;
            }

            $categoryIds = array_values(array_filter(
                array_column($product['categories'] ?? [], 'id'),
                fn ($id) => $id !== $categoryId
            ));

            $results[] = $this->updateProductFields($productId, [
                'categories' => $this->buildProductCategoryInputs($categoryIds, $categoriesById),
            ]);
        }

        return $results[0] ?? [];
    }

    /** @return array<int, string> */
    public function get_product_category_ids(string $productId): array
    {
        $product = $this->get_product_by_id($productId);

        return array_column($product['categories'] ?? [], 'id');
    }

    public function upload_product_image(string $productId, string $variantId, string $imageUrl, int $order = 1, bool $isMain = true): bool
    {
        return $this->client->uploadImage([
            'productId' => $productId,
            'variantId' => $variantId,
            'url' => $imageUrl,
            'order' => $order,
            'isMain' => $isMain,
        ]);
    }

    public function productCreateMedia(string $productId, string $mediaUrl): array
    {
        $product = $this->get_product_by_id($productId);
        $variantId = $product['variants'][0]['id'] ?? null;
        if ($variantId === null) {
            return ['errors' => [['message' => 'No variant for image upload']]];
        }

        $ok = $this->upload_product_image($productId, $variantId, $mediaUrl);

        return ['data' => ['upload' => $ok], 'variantId' => $variantId];
    }

    public function attach_media_to_variant(string $productId, string $variantId, string $mediaUrl): array
    {
        $ok = $this->upload_product_image($productId, $variantId, $mediaUrl);

        return ['data' => ['upload' => $ok]];
    }

    public function publish_product_all(string $productId): array
    {
        $query = 'mutation UpdateSalesChannel($input: [UpdateProductSalesChannelStatusInput!]!) { updateProductSalesChannelStatus(input: $input) }';
        $response = $this->client->request([
            'query' => $query,
            'variables' => [
                'input' => [[
                    'productId' => $productId,
                    'status' => 'VISIBLE',
                ]],
            ],
        ]);

        SyncStorage::writeJson('responses/publish_product.json', $response);

        if (! empty($response['errors'])) {
            throw new Exception('Error publishing product: '.json_encode($response['errors']));
        }

        return $response;
    }

    public function primary_stock_location_id(): string
    {
        if ($this->stockLocationId !== null) {
            return $this->stockLocationId;
        }

        $query = 'query { listStockLocation { id name } }';
        $response = $this->client->request(['query' => $query]);
        $locations = $response['data']['listStockLocation'] ?? [];
        $this->stockLocationId = $locations[0]['id'] ?? '';

        return $this->stockLocationId;
    }

    public function default_price_list_id(): ?string
    {
        if ($this->priceListId !== null) {
            return $this->priceListId;
        }

        $query = 'query { listPriceList { id name } }';
        $response = $this->client->request(['query' => $query]);
        $lists = $response['data']['listPriceList'] ?? [];
        $this->priceListId = $lists[0]['id'] ?? null;

        return $this->priceListId;
    }

    private function findProductIdByVariantId(string $variantId): ?string
    {
        $products = $this->allProducts();
        foreach ($products as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                if (($variant['id'] ?? '') === $variantId) {
                    return $product['id'] ?? null;
                }
            }
        }

        return null;
    }

    /** @param  array<int, array<string, string>>  $options */
    private function buildOptionKey(array $options): string
    {
        $pairs = [];
        foreach ($options as $option) {
            $pairs[] = trim((string) ($option['name'] ?? '')).':'.trim((string) ($option['optionName'] ?? ''));
        }
        sort($pairs);

        return implode('|', $pairs);
    }

    /** @param  array<int, array<string, string>>  $variantValues */
    private function buildOptionKeyFromVariantValues(array $variantValues): string
    {
        $pairs = [];
        foreach ($variantValues as $value) {
            $pairs[] = trim((string) ($value['variantTypeName'] ?? '')).':'.trim((string) ($value['variantValueName'] ?? ''));
        }
        sort($pairs);

        return implode('|', $pairs);
    }

    /** Ürün aktif mi — en az bir aktif varyant varsa true */
    public static function isProductActive(array $product): bool
    {
        foreach ($product['variants'] ?? [] as $variant) {
            if (! empty($variant['isActive'])) {
                return true;
            }
        }

        return false;
    }

  /** Müşterinin gördüğü satış fiyatı (İkas discountPrice veya tek fiyat sellPrice). */
    public static function variantSellPrice(array $variant): float
    {
        $sell = (float) ($variant['prices'][0]['sellPrice'] ?? 0);
        $discount = (float) ($variant['prices'][0]['discountPrice'] ?? 0);

        if ($discount > 0 && $discount < $sell) {
            return $discount;
        }

        return $sell;
    }

    /** Üstü çizili karşılaştırma fiyatı (İkas sellPrice, indirim varsa). */
    public static function variantDiscountPrice(array $variant): float
    {
        $sell = (float) ($variant['prices'][0]['sellPrice'] ?? 0);
        $discount = (float) ($variant['prices'][0]['discountPrice'] ?? 0);

        if ($discount > 0 && $discount < $sell) {
            return $sell;
        }

        return 0;
    }

    public static function variantStockCount(array $variant): int
    {
        return (int) ($variant['stocks'][0]['stockCount'] ?? 0);
    }

    public static function variantDisplayName(array $variant): string
    {
        $parts = [];
        foreach ($variant['variantValues'] ?? [] as $value) {
            $parts[] = (string) ($value['variantValueName'] ?? '');
        }

        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        return (string) ($variant['sku'] ?? 'Default');
    }

    public static function productMainImage(array $product): string
    {
        foreach ($product['variants'] ?? [] as $variant) {
            foreach ($variant['images'] ?? [] as $image) {
                if (! empty($image['isMain']) && ! empty($image['fileName'])) {
                    return self::buildImageUrl((string) $image['fileName']);
                }
            }
        }

        foreach ($product['variants'][0]['images'] ?? [] as $image) {
            if (! empty($image['fileName'])) {
                return self::buildImageUrl((string) $image['fileName']);
            }
        }

        return '';
    }

    public static function variantImage(array $variant, string $fallback = ''): string
    {
        foreach ($variant['images'] ?? [] as $image) {
            if (! empty($image['fileName'])) {
                return self::buildImageUrl((string) $image['fileName']);
            }
        }

        return $fallback;
    }

    public static function isSimpleProduct(array $product): bool
    {
        $variants = $product['variants'] ?? [];

        return count($variants) === 1 && empty($variants[0]['variantValues'] ?? []);
    }

    /**
     * Shopify uyumu: price = satış, compareAt = üstü çizili (yüksek).
     * İkas API: sellPrice = liste (yüksek), discountPrice = indirimli satış (düşük).
     *
     * @return array<string, float>
     */
    private static function buildIkasPriceInput(float $actualSell, float $comparison = 0, float $buyPrice = 0): array
    {
        $price = [];

        if ($comparison > 0 && $comparison > $actualSell && $actualSell > 0) {
            $price['sellPrice'] = $comparison;
            $price['discountPrice'] = $actualSell;
        } else {
            $price['sellPrice'] = $actualSell > 0 ? $actualSell : $comparison;
        }

        if ($buyPrice > 0) {
            $price['buyPrice'] = $buyPrice;
        }

        return $price;
    }

    private static function buildImageUrl(string $fileName): string
    {
        $store = rtrim((string) config('ikas.store_domain'), '/');

        return $store.'/image/'.$fileName;
    }
}
