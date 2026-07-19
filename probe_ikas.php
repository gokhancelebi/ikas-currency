<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = new App\Lib\IkasSync\GraphqlClient(new App\Lib\IkasSync\AuthTokenService());

$queries = [
    'listProduct' => 'query { listProduct(pagination:{page:1,limit:2}) { count hasNext data { id name type variants { id sku isActive variantValues { variantTypeName variantValueName } prices { sellPrice discountPrice buyPrice } stocks { stockCount stockLocationId } } tags { id name } categories { id name } } } }',
    'createProductInput' => 'query { __type(name:"CreateProductInput") { inputFields { name type { name kind ofType { name kind ofType { name } } } } } }',
    'createCategoryInput' => 'query { __type(name:"CreateCategoryInput") { inputFields { name } } }',
    'variantInput' => 'query { __type(name:"CreateProductVariantInput") { inputFields { name type { name kind ofType { name } } } } }',
];

foreach ($queries as $name => $query) {
    $r = $client->request(['query' => $query]);
    file_put_contents(__DIR__."/storage/app/ikas-sync/probe-{$name}.json", json_encode($r, JSON_PRETTY_PRINT));
    echo $name.': '.(empty($r['errors']) ? 'OK' : json_encode($r['errors'][0]['message'] ?? $r['errors']))."\n";
}
