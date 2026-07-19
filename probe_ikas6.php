<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

foreach (['UpdateVariantPricesResponse','UpdateVariantPricesResponseError','ProductVariantPriceInput'] as $t) {
    $r = $client->request(['query' => "query { __type(name:\"$t\") { kind fields { name } inputFields { name } } }"]);
    file_put_contents(__DIR__."/storage/app/ikas-sync/probe6-{$t}.json", json_encode($r, JSON_PRETTY_PRINT));
}

$create = $client->request([
    'query' => 'mutation($input:CreateProductInput!){createProduct(input:$input){id variants{id}}}',
    'variables' => ['input' => [
        'name' => 'P '.time(), 'type' => 'PHYSICAL',
        'variants' => [['sku' => 'P-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 100]]]],
    ]],
]);
$pid = $create['data']['createProduct']['id'];
$vid = $create['data']['createProduct']['variants'][0]['id'];

$update = $client->request([
    'query' => 'mutation($input:UpdateVariantPricesInput!){ updateVariantPrices(input:$input) { errors { errorCode } } }',
    'variables' => ['input' => [
        'priceListId' => null,
        'variantPriceInputs' => [[
            'deleted' => false, 'productId' => $pid, 'variantId' => $vid,
            'price' => ['sellPrice' => 120, 'discountPrice' => 99.9, 'buyPrice' => 50],
        ]],
    ]],
]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe6-update.json', json_encode($update, JSON_PRETTY_PRINT));

$get = $client->request(['query' => 'query($id:StringFilterInput){listProduct(id:$id,pagination:{page:1,limit:1}){data{variants{prices{sellPrice discountPrice buyPrice}}}}}', 'variables' => ['id' => ['eq' => $pid]]]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe6-get.json', json_encode($get, JSON_PRETTY_PRINT));

$client->request(['query' => 'mutation { deleteProductList(idList: ["'.$pid.'"]) }']);
