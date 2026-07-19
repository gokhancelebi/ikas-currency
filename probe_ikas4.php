<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

foreach (['UpdateVariantPriceInput','ProductVariantPriceInput','SaveVariantPriceInput','VariantPriceItemInput'] as $type) {
    $r = $client->request(['query' => "query { __type(name:\"$type\") { inputFields { name type { name kind ofType { name } } } } }"]);
    if (!empty($r['data']['__type'])) {
        file_put_contents(__DIR__."/storage/app/ikas-sync/probe4-{$type}.json", json_encode($r, JSON_PRETTY_PRINT));
        echo "$type found\n";
    }
}

// test updateVariantPrices on probe product
$create = $client->request([
    'query' => 'mutation($input:CreateProductInput!){createProduct(input:$input){id variants{id}}}',
    'variables' => ['input' => [
        'name' => 'Price Probe '.time(), 'type' => 'PHYSICAL',
        'variants' => [['sku' => 'PP-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 100]]]],
    ]],
]);
$pid = $create['data']['createProduct']['id'] ?? null;
$vid = $create['data']['createProduct']['variants'][0]['id'] ?? null;
$priceList = $client->request(['query' => '{ listPriceList { id name } }']);
$plid = $priceList['data']['listPriceList'][0]['id'] ?? null;

$update = $client->request([
    'query' => 'mutation($input:UpdateVariantPricesInput!){updateVariantPrices(input:$input){isSuccess errorInputs{variantId productId}}}',
    'variables' => ['input' => [
        'priceListId' => $plid,
        'variantPriceInputs' => [[
            'deleted' => false,
            'productId' => $pid,
            'variantId' => $vid,
            'price' => ['sellPrice' => 120, 'discountPrice' => 99.9, 'buyPrice' => 50],
        ]],
    ]],
]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe4-updatePrices.json', json_encode($update, JSON_PRETTY_PRINT));
echo 'update: '.json_encode($update['errors'] ?? $update['data'])."\n";
if ($pid) {
    $client->request(['query' => 'mutation { deleteProductList(idList: ["'.$pid.'"]) }']);
}
