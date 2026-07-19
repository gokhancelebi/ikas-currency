<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

$cat = $c->request(['query' => 'mutation($input:CreateCategoryInput!){createCategory(input:$input){id name}}', 'variables' => ['input' => ['name' => 'ProbeCat'.time(), 'isAutomated' => false]]]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe7-cat.json', json_encode($cat, JSON_PRETTY_PRINT));
$catId = $cat['data']['createCategory']['id'] ?? null;

$prod = $c->request(['query' => 'mutation($input:CreateProductInput!){createProduct(input:$input){id}}', 'variables' => ['input' => [
    'name' => 'CatProd'.time(), 'type' => 'PHYSICAL',
    'variants' => [['sku' => 'CP-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 50]]]],
]]]);
$pid = $prod['data']['createProduct']['id'] ?? null;

if ($catId && $pid) {
    $upd = $c->request(['query' => 'mutation($input:UpdateProductInput!){updateProduct(input:$input){id categories{id name}}}', 'variables' => ['input' => [
        'id' => $pid,
        'categories' => [['name' => $cat['data']['createCategory']['name']]],
    ]]]);
    file_put_contents(__DIR__.'/storage/app/ikas-sync/probe7-upd.json', json_encode($upd, JSON_PRETTY_PRINT));
}

// variable product
$var = $c->request(['query' => 'mutation($input:CreateProductInput!){createProduct(input:$input){id variants{id sku variantValues{variantTypeName variantValueName}}}}', 'variables' => ['input' => [
    'name' => 'VarProd'.time(), 'type' => 'PHYSICAL',
    'variants' => [
        ['sku' => 'V1-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 89.9]], 'variantValues' => [['variantTypeName' => 'Renk', 'variantValueName' => 'Kirmizi']]],
        ['sku' => 'V2-'.time(), 'isActive' => true, 'prices' => [['sellPrice' => 91.9]], 'variantValues' => [['variantTypeName' => 'Renk', 'variantValueName' => 'Mavi']]],
    ],
]]]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe7-var.json', json_encode($var, JSON_PRETTY_PRINT));
$varId = $var['data']['createProduct']['id'] ?? null;

// tags
$tag = $c->request(['query' => 'mutation($input:CreateProductTagInput!){createProductTag(input:$input){id name}}', 'variables' => ['input' => ['name' => 'TAG-'.time()]]]);
file_put_contents(__DIR__.'/storage/app/ikas-sync/probe7-tag.json', json_encode($tag, JSON_PRETTY_PRINT));

if ($pid) $c->request(['query' => 'mutation { deleteProductList(idList: ["'.$pid.'"]) }']);
if ($varId) $c->request(['query' => 'mutation { deleteProductList(idList: ["'.$varId.'"]) }']);
if ($catId) $c->request(['query' => 'mutation { deleteCategoryList(idList: ["'.$catId.'"]) }']);
