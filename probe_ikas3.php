<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

$queries = [
    'queries' => 'query { __schema { queryType { fields { name } } } }',
    'categoryInput' => 'query { __type(name:"ProductCategoryInput") { inputFields { name } } }',
    'tagInput' => 'query { __type(name:"ProductProductTagsInput") { inputFields { name } } }',
    'createTagInput' => 'query { __type(name:"CreateProductTagInput") { inputFields { name } } }',
    'variantPriceItem' => 'query { __type(name:"VariantPriceInput") { inputFields { name type { name kind ofType { name } } } } }',
];

foreach ($queries as $name => $query) {
    $r = $client->request(['query' => $query]);
    file_put_contents(__DIR__."/storage/app/ikas-sync/probe3-{$name}.json", json_encode($r, JSON_PRETTY_PRINT));
}

// test create simple product
$create = $client->request([
    'query' => 'mutation CreateProduct($input: CreateProductInput!) { createProduct(input: $input) { id name variants { id sku } } }',
    'variables' => [
        'input' => [
            'name' => 'Probe Simple '.time(),
            'type' => 'PHYSICAL',
            'variants' => [[
                'sku' => 'PROBE-'.time(),
                'isActive' => true,
                'prices' => [['sellPrice' => 120, 'discountPrice' => 99.9]],
            ]],
        ],
    ],
]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe3-create.json', json_encode($create, JSON_PRETTY_PRINT));
if (!empty($create['data']['createProduct']['id'])) {
    $client->request([
        'query' => 'mutation { deleteProductList(idList: ["'.$create['data']['createProduct']['id'].'"]) }',
    ]);
}
echo "create: ".(empty($create['errors']) ? 'OK' : json_encode($create['errors']))."\n";
