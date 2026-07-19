<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

$pl = $client->request(['query' => '{ listPriceList { id name } }']);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe5-pricelist.json', json_encode($pl, JSON_PRETTY_PRINT));

$create = $client->request([
    'query' => 'mutation($input:CreateProductInput!){createProduct(input:$input){id variants{id}}}',
    'variables' => ['input' => [
        'name' => 'Price Probe3 '.time(), 'type' => 'PHYSICAL',
        'variants' => [['sku' => 'PP3-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 100]]]],
    ]],
]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe5-create.json', json_encode($create, JSON_PRETTY_PRINT));
$pid = $create['data']['createProduct']['id'] ?? null;
$vid = $create['data']['createProduct']['variants'][0]['id'] ?? null;

if ($pid && $vid) {
    $update = $client->request([
        'query' => 'mutation($input:UpdateVariantPricesInput!){ updateVariantPrices(input:$input) { errors { errorCode message } } }',
        'variables' => ['input' => [
            'priceListId' => null,
            'variantPriceInputs' => [[
                'deleted' => false,
                'productId' => $pid,
                'variantId' => $vid,
                'price' => ['sellPrice' => 120, 'discountPrice' => 99.9, 'buyPrice' => 50],
            ]],
        ]],
    ]);
    file_put_contents(__DIR__.'/storage/app/ikas-sync/probe5-update.json', json_encode($update, JSON_PRETTY_PRINT));
    $client->request(['query' => 'mutation { deleteProductList(idList: ["'.$pid.'"]) }']);
}
